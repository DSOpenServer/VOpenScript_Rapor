<?php
declare(strict_types=1);

/**
 * RaporRenderer.php
 * Sadece HTML uretir - veritabanina dokunmaz
 */
class RaporRenderer
{
    private RaporRepository $repo;

    public function __construct(RaporRepository $repo)
    {
        $this->repo = $repo;
    }

    public function renderRapor(string $raporad, string $where = '', array $kosullar = []): string
    {
        $rapor = $this->repo->getRaporTanim($raporad);
        if (!$rapor) {
            return "<p class='hata'>Rapor bulunamadi: " . $this->e($raporad) . "</p>";
        }
        $kolonlar = $this->repo->getKolonlar($rapor->kaynak);
        $satirlar = $this->repo->getSatirlar($raporad, $where, $kosullar);
        return $this->buildHTML($raporad, $kolonlar, $satirlar, 'kolonadi');
    }

    public function renderRaporSSP(string $raporad, array $parametreler = [], array $kolonAdlari = []): string
    {
        $kolonlar = array_map(fn($ad) => (object)['kolonadi' => $ad], $kolonAdlari);
        $satirlar = $this->repo->getSatirlarSSP($raporad, $parametreler);
        return $this->buildHTML($raporad, $kolonlar, $satirlar, 'kolonadi');
    }

    public function renderRaporHamSorgu(string $sorgu, array $kolonAdlari, array $kosullar = [], string $raporad = 'rapor'): string
    {
        $kolonlar = array_map(fn($ad) => (object)['kolonadi' => $ad], $kolonAdlari);
        $satirlar = $this->repo->getSatirlarHamSorgu($sorgu, $kosullar);
        return $this->buildHTML($raporad, $kolonlar, $satirlar, 'kolonadi');
    }

    private function buildHTML(string $raporad, array $kolonlar, array $satirlar, string $kolonAdiField): string
    {
        $tableId = 'tbl_' . $this->e($raporad);
        $html    = $this->excelButton($raporad, $tableId);
        $html   .= $this->filterInput($raporad);
        $html   .= "<table class=\"rapor-tablo {$this->e($raporad)}\" id=\"{$tableId}\">";
        $html   .= "<thead><tr>";
        foreach ($kolonlar as $kol) {
            $html .= "<th>" . $this->e($kol->$kolonAdiField) . "</th>";
        }
        $html .= "</tr></thead><tbody>";
        if (empty($satirlar)) {
            $html .= "<tr><td colspan=\"" . count($kolonlar) . "\" class=\"bos-veri\">Kayit bulunamadi.</td></tr>";
        } else {
            foreach ($satirlar as $satir) {
                $html .= "<tr>";
                foreach ($kolonlar as $kol) {
                    $field  = $kol->$kolonAdiField;
                    $deger  = $satir->$field ?? '';
                    $html  .= "<td data-label=\"" . $this->e($field) . "\">" . $this->e((string)$deger) . "</td>";
                }
                $html .= "</tr>";
            }
        }
        $html .= "</tbody></table>";
        return $html;
    }

    private function excelButton(string $raporad, string $tableId): string
    {
        $r = $this->e($raporad);
        $t = $this->e($tableId);
        return "<script>
(function(){
    document.addEventListener('DOMContentLoaded', function(){
        var btn = document.querySelector('[data-excel-target=\"{$t}\"]');
        if(btn){ btn.addEventListener('click', function(){
            $('#{$t}').table2excel({ name:'{$r}', filename:'{$r}', fileext:'.xls',
                exclude_img:true, exclude_links:true, exclude_inputs:true });
        });}
    });
})();
</script>
<button class=\"btn\" data-excel-target=\"{$t}\">Excel Export</button>";
    }

    private function filterInput(string $raporad): string
    {
        return "<input type=\"search\" class=\"light-table-filter\" data-table=\"" . $this->e($raporad) . "\" placeholder=\"Filtrele\">";
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
