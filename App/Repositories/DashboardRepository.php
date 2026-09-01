<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class DashboardRepository
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    /**
     * Retorna os indicadores principais do Dashboard
     * da organização informada.
     */
    public function getResumo(int $cod001): array
    {
        $pdo = $this->database->pdo();

        $sql = <<<'SQL'
            SELECT
                (
                    SELECT COUNT(*)
                    FROM n001
                    WHERE cod001 = :cod001
                ) AS empresas,

                (
                    SELECT COUNT(*)
                    FROM n002
                    WHERE cod001 = :cod001
                      AND sts002 = TRUE
                ) AS atendentes,

                (
                    SELECT COUNT(*)
                    FROM n007
                    WHERE cod001 = :cod001
                      AND sts007 = TRUE
                ) AS clientes,

                (
                    SELECT COUNT(*)
                    FROM n008
                    WHERE cod001 = :cod001
                ) AS conversas
        SQL;

        $statement = $pdo->prepare($sql);

        $statement->execute([
            'cod001' => $cod001,
        ]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return [
                'empresas' => 0,
                'atendentes' => 0,
                'clientes' => 0,
                'conversas' => 0,
            ];
        }

        return [
            'empresas' => (int) $result['empresas'],
            'atendentes' => (int) $result['atendentes'],
            'clientes' => (int) $result['clientes'],
            'conversas' => (int) $result['conversas'],
        ];
    }
}