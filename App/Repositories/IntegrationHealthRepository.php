<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class IntegrationHealthRepository
{
    public function __construct(private readonly Database $database) {}

    public function record(int $companyCode, int $channelCode, string $type, string $status, ?string $description = null): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n019 (cod001, cod003, tip019, sts019, des019)
            VALUES (:companyCode, :channelCode, :type, :status, :description)
        SQL);

        $statement->execute([
            'companyCode' => $companyCode,
            'channelCode' => $channelCode,
            'type' => mb_substr($type, 0, 30),
            'status' => $status === 'Falha' ? 'Falha' : 'Sucesso',
            'description' => $description === null ? null : mb_substr($description, 0, 1000),
        ]);
    }
}
