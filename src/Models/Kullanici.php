<?php
require_once __DIR__ . '/BaseModel.php';

class Kullanici extends BaseModel
{
    protected string $table      = 'Kullanici';
    protected string $primaryKey = 'KullaniciID';

    /**
     * E-posta adresine göre kullanıcı bul.
     *
     * @param string $eposta
     * @return array|false
     */
    public function findByEposta(string $eposta): array|false
    {
        $sql = "SELECT * FROM {$this->table} WHERE Eposta = :eposta LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':eposta' => $eposta]);
        return $stmt->fetch();
    }
}
