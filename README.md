# Çiğköfte Stok ve Sipariş Yönetim Sistemi - Backend

## Proje Yapısı

```
cigkofte-backend/
├── config/
│   ├── db.php            # PDO Singleton bağlantı sınıfı
│   └── db_config.php     # Veritabanı bilgileri (GİZLİ - .gitignore)
├── src/
│   ├── Controllers/      # API endpoint kontrolcüleri
│   │   ├── AuthController.php
│   │   ├── SiparisController.php
│   │   ├── StokController.php
│   │   └── UrunController.php
│   ├── Models/           # Veritabanı model sınıfları
│   │   ├── BaseModel.php
│   │   ├── Kategori.php
│   │   ├── Kullanici.php
│   │   ├── Malzeme.php
│   │   ├── MalzemeParti.php
│   │   ├── Recete.php
│   │   ├── Secenek.php
│   │   ├── SecenekGrubu.php
│   │   ├── SecenekMalzemeKullanim.php
│   │   ├── Siparis.php
│   │   ├── SiparisDetay.php
│   │   ├── SiparisDetaySecenek.php
│   │   ├── StokHareket.php
│   │   ├── Urun.php
│   │   └── UrunBoyut.php
│   ├── Services/         # İş mantığı servisleri
│   │   ├── AuthService.php
│   │   ├── SiparisService.php
│   │   └── StokService.php
│   └── Helpers/
│       └── helpers.php   # Yardımcı fonksiyonlar
├── logs/                 # Hata logları
├── uploads/              # Yüklenen dosyalar
├── .gitignore
└── README.md
```

## Mimari

- **Vanilla PHP** — Framework bağımsız, saf PHP sınıfları
- **Singleton PDO** — Tüm projede tek veritabanı bağlantısı
- **Service-Model Pattern** — İş mantığı Service'lerde, CRUD Model'lerde
- **FEFO Lot Takibi** — Son kullanma tarihi en yakın olan parti önce tükenir
- **PHP Session** — Oturum yönetimi, sadece Admin/Personel erişimi

## Render Deploy

Bu proje Render'da Docker runtime ile çalışacak şekilde hazırlanmıştır.

1. Render Dashboard > New > Web Service
2. GitHub repo olarak `iibrahimkoc/cigkofte-backend` seçilir.
3. Runtime/Language: `Docker`
4. Environment Variables:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   - `DB_CHARSET` (opsiyonel, varsayılan: `utf8mb4`)

Local geliştirme için `config/db_config.example.php` dosyasını `config/db_config.php` olarak kopyalayıp kendi veritabanı bilgilerinle doldurabilirsin. `config/db_config.php` git'e eklenmez.

## Stok Akışı

```
Sipariş → SiparisDetay (BoyutID)
  ├─→ Recete (temel malzemeler)
  └─→ SiparisDetaySecenek → SecenekMalzemeKullanim (ek malzemeler)
      ↓
  FEFO ile MalzemeParti'den stok düşülür
      ↓
  StokHareket tablosuna log yazılır
      ↓
  Malzeme.ToplamStok güncellenir
```
