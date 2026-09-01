<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class WebchatRateLimitRepository
{
    public function __construct(private readonly Database $database) {}

    public function allows(int $companyCode, int $channelCode, string $sessionId, string $ip): bool
    {
        $configuration = $this->configuration($companyCode);
        $keys = [
            'webchat:' . $channelCode . ':ip:' . $ip => $configuration['ip_limit'],
            'webchat:' . $channelCode . ':session:' . $sessionId => $configuration['session_limit'],
        ];
        ksort($keys);

        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:key))');
            $count = $pdo->prepare(<<<'SQL'
                SELECT COALESCE(SUM(qtd016), 0)
                FROM n016
                WHERE chv016 = :key
                  AND jan016 >= CURRENT_TIMESTAMP - (:windowMinutes * INTERVAL '1 minute')
            SQL);

            foreach ($keys as $key => $limit) {
                $lock->execute(['key' => $key]);
                $count->execute(['key' => $key, 'windowMinutes' => $configuration['window_minutes']]);
                if ((int) $count->fetchColumn() >= $limit) {
                    $pdo->commit();
                    return false;
                }
            }

            $increment = $pdo->prepare(<<<'SQL'
                INSERT INTO n016 (chv016, jan016, qtd016)
                VALUES (:key, date_trunc('minute', CURRENT_TIMESTAMP), 1)
                ON CONFLICT (chv016, jan016)
                DO UPDATE SET qtd016 = n016.qtd016 + 1
            SQL);
            foreach (array_keys($keys) as $key) {
                $increment->execute(['key' => $key]);
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

    /** @return array{session_limit: int, ip_limit: int, window_minutes: int} */
    private function configuration(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT COALESCE(lms013, 8), COALESCE(lmi013, 25), COALESCE(jan013, 5)
            FROM n013 WHERE cod001 = :companyCode LIMIT 1
        SQL);
        $statement->execute(['companyCode' => $companyCode]);
        $configuration = $statement->fetch(\PDO::FETCH_NUM);

        return [
            'session_limit' => max(1, min(100, (int) ($configuration[0] ?? 8))),
            'ip_limit' => max(1, min(500, (int) ($configuration[1] ?? 25))),
            'window_minutes' => max(1, min(60, (int) ($configuration[2] ?? 5))),
        ];
    }
}
