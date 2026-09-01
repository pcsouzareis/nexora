<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

/** Controla a primeira visualização do contrato por supervisor e empresa. */
final class LicenseContractAccessRepository
{
    public function __construct(private readonly Database $database) {}

    public function isFirstAccess(int $companyCode, int $userCode): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM n021
            WHERE cod001 = :companyCode
              AND cod002 = :userCode
            LIMIT 1
        SQL);

        $statement->execute(['companyCode' => $companyCode, 'userCode' => $userCode]);

        return $statement->fetchColumn() === false;
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
}
