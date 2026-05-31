<?php
require_once __DIR__ . '/BaseModel.php';

class Siparis extends BaseModel
{
    protected string $table      = 'Siparis';
    protected string $primaryKey = 'SiparisID';

    /**
     * Duruma göre siparişleri getir.
     *
     * @param string $durum
     * @return array
     */
    public function findByDurum(string $durum): array
    {
        $sql = "SELECT s.*, k.AdSoyad AS MusteriAdi, k.Telefon AS MusteriTelefon
                FROM Siparis s
                INNER JOIN Kullanici k ON s.KullaniciID = k.KullaniciID
                WHERE s.Durum = :durum
                ORDER BY s.Tarih DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':durum' => $durum]);
        return $stmt->fetchAll();
    }

    /**
     * Sipariş durumunu güncelle.
     *
     * @param int    $siparisId
     * @param string $yeniDurum
     * @return bool
     */
    public function durumGuncelle(int $siparisId, string $yeniDurum): bool
    {
        $sql = "UPDATE {$this->table} SET Durum = :durum WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':durum' => $yeniDurum, ':id' => $siparisId]);
    }

    /**
     * Tüm siparişleri müşteri bilgileriyle birlikte getir.
     *
     * @return array
     */
    public function findAllWithCustomer(): array
    {
        $sql = "SELECT s.*, k.AdSoyad AS MusteriAdi, k.Telefon AS MusteriTelefon
                FROM Siparis s
                INNER JOIN Kullanici k ON s.KullaniciID = k.KullaniciID
                ORDER BY s.Tarih DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
