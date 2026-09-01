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
        $sessionUser = Session::user();

        if ($sessionUser !== null) {
            $user = $this->users->findByCode((int) $sessionUser['cod002']);

            if ($user !== null) {
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
                    'audit' => Permission::allows($user, Permission::AUDIT_ACCESS),
                ];
            }
        }

        $environment = $this->view->getEnvironment();
        $environment->addGlobal('empresa_atual', $currentCompany);
        $environment->addGlobal('empresas_disponiveis', $availableCompanies);
        $environment->addGlobal('permissoes_menu', $menuPermissions);
        $environment->addGlobal('csrf_token', Session::csrfToken());

        return $handler->handle($request);
    }
}
