<?php
/**
 * UrunController - Ürün ve Menü İşlemleri Kontrolcüsü
 * ────────────────────────────────────────────────────
 * Menüdeki kategorileri, ürünleri, boyutları ve seçenek gruplarını yönetir.
 */

class UrunController
{
    private Urun $urunModel;
    private Kategori $kategoriModel;
    private UrunBoyut $boyutModel;
    private SecenekGrubu $grupModel;
    private Secenek $secenekModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->urunModel = new Urun();
        $this->kategoriModel = new Kategori();
        $this->boyutModel = new UrunBoyut();
        $this->grupModel = new SecenekGrubu();
        $this->secenekModel = new Secenek();
        $this->authService = new AuthService();
    }

    /**
     * Bütünleşik Menü Bilgisi: GET /api/urunler
     * Frontend'in tek istekte tüm satış arayüzünü (Ürünler, Boyutlar, Kategoriler ve Seçenekler) kurmasını sağlar.
     */
    public function listAll(): void
    {
        // Tüm aktif ürünleri al
        $urunler = $this->urunModel->findAllAktif();

        // Her ürünün alt boyutlarını (fiyatlarıyla birlikte) ekle
        foreach ($urunler as &$urun) {
            $urun['Boyutlar'] = $this->boyutModel->findByUrun((int)$urun['UrunID']);
        }

        // Tüm kategorileri al
        $kategoriler = $this->kategoriModel->findAll();

        // Tüm seçenek gruplarını ve alt seçeneklerini (soslar, ekstralar vb.) al
        $secenekGruplari = $this->grupModel->findAll();
        foreach ($secenekGruplari as &$grup) {
            $grup['Secenekler'] = $this->secenekModel->findByGrup((int)$grup['SecenekGrubuID']);
        }

        jsonResponse([
            'success'          => true,
            'kategoriler'      => $kategoriler,
            'urunler'          => $urunler,
            'secenek_gruplari' => $secenekGruplari
        ]);
    }

    /**
     * Sadece Kategorileri Listele: GET /api/kategoriler
     */
    public function listKategoriler(): void
    {
        $kategoriler = $this->kategoriModel->findAll();
        jsonResponse([
            'success'     => true,
            'kategoriler' => $kategoriler
        ]);
    }

    /**
     * Kategori Ekle: POST /api/kategoriler
     */
    public function createKategori(): void
    {
        $this->authService->requireAdmin();
        $data = getJsonBody();
        $ad = trim($data['Ad'] ?? $data['name'] ?? '');

        if (empty($ad)) {
            jsonResponse([
                'success' => false,
                'message' => 'Lütfen kategori adını (Ad) belirtin.'
            ], 400);
        }

        try {
            $id = $this->kategoriModel->create(['Ad' => $ad]);
            jsonResponse([
                'success' => true,
                'message' => 'Kategori başarıyla eklendi!',
                'data' => [
                    'KategoriID' => $id,
                    'Ad' => $ad
                ]
            ], 201);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu kategori adı zaten mevcut veya bir hata oluştu.'
            ], 409);
        }
    }

    /**
     * Kategori Güncelle: PUT /api/kategoriler/{id}
     */
    public function updateKategori(int $id): void
    {
        $this->authService->requireAdmin();
        $data = getJsonBody();
        $ad = trim($data['Ad'] ?? $data['name'] ?? '');

        if (empty($ad)) {
            jsonResponse([
                'success' => false,
                'message' => 'Lütfen kategori adını (Ad) belirtin.'
            ], 400);
        }

        $kategori = $this->kategoriModel->find($id);
        if (!$kategori) {
            jsonResponse([
                'success' => false,
                'message' => 'Güncellenecek kategori bulunamadı!'
            ], 404);
        }

        try {
            $this->kategoriModel->update($id, ['Ad' => $ad]);
            jsonResponse([
                'success' => true,
                'message' => 'Kategori başarıyla güncellendi!'
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu kategori adı başka bir kategoride zaten mevcut veya bir hata oluştu.'
            ], 409);
        }
    }

    /**
     * Kategori Sil: DELETE /api/kategoriler/{id}
     */
    public function deleteKategori(int $id): void
    {
        $this->authService->requireAdmin();
        
        $kategori = $this->kategoriModel->find($id);
        if (!$kategori) {
            jsonResponse([
                'success' => false,
                'message' => 'Silinecek kategori bulunamadı!'
            ], 404);
        }

        $result = $this->kategoriModel->delete($id);
        if ($result) {
            jsonResponse([
                'success' => true,
                'message' => 'Kategori başarıyla silindi!'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Kategori silinemedi.'
            ], 500);
        }
    }

    /**
     * Ürün Detayı Getir: GET /api/urunler/{id}
     */
    public function getUrun(int $id): void
    {
        if (!$this->authService->isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'Bu işlem için giriş yapılması gerekmektedir.'
            ], 401);
        }

        $urun = $this->urunModel->find($id);
        if ($urun && $urun['AktifMi'] == 1) {
            $urun['Boyutlar'] = $this->boyutModel->findByUrun($id);
            jsonResponse([
                'success' => true,
                'urun' => $urun
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Ürün bulunamadı!'
            ], 404);
        }
    }

    /**
     * Ürün Ekle: POST /api/urunler
     */
    public function createUrun(): void
    {
        $this->authService->requireAdmin();
        $data = getJsonBody();

        $ad = trim($data['Ad'] ?? $data['name'] ?? '');
        $kategoriId = (int)($data['KategoriID'] ?? $data['category_id'] ?? 0);
        $aciklama = trim($data['Aciklama'] ?? $data['description'] ?? '');
        $boyutlar = $data['Boyutlar'] ?? $data['sizes'] ?? [];

        if (empty($ad) || $kategoriId <= 0) {
            jsonResponse([
                'success' => false,
                'message' => 'Eksik bilgi! Lütfen ürün adı ve kategori ID\'sini belirtin.'
            ], 400);
        }

        if (!$this->kategoriModel->find($kategoriId)) {
            jsonResponse([
                'success' => false,
                'message' => 'Geçersiz kategori ID\'si!'
            ], 400);
        }

        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            $temelFiyat = 0.0;
            if (!empty($boyutlar) && is_array($boyutlar)) {
                $firstSize = reset($boyutlar);
                $temelFiyat = (float)($firstSize['Fiyat'] ?? $firstSize['price'] ?? 0.0);
            }

            $urunId = $this->urunModel->create([
                'Ad' => $ad,
                'KategoriID' => $kategoriId,
                'Aciklama' => $aciklama,
                'TemelFiyat' => $temelFiyat,
                'AktifMi' => 1
            ]);

            $addedSizes = [];
            foreach ($boyutlar as $boyut) {
                $boyutAdi = trim($boyut['BoyutAdi'] ?? $boyut['size_name'] ?? '');
                $fiyat = (float)($boyut['Fiyat'] ?? $boyut['price'] ?? 0.0);

                if (!empty($boyutAdi) && $fiyat >= 0) {
                    $boyutId = $this->boyutModel->create([
                        'UrunID' => $urunId,
                        'BoyutAdi' => $boyutAdi,
                        'Fiyat' => $fiyat
                    ]);
                    $addedSizes[] = [
                        'BoyutID' => $boyutId,
                        'BoyutAdi' => $boyutAdi,
                        'Fiyat' => $fiyat
                    ];
                }
            }

            $db->commit();

            jsonResponse([
                'success' => true,
                'message' => 'Ürün ve boyut tanımları başarıyla eklendi!',
                'urun' => [
                    'UrunID' => $urunId,
                    'Ad' => $ad,
                    'KategoriID' => $kategoriId,
                    'Aciklama' => $aciklama,
                    'Boyutlar' => $addedSizes
                ]
            ], 201);
        } catch (Exception $e) {
            $db->rollback();
            jsonResponse([
                'success' => false,
                'message' => 'Ürün eklenirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ürün Güncelle: PUT /api/urunler/{id}
     */
    public function updateUrun(int $id): void
    {
        $this->authService->requireAdmin();
        
        $urun = $this->urunModel->find($id);
        if (!$urun) {
            jsonResponse([
                'success' => false,
                'message' => 'Güncellenecek ürün bulunamadı!'
            ], 404);
        }

        $data = getJsonBody();
        $ad = trim($data['Ad'] ?? $data['name'] ?? '');
        $kategoriId = isset($data['KategoriID']) ? (int)$data['KategoriID'] : (isset($data['category_id']) ? (int)$data['category_id'] : null);
        $aciklama = isset($data['Aciklama']) ? trim($data['Aciklama']) : (isset($data['description']) ? trim($data['description']) : null);
        $boyutlar = $data['Boyutlar'] ?? $data['sizes'] ?? null;

        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            $updateData = [];
            if (!empty($ad)) {
                $updateData['Ad'] = $ad;
            }
            if ($kategoriId !== null && $kategoriId > 0) {
                if ($this->kategoriModel->find($kategoriId)) {
                    $updateData['KategoriID'] = $kategoriId;
                }
            }
            if ($aciklama !== null) {
                $updateData['Aciklama'] = $aciklama;
            }
            if ($boyutlar !== null && is_array($boyutlar) && count($boyutlar) > 0) {
                $firstSize = reset($boyutlar);
                $updateData['TemelFiyat'] = (float)($firstSize['Fiyat'] ?? $firstSize['price'] ?? 0.0);
            }

            if (!empty($updateData)) {
                $this->urunModel->update($id, $updateData);
            }

            if ($boyutlar !== null && is_array($boyutlar)) {
                $existingSizes = $this->boyutModel->findByUrun($id);
                $existingSizesByName = array_column($existingSizes, 'BoyutID', 'BoyutAdi');

                $requestedSizeNames = [];

                foreach ($boyutlar as $boyut) {
                    $boyutAdi = trim($boyut['BoyutAdi'] ?? $boyut['size_name'] ?? '');
                    $fiyat = (float)($boyut['Fiyat'] ?? $boyut['price'] ?? 0.0);

                    if (empty($boyutAdi) || $fiyat < 0) {
                        continue;
                    }
                    $requestedSizeNames[] = $boyutAdi;

                    if (isset($existingSizesByName[$boyutAdi])) {
                        $this->boyutModel->update($existingSizesByName[$boyutAdi], ['Fiyat' => $fiyat]);
                    } else {
                        $this->boyutModel->create([
                            'UrunID' => $id,
                            'BoyutAdi' => $boyutAdi,
                            'Fiyat' => $fiyat
                        ]);
                    }
                }

                foreach ($existingSizesByName as $name => $boyutId) {
                    if (!in_array($name, $requestedSizeNames)) {
                        $this->boyutModel->delete($boyutId);
                    }
                }
            }

            $db->commit();
            jsonResponse([
                'success' => true,
                'message' => 'Ürün bilgileri ve boyutları başarıyla güncellendi!'
            ]);
        } catch (Exception $e) {
            $db->rollback();
            jsonResponse([
                'success' => false,
                'message' => 'Güncelleme sırasında hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ürün Sil: DELETE /api/urunler/{id}
     */
    public function deleteUrun(int $id): void
    {
        $this->authService->requireAdmin();

        $urun = $this->urunModel->find($id);
        if (!$urun) {
            jsonResponse([
                'success' => false,
                'message' => 'Silinecek ürün bulunamadı!'
            ], 404);
        }

        $result = $this->urunModel->update($id, ['AktifMi' => 0]);

        if ($result) {
            jsonResponse([
                'success' => true,
                'message' => 'Ürün başarıyla pasifleştirildi!'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Ürün silinemedi.'
            ], 500);
        }
    }
}
