<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class TelegramChannelRepository
{
    public function __construct(private readonly Database $database) {}

    public function findByCompany(int $companyCode, int $channelCode): ?array
    {
        $statement = $this->database->pdo()->prepare("SELECT cod003, cod001, cod005, sts003, bot003, upt003, outtel003 FROM n003 WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 = 'Telegram' LIMIT 1");
        $statement->execute(['companyCode' => $companyCode, 'channelCode' => $channelCode]);
        $channel = $statement->fetch(PDO::FETCH_ASSOC);
        return $channel === false ? null : $channel;
    }

    public function findAllActive(): array
    {
        $statement = $this->database->pdo()->query(<<<'SQL'
            SELECT cod003, cod001, cod005, sts003, bot003, upt003, outtel003
            FROM n003
            WHERE tip003 = 'Telegram' AND sts003 = TRUE AND bot003 IS NOT NULL AND bot003 <> ''
            ORDER BY cod001, cod003
        SQL);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tryAcquireSyncLock(): bool
    {
        return (bool) $this->database->pdo()->query("SELECT pg_try_advisory_lock(hashtext('nexora:telegram-sync'))")->fetchColumn();
    }

    public function releaseSyncLock(): void
    {
        $this->database->pdo()->query("SELECT pg_advisory_unlock(hashtext('nexora:telegram-sync'))");
    }

    public function save(int $companyCode, int $channelCode, ?string $encryptedToken, bool $outbound): void
    {
        $statement = $this->database->pdo()->prepare("UPDATE n003 SET bot003 = COALESCE(:token, bot003), outtel003 = :outbound, atu003 = CURRENT_TIMESTAMP WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 = 'Telegram'");
        $statement->execute(['companyCode' => $companyCode, 'channelCode' => $channelCode, 'token' => $encryptedToken, 'outbound' => $outbound ? 'true' : 'false']);
    }

    public function setLastUpdate(int $companyCode, int $channelCode, int $updateId): void
    {
        $statement = $this->database->pdo()->prepare("UPDATE n003 SET upt003 = :updateId, atu003 = CURRENT_TIMESTAMP WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 = 'Telegram'");
        $statement->execute(['companyCode' => $companyCode, 'channelCode' => $channelCode, 'updateId' => $updateId]);
    }
}
