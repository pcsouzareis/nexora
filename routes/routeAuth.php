<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {

    /*
     * ---------------------------------------------------------
     * Exibe o formulário de login
     * ---------------------------------------------------------
     */
    $app->get(
        '/login',
        [AuthController::class, 'showLogin']
    )->setName('auth.login');

    /*
     * ---------------------------------------------------------
     * Processa o login
     * ---------------------------------------------------------
     */
    $app->post(
        '/login',
        [AuthController::class, 'login']
    )->setName('auth.authenticate');

    $app->get(
        '/minha-senha',
        [AuthController::class, 'showChangePassword']
    )->setName('auth.password.show')->add(AuthMiddleware::class);

    $app->post(
        '/minha-senha',
        [AuthController::class, 'changePassword']
    )->setName('auth.password.update')->add(AuthMiddleware::class);

    /*
     * ---------------------------------------------------------
     * Encerra a sessão
     * ---------------------------------------------------------
     */
    $app->get(
        '/logout',
        [AuthController::class, 'logout']
    )->setName('auth.logout');
};
