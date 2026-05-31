<?php
/**
 * SiparisController - Sipariş İşlemleri Kontrolcüsü
 * ──────────────────────────────────────────────────
 * Yeni sipariş oluşturma, listeleme ve durum güncellemelerini yönetir.
 */

class SiparisController
{
    private SiparisService $siparisService;
    private Siparis $siparisModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->siparisService = new SiparisService();
        $this->siparisModel = new Siparis();
        $this->authService = new AuthService();
    }

    /**
     * Yeni Sipariş Oluştur: POST /api/siparis
     */
    public function create(): void
    {
        // Oturum kontrolü
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $data = getJsonBody();
        $kalemler = $data['Kalemler'] ?? [];

        if (empty($kalemler)) {
            jsonResponse([
                'success' => false,
                'message' => 'Sipariş kalemi (ürün ve adet) belirtilmelidir.'
            ], 400);
        }

        $currentUser = $this->authService->getCurrentUser();
        
        $siparisData = [
            'KullaniciID'    => $data['KullaniciID'] ?? $currentUser['id'],
            'TeslimatAdresi' => $data['TeslimatAdresi'] ?? 'Kasa Satışı',
            'Not'            => $data['Not'] ?? null
        ];

        $result = $this->siparisService->siparisOlustur($siparisData, $kalemler);

        if ($result['success']) {
            jsonResponse($result, 201);
        } else {
            jsonResponse($result, 400);
        }
    }

    /**
     * Sipariş Detayı Getir: GET /api/siparis/{id}
     */
    public function get(int $id): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $siparis = $this->siparisService->getSiparisDetay($id);

        if ($siparis) {
            jsonResponse([
                'success' => true,
                'siparis' => $siparis
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Sipariş bulunamadı.'
            ], 404);
        }
    }

    /**
     * Sipariş Durumunu Güncelle: PUT /api/siparis/{id}/durum
     */
    public function updateDurum(int $id): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $data = getJsonBody();
        $yeniDurum = $data['Durum'] ?? '';

        if (empty($yeniDurum)) {
            jsonResponse([
                'success' => false,
                'message' => 'Yeni sipariş durumu (Durum) belirtilmelidir.'
            ], 400);
        }

        $result = $this->siparisService->durumGuncelle($id, $yeniDurum);

        if ($result['success']) {
            jsonResponse($result);
        } else {
            jsonResponse($result, 400);
        }
    }

    /**
     * Siparişleri Listele: GET /api/siparis
     */
    public function list(): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        // Opsiyonel olarak duruma göre filtreleme
        $durum = getQuery('durum');
        
        if (!empty($durum)) {
            $siparisler = $this->siparisModel->findByDurum($durum);
        } else {
            $siparisler = $this->siparisModel->findAllWithCustomer();
        }

        jsonResponse([
            'success'    => true,
            'siparisler' => $siparisler
        ]);
    }
}
