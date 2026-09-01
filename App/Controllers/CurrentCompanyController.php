<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Services\CurrentCompanyContext;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CurrentCompanyController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly CurrentCompanyContext $companies
    ) {}

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $sessionUser = Session::user();

        if ($sessionUser === null) {
            return $this->redirect($response, '/login');
        }

        $user = $this->users->findByCode((int) $sessionUser['cod002']);

        if ($user === null) {
            Session::logout();
            return $this->redirect($response, '/login');
        }

        $body = (array) $request->getParsedBody();

        if (!$this->companies->select($user, (int) ($body['cod001'] ?? 0))) {
            return $this->redirect($response, '/dashboard');
        }

        $returnTo = (string) ($body['return_to'] ?? '/dashboard');

        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/dashboard';
        }

        return $this->redirect($response, $returnTo);
    }

    private function redirect(ResponseInterface $response, string $location): ResponseInterface
    {
        return $response->withHeader('Location', $location)->withStatus(302);
    }
}
