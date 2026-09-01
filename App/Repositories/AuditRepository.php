<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class AuditRepository
{
    public function __construct(private readonly Database $database) {}

    public function record(?int $companyCode, ?int $userCode, string $action, string $entity, ?int $reference, string $description, ?string $ip): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n017 (cod001, cod002, aca017, ent017, ref017, des017, ip017)
            VALUES (:companyCode, :userCode, :action, :entity, :reference, :description, :ip)
        SQL);
        $statement->execute([
            'companyCode' => $companyCode, 'userCode' => $userCode,
            'action' => mb_substr($action, 0, 60), 'entity' => mb_substr($entity, 0, 60),
            'reference' => $reference, 'description' => $description, 'ip' => $ip === null ? null : mb_substr($ip, 0, 45),
        ]);
    }

    public function findAllByCompany(int $companyCode, int $limit = 200): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT a.cod017, a.aca017, a.ent017, a.ref017, a.des017, a.ip017, a.cad017,
                   u.des002 AS usuario
            FROM n017 a
            LEFT JOIN n002 u ON u.cod002 = a.cod002
            WHERE a.cod001 = :companyCode
            ORDER BY a.cad017 DESC, a.cod017 DESC
            LIMIT :limit
        SQL);
        $statement->bindValue('companyCode', $companyCode, PDO::PARAM_INT);
        $statement->bindValue('limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function filtersByCompany(int $companyCode): array
    {
        $pdo = $this->database->pdo();
        $actions = $pdo->prepare('SELECT DISTINCT aca017 FROM n017 WHERE cod001 = :companyCode ORDER BY aca017');
        $actions->execute(['companyCode' => $companyCode]);
        $entities = $pdo->prepare('SELECT DISTINCT ent017 FROM n017 WHERE cod001 = :companyCode ORDER BY ent017');
        $entities->execute(['companyCode' => $companyCode]);
        $users = $pdo->prepare(<<<'SQL'
            SELECT DISTINCT u.cod002, u.des002
            FROM n017 a INNER JOIN n002 u ON u.cod002 = a.cod002
            WHERE a.cod001 = :companyCode ORDER BY u.des002
        SQL);
        $users->execute(['companyCode' => $companyCode]);
        return ['actions' => $actions->fetchAll(PDO::FETCH_COLUMN), 'entities' => $entities->fetchAll(PDO::FETCH_COLUMN), 'users' => $users->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function dataTable(int $companyCode, array $filters, int $start, int $length, string $search, int $orderColumn, string $orderDirection): array
    {
        $pdo = $this->database->pdo();
        $total = $pdo->prepare('SELECT COUNT(*) FROM n017 WHERE cod001 = :companyCode');
        $total->execute(['companyCode' => $companyCode]);
        [$where, $params] = $this->where($companyCode, $filters, $search);
        $whereSql = implode(' AND ', $where);
        $filtered = $pdo->prepare('SELECT COUNT(*) FROM n017 a LEFT JOIN n002 u ON u.cod002 = a.cod002 WHERE ' . $whereSql);
        $this->execute($filtered, $params);
        $orderColumns = [0 => 'a.cad017', 1 => 'u.des002', 2 => 'a.aca017', 3 => 'a.ent017', 4 => 'a.des017', 5 => 'a.ip017'];
        $order = $orderColumns[$orderColumn] ?? 'a.cad017';
        $direction = strtolower($orderDirection) === 'asc' ? 'ASC' : 'DESC';
        $statement = $pdo->prepare(<<<SQL
            SELECT a.cod017, a.aca017, a.ent017, a.ref017, a.des017, a.ip017,
                   to_char(a.cad017 AT TIME ZONE 'America/Fortaleza', 'DD/MM/YYYY HH24:MI:SS') AS data,
                   COALESCE(u.des002, 'Sistema') AS usuario
            FROM n017 a LEFT JOIN n002 u ON u.cod002 = a.cod002
            WHERE {$whereSql}
            ORDER BY {$order} {$direction}, a.cod017 DESC
            LIMIT :limit OFFSET :offset
        SQL);
        $this->execute($statement, $params, $length, $start);
        return ['recordsTotal' => (int) $total->fetchColumn(), 'recordsFiltered' => (int) $filtered->fetchColumn(), 'data' => $statement->fetchAll(PDO::FETCH_ASSOC)];
    }

    private function where(int $companyCode, array $filters, string $search): array
    {
        $where = ['a.cod001 = :companyCode']; $params = ['companyCode' => $companyCode];
        foreach (['action' => 'aca017', 'entity' => 'ent017'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "a.{$column} = :{$key}"; $params[$key] = mb_substr($value, 0, 60); }
        }
        $user = (int) ($filters['user'] ?? 0);
        if ($user > 0) { $where[] = 'a.cod002 = :user'; $params['user'] = $user; }
        foreach (['from', 'until'] as $key) {
            $date = (string) ($filters[$key] ?? '');
            if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) === 1) { $where[] = $key === 'from' ? 'a.cad017 >= CAST(:from AS date)' : "a.cad017 < CAST(:until AS date) + INTERVAL '1 day'"; $params[$key] = $date; }
        }
        $search = trim($search);
        if ($search !== '') {
            $where[] = '(a.des017 ILIKE :searchDescription OR a.aca017 ILIKE :searchAction OR a.ent017 ILIKE :searchEntity OR u.des002 ILIKE :searchUser)';
            $term = '%' . mb_substr($search, 0, 120) . '%';
            $params += ['searchDescription' => $term, 'searchAction' => $term, 'searchEntity' => $term, 'searchUser' => $term];
        }
        return [$where, $params];
    }

    private function execute(\PDOStatement $statement, array $params, ?int $limit = null, ?int $offset = null): void
    {
        foreach ($params as $key => $value) $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        if ($limit !== null) $statement->bindValue('limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        if ($offset !== null) $statement->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
    }
}
