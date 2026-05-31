<?php
require_once __DIR__ . '/BaseModel.php';

class StokHareket extends BaseModel
{
    protected string $table      = 'StokHareket';
    protected string $primaryKey = 'StokHareketID';

    /**
     * Bir malzemeye ait stok hareketlerini getir (son işlemler önce).
     *
     * @param int $malzemeId
     * @return array
     */
    public function findByMalzeme(int $malzemeId): array
    {
        $sql = "SELECT sh.*, mp.LotNo, m.Ad AS MalzemeAdi
                FROM StokHareket sh
                LEFT JOIN MalzemeParti mp ON sh.PartiID = mp.PartiID
                INNER JOIN Malzeme m ON sh.MalzemeID = m.MalzemeID
                WHERE sh.MalzemeID = :malzemeId
                ORDER BY sh.Tarih DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':malzemeId' => $malzemeId]);
        return $stmt->fetchAll();
    }

    /**
     * Tüm stok hareketlerini malzeme adı, birimi ve parti lot numarasıyla getir.
     *
     * @return array
     */
    public function findAllWithDetails(): array
    {
        $sql = "SELECT sh.*, m.Ad AS MalzemeAdi, m.Birim, mp.LotNo
                FROM StokHareket sh
                INNER JOIN Malzeme m ON sh.MalzemeID = m.MalzemeID
                LEFT JOIN MalzemeParti mp ON sh.PartiID = mp.PartiID
                ORDER BY sh.Tarih DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
