<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Repositories\WebhookRepository;
use App\Services\CurrentCompanyContext;
use App\Services\WebhookMessageProcessor;
use App\Support\Permission;
use App\Support\Session;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class WebhookTestController
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly WebhookRepository $webhooks,
        private readonly CurrentCompanyContext $companies,
        private readonly WebhookMessageProcessor $processor
    ) {}

    public function form(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->currentUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        return $this->render($response, $user, $this->defaults(), []);
    }

    public function send(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->currentUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->render($response, $user, $this->defaults(), [], 'Dados inválidos.');
        }

        $data = [
            'channel_id' => (int) ($body['channel_id'] ?? 0),
            'base_id' => (int) ($body['base_id'] ?? 0),
            'external_id' => trim((string) ($body['external_id'] ?? '')),
            'name' => trim((string) ($body['name'] ?? '')),
            'conversation_id' => trim((string) ($body['conversation_id'] ?? '')),
            'message' => trim((string) ($body['message'] ?? '')),
        ];

        if (
            $data['channel_id'] <= 0 || $data['base_id'] <= 0 ||
            $data['external_id'] === '' || strlen($data['external_id']) > 120 ||
            $data['message'] === '' || strlen($data['message']) > 10000 ||
            strlen($data['name']) > 150 || strlen($data['conversation_id']) > 120
        ) {
            return $this->render($response, $user, $data, [], 'Preencha todos os campos obrigatórios corretamente.');
        }

        if ($data['conversation_id'] === '') {
            $data['conversation_id'] = 'teste:' . $data['external_id'];
        }

        // Não recebe o ID do navegador: cada execução é uma nova mensagem.
        $data['message_id'] = 'teste-' . bin2hex(random_bytes(12));

        $companyCode = $this->companies->companyCode($user);
        $channel = $this->webhooks->findActiveChannel($data['channel_id']);

        if ($channel === null || (int) $channel['cod001'] !== $companyCode) {
            return $this->render($response, $user, $data, [], 'Canal não encontrado para a empresa atual.');
        }

        try {
            $result = $this->processor->process($companyCode, $data['channel_id'], [
                'external_id' => $data['external_id'],
                'name' => $data['name'] !== '' ? $data['name'] : null,
                'conversation_id' => $data['conversation_id'],
                'message_id' => $data['message_id'],
                'base_id' => $data['base_id'],
                'message' => $data['message'],
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->render($response, $user, $data, [], $exception->getMessage());
        }

        return $this->render($response, $user, $data, $result['body']);
    }

    private function currentUser(ResponseInterface $response): array|ResponseInterface
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

        if (!Permission::allows($user, Permission::AI_WEBHOOK_TEST)) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        return $user;
    }

    private function defaults(): array
    {
        return [
            'external_id' => 'teste-cliente-001',
            'name' => 'Cliente de teste',
            'conversation_id' => 'teste-conversa-001',
            'message' => 'Qual é o horário de atendimento?',
        ];
    }

    private function render(
        ResponseInterface $response,
        array $user,
        array $data,
        array $result,
        ?string $error = null
    ): ResponseInterface {
        $companyCode = $this->companies->companyCode($user);

        return $this->view->render($response, 'ai/webhook-test.twig', [
            'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
            'usuario' => [
                'codigo' => (int) $user['cod002'],
                'nome' => (string) $user['des002'],
                'email' => (string) $user['ema002'],
                'perfil' => (string) $user['rol002'],
            ],
            'canais' => $this->webhooks->findActiveChannelsByCompany($companyCode),
            'bases' => $this->webhooks->findActiveBasesByCompany($companyCode),
            'dados' => $data,
            'resultado' => $result,
            'erro' => $error,
        ]);
    }
}
