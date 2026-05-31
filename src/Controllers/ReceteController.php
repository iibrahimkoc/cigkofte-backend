<?php
/**
 * ReceteController - Reçete Yönetim Kontrolcüsü
 * ──────────────────────────────────────────────
 * Ürün boyutlarına ait malzeme kullanım miktarlarını yönetir.
 */

class ReceteController
{
    private Recete $receteModel;
    private UrunBoyut $boyutModel;
    private Malzeme $malzemeModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->receteModel = new Recete();
        $this->boyutModel = new UrunBoyut();
        $this->malzemeModel = new Malzeme();
        $this->authService = new AuthService();
    }

    /**
     * Tüm Reçeteleri Listele: GET /api/recete
     */
    public function listAll(): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $db = Database::getInstance()->getConnection();
        
        $sql = "SELECT 
                    r.ReceteID,
                    r.BoyutID,
                    ub.BoyutAdi,
                    u.Ad AS UrunAdi,
                    r.MalzemeID,
                    m.Ad AS MalzemeAdi,
                    r.KullanilanMiktar,
                    m.Birim
                FROM Recete r
                INNER JOIN UrunBoyut ub ON r.BoyutID = ub.BoyutID
                INNER JOIN Urun u ON ub.UrunID = u.UrunID
                INNER JOIN Malzeme m ON r.MalzemeID = m.MalzemeID
                ORDER BY u.Ad ASC, ub.BoyutAdi ASC";
                
        try {
            $stmt = $db->query($sql);
            $recipes = $stmt->fetchAll();

            $groupedRecipes = [];
            foreach ($recipes as $row) {
                $boyutId = $row['BoyutID'];
                if (!isset($groupedRecipes[$boyutId])) {
                    $groupedRecipes[$boyutId] = [
                        "BoyutID" => $boyutId,
                        "UrunAdi" => $row['UrunAdi'],
                        "BoyutAdi" => $row['BoyutAdi'],
                        "Malzemeler" => []
                    ];
                }
                $groupedRecipes[$boyutId]['Malzemeler'][] = [
                    "ReceteID" => $row['ReceteID'],
                    "MalzemeID" => $row['MalzemeID'],
                    "MalzemeAdi" => $row['MalzemeAdi'],
                    "KullanilanMiktar" => (float)$row['KullanilanMiktar'],
                    "Birim" => $row['Birim']
                ];
            }

            jsonResponse([
                "success" => true,
                "data" => array_values($groupedRecipes)
            ]);
        } catch (Exception $e) {
            jsonResponse([
                "success" => false,
                "message" => "Reçeteler listelenirken hata oluştu: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Belirli Bir Ürün Boyutunun Reçetesini Getir: GET /api/recete/{id}
     */
    public function get(int $boyutId): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $boyut = $this->boyutModel->find($boyutId);
        if (!$boyut) {
            jsonResponse([
                'success' => false,
                'message' => 'Belirtilen ürün boyutu bulunamadı!'
            ], 404);
        }

        $recipeItems = $this->receteModel->findByBoyut($boyutId);

        jsonResponse([
            "success" => true,
            "BoyutID" => $boyutId,
            "BoyutAdi" => $boyut['BoyutAdi'],
            "Reçete" => array_map(function($item) {
                return [
                    "ReceteID" => $item['ReceteID'],
                    "MalzemeID" => $item['MalzemeID'],
                    "MalzemeAdi" => $item['MalzemeAdi'],
                    "KullanilanMiktar" => (float)$item['KullanilanMiktar'],
                    "Birim" => $item['Birim']
                ];
            }, $recipeItems)
        ]);
    }

    /**
     * Reçete Kaydet/Güncelle: POST /api/recete
     */
    public function save(): void
    {
        $this->authService->requireAdmin();
        $data = getJsonBody();

        $boyutId = (int)($data['BoyutID'] ?? $data['product_size_id'] ?? 0);
        $malzemeler = $data['Malzemeler'] ?? $data['ingredients'] ?? [];

        if ($boyutId <= 0 || empty($malzemeler) || !is_array($malzemeler)) {
            jsonResponse([
                'success' => false,
                'message' => 'Eksik veya geçersiz veri! BoyutID ve Malzemeler dizisini göndermelisiniz.'
            ], 400);
        }

        $boyut = $this->boyutModel->find($boyutId);
        if (!$boyut) {
            jsonResponse([
                'success' => false,
                'message' => 'Geçersiz ürün boyutu ID\'si!'
            ], 400);
        }

        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            // 1. Mevcut reçete satırlarını sil
            $pdo = $db->getConnection();
            $stmtDelete = $pdo->prepare("DELETE FROM Recete WHERE BoyutID = :boyutId");
            $stmtDelete->execute([':boyutId' => $boyutId]);

            // 2. Yeni reçete satırlarını ekle
            $addedItems = [];
            foreach ($malzemeler as $item) {
                $malzemeId = (int)($item['MalzemeID'] ?? $item['ingredient_id'] ?? 0);
                $miktar = (float)($item['KullanilanMiktar'] ?? $item['quantity'] ?? 0.0);

                if ($malzemeId <= 0 || $miktar <= 0) {
                    continue;
                }

                $malzeme = $this->malzemeModel->find($malzemeId);
                if ($malzeme) {
                    $this->receteModel->create([
                        'BoyutID' => $boyutId,
                        'MalzemeID' => $malzemeId,
                        'KullanilanMiktar' => $miktar
                    ]);

                    $addedItems[] = [
                        'MalzemeID' => $malzemeId,
                        'MalzemeAdi' => $malzeme['Ad'],
                        'KullanilanMiktar' => $miktar,
                        'Birim' => $malzeme['Birim']
                    ];
                }
            }

            $db->commit();

            jsonResponse([
                'success' => true,
                'message' => 'Reçete başarıyla tanımlandı ve güncellendi!',
                'BoyutID' => $boyutId,
                'Reçete' => $addedItems
            ]);
        } catch (Exception $e) {
            $db->rollback();
            jsonResponse([
                'success' => false,
                'message' => 'Reçete kaydedilirken hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reçete Sil: DELETE /api/recete/{id}
     */
    public function delete(int $boyutId): void
    {
        $this->authService->requireAdmin();

        $db = Database::getInstance()->getConnection();
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM Recete WHERE BoyutID = :boyutId");
        $stmtCheck->execute([':boyutId' => $boyutId]);
        if ($stmtCheck->fetchColumn() == 0) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu ürün boyutuna ait tanımlı bir reçete bulunamadı!'
            ], 404);
        }

        $stmtDelete = $db->prepare("DELETE FROM Recete WHERE BoyutID = :boyutId");
        $result = $stmtDelete->execute([':boyutId' => $boyutId]);

        if ($result) {
            jsonResponse([
                'success' => true,
                'message' => 'Ürün boyutuna ait reçete başarıyla silindi!'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Reçete silinemedi.'
            ], 500);
        }
    }
}
