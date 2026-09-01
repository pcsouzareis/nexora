<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Support\Database;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

return [

    LoggerInterface::class => function (): LoggerInterface {

        $logger = new Logger($_ENV['APP_NAME']);

        $logger->pushHandler(
            new StreamHandler(
                __DIR__ . '/../Storage/logs/app.log',
                Level::Debug
            )
        );

        return $logger;
    },

    Twig::class => function (): Twig {
        $twig = Twig::create(
            __DIR__ . '/../templates',
            [
                'cache' => false,
                'debug' => filter_var(
                    $_ENV['APP_DEBUG'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]
        );

        $twig->getEnvironment()->addGlobal(
            'app_name',
            $_ENV['APP_NAME'] ?? 'Nexora'
        );

        return $twig;
    },

    Database::class => function (): Database {

        return new Database([
            'driver' => $_ENV['DB_DRIVER'] ?? '',
            'host' => $_ENV['DB_HOST'] ?? '',
            'port' => $_ENV['DB_PORT'] ?? '',
            'dbname' => $_ENV['DB_NAME'] ?? '',
            'user' => $_ENV['DB_USER'] ?? '',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ]);
    },

    HomeController::class => function (
        Twig $view
    ): HomeController {

        return new HomeController(
            $view
        );
    },
];
