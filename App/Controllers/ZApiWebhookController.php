<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ChannelRepository;
use App\Repositories\OutboundMessageRepository;
use App\Services\OutboundMessageService;
use App\Services\WebhookMessageProcessor;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ZApiWebhookController
{
    public function __construct(
        private readonly ChannelRepository $channels,
        private readonly WebhookMessageProcessor $processor,
        private readonly OutboundMessageService $outbound,
        private readonly OutboundMessageRepository $deliveries
    ) {}

    public function receive(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = (string) ($args['token'] ?? '');
        $channel = $this->channel($token);
        $body = $this->body($request);
        $this->log('Webhook Z-API de recebimento recebido.', [
            'token_valid' => $channel !== null,
            'body_keys' => is_array($body) ? array_keys($body) : [],
            'content_type' => $request->getHeaderLine('Content-Type'),
        ]);
        if ($channel === null) {
            $this->log('Webhook Z-API rejeitado: canal não encontrado ou token inválido.');
            return $this->json($response, ['error' => 'Canal não encontrado.'], 404);
        }
        if (!is_array($body)) {
            $this->log('Webhook Z-API rejeitado: JSON inválido.');
            return $this->json($response, ['error' => 'JSON inválido.'], 422);
        }
        if (($body['fromMe'] ?? false) || ($body['isStatusReply'] ?? false) || ($body['isGroup'] ?? false) || ($body['isNewsletter'] ?? false)) return $this->json($response, ['received' => true, 'ignored' => true]);

        $message = trim((string) (($body['text']['message'] ?? '')));
        $phone = trim((string) ($body['phone'] ?? '')) ?: trim((string) ($body['senderLid'] ?? ''));
        $messageId = trim((string) ($body['messageId'] ?? ''));
        if ($message === '' || $phone === '' || $messageId === '' || (int) ($channel['cod005'] ?? 0) <= 0) {
            $this->log('Webhook Z-API rejeitado: campos obrigatórios ausentes.', [
                'has_message' => $message !== '', 'has_phone' => $phone !== '',
                'has_message_id' => $messageId !== '', 'has_base' => (int) ($channel['cod005'] ?? 0) > 0,
            ]);
            return $this->json($response, ['error' => 'Evento sem texto, identificador ou base padrão.'], 422);
        }

        try {
            $result = $this->processor->process((int) $channel['cod001'], (int) $channel['cod003'], [
                'external_id' => $phone, 'name' => trim((string) ($body['senderName'] ?? $body['chatName'] ?? '')),
                'conversation_id' => $phone, 'message_id' => $messageId, 'base_id' => (int) $channel['cod005'], 'message' => $message,
            ]);
        } catch (InvalidArgumentException) {
            return $this->json($response, ['error' => 'Base inválida para este canal.'], 422);
        }

        $reply = $result['body']['reply'] ?? null;
        if (is_array($reply) && isset($reply['message_id'], $reply['message'])) {
            $this->outbound->deliverMessage((int) $channel['cod001'], (int) $result['body']['conversation_id'], (int) $reply['message_id'], (string) $reply['message']);
        }
        return $this->json($response, $result['body'], $result['status']);
    }

    private function body(ServerRequestInterface $request): mixed
    {
        $body = $request->getParsedBody();
        if (is_array($body)) return $body;

        $raw = trim((string) $request->getBody());
        if ($raw === '') return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function log(string $message, array $context = []): void
    {
        error_log($message . ($context === [] ? '' : ' ' . (string) json_encode($context, JSON_UNESCAPED_UNICODE)));
    }

    public function delivery(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $channel = $this->channel((string) ($args['token'] ?? ''));
        $body = $request->getParsedBody();
        if ($channel === null) return $this->json($response, ['error' => 'Canal não encontrado.'], 404);
        if (!is_array($body)) return $this->json($response, ['error' => 'JSON inválido.'], 422);
        $messageId = trim((string) ($body['messageId'] ?? ''));
        if ($messageId === '') return $this->json($response, ['error' => 'messageId não informado.'], 422);
        $error = isset($body['error']) && (string) $body['error'] !== '' ? (string) $body['error'] : null;
        $this->deliveries->updateDelivery((int) $channel['cod001'], $messageId, $error);
        return $this->json($response, ['received' => true]);
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $channel = $this->channel((string) ($args['token'] ?? ''));
        $body = $request->getParsedBody();
        if ($channel === null) return $this->json($response, ['error' => 'Canal não encontrado.'], 404);
        if (!is_array($body)) return $this->json($response, ['error' => 'JSON inválido.'], 422);
        $status = match (strtoupper((string) ($body['status'] ?? ''))) {
            'SENT' => 'Enviada', 'RECEIVED' => 'Entregue', 'READ' => 'Lida', 'PLAYED' => 'Reproduzida', default => null,
        };
        $ids = is_array($body['ids'] ?? null) ? $body['ids'] : [];
        if ($status === null || $ids === []) return $this->json($response, ['received' => true, 'ignored' => true]);
        foreach ($ids as $id) {
            if (is_scalar($id) && (string) $id !== '') $this->deliveries->updateStatus((int) $channel['cod001'], (string) $id, $status);
        }
        return $this->json($response, ['received' => true]);
    }

    private function channel(string $token): ?array { return preg_match('/^[a-f0-9]{40}$/', $token) === 1 ? $this->channels->findActiveZapiByToken($token) : null; }
    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface { $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status); }
}
