<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\Session;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users
    ) {}

    public function login(int $code, string $password): bool
    {
        $user = $this->users->findByCode($code);

        if ($user === null) {
            return false;
        }

        if (!(bool) $user['sts002']) {
            return false;
        }

        if (!password_verify($password, $user['sen002'])) {
            return false;
        }

        Session::authenticate(
            (int) $user['cod002'],
            (int) $user['cod001'],
            (string) $user['rol002']
        );

        return true;
    }

    public function logout(): void
    {
        Session::logout();
    }

    public function changePassword(
        int $code,
        string $currentPassword,
        string $newPassword
    ): bool {
        $user = $this->users->findByCode($code);

        if (
            $user === null ||
            !password_verify($currentPassword, (string) $user['sen002'])
        ) {
            return false;
        }

        $this->users->updatePassword(
            $code,
            password_hash($newPassword, PASSWORD_DEFAULT)
        );

        return true;
    }
}
