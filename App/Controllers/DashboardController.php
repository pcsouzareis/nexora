<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Support\Permission;
use App\Services\DashboardService;
use App\Services\CurrentCompanyContext;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class DashboardController
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly DashboardService $dashboard,
        private readonly CurrentCompanyContext $companies
    ) {
    }

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {

        $sessionUser = Session::user();        

        if ($sessionUser === null) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $user = $this->users->findByCode(
            (int) $sessionUser['cod002']
        );

        if ($user === null) {
            Session::logout();

            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $dashboardEnabled = Permission::allows(
            $user,
            Permission::DASHBOARD_ACCESS
        );

        $currentCompany = $this->companies->currentCompany($user);
        $companyCode = $currentCompany !== null
            ? (int) $currentCompany['cod001']
            : (int) $user['cod001'];

        $dashboard = $dashboardEnabled
            ? $this->dashboard->getDashboard($companyCode)
            : ['resumo' => []];
        
        
        return $this->view->render(
            $response,
            'dashboard/index.twig',
            [
                'app_name' => $_ENV['APP_NAME'],

                'usuario' => [
                    'codigo' => (int) $user['cod002'],
                    'nome' => (string) $user['des002'],
                    'email' => (string) $user['ema002'],
                    'perfil' => (string) $user['rol002'],
                    'perfil_name' => (string) $user['perfil_name'],
                ],

                'resumo' => $dashboard['resumo'],
                'dashboard_enabled' => $dashboardEnabled,
                'empresa_atual' => (string) ($currentCompany['des001'] ?? 'Empresa não encontrada'),
            ]
        );
    }
}
