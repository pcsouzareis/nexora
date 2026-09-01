<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Encryption
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $key;

    public function __construct()
    {
        if (
            !function_exists('openssl_encrypt') ||
            !function_exists('openssl_decrypt')
        ) {
            throw new RuntimeException(
                'A extensão OpenSSL não está disponível no PHP.'
            );
        }

        $encodedKey = (string) ($_ENV['APP_ENCRYPTION_KEY'] ?? '');
        $key = base64_decode($encodedKey, true);

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException(
                'APP_ENCRYPTION_KEY inválida ou não configurada.'
            );
        }

        $this->key = $key;
    }

    public function encrypt(string $value): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $encrypted = openssl_encrypt(
            $value,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($encrypted === false) {
            throw new RuntimeException(
                'Não foi possível criptografar a credencial da IA.'
            );
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $value): string
    {
        $payload = base64_decode($value, true);

        if (
            $payload === false ||
            strlen($payload) < self::IV_LENGTH + self::TAG_LENGTH
        ) {
            throw new RuntimeException('Credencial da IA inválida.');
        }

        $iv = substr($payload, 0, self::IV_LENGTH);
        $tag = substr(
            $payload,
            self::IV_LENGTH,
            self::TAG_LENGTH
        );
        $encrypted = substr(
            $payload,
            self::IV_LENGTH + self::TAG_LENGTH
        );

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new RuntimeException(
                'Não foi possível ler a credencial da IA.'
            );
        }

        return $decrypted;
    }
}