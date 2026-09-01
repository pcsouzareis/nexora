<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class IntegrationRepository
{
    public function __construct(private readonly Database $database) {}

    /** @return array<string, mixed> */
    public function summaryByCompany(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT
                e.cod001,
                e.des001,
                e.sts001,
                COALESCE(ai.sts013, FALSE) AS ia_ativa,
                COUNT(c.cod003) FILTER (WHERE c.sts003 = TRUE) AS canais_ativos,
                COUNT(c.cod003) FILTER (WHERE c.sts003 = TRUE AND c.tip003 = 'WhatsApp') AS whatsapp,
                COUNT(c.cod003) FILTER (WHERE c.sts003 = TRUE AND c.tip003 = 'Telegram') AS telegram,
                COUNT(c.cod003) FILTER (WHERE c.sts003 = TRUE AND c.tip003 = 'E-Mail') AS email,
                COUNT(c.cod003) FILTER (WHERE c.sts003 = TRUE AND c.tip003 IN ('Facebook', 'Instagram')) AS meta
            FROM n001 e
            LEFT JOIN n013 ai ON ai.cod001 = e.cod001
            LEFT JOIN n003 c ON c.cod001 = e.cod001
            WHERE e.cod001 = :companyCode
            GROUP BY e.cod001, e.des001, e.sts001, ai.sts013
        SQL);

        $statement->execute(['companyCode' => $companyCode]);
        $summary = $statement->fetch(PDO::FETCH_ASSOC);

        return $summary === false ? [] : $summary;
    }
}
