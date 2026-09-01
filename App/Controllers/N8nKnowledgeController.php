<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\KnowledgeRepository;
use App\Repositories\AuditRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class N8nKnowledgeController
{
    public function __construct(private readonly KnowledgeRepository $knowledge, private readonly AuditRepository $audit) {}

    public function upsert(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $base = $this->knowledge->findBaseForN8n((int) ($args['id'] ?? 0));
        $key = $request->getHeaderLine('X-Nexora-N8N-Key');
        if ($base === null || $key === '' || empty($base['nkh005']) || !password_verify($key, (string) $base['nkh005'])) {
            return $this->json($response, ['error' => 'Não autorizado.'], 401);
        }
        if (!$base['sts005']) return $this->json($response, ['error' => 'Base inativa.'], 409);

        $body = $request->getParsedBody();
        if (!is_array($body)) return $this->json($response, ['error' => 'JSON inválido.'], 422);
        $data = [
            'external_id' => trim((string) ($body['external_id'] ?? '')),
            'title' => trim((string) ($body['titulo'] ?? '')),
            'content' => trim((string) ($body['conteudo'] ?? '')),
            'url' => trim((string) ($body['url'] ?? '')),
            'visibility' => (int) ($body['visibilidade'] ?? 2),
            'active' => filter_var($body['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ];
        if ($data['external_id'] === '' || strlen($data['external_id']) > 190 || $data['title'] === '' || strlen($data['title']) > 200 || $data['content'] === '' || strlen($data['content']) > 50000 || !in_array($data['visibility'], [1, 2, 3], true)) {
            return $this->json($response, ['error' => 'Dados do artigo inválidos.'], 422);
        }
        if ($data['url'] !== '' && (!filter_var($data['url'], FILTER_VALIDATE_URL) || strlen($data['url']) > 500)) {
            return $this->json($response, ['error' => 'URL da fonte inválida.'], 422);
        }

        $result = $this->knowledge->upsertN8nArticle((int) $base['cod005'], $data);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $this->audit->record((int) $base['cod001'], null, 'SYNC', 'n8n Artigo', (int) $result['article_id'], 'n8n: artigo ' . $result['action'] . ' (' . $data['external_id'] . ').', is_string($ip) ? $ip : null);
        return $this->json($response, ['received' => true, 'action' => $result['action'], 'article_id' => $result['article_id']]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }
}
