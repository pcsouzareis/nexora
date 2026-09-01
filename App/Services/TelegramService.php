<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TelegramChannelRepository;
use App\Support\Encryption;
use GuzzleHttp\Client;
use RuntimeException;

final class TelegramService
{
    public function __construct(private readonly TelegramChannelRepository $channels, private readonly Encryption $encryption, private readonly WebhookMessageProcessor $processor) {}

    public function test(array $channel): string
    {
        $result = $this->request($channel, 'getMe');
        $bot = (string) ($result['username'] ?? $result['first_name'] ?? 'bot');
        return 'Conexão com o Telegram validada para @' . ltrim($bot, '@') . '.';
    }

    public function synchronize(array $channel): int
    {
        $offset = isset($channel['upt003']) && $channel['upt003'] !== null ? (int) $channel['upt003'] + 1 : null;
        $updates = $this->request($channel, 'getUpdates', array_filter(['offset' => $offset, 'limit' => 100]));
        $processed = 0;
        foreach ($updates as $update) {
            $updateId = (int) ($update['update_id'] ?? 0);
            $message = $update['message'] ?? null;
            if (is_array($message) && isset($message['chat']['id'], $message['message_id']) && trim((string) ($message['text'] ?? '')) !== '') {
                $from = (array) ($message['from'] ?? []);
                $name = trim((string) (($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')));
                try {
                    $result = $this->processor->process((int) $channel['cod001'], (int) $channel['cod003'], [
                        'external_id' => (string) ($from['id'] ?? $message['chat']['id']),
                        'name' => $name !== '' ? $name : null,
                        'conversation_id' => (string) $message['chat']['id'],
                        'message_id' => (string) $message['message_id'],
                        'base_id' => (int) $channel['cod005'],
                        'message' => trim((string) $message['text']),
                    ]);
                    $reply = $result['body']['reply'] ?? null;
                    if (!$result['body']['duplicate'] && $channel['outtel003'] && is_array($reply) && isset($reply['message'])) $this->send($channel, (string) $message['chat']['id'], (string) $reply['message']);
                    $processed++;
                } catch (\InvalidArgumentException) { }
            }
            if ($updateId > 0) $this->channels->setLastUpdate((int) $channel['cod001'], (int) $channel['cod003'], $updateId);
        }
        return $processed;
    }

    private function send(array $channel, string $chatId, string $message): void { $this->request($channel, 'sendMessage', ['chat_id' => $chatId, 'text' => mb_substr($message, 0, 4096)]); }
    private function request(array $channel, string $method, array $parameters = []): array
    {
        if (empty($channel['bot003'])) throw new RuntimeException('Informe e salve o token do bot Telegram antes de continuar.');
        try {
            $token = $this->encryption->decrypt((string) $channel['bot003']);
            $response = (new Client(['timeout' => 25, 'connect_timeout' => 10, 'http_errors' => false]))->post('https://api.telegram.org/bot' . $token . '/' . $method, ['json' => $parameters]);
            $body = json_decode((string) $response->getBody(), true);
        } catch (\Throwable $exception) { throw new RuntimeException('Não foi possível conectar ao Telegram.'); }
        if (!is_array($body) || !($body['ok'] ?? false)) throw new RuntimeException('Telegram recusou a solicitação: ' . (string) ($body['description'] ?? 'erro desconhecido.'));
        return (array) ($body['result'] ?? []);
    }
}
