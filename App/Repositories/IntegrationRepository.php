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
                COUNT(c.cod003) FILTER (WHERE c.sts003 = TRUE AND c.tip003 IN ('Facebook', 'Instagram')) AS meta,
                (
                    SELECT COUNT(*)
                    FROM n005 b
                    WHERE b.cod001 = e.cod001
                      AND NULLIF(BTRIM(b.nkh005), '') IS NOT NULL
                ) AS n8n
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

    /** @return array<int, array<string, mixed>> */
    public function channelsByCompany(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT c.cod003, c.des003, c.tip003, c.sts003,
                   health.tip019, health.sts019, health.des019, health.cad019
            FROM n003 c
            LEFT JOIN LATERAL (
                SELECT tip019, sts019, des019, cad019
                FROM n019
                WHERE cod003 = c.cod003
                ORDER BY cad019 DESC, cod019 DESC
                LIMIT 1
            ) health ON TRUE
            WHERE c.cod001 = :companyCode
            ORDER BY c.sts003 DESC, c.des003
        SQL);

        $statement->execute(['companyCode' => $companyCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
