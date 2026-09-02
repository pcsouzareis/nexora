<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

$pdo = new PDO(
    sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        $_ENV['DB_PORT'] ?? '5432',
        $_ENV['DB_NAME'] ?? ''
    ),
    $_ENV['DB_USER'] ?? '',
    $_ENV['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS n020 (
        nom020 VARCHAR(190) PRIMARY KEY,
        sha020 CHAR(64) NOT NULL,
        cad020 TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
SQL);

$dryRun = in_array('--dry-run', $argv, true);
$directory = dirname(__DIR__) . '/Database/Migrations';
$files = glob($directory . '/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    if (basename($file) === 'README.sql') {
        continue;
    }

    $name = basename($file);
    $checksum = hash_file('sha256', $file);
    $statement = $pdo->prepare('SELECT sha020 FROM n020 WHERE nom020 = :name');
    $statement->execute(['name' => $name]);
    $storedChecksum = $statement->fetchColumn();

    if ($storedChecksum !== false) {
        if (!hash_equals((string) $storedChecksum, $checksum)) {
            throw new RuntimeException("A migração {$name} foi alterada após já ter sido aplicada.");
        }

        fwrite(STDOUT, "Ignorada: {$name}\n");
        continue;
    }

    if ($dryRun) {
        fwrite(STDOUT, "Pendente: {$name}\n");
        continue;
    }

    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException("Não foi possível ler {$name}.");
    }

    $pdo->beginTransaction();

    try {
        $pdo->exec($sql);
        $record = $pdo->prepare('INSERT INTO n020 (nom020, sha020) VALUES (:name, :checksum)');
        $record->execute(['name' => $name, 'checksum' => $checksum]);
        $pdo->commit();
        fwrite(STDOUT, "Aplicada: {$name}\n");
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

fwrite(STDOUT, $dryRun ? "Verificação concluída.\n" : "Migrações concluídas.\n");
