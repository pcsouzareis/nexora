<?php

declare(strict_types=1);

use App\Controllers\WebhookTestController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/testes/webhook', [WebhookTestController::class, 'form'])
        ->setName('webhook.test.form')
        ->add(AuthMiddleware::class);

    $app->post('/testes/webhook', [WebhookTestController::class, 'send'])
        ->setName('webhook.test.send')
        ->add(AuthMiddleware::class);
};
