<?php
require_once __DIR__ . '/BaseModel.php';

class Secenek extends BaseModel
{
    protected string $table      = 'Secenek';
    protected string $primaryKey = 'SecenekID';

    /**
     * Bir gruba ait aktif seçenekleri getir.
     *
     * @param int $grupId
     * @return array
     */
    public function findByGrup(int $grupId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE SecenekGrubuID = :grupId AND AktifMi = 1
                ORDER BY Ad ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':grupId' => $grupId]);
        return $stmt->fetchAll();
    }
}
