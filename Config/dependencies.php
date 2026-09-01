<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use DI\Container;
use App\Controllers\CurrentCompanyController;
use App\Middleware\CurrentCompanyMiddleware;
use App\Services\CurrentCompanyContext;

use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\CompanyController;

use Psr\Container\ContainerInterface;

use App\Support\Database;

use App\Repositories\DashboardRepository;
use App\Repositories\UserRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\SupervisorCompanyRepository;
use App\Repositories\AuditRepository;

use App\Services\DashboardService;

use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;


return [

    /*
 * ---------------------------------------------------------
 * Company Controller
 * ---------------------------------------------------------
 */

    CompanyController::class => function (
        ContainerInterface $container
    ): CompanyController {

        return new CompanyController(
            $container->get(Twig::class),
            $container->get(UserRepository::class),
            $container->get(CompanyRepository::class),
            $container->get(SupervisorCompanyRepository::class),
            $container->get(AuditRepository::class)
        );
    },

    /*
     * ---------------------------------------------------------
     * Database
     * ---------------------------------------------------------
     */

    Database::class => function (): Database {

        return new Database([
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '5432',
            'name' => $_ENV['DB_NAME'] ?? 'base_chatBots',
            'user' => $_ENV['DB_USER'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ]);
    },


    /*
     * ---------------------------------------------------------
     * Twig
     * ---------------------------------------------------------
     */

    Twig::class => function (): Twig {

        return Twig::create(
            __DIR__ . '/../templates',
            [
                'cache' => false,
                'auto_reload' => true,
            ]
        );
    },


    /*
     * ---------------------------------------------------------
     * Twig Middleware
     * ---------------------------------------------------------
     */

    TwigMiddleware::class => function (
        Container $container
    ): TwigMiddleware {

        return TwigMiddleware::createFromContainer(
            $container->get(\Slim\App::class),
            Twig::class
        );
    },


    /*
     * ---------------------------------------------------------
     * User Repository
     * ---------------------------------------------------------
     */

    UserRepository::class => function (
        ContainerInterface $container
    ): UserRepository {

        return new UserRepository(
            $container->get(Database::class)
        );
    },

    CompanyRepository::class => function (
        ContainerInterface $container
    ): CompanyRepository {
        return new CompanyRepository(
            $container->get(Database::class)
        );
    },

    CurrentCompanyController::class => function (
        ContainerInterface $container
    ): CurrentCompanyController {
        return new CurrentCompanyController(
            $container->get(UserRepository::class),
            $container->get(CurrentCompanyContext::class)
        );
    },

    SupervisorCompanyRepository::class => function (
        ContainerInterface $container
    ): SupervisorCompanyRepository {
        return new SupervisorCompanyRepository(
            $container->get(Database::class)
        );
    },

    CurrentCompanyContext::class => function (
        ContainerInterface $container
    ): CurrentCompanyContext {
        return new CurrentCompanyContext(
            $container->get(CompanyRepository::class),
            $container->get(SupervisorCompanyRepository::class)
        );
    },

    CurrentCompanyMiddleware::class => function (
        ContainerInterface $container
    ): CurrentCompanyMiddleware {
        return new CurrentCompanyMiddleware(
            $container->get(Twig::class),
            $container->get(UserRepository::class),
            $container->get(CurrentCompanyContext::class)
        );
    },

    /*
     * ---------------------------------------------------------
     * Dashboard Repository
     * ---------------------------------------------------------
     */

    DashboardRepository::class => function (
        ContainerInterface $container
    ): DashboardRepository {

        return new DashboardRepository(
            $container->get(Database::class)
        );
    },


    /*
     * ---------------------------------------------------------
     * Dashboard Service
     * ---------------------------------------------------------
     */

    DashboardService::class => function (
        ContainerInterface $container
    ): DashboardService {

        return new DashboardService(
            $container->get(DashboardRepository::class)
        );
    },


    /*
     * ---------------------------------------------------------
     * Home Controller
     * ---------------------------------------------------------
     */

    HomeController::class => function (
        ContainerInterface $container
    ): HomeController {

        return new HomeController(
            $container->get(Twig::class)
        );
    },


    /*
     * ---------------------------------------------------------
     * Health Controller
     * ---------------------------------------------------------
     */

    HealthController::class => function (
        ContainerInterface $container
    ): HealthController {

        return new HealthController(
            $container->get(Database::class)
        );
    },


    /*
     * ---------------------------------------------------------
     * Dashboard Controller
     * ---------------------------------------------------------
     */

    DashboardController::class => function (
        ContainerInterface $container
    ): DashboardController {

        return new DashboardController(
            $container->get(Twig::class),
            $container->get(UserRepository::class),
            $container->get(DashboardService::class),
            $container->get(CurrentCompanyContext::class)
        );
    },
];
