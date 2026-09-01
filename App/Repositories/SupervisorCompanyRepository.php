<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class SupervisorCompanyRepository
{
    public function __construct(private readonly Database $database) {}

    public function assign(int $supervisorCode, int $companyCode): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n015 (cod002, cod001, sts015)
            VALUES (:supervisorCode, :companyCode, TRUE)
            ON CONFLICT (cod002, cod001)
            DO UPDATE SET sts015 = TRUE, atu015 = CURRENT_TIMESTAMP
        SQL);

        $statement->execute([
            'supervisorCode' => $supervisorCode,
            'companyCode' => $companyCode,
        ]);
    }

    public function hasActiveCompany(int $supervisorCode, int $companyCode): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM n015
            WHERE cod002 = :supervisorCode
              AND cod001 = :companyCode
              AND sts015 = TRUE
            LIMIT 1
        SQL);

        $statement->execute([
            'supervisorCode' => $supervisorCode,
            'companyCode' => $companyCode,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function findCompanies(int $supervisorCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT
                c.cod001,
                c.des001,
                c.doc001,
                c.ema001,
                c.tel001,
                c.log001,
                c.sts001,
                c.cad001,
                c.atu001
            FROM n015 l
            INNER JOIN n001 c ON c.cod001 = l.cod001
            WHERE l.cod002 = :supervisorCode
              AND l.sts015 = TRUE
              AND c.sts001 = TRUE
            ORDER BY c.des001 ASC
        SQL);

        $statement->execute(['supervisorCode' => $supervisorCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
