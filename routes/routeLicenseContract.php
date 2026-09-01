<?php

declare(strict_types=1);

use App\Controllers\LicenseContractController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/contrato/licenca', [LicenseContractController::class, 'show'])
        ->add(AuthMiddleware::class);
};
