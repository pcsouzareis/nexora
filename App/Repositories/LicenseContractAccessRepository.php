<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/** Controla a primeira visualização do contrato por supervisor e empresa. */
final class LicenseContractAccessRepository
{
    public function __construct(private readonly Database $database) {}

    public function isAccepted(int $companyCode, int $userCode): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM n021
            WHERE cod001 = :companyCode
              AND cod002 = :userCode
              AND ace021 IS NOT NULL
            LIMIT 1
        SQL);

        $statement->execute(['companyCode' => $companyCode, 'userCode' => $userCode]);

        return $statement->fetchColumn() !== false;
    }

    public function registerView(int $companyCode, int $userCode): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n021 (cod001, cod002)
            VALUES (:companyCode, :userCode)
            ON CONFLICT (cod001, cod002) DO NOTHING
        SQL);

        $statement->execute(['companyCode' => $companyCode, 'userCode' => $userCode]);
    }

    public function find(int $companyCode, int $userCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod021, cod001, cod002, cad021, ace021, ver021, ip021
            FROM n021
            WHERE cod001 = :companyCode
              AND cod002 = :userCode
            LIMIT 1
        SQL);

        $statement->execute(['companyCode' => $companyCode, 'userCode' => $userCode]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function accept(
        int $companyCode,
        int $userCode,
        string $version,
        ?string $ipAddress
    ): void {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n021 (cod001, cod002, ace021, ver021, ip021)
            VALUES (:companyCode, :userCode, CURRENT_TIMESTAMP, :version, :ipAddress)
            ON CONFLICT (cod001, cod002) DO UPDATE
            SET ace021 = EXCLUDED.ace021,
                ver021 = EXCLUDED.ver021,
                ip021 = EXCLUDED.ip021
            WHERE n021.ace021 IS NULL
        SQL);

        $statement->execute([
            'companyCode' => $companyCode,
            'userCode' => $userCode,
            'version' => $version,
            'ipAddress' => $ipAddress,
        ]);
    }
}
