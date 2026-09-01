<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class ProfileRepository
{
    public function __construct(
        private readonly Database $database
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $statement = $this->database->pdo()->query(<<<'SQL'
            SELECT cod014, des014, ace014
            FROM n014
            ORDER BY des014
        SQL);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(int $code): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod014, des014, ace014
            FROM n014
            WHERE cod014 = :code
            LIMIT 1
        SQL);

        $statement->execute(['code' => $code]);

        $profile = $statement->fetch(PDO::FETCH_ASSOC);

        return $profile === false ? null : $profile;
    }
}
