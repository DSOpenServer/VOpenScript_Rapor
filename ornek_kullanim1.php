<?php
/**
 * ornek_kullanim.php
 * Raporlar modulu - tum senaryolari kapsayan ornek kullanim dosyasi
 *
 * Klasor yapisi:
 *   cls/
 *   ├── Raporlar.php           <- Ana sinif, sadece bunu include edin
 *   ├── RaporRepository.php    <- Otomatik yuklenir
 *   ├── RaporRenderer.php      <- Otomatik yuklenir
 *   ├── RaporExporter.php      <- Otomatik yuklenir
 *   └── ornek_kullanim.php     <- Bu dosya
 *
 * Test URL ornekleri:
 *   ornek_kullanim.php          -> Ana menu
 *   ornek_kullanim.php?sayfa=1  -> Filtresiz rapor
 *   ornek_kullanim.php?sayfa=2  -> WHERE filtreli rapor
 *   ornek_kullanim.php?sayfa=3  -> Stored procedure raporu
 *   ornek_kullanim.php?sayfa=4  -> CSV/Excel indirme
 *   ornek_kullanim.php?sayfa=5  -> Ham sorgu ile rapor
 *   ornek_kullanim.php?sayfa=6  -> Alt siniflari dogrudan kullanma
 */

declare(strict_types=1);

// Sadece Raporlar.php include etmek yeterli - digerleri otomatik yuklenir
require_once __DIR__ . '/Raporlar.php';

// -----------------------------------------------------------------------------
// PDO BAGLANTISI
// Bu blogu config.php veya bootstrap.php'ye tasiyin, her sayfada tekrar yazmayın
// -----------------------------------------------------------------------------
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

$raporlar = new Raporlar($pdo);
$sayfa    = $_GET['sayfa'] ?? '0';

// -----------------------------------------------------------------------------
// SENARYO 4 — CSV export header() gonderdigi icin HTML'den ONCE olmali
// -----------------------------------------------------------------------------
if ($sayfa === '4') {
    try {
        $raporlar->exportExcel('satis_raporu');
    } catch (RuntimeException $e) {
        error_log($e->getMessage());
        if (!headers_sent()) {
            header('Location: ornek_kullanim.php?hata=export_basarisiz');
        }
    }
    exit;
}

if ($sayfa === '4b') {
    $musteriId = (int)($_GET['musteri_id'] ?? 0);
    try {
        $raporlar->exportExcel(
            raporad:  'satis_raporu',
            where:    $musteriId > 0 ? 'musteri_id = :mid' : '',
            kosullar: $musteriId > 0 ? [':mid' => $musteriId] : []
        );
    } catch (RuntimeException $e) {
        error_log($e->getMessage());
    }
    exit;
}

// -----------------------------------------------------------------------------
// Icerik uretimi: ob_start() ile tamponla, HTML sablonuna gom
// -----------------------------------------------------------------------------
ob_start();

switch ($sayfa) {

    // -------------------------------------------------------------------------
    // SENARYO 1 — FILTRESIZ RAPOR
    // raporlar tablosunda raporad='satis_raporu' kaydi olmali
    // -------------------------------------------------------------------------
    case '1':
        echo '<h2>Senaryo 1 - Filtresiz Rapor</h2>';
        try {
            $raporlar->RaporOlustur('satis_raporu');
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            echo '<p class="hata">Rapor yuklenemedi: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        break;

    // -------------------------------------------------------------------------
    // SENARYO 2 — WHERE FILTRELI RAPOR
    // Kullanici girdisi PDO parametreleri ile gecilir, SQL injection yok
    // -------------------------------------------------------------------------
    case '2':
        $bas       = htmlspecialchars($_GET['bas']        ?? date('Y-01-01'));
        $bit       = htmlspecialchars($_GET['bit']        ?? date('Y-12-31'));
        $musteriId = (int)($_GET['musteri_id'] ?? 0);

        echo '<h2>Senaryo 2 - WHERE Filtreli Rapor</h2>';
        echo '<form method="GET" action="">';
        echo '<input type="hidden" name="sayfa" value="2">';
        echo '<div class="form-grup"><label>Baslangic Tarihi:</label>';
        echo '<input type="date" name="bas" value="' . $bas . '"></div>';
        echo '<div class="form-grup"><label>Bitis Tarihi:</label>';
        echo '<input type="date" name="bit" value="' . $bit . '"></div>';
        echo '<div class="form-grup"><label>Musteri ID:</label>';
        echo '<input type="text" name="musteri_id" value="' . ($musteriId ?: '') . '" placeholder="Bos birakilabilir"></div>';
        echo '<button type="submit" class="btn-form">Filtrele</button>';
        echo '</form>';
        echo '<h3>Sonuclar</h3>';

        $where    = 'tarih >= :bas AND tarih <= :bit';
        $kosullar = [
            ':bas' => $_GET['bas'] ?? date('Y-01-01'),
            ':bit' => $_GET['bit'] ?? date('Y-12-31'),
        ];
        if ($musteriId > 0) {
            $where            .= ' AND musteri_id = :mid';
            $kosullar[':mid']  = $musteriId;
        }

        try {
            $raporlar->RaporOlustur(
                raporad:  'satis_raporu',
                where:    $where,
                kosullar: $kosullar
            );
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            echo '<p class="hata">Rapor yuklenemedi: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        break;

    // -------------------------------------------------------------------------
    // SENARYO 3 — STORED PROCEDURE RAPORU
    // raporlar tablosundaki kaynak alani bir stored procedure adi olmali
    // -------------------------------------------------------------------------
    case '3':
        $seciliYil = (int)($_GET['yil'] ?? date('Y'));
        $seciliAy  = (int)($_GET['ay']  ?? date('n'));

        echo '<h2>Senaryo 3 - Stored Procedure Raporu</h2>';
        echo '<form method="GET" action="">';
        echo '<input type="hidden" name="sayfa" value="3">';
        echo '<div class="form-grup"><label>Yil:</label><select name="yil">';
        foreach (range(date('Y'), date('Y') - 4) as $y) {
            $sel = ($seciliYil === (int)$y) ? ' selected' : '';
            echo '<option value="' . $y . '"' . $sel . '>' . $y . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-grup"><label>Ay:</label><select name="ay">';
        for ($ay = 1; $ay <= 12; $ay++) {
            $sel = ($seciliAy === $ay) ? ' selected' : '';
            echo '<option value="' . $ay . '"' . $sel . '>' . $ay . '. Ay</option>';
        }
        echo '</select></div>';
        echo '<button type="submit" class="btn-form">Raporu Getir</button>';
        echo '</form>';
        echo '<h3>Sonuclar</h3>';

        try {
            $raporlar->RaporOlusturSSP(
                raporad:      'aylik_ozet',
                parametreler: [(string)$seciliYil, str_pad((string)$seciliAy, 2, '0', STR_PAD_LEFT)],
                kolonAdlari:  ['ay', 'toplam_satis', 'adet']
            );
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            echo '<p class="hata">Rapor yuklenemedi: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        break;

    // -------------------------------------------------------------------------
    // SENARYO 4 — CSV INDIRME BILGI SAYFASI
    // Gercek indirme yukardaki if blogu ile gerceklestirildi
    // -------------------------------------------------------------------------
    case '4':
        echo '<h2>Senaryo 4 - CSV / Excel Indirme</h2>';
        echo '<p>Asagidaki linkler CSV dosyasini dogrudan indirir:</p>';
        echo '<p><a href="?sayfa=4" class="btn-indir">Tum Satis Raporunu Indir (CSV)</a></p>';
        echo '<p><a href="?sayfa=4b&musteri_id=1" class="btn-indir btn-mor">Musteri #1 Raporunu Indir (CSV)</a></p>';
        echo '<h3>Kod Ornegi</h3>';
        echo '<pre>';
        echo htmlspecialchars(
            "// Filtresiz export:\n" .
            "\$raporlar->exportExcel('satis_raporu');\n" .
            "exit;\n\n" .
            "// Filtreli export:\n" .
            "\$raporlar->exportExcel(\n" .
            "    raporad:  'satis_raporu',\n" .
            "    where:    'musteri_id = :mid',\n" .
            "    kosullar: [':mid' => 5]\n" .
            ");\n" .
            "exit;"
        );
        echo '</pre>';
        break;

    // -------------------------------------------------------------------------
    // SENARYO 5 — HAM SQL SORGUSUYLA RAPOR
    // Rapor tanimi olmadan JOIN/subquery gibi karmasik sorgular icin
    // -------------------------------------------------------------------------
    case '5':
        $izinliDurumlar = ['aktif', 'pasif', 'bekleyen'];
        $durum = in_array($_GET['durum'] ?? '', $izinliDurumlar, true)
            ? $_GET['durum']
            : 'aktif';

        echo '<h2>Senaryo 5 - Ham SQL Sorgusuyla Rapor</h2>';
        echo '<form method="GET" action="">';
        echo '<input type="hidden" name="sayfa" value="5">';
        echo '<div class="form-grup"><label>Durum:</label><select name="durum">';
        foreach ($izinliDurumlar as $d) {
            $sel = ($durum === $d) ? ' selected' : '';
            echo '<option value="' . $d . '"' . $sel . '>' . ucfirst($d) . '</option>';
        }
        echo '</select></div>';
        echo '<button type="submit" class="btn-form">Filtrele</button>';
        echo '</form>';
        echo '<h3>Sonuclar</h3>';

        try {
            $raporlar->RaporOlusturWithQuery(
                sorgu: 'SELECT
                            k.id,
                            k.ad,
                            k.soyad,
                            k.email,
                            COUNT(s.id)  AS satis_adeti,
                            SUM(s.tutar) AS toplam_tutar
                        FROM kullanicilar k
                        LEFT JOIN satislar s ON s.kullanici_id = k.id
                        WHERE k.durum = :durum
                        GROUP BY k.id, k.ad, k.soyad, k.email
                        ORDER BY toplam_tutar DESC',
                kolonAdlari: ['id', 'ad', 'soyad', 'email', 'satis_adeti', 'toplam_tutar'],
                kosullar:    [':durum' => $durum],
                raporad:     'kullanici_satis_ozeti'
            );
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            echo '<p class="hata">Rapor yuklenemedi: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        break;

    // -------------------------------------------------------------------------
    // SENARYO 6 — ALT SINIFLARI DOGRUDAN KULLANMA
    // HTML'i degiskende tutmak veya sablona gomek gerektiginde
    // -------------------------------------------------------------------------
    case '6':
        $repo     = new RaporRepository($pdo);
        $renderer = new RaporRenderer($repo);

        echo '<h2>Senaryo 6 - Alt Siniflari Dogrudan Kullanma</h2>';

        try {
            $rapor    = $repo->getRaporTanim('satis_raporu');
            $satirlar = $repo->getSatirlar('satis_raporu', 'aktif = :aktif', [':aktif' => 1]);
            $kolonlar = $repo->getKolonlar($rapor->kaynak);

            echo '<h3>6a - Sadece veriyi al (kendi HTML yapinda kullan)</h3>';
            echo '<p>Toplam <strong>' . count($satirlar) . '</strong> kayit.</p>';
            echo '<ul>';
            foreach ($satirlar as $satir) {
                $ilkKolon = $kolonlar[0]->kolonadi ?? 'id';
                echo '<li>' . htmlspecialchars((string)($satir->$ilkKolon ?? '')) . '</li>';
            }
            echo '</ul>';

            echo '<h3>6b - HTML ciktisini degiskene al, sablona gom</h3>';
            $htmlCiktisi = $renderer->renderRapor('satis_raporu');
            echo '<div class="rapor-konteyner">' . $htmlCiktisi . '</div>';

            echo '<h3>6c - Kolon listesi</h3><ul>';
            foreach ($kolonlar as $kol) {
                echo '<li>' . htmlspecialchars($kol->kolonadi) . '</li>';
            }
            echo '</ul>';

        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            echo '<p class="hata">Hata: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        break;

    // -------------------------------------------------------------------------
    // DEFAULT — Ana menu
    // -------------------------------------------------------------------------
    default:
        echo '<h2>Raporlar Modulu - Ornek Kullanim</h2>';
        echo '<p>Bir senaryo secin:</p>';
        echo '<ul style="line-height:2.4;">';
        echo '<li><a href="?sayfa=1">Senaryo 1 - Filtresiz Rapor</a></li>';
        echo '<li><a href="?sayfa=2">Senaryo 2 - WHERE Filtreli Rapor</a></li>';
        echo '<li><a href="?sayfa=3">Senaryo 3 - Stored Procedure Raporu</a></li>';
        echo '<li><a href="?sayfa=4">Senaryo 4 - CSV / Excel Indirme</a></li>';
        echo '<li><a href="?sayfa=5">Senaryo 5 - Ham SQL Sorgusuyla Rapor</a></li>';
        echo '<li><a href="?sayfa=6">Senaryo 6 - Alt Siniflari Dogrudan Kullanma</a></li>';
        echo '</ul>';
        break;
}

$icerik = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Raporlar Modulu</title>
    <style>
        body         { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        h2           { border-bottom: 2px solid #0066cc; padding-bottom: 8px; color: #222; }
        h3           { color: #555; margin-top: 25px; }
        .hata        { color: #c00; padding: 10px; border: 1px solid #c00; border-radius: 4px; background: #fff0f0; }
        .nav         { background: #0066cc; padding: 12px 16px; border-radius: 6px; margin-bottom: 25px; }
        .nav a       { margin-right: 8px; padding: 5px 12px; background: rgba(255,255,255,0.2);
                       color: #fff; text-decoration: none; border-radius: 4px; font-size: 13px; }
        .nav a:hover { background: rgba(255,255,255,0.35); }
        .form-grup   { margin-bottom: 12px; }
        label        { display: inline-block; width: 150px; font-weight: bold; color: #444; }
        input[type="text"], input[type="date"], select {
            padding: 5px 8px; border: 1px solid #ccc; border-radius: 3px; width: 200px; }
        .btn-form    { padding: 7px 20px; background: #28a745; color: #fff;
                       border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-form:hover { background: #218838; }
        .btn-indir   { display: inline-block; padding: 8px 16px; background: #17a2b8;
                       color: #fff; text-decoration: none; border-radius: 4px; margin-bottom: 8px; }
        .btn-mor     { background: #6f42c1; }
        pre          { background: #f4f4f4; padding: 15px; border-radius: 4px;
                       overflow-x: auto; border-left: 4px solid #0066cc; }
        table.rapor-tablo { border-collapse: collapse; width: 100%; margin-top: 10px; }
        table.rapor-tablo th { background: #0066cc; color: #fff; padding: 8px 12px; text-align: left; }
        table.rapor-tablo td { padding: 7px 12px; border-bottom: 1px solid #ddd; }
        table.rapor-tablo tr:hover td { background: #f0f6ff; }
        .rapor-konteyner { margin-top: 10px; }
        input[type="search"] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;
                               width: 250px; margin-bottom: 10px; }
        .btn { padding: 5px 14px; background: #0066cc; color: #fff; border: none;
               border-radius: 4px; cursor: pointer; margin-bottom: 10px; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="table2excel.js"></script>
</head>
<body>

<div class="nav">
    <a href="?sayfa=1">1 Filtresiz</a>
    <a href="?sayfa=2">2 Filtreli</a>
    <a href="?sayfa=3">3 Stored Proc</a>
    <a href="?sayfa=4">4 CSV Indir</a>
    <a href="?sayfa=5">5 Ham Sorgu</a>
    <a href="?sayfa=6">6 Alt Siniflar</a>
</div>

<?php echo $icerik; ?>

</body>
</html>
