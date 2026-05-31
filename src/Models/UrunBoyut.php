<?php
require_once __DIR__ . '/BaseModel.php';

class UrunBoyut extends BaseModel
{
    protected string $table      = 'UrunBoyut';
    protected string $primaryKey = 'BoyutID';

    /**
     * Bir ürüne ait tüm boyutları getir.
     *
     * @param int $urunId
     * @return array
     */
    public function findByUrun(int $urunId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE UrunID = :urunId ORDER BY Fiyat ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':urunId' => $urunId]);
        return $stmt->fetchAll();
    }
}
