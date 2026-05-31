<?php
/**
 * RaporController - Yönetici Raporlama Kontrolcüsü
 * ───────────────────────────────────────────────
 * Günlük ciro, haftalık satış trendi, en çok satan ürünler ve kritik stok uyarılarını listeler.
 */

class RaporController
{
    private StokService $stokService;
    private AuthService $authService;

    public function __construct()
    {
        $this->stokService = new StokService();
        $this->authService = new AuthService();
    }

    /**
     * Dashboard Genel Raporları: GET /api/raporlar
     */
    public function dashboard(): void
    {
        $this->authService->requireAdmin();

        $targetDate = getQuery('date');

        if ($targetDate) {
            // Tarih formatı doğrulaması (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Geçersiz tarih formatı! Lütfen YYYY-MM-DD formatında gönderin.'
                ], 400);
            }
        } else {
            $targetDate = date('Y-m-d');
        }

        $db = Database::getInstance()->getConnection();

        try {
            // --- 1. GÜNLÜK CİRO ANALİZİ ---
            $stmtDaily = $db->prepare("
                SELECT 
                    COALESCE(SUM(ToplamTutar), 0.0) as total_revenue,
                    COUNT(SiparisID) as total_orders,
                    COALESCE(AVG(ToplamTutar), 0.0) as average_order_value
                FROM Siparis
                WHERE DATE(Tarih) = :target_date
            ");
            $stmtDaily->execute([':target_date' => $targetDate]);
            $dailySummary = $stmtDaily->fetch();

            // --- 2. SON 7 GÜNLÜK SATIŞ TRENDİ ---
            $stmtWeekly = $db->query("
                SELECT 
                    DATE(Tarih) as sale_date,
                    SUM(ToplamTutar) as daily_revenue,
                    COUNT(SiparisID) as order_count
                FROM Siparis
                WHERE Tarih >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY sale_date
                ORDER BY sale_date ASC
            ");
            $weeklyTrend = $stmtWeekly->fetchAll();

            // --- 3. EN ÇOK SATAN ÜRÜNLER ---
            $stmtTop = $db->query("
                SELECT 
                    u.Ad as product_name,
                    ub.BoyutAdi as size_name,
                    SUM(sd.Adet) as total_quantity_sold,
                    SUM(sd.AraToplam) as total_revenue_generated
                FROM SiparisDetay sd
                INNER JOIN UrunBoyut ub ON sd.BoyutID = ub.BoyutID
                INNER JOIN Urun u ON ub.UrunID = u.UrunID
                GROUP BY sd.BoyutID
                ORDER BY total_quantity_sold DESC
                LIMIT 10
            ");
            $topSelling = $stmtTop->fetchAll();

            // --- 4. ENVANTER KRİTİK STOK UYARISI ---
            $criticalStock = $this->stokService->getKritikStokUyarilari();

            jsonResponse([
                'success' => true,
                'reporting_date' => $targetDate,
                'data' => [
                    'daily_summary' => [
                        'total_revenue' => (float)$dailySummary['total_revenue'],
                        'total_orders' => (int)$dailySummary['total_orders'],
                        'average_order_value' => round((float)$dailySummary['average_order_value'], 2)
                    ],
                    'weekly_trend' => array_map(function($row) {
                        return [
                            'date' => $row['sale_date'],
                            'revenue' => (float)$row['daily_revenue'],
                            'orders' => (int)$row['order_count']
                        ];
                    }, $weeklyTrend),
                    'top_selling_products' => array_map(function($row) {
                        return [
                            'product_name' => $row['product_name'],
                            'size_name' => $row['size_name'],
                            'total_quantity_sold' => (int)$row['total_quantity_sold'],
                            'total_revenue' => (float)$row['total_revenue_generated']
                        ];
                    }, $topSelling),
                    'critical_stock_warnings' => array_map(function($row) {
                        return [
                            'ingredient_id' => $row['MalzemeID'],
                            'name' => $row['Ad'],
                            'stock_quantity' => (float)$row['ToplamStok'],
                            'unit' => $row['Birim']
                        ];
                    }, $criticalStock)
                ]
            ]);

        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Rapor oluşturulurken veritabanı hatası oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
}
