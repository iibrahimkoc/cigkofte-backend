<?php
/**
 * Entrypoint & Router - Çiğköfte API Giriş ve Yönlendirme Noktası
 * ────────────────────────────────────────────────────────────────
 * Tüm istekler bu dosya üzerinden geçerek ilgili controller'a yönlendirilir.
 */

// Geliştirme aşamasında hata gösterimi (Hatalar log dosyasına da yazılacaktır)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// CORS Başlıkları (Frontend bağlantısı için kritik önem taşır)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '')));
if ($origin !== '' && (empty($allowedOrigins) || in_array($origin, $allowedOrigins, true))) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
} elseif ($origin === '') {
    header('Access-Control-Allow-Origin: *');
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Preflight (ön-kontrol) OPTIONS isteklerini yanıtla ve çık
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Helpers / Yardımcı Fonksiyonlar
require_once __DIR__ . '/src/Helpers/helpers.php';

// Class Autoloader - Tüm sınıfları dinamik olarak yükler
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/src/Controllers/',
        __DIR__ . '/src/Models/',
        __DIR__ . '/src/Services/',
        __DIR__ . '/config/',
    ];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Gelen URL'i parse et
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Sağdaki gereksiz eğik çizgileri (slash) temizle
$path = rtrim($requestUri, '/');
if (empty($path)) {
    $path = '/';
}

// Rotaları Regex kalıplarıyla tanımla
// Format: Regex Pattern => [HTTP Metodu => [Controller Sınıfı, Metodu]]
$routes = [
    '#^/$#' => [
        'GET' => function () {
            jsonResponse([
                'success' => true,
                'message' => 'Çiğköfte API çalışıyor.'
            ]);
        }
    ],
    '#^/health$#' => [
        'GET' => function () {
            jsonResponse([
                'success' => true,
                'status' => 'ok'
            ]);
        }
    ],

    // ──────────────────────────────────────────
    // Kimlik Doğrulama (Auth)
    // ──────────────────────────────────────────
    '#/api/auth/login$#' => [
        'POST' => ['AuthController', 'login']
    ],
    '#/api/auth/logout$#' => [
        'POST' => ['AuthController', 'logout']
    ],
    '#/api/auth/me$#' => [
        'GET' => ['AuthController', 'me']
    ],

    // ──────────────────────────────────────────
    // Ürünler & Kategoriler (Menu)
    // ──────────────────────────────────────────
    '#/api/urunler$#' => [
        'GET' => ['UrunController', 'listAll'],
        'POST' => ['UrunController', 'createUrun']
    ],
    '#/api/urunler/(\d+)$#' => [
        'GET' => ['UrunController', 'getUrun'],
        'PUT' => ['UrunController', 'updateUrun'],
        'DELETE' => ['UrunController', 'deleteUrun']
    ],
    '#/api/kategoriler$#' => [
        'GET' => ['UrunController', 'listKategoriler'],
        'POST' => ['UrunController', 'createKategori']
    ],
    '#/api/kategoriler/(\d+)$#' => [
        'PUT' => ['UrunController', 'updateKategori'],
        'DELETE' => ['UrunController', 'deleteKategori']
    ],

    // ──────────────────────────────────────────
    // Sipariş İşlemleri
    // ──────────────────────────────────────────
    '#/api/siparis$#' => [
        'POST' => ['SiparisController', 'create'],
        'GET'  => ['SiparisController', 'list']
    ],
    '#/api/siparis/(\d+)$#' => [
        'GET' => ['SiparisController', 'get']
    ],
    '#/api/siparis/(\d+)/durum$#' => [
        'PUT' => ['SiparisController', 'updateDurum']
    ],

    // ──────────────────────────────────────────
    // Stok İşlemleri
    // ──────────────────────────────────────────
    '#/api/stok/malzemeler$#' => [
        'GET' => ['StokController', 'listMalzemeler'],
        'POST' => ['StokController', 'createMalzeme']
    ],
    '#/api/stok/malzemeler/(\d+)$#' => [
        'GET' => ['StokController', 'getMalzeme'],
        'PUT' => ['StokController', 'updateMalzeme'],
        'DELETE' => ['StokController', 'deleteMalzeme']
    ],
    '#/api/stok/partiler$#' => [
        'GET' => ['StokController', 'listPartiler']
    ],
    '#/api/stok/hareketler$#' => [
        'GET' => ['StokController', 'listHareketler']
    ],
    '#/api/stok/kritik$#' => [
        'GET' => ['StokController', 'listKritik']
    ],
    '#/api/stok/parti$#' => [
        'POST' => ['StokController', 'createParti']
    ],

    // ──────────────────────────────────────────
    // Reçete İşlemleri
    // ──────────────────────────────────────────
    '#/api/recete$#' => [
        'GET' => ['ReceteController', 'listAll'],
        'POST' => ['ReceteController', 'save']
    ],
    '#/api/recete/(\d+)$#' => [
        'GET' => ['ReceteController', 'get'],
        'DELETE' => ['ReceteController', 'delete']
    ],

    // ──────────────────────────────────────────
    // Personel İşlemleri
    // ──────────────────────────────────────────
    '#/api/personel$#' => [
        'GET' => ['PersonelController', 'list'],
        'POST' => ['PersonelController', 'create']
    ],
    '#/api/personel/(\d+)$#' => [
        'GET' => ['PersonelController', 'get'],
        'PUT' => ['PersonelController', 'update'],
        'DELETE' => ['PersonelController', 'delete']
    ],

    // ──────────────────────────────────────────
    // Raporlama
    // ──────────────────────────────────────────
    '#/api/raporlar$#' => [
        'GET' => ['RaporController', 'dashboard']
    ]
];

// Eşleşen rotayı ara
$matched = false;
foreach ($routes as $pattern => $methods) {
    if (preg_match($pattern, $path, $matches)) {
        $matched = true;
        
        if (isset($methods[$requestMethod])) {
            try {
                $routeInfo = $methods[$requestMethod];

                // İlk eşleşmeyi (tam metin) diziden çıkar, parametreleri bırak (örn. ID)
                array_shift($matches);

                if (is_callable($routeInfo)) {
                    call_user_func_array($routeInfo, $matches);
                } else {
                    $controllerName = $routeInfo[0];
                    $controllerMethod = $routeInfo[1];
                    $controller = new $controllerName();
                    // Parametreleri metoda argüman olarak geç ve çalıştır
                    call_user_func_array([$controller, $controllerMethod], $matches);
                }
            } catch (Exception $e) {
                error_log('[Router Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                jsonResponse([
                    'success' => false,
                    'message' => 'Sunucu kaynaklı bir hata oluştu.'
                ], 500);
            }
            exit;
        } else {
            // Method Not Allowed
            jsonResponse([
                'success' => false,
                'message' => 'Bu endpoint için ' . $requestMethod . ' metodu desteklenmemektedir.'
            ], 405);
        }
    }
}

// Rotaların hiçbiri eşleşmediyse
if (!$matched) {
    jsonResponse([
        'success' => false,
        'message' => 'Geçersiz API uç noktası (Endpoint).'
    ], 404);
}
