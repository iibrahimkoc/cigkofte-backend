<?php
require_once __DIR__ . '/BaseModel.php';

class Malzeme extends BaseModel
{
    protected string $table      = 'Malzeme';
    protected string $primaryKey = 'MalzemeID';

    /**
     * Kritik stok seviyesinin altındaki malzemeleri getir.
     *
     * @return array
     */
    public function findKritikStokAlti(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE ToplamStok <= KritikStok AND AktifMi = 1
                ORDER BY ToplamStok ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Malzeme toplam stok miktarını güncelle.
     *
     * @param int    $malzemeId
     * @param float  $miktar    Pozitif = artır, Negatif = azalt
     * @return bool
     */
    public function stokGuncelle(int $malzemeId, float $miktar): bool
    {
        $sql = "UPDATE {$this->table} 
                SET ToplamStok = ToplamStok + :miktar 
                WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':miktar' => $miktar, ':id' => $malzemeId]);
    }
}
