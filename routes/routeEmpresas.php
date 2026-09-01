<?php

declare(strict_types=1);

use App\Controllers\CompanyController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface;

return static function (App $app): void {

    /*
     * ---------------------------------------------------------
     * Cadastro de Empresas
     * ---------------------------------------------------------
     */
    $app->group(
        '/empresas',
        function (
            RouteCollectorProxyInterface $group
        ): void {

            /*
             * Lista as empresas.
             */
            $group->get(
                '',
                [CompanyController::class, 'index']
            )->setName('companies.index');

            /*
             * Exibe o formulário de cadastro.
             */
            $group->get(
                '/criar',
                [CompanyController::class, 'create']
            )->setName('companies.create');

            /*
             * Grava uma nova empresa.
             */
            $group->post(
                '',
                [CompanyController::class, 'store']
            )->setName('companies.store');

            /*
             * Exibe uma empresa.
             */
            $group->get(
                '/{id:[0-9]+}',
                [CompanyController::class, 'show']
            )->setName('companies.show');

            /*
             * Exibe o formulário de edição.
             */
            $group->get(
                '/{id:[0-9]+}/editar',
                [CompanyController::class, 'edit']
            )->setName('companies.edit');

            /*
             * Atualiza uma empresa.
             */
            $group->post(
                '/{id:[0-9]+}',
                [CompanyController::class, 'update']
            )->setName('companies.update');
        }
    )->add(AuthMiddleware::class);
};