<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Encryption;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class AIConnectionTester
{
    public function __construct(private readonly Encryption $encryption) {}

    public function test(array $configuration): string
    {
        if (!$this->isActive($configuration['sts013'] ?? false)) {
            throw new RuntimeException('A configuração da IA está inativa para esta empresa.');
        }

        $url = rtrim((string) ($configuration['url013'] ?? ''), '/');
        $model = trim((string) ($configuration['mod013'] ?? ''));
        $encryptedKey = (string) ($configuration['key013'] ?? '');

        if ($url === '' || $model === '' || $encryptedKey === '') {
            throw new RuntimeException('Salve URL, modelo e chave da API antes de testar a conexão.');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('A URL da IA deve usar HTTP ou HTTPS.');
        }

        $endpoint = str_ends_with($url, '/responses')
            ? $url
            : $url . '/responses';

        try {
            $response = (new Client([
                'timeout' => 20,
                'connect_timeout' => 10,
                'http_errors' => false,
            ]))->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->encryption->decrypt($encryptedKey),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'input' => 'Responda apenas: conexão validada.',
                    'max_output_tokens' => 20,
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Não foi possível conectar à URL da IA.');
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                'A IA recusou o teste de conexão (HTTP ' . $status . '). Verifique a URL, chave e modelo.'
            );
        }

        return 'Conexão com a IA validada com sucesso.';
    }

    private function isActive(mixed $status): bool
    {
        return in_array($status, [true, 1, '1', 't', 'true'], true);
    }
}
