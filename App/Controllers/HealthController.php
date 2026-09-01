<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {

        $database = 'ok';

        try {

            $this->database
                ->pdo()
                ->query('SELECT 1');

        } catch (\Throwable $exception) {

            $database = 'error';
        }

        $data = [
            'status' =>
                $database === 'ok'
                    ? 'ok'
                    : 'degraded',

            'application' =>
                $_ENV['APP_NAME'],

            'database' => $database,

            'timestamp' => date(DATE_ATOM)
        ];

        $response->getBody()->write(
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE |
                JSON_PRETTY_PRINT
            )
        );

        return $response
            ->withHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            );
    }
}
