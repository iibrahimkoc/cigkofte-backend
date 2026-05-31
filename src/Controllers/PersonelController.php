<?php
/**
 * PersonelController - Personel (Kullanıcı) Yönetim Kontrolcüsü
 * ─────────────────────────────────────────────────────────────
 * Sistem yöneticilerinin personel tanımlamalarını yönetmesini sağlar.
 */

class PersonelController
{
    private Kullanici $kullaniciModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->kullaniciModel = new Kullanici();
        $this->authService = new AuthService();
    }

    /**
     * Personelleri Listele: GET /api/personel
     */
    public function list(): void
    {
        $this->authService->requireAdmin();
        
        $db = Database::getInstance()->getConnection();
        try {
            $stmt = $db->query("SELECT KullaniciID, Eposta, AdSoyad, Rol, Telefon FROM Kullanici WHERE Rol IN ('Admin', 'Personel') ORDER BY AdSoyad ASC");
            $users = $stmt->fetchAll();

            jsonResponse([
                'success' => true,
                'data' => $users
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Personel listesi alınırken hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Personel Detayı Getir: GET /api/personel/{id}
     */
    public function get(int $id): void
    {
        $this->authService->requireAdmin();

        $user = $this->kullaniciModel->find($id);
        if ($user && in_array($user['Rol'], ['Admin', 'Personel'])) {
            unset($user['Sifre']); // Güvenlik amacıyla şifreyi çıkar
            jsonResponse([
                'success' => true,
                'data' => $user
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Personel bulunamadı!'
            ], 404);
        }
    }

    /**
     * Personel Ekle: POST /api/personel
     */
    public function create(): void
    {
        $this->authService->requireAdmin();
        $data = getJsonBody();

        $eposta = trim($data['Eposta'] ?? $data['username'] ?? $data['email'] ?? '');
        $sifre = trim($data['Sifre'] ?? $data['password'] ?? '');
        $adSoyad = trim($data['AdSoyad'] ?? $data['full_name'] ?? '');
        $rol = trim($data['Rol'] ?? $data['role'] ?? 'Personel');
        $telefon = trim($data['Telefon'] ?? $data['phone'] ?? '');

        if (empty($eposta) || empty($sifre) || empty($adSoyad)) {
            jsonResponse([
                'success' => false,
                'message' => 'Eksik bilgi! Lütfen e-posta (Eposta), şifre (Sifre) ve ad soyad (AdSoyad) alanlarını doldurun.'
            ], 400);
        }

        $rol = ucfirst(strtolower($rol));
        if ($rol === 'Cashier' || $rol === 'Personel') {
            $rol = 'Personel';
        } elseif ($rol !== 'Admin') {
            jsonResponse([
                'success' => false,
                'message' => 'Geçersiz yetki (rol)! Sadece \'Admin\' veya \'Personel\' seçilebilir.'
            ], 400);
        }

        // E-posta benzersiz olmalı
        if ($this->kullaniciModel->findByEposta($eposta)) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu e-posta adresi zaten kullanımda!'
            ], 409);
        }

        try {
            $hashed = hashPassword($sifre);
            $id = $this->kullaniciModel->create([
                'Eposta' => $eposta,
                'Sifre' => $hashed,
                'AdSoyad' => $adSoyad,
                'Rol' => $rol,
                'Telefon' => $telefon ?: null
            ]);

            jsonResponse([
                'success' => true,
                'message' => 'Yeni personel başarıyla kaydedildi!',
                'data' => [
                    'KullaniciID' => $id,
                    'Eposta' => $eposta,
                    'AdSoyad' => $adSoyad,
                    'Rol' => $rol,
                    'Telefon' => $telefon
                ]
            ], 201);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Personel kaydedilirken hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Personel Bilgilerini Güncelle: PUT /api/personel/{id}
     */
    public function update(int $id): void
    {
        $this->authService->requireAdmin();

        $user = $this->kullaniciModel->find($id);
        if (!$user || !in_array($user['Rol'], ['Admin', 'Personel'])) {
            jsonResponse([
                'success' => false,
                'message' => 'Güncellenecek personel bulunamadı!'
            ], 404);
        }

        $data = getJsonBody();
        $eposta = trim($data['Eposta'] ?? $data['username'] ?? $data['email'] ?? '');
        $sifre = trim($data['Sifre'] ?? $data['password'] ?? '');
        $adSoyad = trim($data['AdSoyad'] ?? $data['full_name'] ?? '');
        $rol = trim($data['Rol'] ?? $data['role'] ?? '');
        $telefon = isset($data['Telefon']) ? trim($data['Telefon']) : (isset($data['phone']) ? trim($data['phone']) : null);

        $updateFields = [];
        if (!empty($eposta)) {
            $existing = $this->kullaniciModel->findByEposta($eposta);
            if ($existing && $existing['KullaniciID'] != $id) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor!'
                ], 409);
            }
            $updateFields['Eposta'] = $eposta;
        }

        if (!empty($sifre)) {
            $updateFields['Sifre'] = hashPassword($sifre);
        }

        if (!empty($adSoyad)) {
            $updateFields['AdSoyad'] = $adSoyad;
        }

        if (!empty($rol)) {
            $rol = ucfirst(strtolower($rol));
            if ($rol === 'Cashier' || $rol === 'Personel') {
                $rol = 'Personel';
            }
            if ($rol === 'Admin' || $rol === 'Personel') {
                $updateFields['Rol'] = $rol;
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Geçersiz yetki (rol)! Sadece \'Admin\' veya \'Personel\' seçilebilir.'
                ], 400);
            }
        }

        if ($telefon !== null) {
            $updateFields['Telefon'] = $telefon ?: null;
        }

        if (empty($updateFields)) {
            jsonResponse([
                'success' => false,
                'message' => 'Güncellenecek herhangi bir alan gönderilmedi!'
            ], 400);
        }

        try {
            $this->kullaniciModel->update($id, $updateFields);
            jsonResponse([
                'success' => true,
                'message' => 'Personel bilgileri başarıyla güncellendi!'
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Güncelleme sırasında hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Personel Sil: DELETE /api/personel/{id}
     */
    public function delete(int $id): void
    {
        $this->authService->requireAdmin();
        $currentUser = $this->authService->getCurrentUser();

        if ($id === (int)$currentUser['id']) {
            jsonResponse([
                'success' => false,
                'message' => 'Kendi hesabınızı silemezsiniz!'
            ], 400);
        }

        $user = $this->kullaniciModel->find($id);
        if (!$user || !in_array($user['Rol'], ['Admin', 'Personel'])) {
            jsonResponse([
                'success' => false,
                'message' => 'Silinecek personel bulunamadı!'
            ], 404);
        }

        try {
            $this->kullaniciModel->delete($id);
            jsonResponse([
                'success' => true,
                'message' => 'Personel başarıyla sistemden silindi!'
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Personel silinirken hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
}
