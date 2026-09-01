<?php

declare(strict_types=1);

use App\Controllers\AIConfigurationController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/configuracoes/ia', [AIConfigurationController::class, 'edit'])
        ->setName('ai.configuration.edit')
        ->add(AuthMiddleware::class);

    $app->post('/configuracoes/ia', [AIConfigurationController::class, 'update'])
        ->setName('ai.configuration.update')
        ->add(AuthMiddleware::class);

    $app->post('/configuracoes/ia/testar', [AIConfigurationController::class, 'test'])
        ->setName('ai.configuration.test')
        ->add(AuthMiddleware::class);
};
