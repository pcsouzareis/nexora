<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class HomeController
{
    public function __construct(
        private readonly Twig $view
    ) {}

    /**
     * Redireciona a página inicial para o login.
     */
    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }

    /**
     * Retorna o estado de funcionamento da aplicação.
     */
    public function health(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $response->getBody()->write(
            json_encode(
                [
                    'status' => 'ok',
                    'application' => $_env['APP_NAME'],
                    'timestamp' => gmdate('c'),
                ],
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            )
        );

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}