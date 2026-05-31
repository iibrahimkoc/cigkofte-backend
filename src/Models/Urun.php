<?php
require_once __DIR__ . '/BaseModel.php';

class Urun extends BaseModel
{
    protected string $table      = 'Urun';
    protected string $primaryKey = 'UrunID';

    /**
     * Kategorisiyle birlikte ürün getir.
     *
     * @param int $id
     * @return array|false
     */
    public function findWithKategori(int $id): array|false
    {
        $sql = "SELECT u.*, k.Ad AS KategoriAdi 
                FROM Urun u
                LEFT JOIN Kategori k ON u.KategoriID = k.KategoriID
                WHERE u.UrunID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Tüm aktif ürünleri kategorileriyle birlikte getir.
     *
     * @return array
     */
    public function findAllAktif(): array
    {
        $sql = "SELECT u.*, k.Ad AS KategoriAdi 
                FROM Urun u
                LEFT JOIN Kategori k ON u.KategoriID = k.KategoriID
                WHERE u.AktifMi = 1
                ORDER BY k.Ad, u.Ad";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
