-- =============================================================================
-- sp_aylik_ozet.sql
-- Aylik ozet raporu stored procedure
-- Calistirma: phpMyAdmin'de import edin veya MySQL komut satirinda:
--   mysql -u root -p voidb < sp_aylik_ozet.sql
-- =============================================================================

USE voidb;

-- Varsa onceki versiyonu sil
DROP PROCEDURE IF EXISTS sp_aylik_ozet;

DELIMITER //

CREATE PROCEDURE sp_aylik_ozet(
    IN p_yil  VARCHAR(4),   -- Ornek: '2024' veya '2025'
    IN p_ay   VARCHAR(2)    -- Ornek: '01', '02', ... '12'
)
BEGIN
    -- -----------------------------------------------------------------
    -- Parametre dogrulama
    -- -----------------------------------------------------------------
    IF p_yil NOT REGEXP '^[0-9]{4}$' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Gecersiz yil parametresi. 4 haneli sayi olmali. Ornek: 2025';
    END IF;

    IF p_ay NOT REGEXP '^(0[1-9]|1[0-2])$' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Gecersiz ay parametresi. 01-12 arasinda olmali. Ornek: 03';
    END IF;

    -- -----------------------------------------------------------------
    -- Ana sorgu: o aya ait tum satis ozeti
    -- -----------------------------------------------------------------
    SELECT
        -- Genel bilgiler
        CONCAT(p_yil, '-', p_ay)                        AS donem,
        DATE_FORMAT(
            STR_TO_DATE(CONCAT(p_yil, '-', p_ay, '-01'), '%Y-%m-%d'),
            '%M %Y'
        )                                               AS donem_adi,

        -- Satis adetleri
        COUNT(*)                                        AS toplam_islem,
        SUM(CASE WHEN s.durum = 'tamamlandi' THEN 1 ELSE 0 END) AS tamamlanan,
        SUM(CASE WHEN s.durum = 'beklemede'  THEN 1 ELSE 0 END) AS bekleyen,
        SUM(CASE WHEN s.durum = 'iptal'      THEN 1 ELSE 0 END) AS iptal_edilen,

        -- Finansal ozet (sadece tamamlanan satislar)
        COALESCE(SUM(CASE WHEN s.durum = 'tamamlandi' THEN s.tutar END), 0)    AS toplam_ciro,
        COALESCE(AVG(CASE WHEN s.durum = 'tamamlandi' THEN s.tutar END), 0)    AS ortalama_satis,
        COALESCE(MAX(CASE WHEN s.durum = 'tamamlandi' THEN s.tutar END), 0)    AS en_yuksek_satis,
        COALESCE(MIN(CASE WHEN s.durum = 'tamamlandi' THEN s.tutar END), 0)    AS en_dusuk_satis,

        -- En cok satan urun
        (
            SELECT u2.urun_adi
            FROM satislar s2
            JOIN urunler u2 ON u2.id = s2.urun_id
            WHERE YEAR(s2.satis_tar) = p_yil
              AND MONTH(s2.satis_tar) = CAST(p_ay AS UNSIGNED)
              AND s2.durum = 'tamamlandi'
            GROUP BY s2.urun_id
            ORDER BY SUM(s2.tutar) DESC
            LIMIT 1
        )                                               AS en_cok_satan_urun,

        -- En cok alisveris yapan musteri
        (
            SELECT CONCAT(m2.ad, ' ', m2.soyad)
            FROM satislar s3
            JOIN musteriler m2 ON m2.id = s3.musteri_id
            WHERE YEAR(s3.satis_tar) = p_yil
              AND MONTH(s3.satis_tar) = CAST(p_ay AS UNSIGNED)
              AND s3.durum = 'tamamlandi'
            GROUP BY s3.musteri_id
            ORDER BY SUM(s3.tutar) DESC
            LIMIT 1
        )                                               AS en_iyi_musteri,

        -- Kategori bazli en yuksek ciro
        (
            SELECT u3.kategori
            FROM satislar s4
            JOIN urunler u3 ON u3.id = s4.urun_id
            WHERE YEAR(s4.satis_tar) = p_yil
              AND MONTH(s4.satis_tar) = CAST(p_ay AS UNSIGNED)
              AND s4.durum = 'tamamlandi'
            GROUP BY u3.kategori
            ORDER BY SUM(s4.tutar) DESC
            LIMIT 1
        )                                               AS en_iyi_kategori

    FROM satislar s
    WHERE YEAR(s.satis_tar)  = p_yil
      AND MONTH(s.satis_tar) = CAST(p_ay AS UNSIGNED);

    -- -----------------------------------------------------------------
    -- Urun bazli dagilim (ayni call'da ikinci result set)
    -- -----------------------------------------------------------------
    SELECT
        u.urun_adi                                      AS urun,
        u.kategori                                      AS kategori,
        COUNT(*)                                        AS satis_adeti,
        SUM(s.adet)                                     AS toplam_adet,
        SUM(s.tutar)                                    AS toplam_tutar,
        ROUND(
            SUM(s.tutar) * 100.0 /
            NULLIF((
                SELECT SUM(s2.tutar)
                FROM satislar s2
                WHERE YEAR(s2.satis_tar)  = p_yil
                  AND MONTH(s2.satis_tar) = CAST(p_ay AS UNSIGNED)
                  AND s2.durum = 'tamamlandi'
            ), 0)
        , 1)                                            AS ciro_yuzdesi
    FROM satislar s
    JOIN urunler u ON u.id = s.urun_id
    WHERE YEAR(s.satis_tar)  = p_yil
      AND MONTH(s.satis_tar) = CAST(p_ay AS UNSIGNED)
      AND s.durum = 'tamamlandi'
    GROUP BY s.urun_id, u.urun_adi, u.kategori
    ORDER BY toplam_tutar DESC;

END //

DELIMITER ;

-- =============================================================================
-- TEST: MySQL'de dogrudan test etmek icin
-- =============================================================================
-- CALL sp_aylik_ozet('2024', '11');
-- CALL sp_aylik_ozet('2025', '01');
-- CALL sp_aylik_ozet('2024', '99');  -- Hata: gecersiz ay