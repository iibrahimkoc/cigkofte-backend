<?php
require_once __DIR__ . '/BaseModel.php';

class MalzemeParti extends BaseModel
{
    protected string $table      = 'MalzemeParti';
    protected string $primaryKey = 'PartiID';

    /**
     * Bir malzemenin FEFO sırasına göre (son kullanma tarihi en yakın olan önce)
     * stokta kalan partilerini getir.
     *
     * @param int $malzemeId
     * @return array
     */
    public function findByMalzemeFEFO(int $malzemeId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE MalzemeID = :malzemeId AND KalanMiktar > 0
                ORDER BY SonKullanmaTarihi ASC, AlimTarihi ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':malzemeId' => $malzemeId]);
        return $stmt->fetchAll();
    }

    /**
     * Parti kalan miktarını güncelle.
     *
     * @param int   $partiId
     * @param float $kalanMiktar
     * @return bool
     */
    public function kalanMiktarGuncelle(int $partiId, float $kalanMiktar): bool
    {
        $sql = "UPDATE {$this->table} SET KalanMiktar = :kalan WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':kalan' => $kalanMiktar, ':id' => $partiId]);
    }

    /**
     * Tüm partileri malzeme adı ve birimiyle birlikte getir.
     *
     * @return array
     */
    public function findAllWithMalzeme(): array
    {
        $sql = "SELECT mp.*, m.Ad AS MalzemeAdi, m.Birim
                FROM MalzemeParti mp
                INNER JOIN Malzeme m ON mp.MalzemeID = m.MalzemeID
                ORDER BY mp.SonKullanmaTarihi ASC, mp.AlimTarihi DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
