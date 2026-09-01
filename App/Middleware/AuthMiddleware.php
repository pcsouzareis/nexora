<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\UserRepository;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AuthMiddleware
{
    public function __construct(
        private readonly UserRepository $users
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (!Session::isAuthenticated()) {
            return $this->redirectToLogin();
        }

        $sessionUser = Session::user();
        $user = $this->users->findByCode(
            (int) $sessionUser['cod002']
        );

        if (
            $user === null ||
            !(bool) $user['sts002'] ||
            !(bool) $user['sts013']
        ) {
            Session::logout();

            return $this->redirectToLogin();
        }

        return $handler->handle($request);
    }

    private function redirectToLogin(): ResponseInterface
    {
        return (new Response())
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
}
