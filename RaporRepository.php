<?php
declare(strict_types=1);

/**
 * RaporRepository.php
 * Sadece veritabani islemleri - HTML uretmez
 */
class RaporRepository
{
    private PDO    $pdo;
    private string $tabloAd;

    public function __construct(PDO $pdo, string $tabloAd = 'raporlar')
    {
        $this->pdo     = $pdo;
        $this->tabloAd = $tabloAd;
    }

    public function getRaporTanim(string $raporad): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->tabloAd} WHERE raporad = :raporad LIMIT 1"
        );
        $stmt->execute([':raporad' => $raporad]);
        $sonuc = $stmt->fetch(PDO::FETCH_OBJ);
        return $sonuc ?: null;
    }

    public function getKolonlar(string $kaynak): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $kaynak)) {
            throw new InvalidArgumentException("Gecersiz kaynak adi: {$kaynak}");
        }
        $dbAdi = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
        $stmt  = $this->pdo->prepare(
            "SELECT COLUMN_NAME AS kolonadi
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME   = :tablo
               AND TABLE_SCHEMA = :db
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([':tablo' => $kaynak, ':db' => $dbAdi]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getSatirlar(string $raporad, string $where = '', array $kosullar = []): array
    {
        $rapor = $this->getRaporTanim($raporad);
        if (!$rapor) {
            throw new RuntimeException("Rapor bulunamadi: {$raporad}");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $rapor->kaynak)) {
            throw new RuntimeException("Gecersiz kaynak tablo: {$rapor->kaynak}");
        }
        $sql = "SELECT * FROM {$rapor->kaynak}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($kosullar);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getSatirlarSSP(string $raporad, array $parametreler = []): array
    {
        $rapor = $this->getRaporTanim($raporad);
        if (!$rapor) {
            throw new RuntimeException("Rapor bulunamadi: {$raporad}");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $rapor->kaynak)) {
            throw new RuntimeException("Gecersiz stored procedure adi: {$rapor->kaynak}");
        }
        $placeholders = implode(',', array_fill(0, count($parametreler), '?'));
        $stmt = $this->pdo->prepare("CALL {$rapor->kaynak}({$placeholders})");
        $stmt->execute(array_values($parametreler));
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getSatirlarHamSorgu(string $sorgu, array $kosullar = []): array
    {
        $stmt = $this->pdo->prepare($sorgu);
        $stmt->execute($kosullar);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
