<?php

declare(strict_types=1);

// =============================================================================
// RaporExporter — gerçek CSV/Excel export (HTML tablo değil)
// =============================================================================
class RaporExporter
{
    private RaporRepository $repo;

    public function __construct(RaporRepository $repo)
    {
        $this->repo = $repo;
    }

    // -------------------------------------------------------------------------
    // CSV olarak indirir — UTF-8 BOM ile Excel uyumlu
    // -------------------------------------------------------------------------
    public function exportCSV(
        string $raporad,
        string $where    = '',
        array  $kosullar = []
    ): void {
        $rapor    = $this->repo->getRaporTanim($raporad);
        if (!$rapor) {
            throw new RuntimeException("Rapor bulunamadı: {$raporad}");
        }

        $kolonlar = $this->repo->getKolonlar($rapor->kaynak);
        $satirlar = $this->repo->getSatirlar($raporad, $where, $kosullar);

        $this->sendCSVHeaders($raporad);

        $output = fopen('php://output', 'w');

        // UTF-8 BOM — Excel'in Türkçe karakterleri doğru okuması için
        fwrite($output, "\xEF\xBB\xBF");

        // Başlık satırı
        fputcsv($output, array_map(fn($k) => $k->kolonadi, $kolonlar), ';');

        // Veri satırları
        foreach ($satirlar as $satir) {
            $satir_arr = [];
            foreach ($kolonlar as $kol) {
                $satir_arr[] = $satir->{$kol->kolonadi} ?? '';
            }
            fputcsv($output, $satir_arr, ';');
        }

        fclose($output);
    }

    // -------------------------------------------------------------------------
    // Stored procedure sonucunu CSV'ye aktarır
    // -------------------------------------------------------------------------
    public function exportCSVSSP(
        string $raporad,
        array  $parametreler = [],
        array  $kolonAdlari  = []
    ): void {
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

    // =========================================================================
    // PRIVATE
    // =========================================================================
    private function sendCSVHeaders(string $dosyaAdi): void
    {
        if (headers_sent()) {
            throw new RuntimeException('Header zaten gönderilmiş, export yapılamaz.');
        }

        $guvenliAd = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dosyaAdi);

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$guvenliAd}.csv\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}