<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Services\CurrentCompanyContext;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuditMiddleware
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuditRepository $audit,
        private readonly CurrentCompanyContext $companies
    ) {}

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($response->getStatusCode() >= 400) return $response;

        $event = $this->event($request->getMethod(), $request->getUri()->getPath());
        $session = Session::user();
        if ($event === null || $session === null) return $response;

        $user = $this->users->findByCode((int) $session['cod002']);
        if ($user === null) return $response;

        [$action, $entity, $description, $reference] = $event;
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $this->audit->record(
            $this->companies->companyCode($user),
            (int) $user['cod002'],
            $action,
            $entity,
            $reference,
            $description,
            is_string($ip) ? $ip : null
        );

        return $response;
    }

    private function event(string $method, string $path): ?array
    {
        if (str_starts_with($path, '/api/') || str_starts_with($path, '/chat/') || $path === '/auditoria/dados') return null;
        if (in_array($path, ['/', '/health', '/login', '/logout'], true)) return null;

        $modules = [
            '/dashboard' => 'Dashboard', '/empresas' => 'Empresas', '/usuarios' => 'Usuários',
            '/conhecimento' => 'Aprendizado', '/configuracoes/ia' => 'Configuração da IA',
            '/testes/webhook' => 'Teste de webhook', '/conversas' => 'Conversas',
            '/canais' => 'Canais', '/auditoria' => 'Auditoria', '/minha-senha' => 'Minha senha',
            '/empresa-atual' => 'Empresa atual',
        ];
        $entity = null;
        foreach ($modules as $prefix => $name) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) { $entity = $name; break; }
        }
        if ($entity === null) return null;

        $reference = preg_match('#/(?:empresas|usuarios|conhecimento|conversas|canais)/(\\d+)#', $path, $match) === 1 ? (int) $match[1] : null;
        if ($method === 'GET') {
            if (in_array($path, ['/empresas/criar', '/usuarios/novo', '/canais/novo'], true)) {
                return ['INCLUSÃO', $entity, 'Formulário de inclusão aberto no módulo ' . $entity . '.', null];
            }
            if (str_ends_with($path, '/editar') || in_array($path, ['/configuracoes/ia', '/minha-senha'], true)) {
                return ['EDIÇÃO', $entity, 'Formulário de edição aberto no módulo ' . $entity . '.', $reference];
            }
            return [$path === '/dashboard' ? 'ACESSO' : 'CONSULTA', $entity, ($path === '/dashboard' ? 'Acesso' : 'Consulta') . ' ao módulo ' . $entity . '.', $reference];
        }
        if ($method !== 'POST') return null;

        if ($path === '/empresa-atual') return ['ALTERAÇÃO', $entity, 'Empresa atual alterada.', null];
        if ($path === '/testes/webhook' || $path === '/configuracoes/ia/testar') return ['TESTE', $entity, 'Teste executado.', null];
        if (str_starts_with($path, '/conhecimento')) {
            $action = str_ends_with($path, '/artigos') ? 'INCLUSÃO' : ($path === '/conhecimento' ? 'INCLUSÃO' : 'EDIÇÃO');
            return [$action, $entity, $action . ' no módulo ' . $entity . '.', $reference];
        }
        if ($path === '/configuracoes/ia') return ['EDIÇÃO', $entity, 'Configuração da IA atualizada.', null];

        return null;
    }
}
