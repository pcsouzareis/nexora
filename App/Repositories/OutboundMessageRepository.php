<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class OutboundMessageRepository
{
    public function __construct(private readonly Database $database) {}

    public function destination(int $companyCode, int $conversationCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT c.cod003, c.tip003, c.out003, c.ins003, c.tok003, c.cli003, c.bot003, c.outtel003,
                   COALESCE(NULLIF(cli.tel007, ''), cli.ide007) AS destination,
                   cv.ide008 AS conversation_destination
            FROM n008 cv
            INNER JOIN n003 c ON c.cod003 = cv.cod003 AND c.cod001 = cv.cod001
            INNER JOIN n007 cli ON cli.cod007 = cv.cod007
            WHERE cv.cod001 = :companyCode AND cv.cod008 = :conversationCode
            LIMIT 1
        SQL);
        $statement->execute(['companyCode' => $companyCode, 'conversationCode' => $conversationCode]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function record(int $companyCode, int $channelCode, int $messageCode, string $status, ?string $externalId = null, ?string $error = null): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n018 (cod001, cod003, cod009, sta018, ext018, err018)
            VALUES (:companyCode, :channelCode, :messageCode, :status, :externalId, :error)
            ON CONFLICT (cod009) DO UPDATE
            SET sta018 = EXCLUDED.sta018, ext018 = EXCLUDED.ext018, err018 = EXCLUDED.err018, atu018 = CURRENT_TIMESTAMP
        SQL);
        $statement->execute([
            'companyCode' => $companyCode, 'channelCode' => $channelCode, 'messageCode' => $messageCode,
            'status' => mb_substr($status, 0, 30), 'externalId' => $externalId === null ? null : mb_substr($externalId, 0, 255),
            'error' => $error === null ? null : mb_substr($error, 0, 2000),
        ]);
    }

    public function updateDelivery(int $companyCode, string $externalId, ?string $error): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            UPDATE n018
            SET sta018 = :status, err018 = :error, atu018 = CURRENT_TIMESTAMP
            WHERE cod001 = :companyCode AND ext018 = :externalId
        SQL);
        $statement->execute([
            'companyCode' => $companyCode, 'externalId' => mb_substr($externalId, 0, 255),
            'status' => $error === null || $error === '' ? 'Entregue' : 'Falha',
            'error' => $error === null || $error === '' ? null : mb_substr($error, 0, 2000),
        ]);
    }

    public function updateStatus(int $companyCode, string $externalId, string $status): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            UPDATE n018
            SET sta018 = CASE
                WHEN sta018 IN ('Lida', 'Reproduzida') THEN sta018
                WHEN sta018 = 'Entregue' AND :status = 'Enviada' THEN sta018
                ELSE :status
            END,
            atu018 = CURRENT_TIMESTAMP
            WHERE cod001 = :companyCode AND ext018 = :externalId AND sta018 <> 'Falha'
        SQL);
        $statement->execute(['companyCode' => $companyCode, 'externalId' => mb_substr($externalId, 0, 255), 'status' => $status]);
    }
}
