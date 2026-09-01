<?php

declare(strict_types=1);

use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {

    $app->get(
        '/usuarios',
        [UserController::class, 'index']
    )->setName('users.index')->add(AuthMiddleware::class);

    $app->get(
        '/usuarios/novo',
        [UserController::class, 'create']
    )->setName('users.create')->add(AuthMiddleware::class);

    $app->post(
        '/usuarios',
        [UserController::class, 'store']
    )->setName('users.store')->add(AuthMiddleware::class);

    $app->get(
        '/usuarios/{id:[0-9]+}',
        [UserController::class, 'show']
    )->setName('users.show')->add(AuthMiddleware::class);

    $app->get(
        '/usuarios/{id:[0-9]+}/editar',
        [UserController::class, 'edit']
    )->setName('users.edit')->add(AuthMiddleware::class);

    $app->post(
        '/usuarios/{id:[0-9]+}',
        [UserController::class, 'update']
    )->setName('users.update')->add(AuthMiddleware::class);
};
