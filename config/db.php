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

require_once __DIR__ . '/db_config.php';

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
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

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

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
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
}
