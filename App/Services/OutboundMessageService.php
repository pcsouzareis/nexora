<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OutboundMessageRepository;
use App\Support\Encryption;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class OutboundMessageService
{
    public function __construct(private readonly OutboundMessageRepository $outbound, private readonly Encryption $encryption) {}

    public function deliverMessage(int $companyCode, int $conversationCode, int $messageCode, string $message): void
    {
        $channel = $this->outbound->destination($companyCode, $conversationCode);
        if ($channel === null) return;

        if ($channel['tip003'] === 'Telegram') {
            $this->deliverTelegram($companyCode, $channel, $messageCode, $message);
            return;
        }

        if ($channel['tip003'] !== 'WhatsApp' || !$channel['out003']) return;

        $instance = trim((string) ($channel['ins003'] ?? ''));
        $token = (string) ($channel['tok003'] ?? '');
        $clientToken = (string) ($channel['cli003'] ?? '');
        $phone = trim((string) ($channel['destination'] ?? ''));
        if ($instance === '' || $token === '' || $clientToken === '' || $phone === '') {
            $this->outbound->record($companyCode, (int) $channel['cod003'], $messageCode, 'Falha', null, 'Configuração da Z-API ou destinatário ausente.');
            return;
        }

        try {
            $endpoint = 'https://api.z-api.io/instances/' . rawurlencode($instance) . '/token/' . rawurlencode($this->encryption->decrypt($token)) . '/send-text';
            $response = (new Client(['timeout' => 20, 'connect_timeout' => 10, 'http_errors' => false]))->post($endpoint, [
                'headers' => ['Client-Token' => $this->encryption->decrypt($clientToken), 'Content-Type' => 'application/json'],
                'json' => ['phone' => $phone, 'message' => $message],
            ]);
            $body = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $this->outbound->record($companyCode, (int) $channel['cod003'], $messageCode, 'Enfileirada', is_array($body) ? (string) ($body['messageId'] ?? $body['id'] ?? '') : null);
                return;
            }
            $this->outbound->record($companyCode, (int) $channel['cod003'], $messageCode, 'Falha', null, 'Z-API recusou o envio (HTTP ' . $response->getStatusCode() . ').');
        } catch (GuzzleException|\RuntimeException) {
            $this->outbound->record($companyCode, (int) $channel['cod003'], $messageCode, 'Falha', null, 'Não foi possível enviar a mensagem pela Z-API.');
        }
    }

    private function deliverTelegram(int $companyCode, array $channel, int $messageCode, string $message): void
    {
        $token = (string) ($channel['bot003'] ?? '');
        $destination = trim((string) ($channel['conversation_destination'] ?? ''));
        if (!$channel['outtel003'] || $token === '' || $destination === '') {
            $this->outbound->record($companyCode, (int) $channel['cod003'], $messageCode, 'Falha', null, 'Configuração do Telegram ou destinatário ausente.');
            return;
        }
        try {
            $response = (new Client(['timeout' => 20, 'connect_timeout' => 10, 'http_errors' => false]))->post('https://api.telegram.org/bot' . $this->encryption->decrypt($token) . '/sendMessage', ['json' => ['chat_id' => $destination, 'text' => mb_substr($message, 0, 4096)]]);
            $body = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && is_array($body) && ($body['ok'] ?? false)) {
                $this->outbound->record($companyCode, (int) $channel['cod003'], $messageCode, 'Enviada', (string) ($body['result']['message_id'] ?? ''));
                return;
            }
            $this->outbound->record($companyCode, (int) $channel['cod003'], $messageCode, 'Falha', null, 'Telegram recusou o envio.');
        } catch (GuzzleException|\RuntimeException) {
            $this->outbound->record($companyCode, (int) $channel['cod003'], $messageCode, 'Falha', null, 'Não foi possível enviar a mensagem pelo Telegram.');
        }
    }
}
