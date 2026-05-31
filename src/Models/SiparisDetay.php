<?php
require_once __DIR__ . '/BaseModel.php';

class SiparisDetay extends BaseModel
{
    protected string $table      = 'SiparisDetay';
    protected string $primaryKey = 'SiparisDetayID';

    /**
     * Bir siparişe ait tüm detay kalemleri getir (ürün ve boyut bilgisiyle).
     *
     * @param int $siparisId
     * @return array
     */
    public function findBySiparis(int $siparisId): array
    {
        $sql = "SELECT sd.*, ub.BoyutAdi, u.Ad AS UrunAdi
                FROM SiparisDetay sd
                INNER JOIN UrunBoyut ub ON sd.BoyutID = ub.BoyutID
                INNER JOIN Urun u ON ub.UrunID = u.UrunID
                WHERE sd.SiparisID = :siparisId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':siparisId' => $siparisId]);
        return $stmt->fetchAll();
    }
}
