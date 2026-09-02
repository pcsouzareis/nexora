<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\LicenseContractAccessRepository;
use App\Repositories\UserRepository;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/** Consulta administrativa dos contratos exibidos e aceitos. */
final class ContractAcceptanceController
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly LicenseContractAccessRepository $contracts
    ) {}

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->administrator($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        return $this->view->render($response, 'contracts/index.twig', [
            'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
            'usuario' => [
                'codigo' => (int) $user['cod002'],
                'nome' => (string) $user['des002'],
                'email' => (string) $user['ema002'],
                'perfil' => (string) $user['rol002'],
            ],
            'filtros' => $this->contracts->filters(),
        ]);
    }

    public function data(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->administrator($response);

        if ($user instanceof ResponseInterface) {
            return $this->json($response->withStatus(403), ['error' => 'Acesso negado.']);
        }

        $query = $request->getQueryParams();
        $order = is_array($query['order'] ?? null) ? ($query['order'][0] ?? []) : [];
        $search = is_array($query['search'] ?? null)
            ? (string) ($query['search']['value'] ?? '')
            : '';

        $result = $this->contracts->dataTable(
            [
                'company' => $query['company'] ?? '',
                'user' => $query['user'] ?? '',
                'status' => $query['status'] ?? '',
            ],
            max(0, (int) ($query['start'] ?? 0)),
            max(1, min(100, (int) ($query['length'] ?? 25))),
            $search,
            (int) ($order['column'] ?? 0),
            (string) ($order['dir'] ?? 'desc')
        );

        return $this->json($response, [
            'draw' => max(0, (int) ($query['draw'] ?? 0)),
        ] + $result);
    }

    private function administrator(ResponseInterface $response): array|ResponseInterface
    {
        $sessionUser = Session::user();

        if ($sessionUser === null) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = $this->users->findByCode((int) $sessionUser['cod002']);

        if ($user === null) {
            Session::logout();

            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        if ((string) $user['rol002'] !== 'D') {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        return $user;
    }

    private function json(ResponseInterface $response, array $data): ResponseInterface
    {
        $response->getBody()->write((string) json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
