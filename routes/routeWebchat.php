<?php

declare(strict_types=1);

use App\Controllers\WebchatController;
use Slim\App;

return static function (App $app): void {
    $app->get('/chat/{token:[A-Za-z0-9_-]+}', [WebchatController::class, 'widget']);
    $app->post('/api/webchat/{token:[A-Za-z0-9_-]+}/mensagens', [WebchatController::class, 'send']);
    $app->get('/api/webchat/{token:[A-Za-z0-9_-]+}/mensagens/{session:[A-Za-z0-9_-]+}', [WebchatController::class, 'messages']);
    $app->post('/api/webchat/{token:[A-Za-z0-9_-]+}/encerrar', [WebchatController::class, 'close']);
};
