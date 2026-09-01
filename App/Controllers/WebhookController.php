<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\WebhookRepository;
use App\Services\WebhookMessageProcessor;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class WebhookController
{
    public function __construct(
        private readonly WebhookRepository $webhooks,
        private readonly WebhookMessageProcessor $processor
    ) {}

    public function receive(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $channelCode = (int) ($args['channel'] ?? 0);
        $channel = $this->webhooks->findActiveChannel($channelCode);
        
        if ($channel === null || !$this->validSecret($request, $channel['api003'] ?? null)) {
            return $this->json($response, ['error' => 'Não autorizado.'], 401);
        }

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->json($response, ['error' => 'JSON inválido.'], 422);
        }

        $externalId = trim((string) ($body['external_id'] ?? ''));
        $messageId = trim((string) ($body['message_id'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));
        $conversationId = trim((string) ($body['conversation_id'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));
        $baseCode = (int) ($body['base_id'] ?? 0);

        if ($externalId === '' || strlen($externalId) > 120 || $messageId === '' || strlen($messageId) > 120 || $message === '' || strlen($message) > 10000 || $baseCode <= 0) {
            return $this->json($response, ['error' => 'Informe external_id, message_id, base_id e message válidos.'], 422);
        }

        if ($conversationId === '') {
            $conversationId = 'webhook:' . $externalId;
        }

        if (strlen($conversationId) > 120 || strlen($name) > 150) {
            return $this->json($response, ['error' => 'Identificadores inválidos.'], 422);
        }

        $companyCode = (int) $channel['cod001'];

        try {
            $result = $this->processor->process($companyCode, $channelCode, [
                'external_id' => $externalId,
                'name' => $name !== '' ? $name : null,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'base_id' => $baseCode,
                'message' => $message,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        }

        return $this->json($response, $result['body'], $result['status']);
    }

    private function validSecret(ServerRequestInterface $request, mixed $hash): bool
    {
        $secret = $request->getHeaderLine('X-Nexora-Webhook-Key');
        
        return $secret !== '' && is_string($hash) && password_verify($secret, $hash);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
