<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Slim\App;

return static function (App $app): void {

    /*
     * ---------------------------------------------------------
     * Página inicial
     * ---------------------------------------------------------
     */
    $app->get(
        '/',
        [HomeController::class, 'index']
    )->setName('home');

    /*
     * ---------------------------------------------------------
     * Health Check
     * ---------------------------------------------------------
     */
    $app->get(
        '/health',
        [HomeController::class, 'health']
    )->setName('health');
};