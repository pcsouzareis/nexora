<?php

declare(strict_types=1);

use App\Controllers\AuditController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/auditoria', [AuditController::class, 'index'])->add(AuthMiddleware::class);
    $app->get('/auditoria/dados', [AuditController::class, 'data'])->add(AuthMiddleware::class);
};
