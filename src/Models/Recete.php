<?php
require_once __DIR__ . '/BaseModel.php';

class Recete extends BaseModel
{
    protected string $table      = 'Recete';
    protected string $primaryKey = 'ReceteID';

    /**
     * Bir boyuta ait reçete kalemlerini malzeme bilgisiyle getir.
     *
     * @param int $boyutId
     * @return array
     */
    public function findByBoyut(int $boyutId): array
    {
        $sql = "SELECT r.*, m.Ad AS MalzemeAdi, m.Birim, m.ToplamStok
                FROM Recete r
                INNER JOIN Malzeme m ON r.MalzemeID = m.MalzemeID
                WHERE r.BoyutID = :boyutId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':boyutId' => $boyutId]);
        return $stmt->fetchAll();
    }
}
