<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditRepository;
use App\Repositories\LicenseContractAccessRepository;
use App\Services\AuthService;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class AuthController
{
    public function __construct(
        private readonly Twig $view,
        private readonly AuthService $auth,
        private readonly AuditRepository $audit,
        private readonly LicenseContractAccessRepository $contractAccesses
    ) {}

    public function showLogin(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return $this->view->render(
            $response,
            'auth/login.twig',
            ['app_name' => $_ENV['APP_NAME'] ?? 'Nexora']
        );
    }

    public function login(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = (array) $request->getParsedBody();

        $code = (int) ($data['code'] ?? 0);
        $password = (string) ($data['password'] ?? '');

        if ($code <= 0 || $password === '') {
            return $this->view->render(
                $response->withStatus(422),
                'auth/login.twig',
                [
                    'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
                    'error' => 'Informe o código do usuário e a senha.',
                    'code' => $code > 0 ? $code : '',
                ]
            );
        }

        if (!$this->auth->login($code, $password)) {
            return $this->view->render(
                $response->withStatus(401),
                'auth/login.twig',
                [
                    'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
                    'error' => 'Código do usuário ou senha inválidos.',
                    'code' => $code,
                ]
            );
        }

        $user = Session::user();
        if ($user !== null) {
            $this->audit->record((int) $user['cod001'], (int) $user['cod002'], 'LOGIN', 'Sessão', null, 'Login realizado.', $this->clientIp($request));

            if (
                (string) $user['rol002'] === 'S'
                && !$this->contractAccesses->isAccepted(
                    (int) $user['cod001'],
                    (int) $user['cod002']
                )
            ) {
                return $response
                    ->withHeader('Location', '/contrato/licenca')
                    ->withStatus(302);
            }
        }

        return $response
            ->withHeader('Location', '/dashboard')
            ->withStatus(302);
    }

    public function logout(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = Session::user();
        if ($user !== null) {
            $this->audit->record((int) $user['cod001'], (int) $user['cod002'], 'LOGOUT', 'Sessão', null, 'Logout realizado.', $this->clientIp($request));
        }
        Session::logout();

        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }

    public function showChangePassword(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if (Session::user() === null) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        return $this->view->render(
            $response,
            'auth/login.twig',
            [
                'change_password' => true,
                'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
                'csrf_token' => Session::csrfToken(),
            ]
        );
    }

    public function changePassword(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $sessionUser = Session::user();

        if ($sessionUser === null) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $data = (array) $request->getParsedBody();
        $currentPassword = (string) ($data['senha_atual'] ?? '');
        $newPassword = (string) ($data['nova_senha'] ?? '');
        $confirmation = (string) ($data['confirmacao_senha'] ?? '');

        if (strlen($newPassword) < 8 || $newPassword !== $confirmation) {
            return $this->renderChangePassword(
                $response->withStatus(422),
                'A nova senha deve ter ao menos 8 caracteres e a confirmação deve ser igual.'
            );
        }

        if (!$this->auth->changePassword(
            (int) $sessionUser['cod002'],
            $currentPassword,
            $newPassword
        )) {
            return $this->renderChangePassword(
                $response->withStatus(422),
                'A senha atual está incorreta.'
            );
        }

        $this->audit->record((int) $sessionUser['cod001'], (int) $sessionUser['cod002'], 'UPDATE', 'Senha', (int) $sessionUser['cod002'], 'Senha própria atualizada.', $this->clientIp($request));

        return $this->renderChangePassword(
            $response,
            null,
            'Senha atualizada com sucesso.'
        );
    }

    private function renderChangePassword(
        ResponseInterface $response,
        ?string $error = null,
        ?string $success = null
    ): ResponseInterface {
        return $this->view->render(
            $response,
            'auth/login.twig',
            [
                'change_password' => true,
                'app_name' => $_ENV['APP_NAME'] ?? 'Nexora',
                'error' => $error,
                'success' => $success,
                'csrf_token' => Session::csrfToken(),
            ]
        );
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        return is_string($ip) ? $ip : null;
    }
}
