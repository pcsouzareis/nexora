<?php

declare(strict_types=1);

use App\Controllers\ContractAcceptanceController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/contratos', [ContractAcceptanceController::class, 'index'])
        ->add(AuthMiddleware::class);
    $app->get('/contratos/dados', [ContractAcceptanceController::class, 'data'])
        ->add(AuthMiddleware::class);
};
