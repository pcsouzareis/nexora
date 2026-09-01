<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {

    /*
     * ---------------------------------------------------------
     * Dashboard
     * ---------------------------------------------------------
     */
    $app->get(
        '/dashboard',
        [DashboardController::class, 'index']
    )
        ->setName('dashboard')
        ->add(AuthMiddleware::class);
};