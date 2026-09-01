<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\WebhookController;
use App\Controllers\ZApiWebhookController;
use App\Controllers\MetaWebhookController;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface;

return static function (App $app): void {

    /*
     * ---------------------------------------------------------
     * API
     * ---------------------------------------------------------
     */
    $app->group(
        '/api',
        function (
            RouteCollectorProxyInterface $group
        ): void {

            $group->get(
                '/health',
                [HomeController::class, 'health']
            )->setName('api.health');

            $group->post(
                '/webhooks/{channel:[0-9]+}',
                [WebhookController::class, 'receive']
            )->setName('api.webhooks.receive');

            $group->post('/zapi/{token:[a-f0-9]{40}}/receber', [ZApiWebhookController::class, 'receive']);
            $group->post('/zapi/{token:[a-f0-9]{40}}/entrega', [ZApiWebhookController::class, 'delivery']);
            $group->post('/zapi/{token:[a-f0-9]{40}}/status', [ZApiWebhookController::class, 'status']);
            $group->get('/meta/{token:[a-f0-9]{40}}', [MetaWebhookController::class, 'verify']);
            $group->post('/meta/{token:[a-f0-9]{40}}', [MetaWebhookController::class, 'receive']);
        }
    );
};
