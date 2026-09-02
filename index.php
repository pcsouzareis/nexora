<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuditMiddleware;

require __DIR__ . '/vendor/autoload.php';

/*
 * ---------------------------------------------------------
 * Ambiente
 * ---------------------------------------------------------
 */

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/*
 * ---------------------------------------------------------
 * Container PHP-DI
 * ---------------------------------------------------------
 */

$containerBuilder = new ContainerBuilder();

$definitions = require __DIR__ . '/Config/dependencies.php';

$containerBuilder->addDefinitions($definitions);

$container = $containerBuilder->build();

/*
 * ---------------------------------------------------------
 * Slim
 * ---------------------------------------------------------
 */

AppFactory::setContainer($container);

$app = AppFactory::create();

/*
 * ---------------------------------------------------------
 * Middleware
 * ---------------------------------------------------------
 */

/* A auditoria fica antes do roteamento para receber a rota já resolvida. */
$app->add($container->get(AuditMiddleware::class));

$app->addRoutingMiddleware();

/*
 * ---------------------------------------------------------
 * Twig
 * ---------------------------------------------------------
 */

$app->add(
    TwigMiddleware::createFromContainer(
        $app,
        \Slim\Views\Twig::class
    )
);

$app->add($container->get(\App\Middleware\CurrentCompanyMiddleware::class));
$app->add($container->get(CsrfMiddleware::class));
$app->addBodyParsingMiddleware();


/*
 * ---------------------------------------------------------
 * Rotas
 * ---------------------------------------------------------
 */

$registrarRotas = require __DIR__ . '/App/routes/web.php';
$registrarRotas($app);
/*
 * ---------------------------------------------------------
 * Tratamento de erros
 * ---------------------------------------------------------
 */

$errorMiddleware = $app->addErrorMiddleware(
    true,
    true,
    true
);


/*
 * ---------------------------------------------------------
 * Tratamento da página 404
 * ---------------------------------------------------------
 */

$errorMiddleware->setErrorHandler(
    \Slim\Exception\HttpNotFoundException::class,
    function (
        \Psr\Http\Message\ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) use ($app): \Psr\Http\Message\ResponseInterface {

        $response = new \Slim\Psr7\Response();

        /** @var \Slim\Views\Twig $view */
        $view = $app
            ->getContainer()
            ->get(\Slim\Views\Twig::class);

        return $view->render(
            $response->withStatus(404),
            'errors/404.twig'
        );
    }
);


/*
 * ---------------------------------------------------------
 * Executa aplicação
 * ---------------------------------------------------------
 */

$app->run();
