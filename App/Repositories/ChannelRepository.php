<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class ChannelRepository
{
    public function __construct(private readonly Database $database) {}

    public function findAllByCompany(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT c.cod003, c.des003, c.tip003, c.cod005, c.pub003, c.sts003, c.ins003, c.out003, c.pag003, c.outmet003, c.imh003, c.imp003, c.ime003, c.imu003, c.smh003, c.smp003, c.sme003, c.smu003, c.outema003, c.outtel003,
                   b.des005 AS base,
                   CASE WHEN c.api003 IS NULL OR c.api003 = '' THEN FALSE ELSE TRUE END AS webhook_configured,
                   CASE WHEN c.tok003 IS NULL OR c.tok003 = '' OR c.cli003 IS NULL OR c.cli003 = '' THEN FALSE ELSE TRUE END AS zapi_configured,
                   CASE WHEN c.met003 IS NULL OR c.met003 = '' OR c.sec003 IS NULL OR c.sec003 = '' THEN FALSE ELSE TRUE END AS meta_configured,
                   CASE WHEN c.imh003 IS NULL OR c.imw003 IS NULL OR c.smh003 IS NULL OR c.smw003 IS NULL THEN FALSE ELSE TRUE END AS email_configured,
                   CASE WHEN c.bot003 IS NULL OR c.bot003 = '' THEN FALSE ELSE TRUE END AS telegram_configured
            FROM n003 c
            LEFT JOIN n005 b ON b.cod005 = c.cod005
            WHERE c.cod001 = :companyCode
            ORDER BY c.des003
        SQL);
        $statement->execute(['companyCode' => $companyCode]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByCompany(int $companyCode, int $channelCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT c.cod003, c.cod001, c.des003, c.tip003, c.cod005, c.pub003, c.sts003, c.ins003, c.out003, c.pag003, c.outmet003, c.imh003, c.imp003, c.ime003, c.imu003, c.smh003, c.smp003, c.sme003, c.smu003, c.outema003, c.outtel003,
                   b.des005 AS base,
                   CASE WHEN c.api003 IS NULL OR c.api003 = '' THEN FALSE ELSE TRUE END AS webhook_configured,
                   CASE WHEN c.tok003 IS NULL OR c.tok003 = '' OR c.cli003 IS NULL OR c.cli003 = '' THEN FALSE ELSE TRUE END AS zapi_configured,
                   CASE WHEN c.met003 IS NULL OR c.met003 = '' OR c.sec003 IS NULL OR c.sec003 = '' THEN FALSE ELSE TRUE END AS meta_configured,
                   CASE WHEN c.imh003 IS NULL OR c.imw003 IS NULL OR c.smh003 IS NULL OR c.smw003 IS NULL THEN FALSE ELSE TRUE END AS email_configured,
                   CASE WHEN c.bot003 IS NULL OR c.bot003 = '' THEN FALSE ELSE TRUE END AS telegram_configured
            FROM n003 c
            LEFT JOIN n005 b ON b.cod005 = c.cod005
            WHERE c.cod001 = :companyCode AND c.cod003 = :channelCode
            LIMIT 1
        SQL);
        $statement->execute(['companyCode' => $companyCode, 'channelCode' => $channelCode]);
        $channel = $statement->fetch(PDO::FETCH_ASSOC);
        return $channel === false ? null : $channel;
    }

    public function findActiveBasesByCompany(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod005, des005 FROM n005
            WHERE cod001 = :companyCode AND sts005 = TRUE
            ORDER BY des005
        SQL);
        $statement->execute(['companyCode' => $companyCode]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActiveZapiByToken(string $token): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod003, cod001, cod005, des003
            FROM n003
            WHERE pub003 = :token AND tip003 = 'WhatsApp' AND sts003 = TRUE
            LIMIT 1
        SQL);
        $statement->execute(['token' => $token]);
        $channel = $statement->fetch(PDO::FETCH_ASSOC);
        return $channel === false ? null : $channel;
    }

    public function baseBelongsToCompany(int $companyCode, ?int $baseCode): bool
    {
        if ($baseCode === null) return true;
        $statement = $this->database->pdo()->prepare('SELECT 1 FROM n005 WHERE cod005 = :baseCode AND cod001 = :companyCode LIMIT 1');
        $statement->execute(['companyCode' => $companyCode, 'baseCode' => $baseCode]);
        return $statement->fetchColumn() !== false;
    }

    public function create(int $companyCode, array $data, ?string $webhookHash, ?string $publicToken): int
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n003 (cod001, des003, tip003, cod005, api003, pub003, sts003, ins003, tok003, cli003, out003)
            VALUES (:companyCode, :description, :type, :baseCode, :webhookHash, :publicToken, :active, :instance, :zapiToken, :zapiClientToken, :outbound)
            RETURNING cod003
        SQL);
        $statement->execute([
            'companyCode' => $companyCode,
            'description' => $data['description'],
            'type' => $data['type'],
            'baseCode' => $data['base_code'],
            'webhookHash' => $webhookHash,
            'publicToken' => $publicToken,
            'active' => $data['active'] ? 'true' : 'false',
            'instance' => $data['type'] === 'WhatsApp' ? $data['zapi_instance'] : null,
            'zapiToken' => $data['zapi_token_encrypted'] ?? null,
            'zapiClientToken' => $data['zapi_client_token_encrypted'] ?? null,
            'outbound' => $data['type'] === 'WhatsApp' && $data['zapi_enabled'] ? 'true' : 'false',
        ]);
        return (int) $statement->fetchColumn();
    }

    public function update(int $companyCode, int $channelCode, array $data): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            UPDATE n003
            SET des003 = :description, tip003 = :type, cod005 = :baseCode, sts003 = :active, atu003 = CURRENT_TIMESTAMP
            WHERE cod001 = :companyCode AND cod003 = :channelCode
        SQL);
        $statement->execute([
            'companyCode' => $companyCode, 'channelCode' => $channelCode,
            'description' => $data['description'], 'type' => $data['type'],
            'baseCode' => $data['base_code'], 'active' => $data['active'] ? 'true' : 'false',
        ]);
        return $statement->rowCount() === 1;
    }

    public function replaceWebhookHash(int $companyCode, int $channelCode, string $hash): bool
    {
        $statement = $this->database->pdo()->prepare('UPDATE n003 SET api003 = :hash, atu003 = CURRENT_TIMESTAMP WHERE cod001 = :companyCode AND cod003 = :channelCode');
        $statement->execute(['hash' => $hash, 'companyCode' => $companyCode, 'channelCode' => $channelCode]);
        return $statement->rowCount() === 1;
    }

    public function replacePublicToken(int $companyCode, int $channelCode, string $token): bool
    {
        $statement = $this->database->pdo()->prepare('UPDATE n003 SET pub003 = :token, atu003 = CURRENT_TIMESTAMP WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 = \'Web\'');
        $statement->execute(['token' => $token, 'companyCode' => $companyCode, 'channelCode' => $channelCode]);
        return $statement->rowCount() === 1;
    }

    public function updateZapiConfiguration(int $companyCode, int $channelCode, string $instance, ?string $token, ?string $clientToken, bool $enabled): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            UPDATE n003
            SET ins003 = :instance,
                tok003 = COALESCE(:token, tok003),
                cli003 = COALESCE(:clientToken, cli003),
                out003 = :enabled,
                atu003 = CURRENT_TIMESTAMP
            WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 = 'WhatsApp'
        SQL);
        $statement->execute([
            'companyCode' => $companyCode, 'channelCode' => $channelCode, 'instance' => $instance,
            'token' => $token, 'clientToken' => $clientToken, 'enabled' => $enabled ? 'true' : 'false',
        ]);
        return $statement->rowCount() === 1;
    }

    public function ensureZapiWebhookToken(int $companyCode, int $channelCode, string $token): void
    {
        $statement = $this->database->pdo()->prepare("UPDATE n003 SET pub003 = COALESCE(NULLIF(pub003, ''), :token) WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 = 'WhatsApp'");
        $statement->execute(['companyCode' => $companyCode, 'channelCode' => $channelCode, 'token' => $token]);
    }

    public function ensureFacebookWebhookToken(int $companyCode, int $channelCode, string $token): void
    {
        $statement = $this->database->pdo()->prepare("UPDATE n003 SET pub003 = COALESCE(NULLIF(pub003, ''), :token) WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 IN ('Facebook', 'Instagram')");
        $statement->execute(['companyCode' => $companyCode, 'channelCode' => $channelCode, 'token' => $token]);
    }
}
