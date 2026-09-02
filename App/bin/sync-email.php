<?php

    declare(strict_types=1);

    use App\Repositories\EmailChannelRepository;
    use App\Repositories\IntegrationHealthRepository;
    use App\Services\EmailSynchronizationService;
    use DI\ContainerBuilder;
    use Dotenv\Dotenv;

    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

    $builder = new ContainerBuilder();
    $builder->addDefinitions(require dirname(__DIR__, 2) . '/Config/dependencies.php');
    $container = $builder->build();
    $channels = $container->get(EmailChannelRepository::class);
    $email = $container->get(EmailSynchronizationService::class);
    $health = $container->get(IntegrationHealthRepository::class);

    if (!$channels->tryAcquireSyncLock()) {
        fwrite(STDOUT, "Sincronização de e-mail já está em execução.\n");
        exit(0);
    }
    $processed = 0;
    $failures = 0;
    try {
        foreach ($channels->findAllActive() as $channel) {
            try {
                $count = $email->synchronize($channel);
                $processed += $count;
                $health->record((int) $channel['cod001'], (int) $channel['cod003'], 'Sincronização', 'Sucesso', $count . ' e-mail(s) sincronizado(s).');
                fwrite(STDOUT, sprintf("Canal %d: %d e-mail(s) sincronizado(s).\n", $channel['cod003'], $count));
            } catch (RuntimeException $exception) {
                $failures++;
                $health->record((int) $channel['cod001'], (int) $channel['cod003'], 'Sincronização', 'Falha', $exception->getMessage());
                fwrite(STDERR, sprintf("Canal %d: %s\n", $channel['cod003'], $exception->getMessage()));
            }
        }
    } finally {
        $channels->releaseSyncLock();
    }
    fwrite(STDOUT, sprintf("Concluído. E-mails: %d. Falhas: %d.\n", $processed, $failures));
    exit($failures === 0 ? 0 : 1);
