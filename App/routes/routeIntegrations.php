<?php

declare(strict_types=1);

use App\Controllers\IntegrationController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/integracoes', [IntegrationController::class, 'index'])
        ->add(AuthMiddleware::class);
};
