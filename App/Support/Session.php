<?php

declare(strict_types=1);

namespace App\Support;

final class Session
{
    private const AUTH_KEY = 'auth';
    private const CSRF_KEY = 'csrf_token';
    private const PROFILE_NAMES = [
        'D' => 'Administrador',
        'S' => 'Supervisor',
        'A' => 'Atendente',
    ];

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function authenticate(
        int $cod002,
        int $cod001,
        string $rol002        
    ): void {
        self::start();

        session_regenerate_id(true);

        unset($_SESSION[self::CSRF_KEY]);

        $_SESSION[self::AUTH_KEY] = [
            'cod002' => $cod002,
            'cod001' => $cod001,
            'empresa_atual' => $cod001,
            'rol002' => $rol002,
            'perfil_name' => self::PROFILE_NAMES[$rol002] ?? $rol002,
        ];
        
    }

    public static function isAuthenticated(): bool
    {
        self::start();
        
        return isset($_SESSION[self::AUTH_KEY])
            && is_array($_SESSION[self::AUTH_KEY])
            && isset(
                $_SESSION[self::AUTH_KEY]['cod002'],
                $_SESSION[self::AUTH_KEY]['cod001'],
                $_SESSION[self::AUTH_KEY]['rol002'],
                $_SESSION[self::AUTH_KEY]['perfil_name']
            );
    }

    public static function user(): ?array
    {
        if (!self::isAuthenticated()) {
            return null;
        }        
        return $_SESSION[self::AUTH_KEY];
    }

    public static function currentCompanyCode(): ?int
    {
        $user = self::user();

        if ($user === null) {
            return null;
        }

        $companyCode = $user['empresa_atual'] ?? $user['cod001'];

        return (int) $companyCode > 0 ? (int) $companyCode : null;
    }

    public static function setCurrentCompany(int $companyCode): void
    {
        self::start();

        if (!isset($_SESSION[self::AUTH_KEY]) || !is_array($_SESSION[self::AUTH_KEY])) {
            return;
        }

        $_SESSION[self::AUTH_KEY]['empresa_atual'] = $companyCode;
    }

    public static function csrfToken(): string
    {
        self::start();

        if (!isset($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_KEY];
    }

    public static function validCsrfToken(mixed $token): bool
    {
        self::start();
        $expected = $_SESSION[self::CSRF_KEY] ?? null;

        return is_string($token)
            && is_string($expected)
            && hash_equals($expected, $token);
    }

    /**
     * Logs out the current user.
     */
    public static function logout(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
