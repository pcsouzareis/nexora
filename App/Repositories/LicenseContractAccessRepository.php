<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/** Controla a primeira visualização do contrato por supervisor e empresa. */
final class LicenseContractAccessRepository
{
    public function __construct(private readonly Database $database) {}

    public function isAccepted(int $companyCode, int $userCode): bool
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM n021
            WHERE cod001 = :companyCode
              AND cod002 = :userCode
              AND ace021 IS NOT NULL
            LIMIT 1
        SQL);

        $statement->execute(['companyCode' => $companyCode, 'userCode' => $userCode]);

        return $statement->fetchColumn() !== false;
    }

    public function registerView(int $companyCode, int $userCode): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n021 (cod001, cod002)
            VALUES (:companyCode, :userCode)
            ON CONFLICT (cod001, cod002) DO NOTHING
        SQL);

        $statement->execute(['companyCode' => $companyCode, 'userCode' => $userCode]);
    }

    public function find(int $companyCode, int $userCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod021, cod001, cod002, cad021, ace021, ver021, ip021, pdf021
            FROM n021
            WHERE cod001 = :companyCode
              AND cod002 = :userCode
            LIMIT 1
        SQL);

        $statement->execute(['companyCode' => $companyCode, 'userCode' => $userCode]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function accept(
        int $companyCode,
        int $userCode,
        string $version,
        ?string $ipAddress,
        string $pdfPath,
        string $acceptedAt
    ): void {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n021 (cod001, cod002, ace021, ver021, ip021, pdf021)
            VALUES (:companyCode, :userCode, :acceptedAt, :version, :ipAddress, :pdfPath)
            ON CONFLICT (cod001, cod002) DO UPDATE
            SET ace021 = EXCLUDED.ace021,
                ver021 = EXCLUDED.ver021,
                ip021 = EXCLUDED.ip021,
                pdf021 = EXCLUDED.pdf021
            WHERE n021.ace021 IS NULL
        SQL);

        $statement->execute([
            'companyCode' => $companyCode,
            'userCode' => $userCode,
            'version' => $version,
            'ipAddress' => $ipAddress,
            'pdfPath' => $pdfPath,
            'acceptedAt' => $acceptedAt,
        ]);
    }

    public function pdfPathByAccessCode(int $accessCode): ?string
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT pdf021
            FROM n021
            WHERE cod021 = :accessCode
              AND ace021 IS NOT NULL
              AND pdf021 IS NOT NULL
            LIMIT 1
        SQL);

        $statement->execute(['accessCode' => $accessCode]);
        $path = $statement->fetchColumn();

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function filters(): array
    {
        $pdo = $this->database->pdo();
        $companies = $pdo->query('SELECT cod001, des001 FROM n001 ORDER BY des001')->fetchAll(PDO::FETCH_ASSOC);
        $users = $pdo->query(<<<'SQL'
            SELECT DISTINCT u.cod002, u.des002
            FROM n021 a
            INNER JOIN n002 u ON u.cod002 = a.cod002
            ORDER BY u.des002
        SQL)->fetchAll(PDO::FETCH_ASSOC);

        return ['companies' => $companies, 'users' => $users];
    }

    public function dataTable(
        array $filters,
        int $start,
        int $length,
        string $search,
        int $orderColumn,
        string $orderDirection
    ): array {
        $pdo = $this->database->pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM n021')->fetchColumn();
        [$where, $parameters] = $this->where($filters, $search);
        $whereSql = implode(' AND ', $where);

        $filtered = $pdo->prepare(<<<SQL
            SELECT COUNT(*)
            FROM n021 a
            INNER JOIN n001 e ON e.cod001 = a.cod001
            INNER JOIN n002 u ON u.cod002 = a.cod002
            WHERE {$whereSql}
        SQL);
        $this->execute($filtered, $parameters);

        $columns = [
            0 => 'e.des001', 1 => 'u.des002', 2 => 'a.ace021', 3 => 'a.ver021',
            4 => 'a.cad021', 5 => 'a.ace021', 6 => 'a.ip021', 7 => 'a.pdf021',
        ];
        $order = $columns[$orderColumn] ?? 'a.cad021';
        $direction = strtolower($orderDirection) === 'asc' ? 'ASC' : 'DESC';

        $statement = $pdo->prepare(<<<SQL
            SELECT
                a.cod021,
                e.des001 AS empresa,
                u.des002 AS supervisor,
                CASE WHEN a.ace021 IS NULL THEN 'Pendente' ELSE 'Aceito' END AS status,
                COALESCE(a.ver021, '—') AS versao,
                to_char(a.cad021 AT TIME ZONE 'America/Fortaleza', 'DD/MM/YYYY HH24:MI:SS') AS visualizado_em,
                CASE WHEN a.ace021 IS NULL THEN '—'
                    ELSE to_char(a.ace021 AT TIME ZONE 'America/Fortaleza', 'DD/MM/YYYY HH24:MI:SS') END AS aceito_em,
                COALESCE(a.ip021, '—') AS ip,
                (a.pdf021 IS NOT NULL) AS possui_pdf
            FROM n021 a
            INNER JOIN n001 e ON e.cod001 = a.cod001
            INNER JOIN n002 u ON u.cod002 = a.cod002
            WHERE {$whereSql}
            ORDER BY {$order} {$direction}, a.cod021 DESC
            LIMIT :limit OFFSET :offset
        SQL);
        $this->execute($statement, $parameters, $length, $start);

        return [
            'recordsTotal' => $total,
            'recordsFiltered' => (int) $filtered->fetchColumn(),
            'data' => $statement->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function where(array $filters, string $search): array
    {
        $where = ['1 = 1'];
        $parameters = [];
        $companyCode = (int) ($filters['company'] ?? 0);
        $userCode = (int) ($filters['user'] ?? 0);
        $status = (string) ($filters['status'] ?? '');

        if ($companyCode > 0) {
            $where[] = 'a.cod001 = :companyCode';
            $parameters['companyCode'] = $companyCode;
        }

        if ($userCode > 0) {
            $where[] = 'a.cod002 = :userCode';
            $parameters['userCode'] = $userCode;
        }

        if ($status === 'accepted') {
            $where[] = 'a.ace021 IS NOT NULL';
        } elseif ($status === 'pending') {
            $where[] = 'a.ace021 IS NULL';
        }

        $search = trim($search);

        if ($search !== '') {
            $where[] = '(e.des001 ILIKE :search OR u.des002 ILIKE :search OR a.ver021 ILIKE :search OR a.ip021 ILIKE :search)';
            $parameters['search'] = '%' . mb_substr($search, 0, 120) . '%';
        }

        return [$where, $parameters];
    }

    private function execute(
        \PDOStatement $statement,
        array $parameters,
        ?int $length = null,
        ?int $start = null
    ): void {
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        if ($length !== null) {
            $statement->bindValue('limit', max(1, min(100, $length)), PDO::PARAM_INT);
        }

        if ($start !== null) {
            $statement->bindValue('offset', max(0, $start), PDO::PARAM_INT);
        }

        $statement->execute();
    }
}
