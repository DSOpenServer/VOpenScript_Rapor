# VOpenScript_Rapor

PHP 8 + PDO tabanlı, modüler yapıda veritabanı rapor üretim sistemi.  
SQL Injection koruması, XSS koruması ve temiz mimari ile geliştirilmiştir.

---

## 📁 Dosya Yapısı

```
VOpenScript_Rapor/
├── Raporlar.php            ← Ana facade sınıfı — sadece bunu include edin
├── RaporRepository.php     ← Veritabanı katmanı (otomatik yüklenir)
├── RaporRenderer.php       ← HTML üretim katmanı (otomatik yüklenir)
├── RaporExporter.php       ← CSV/Excel export katmanı (otomatik yüklenir)
├── kurulum.sql             ← Örnek veritabanı yapısı ve test verileri
├── sp.sql                  ← Aylık özet stored procedure
├── ornek_kullanim1.php     ← Filtresiz ve WHERE filtreli rapor örnekleri
├── ornek_kullanim2.php     ← Stored procedure raporu örneği
└── ornek_kullanim3.php     ← Ham sorgu ve alt sınıf kullanım örnekleri
```

---

## ⚙️ Gereksinimler

| Gereksinim | Versiyon |
|---|---|
| PHP | 8.0 ve üzeri |
| MySQL | 5.7 ve üzeri |
| PDO | `pdo_mysql` extension aktif olmalı |

---

## 🚀 Kurulum

### 1. Dosyaları indirin
Tüm dosyaları aynı klasöre koyun:
```
C:\xampp\htdocs\projem\cls\
```

### 2. Veritabanını kurun
`kurulum.sql` dosyasını phpMyAdmin'de import edin.  
Aşağıdaki yapılar oluşacak:

| Nesne | Tür | Açıklama |
|---|---|---|
| `musteriler` | Tablo | 10 örnek müşteri kaydı |
| `urunler` | Tablo | 10 örnek ürün kaydı |
| `satislar` | Tablo | 35 örnek satış kaydı (2024-2025) |
| `v_satis_raporu` | View | Müşteri + ürün bilgisini birleştiren rapor view'ı |
| `raporlar` | Tablo | Modülün ihtiyaç duyduğu rapor tanım tablosu |

### 3. Stored procedure'ü yükleyin *(opsiyonel)*
Aylık özet raporu için `sp.sql` dosyasını da import edin.

### 4. PDO bağlantısını yapılandırın
```php
$pdo = new PDO(
    'mysql:host=localhost;dbname=voidb;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);
```

---

## 📖 Kullanım

### Temel Kullanım
Sadece `Raporlar.php` include etmek yeterlidir — diğer dosyalar otomatik yüklenir.

```php
require_once __DIR__ . '/cls/Raporlar.php';

$raporlar = new Raporlar($pdo);
```

---

### 1. Filtresiz Rapor

`raporlar` tablosundaki `raporad` değerine karşılık gelen raporu listeler.

```php
$raporlar->RaporOlustur('satis_raporu');
```

---

### 2. WHERE Filtreli Rapor

Kullanıcı girdisi `$kosullar` array'i ile PDO'ya güvenle geçirilir,  
doğrudan SQL'e yazmak **SQL Injection açığı** oluşturur.

```php
// Tarih aralığı filtresi
$raporlar->RaporOlustur(
    raporad:  'satis_raporu',
    where:    'tarih >= :bas AND tarih <= :bit',
    kosullar: [':bas' => '2025-01-01', ':bit' => '2025-12-31']
);

// Birden fazla koşul
$raporlar->RaporOlustur(
    raporad:  'satis_raporu',
    where:    'musteri_id = :mid AND durum = :durum',
    kosullar: [':mid' => 5, ':durum' => 'tamamlandi']
);
```

---

### 3. Stored Procedure Raporu

`raporlar` tablosundaki `kaynak` alanı bir stored procedure adı olmalıdır.  
Parametreler sıralı dizi olarak geçirilir.

```php
$raporlar->RaporOlusturSSP(
    raporad:      'aylik_ozet',
    parametreler: ['2025', '03'],
    kolonAdlari:  ['ay', 'toplam_satis', 'adet']
);
```

---

### 4. Ham SQL Sorgusuyla Rapor

Rapor tanımı olmadan JOIN veya karmaşık sorgular için kullanılır.  
`$sorgu` içine kullanıcı girdisi **doğrudan yazılmamalıdır**.

```php
$raporlar->RaporOlusturWithQuery(
    sorgu: 'SELECT k.ad, k.soyad, COUNT(s.id) AS satis_adeti
            FROM kullanicilar k
            LEFT JOIN satislar s ON s.kullanici_id = k.id
            WHERE k.durum = :durum
            GROUP BY k.id',
    kolonAdlari: ['ad', 'soyad', 'satis_adeti'],
    kosullar:    [':durum' => 'aktif'],
    raporad:     'kullanici_ozeti'
);
```

---

### 5. CSV / Excel İndirme

`exportExcel()` çağrıldığında tarayıcı dosyayı indirir.  
Bu metodun çağrılmadan önce **hiç HTML çıktısı gönderilmemiş olmalıdır**.

```php
// Filtresiz export
$raporlar->exportExcel('satis_raporu');
exit;

// Filtreli export
$raporlar->exportExcel(
    raporad:  'satis_raporu',
    where:    'tarih >= :bas AND tarih <= :bit',
    kosullar: [':bas' => '2025-01-01', ':bit' => '2025-12-31']
);
exit;
```

---

### 6. Alt Sınıfları Doğrudan Kullanma

HTML üretmeden sadece veri almak, şablona gömmek veya test yazmak için:

```php
$repo     = new RaporRepository($pdo);
$renderer = new RaporRenderer($repo);
$exporter = new RaporExporter($repo);

// Sadece veriyi al
$satirlar = $repo->getSatirlar(
    raporad:  'satis_raporu',
    where:    'durum = :durum',
    kosullar: [':durum' => 'tamamlandi']
);

// HTML'i değişkene al, template'e göm
$html = $renderer->renderRapor('satis_raporu');
echo '<div class="rapor">' . $html . '</div>';

// Stored procedure CSV export
$exporter->exportCSVSSP(
    raporad:      'aylik_ozet',
    parametreler: ['2025', '06'],
    kolonAdlari:  ['ay', 'toplam', 'adet']
);
exit;
```

---

## 🗄️ Raporlar Tablosu Yapısı

Modülün çalışması için veritabanında bir `raporlar` tablosu bulunmalıdır:

```sql
CREATE TABLE raporlar (
    id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    raporad  VARCHAR(100)  NOT NULL UNIQUE,  -- kod içinde kullanılan tekil isim
    aciklama VARCHAR(255)  DEFAULT NULL,     -- açıklama (opsiyonel)
    kaynak   VARCHAR(100)  NOT NULL,         -- tablo, view veya stored procedure adı
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Örnek kayıtlar:

```sql
INSERT INTO raporlar (raporad, aciklama, kaynak) VALUES
('satis_raporu',    'Satış detay raporu',    'v_satis_raporu'),
('musteri_listesi', 'Müşteri listesi',        'musteriler'),
('aylik_ozet',      'Aylık özet (SP)',        'sp_aylik_ozet');
```

---

## 🏗️ Mimari

Bu modül **Tek Sorumluluk İlkesi (SRP)** gözetilerek 3 katmana ayrılmıştır:

```
Raporlar.php          ← Facade: dışarıya tek giriş noktası
├── RaporRepository   ← Sadece veritabanı işlemleri
├── RaporRenderer     ← Sadece HTML üretimi
└── RaporExporter     ← Sadece CSV/Excel export
```

### Neden bu mimari?

| Orijinal Yapı | Bu Modül |
|---|---|
| SQL Injection açığı (ham string birleştirme) | PDO prepared statements |
| Her metodda ayrı DB bağlantısı | Constructor injection ile tek PDO |
| HTML + veri + export tek metodda | Her sınıf tek iş yapar |
| `@` operatörü ile hata gizleme | `try/catch` ile düzgün hata yönetimi |
| `mysql_*` fonksiyonları | PDO (PHP 8 uyumlu) |
| XSS koruması yok | `htmlspecialchars()` her çıktıda |

---

## 🔒 Güvenlik

- **SQL Injection**: Tüm kullanıcı girdileri PDO named placeholder ile geçirilir
- **XSS**: Tüm HTML çıktıları `htmlspecialchars()` ile encode edilir
- **Beyaz Liste**: Tablo ve kolon adları `preg_match('/^[a-zA-Z0-9_]+$/')` ile doğrulanır
- **Hata Yönetimi**: `RuntimeException` ve `InvalidArgumentException` ile açık hata mesajları

---

## 🛠️ Stored Procedure — sp_aylik_ozet

`sp.sql` içindeki procedure iki result set döndürür:

| Result Set | İçerik |
|---|---|
| 1. | Genel özet (toplam ciro, ortalama, en iyi ürün/müşteri) |
| 2. | Ürün bazlı dağılım ve ciro yüzdeleri |

```sql
-- MySQL'de test:
CALL sp_aylik_ozet('2025', '03');
```

PHP tarafında `nextRowset()` ile her iki sonuç ayrı ayrı alınır:

```php
$stmt = $pdo->prepare("CALL sp_aylik_ozet(:yil, :ay)");
$stmt->execute([':yil' => '2025', ':ay' => '03']);

$ozet        = $stmt->fetch(PDO::FETCH_OBJ);    // 1. result set
$stmt->nextRowset();
$urunDagilim = $stmt->fetchAll(PDO::FETCH_OBJ); // 2. result set
$stmt->closeCursor();
```

---

## 📄 Lisans

MIT License — özgürce kullanabilir, değiştirebilir ve dağıtabilirsiniz.
