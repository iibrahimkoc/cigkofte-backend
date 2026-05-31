<?php
/**
 * StokController - Stok ve Malzeme İşlemleri Kontrolcüsü
 * ──────────────────────────────────────────────────────
 * Malzeme stok durumunu, partileri, stok hareketlerini ve girişlerini yönetir.
 */

class StokController
{
    private StokService $stokService;
    private Malzeme $malzemeModel;
    private MalzemeParti $partiModel;
    private StokHareket $stokHareketModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->stokService = new StokService();
        $this->malzemeModel = new Malzeme();
        $this->partiModel = new MalzemeParti();
        $this->stokHareketModel = new StokHareket();
        $this->authService = new AuthService();
    }

    /**
     * Malzemeleri Listele: GET /api/stok/malzemeler
     */
    public function listMalzemeler(): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $malzemeler = $this->malzemeModel->findAll();
        jsonResponse([
            'success'    => true,
            'malzemeler' => $malzemeler
        ]);
    }

    /**
     * Parti/Lot Girişlerini Listele: GET /api/stok/partiler
     */
    public function listPartiler(): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $malzemeId = getQuery('malzeme_id');
        
        if (!empty($malzemeId)) {
            $partiler = $this->partiModel->findBy(['MalzemeID' => (int)$malzemeId]);
        } else {
            $partiler = $this->partiModel->findAllWithMalzeme();
        }

        jsonResponse([
            'success'  => true,
            'partiler' => $partiler
        ]);
    }

    /**
     * Stok Hareket Geçmişini Listele: GET /api/stok/hareketler
     */
    public function listHareketler(): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $malzemeId = getQuery('malzeme_id');
        
        if (!empty($malzemeId)) {
            $hareketler = $this->stokHareketModel->findByMalzeme((int)$malzemeId);
        } else {
            $hareketler = $this->stokHareketModel->findAllWithDetails();
        }

        jsonResponse([
            'success'    => true,
            'hareketler' => $hareketler
        ]);
    }

    /**
     * Kritik Seviyenin Altındaki Stokları Listele: GET /api/stok/kritik
     */
    public function listKritik(): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $kritik = $this->stokService->getKritikStokUyarilari();
        jsonResponse([
            'success'           => true,
            'kritik_malzemeler' => $kritik
        ]);
    }

    /**
     * Yeni Parti Girişi (Stok Girişi) Yap: POST /api/stok/parti
     * Sadece Admin yetkisi gerektirir.
     */
    public function createParti(): void
    {
        // Admin kontrolü (requireAdmin otomatik 403 döner)
        $this->authService->requireAdmin();

        $data = getJsonBody();

        // Girdi Kontrolleri
        $requiredFields = ['MalzemeID', 'LotNo', 'Miktar', 'AlimTarihi', 'SonKullanmaTarihi'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                jsonResponse([
                    'success' => false,
                    'message' => "{$field} alanı zorunludur."
                ], 400);
            }
        }

        $result = $this->stokService->partiGirisi([
            'MalzemeID'         => (int)$data['MalzemeID'],
            'LotNo'             => $data['LotNo'],
            'Miktar'            => (float)$data['Miktar'],
            'AlimTarihi'        => $data['AlimTarihi'],
            'SonKullanmaTarihi' => $data['SonKullanmaTarihi']
        ]);

        if ($result['success']) {
            jsonResponse($result, 201);
        } else {
            jsonResponse($result, 400);
        }
    }

    /**
     * Malzeme Detayını Getir: GET /api/stok/malzemeler/{id}
     */
    public function getMalzeme(int $id): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $malzeme = $this->malzemeModel->find($id);
        if ($malzeme && $malzeme['AktifMi'] == 1) {
            jsonResponse([
                'success' => true,
                'malzeme' => $malzeme
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Malzeme bulunamadı!'
            ], 404);
        }
    }

    /**
     * Malzeme Ekle: POST /api/stok/malzemeler
     */
    public function createMalzeme(): void
    {
        $this->authService->requireAdmin();
        $data = getJsonBody();

        $ad = trim($data['Ad'] ?? $data['name'] ?? '');
        $birim = trim($data['Birim'] ?? $data['unit'] ?? 'gram');
        $toplamStok = (float)($data['ToplamStok'] ?? $data['stock_quantity'] ?? 0.0);
        $kritikStok = (float)($data['KritikStok'] ?? $data['critical_limit'] ?? 0.0);

        if (empty($ad) || empty($birim)) {
            jsonResponse([
                'success' => false,
                'message' => 'Eksik bilgi! Lütfen malzeme adı ve birimini belirtin.'
            ], 400);
        }

        try {
            $id = $this->malzemeModel->create([
                'Ad' => $ad,
                'Birim' => $birim,
                'ToplamStok' => $toplamStok,
                'KritikStok' => $kritikStok,
                'AktifMi' => 1
            ]);
            jsonResponse([
                'success' => true,
                'message' => 'Yeni malzeme başarıyla envantere eklendi!',
                'data' => [
                    'MalzemeID' => $id,
                    'Ad' => $ad,
                    'Birim' => $birim,
                    'ToplamStok' => $toplamStok,
                    'KritikStok' => $kritikStok
                ]
            ], 201);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu isimde bir malzeme envanterde zaten mevcut veya bir hata oluştu.'
            ], 409);
        }
    }

    /**
     * Malzeme Güncelle: PUT /api/stok/malzemeler/{id}
     */
    public function updateMalzeme(int $id): void
    {
        $this->authService->requireAdmin();

        $malzeme = $this->malzemeModel->find($id);
        if (!$malzeme) {
            jsonResponse([
                'success' => false,
                'message' => 'Güncellenecek malzeme bulunamadı!'
            ], 404);
        }

        $data = getJsonBody();
        $ad = trim($data['Ad'] ?? $data['name'] ?? '');
        $birim = trim($data['Birim'] ?? $data['unit'] ?? '');
        $toplamStok = isset($data['ToplamStok']) ? (float)$data['ToplamStok'] : (isset($data['stock_quantity']) ? (float)$data['stock_quantity'] : null);
        $kritikStok = isset($data['KritikStok']) ? (float)$data['KritikStok'] : (isset($data['critical_limit']) ? (float)$data['critical_limit'] : null);

        $updateFields = [];
        if (!empty($ad)) {
            $updateFields['Ad'] = $ad;
        }
        if (!empty($birim)) {
            $updateFields['Birim'] = $birim;
        }
        if ($toplamStok !== null) {
            $updateFields['ToplamStok'] = $toplamStok;
        }
        if ($kritikStok !== null) {
            $updateFields['KritikStok'] = $kritikStok;
        }

        if (empty($updateFields)) {
            jsonResponse([
                'success' => false,
                'message' => 'Güncellenecek herhangi bir alan belirtilmedi!'
            ], 400);
        }

        try {
            $this->malzemeModel->update($id, $updateFields);
            jsonResponse([
                'success' => true,
                'message' => 'Malzeme başarıyla güncellendi!'
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Güncelleme başarısız! Bu isimde başka bir malzeme olabilir.'
            ], 409);
        }
    }

    /**
     * Malzeme Sil (Pasifleştir): DELETE /api/stok/malzemeler/{id}
     */
    public function deleteMalzeme(int $id): void
    {
        $this->authService->requireAdmin();

        $malzeme = $this->malzemeModel->find($id);
        if (!$malzeme) {
            jsonResponse([
                'success' => false,
                'message' => 'Silinecek malzeme bulunamadı!'
            ], 404);
        }

        $result = $this->malzemeModel->update($id, ['AktifMi' => 0]);

        if ($result) {
            jsonResponse([
                'success' => true,
                'message' => 'Malzeme başarıyla pasifleştirildi!'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Malzeme silinemedi.'
            ], 500);
        }
    }
}
