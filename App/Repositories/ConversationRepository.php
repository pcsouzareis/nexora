<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class ConversationRepository
{
    public function __construct(private readonly Database $database) {}

    public function findAllByCompany(int $companyCode, string $status = '', string $search = '', string $startDate = ''): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT
                c.cod008, c.sts008, c.pri008, c.ini008, c.cod002, c.web008,
                (c.web008 >= CURRENT_TIMESTAMP - INTERVAL '2 minutes' AND c.sts008 NOT IN ('Encerrada', 'Cancelada')) AS cliente_online,
                cli.des007 AS cliente, cli.tel007,
                ch.des003 AS canal,
                b.des005 AS base,
                u.des002 AS atendente,
                last_message.con009 AS ultima_mensagem,
                last_message.ori009 AS ultima_origem,
                last_message.env009 AS ultima_data
            FROM n008 c
            INNER JOIN n007 cli ON cli.cod007 = c.cod007
            INNER JOIN n003 ch ON ch.cod003 = c.cod003
            LEFT JOIN n005 b ON b.cod005 = c.cod005
            LEFT JOIN n002 u ON u.cod002 = c.cod002
            LEFT JOIN LATERAL (
                SELECT con009, ori009, env009
                FROM n009
                WHERE cod008 = c.cod008
                ORDER BY env009 DESC, cod009 DESC
                LIMIT 1
            ) last_message ON TRUE
            WHERE c.cod001 = :companyCode
              AND c.sts008 = COALESCE(NULLIF(:status, ''), c.sts008)
              AND CONCAT_WS(' ', cli.des007, cli.tel007, c.ide008) ILIKE :searchLike
              AND c.ini008::date = COALESCE(CAST(NULLIF(:startDate, '') AS date), c.ini008::date)
            ORDER BY CASE c.sts008 WHEN 'Aguardando' THEN 0 WHEN 'Aberta' THEN 1 WHEN 'Em Atendimento' THEN 2 ELSE 3 END,
                     COALESCE(last_message.env009, c.ini008) DESC, c.cod008 DESC
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'status' => $status,
            'searchLike' => '%' . $search . '%',
            'startDate' => $startDate,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByCompany(int $companyCode, int $conversationCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT
                c.cod008, c.sts008, c.pri008, c.ini008, c.fim008, c.cod002, c.ide008, c.web008,
                (c.sts008 = 'Aguardando' OR (c.cod002 IS NOT NULL AND c.sts008 = 'Em Atendimento')) AS atendimento_humano,
                (c.web008 >= CURRENT_TIMESTAMP - INTERVAL '2 minutes' AND c.sts008 NOT IN ('Encerrada', 'Cancelada')) AS cliente_online,
                cli.des007 AS cliente, cli.ema007, cli.tel007,
                ch.des003 AS canal, b.des005 AS base, u.des002 AS atendente
            FROM n008 c
            INNER JOIN n007 cli ON cli.cod007 = c.cod007
            INNER JOIN n003 ch ON ch.cod003 = c.cod003
            LEFT JOIN n005 b ON b.cod005 = c.cod005
            LEFT JOIN n002 u ON u.cod002 = c.cod002
            WHERE c.cod008 = :conversationCode AND c.cod001 = :companyCode
            LIMIT 1
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'conversationCode' => $conversationCode,
        ]);
        $conversation = $statement->fetch(PDO::FETCH_ASSOC);

        return $conversation === false ? null : $conversation;
    }

    public function findMessages(int $conversationCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT m.cod009, m.con009, m.ori009, m.tip009, m.env009, m.tok009,
                   u.des002 AS atendente, d.sta018 AS entrega_status, d.err018 AS entrega_erro
            FROM n009 m
            LEFT JOIN n002 u ON u.cod002 = m.cod002
            LEFT JOIN n018 d ON d.cod009 = m.cod009
            WHERE m.cod008 = :conversationCode
            ORDER BY m.env009, m.cod009
        SQL);
        $statement->execute(['conversationCode' => $conversationCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function queueSummary(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            WITH fila AS (
                SELECT c.sts008,
                       COALESCE((SELECT MAX(m.env009) FROM n009 m WHERE m.cod008 = c.cod008), c.ini008) AS atualizada_em
                FROM n008 c
                WHERE c.cod001 = :companyCode
                  AND c.sts008 IN ('Aberta', 'Aguardando', 'Em Atendimento')
            )
            SELECT COUNT(*) FILTER (WHERE sts008 = 'Aguardando') AS aguardando,
                   COUNT(*) FILTER (WHERE sts008 = 'Aberta') AS abertas,
                   COUNT(*) FILTER (WHERE sts008 = 'Em Atendimento') AS em_atendimento,
                   COALESCE(MAX(atualizada_em)::text, '') AS ultima_atualizacao
            FROM fila
        SQL);
        $statement->execute(['companyCode' => $companyCode]);
        $summary = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'aguardando' => (int) ($summary['aguardando'] ?? 0),
            'abertas' => (int) ($summary['abertas'] ?? 0),
            'em_atendimento' => (int) ($summary['em_atendimento'] ?? 0),
            'ultima_atualizacao' => (string) ($summary['ultima_atualizacao'] ?? ''),
        ];
    }

    public function countAssignedTo(int $companyCode, int $userCode): int
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM n008
            WHERE cod001 = :companyCode
              AND cod002 = :userCode
              AND sts008 IN ('Aguardando', 'Em Atendimento')
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'userCode' => $userCode,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function queues(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod010, des010, pri010, sla010
            FROM n010
            WHERE cod001 = :companyCode
              AND sts010 = TRUE
            ORDER BY pri010, des010, cod010
        SQL);
        $statement->execute(['companyCode' => $companyCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function queueHistory(int $companyCode, int $conversationCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT h.cod011, h.mot011, h.sts011, h.enc011, h.ace011,
                   q.des010 AS fila, u.des002 AS atendente
            FROM n011 h
            INNER JOIN n008 c ON c.cod008 = h.cod008
            INNER JOIN n010 q ON q.cod010 = h.cod010
            LEFT JOIN n002 u ON u.cod002 = h.cod002
            WHERE h.cod008 = :conversationCode
              AND c.cod001 = :companyCode
            ORDER BY h.enc011 DESC, h.cod011 DESC
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'conversationCode' => $conversationCode,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function take(int $companyCode, int $conversationCode, int $userCode): bool
    {
        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            if (!$this->activateHumanHandling($pdo, $companyCode, $conversationCode, $userCode)) {
                $pdo->rollBack();
                return false;
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function transfer(
        int $companyCode,
        int $conversationCode,
        int $userCode,
        int $queueCode,
        string $reason
    ): bool {
        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            $queue = $pdo->prepare('SELECT 1 FROM n010 WHERE cod010 = :queueCode AND cod001 = :companyCode AND sts010 = TRUE');
            $queue->execute(['queueCode' => $queueCode, 'companyCode' => $companyCode]);

            if ($queue->fetchColumn() === false) {
                $pdo->rollBack();
                return false;
            }

            $conversation = $pdo->prepare(<<<'SQL'
                UPDATE n008
                SET cod002 = NULL,
                    sts008 = 'Aguardando'
                WHERE cod008 = :conversationCode
                  AND cod001 = :companyCode
                  AND sts008 IN ('Aberta', 'Aguardando', 'Em Atendimento')
            SQL);
            $conversation->execute(['companyCode' => $companyCode, 'conversationCode' => $conversationCode]);

            if ($conversation->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $finish = $pdo->prepare(<<<'SQL'
                UPDATE n011
                SET sts011 = 'Transferido'
                WHERE cod011 = (
                    SELECT cod011
                    FROM n011
                    WHERE cod008 = :conversationCode
                      AND sts011 IN ('Pendente', 'Aceito')
                    ORDER BY enc011 DESC, cod011 DESC
                    LIMIT 1
                )
            SQL);
            $finish->execute(['conversationCode' => $conversationCode]);

            $entry = $pdo->prepare(<<<'SQL'
                INSERT INTO n011 (cod008, cod010, cod002, mot011, sts011)
                VALUES (:conversationCode, :queueCode, NULL, :reason, 'Pendente')
            SQL);
            $entry->execute([
                'conversationCode' => $conversationCode,
                'queueCode' => $queueCode,
                'reason' => $reason !== '' ? $reason : 'Transferência de atendimento.',
            ]);

            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function close(int $companyCode, int $conversationCode, int $userCode): bool
    {
        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(<<<'SQL'
                UPDATE n008
                SET sts008 = 'Encerrada', fim008 = CURRENT_TIMESTAMP, web008 = NULL
                WHERE cod008 = :conversationCode
                  AND cod001 = :companyCode
                  AND sts008 IN ('Aberta', 'Aguardando', 'Em Atendimento')
            SQL);
            $statement->execute(['companyCode' => $companyCode, 'conversationCode' => $conversationCode]);

            if ($statement->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $history = $pdo->prepare(<<<'SQL'
                UPDATE n011
                SET sts011 = 'Encerrado'
                WHERE cod011 = (
                    SELECT cod011 FROM n011
                    WHERE cod008 = :conversationCode AND sts011 IN ('Pendente', 'Aceito')
                    ORDER BY enc011 DESC, cod011 DESC LIMIT 1
                )
            SQL);
            $history->execute(['conversationCode' => $conversationCode]);

            if ($history->rowCount() === 0) {
                $this->createQueueHistory($pdo, $companyCode, $conversationCode, $userCode, 'Encerrada sem atendimento humano.', 'Encerrado');
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function addHumanMessage(
        int $companyCode,
        int $conversationCode,
        int $userCode,
        string $message
    ): ?int {
        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            if (!$this->activateHumanHandling($pdo, $companyCode, $conversationCode, $userCode)) {
                $pdo->rollBack();
                return null;
            }

            $statement = $pdo->prepare(<<<'SQL'
                INSERT INTO n009 (cod008, cod002, con009, ori009, tip009, lid009)
                VALUES (:conversationCode, :userCode, :message, 'Atendente', 'Texto', FALSE)
                RETURNING cod009
            SQL);
            $statement->execute([
                'conversationCode' => $conversationCode,
                'userCode' => $userCode,
                'message' => $message,
            ]);
            $messageCode = (int) $statement->fetchColumn();
            $pdo->commit();
            return $messageCode;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function activateHumanHandling(PDO $pdo, int $companyCode, int $conversationCode, int $userCode): bool
    {
        $conversation = $pdo->prepare(<<<'SQL'
            UPDATE n008
            SET cod002 = :userCode, sts008 = 'Em Atendimento'
            WHERE cod008 = :conversationCode
              AND cod001 = :companyCode
              AND (
                  (cod002 IS NULL AND sts008 IN ('Aberta', 'Aguardando'))
                  OR (cod002 = :userCode AND sts008 IN ('Aguardando', 'Em Atendimento'))
              )
        SQL);
        $conversation->execute([
            'companyCode' => $companyCode,
            'conversationCode' => $conversationCode,
            'userCode' => $userCode,
        ]);

        if ($conversation->rowCount() !== 1) {
            return false;
        }

        $accepted = $pdo->prepare(<<<'SQL'
            UPDATE n011
            SET cod002 = :userCode,
                sts011 = 'Aceito',
                ace011 = COALESCE(ace011, CURRENT_TIMESTAMP)
            WHERE cod011 = (
                SELECT cod011 FROM n011
                WHERE cod008 = :conversationCode AND sts011 = 'Pendente'
                ORDER BY enc011 DESC, cod011 DESC LIMIT 1
            )
        SQL);
        $accepted->execute(['conversationCode' => $conversationCode, 'userCode' => $userCode]);

        if ($accepted->rowCount() === 0) {
            $this->createQueueHistory($pdo, $companyCode, $conversationCode, $userCode, 'Atendimento assumido diretamente.', 'Aceito');
        }

        return true;
    }

    private function createQueueHistory(PDO $pdo, int $companyCode, int $conversationCode, ?int $userCode, string $reason, string $status): void
    {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO n011 (cod008, cod010, cod002, mot011, sts011, ace011)
            SELECT :conversationCode, q.cod010, :userCode, :reason, :status, :acceptedAt
            FROM n010 q
            WHERE q.cod001 = :companyCode
              AND q.sts010 = TRUE
            ORDER BY q.pri010, q.cod010
            LIMIT 1
        SQL);
        $statement->execute([
            'conversationCode' => $conversationCode,
            'companyCode' => $companyCode,
            'userCode' => $userCode,
            'reason' => $reason,
            'status' => $status,
            'acceptedAt' => $status === 'Aceito' ? (new \DateTimeImmutable())->format('Y-m-d H:i:sP') : null,
        ]);
    }
}
