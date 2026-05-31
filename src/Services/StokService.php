<?php
/**
 * StokService - Stok Yönetim Servisi
 * ────────────────────────────────────
 * FEFO (First Expired, First Out) mantığıyla stok düşme,
 * stok giriş, lot takibi ve stok hareket loglama işlemlerini yönetir.
 */

require_once __DIR__ . '/../Models/Malzeme.php';
require_once __DIR__ . '/../Models/MalzemeParti.php';
require_once __DIR__ . '/../Models/StokHareket.php';
require_once __DIR__ . '/../Models/Recete.php';
require_once __DIR__ . '/../Models/SecenekMalzemeKullanim.php';
require_once __DIR__ . '/../../config/db.php';

class StokService
{
    private Malzeme $malzemeModel;
    private MalzemeParti $partiModel;
    private StokHareket $stokHareketModel;
    private Recete $receteModel;
    private SecenekMalzemeKullanim $secenekMalzModel;

    public function __construct()
    {
        $this->malzemeModel     = new Malzeme();
        $this->partiModel       = new MalzemeParti();
        $this->stokHareketModel = new StokHareket();
        $this->receteModel      = new Recete();
        $this->secenekMalzModel = new SecenekMalzemeKullanim();
    }

    /**
     * Sipariş kalemine göre stok düş.
     * 1. Reçeteden temel malzemeleri al (boyuta göre)
     * 2. Seçilen ek seçeneklerin malzemelerini al
     * 3. Her malzeme için FEFO ile lot bazlı stok düş
     *
     * @param int   $boyutId     Sipariş edilen ürün boyutu
     * @param int   $adet        Sipariş adedi
     * @param array $secenekIds  Seçilen seçenek ID'leri
     * @param int   $siparisId   Sipariş ID (log için)
     * @return array ['success' => bool, 'message' => string, 'details' => array]
     */
    public function siparisIcinStokDus(int $boyutId, int $adet, array $secenekIds, int $siparisId): array
    {
        $db = Database::getInstance();
        $malzemeIhtiyac = []; // [MalzemeID => toplam miktar]

        // 1. Reçeteden temel malzemeleri topla
        $receteKalemleri = $this->receteModel->findByBoyut($boyutId);
        foreach ($receteKalemleri as $kalem) {
            $malzemeId = $kalem['MalzemeID'];
            $miktar    = $kalem['KullanilanMiktar'] * $adet;

            if (isset($malzemeIhtiyac[$malzemeId])) {
                $malzemeIhtiyac[$malzemeId] += $miktar;
            } else {
                $malzemeIhtiyac[$malzemeId] = $miktar;
            }
        }

        // 2. Ek seçeneklerin malzeme ihtiyaçlarını topla
        foreach ($secenekIds as $secenekId) {
            $secenekMalzemeler = $this->secenekMalzModel->findBySecenek($secenekId);
            foreach ($secenekMalzemeler as $sm) {
                $malzemeId = $sm['MalzemeID'];
                $miktar    = $sm['KullanilanMiktar'] * $adet;

                if (isset($malzemeIhtiyac[$malzemeId])) {
                    $malzemeIhtiyac[$malzemeId] += $miktar;
                } else {
                    $malzemeIhtiyac[$malzemeId] = $miktar;
                }
            }
        }

        // 3. Stok yeterliliğini kontrol et
        foreach ($malzemeIhtiyac as $malzemeId => $gerekliMiktar) {
            $malzeme = $this->malzemeModel->find($malzemeId);
            if (!$malzeme || $malzeme['ToplamStok'] < $gerekliMiktar) {
                $ad = $malzeme ? $malzeme['Ad'] : "ID:{$malzemeId}";
                return [
                    'success' => false,
                    'message' => "Yetersiz stok: {$ad} - Gereken: {$gerekliMiktar}, Mevcut: " . ($malzeme['ToplamStok'] ?? 0),
                    'details' => []
                ];
            }
        }

        // 4. Transaction içinde FEFO ile stok düş
        try {
            $db->beginTransaction();
            $hareketDetaylar = [];

            foreach ($malzemeIhtiyac as $malzemeId => $gerekliMiktar) {
                $result = $this->fefoStokDus($malzemeId, $gerekliMiktar, $siparisId);
                if (!$result['success']) {
                    $db->rollback();
                    return $result;
                }
                $hareketDetaylar = array_merge($hareketDetaylar, $result['details']);
            }

            $db->commit();

            return [
                'success' => true,
                'message' => 'Stok başarıyla düşüldü.',
                'details' => $hareketDetaylar
            ];
        } catch (\Exception $e) {
            $db->rollback();
            error_log('[StokService] Stok düşme hatası: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Stok düşme sırasında bir hata oluştu.',
                'details' => []
            ];
        }
    }

    /**
     * FEFO mantığıyla tek bir malzemenin stokunu lot bazlı düş.
     * Son kullanma tarihi en yakın olan partiden başlayarak düşer.
     *
     * @param int    $malzemeId
     * @param float  $gerekliMiktar
     * @param int    $siparisId
     * @return array
     */
    private function fefoStokDus(int $malzemeId, float $gerekliMiktar, int $siparisId): array
    {
        $partiler = $this->partiModel->findByMalzemeFEFO($malzemeId);
        $kalanIhtiyac = $gerekliMiktar;
        $detaylar = [];

        foreach ($partiler as $parti) {
            if ($kalanIhtiyac <= 0) break;

            $partidenDusulecek = min($kalanIhtiyac, $parti['KalanMiktar']);
            $yeniKalan = $parti['KalanMiktar'] - $partidenDusulecek;

            // Parti kalan miktarını güncelle
            $this->partiModel->kalanMiktarGuncelle($parti['PartiID'], $yeniKalan);

            // Stok hareket logu oluştur
            $this->stokHareketModel->create([
                'MalzemeID'   => $malzemeId,
                'PartiID'     => $parti['PartiID'],
                'HareketTuru' => 'Cikis',
                'Miktar'      => $partidenDusulecek,
                'Aciklama'    => "Sipariş #{$siparisId} için stok çıkışı (Lot: {$parti['LotNo']})"
            ]);

            $detaylar[] = [
                'PartiID'     => $parti['PartiID'],
                'LotNo'       => $parti['LotNo'],
                'Dusuldu'     => $partidenDusulecek,
                'PartiKalan'  => $yeniKalan,
                'SKT'         => $parti['SonKullanmaTarihi']
            ];

            $kalanIhtiyac -= $partidenDusulecek;
        }

        // Hâlâ karşılanamayan ihtiyaç varsa hata
        if ($kalanIhtiyac > 0) {
            return [
                'success' => false,
                'message' => "Lot bazlı stok yetersiz: MalzemeID={$malzemeId}, Eksik={$kalanIhtiyac}",
                'details' => []
            ];
        }

        // Malzeme toplam stokunu güncelle
        $this->malzemeModel->stokGuncelle($malzemeId, -$gerekliMiktar);

        return ['success' => true, 'message' => 'OK', 'details' => $detaylar];
    }

    /**
     * Yeni parti (lot) girişi yap ve stok artır.
     *
     * @param array $partiData ['MalzemeID', 'LotNo', 'Miktar', 'AlimTarihi', 'SonKullanmaTarihi']
     * @return array
     */
    public function partiGirisi(array $partiData): array
    {
        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Parti kaydı oluştur
            $partiData['KalanMiktar'] = $partiData['Miktar'];
            $partiId = $this->partiModel->create($partiData);

            // Malzeme toplam stokunu artır
            $this->malzemeModel->stokGuncelle($partiData['MalzemeID'], $partiData['Miktar']);

            // Stok hareket logu
            $this->stokHareketModel->create([
                'MalzemeID'   => $partiData['MalzemeID'],
                'PartiID'     => $partiId,
                'HareketTuru' => 'Giris',
                'Miktar'      => $partiData['Miktar'],
                'Aciklama'    => "Yeni parti girişi (Lot: {$partiData['LotNo']})"
            ]);

            $db->commit();

            return ['success' => true, 'message' => 'Parti girişi başarılı.', 'partiId' => $partiId];
        } catch (\Exception $e) {
            $db->rollback();
            error_log('[StokService] Parti giriş hatası: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Parti girişi sırasında bir hata oluştu.'];
        }
    }

    /**
     * Kritik stok seviyesinin altındaki malzemeleri getir.
     *
     * @return array
     */
    public function getKritikStokUyarilari(): array
    {
        return $this->malzemeModel->findKritikStokAlti();
    }
}
