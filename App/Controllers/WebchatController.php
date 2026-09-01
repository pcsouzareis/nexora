<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\WebchatRepository;
use App\Repositories\WebchatRateLimitRepository;
use App\Services\WebhookMessageProcessor;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class WebchatController
{
    public function __construct(
        private readonly Twig $view,
        private readonly WebchatRepository $webchat,
        private readonly WebchatRateLimitRepository $rateLimit,
        private readonly WebhookMessageProcessor $processor
    ) {}

    public function widget(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = (string) ($args['token'] ?? '');
        $channel = $this->webchat->findActiveChannel($token);

        if ($channel === null) {
            $response->getBody()->write('Webchat não encontrado.');
            return $response->withStatus(404);
        }

        return $this->view->render($response, 'webchat/widget.twig', [
            'channel_token' => $token,
            'channel' => $channel,
        ]);
    }

    public function send(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = (string) ($args['token'] ?? '');
        $channel = $this->webchat->findActiveChannel($token);
        if ($channel === null) return $this->json($response, ['error' => 'Webchat não encontrado.'], 404);

        $body = $request->getParsedBody();
        if (!is_array($body)) return $this->json($response, ['error' => 'JSON inválido.'], 422);

        $sessionId = trim((string) ($body['session_id'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9_-]{16,120}$/', $sessionId) || strlen($name) > 150 || $message === '' || strlen($message) > 10000) {
            return $this->json($response, ['error' => 'Dados inválidos.'], 422);
        }

        $existingSession = $this->webchat->findSession((int) $channel['cod003'], (int) $channel['cod001'], $sessionId);
        if ($existingSession !== null && in_array($existingSession['sts008'], ['Encerrada', 'Cancelada'], true)) {
            return $this->json($response, ['error' => 'Este atendimento já foi encerrado.'], 409);
        }

        if (!$this->rateLimit->allows((int) $channel['cod001'], (int) $channel['cod003'], $sessionId, $this->clientIp($request))) {
            return $this->json(
                $response->withHeader('Retry-After', '300'),
                ['error' => 'Muitas mensagens em pouco tempo. Aguarde alguns minutos e tente novamente.'],
                429
            );
        }

        try {
            $result = $this->processor->process((int) $channel['cod001'], (int) $channel['cod003'], [
                'external_id' => 'web:' . $sessionId,
                'name' => $name !== '' ? $name : 'Visitante do site',
                'conversation_id' => 'web:' . $sessionId,
                'message_id' => 'web:' . bin2hex(random_bytes(16)),
                'base_id' => (int) $channel['cod005'],
                'message' => $message,
            ]);
        } catch (InvalidArgumentException) {
            return $this->json($response, ['error' => 'Não foi possível processar a mensagem.'], 422);
        }

        $session = $this->webchat->findSession((int) $channel['cod003'], (int) $channel['cod001'], $sessionId);
        if ($session !== null) {
            $this->webchat->touchSession((int) $session['cod008']);
        }

        return $this->json($response, $result['body'], $result['status']);
    }

    public function messages(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = (string) ($args['token'] ?? '');
        $sessionId = (string) ($args['session'] ?? '');
        $channel = $this->webchat->findActiveChannel($token);

        if ($channel === null || !preg_match('/^[A-Za-z0-9_-]{16,120}$/', $sessionId)) {
            return $this->json($response, ['error' => 'Não encontrado.'], 404);
        }

        $session = $this->webchat->findSession((int) $channel['cod003'], (int) $channel['cod001'], $sessionId);
        if ($session === null) return $this->json($response, ['messages' => [], 'status' => 'Nova']);
        if (!in_array($session['sts008'], ['Encerrada', 'Cancelada'], true)) {
            $this->webchat->touchSession((int) $session['cod008']);
        }

        return $this->json($response, [
            'messages' => $this->webchat->findMessages((int) $session['cod008']),
            'status' => $session['sts008'],
            'human_handling' => (bool) $session['human_handling'],
        ]);
    }

    public function close(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = (string) ($args['token'] ?? '');
        $channel = $this->webchat->findActiveChannel($token);
        $body = $request->getParsedBody();
        $sessionId = is_array($body) ? trim((string) ($body['session_id'] ?? '')) : '';
        if ($channel === null || !preg_match('/^[A-Za-z0-9_-]{16,120}$/', $sessionId)) return $this->json($response, ['error' => 'Não encontrado.'], 404);

        $closed = $this->webchat->closeSession((int) $channel['cod003'], (int) $channel['cod001'], $sessionId);
        return $this->json($response, ['closed' => $closed]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        return mb_substr($ip, 0, 45);
    }
}
