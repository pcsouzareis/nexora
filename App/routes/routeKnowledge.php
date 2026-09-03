<?php

declare(strict_types=1);

use App\Controllers\KnowledgeController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/conhecimento', [KnowledgeController::class, 'index'])->add(AuthMiddleware::class);
    $app->post('/conhecimento', [KnowledgeController::class, 'storeBase'])->add(AuthMiddleware::class);
    $app->get('/conhecimento/{id:[0-9]+}', [KnowledgeController::class, 'show'])->add(AuthMiddleware::class);
    $app->post(
        '/conhecimento/{id:[0-9]+}/status',
        [KnowledgeController::class, 'updateBaseStatus']
    )->add(AuthMiddleware::class);
    $app->post('/conhecimento/{id:[0-9]+}/artigos', [KnowledgeController::class, 'storeArticle'])->add(AuthMiddleware::class);
    $app->post(
        '/conhecimento/{id:[0-9]+}/artigos/{article:[0-9]+}/status',
        [KnowledgeController::class, 'updateArticleStatus']
    )->add(AuthMiddleware::class);
    $app->post(
        '/conhecimento/{id:[0-9]+}/artigos/{article:[0-9]+}',
        [KnowledgeController::class, 'updateArticle']
    )->add(AuthMiddleware::class);
    $app->post(
        '/conhecimento/{id:[0-9]+}/configuracao-ia',
        [KnowledgeController::class, 'updateBaseAiConfiguration']
    )->add(AuthMiddleware::class);
    $app->post('/conhecimento/{id:[0-9]+}/n8n/nova-chave', [KnowledgeController::class, 'regenerateN8nKey'])->add(AuthMiddleware::class);
};
