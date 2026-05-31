<?php
/**
 * AuthController - Kimlik Doğrulama Kontrolcüsü
 * ────────────────────────────────────────────────
 * Giriş, çıkış ve aktif oturum bilgilerini yönetir.
 */

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Giriş Yap: POST /api/auth/login
     */
    public function login(): void
    {
        $data = getJsonBody();
        $eposta = $data['Eposta'] ?? '';
        $sifre = $data['Sifre'] ?? '';

        if (empty($eposta) || empty($sifre)) {
            jsonResponse([
                'success' => false,
                'message' => 'E-posta ve şifre alanları zorunludur.'
            ], 400);
        }

        $result = $this->authService->login($eposta, $sifre);
        
        if ($result['success']) {
            jsonResponse($result);
        } else {
            jsonResponse($result, 401);
        }
    }

    /**
     * Çıkış Yap: POST /api/auth/logout
     */
    public function logout(): void
    {
        $this->authService->logout();
        jsonResponse([
            'success' => true,
            'message' => 'Başarıyla çıkış yapıldı.'
        ]);
    }

    /**
     * Aktif Oturumu Getir: GET /api/auth/me
     */
    public function me(): void
    {
        $user = $this->authService->getCurrentUser();
        
        if ($user) {
            jsonResponse([
                'success' => true,
                'user' => $user
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Oturum bulunamadı veya süresi dolmuş.'
            ], 401);
        }
    }
}
