<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\KnowledgeRepository;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Support\Permission;
use App\Support\Session;
use App\Services\CurrentCompanyContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class KnowledgeController
{
    public function __construct(private readonly Twig $view, private readonly UserRepository $users, private readonly KnowledgeRepository $knowledge, private readonly CurrentCompanyContext $companies, private readonly AuditRepository $audit) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response);
        if ($user instanceof ResponseInterface) return $user;
        if (!Permission::allows($user, Permission::KNOWLEDGE_ACCESS)) return $this->redirect($response, '/dashboard');
        return $this->view->render($response, 'knowledge/index.twig', $this->context($user, [
            'bases' => $this->knowledge->findBasesByCompany($this->companies->companyCode($user)),
            'can_manage' => Permission::allows($user, Permission::KNOWLEDGE_UPDATE),
        ]));
    }

    public function storeBase(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->user($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::KNOWLEDGE_UPDATE)) {
            return $this->redirect($response, '/dashboard');
        }

        $body = (array) $request->getParsedBody();
        $description = trim((string) ($body['des005'] ?? ''));

        if ($description === '' || strlen($description) > 120) {
            return $this->redirect($response, '/conhecimento');
        }

        $this->knowledge->createBase(
            $this->companies->companyCode($user),
            $description,
            (int) ($body['sts005'] ?? 1) === 1
        );

        return $this->redirect($response, '/conhecimento');
    }

    public function updateBaseStatus(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->user($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::KNOWLEDGE_UPDATE)) {
            return $this->redirect($response, '/dashboard');
        }

        $base = $this->knowledge->findBase(
            $this->companies->companyCode($user),
            (int) ($args['id'] ?? 0)
        );

        if ($base === null) {
            return $this->redirect($response, '/conhecimento');
        }

        $body = (array) $request->getParsedBody();

        $this->knowledge->updateBaseStatus(
            $this->companies->companyCode($user),
            (int) $base['cod005'],
            (int) ($body['sts005'] ?? 0) === 1
        );

        return $this->redirect(
            $response,
            '/conhecimento/' . $base['cod005']
        );
    }

public function updateBaseAiConfiguration(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $user = $this->user($response);

    if ($user instanceof ResponseInterface) {
        return $user;
    }

    if (!Permission::allows($user, Permission::KNOWLEDGE_UPDATE)) {
        return $this->redirect($response, '/dashboard');
    }

    $base = $this->knowledge->findBase(
        $this->companies->companyCode($user),
        (int) ($args['id'] ?? 0)
    );

    if ($base === null) {
        return $this->redirect($response, '/conhecimento');
    }

    $body = (array) $request->getParsedBody();

    $model = trim((string) ($body['mod005'] ?? ''));
    $temperatureRaw = trim((string) ($body['tmp005'] ?? ''));
    $limitRaw = trim((string) ($body['lim005'] ?? ''));

    if ($model !== '' && strlen($model) > 100) {
        return $this->redirect($response, '/conhecimento/' . $base['cod005']);
    }

    $temperature = null;

    if ($temperatureRaw !== '') {
        if (!is_numeric($temperatureRaw)) {
            return $this->redirect($response, '/conhecimento/' . $base['cod005']);
        }

        $temperature = (float) $temperatureRaw;

        if ($temperature < 0 || $temperature > 2) {
            return $this->redirect($response, '/conhecimento/' . $base['cod005']);
        }
    }

    $limit = null;

    if ($limitRaw !== '') {
        $limit = filter_var($limitRaw, FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 50 || $limit > 4000) {
            return $this->redirect($response, '/conhecimento/' . $base['cod005']);
        }
    }

    $this->knowledge->updateBaseAiConfiguration(
        $this->companies->companyCode($user),
        (int) $base['cod005'],
        [
            'model' => $model !== '' ? $model : null,
            'temperature' => $temperature,
            'limit' => $limit,
            'instruction' => ($value = trim((string) ($body['ins005'] ?? ''))) !== ''
                ? $value
                : null,
            'welcome' => ($value = trim((string) ($body['msg005'] ?? ''))) !== ''
                ? $value
                : null,
            'farewell' => ($value = trim((string) ($body['msgfim005'] ?? ''))) !== ''
                ? $value
                : null,
        ]
    );

    return $this->redirect(
        $response,
        '/conhecimento/' . $base['cod005']
    );
}    

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response);
        if ($user instanceof ResponseInterface) return $user;
        if (!Permission::allows($user, Permission::KNOWLEDGE_ACCESS)) return $this->redirect($response, '/dashboard');
        $base = $this->knowledge->findBase($this->companies->companyCode($user), (int) ($args['id'] ?? 0));
        if ($base === null) return $this->redirect($response, '/conhecimento');
        return $this->renderBase($response, $user, $base);
    }

    public function regenerateN8nKey(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response);
        if ($user instanceof ResponseInterface) return $user;
        if (!Permission::allows($user, Permission::KNOWLEDGE_UPDATE)) return $this->redirect($response, '/dashboard');
        $companyCode = $this->companies->companyCode($user);
        $base = $this->knowledge->findBase($companyCode, (int) ($args['id'] ?? 0));
        if ($base === null) return $this->redirect($response, '/conhecimento');
        $key = 'n8n_' . bin2hex(random_bytes(24));
        $this->knowledge->updateN8nKeyHash($companyCode, (int) $base['cod005'], password_hash($key, PASSWORD_DEFAULT));
        $base['nkh005'] = 'configured';
        $this->audit->record($companyCode, (int) $user['cod002'], 'UPDATE', 'Integração n8n', (int) $base['cod005'], 'Chave da integração n8n regenerada.', $this->clientIp($request));
        return $this->renderBase($response, $user, $base, $key);
    }

    public function storeArticle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response);
        if ($user instanceof ResponseInterface) return $user;
        if (!Permission::allows($user, Permission::KNOWLEDGE_UPDATE)) return $this->redirect($response, '/dashboard');
        $base = $this->knowledge->findBase($this->companies->companyCode($user), (int) ($args['id'] ?? 0));
        if ($base === null) return $this->redirect($response, '/conhecimento');
        $body = (array) $request->getParsedBody();
        $data = [
            'title' => trim((string) ($body['tit006'] ?? '')),
            'content' => trim((string) ($body['con006'] ?? '')),
            'url' => trim((string) ($body['url006'] ?? '')),
            'visibility' => (int) ($body['vis006'] ?? 2),
            'active' => (int) ($body['sts006'] ?? 1) === 1,
        ];
        if ($data['title'] === '' || $data['content'] === '' || !in_array($data['visibility'], [1, 2, 3], true)) return $this->redirect($response, '/conhecimento/' . $base['cod005']);
        $this->knowledge->createArticle((int) $base['cod005'], $data);
        return $this->redirect($response, '/conhecimento/' . $base['cod005']);
    }

    public function updateArticleStatus(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->user($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::KNOWLEDGE_UPDATE)) {
            return $this->redirect($response, '/dashboard');
        }

        $base = $this->knowledge->findBase(
            $this->companies->companyCode($user),
            (int) ($args['id'] ?? 0)
        );

        if ($base === null) {
            return $this->redirect($response, '/conhecimento');
        }

        $body = (array) $request->getParsedBody();

        $this->knowledge->updateArticleStatus(
            (int) $base['cod005'],
            (int) ($args['article'] ?? 0),
            (int) ($body['sts006'] ?? 0) === 1
        );

        return $this->redirect(
            $response,
            '/conhecimento/' . $base['cod005']
        );
    }

    public function updateArticle(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->user($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::KNOWLEDGE_UPDATE)) {
            return $this->redirect($response, '/dashboard');
        }

        $base = $this->knowledge->findBase(
            $this->companies->companyCode($user),
            (int) ($args['id'] ?? 0)
        );

        if ($base === null) {
            return $this->redirect($response, '/conhecimento');
        }

        $body = (array) $request->getParsedBody();
        $data = [
            'title' => trim((string) ($body['tit006'] ?? '')),
            'content' => trim((string) ($body['con006'] ?? '')),
            'url' => trim((string) ($body['url006'] ?? '')),
            'visibility' => (int) ($body['vis006'] ?? 2),
            'active' => (int) ($body['sts006'] ?? 0) === 1,
        ];

        if (
            $data['title'] === '' || strlen($data['title']) > 200 ||
            $data['content'] === '' || strlen($data['content']) > 50000 ||
            strlen($data['url']) > 500 ||
            !in_array($data['visibility'], [1, 2, 3], true)
        ) {
            return $this->redirect($response, '/conhecimento/' . $base['cod005']);
        }

        $this->knowledge->updateArticle(
            (int) $base['cod005'],
            (int) ($args['article'] ?? 0),
            $data
        );

        return $this->redirect($response, '/conhecimento/' . $base['cod005']);
    }

    private function user(ResponseInterface $response): array|ResponseInterface
    {
        $session = Session::user();
        if ($session === null) return $this->redirect($response, '/login');
        $user = $this->users->findByCode((int) $session['cod002']);
        return $user ?? $this->redirect($response, '/login');
    }

    private function renderBase(ResponseInterface $response, array $user, array $base, ?string $n8nKey = null): ResponseInterface
    {
        return $this->view->render($response, 'knowledge/show.twig', $this->context($user, [
            'base' => $base,
            'artigos' => $this->knowledge->findArticles((int) $base['cod005']),
            'can_manage' => Permission::allows($user, Permission::KNOWLEDGE_UPDATE),
            'n8n_key' => $n8nKey,
            'configuracao_canais' => $this->channelConfigurationDocument(),
        ]));
    }

    private function channelConfigurationDocument(): string
    {
        $path = dirname(__DIR__, 2) . '/Storage/documents/configuracao-canais.md';
        $document = file_get_contents($path);

        return $document === false ? 'Documento de configuração não encontrado.' : $document;
    }
    private function context(array $user, array $data): array
    {
        return $data + ['app_name' => $_ENV['APP_NAME'], 'usuario' => ['codigo' => $user['cod002'], 'nome' => $user['des002'], 'email' => $user['ema002'], 'perfil' => $user['rol002']]];
    }
    private function redirect(ResponseInterface $response, string $location): ResponseInterface
    {
        return $response->withHeader('Location', $location)->withStatus(302);
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        return is_string($ip) ? $ip : null;
    }
}
