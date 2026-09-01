<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CompanyRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\UserRepository;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class IntegrationController
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly CompanyRepository $companies,
        private readonly IntegrationRepository $integrations
    ) {}

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $sessionUser = Session::user();

        if ($sessionUser === null) {
            return $this->redirect($response, '/login');
        }

        $user = $this->users->findByCode((int) $sessionUser['cod002']);

        if ($user === null) {
            Session::logout();
            return $this->redirect($response, '/login');
        }

        $role = (string) $user['rol002'];

        if (!in_array($role, ['D', 'S'], true)) {
            return $this->redirect($response, '/dashboard');
        }

        $companies = $role === 'D'
            ? $this->companies->findAll()
            : $this->companies->findAllCreatedBy((int) $user['cod002']);

        $summaries = [];

        foreach ($companies as $company) {
            $summary = $this->integrations->summaryByCompany((int) $company['cod001']);

            if ($summary !== []) {
                $summary['canais'] = $this->integrations->channelsByCompany((int) $company['cod001']);
                $summaries[] = $summary;
            }
        }

        return $this->view->render($response, 'integrations/index.twig', [
            'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
            'usuario' => [
                'codigo' => (int) $user['cod002'],
                'nome' => (string) $user['des002'],
                'perfil' => $role,
            ],
            'integracoes' => $summaries,
        ]);
    }

    private function redirect(ResponseInterface $response, string $location): ResponseInterface
    {
        return $response->withHeader('Location', $location)->withStatus(302);
    }
}
