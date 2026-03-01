<?php
/**
 * aylik_ozet_raporu.php
 * Stored procedure (sp_aylik_ozet) ile aylik ozet raporu ornegi
 * Klasor: C:\xampp\htdocs\vv2\cls\
 * URL: http://localhost/vv2/cls/aylik_ozet_raporu.php
 */

declare(strict_types=1);

require_once __DIR__ . '/Raporlar.php';

// -----------------------------------------------------------------------------
// PDO BAGLANTISI
// -----------------------------------------------------------------------------
try {
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
} catch (PDOException $e) {
    die('<p style="color:red">Veritabani baglanamadi: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// -----------------------------------------------------------------------------
// Parametreleri al ve dogrula
// -----------------------------------------------------------------------------
$seciliYil = (int)($_GET['yil'] ?? date('Y'));
$seciliAy  = (int)($_GET['ay']  ?? date('n'));

// Gecerli aralik kontrolu
if ($seciliYil < 2000 || $seciliYil > 2099) { $seciliYil = (int)date('Y'); }
if ($seciliAy  < 1    || $seciliAy  > 12)   { $seciliAy  = (int)date('n'); }

$yilStr = (string)$seciliYil;
$ayStr  = str_pad((string)$seciliAy, 2, '0', STR_PAD_LEFT);

// -----------------------------------------------------------------------------
// STORED PROCEDURE'U DOGRUDAN PDO ILE CAL
// sp_aylik_ozet 2 adet result set dondurur:
//   1. result set: genel ozet (tek satir)
//   2. result set: urun bazli dagilim (N satir)
// PDO'nun nextRowset() ile her ikisi de aliniyor
// -----------------------------------------------------------------------------
$ozet        = null;
$urunDagilim = [];
$hata        = null;

try {
    $stmt = $pdo->prepare("CALL sp_aylik_ozet(:yil, :ay)");
    $stmt->execute([':yil' => $yilStr, ':ay' => $ayStr]);

    // 1. result set — genel ozet
    $ozet = $stmt->fetch(PDO::FETCH_OBJ);

    // 2. result set — urun bazli dagilim
    if ($stmt->nextRowset()) {
        $urunDagilim = $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    $stmt->closeCursor();

} catch (PDOException $e) {
    // MySQL SIGNAL ile firlatilan hatayi yakala (gecersiz parametre vs)
    $hata = $e->getMessage();
    error_log('sp_aylik_ozet hatasi: ' . $e->getMessage());
}

// -----------------------------------------------------------------------------
// Yardimci: para formati
// -----------------------------------------------------------------------------
function para(mixed $deger): string
{
    return '₺' . number_format((float)($deger ?? 0), 2, ',', '.');
}

// -----------------------------------------------------------------------------
// Ay isimleri
// -----------------------------------------------------------------------------
$ayIsimleri = [
    1=>'Ocak', 2=>'Subat', 3=>'Mart', 4=>'Nisan', 5=>'Mayis', 6=>'Haziran',
    7=>'Temmuz', 8=>'Agustos', 9=>'Eylul', 10=>'Ekim', 11=>'Kasim', 12=>'Aralik'
];

ob_start();
?>

<h2>Aylik Ozet Raporu</h2>

<!-- Secim formu -->
<form method="GET" action="" class="filtre-form">
    <div class="filtre-grup">
        <label>Yil</label>
        <select name="yil">
            <?php foreach (range(date('Y'), 2024) as $y): ?>
                <option value="<?= $y ?>" <?= ($seciliYil === $y ? 'selected' : '') ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filtre-grup">
        <label>Ay</label>
        <select name="ay">
            <?php foreach ($ayIsimleri as $no => $isim): ?>
                <option value="<?= $no ?>" <?= ($seciliAy === $no ? 'selected' : '') ?>><?= $isim ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-filtrele">Raporu Getir</button>
</form>

<?php if ($hata): ?>
    <p class="hata">Hata: <?= htmlspecialchars($hata) ?></p>
<?php elseif (!$ozet || $ozet->toplam_islem == 0): ?>
    <div class="bos-mesaj">
        <p><?= $ayIsimleri[$seciliAy] ?> <?= $seciliYil ?> doneminde kayitli satis bulunmuyor.</p>
    </div>
<?php else: ?>

    <h3 class="donem-baslik"><?= htmlspecialchars($ozet->donem_adi ?? '') ?> Donemi Ozeti</h3>

    <!-- Ozet kartlar -->
    <div class="ozet-konteyner">
        <div class="kart mavi">
            <div class="kart-sayi"><?= (int)$ozet->toplam_islem ?></div>
            <div class="kart-etiket">Toplam Islem</div>
        </div>
        <div class="kart yesil">
            <div class="kart-sayi"><?= para($ozet->toplam_ciro) ?></div>
            <div class="kart-etiket">Toplam Ciro</div>
        </div>
        <div class="kart turuncu">
            <div class="kart-sayi"><?= para($ozet->ortalama_satis) ?></div>
            <div class="kart-etiket">Ortalama Satis</div>
        </div>
        <div class="kart mor">
            <div class="kart-sayi"><?= para($ozet->en_yuksek_satis) ?></div>
            <div class="kart-etiket">En Yuksek Satis</div>
        </div>
    </div>

    <!-- Durum dagilimi -->
    <div class="detay-grid">
        <div class="detay-kart">
            <h4>Islem Durumu</h4>
            <table class="mini-tablo">
                <tr><td>Tamamlanan</td><td class="sayi yesil-yazi"><?= (int)$ozet->tamamlanan ?></td></tr>
                <tr><td>Bekleyen</td>  <td class="sayi turuncu-yazi"><?= (int)$ozet->bekleyen ?></td></tr>
                <tr><td>Iptal</td>     <td class="sayi kirmizi-yazi"><?= (int)$ozet->iptal_edilen ?></td></tr>
            </table>
        </div>
        <div class="detay-kart">
            <h4>One Cikmalar</h4>
            <table class="mini-tablo">
                <tr>
                    <td>En Cok Satan Urun</td>
                    <td class="sayi"><strong><?= htmlspecialchars($ozet->en_cok_satan_urun ?? '-') ?></strong></td>
                </tr>
                <tr>
                    <td>En Iyi Musteri</td>
                    <td class="sayi"><strong><?= htmlspecialchars($ozet->en_iyi_musteri ?? '-') ?></strong></td>
                </tr>
                <tr>
                    <td>En Iyi Kategori</td>
                    <td class="sayi"><strong><?= htmlspecialchars($ozet->en_iyi_kategori ?? '-') ?></strong></td>
                </tr>
            </table>
        </div>
        <div class="detay-kart">
            <h4>Finansal Aralik</h4>
            <table class="mini-tablo">
                <tr><td>En Yuksek Satis</td><td class="sayi"><?= para($ozet->en_yuksek_satis) ?></td></tr>
                <tr><td>En Dusuk Satis</td> <td class="sayi"><?= para($ozet->en_dusuk_satis) ?></td></tr>
                <tr><td>Ortalama</td>        <td class="sayi"><?= para($ozet->ortalama_satis) ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Urun bazli dagilim tablosu -->
    <?php if (!empty($urunDagilim)): ?>
    <h3>Urun Bazli Dagilim</h3>
    <div class="tablo-konteyner">
        <table class="rapor-tablo">
            <thead>
                <tr>
                    <th>Urun</th>
                    <th>Kategori</th>
                    <th>Satis Adeti</th>
                    <th>Toplam Adet</th>
                    <th>Toplam Tutar</th>
                    <th>Ciro Payi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($urunDagilim as $satir): ?>
                <tr>
                    <td><?= htmlspecialchars($satir->urun) ?></td>
                    <td><span class="etiket"><?= htmlspecialchars($satir->kategori) ?></span></td>
                    <td><?= (int)$satir->satis_adeti ?></td>
                    <td><?= (int)$satir->toplam_adet ?></td>
                    <td><strong><?= para($satir->toplam_tutar) ?></strong></td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-dolu" style="width:<?= min(100, (float)$satir->ciro_yuzdesi) ?>%"></div>
                            <span class="progress-yazi">%<?= number_format((float)$satir->ciro_yuzdesi, 1) ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

<?php endif; ?>

<?php
$icerik = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aylik Ozet Raporu</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: #f0f2f5; color: #333; }

        .header { background: #1a3c6e; color: #fff; padding: 16px 24px; }
        .header h1 { margin: 0; font-size: 20px; }
        .icerik { padding: 20px 24px; }

        h2 { color: #1a3c6e; margin-top: 0; }
        h3 { color: #1a3c6e; }
        .donem-baslik { font-size: 18px; border-left: 4px solid #1976d2; padding-left: 10px; }

        /* Filtre */
        .filtre-form { background: #fff; padding: 16px 20px; border-radius: 8px;
                       margin-bottom: 20px; display: flex; gap: 16px; align-items: flex-end;
                       flex-wrap: wrap; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .filtre-grup { display: flex; flex-direction: column; gap: 4px; }
        .filtre-grup label { font-size: 12px; font-weight: bold; color: #555; }
        select { padding: 7px 10px; border: 1px solid #ccc; border-radius: 4px;
                 font-size: 14px; min-width: 130px; }
        .btn-filtrele { padding: 8px 22px; background: #1976d2; color: #fff;
                        border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-filtrele:hover { background: #1565c0; }

        /* Ozet kartlar */
        .ozet-konteyner { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .kart { flex: 1; min-width: 160px; padding: 18px 20px; border-radius: 8px;
                color: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
        .kart.mavi    { background: #1976d2; }
        .kart.yesil   { background: #388e3c; }
        .kart.turuncu { background: #f57c00; }
        .kart.mor     { background: #7b1fa2; }
        .kart-sayi    { font-size: 20px; font-weight: bold; }
        .kart-etiket  { font-size: 12px; opacity: 0.85; margin-top: 4px; }

        /* Detay grid */
        .detay-grid { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
        .detay-kart { flex: 1; min-width: 220px; background: #fff; border-radius: 8px;
                      padding: 16px 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .detay-kart h4 { margin: 0 0 12px 0; color: #1a3c6e; font-size: 14px;
                         border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .mini-tablo { width: 100%; border-collapse: collapse; font-size: 13px; }
        .mini-tablo td { padding: 6px 4px; border-bottom: 1px solid #f5f5f5; }
        .mini-tablo tr:last-child td { border-bottom: none; }
        .sayi { text-align: right; font-weight: bold; }
        .yesil-yazi   { color: #388e3c; }
        .turuncu-yazi { color: #f57c00; }
        .kirmizi-yazi { color: #c62828; }

        /* Tablo */
        .tablo-konteyner { background: #fff; border-radius: 8px; padding: 16px;
                           box-shadow: 0 1px 4px rgba(0,0,0,0.08); overflow-x: auto; }
        .rapor-tablo { border-collapse: collapse; width: 100%; min-width: 600px; }
        .rapor-tablo th { background: #1a3c6e; color: #fff; padding: 10px 14px;
                          text-align: left; font-size: 13px; }
        .rapor-tablo td { padding: 9px 14px; border-bottom: 1px solid #eee; font-size: 13px; }
        .rapor-tablo tr:last-child td { border-bottom: none; }
        .rapor-tablo tr:hover td { background: #f5f8ff; }

        .etiket { background: #e3f2fd; color: #1565c0; padding: 2px 8px;
                  border-radius: 12px; font-size: 11px; white-space: nowrap; }

        /* Progress bar */
        .progress-bar { position: relative; background: #e0e0e0; border-radius: 4px;
                        height: 20px; min-width: 100px; }
        .progress-dolu { background: #1976d2; height: 100%; border-radius: 4px;
                         transition: width 0.3s ease; }
        .progress-yazi { position: absolute; right: 6px; top: 50%;
                         transform: translateY(-50%); font-size: 11px;
                         font-weight: bold; color: #333; }

        .bos-mesaj { background: #fff; border-radius: 8px; padding: 40px;
                     text-align: center; color: #999; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .hata { color: #c00; padding: 12px; border: 1px solid #c00;
                border-radius: 4px; background: #fff0f0; }
    </style>
</head>
<body>

<div class="header">
    <h1>Aylik Ozet Raporu</h1>
</div>

<div class="icerik">
    <?php echo $icerik; ?>
</div>

</body>
</html>