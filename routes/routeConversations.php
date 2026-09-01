<?php

declare(strict_types=1);

use App\Controllers\ConversationController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/conversas', [ConversationController::class, 'index'])->add(AuthMiddleware::class);
    $app->get('/conversas/resumo', [ConversationController::class, 'summary'])->add(AuthMiddleware::class);
    $app->get('/conversas/{id:[0-9]+}', [ConversationController::class, 'show'])->add(AuthMiddleware::class);
    $app->post('/conversas/{id:[0-9]+}/assumir', [ConversationController::class, 'take'])->add(AuthMiddleware::class);
    $app->post('/conversas/{id:[0-9]+}/responder', [ConversationController::class, 'reply'])->add(AuthMiddleware::class);
    $app->post('/conversas/{id:[0-9]+}/encerrar', [ConversationController::class, 'close'])->add(AuthMiddleware::class);
};
