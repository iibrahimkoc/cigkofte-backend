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

## Render + Aiven Kurulum

### 1. Aiven veritabanını hazırla

Aiven MySQL servisinde `defaultdb` içine önce şemayı import et:

```bash
mysql --host=HOST --port=PORT --user=USER --password --ssl-mode=REQUIRED defaultdb < database/schema.sql
```

İlk admin kullanıcısını eklemek için:

```bash
mysql --host=HOST --port=PORT --user=USER --password --ssl-mode=REQUIRED defaultdb < database/seed_admin.sql
```

Geçici giriş:

- Eposta: `admin@example.com`
- Şifre: `password`

Canlıya aldıktan sonra bu şifreyi hemen değiştir.

### 2. Render Web Service oluştur

- Runtime/Language: `Docker`
- Branch: deploy edeceğin branch
- Dockerfile: proje kökündeki `Dockerfile`

Render Environment Variables:

```text
MYSQL_URI=mysql://USER:PASSWORD@HOST:PORT/defaultdb?ssl-mode=REQUIRED
CORS_ALLOWED_ORIGINS=https://frontend-domaininiz.com
SESSION_SECURE=true
SESSION_SAMESITE=None
```

`MYSQL_URI` değerini Aiven Connection information bölümündeki Service URI'den al. Parolayı repoya yazma.

### 3. Kontrol endpointleri

Deploy sonrası şu adresler çalışmalı:

```text
GET /
GET /health
GET /api/urunler
```

Frontend ayrı domaindeyse API çağrılarında cookie için credentials/include açık olmalı.

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
