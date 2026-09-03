<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class UserRepository
{
    public function __construct(
        private readonly Database $database
    ) {}

    /**
     * Retorna todos os usuários.
     *
     * Uso exclusivo do Administrador.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = <<<'SQL'
            SELECT
                u.cod002,
                u.cod001,
                u.cod014,
                u.des002,
                u.ema002,
                u.rol002,
                u.sts002,
                u.cri002,
                u.cad002,
                u.atu002,
                e.des001,
                p.des014,
                p.ace014
            FROM n002 u
            INNER JOIN n001 e
                ON e.cod001 = u.cod001
            INNER JOIN n014 p
                ON p.cod014 = u.cod014
            ORDER BY
                e.des001,
                u.des002
        SQL;

        $statement = $this->database->pdo()->query($sql);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os usuários de uma empresa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllByCompany(int $companyCode): array
    {
        $sql = <<<'SQL'
            SELECT
                u.cod002,
                u.cod001,
                u.cod014,
                u.des002,
                u.ema002,
                u.rol002,
                u.sts002,
                u.cri002,
                u.cad002,
                u.atu002,
                e.des001,
                p.des014,
                p.ace014
            FROM n002 u
            INNER JOIN n001 e
                ON e.cod001 = u.cod001
            INNER JOIN n014 p
                ON p.cod014 = u.cod014
            WHERE u.cod001 = :companyCode
            ORDER BY u.des002
        SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'companyCode' => $companyCode,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna um usuário pelo código.
     */
    public function findByCode(int $code): ?array
    {
        $sql = <<<'SQL'
        SELECT
            u.cod002,
            u.cod001,
            u.cod014,
            u.des002,
            u.ema002,
            u.rol002,
            u.sen002,
            u.sts002,
            u.cri002,
            u.cad002,
            u.atu002,
            e.des001,
            p.des014,
            p.ace014,
            c.cod013,
            c.sts013
        FROM n002 u
        INNER JOIN n001 e
            ON e.cod001 = u.cod001
        INNER JOIN n014 p
            ON p.cod014 = u.cod014
        LEFT JOIN n013 c
            ON c.cod001 = u.cod001
        WHERE u.cod002 = :code
        LIMIT 1
    SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'code' => $code,
        ]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        /*
     * Mantém o nome do perfil disponível
     * para as telas atuais.
     */
        $result['perfil_name'] = $result['des014'];

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function findAllCreatedBy(int $creatorCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT
                u.cod002, u.cod001, u.cod014, u.des002, u.ema002, u.rol002,
                u.sts002, u.cri002, u.cad002, u.atu002, e.des001,
                p.des014, p.ace014
            FROM n002 u
            INNER JOIN n001 e ON e.cod001 = u.cod001
            INNER JOIN n014 p ON p.cod014 = u.cod014
            WHERE u.cri002 = :creatorCode
            ORDER BY e.des001, u.des002
        SQL);

        $statement->execute(['creatorCode' => $creatorCode]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se o e-mail já existe na empresa.
     */
    public function emailExists(
        int $companyCode,
        string $email
    ): bool {
        $sql = <<<'SQL'
            SELECT 1
            FROM n002
            WHERE cod001 = :companyCode
              AND LOWER(ema002) = LOWER(:email)
            LIMIT 1
        SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'companyCode' => $companyCode,
            'email' => $email,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Verifica se o e-mail pertence a outro usuário da empresa.
     */
    public function emailExistsForAnotherUser(
        int $companyCode,
        string $email,
        int $userCode
    ): bool {
        $sql = <<<'SQL'
            SELECT 1
            FROM n002
            WHERE cod001 = :companyCode
              AND LOWER(ema002) = LOWER(:email)
              AND cod002 <> :userCode
            LIMIT 1
        SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'companyCode' => $companyCode,
            'email' => $email,
            'userCode' => $userCode,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function findProfileCodeForRole(string $role): ?int
    {
        $profiles = [
            'D' => 'Administrador',
            'S' => 'Supervisor',
            'A' => 'Atendente',
        ];

        if (!isset($profiles[$role])) {
            return null;
        }

        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod014
            FROM n014
            WHERE des014 = :description
            LIMIT 1
        SQL);

        $statement->execute(['description' => $profiles[$role]]);
        $profileCode = $statement->fetchColumn();

        return $profileCode === false ? null : (int) $profileCode;
    }

    /**
     * Cadastra um usuário e retorna seu código.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO n002 (
                cod001,
                cod014,
                des002,
                ema002,
                rol002,
                sts002,
                cri002
            ) VALUES (
                :cod001,
                :cod014,
                :des002,
                :ema002,
                :rol002,
                :sts002,
                :cri002
            )
            RETURNING cod002
        SQL;

        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare($sql);

            $statement->execute([
                'cod001' => $data['cod001'],
                'cod014' => $data['cod014'],
                'des002' => $data['des002'],
                'ema002' => $data['ema002'],
                'rol002' => $data['rol002'],
                'sts002' => $data['sts002'] ? 'true' : 'false',
                'cri002' => $data['cri002'] ?? null,
            ]);

            $code = (int) $statement->fetchColumn();

            $passwordStatement = $pdo->prepare(<<<'SQL'
                UPDATE n002
                SET sen002 = :password
                WHERE cod002 = :code
            SQL);

            $passwordStatement->execute([
                'password' => password_hash((string) $code, PASSWORD_DEFAULT),
                'code' => $code,
            ]);

            $pdo->commit();

            return $code;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Atualiza um usuário.
     *
     * A senha somente é alterada quando um novo hash é informado.
     *
     * @param array<string, mixed> $data
     */
    public function update(
        int $code,
        array $data,
        ?string $passwordHash = null
    ): void {
        $sql = <<<'SQL'
            UPDATE n002
            SET
                cod001 = :cod001,
                cod014 = :cod014,
                des002 = :des002,
                ema002 = :ema002,
                rol002 = :rol002,
                sts002 = :sts002,
                atu002 = CURRENT_TIMESTAMP
        SQL;

        $parameters = [
            'cod001' => $data['cod001'],
            'cod014' => $data['cod014'],
            'des002' => $data['des002'],
            'ema002' => $data['ema002'],
            'rol002' => $data['rol002'],
            'sts002' => $data['sts002'] ? 'true' : 'false',
            'cod002' => $code,
        ];

        if ($passwordHash !== null) {
            $sql .= ', sen002 = :sen002';
            $parameters['sen002'] = $passwordHash;
        }

        $sql .= ' WHERE cod002 = :cod002';

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($parameters);
    }

    public function updatePassword(int $code, string $passwordHash): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            UPDATE n002
            SET sen002 = :password, atu002 = CURRENT_TIMESTAMP
            WHERE cod002 = :code
        SQL);

        $statement->execute([
            'password' => $passwordHash,
            'code' => $code,
        ]);
    }

    /**
     * Atualiza a data do último acesso.
     */
    public function updateLastAccess(int $credentialCode): void
    {
        $sql = <<<'SQL'
            UPDATE n013
            SET
                ult013 = CURRENT_TIMESTAMP,
                atu013 = CURRENT_TIMESTAMP
            WHERE cod013 = :code
        SQL;

        $statement = $this->database->pdo()->prepare($sql);

        $statement->execute([
            'code' => $credentialCode,
        ]);
    }
}
