<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class WebhookRepository
{
    public function __construct(private readonly Database $database) {}

    public function findActiveChannel(int $channelCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod003, cod001, api003
            FROM n003
            WHERE cod003 = :channelCode AND sts003 = TRUE
            LIMIT 1
        SQL);
        $statement->execute(['channelCode' => $channelCode]);
        $channel = $statement->fetch(PDO::FETCH_ASSOC);        
        return $channel === false ? null : $channel;
    }

    public function findActiveChannelsByCompany(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod003, des003, tip003
            FROM n003
            WHERE cod001 = :companyCode AND sts003 = TRUE
            ORDER BY des003
        SQL);
        $statement->execute(['companyCode' => $companyCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActiveBasesByCompany(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod005, des005
            FROM n005
            WHERE cod001 = :companyCode AND sts005 = TRUE
            ORDER BY des005
        SQL);
        $statement->execute(['companyCode' => $companyCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findOrCreateClient(int $companyCode, string $externalId, ?string $name, ?string $phone = null): int
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod007
            FROM n007
            WHERE cod001 = :companyCode AND ide007 = :externalId
            LIMIT 1
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'externalId' => $externalId,
        ]);
        $clientCode = $statement->fetchColumn();

        if ($clientCode !== false) {
            if ($phone !== null && $phone !== '') {
                $update = $this->database->pdo()->prepare('UPDATE n007 SET tel007 = COALESCE(NULLIF(tel007, \'\'), :phone) WHERE cod007 = :clientCode');
                $update->execute(['phone' => $phone, 'clientCode' => $clientCode]);
            }
            return (int) $clientCode;
        }

        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n007 (cod001, des007, ide007, tel007, sts007)
            VALUES (:companyCode, :name, :externalId, :phone, TRUE)
            RETURNING cod007
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'name' => $name ?: $externalId,
            'externalId' => $externalId,
            'phone' => $phone,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findOrCreateConversation(
        int $companyCode,
        int $channelCode,
        int $clientCode,
        int $baseCode,
        string $externalConversationId
    ): int {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod008
            FROM n008
            WHERE cod001 = :companyCode
              AND cod003 = :channelCode
              AND cod007 = :clientCode
              AND ide008 = :externalConversationId
            LIMIT 1
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'channelCode' => $channelCode,
            'clientCode' => $clientCode,
            'externalConversationId' => $externalConversationId,
        ]);
        $conversationCode = $statement->fetchColumn();

        if ($conversationCode !== false) {
            return (int) $conversationCode;
        }

        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n008 (cod001, cod007, cod003, cod005, ide008, sts008)
            VALUES (
                :companyCode,
                :clientCode,
                :channelCode,
                :baseCode,
                :externalConversationId,
                'Aberta'
            )
            RETURNING cod008
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'clientCode' => $clientCode,
            'channelCode' => $channelCode,
            'baseCode' => $baseCode,
            'externalConversationId' => $externalConversationId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findIncomingMessageByExternalId(
        int $conversationCode,
        string $externalMessageId
    ): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod009, con009
            FROM n009
            WHERE cod008 = :conversationCode AND ide009 = :externalMessageId
            LIMIT 1
        SQL);
        $statement->execute([
            'conversationCode' => $conversationCode,
            'externalMessageId' => $externalMessageId,
        ]);

        $message = $statement->fetch(PDO::FETCH_ASSOC);

        return $message === false ? null : $message;
    }

    public function createIncomingMessage(
        int $conversationCode,
        string $externalMessageId,
        string $message
    ): int {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n009 (cod008, con009, ori009, tip009, ide009)
            VALUES (:conversationCode, :message, 'Cliente', 'Texto', :externalMessageId)
            RETURNING cod009
        SQL);
        $statement->execute([
            'conversationCode' => $conversationCode,
            'message' => $message,
            'externalMessageId' => $externalMessageId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findChatbotReply(int $incomingMessageCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod009, con009, tok009
            FROM n009
            WHERE ref009 = :incomingMessageCode
              AND ori009 = 'Chatbot'
            LIMIT 1
        SQL);
        $statement->execute([
            'incomingMessageCode' => $incomingMessageCode,
        ]);

        $message = $statement->fetch(PDO::FETCH_ASSOC);

        return $message === false ? null : $message;
    }

    public function isHumanHandling(int $companyCode, int $conversationCode): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM n008
            WHERE cod001 = :companyCode
              AND cod008 = :conversationCode
              AND (
                  sts008 = 'Aguardando'
                  OR (cod002 IS NOT NULL AND sts008 = 'Em Atendimento')
              )
            LIMIT 1
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'conversationCode' => $conversationCode,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function markWaitingForAgent(int $companyCode, int $conversationCode): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            UPDATE n008
            SET sts008 = 'Aguardando'
            WHERE cod001 = :companyCode
              AND cod008 = :conversationCode
              AND cod002 IS NOT NULL
              AND sts008 IN ('Em Atendimento', 'Aguardando')
        SQL);
        $statement->execute(['companyCode' => $companyCode, 'conversationCode' => $conversationCode]);
    }

    public function markForHumanHandoff(int $companyCode, int $conversationCode): void
    {
        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
        $statement = $pdo->prepare(<<<'SQL'
            UPDATE n008
            SET cod002 = NULL,
                sts008 = 'Aguardando'
            WHERE cod001 = :companyCode
              AND cod008 = :conversationCode
              AND sts008 IN ('Aberta', 'Aguardando', 'Em Atendimento')
        SQL);

        $statement->execute([
            'companyCode' => $companyCode,
            'conversationCode' => $conversationCode,
        ]);

        if ($statement->rowCount() === 1) {
            $history = $pdo->prepare(<<<'SQL'
                INSERT INTO n011 (cod008, cod010, mot011, sts011)
                SELECT :conversationCode, q.cod010, 'Encaminhada pela IA para atendimento humano.', 'Pendente'
                FROM n010 q
                WHERE q.cod001 = :companyCode
                  AND q.sts010 = TRUE
                  AND NOT EXISTS (
                      SELECT 1 FROM n011 h
                      WHERE h.cod008 = :historyConversationCode
                        AND h.sts011 IN ('Pendente', 'Aceito')
                  )
                ORDER BY q.pri010, q.cod010
                LIMIT 1
            SQL);
            $history->execute([
                'companyCode' => $companyCode,
                'conversationCode' => $conversationCode,
                'historyConversationCode' => $conversationCode,
            ]);
        }

        $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function createChatbotReply(
        int $conversationCode,
        int $incomingMessageCode,
        string $message,
        ?int $tokens
    ): int {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n009 (cod008, ref009, con009, ori009, tip009, tok009, lid009)
            VALUES (
                :conversationCode,
                :incomingMessageCode,
                :message,
                'Chatbot',
                'Texto',
                :tokens,
                FALSE
            )
            RETURNING cod009
        SQL);
        $statement->execute([
            'conversationCode' => $conversationCode,
            'incomingMessageCode' => $incomingMessageCode,
            'message' => $message,
            'tokens' => $tokens,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findAIContext(int $companyCode, int $baseCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT
                c.url013,
                c.key013,
                c.sts013,
                COALESCE(NULLIF(b.mod005, ''), c.mod013) AS model,
                COALESCE(b.tmp005, c.temp013) AS temperature,
                COALESCE(b.lim005, c.lim013) AS output_limit,
                COALESCE(NULLIF(b.ins005, ''), c.ins013) AS instruction
            FROM n005 b
            INNER JOIN n013 c ON c.cod001 = b.cod001
            WHERE b.cod005 = :baseCode
              AND b.cod001 = :companyCode
              AND b.sts005 = TRUE
            LIMIT 1
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'baseCode' => $baseCode,
        ]);
        $context = $statement->fetch(PDO::FETCH_ASSOC);

        return $context === false ? null : $context;
    }

    public function findPublicKnowledge(int $baseCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT tit006, con006
            FROM n006
            WHERE cod005 = :baseCode
              AND sts006 = TRUE
              AND vis006 = 1
            ORDER BY cod006
            LIMIT 20
        SQL);
        $statement->execute(['baseCode' => $baseCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function baseBelongsToCompany(int $companyCode, int $baseCode): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM n005
            WHERE cod005 = :baseCode
              AND cod001 = :companyCode
              AND sts005 = TRUE
            LIMIT 1
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'baseCode' => $baseCode,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
