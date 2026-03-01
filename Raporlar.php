<?php
declare(strict_types=1);

/**
 * Raporlar.php - Ana Facade sinifi
 * Bu dosyayi include etmek yeterli, diger dosyalari otomatik yukler
 */

require_once __DIR__ . '/RaporRepository.php';
require_once __DIR__ . '/RaporRenderer.php';
require_once __DIR__ . '/RaporExporter.php';

class Raporlar
{
    private RaporRepository $repo;
    private RaporRenderer   $renderer;
    private RaporExporter   $exporter;

    public function __construct(PDO $pdo, string $tabloAd = 'raporlar')
    {
        $this->repo     = new RaporRepository($pdo, $tabloAd);
        $this->renderer = new RaporRenderer($this->repo);
        $this->exporter = new RaporExporter($this->repo);
    }

    public function RaporOlustur(string $raporad, string $where = '', array $kosullar = []): void
    {
        echo $this->renderer->renderRapor($raporad, $where, $kosullar);
    }

    public function RaporOlusturSSP(string $raporad, array $parametreler = [], array $kolonAdlari = []): void
    {
        echo $this->renderer->renderRaporSSP($raporad, $parametreler, $kolonAdlari);
    }

    public function RaporOlusturWithQuery(string $sorgu, array $kolonAdlari, array $kosullar = [], string $raporad = ''): void
    {
        echo $this->renderer->renderRaporHamSorgu($sorgu, $kolonAdlari, $kosullar, $raporad);
    }

    public function exportExcel(string $raporad, string $where = '', array $kosullar = []): void
    {
        $this->exporter->exportCSV($raporad, $where, $kosullar);
    }

    // Alt siniflara dogrudan erisim gerektiginde
    public function getRepository(): RaporRepository { return $this->repo; }
    public function getRenderer(): RaporRenderer     { return $this->renderer; }
    public function getExporter(): RaporExporter     { return $this->exporter; }
}
