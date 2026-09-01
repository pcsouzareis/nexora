<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class AIConfigurationRepository
{
    public function __construct(
        private readonly Database $database
    ) {}

    public function findByCompany(int $companyCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT
                cod013,
                cod001,
                temp013,
                msg013,
                msgfim013,
                sts013,
                mod013,
                ins013,
                lim013,
                lms013,
                lmi013,
                jan013,
                url013,
                CASE
                    WHEN key013 IS NULL OR key013 = '' THEN 0
                    ELSE 1
                END AS key_configured
            FROM n013
            WHERE cod001 = :companyCode
            LIMIT 1
        SQL);

        $statement->execute([
            'companyCode' => $companyCode,
        ]);

        $configuration = $statement->fetch(PDO::FETCH_ASSOC);

        return $configuration === false ? null : $configuration;
    }

    public function findConnectionByCompany(int $companyCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod001, url013, key013, mod013, sts013
            FROM n013
            WHERE cod001 = :companyCode
            LIMIT 1
        SQL);
        $statement->execute(['companyCode' => $companyCode]);

        $configuration = $statement->fetch(PDO::FETCH_ASSOC);

        return $configuration === false ? null : $configuration;
    }

    public function save(
        int $companyCode,
        array $data,
        ?string $encryptedKey
    ): void {
        $configuration = $this->findByCompany($companyCode);

        if ($configuration === null) {
            $statement = $this->database->pdo()->prepare(<<<'SQL'
                INSERT INTO n013 (
                    cod001,
                    temp013,
                    msg013,
                    msgfim013,
                    sts013,
                    mod013,
                    ins013,
                    lim013,
                    lms013,
                    lmi013,
                    jan013,
                    url013,
                    key013
                ) VALUES (
                    :companyCode,
                    :temperature,
                    :welcome,
                    :farewell,
                    :active,
                    :model,
                    :instruction,
                    :limit,
                    :sessionLimit,
                    :ipLimit,
                    :windowMinutes,
                    :url,
                    :key
                )
            SQL);
        } else {
            $statement = $this->database->pdo()->prepare(<<<'SQL'
                UPDATE n013
                SET
                    temp013 = :temperature,
                    msg013 = :welcome,
                    msgfim013 = :farewell,
                    sts013 = :active,
                    mod013 = :model,
                    ins013 = :instruction,
                    lim013 = :limit,
                    lms013 = :sessionLimit,
                    lmi013 = :ipLimit,
                    jan013 = :windowMinutes,
                    url013 = :url,
                    key013 = COALESCE(:key, key013),
                    atu013 = CURRENT_TIMESTAMP
                WHERE cod001 = :companyCode
            SQL);
        }

        $statement->execute([
            'companyCode' => $companyCode,
            'temperature' => $data['temperature'],
            'welcome' => $data['welcome'] ?: null,
            'farewell' => $data['farewell'] ?: null,
            'active' => $data['active'] ? 'true' : 'false',
            'model' => $data['model'],
            'instruction' => $data['instruction'] ?: null,
            'limit' => $data['limit'],
            'sessionLimit' => $data['session_limit'],
            'ipLimit' => $data['ip_limit'],
            'windowMinutes' => $data['window_minutes'],
            'url' => $data['url'],
            'key' => $encryptedKey,
        ]);
    }
}
