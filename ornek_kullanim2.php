<?php
/**
 * satis_raporu.php
 * Satis raporunun tam calisma ornegi
 * Klasor: C:\xampp\htdocs\vv2\cls\
 */

declare(strict_types=1);

require_once __DIR__ . '/Raporlar.php';

// -----------------------------------------------------------------------------
// PDO BAGLANTISI
// -----------------------------------------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=voidb;charset=utf8mb4',
        'root',   // XAMPP varsayilan kullanicisi
        '',       // XAMPP varsayilan sifresi bos
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<p style="color:red">Veritabani baglanamadi: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

$raporlar = new Raporlar($pdo);
$sayfa    = $_GET['sayfa'] ?? 'ana';

// -----------------------------------------------------------------------------
// CSV export — HTML'den once olmali
// -----------------------------------------------------------------------------
if ($sayfa === 'export') {
    try {
        // Aktif filtreler varsa export'a da uygula
        $where    = '';
        $kosullar = [];
        if (!empty($_GET['bas']) && !empty($_GET['bit'])) {
            $where    = 'tarih >= :bas AND tarih <= :bit';
            $kosullar = [':bas' => $_GET['bas'], ':bit' => $_GET['bit']];
        }
        if (!empty($_GET['durum']) && $_GET['durum'] !== 'hepsi') {
            $where              = $where ? $where . ' AND durum = :durum' : 'durum = :durum';
            $kosullar[':durum'] = $_GET['durum'];
        }
        $raporlar->exportExcel(raporad: 'satis_raporu', where: $where, kosullar: $kosullar);
    } catch (RuntimeException $e) {
        error_log($e->getMessage());
    }
    exit;
}

// -----------------------------------------------------------------------------
// Filtre degerlerini al ve dogrula
// -----------------------------------------------------------------------------
$bas    = $_GET['bas']    ?? date('Y-01-01');
$bit    = $_GET['bit']    ?? date('Y-12-31');
$durum  = $_GET['durum']  ?? 'hepsi';

$izinliDurumlar = ['hepsi', 'tamamlandi', 'beklemede', 'iptal'];
if (!in_array($durum, $izinliDurumlar, true)) {
    $durum = 'hepsi';
}

// -----------------------------------------------------------------------------
// WHERE ve parametreleri olustur
// -----------------------------------------------------------------------------
$where    = 'tarih >= :bas AND tarih <= :bit';
$kosullar = [':bas' => $bas, ':bit' => $bit];

if ($durum !== 'hepsi') {
    $where             .= ' AND durum = :durum';
    $kosullar[':durum'] = $durum;
}

// -----------------------------------------------------------------------------
// Ozet istatistikleri dogrudan repository'den al
// -----------------------------------------------------------------------------
$repo = $raporlar->getRepository();
try {
    $istatistik = $repo->getSatirlarHamSorgu(
        'SELECT
            COUNT(*)       AS toplam_satis,
            SUM(tutar)     AS toplam_ciro,
            AVG(tutar)     AS ortalama_tutar,
            MAX(tutar)     AS en_yuksek,
            MIN(tutar)     AS en_dusuk
         FROM v_satis_raporu
         WHERE tarih >= :bas AND tarih <= :bit'
         . ($durum !== 'hepsi' ? ' AND durum = :durum' : ''),
        $kosullar
    );
    $ozet = $istatistik[0] ?? null;
} catch (RuntimeException $e) {
    $ozet = null;
}

// -----------------------------------------------------------------------------
// Export URL'ini olustur (mevcut filtreleri koru)
// -----------------------------------------------------------------------------
$exportUrl = '?sayfa=export&bas=' . urlencode($bas) . '&bit=' . urlencode($bit) . '&durum=' . urlencode($durum);

ob_start();

echo '<h2>Satis Raporu</h2>';

// Ozet kartlar
if ($ozet) {
    $ciro    = number_format((float)($ozet->toplam_ciro  ?? 0), 2, ',', '.');
    $ort     = number_format((float)($ozet->ortalama_tutar ?? 0), 2, ',', '.');
    $en_y    = number_format((float)($ozet->en_yuksek    ?? 0), 2, ',', '.');
    echo '<div class="ozet-konteyner">';
    echo '<div class="kart mavi"><div class="kart-sayi">' . (int)($ozet->toplam_satis ?? 0) . '</div><div class="kart-etiket">Toplam Satis</div></div>';
    echo '<div class="kart yesil"><div class="kart-sayi">₺' . $ciro . '</div><div class="kart-etiket">Toplam Ciro</div></div>';
    echo '<div class="kart turuncu"><div class="kart-sayi">₺' . $ort . '</div><div class="kart-etiket">Ortalama Tutar</div></div>';
    echo '<div class="kart mor"><div class="kart-sayi">₺' . $en_y . '</div><div class="kart-etiket">En Yuksek Satis</div></div>';
    echo '</div>';
}

// Filtre formu
echo '<form method="GET" action="" class="filtre-form">';
echo '<input type="hidden" name="sayfa" value="ana">';
echo '<div class="filtre-grup">';
echo '<label>Baslangic:</label>';
echo '<input type="date" name="bas" value="' . htmlspecialchars($bas) . '">';
echo '</div>';
echo '<div class="filtre-grup">';
echo '<label>Bitis:</label>';
echo '<input type="date" name="bit" value="' . htmlspecialchars($bit) . '">';
echo '</div>';
echo '<div class="filtre-grup">';
echo '<label>Durum:</label>';
echo '<select name="durum">';
$durumEtiketler = ['hepsi' => 'Hepsi', 'tamamlandi' => 'Tamamlandi', 'beklemede' => 'Beklemede', 'iptal' => 'Iptal'];
foreach ($durumEtiketler as $val => $etiket) {
    $sel = ($durum === $val) ? ' selected' : '';
    echo '<option value="' . $val . '"' . $sel . '>' . $etiket . '</option>';
}
echo '</select>';
echo '</div>';
echo '<button type="submit" class="btn-filtrele">Filtrele</button>';
echo '<a href="' . htmlspecialchars($exportUrl) . '" class="btn-export">CSV Indir</a>';
echo '</form>';

// Rapor tablosu
echo '<div class="tablo-konteyner">';
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
echo '</div>';

$icerik = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Satis Raporu</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: #f0f2f5; color: #333; }

        .header { background: #1a3c6e; color: #fff; padding: 16px 24px; }
        .header h1 { margin: 0; font-size: 20px; }

        .icerik { padding: 20px 24px; }

        h2 { color: #1a3c6e; margin-top: 0; }

        /* Ozet kartlar */
        .ozet-konteyner { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .kart { flex: 1; min-width: 160px; padding: 16px 20px; border-radius: 8px;
                color: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
        .kart.mavi    { background: #1976d2; }
        .kart.yesil   { background: #388e3c; }
        .kart.turuncu { background: #f57c00; }
        .kart.mor     { background: #7b1fa2; }
        .kart-sayi    { font-size: 22px; font-weight: bold; }
        .kart-etiket  { font-size: 12px; opacity: 0.85; margin-top: 4px; }

        /* Filtre formu */
        .filtre-form { background: #fff; padding: 16px 20px; border-radius: 8px;
                       margin-bottom: 20px; display: flex; gap: 16px; align-items: flex-end;
                       flex-wrap: wrap; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .filtre-grup { display: flex; flex-direction: column; gap: 4px; }
        .filtre-grup label { font-size: 12px; font-weight: bold; color: #555; }
        input[type="date"], select {
            padding: 7px 10px; border: 1px solid #ccc; border-radius: 4px;
            font-size: 14px; min-width: 150px; }
        .btn-filtrele { padding: 8px 20px; background: #1976d2; color: #fff;
                        border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-filtrele:hover { background: #1565c0; }
        .btn-export   { padding: 8px 20px; background: #388e3c; color: #fff;
                        text-decoration: none; border-radius: 4px; font-size: 14px; }
        .btn-export:hover { background: #2e7d32; }

        /* Tablo */
        .tablo-konteyner { background: #fff; border-radius: 8px; padding: 16px;
                           box-shadow: 0 1px 4px rgba(0,0,0,0.08); overflow-x: auto; }
        table.rapor-tablo { border-collapse: collapse; width: 100%; min-width: 700px; }
        table.rapor-tablo th {
            background: #1a3c6e; color: #fff; padding: 10px 14px;
            text-align: left; font-size: 13px; white-space: nowrap; }
        table.rapor-tablo td { padding: 9px 14px; border-bottom: 1px solid #eee; font-size: 13px; }
        table.rapor-tablo tr:last-child td { border-bottom: none; }
        table.rapor-tablo tr:hover td { background: #f5f8ff; }
        .bos-veri { text-align: center; color: #999; padding: 30px !important; }

        /* Filtre input ustu */
        input[type="search"] {
            padding: 7px 10px; border: 1px solid #ccc; border-radius: 4px;
            width: 240px; margin-bottom: 12px; font-size: 13px; }
        .btn { padding: 6px 14px; background: #1976d2; color: #fff;
               border: none; border-radius: 4px; cursor: pointer; margin-bottom: 10px; }

        .hata { color: #c00; padding: 12px; border: 1px solid #c00;
                border-radius: 4px; background: #fff0f0; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- table2excel icin: https://github.com/rainabba/jquery-table2excel -->
    <script src="table2excel.js"></script>
</head>
<body>

<div class="header">
    <h1>Satis Yonetim Paneli</h1>
</div>

<div class="icerik">
    <?php echo $icerik; ?>
</div>

</body>
</html>
