<?php

declare(strict_types=1);

use Slim\App;

return static function (App $app): void {

    $arquivosRotas = [
        __DIR__ . '/routeHome.php',
        __DIR__ . '/routeAuth.php',
        __DIR__ . '/routeCurrentCompany.php',
        __DIR__ . '/routeLicenseContract.php',
        __DIR__ . '/routeContracts.php',
        __DIR__ . '/routeDashboard.php',
        __DIR__ . '/routeAI.php',
        __DIR__ . '/routeWebhookTest.php',
        __DIR__ . '/routeKnowledge.php',
        __DIR__ . '/routeConversations.php',
        __DIR__ . '/routeChannels.php',
        __DIR__ . '/routeIntegrations.php',
        __DIR__ . '/routeAudit.php',
        __DIR__ . '/routeEmpresas.php',
        __DIR__ . '/routeUsuarios.php',
        __DIR__ . '/routeApi.php',
        __DIR__ . '/routeWebchat.php',
    ];

    foreach ($arquivosRotas as $arquivo) {
        $registrarRotas = require $arquivo;

        $registrarRotas($app);
    }
};
