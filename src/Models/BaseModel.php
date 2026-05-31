<?php
/**
 * BaseModel - Tüm modellerin türeyeceği temel sınıf
 * ──────────────────────────────────────────────────
 * Ortak CRUD işlemlerini içerir.
 * Alt sınıflar $table ve $primaryKey değerlerini override eder.
 */

require_once __DIR__ . '/../../config/db.php';

abstract class BaseModel
{
    /** @var PDO Veritabanı bağlantısı */
    protected PDO $db;

    /** @var string Tablo adı — alt sınıflar override etmeli */
    protected string $table = '';

    /** @var string Birincil anahtar sütunu */
    protected string $primaryKey = '';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Birincil anahtara göre tek kayıt getir.
     *
     * @param int $id
     * @return array|false
     */
    public function find(int $id): array|false
    {
        $sql = "SELECT * FROM {$this->quoteIdentifier($this->table)} WHERE {$this->quoteIdentifier($this->primaryKey)} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Tablodaki tüm kayıtları getir.
     *
     * @return array
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->quoteIdentifier($this->table)}";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Belirli koşullara göre kayıtları getir.
     *
     * @param array $conditions ['sütun' => değer] formatında
     * @return array
     */
    public function findBy(array $conditions): array
    {
        $clauses = [];
        $params  = [];

        foreach ($conditions as $column => $value) {
            $clauses[]           = "{$this->quoteIdentifier($column)} = :{$column}";
            $params[":{$column}"] = $value;
        }

        $whereClause = implode(' AND ', $clauses);
        $sql = "SELECT * FROM {$this->quoteIdentifier($this->table)} WHERE {$whereClause}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Yeni kayıt ekle.
     *
     * @param array $data ['sütun' => değer] formatında
     * @return int Eklenen kaydın ID'si
     */
    public function create(array $data): int
    {
        $columns      = implode(', ', array_map([$this, 'quoteIdentifier'], array_keys($data)));
        $placeholders = implode(', ', array_map(fn($col) => ":{$col}", array_keys($data)));

        $sql = "INSERT INTO {$this->quoteIdentifier($this->table)} ({$columns}) VALUES ({$placeholders})";

        $params = [];
        foreach ($data as $col => $val) {
            $params[":{$col}"] = $val;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Mevcut kaydı güncelle.
     *
     * @param int   $id   Güncellenecek kaydın ID'si
     * @param array $data ['sütun' => değer] formatında
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params     = [':id' => $id];

        foreach ($data as $col => $val) {
            $setClauses[]      = "{$this->quoteIdentifier($col)} = :{$col}";
            $params[":{$col}"] = $val;
        }

        $setString = implode(', ', $setClauses);
        $sql = "UPDATE {$this->quoteIdentifier($this->table)} SET {$setString} WHERE {$this->quoteIdentifier($this->primaryKey)} = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Kaydı sil.
     *
     * @param int $id Silinecek kaydın ID'si
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->quoteIdentifier($this->table)} WHERE {$this->quoteIdentifier($this->primaryKey)} = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
