<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AIConfigurationRepository;
use App\Repositories\UserRepository;
use App\Support\Encryption;
use App\Support\Permission;
use App\Support\Session;
use App\Services\CurrentCompanyContext;
use App\Services\AIConnectionTester;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class AIConfigurationController
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly AIConfigurationRepository $configuration,
        private readonly Encryption $encryption,
        private readonly CurrentCompanyContext $companies,
        private readonly AIConnectionTester $connectionTester
    ) {}

    public function edit(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->currentUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::AI_UPDATE)) {
            return $response
                ->withHeader('Location', '/dashboard')
                ->withStatus(302);
        }

        return $this->render(
            $response,
            $user,
            $this->configuration->findByCompany($this->companies->companyCode($user)) ?? []
        );
    }

    public function update(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->currentUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::AI_UPDATE)) {
            return $response
                ->withHeader('Location', '/dashboard')
                ->withStatus(302);
        }

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->render($response, $user, [], 'Dados inválidos.');
        }

        $url = trim((string) ($body['url013'] ?? ''));
        $apiKey = trim((string) ($body['key013'] ?? ''));

        $data = [
            'url' => $url,
            'model' => trim((string) ($body['mod013'] ?? '')),
            'temperature' => (float) ($body['temp013'] ?? -1),
            'limit' => (int) ($body['lim013'] ?? 0),
            'session_limit' => (int) ($body['lms013'] ?? 8),
            'ip_limit' => (int) ($body['lmi013'] ?? 25),
            'window_minutes' => (int) ($body['jan013'] ?? 5),
            'instruction' => trim((string) ($body['ins013'] ?? '')),
            'welcome' => trim((string) ($body['msg013'] ?? '')),
            'farewell' => trim((string) ($body['msgfim013'] ?? '')),
            'active' => isset($body['sts013']),
        ];

        if (
            $data['url'] === '' ||
            strlen($data['url']) > 500 ||
            filter_var($data['url'], FILTER_VALIDATE_URL) === false
        ) {
            return $this->render(
                $response,
                $user,
                $data,
                'Informe uma URL válida para a IA.'
            );
        }

        if ($data['model'] === '' || strlen($data['model']) > 100) {
            return $this->render(
                $response,
                $user,
                $data,
                'Informe um modelo válido.'
            );
        }

        if ($data['temperature'] < 0 || $data['temperature'] > 2) {
            return $this->render(
                $response,
                $user,
                $data,
                'A temperatura deve estar entre 0 e 2.'
            );
        }

        if ($data['limit'] < 50 || $data['limit'] > 4000) {
            return $this->render(
                $response,
                $user,
                $data,
                'O limite deve estar entre 50 e 4000.'
            );
        }

        if ($data['session_limit'] < 1 || $data['session_limit'] > 100 || $data['ip_limit'] < 1 || $data['ip_limit'] > 500 || $data['window_minutes'] < 1 || $data['window_minutes'] > 60) {
            return $this->render($response, $user, $data, 'Informe limites válidos para o Webchat.');
        }

        $encryptedKey = $apiKey === ''
            ? null
            : $this->encryption->encrypt($apiKey);

        $this->configuration->save(
            $this->companies->companyCode($user),
            $data,
            $encryptedKey
        );

        $configuration = $this->configuration->findByCompany(
            $this->companies->companyCode($user)
        ) ?? [];

        return $this->render(
            $response,
            $user,
            $configuration,
            null,
            'Configuração da IA salva com sucesso.'
        );
    }

    public function test(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->currentUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::AI_UPDATE)) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        $companyCode = $this->companies->companyCode($user);
        $configuration = $this->configuration->findConnectionByCompany($companyCode);
        $viewConfiguration = $this->configuration->findByCompany($companyCode) ?? [];

        if ($configuration === null) {
            return $this->render(
                $response,
                $user,
                $viewConfiguration,
                'Salve a configuração da IA antes de testar a conexão.'
            );
        }

        try {
            $message = $this->connectionTester->test($configuration);
        } catch (\Throwable $exception) {
            return $this->render($response, $user, $viewConfiguration, $exception->getMessage());
        }

        return $this->render($response, $user, $viewConfiguration, null, $message);
    }

    private function currentUser(ResponseInterface $response): array|ResponseInterface
    {
        $sessionUser = Session::user();

        if ($sessionUser === null) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $user = $this->users->findByCode((int) $sessionUser['cod002']);

        if ($user === null) {
            Session::logout();

            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        return $user;
    }

    private function render(
        ResponseInterface $response,
        array $user,
        array $configuration,
        ?string $error = null,
        ?string $success = null
    ): ResponseInterface {
        return $this->view->render($response, 'ai/configuration.twig', [
            'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
            'usuario' => [
                'codigo' => (int) $user['cod002'],
                'nome' => (string) $user['des002'],
                'email' => (string) $user['ema002'],
                'perfil' => (string) $user['rol002'],
            ],
            'configuracao' => $configuration,
            'erro' => $error,
            'sucesso' => $success,
        ]);
    }
}
