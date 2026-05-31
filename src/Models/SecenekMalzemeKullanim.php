<?php
require_once __DIR__ . '/BaseModel.php';

class SecenekMalzemeKullanim extends BaseModel
{
    protected string $table      = 'SecenekMalzemeKullanim';
    protected string $primaryKey = 'KullanimID';

    /**
     * Bir seçeneğe ait malzeme kullanımlarını getir.
     *
     * @param int $secenekId
     * @return array
     */
    public function findBySecenek(int $secenekId): array
    {
        $sql = "SELECT smk.*, m.Ad AS MalzemeAdi, m.Birim
                FROM SecenekMalzemeKullanim smk
                INNER JOIN Malzeme m ON smk.MalzemeID = m.MalzemeID
                WHERE smk.SecenekID = :secenekId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':secenekId' => $secenekId]);
        return $stmt->fetchAll();
    }
}
