<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class CsrfMiddleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        if (str_starts_with($request->getUri()->getPath(), '/api/') || !Session::isAuthenticated()) {
            return $handler->handle($request);
        }

        $body = $request->getParsedBody();
        $token = is_array($body) ? ($body['csrf_token'] ?? null) : null;

        if (Session::validCsrfToken($token)) {
            return $handler->handle($request);
        }

        $response = new Response(403);
        $response->getBody()->write('Solicitação inválida ou expirada. Atualize a página e tente novamente.');
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
