<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class CompanyRepository
{
    public function __construct(
        private readonly Database $database
    ) {}

    public function emailExists(string $email): bool
    {
        $sql = <<<'SQL'
        SELECT 1
        FROM n001
        WHERE ema001 = :ema001
        LIMIT 1
    SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'ema001' => $email,
        ]);

        return $statement->fetchColumn() !== false;
    }
    /**
     * Retorna todas as empresas cadastradas na plataforma.
     *
     * Utilizado pelo Administrador.
     */
    public function findAll(): array
    {
        $sql = <<<'SQL'
        SELECT
            cod001,
            des001,
            doc001,
            ema001,
            tel001,
            log001,
            sts001,
            cri001,
            cad001,
            atu001
        FROM n001
        ORDER BY des001 ASC
    SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todas as empresas da organização informada.
     */
    public function findAllByOrganization(int $cod001): array
    {
        $sql = <<<'SQL'
                SELECT
                    cod001,
                    des001,
                    doc001,
                    ema001,
                    tel001,
                    log001,
                    sts001,
                    cri001,
                    cad001,
                    atu001
                FROM n001
                WHERE cod001 = :cod001
                ORDER BY des001 ASC
            SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'cod001' => $cod001,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna uma empresa pelo código.
     */
    public function findByCode(int $code): ?array
    {
        $sql = <<<'SQL'
                SELECT
                    cod001,
                    des001,
                    doc001,
                    ema001,
                    tel001,
                    log001,
                    sts001,
                    cri001,
                    cad001,
                    atu001
                FROM n001
                WHERE cod001 = :code
                LIMIT 1
            SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'code' => $code,
        ]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function findAllCreatedBy(int $creatorCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod001, des001, doc001, ema001, tel001, log001, sts001,
                   cri001, cad001, atu001
            FROM n001
            WHERE cri001 = :creatorCode
            ORDER BY des001 ASC
        SQL);

        $statement->execute(['creatorCode' => $creatorCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se o e-mail já pertence a outra empresa.
     */
    public function emailExistsForAnotherCompany(
        string $email,
        int $cod001
    ): bool {
        $sql = <<<'SQL'
        SELECT 1
        FROM n001
        WHERE ema001 = :ema001
          AND cod001 <> :cod001
        LIMIT 1
    SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'ema001' => $email,
            'cod001' => $cod001,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Atualiza os dados de uma empresa.
     */
    public function update(int $cod001, array $data): bool
    {
        $sql = <<<'SQL'
        UPDATE n001
        SET
            des001 = :des001,
            doc001 = :doc001,
            ema001 = :ema001,
            tel001 = :tel001,
            log001 = :log001,
            sts001 = :sts001,
            atu001 = CURRENT_TIMESTAMP
        WHERE cod001 = :cod001
    SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->bindValue('cod001', $cod001, PDO::PARAM_INT);
        $statement->bindValue('des001', $data['des001'], PDO::PARAM_STR);
        $statement->bindValue(
            'doc001',
            $data['doc001'] ?? null,
            isset($data['doc001']) ? PDO::PARAM_STR : PDO::PARAM_NULL
        );
        $statement->bindValue('ema001', $data['ema001'], PDO::PARAM_STR);
        $statement->bindValue(
            'tel001',
            $data['tel001'] ?? null,
            isset($data['tel001']) ? PDO::PARAM_STR : PDO::PARAM_NULL
        );
        $statement->bindValue(
            'log001',
            $data['log001'] ?? null,
            isset($data['log001']) ? PDO::PARAM_STR : PDO::PARAM_NULL
        );
        $statement->bindValue(
            'sts001',
            (bool) ($data['sts001'] ?? true),
            PDO::PARAM_BOOL
        );

        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /**
     * Cria uma nova empresa.
     *
     * Retorna o código gerado pelo PostgreSQL.
     */
    public function create(array $data): int
    {
        $sql = <<<'SQL'
        INSERT INTO n001 (
            des001,
            doc001,
            ema001,
            tel001,
            log001,
            sts001,
            cri001
        )
        VALUES (
            :des001,
            :doc001,
            :ema001,
            :tel001,
            :log001,
            :sts001,
            :cri001
        )
        RETURNING cod001
    SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'des001' => $data['des001'],
            'doc001' => $data['doc001'] ?? null,
            'ema001' => $data['ema001'],
            'tel001' => $data['tel001'] ?? null,
            'log001' => $data['log001'] ?? null,
            'sts001' => (bool) ($data['sts001'] ?? false),
            'cri001' => $data['cri001'] ?? null,
        ]);

        return (int) $statement->fetchColumn();
    }
}
