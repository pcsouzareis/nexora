<?php

declare(strict_types=1);

use App\Repositories\TelegramChannelRepository;
use App\Services\TelegramService;
use DI\ContainerBuilder;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$builder = new ContainerBuilder();
$builder->addDefinitions(require dirname(__DIR__) . '/Config/dependencies.php');
$container = $builder->build();

$channels = $container->get(TelegramChannelRepository::class);
$telegram = $container->get(TelegramService::class);

if (!$channels->tryAcquireSyncLock()) {
    fwrite(STDOUT, "Sincronização do Telegram já está em execução.\n");
    exit(0);
}

$synchronized = 0;
$failures = 0;

try {
    foreach ($channels->findAllActive() as $channel) {
        try {
            $count = $telegram->synchronize($channel);
            $synchronized += $count;
            fwrite(STDOUT, sprintf("Canal %d: %d mensagem(ns) sincronizada(s).\n", $channel['cod003'], $count));
        } catch (RuntimeException $exception) {
            $failures++;
            fwrite(STDERR, sprintf("Canal %d: %s\n", $channel['cod003'], $exception->getMessage()));
        }
    }
} finally {
    $channels->releaseSyncLock();
}

fwrite(STDOUT, sprintf("Concluído. Mensagens: %d. Falhas: %d.\n", $synchronized, $failures));
exit($failures === 0 ? 0 : 1);
