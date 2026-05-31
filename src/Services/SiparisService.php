<?php
/**
 * SiparisService - Sipariş Yönetim Servisi
 * ──────────────────────────────────────────
 * Sipariş oluşturma, durum güncelleme ve stok entegrasyonu.
 */

require_once __DIR__ . '/../Models/Siparis.php';
require_once __DIR__ . '/../Models/SiparisDetay.php';
require_once __DIR__ . '/../Models/SiparisDetaySecenek.php';
require_once __DIR__ . '/../Models/UrunBoyut.php';
require_once __DIR__ . '/../Models/Secenek.php';
require_once __DIR__ . '/StokService.php';
require_once __DIR__ . '/../../config/db.php';

class SiparisService
{
    private Siparis $siparisModel;
    private SiparisDetay $detayModel;
    private SiparisDetaySecenek $detaySecenekModel;
    private UrunBoyut $boyutModel;
    private Secenek $secenekModel;
    private StokService $stokService;

    public function __construct()
    {
        $this->siparisModel      = new Siparis();
        $this->detayModel        = new SiparisDetay();
        $this->detaySecenekModel = new SiparisDetaySecenek();
        $this->boyutModel        = new UrunBoyut();
        $this->secenekModel      = new Secenek();
        $this->stokService       = new StokService();
    }

    /**
     * Yeni sipariş oluştur ve stokları düş.
     *
     * @param array $siparisData    ['KullaniciID', 'TeslimatAdresi', 'Not']
     * @param array $kalemler       [['BoyutID' => int, 'Adet' => int, 'SecenekIDs' => [int, ...]], ...]
     * @return array
     */
    public function siparisOlustur(array $siparisData, array $kalemler): array
    {
        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $toplamTutar = 0;

            // Her kalem için fiyat hesapla
            $hesaplananKalemler = [];
            foreach ($kalemler as $kalem) {
                $boyut = $this->boyutModel->find($kalem['BoyutID']);
                if (!$boyut) {
                    $db->rollback();
                    return ['success' => false, 'message' => "Geçersiz ürün boyutu: {$kalem['BoyutID']}"];
                }

                $birimFiyat = $boyut['Fiyat'];
                $secenekIds = $kalem['SecenekIDs'] ?? [];

                // Ek seçenek fiyatlarını ekle
                foreach ($secenekIds as $secenekId) {
                    $secenek = $this->secenekModel->find($secenekId);
                    if ($secenek) {
                        $birimFiyat += $secenek['EkFiyat'];
                    }
                }

                $araToplam = $birimFiyat * $kalem['Adet'];
                $toplamTutar += $araToplam;

                $hesaplananKalemler[] = [
                    'BoyutID'     => $kalem['BoyutID'],
                    'Adet'        => $kalem['Adet'],
                    'BirimFiyat'  => $birimFiyat,
                    'AraToplam'   => $araToplam,
                    'SecenekIDs'  => $secenekIds,
                ];
            }

            // Sipariş ana kaydını oluştur
            $siparisId = $this->siparisModel->create([
                'KullaniciID'    => $siparisData['KullaniciID'],
                'ToplamTutar'    => $toplamTutar,
                'TeslimatAdresi' => $siparisData['TeslimatAdresi'],
                'Not'            => $siparisData['Not'] ?? null,
            ]);

            // Her kalemi kaydet ve stok düş
            foreach ($hesaplananKalemler as $kalem) {
                // Sipariş detayı oluştur
                $detayId = $this->detayModel->create([
                    'SiparisID'  => $siparisId,
                    'BoyutID'    => $kalem['BoyutID'],
                    'Adet'       => $kalem['Adet'],
                    'BirimFiyat' => $kalem['BirimFiyat'],
                    'AraToplam'  => $kalem['AraToplam'],
                ]);

                // Seçenekleri kaydet
                foreach ($kalem['SecenekIDs'] as $secenekId) {
                    $this->detaySecenekModel->create([
                        'SiparisDetayID' => $detayId,
                        'SecenekID'      => $secenekId,
                    ]);
                }

                // FEFO ile stok düş
                $stokResult = $this->stokService->siparisIcinStokDus(
                    $kalem['BoyutID'],
                    $kalem['Adet'],
                    $kalem['SecenekIDs'],
                    $siparisId
                );

                if (!$stokResult['success']) {
                    $db->rollback();
                    return $stokResult;
                }
            }

            $db->commit();

            return [
                'success'   => true,
                'message'   => 'Sipariş başarıyla oluşturuldu.',
                'siparisId' => $siparisId,
                'toplam'    => $toplamTutar,
            ];
        } catch (\Exception $e) {
            $db->rollback();
            error_log('[SiparisService] Sipariş oluşturma hatası: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Sipariş oluşturulurken bir hata oluştu.'];
        }
    }

    /**
     * Sipariş durumunu güncelle.
     *
     * @param int    $siparisId
     * @param string $yeniDurum
     * @return array
     */
    public function durumGuncelle(int $siparisId, string $yeniDurum): array
    {
        $gecerliDurumlar = ['Hazirlaniyor', 'Yolda', 'TeslimEdildi', 'IptalEdildi'];

        if (!in_array($yeniDurum, $gecerliDurumlar)) {
            return ['success' => false, 'message' => 'Geçersiz sipariş durumu.'];
        }

        $result = $this->siparisModel->durumGuncelle($siparisId, $yeniDurum);

        return [
            'success' => $result,
            'message' => $result ? 'Durum güncellendi.' : 'Durum güncellenemedi.',
        ];
    }

    /**
     * Sipariş detaylarını getir (kalemler ve seçeneklerle birlikte).
     *
     * @param int $siparisId
     * @return array|null
     */
    public function getSiparisDetay(int $siparisId): ?array
    {
        $siparis = $this->siparisModel->find($siparisId);
        if (!$siparis) return null;

        $kalemler = $this->detayModel->findBySiparis($siparisId);

        foreach ($kalemler as &$kalem) {
            $kalem['Secenekler'] = $this->detaySecenekModel->findByDetay($kalem['SiparisDetayID']);
        }

        $siparis['Kalemler'] = $kalemler;
        return $siparis;
    }
}
