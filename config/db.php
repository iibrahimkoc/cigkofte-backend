<?php
/**
 * Database - PDO Bağlantı Sınıfı (Singleton)
 * ────────────────────────────────────────────
 * Tüm projede tek bir PDO örneği kullanılmasını sağlar.
 * 
 * Kullanım:
 *   $db = Database::getInstance()->getConnection();
 *   $stmt = $db->prepare("SELECT * FROM Malzeme WHERE AktifMi = 1");
 *   $stmt->execute();
 */

if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}

class Database
{
    /** @var Database|null Singleton örneği */
    private static ?Database $instance = null;

    /** @var PDO Aktif PDO bağlantısı */
    private PDO $pdo;

    /**
     * Yapıcı - dışarıdan erişimi engelle (Singleton)
     * PDO bağlantısını oluşturur ve yapılandırır.
     *
     * @throws PDOException Bağlantı başarısız olursa
     */
    private function __construct()
    {
        $config = self::resolveConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        if (!empty($config['ssl_ca_path'])) {
            $dsn .= ';sslmode=verify-ca;sslrootcert=' . $config['ssl_ca_path'];
        } elseif ($config['ssl']) {
            $dsn .= ';sslmode=required';
        }

        $options = [
            // Hata modunu exception olarak ayarla
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // Sonuçları assoc array olarak döndür
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // PDO'nun kendi prepared statement'larını kullanmasını engelle
            // Gerçek MySQL prepared statement'ları kullan (güvenlik)
            PDO::ATTR_EMULATE_PREPARES   => false,

            // Bağlantı süresiz beklemesini engelle (30 saniye timeout)
            PDO::ATTR_TIMEOUT            => 30,
        ];

        if ($config['ssl'] && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        if (!empty($config['ssl_ca_path']) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $config['ssl_ca_path'];
        }

        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        } catch (PDOException $e) {
            // Üretim ortamında hassas bilgileri logla, kullanıcıya genel mesaj göster
            error_log('[Database] Bağlantı hatası: ' . $e->getMessage());
            throw new PDOException('Veritabanı bağlantısı kurulamadı.');
        }
    }

    /**
     * Klonlamayı engelle (Singleton)
     */
    private function __clone() {}

    /**
     * Unserialize'ı engelle (Singleton)
     *
     * @throws \Exception Her zaman
     */
    public function __wakeup()
    {
        throw new \Exception('Singleton sınıfı unserialize edilemez.');
    }

    /**
     * Singleton örneğini döndürür.
     * İlk çağrıda bağlantı oluşturulur, sonraki çağrılarda aynı örnek döner.
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Aktif PDO bağlantısını döndürür.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    // ─────────────────────────────────────
    // Transaction Yardımcı Metotları
    // ─────────────────────────────────────

    /**
     * Yeni bir transaction başlatır.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Aktif transaction'ı onaylar (commit).
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Aktif transaction'ı geri alır (rollback).
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Render/Aiven gibi ortamlarda env değişkenlerinden, yerelde varsa db_config.php'den okur.
     */
    private static function resolveConfig(): array
    {
        $uri = getenv('MYSQL_URI') ?: getenv('DATABASE_URL') ?: '';

        if ($uri !== '') {
            $parts = parse_url($uri);
            if ($parts === false || empty($parts['host'])) {
                throw new PDOException('MYSQL_URI veya DATABASE_URL geçersiz.');
            }

            parse_str($parts['query'] ?? '', $query);

            return [
                'host' => $parts['host'],
                'port' => $parts['port'] ?? 3306,
                'name' => ltrim($parts['path'] ?? '/defaultdb', '/') ?: 'defaultdb',
                'user' => rawurldecode($parts['user'] ?? ''),
                'pass' => rawurldecode($parts['pass'] ?? ''),
                'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
                'ssl' => !empty($query['ssl-mode']) || !empty($query['sslmode']) || (strtolower((string)getenv('DB_SSL')) === 'true'),
                'ssl_ca_path' => getenv('DB_SSL_CA_PATH') ?: null,
            ];
        }

        return [
            'host' => getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1'),
            'port' => getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306),
            'name' => getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'defaultdb'),
            'user' => getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : ''),
            'pass' => getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : ''),
            'charset' => getenv('DB_CHARSET') ?: (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
            'ssl' => strtolower((string)getenv('DB_SSL')) === 'true',
            'ssl_ca_path' => getenv('DB_SSL_CA_PATH') ?: null,
        ];
    }
}
