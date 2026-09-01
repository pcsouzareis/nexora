<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class KnowledgeRepository
{
    public function __construct(private readonly Database $database) {}

    public function findBasesByCompany(int $companyCode): array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT
                b.cod005,
                b.des005,
                b.sts005,
                b.cad005,
                c.des001 AS empresa,
                COUNT(a.cod006) AS artigos
            FROM n005 b
            INNER JOIN n001 c ON c.cod001 = b.cod001
            LEFT JOIN n006 a ON a.cod005 = b.cod005 AND a.sts006 = TRUE
            WHERE b.cod001 = :companyCode
            GROUP BY b.cod005, c.cod001, c.des001
            ORDER BY b.des005
        SQL);
        $statement->execute(['companyCode' => $companyCode]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    

    public function findBase(int $companyCode, int $baseCode): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM n005 WHERE cod005 = :baseCode AND cod001 = :companyCode LIMIT 1');
        $statement->execute(['baseCode' => $baseCode, 'companyCode' => $companyCode]);
        $base = $statement->fetch(PDO::FETCH_ASSOC);
        return $base === false ? null : $base;
    }

    public function findBaseForN8n(int $baseCode): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT cod005, cod001, sts005, nkh005 FROM n005 WHERE cod005 = :baseCode LIMIT 1');
        $statement->execute(['baseCode' => $baseCode]);
        $base = $statement->fetch(PDO::FETCH_ASSOC);
        return $base === false ? null : $base;
    }

    public function updateN8nKeyHash(int $companyCode, int $baseCode, string $hash): void
    {
        $statement = $this->database->pdo()->prepare('UPDATE n005 SET nkh005 = :hash, atu005 = CURRENT_TIMESTAMP WHERE cod005 = :baseCode AND cod001 = :companyCode');
        $statement->execute(['hash' => $hash, 'baseCode' => $baseCode, 'companyCode' => $companyCode]);
    }

public function createBase(
    int $companyCode,
    string $description,
    bool $active
): int {
    $statement = $this->database->pdo()->prepare(
        'INSERT INTO n005 (cod001, des005, sts005)
         VALUES (:companyCode, :description, :active)
         RETURNING cod005'
    );

    $statement->execute([
        'companyCode' => $companyCode,
        'description' => $description,
        'active' => $active ? 'true' : 'false',
    ]);

    return (int) $statement->fetchColumn();
}

public function updateBaseStatus(
    int $companyCode,
    int $baseCode,
    bool $active
): void {
    $statement = $this->database->pdo()->prepare(<<<'SQL'
        UPDATE n005
        SET
            sts005 = :active,
            atu005 = CURRENT_TIMESTAMP
        WHERE cod005 = :baseCode
          AND cod001 = :companyCode
    SQL);

    $statement->execute([
        'active' => $active ? 'true' : 'false',
        'baseCode' => $baseCode,
        'companyCode' => $companyCode,
    ]);
}

public function updateBaseAiConfiguration(
    int $companyCode,
    int $baseCode,
    array $data
): void {
    $statement = $this->database->pdo()->prepare(<<<'SQL'
        UPDATE n005
        SET
            mod005 = :model,
            tmp005 = :temperature,
            lim005 = :limit,
            ins005 = :instruction,
            msg005 = :welcome,
            msgfim005 = :farewell,
            atu005 = CURRENT_TIMESTAMP
        WHERE cod005 = :baseCode
          AND cod001 = :companyCode
    SQL);

    $statement->execute([
        'companyCode' => $companyCode,
        'baseCode' => $baseCode,
        'model' => $data['model'],
        'temperature' => $data['temperature'],
        'limit' => $data['limit'],
        'instruction' => $data['instruction'],
        'welcome' => $data['welcome'],
        'farewell' => $data['farewell'],
    ]);
}

    public function findArticles(int $baseCode): array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM n006 WHERE cod005 = :baseCode ORDER BY tit006');
        $statement->execute(['baseCode' => $baseCode]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createArticle(int $baseCode, array $data): int
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n006 (cod005, tit006, con006, url006, vis006, sts006)
            VALUES (:baseCode, :title, :content, :url, :visibility, :active)
            RETURNING cod006
        SQL);
        $statement->execute([
            'baseCode' => $baseCode,
            'title' => $data['title'],
            'content' => $data['content'],
            'url' => $data['url'] ?: null,
            'visibility' => $data['visibility'],
            'active' => $data['active'] ? 'true' : 'false',
        ]);
        return (int) $statement->fetchColumn();
    }

    public function upsertN8nArticle(int $baseCode, array $data): array
    {
        $checksum = hash('sha256', json_encode([$data['title'], $data['content'], $data['url'], $data['visibility'], $data['active']], JSON_UNESCAPED_UNICODE));
        $existing = $this->database->pdo()->prepare('SELECT cod006, sha006 FROM n006 WHERE cod005 = :baseCode AND ext006 = :externalId LIMIT 1');
        $existing->execute(['baseCode' => $baseCode, 'externalId' => $data['external_id']]);
        $article = $existing->fetch(PDO::FETCH_ASSOC);
        if ($article !== false && hash_equals((string) ($article['sha006'] ?? ''), $checksum)) return ['action' => 'ignored', 'article_id' => (int) $article['cod006']];

        if ($article !== false) {
            $statement = $this->database->pdo()->prepare(<<<'SQL'
                UPDATE n006 SET tit006 = :title, con006 = :content, url006 = :url,
                    vis006 = :visibility, sts006 = :active, sha006 = :checksum,
                    ori006 = 'n8n', atu006 = CURRENT_TIMESTAMP
                WHERE cod006 = :articleCode AND cod005 = :baseCode
            SQL);
            $statement->execute(['articleCode' => $article['cod006'], 'baseCode' => $baseCode, 'title' => $data['title'], 'content' => $data['content'], 'url' => $data['url'] ?: null, 'visibility' => $data['visibility'], 'active' => $data['active'] ? 'true' : 'false', 'checksum' => $checksum]);
            return ['action' => 'updated', 'article_id' => (int) $article['cod006']];
        }

        $statement = $this->database->pdo()->prepare(<<<'SQL'
            INSERT INTO n006 (cod005, tit006, con006, url006, vis006, sts006, ext006, sha006, ori006)
            VALUES (:baseCode, :title, :content, :url, :visibility, :active, :externalId, :checksum, 'n8n')
            RETURNING cod006
        SQL);
        $statement->execute(['baseCode' => $baseCode, 'title' => $data['title'], 'content' => $data['content'], 'url' => $data['url'] ?: null, 'visibility' => $data['visibility'], 'active' => $data['active'] ? 'true' : 'false', 'externalId' => $data['external_id'], 'checksum' => $checksum]);
        return ['action' => 'created', 'article_id' => (int) $statement->fetchColumn()];
    }
    
    public function updateArticleStatus(
        int $baseCode,
        int $articleCode,
        bool $active
    ): void {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
        UPDATE n006
        SET
            sts006 = :active,
            atu006 = CURRENT_TIMESTAMP
        WHERE cod006 = :articleCode
          AND cod005 = :baseCode
    SQL);

        $statement->execute([
            'active' => $active ? 'true' : 'false',
            'articleCode' => $articleCode,
            'baseCode' => $baseCode,
        ]);
    }
}
