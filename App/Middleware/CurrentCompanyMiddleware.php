<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\UserRepository;
use App\Services\CurrentCompanyContext;
use App\Support\Permission;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final class CurrentCompanyMiddleware
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly CurrentCompanyContext $companies
    ) {}

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $currentCompany = null;
        $availableCompanies = [];
        $menuPermissions = [];
        $currentUser = null;
        $sessionUser = Session::user();

        if ($sessionUser !== null) {
            $user = $this->users->findByCode((int) $sessionUser['cod002']);

            if ($user !== null) {
                $currentUser = [
                    'codigo' => (int) $user['cod002'],
                    'perfil' => (string) $user['rol002'],
                ];
                $availableCompanies = $this->companies->availableCompanies($user);
                $currentCompany = $this->companies->currentCompany($user);
                $menuPermissions = [
                    'companies' => Permission::allows($user, Permission::COMPANY_ACCESS),
                    'users' => Permission::allows($user, Permission::USER_ACCESS),
                    'knowledge' => Permission::allows($user, Permission::KNOWLEDGE_ACCESS),
                    'ai_configuration' => Permission::allows($user, Permission::AI_UPDATE),
                    'webhook_test' => Permission::allows($user, Permission::AI_WEBHOOK_TEST),
                    'conversations' => Permission::allows($user, Permission::CONVERSATION_ACCESS),
                    'channels' => Permission::allows($user, Permission::CHANNEL_ACCESS),
                    'integrations' => in_array((string) $user['rol002'], ['D', 'S'], true),
                    'audit' => Permission::allows($user, Permission::AUDIT_ACCESS),
                    'contracts' => (string) $user['rol002'] === 'D',
                ];
            }
        }

        $environment = $this->view->getEnvironment();
        $environment->addGlobal('empresa_atual', $currentCompany);
        $environment->addGlobal('empresas_disponiveis', $availableCompanies);
        $environment->addGlobal('usuario_atual', $currentUser);
        $environment->addGlobal('permissoes_menu', $menuPermissions);
        $environment->addGlobal('rota_atual', $request->getUri()->getPath());
        $environment->addGlobal('csrf_token', Session::csrfToken());

        return $handler->handle($request);
    }
}
