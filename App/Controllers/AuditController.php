<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Services\CurrentCompanyContext;
use App\Support\Permission;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class AuditController
{
    public function __construct(private readonly Twig $view, private readonly UserRepository $users, private readonly AuditRepository $audit, private readonly CurrentCompanyContext $companies) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $session = Session::user();
        if ($session === null) return $response->withHeader('Location', '/login')->withStatus(302);
        $user = $this->users->findByCode((int) $session['cod002']);
        if ($user === null || !Permission::allows($user, Permission::AUDIT_ACCESS)) return $response->withHeader('Location', '/dashboard')->withStatus(302);
        $company = $this->companies->companyCode($user);
        return $this->view->render($response, 'audit/index.twig', [
            'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
            'usuario' => ['codigo' => $user['cod002'], 'nome' => $user['des002'], 'email' => $user['ema002'], 'perfil' => $user['rol002']],
            'filtros' => $this->audit->filtersByCompany($company),
        ]);
    }

    public function data(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $session = Session::user();
        if ($session === null) return $this->json($response->withStatus(401), ['error' => 'Não autorizado.']);
        $user = $this->users->findByCode((int) $session['cod002']);
        if ($user === null || !Permission::allows($user, Permission::AUDIT_ACCESS)) return $this->json($response->withStatus(403), ['error' => 'Acesso negado.']);
        $query = $request->getQueryParams();
        $order = is_array($query['order'] ?? null) ? ($query['order'][0] ?? []) : [];
        $search = is_array($query['search'] ?? null) ? (string) ($query['search']['value'] ?? '') : '';
        $result = $this->audit->dataTable(
            $this->companies->companyCode($user),
            ['action' => $query['action'] ?? '', 'entity' => $query['entity'] ?? '', 'user' => $query['user'] ?? '', 'from' => $query['from'] ?? '', 'until' => $query['until'] ?? ''],
            max(0, (int) ($query['start'] ?? 0)), max(1, min(100, (int) ($query['length'] ?? 25))), $search,
            (int) ($order['column'] ?? 0), (string) ($order['dir'] ?? 'desc')
        );
        return $this->json($response, ['draw' => max(0, (int) ($query['draw'] ?? 0))] + $result);
    }

    private function json(ResponseInterface $response, array $data): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
