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