<?php
declare(strict_types=1);

/**
 * RaporExporter.php
 * Gercek CSV/Excel export - HTML tablo degil
 */
class RaporExporter
{
    private RaporRepository $repo;

    public function __construct(RaporRepository $repo)
    {
        $this->repo = $repo;
    }

    public function exportCSV(string $raporad, string $where = '', array $kosullar = []): void
    {
        $rapor = $this->repo->getRaporTanim($raporad);
        if (!$rapor) {
            throw new RuntimeException("Rapor bulunamadi: {$raporad}");
        }
        $kolonlar = $this->repo->getKolonlar($rapor->kaynak);
        $satirlar = $this->repo->getSatirlar($raporad, $where, $kosullar);
        $this->sendCSVHeaders($raporad);
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM - Turkce karakter destegi
        fputcsv($output, array_map(fn($k) => $k->kolonadi, $kolonlar), ';');
        foreach ($satirlar as $satir) {
            $satir_arr = [];
            foreach ($kolonlar as $kol) {
                $satir_arr[] = $satir->{$kol->kolonadi} ?? '';
            }
            fputcsv($output, $satir_arr, ';');
        }
        fclose($output);
    }

    public function exportCSVSSP(string $raporad, array $parametreler = [], array $kolonAdlari = []): void
    {
        $satirlar = $this->repo->getSatirlarSSP($raporad, $parametreler);
        $this->sendCSVHeaders($raporad);
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $kolonAdlari, ';');
        foreach ($satirlar as $satir) {
            $satir_arr = [];
            foreach ($kolonAdlari as $kol) {
                $satir_arr[] = $satir->$kol ?? '';
            }
            fputcsv($output, $satir_arr, ';');
        }
        fclose($output);
    }

    private function sendCSVHeaders(string $dosyaAdi): void
    {
        if (headers_sent()) {
            throw new RuntimeException('Header zaten gonderilmis, export yapilamaz.');
        }
        $guvenliAd = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dosyaAdi);
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$guvenliAd}.csv\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
