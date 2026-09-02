<?php

declare(strict_types=1);

use App\Controllers\ChannelController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/canais', [ChannelController::class, 'index'])->add(AuthMiddleware::class);
    $app->get('/canais/novo', [ChannelController::class, 'create'])->add(AuthMiddleware::class);
    $app->post('/canais', [ChannelController::class, 'store'])->add(AuthMiddleware::class);
    $app->get('/canais/{id:[0-9]+}', [ChannelController::class, 'show'])->add(AuthMiddleware::class);
    $app->get('/canais/{id:[0-9]+}/editar', [ChannelController::class, 'edit'])->add(AuthMiddleware::class);
    $app->post('/canais/{id:[0-9]+}', [ChannelController::class, 'update'])->add(AuthMiddleware::class);
    $app->post('/canais/{id:[0-9]+}/testar-email', [ChannelController::class, 'testEmail'])->add(AuthMiddleware::class);
    $app->post('/canais/{id:[0-9]+}/testar-telegram', [ChannelController::class, 'testTelegram'])->add(AuthMiddleware::class);
    $app->post('/canais/{id:[0-9]+}/sincronizar-telegram', [ChannelController::class, 'synchronizeTelegram'])->add(AuthMiddleware::class);
    $app->post('/canais/{id:[0-9]+}/nova-chave', [ChannelController::class, 'regenerateWebhookKey'])->add(AuthMiddleware::class);
    $app->post('/canais/{id:[0-9]+}/novo-token-webchat', [ChannelController::class, 'regenerateWebchatToken'])->add(AuthMiddleware::class);
};
