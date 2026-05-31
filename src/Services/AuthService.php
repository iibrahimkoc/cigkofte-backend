<?php
/**
 * AuthService - Kimlik Doğrulama Servisi
 * ────────────────────────────────────────
 * PHP Session tabanlı oturum yönetimi.
 * Admin ve Personel girişini kontrol eder.
 */

require_once __DIR__ . '/../Models/Kullanici.php';

class AuthService
{
    private Kullanici $kullaniciModel;

    public function __construct()
    {
        $this->kullaniciModel = new Kullanici();
    }

    /**
     * Kullanıcı girişi yap.
     *
     * @param string $eposta
     * @param string $sifre
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function login(string $eposta, string $sifre): array
    {
        $kullanici = $this->kullaniciModel->findByEposta($eposta);

        if (!$kullanici) {
            return ['success' => false, 'message' => 'E-posta veya şifre hatalı.', 'user' => null];
        }

        if (!password_verify($sifre, $kullanici['Sifre'])) {
            return ['success' => false, 'message' => 'E-posta veya şifre hatalı.', 'user' => null];
        }

        // Sadece Admin ve Personel giriş yapabilir
        if (!in_array($kullanici['Rol'], ['Admin', 'Personel'])) {
            return ['success' => false, 'message' => 'Bu panele erişim yetkiniz bulunmamaktadır.', 'user' => null];
        }

        // Session'ı başlat ve kullanıcı bilgilerini kaydet
        $this->startSession();

        $_SESSION['user_id']   = $kullanici['KullaniciID'];
        $_SESSION['user_name'] = $kullanici['AdSoyad'];
        $_SESSION['user_role'] = $kullanici['Rol'];
        $_SESSION['login_time'] = time();

        // Şifre hash'ini session'da saklamıyoruz (güvenlik)
        unset($kullanici['Sifre']);

        return ['success' => true, 'message' => 'Giriş başarılı.', 'user' => $kullanici];
    }

    /**
     * Çıkış yap — session'ı tamamen yok et.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->startSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Kullanıcının oturum açıp açmadığını kontrol et.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        $this->startSession();
        return isset($_SESSION['user_id']);
    }

    /**
     * Kullanıcının belirli bir role sahip olup olmadığını kontrol et.
     *
     * @param string $rol
     * @return bool
     */
    public function hasRole(string $rol): bool
    {
        $this->startSession();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $rol;
    }

    /**
     * Oturum açık değilse login sayfasına yönlendir.
     *
     * @return void
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Bu işlem için giriş yapılması gerekmektedir.'], 401);
            exit;
        }
    }

    /**
     * Admin rolü gerektir.
     *
     * @return void
     */
    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (!$this->hasRole('Admin')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
            exit;
        }
    }

    /**
     * Mevcut oturumdaki kullanıcı bilgilerini döndür.
     *
     * @return array|null
     */
    public function getCurrentUser(): ?array
    {
        $this->startSession();
        if (!$this->isLoggedIn()) {
            return null;
        }
        return [
            'id'   => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role'],
        ];
    }

    /**
     * Session'ı güvenli şekilde başlat (zaten başlatılmışsa tekrar başlatma).
     *
     * @return void
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = strtolower((string)getenv('SESSION_SECURE')) === 'true'
                || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $sameSite = getenv('SESSION_SAMESITE') ?: ($secure ? 'None' : 'Lax');

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => getenv('SESSION_COOKIE_DOMAIN') ?: '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ]);

            session_start();
        }
    }
}
