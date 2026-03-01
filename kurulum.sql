-- =============================================================================
-- kurulum.sql
-- Raporlar modulu icin ornek veritabani yapisi ve test verileri
-- Calistirma: phpMyAdmin'de import edin veya MySQL komut satirinda:
--   mysql -u root -p voidb < kurulum.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS voidb CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE voidb;

-- =============================================================================
-- 1. MUSTERILER TABLOSU
-- =============================================================================
CREATE TABLE IF NOT EXISTS musteriler (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    ad         VARCHAR(100)    NOT NULL,
    soyad      VARCHAR(100)    NOT NULL,
    email      VARCHAR(150)    NOT NULL UNIQUE,
    telefon    VARCHAR(20)     DEFAULT NULL,
    sehir      VARCHAR(100)    DEFAULT NULL,
    aktif      TINYINT(1)      NOT NULL DEFAULT 1,
    kayit_tar  DATE            NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 2. URUNLER TABLOSU
-- =============================================================================
CREATE TABLE IF NOT EXISTS urunler (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    urun_adi   VARCHAR(200)    NOT NULL,
    kategori   VARCHAR(100)    NOT NULL,
    fiyat      DECIMAL(10,2)   NOT NULL,
    stok       INT             NOT NULL DEFAULT 0,
    aktif      TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 3. SATISLAR TABLOSU
-- =============================================================================
CREATE TABLE IF NOT EXISTS satislar (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    musteri_id   INT UNSIGNED    NOT NULL,
    urun_id      INT UNSIGNED    NOT NULL,
    adet         INT             NOT NULL DEFAULT 1,
    birim_fiyat  DECIMAL(10,2)   NOT NULL,
    tutar        DECIMAL(10,2)   NOT NULL,
    satis_tar    DATE            NOT NULL,
    durum        ENUM('tamamlandi','beklemede','iptal') NOT NULL DEFAULT 'tamamlandi',
    PRIMARY KEY (id),
    FOREIGN KEY (musteri_id) REFERENCES musteriler(id),
    FOREIGN KEY (urun_id)    REFERENCES urunler(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 4. RAPORLAR TANIMI TABLOSU (modülün ihtiyac duydugu tablo)
-- kaynak: hangi tablodan/view'dan veri cekilecek
-- =============================================================================
CREATE TABLE IF NOT EXISTS raporlar (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    raporad    VARCHAR(100)    NOT NULL UNIQUE,
    aciklama   VARCHAR(255)    DEFAULT NULL,
    kaynak     VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 5. SATIS RAPORU VIEW'I
-- Modülün kullanacagi view: musteri adi, urun adi, tutar vs. bir arada
-- =============================================================================
CREATE OR REPLACE VIEW v_satis_raporu AS
SELECT
    s.id                                        AS satis_id,
    s.satis_tar                                 AS tarih,
    CONCAT(m.ad, ' ', m.soyad)                 AS musteri,
    m.sehir                                     AS sehir,
    u.urun_adi                                  AS urun,
    u.kategori                                  AS kategori,
    s.adet                                      AS adet,
    s.birim_fiyat                               AS birim_fiyat,
    s.tutar                                     AS tutar,
    s.durum                                     AS durum
FROM satislar s
JOIN musteriler m ON m.id = s.musteri_id
JOIN urunler    u ON u.id = s.urun_id;

-- =============================================================================
-- 6. MUSTERILER VERISI
-- =============================================================================
INSERT INTO musteriler (ad, soyad, email, telefon, sehir, aktif, kayit_tar) VALUES
('Ahmet',   'Yilmaz',  'ahmet@ornek.com',   '5301111111', 'Istanbul',  1, '2024-01-10'),
('Ayse',    'Kaya',    'ayse@ornek.com',    '5322222222', 'Ankara',    1, '2024-02-14'),
('Mehmet',  'Demir',   'mehmet@ornek.com',  '5333333333', 'Izmir',     1, '2024-03-05'),
('Fatma',   'Celik',   'fatma@ornek.com',   '5344444444', 'Bursa',     1, '2024-04-20'),
('Ali',     'Sahin',   'ali@ornek.com',     '5355555555', 'Antalya',   1, '2024-05-11'),
('Zeynep',  'Arslan',  'zeynep@ornek.com',  '5366666666', 'Adana',     0, '2024-06-18'),
('Mustafa', 'Ozturk',  'mustafa@ornek.com', '5377777777', 'Istanbul',  1, '2024-07-22'),
('Elif',    'Yildirim','elif@ornek.com',    '5388888888', 'Ankara',    1, '2024-08-09'),
('Hasan',   'Kurt',    'hasan@ornek.com',   '5399999999', 'Konya',     1, '2024-09-03'),
('Merve',   'Ozkan',   'merve@ornek.com',   '5311111112', 'Trabzon',   1, '2024-10-15');

-- =============================================================================
-- 7. URUNLER VERISI
-- =============================================================================
INSERT INTO urunler (urun_adi, kategori, fiyat, stok, aktif) VALUES
('Laptop Pro 15',        'Bilgisayar',   24999.00,  50, 1),
('Mekanik Klavye',       'Aksesuar',      1299.00, 200, 1),
('Kablosuz Mouse',       'Aksesuar',       549.00, 300, 1),
('27" Monitör',          'Ekran',         8999.00,  30, 1),
('USB-C Hub',            'Aksesuar',       799.00, 150, 1),
('Webcam HD',            'Kamera',        1499.00,  80, 1),
('Kulaküstü Kulaklık',   'Ses',           2999.00,  60, 1),
('SSD 1TB',              'Depolama',      1899.00, 120, 1),
('Tablet 10"',           'Tablet',        6499.00,  40, 1),
('Akıllı Saat',          'Giyilebilir',   4299.00,  70, 1);

-- =============================================================================
-- 8. SATISLAR VERISI (2024-2025 arasi cesitli tarihler)
-- =============================================================================
INSERT INTO satislar (musteri_id, urun_id, adet, birim_fiyat, tutar, satis_tar, durum) VALUES
(1,  1, 1, 24999.00, 24999.00, '2024-01-15', 'tamamlandi'),
(1,  2, 2,  1299.00,  2598.00, '2024-01-20', 'tamamlandi'),
(2,  4, 1,  8999.00,  8999.00, '2024-02-05', 'tamamlandi'),
(2,  3, 1,   549.00,   549.00, '2024-02-10', 'tamamlandi'),
(3,  1, 1, 24999.00, 24999.00, '2024-03-12', 'tamamlandi'),
(3,  7, 1,  2999.00,  2999.00, '2024-03-18', 'tamamlandi'),
(4,  9, 1,  6499.00,  6499.00, '2024-04-22', 'tamamlandi'),
(4,  5, 2,   799.00,  1598.00, '2024-04-25', 'tamamlandi'),
(5,  6, 1,  1499.00,  1499.00, '2024-05-14', 'tamamlandi'),
(5, 10, 1,  4299.00,  4299.00, '2024-05-20', 'tamamlandi'),
(6,  8, 2,  1899.00,  3798.00, '2024-06-08', 'iptal'),
(7,  1, 1, 24999.00, 24999.00, '2024-07-03', 'tamamlandi'),
(7,  2, 1,  1299.00,  1299.00, '2024-07-03', 'tamamlandi'),
(7,  3, 1,   549.00,   549.00, '2024-07-03', 'tamamlandi'),
(8,  4, 2,  8999.00, 17998.00, '2024-08-11', 'tamamlandi'),
(8,  7, 1,  2999.00,  2999.00, '2024-08-15', 'beklemede'),
(9,  5, 3,   799.00,  2397.00, '2024-09-06', 'tamamlandi'),
(9,  8, 1,  1899.00,  1899.00, '2024-09-20', 'tamamlandi'),
(10, 9, 1,  6499.00,  6499.00, '2024-10-18', 'tamamlandi'),
(10,10, 2,  4299.00,  8598.00, '2024-10-25', 'tamamlandi'),
(1,  6, 1,  1499.00,  1499.00, '2024-11-05', 'tamamlandi'),
(2,  1, 1, 24999.00, 24999.00, '2024-11-11', 'tamamlandi'),
(3,  3, 2,   549.00,  1098.00, '2024-11-20', 'tamamlandi'),
(4,  2, 1,  1299.00,  1299.00, '2024-12-02', 'tamamlandi'),
(5,  4, 1,  8999.00,  8999.00, '2024-12-10', 'tamamlandi'),
(6,  7, 1,  2999.00,  2999.00, '2024-12-14', 'beklemede'),
(7,  8, 2,  1899.00,  3798.00, '2024-12-18', 'tamamlandi'),
(8,  5, 1,   799.00,   799.00, '2025-01-07', 'tamamlandi'),
(9,  1, 1, 24999.00, 24999.00, '2025-01-14', 'tamamlandi'),
(10, 6, 2,  1499.00,  2998.00, '2025-01-22', 'tamamlandi'),
(1,  9, 1,  6499.00,  6499.00, '2025-02-03', 'tamamlandi'),
(2, 10, 1,  4299.00,  4299.00, '2025-02-17', 'tamamlandi'),
(3,  4, 1,  8999.00,  8999.00, '2025-03-05', 'tamamlandi'),
(4,  1, 1, 24999.00, 24999.00, '2025-03-12', 'tamamlandi'),
(5,  8, 3,  1899.00,  5697.00, '2025-03-20', 'tamamlandi');

-- =============================================================================
-- 9. RAPORLAR TANIMLARINI EKLE
-- kaynak alani: view adi veya tablo adi
-- =============================================================================
INSERT INTO raporlar (raporad, aciklama, kaynak) VALUES
('satis_raporu',    'Tum satis detaylari (musteri + urun bilgisi ile)', 'v_satis_raporu'),
('musteri_listesi', 'Tum musteriler',                                   'musteriler'),
('urun_listesi',    'Tum urun katalogu',                                'urunler');

-- =============================================================================
-- KONTROL SORGULARI (calistirarak veriyi dogrulayin)
-- =============================================================================
-- SELECT * FROM v_satis_raporu ORDER BY tarih DESC;
-- SELECT COUNT(*) AS toplam_satis, SUM(tutar) AS toplam_ciro FROM satislar WHERE durum='tamamlandi';
-- SELECT * FROM raporlar;
