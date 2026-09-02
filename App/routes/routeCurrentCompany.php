<?php

declare(strict_types=1);

use App\Controllers\CurrentCompanyController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->post('/empresa-atual', [CurrentCompanyController::class, 'update'])
        ->add(AuthMiddleware::class);
};
