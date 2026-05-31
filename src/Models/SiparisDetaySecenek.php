<?php
require_once __DIR__ . '/BaseModel.php';

class SiparisDetaySecenek extends BaseModel
{
    protected string $table      = 'SiparisDetaySecenek';
    protected string $primaryKey = 'SiparisDetaySecenekID';

    /**
     * Bir sipariş detayına ait seçenekleri getir.
     *
     * @param int $siparisDetayId
     * @return array
     */
    public function findByDetay(int $siparisDetayId): array
    {
        $sql = "SELECT sds.*, s.Ad AS SecenekAdi, s.EkFiyat
                FROM SiparisDetaySecenek sds
                INNER JOIN Secenek s ON sds.SecenekID = s.SecenekID
                WHERE sds.SiparisDetayID = :detayId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':detayId' => $siparisDetayId]);
        return $stmt->fetchAll();
    }
}
