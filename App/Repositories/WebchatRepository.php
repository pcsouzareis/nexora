<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class WebchatRepository
{
    public function __construct(private readonly Database $database) {}

    public function findActiveChannel(string $token): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT c.cod003, c.cod001, c.cod005, c.des003,
                   e.des001 AS empresa, b.des005 AS base,
                   COALESCE(NULLIF(b.msg005, ''), cfg.msg013, 'Olá! Como posso ajudar?') AS welcome
            FROM n003 c
            INNER JOIN n001 e ON e.cod001 = c.cod001 AND e.sts001 = TRUE
            INNER JOIN n005 b ON b.cod005 = c.cod005 AND b.sts005 = TRUE
            LEFT JOIN n013 cfg ON cfg.cod001 = c.cod001
            WHERE c.pub003 = :token
              AND c.tip003 = 'Web'
              AND c.sts003 = TRUE
            LIMIT 1
        SQL);
        $statement->execute(['token' => $token]);
        $channel = $statement->fetch(PDO::FETCH_ASSOC);

        return $channel === false ? null : $channel;
    }

    public function findSession(int $channelCode, int $companyCode, string $sessionId): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT c.cod008, c.sts008,
                   (c.cod002 IS NOT NULL AND c.sts008 IN ('Em Atendimento', 'Aguardando')) AS human_handling
            FROM n008 c
            INNER JOIN n007 cli ON cli.cod007 = c.cod007
            WHERE c.cod003 = :channelCode
              AND c.cod001 = :companyCode
              AND cli.ide007 = :externalId
              AND c.ide008 = :conversationId
            LIMIT 1
        SQL);
        $statement->execute([
            'channelCode' => $channelCode,
            'companyCode' => $companyCode,
            'externalId' => 'web:' . $sessionId,
            'conversationId' => 'web:' . $sessionId,
        ]);
        $session = $statement->fetch(PDO::FETCH_ASSOC);
        return $session === false ? null : $session;
    }

    public function findMessages(int $conversationCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT m.con009, m.ori009, m.env009
            FROM n009 m
            WHERE m.cod008 = :conversationCode
            ORDER BY m.env009, m.cod009
        SQL);
        $statement->execute(['conversationCode' => $conversationCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function touchSession(int $conversationCode): void
    {
        $statement = $this->database->pdo()->prepare('UPDATE n008 SET web008 = CURRENT_TIMESTAMP WHERE cod008 = :conversationCode');
        $statement->execute(['conversationCode' => $conversationCode]);
    }

    public function closeSession(int $channelCode, int $companyCode, string $sessionId): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            UPDATE n008 c
            SET sts008 = 'Encerrada', fim008 = CURRENT_TIMESTAMP, web008 = NULL
            FROM n007 cli
            WHERE cli.cod007 = c.cod007
              AND c.cod003 = :channelCode
              AND c.cod001 = :companyCode
              AND cli.ide007 = :externalId
              AND c.ide008 = :conversationId
              AND c.sts008 IN ('Aberta', 'Aguardando', 'Em Atendimento')
        SQL);
        $statement->execute([
            'channelCode' => $channelCode,
            'companyCode' => $companyCode,
            'externalId' => 'web:' . $sessionId,
            'conversationId' => 'web:' . $sessionId,
        ]);
        return $statement->rowCount() === 1;
    }
}
