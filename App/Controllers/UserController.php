<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CompanyRepository;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Support\Permission;
use App\Support\Session;
use App\Support\UserRole;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class UserController
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly CompanyRepository $companies,
        private readonly AuditRepository $audit
    ) {}

    /**
     * Lista os usuários.
     */
    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $authenticatedUser = $this->authenticatedUser($response);

        if ($authenticatedUser instanceof ResponseInterface) {
            return $authenticatedUser;
        }

        $profile = (string) $authenticatedUser['rol002'];

        if (!Permission::allows(
            $authenticatedUser,
            Permission::USER_ACCESS
        )) {
            return $this->redirect($response, '/minha-senha');
        }

        $users = $profile === 'D'
            ? $this->users->findAll()
            : $this->users->findAllCreatedBy(
                (int) $authenticatedUser['cod002']
            );

        return $this->view->render(
            $response,
            'user/index.twig',
            [
                'app_name' => $_ENV['APP_NAME'],
                'usuario' => $this->userView($authenticatedUser),
                'usuarios' => $users,
                'perfil_nome' =>
                    UserRole::PERFIL[$profile]
                    ?? 'Desconhecido',
            ]
        );
    }

    /**
     * Exibe o formulário de cadastro.
     */
    public function create(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $authenticatedUser = $this->authenticatedUser($response);

        if ($authenticatedUser instanceof ResponseInterface) {
            return $authenticatedUser;
        }

        if (!Permission::allows(
            $authenticatedUser,
            Permission::USER_CREATE
        )) {
            return $this->redirect($response, '/dashboard');
        }

        return $this->view->render(
            $response,
            'user/create.twig',
            [
                'app_name' => $_ENV['APP_NAME'],
                'usuario' => $this->userView($authenticatedUser),
                'empresas' => $this->availableCompanies($authenticatedUser),
                'perfis' => $this->availableProfiles($authenticatedUser),
                'dados' => [],
                'erro' => null,
            ]
        );
    }

    /**
     * Cadastra um usuário.
     */
    public function store(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $authenticatedUser = $this->authenticatedUser($response);

        if ($authenticatedUser instanceof ResponseInterface) {
            return $authenticatedUser;
        }

        if (!Permission::allows(
            $authenticatedUser,
            Permission::USER_CREATE
        )) {
            return $this->redirect($response, '/dashboard');
        }

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->renderCreateError(
                $response,
                $authenticatedUser,
                'Dados inválidos para cadastro.',
                []
            );
        }

        $data = [
            'cod001' => (int) ($body['cod001'] ?? 0),
            'des002' => trim((string) ($body['des002'] ?? '')),
            'ema002' => strtolower(
                trim((string) ($body['ema002'] ?? ''))
            ),
            'rol002' => strtoupper(
                trim((string) ($body['rol002'] ?? 'A'))
            ),
            'sts002' => isset($body['sts002']),
            'cri002' => (int) $authenticatedUser['cod002'],
        ];

        /*
         * Supervisor sempre cadastra Atendente
         * na própria empresa.
         */
        if ((string) $authenticatedUser['rol002'] === 'S') {
            $data['cod001'] = (int) $authenticatedUser['cod001'];
            $data['rol002'] = 'A';
        }

        $data['cod014'] = $this->users->findProfileCodeForRole(
            $data['rol002']
        );

        if ($data['cod014'] === null) {
            return $this->renderCreateError(
                $response,
                $authenticatedUser,
                'Perfil de acesso não encontrado.',
                $data
            );
        }

        $error = $this->validateData(
            $data,
            '',
            '',
            null,
            $authenticatedUser
        );

        if ($error !== null) {
            return $this->renderCreateError(
                $response,
                $authenticatedUser,
                $error,
                $data
            );
        }

        $code = $this->users->create($data);
        $this->audit->record((int) $data['cod001'], (int) $authenticatedUser['cod002'], 'CREATE', 'Usuário', $code, 'Usuário criado: ' . $data['des002'] . '.', $this->clientIp($request));

        return $this->redirect(
            $response,
            '/usuarios/' . $code
        );
    }

    /**
     * Exibe um usuário.
     */
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $authenticatedUser = $this->authenticatedUser($response);

        if ($authenticatedUser instanceof ResponseInterface) {
            return $authenticatedUser;
        }

        if (!Permission::allows(
            $authenticatedUser,
            Permission::USER_VIEW
        )) {
            return $this->redirect($response, '/dashboard');
        }

        $code = (int) ($args['id'] ?? 0);
        $user = $this->users->findByCode($code);

        if (
            $user === null ||
            !$this->canViewUser($authenticatedUser, $user)
        ) {
            return $this->redirect($response, '/usuarios');
        }

        return $this->view->render(
            $response,
            'user/show.twig',
            [
                'app_name' => $_ENV['APP_NAME'],
                'usuario' => $this->userView($authenticatedUser),
                'usuario_cadastro' => $user,
            ]
        );
    }

    /**
     * Exibe o formulário de edição.
     */
    public function edit(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $authenticatedUser = $this->authenticatedUser($response);

        if ($authenticatedUser instanceof ResponseInterface) {
            return $authenticatedUser;
        }

        if (!Permission::allows(
            $authenticatedUser,
            Permission::USER_UPDATE
        )) {
            return $this->redirect($response, '/dashboard');
        }

        $code = (int) ($args['id'] ?? 0);
        $user = $this->users->findByCode($code);

        if (
            $user === null ||
            !$this->canManageUser($authenticatedUser, $user)
        ) {
            return $this->redirect($response, '/usuarios');
        }

        return $this->view->render(
            $response,
            'user/edit.twig',
            [
                'app_name' => $_ENV['APP_NAME'],
                'usuario' => $this->userView($authenticatedUser),
                'usuario_cadastro' => $user,
                'empresas' => $this->availableCompanies($authenticatedUser),
                'perfis' => $this->availableProfiles($authenticatedUser),
                'dados' => $user,
                'erro' => null,
            ]
        );
    }

    /**
     * Atualiza um usuário.
     */
    public function update(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $authenticatedUser = $this->authenticatedUser($response);

        if ($authenticatedUser instanceof ResponseInterface) {
            return $authenticatedUser;
        }

        if (!Permission::allows(
            $authenticatedUser,
            Permission::USER_UPDATE
        )) {
            return $this->redirect($response, '/dashboard');
        }

        $code = (int) ($args['id'] ?? 0);
        $user = $this->users->findByCode($code);

        if (
            $user === null ||
            !$this->canManageUser($authenticatedUser, $user)
        ) {
            return $this->redirect($response, '/usuarios');
        }

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->renderEditError(
                $response,
                $authenticatedUser,
                $user,
                'Dados inválidos para atualização.'
            );
        }

        $data = [
            'cod001' => (int) ($body['cod001'] ?? 0),
            'des002' => trim((string) ($body['des002'] ?? '')),
            'ema002' => strtolower(
                trim((string) ($body['ema002'] ?? ''))
            ),
            'rol002' => strtoupper(
                trim((string) ($body['rol002'] ?? 'A'))
            ),
            'sts002' => isset($body['sts002']),
        ];

        $password = (string) ($body['sen002'] ?? '');
        $passwordConfirmation =
            (string) ($body['sen002_confirmacao'] ?? '');

        /*
         * Supervisor somente altera Atendentes
         * da própria empresa.
         */
        if ((string) $authenticatedUser['rol002'] === 'S') {
            $data['cod001'] = (int) $authenticatedUser['cod001'];
            $data['rol002'] = 'A';
        }

        $data['cod014'] = $this->users->findProfileCodeForRole(
            $data['rol002']
        );

        if ($data['cod014'] === null) {
            return $this->renderEditError(
                $response,
                $authenticatedUser,
                $user,
                'Perfil de acesso não encontrado.',
                $data
            );
        }

        $error = $this->validateData(
            $data,
            $password,
            $passwordConfirmation,
            $code,
            $authenticatedUser
        );

        if ($error !== null) {
            return $this->renderEditError(
                $response,
                $authenticatedUser,
                $user,
                $error,
                $data
            );
        }

        $passwordHash = $password !== ''
            ? password_hash($password, PASSWORD_DEFAULT)
            : null;

        $this->users->update(
            $code,
            $data,
            $passwordHash
        );
        $this->audit->record((int) $data['cod001'], (int) $authenticatedUser['cod002'], 'UPDATE', 'Usuário', $code, 'Usuário atualizado: ' . $data['des002'] . '.', $this->clientIp($request));

        return $this->redirect(
            $response,
            '/usuarios/' . $code
        );
    }

    /**
     * Valida os dados do usuário.
     */
    private function validateData(
        array $data,
        string $password,
        string $passwordConfirmation,
        ?int $userCode,
        array $authenticatedUser
    ): ?string {
        if ($data['cod001'] <= 0) {
            return 'A empresa é obrigatória.';
        }

        $company = $this->companies->findByCode(
            $data['cod001']
        );

        if ($company === null) {
            return 'Empresa não encontrada.';
        }

        if (
            (string) $authenticatedUser['rol002'] !== 'D' &&
            (int) $authenticatedUser['cod001'] !== $data['cod001']
        ) {
            return 'Você não possui acesso a esta empresa.';
        }

        if ($data['des002'] === '') {
            return 'O nome do usuário é obrigatório.';
        }

        if ($data['ema002'] === '') {
            return 'O e-mail do usuário é obrigatório.';
        }

        if (!filter_var($data['ema002'], FILTER_VALIDATE_EMAIL)) {
            return 'Informe um e-mail válido.';
        }

        $allowedProfiles = array_keys(
            $this->availableProfiles($authenticatedUser)
        );

        if (!in_array($data['rol002'], $allowedProfiles, true)) {
            return 'Perfil de usuário inválido.';
        }

        $emailExists = $userCode === null
            ? $this->users->emailExists(
                $data['cod001'],
                $data['ema002']
            )
            : $this->users->emailExistsForAnotherUser(
                $data['cod001'],
                $data['ema002'],
                $userCode
            );

        if ($emailExists) {
            return 'Este e-mail já está cadastrado nesta empresa.';
        }

        if ($password !== '' && strlen($password) < 8) {
            return 'A senha deve possuir pelo menos 8 caracteres.';
        }

        if ($password !== $passwordConfirmation) {
            return 'A confirmação da senha não confere.';
        }

        return null;
    }

    /**
     * Retorna o usuário autenticado.
     *
     * @return array<string, mixed>|ResponseInterface
     */
    private function authenticatedUser(
        ResponseInterface $response
    ): array|ResponseInterface {
        $sessionUser = Session::user();

        if ($sessionUser === null) {
            return $this->redirect($response, '/login');
        }

        $user = $this->users->findByCode(
            (int) $sessionUser['cod002']
        );

        if ($user === null) {
            Session::logout();

            return $this->redirect($response, '/login');
        }

        return $user;
    }

    /**
     * Verifica se o usuário autenticado pode visualizar o cadastro.
     */
    private function canViewUser(
        array $authenticatedUser,
        array $targetUser
    ): bool {
        if ((string) $authenticatedUser['rol002'] === 'D') {
            return true;
        }

        return (
            (string) $authenticatedUser['rol002'] === 'S' &&
            (int) ($targetUser['cri002'] ?? 0) ===
                (int) $authenticatedUser['cod002']
        );
    }

    /**
     * Verifica se o usuário autenticado pode alterar o cadastro.
     */
    private function canManageUser(
        array $authenticatedUser,
        array $targetUser
    ): bool {
        if ((string) $authenticatedUser['rol002'] === 'D') {
            return true;
        }

        return (
            (string) $authenticatedUser['rol002'] === 'S' &&
            (string) $targetUser['rol002'] === 'A' &&
            (int) ($targetUser['cri002'] ?? 0) ===
                (int) $authenticatedUser['cod002']
        );
    }

    /**
     * Retorna as empresas disponíveis.
     *
     * @return array<int, array<string, mixed>>
     */
    private function availableCompanies(array $user): array
    {
        if ((string) $user['rol002'] === 'D') {
            return $this->companies->findAll();
        }

        $company = $this->companies->findByCode(
            (int) $user['cod001']
        );

        return $company !== null
            ? [$company]
            : [];
    }

    /**
     * Retorna os perfis disponíveis.
     *
     * @return array<string, string>
     */
    private function availableProfiles(array $user): array
    {
        if ((string) $user['rol002'] === 'D') {
            return UserRole::PERFIL;
        }

        return [
            'A' => UserRole::PERFIL['A'] ?? 'Atendente',
        ];
    }

    /**
     * Dados do usuário autenticado enviados ao Twig.
     *
     * @return array<string, mixed>
     */
    private function userView(array $user): array
    {
        return [
            'codigo' => (int) $user['cod002'],
            'empresa' => (int) $user['cod001'],
            'nome' => (string) $user['des002'],
            'email' => (string) $user['ema002'],
            'perfil' => (string) $user['rol002'],
        ];
    }

    /**
     * Renderiza o formulário de criação com erro.
     */
    private function renderCreateError(
        ResponseInterface $response,
        array $authenticatedUser,
        string $error,
        array $data
    ): ResponseInterface {
        return $this->view->render(
            $response,
            'user/create.twig',
            [
                'app_name' => $_ENV['APP_NAME'],
                'usuario' => $this->userView($authenticatedUser),
                'empresas' => $this->availableCompanies($authenticatedUser),
                'perfis' => $this->availableProfiles($authenticatedUser),
                'dados' => $data,
                'erro' => $error,
            ]
        );
    }

    /**
     * Renderiza o formulário de edição com erro.
     */
    private function renderEditError(
        ResponseInterface $response,
        array $authenticatedUser,
        array $user,
        string $error,
        ?array $data = null
    ): ResponseInterface {
        return $this->view->render(
            $response,
            'user/edit.twig',
            [
                'app_name' => $_ENV['APP_NAME'],
                'usuario' => $this->userView($authenticatedUser),
                'usuario_cadastro' => $user,
                'empresas' => $this->availableCompanies($authenticatedUser),
                'perfis' => $this->availableProfiles($authenticatedUser),
                'dados' => $data ?? $user,
                'erro' => $error,
            ]
        );
    }

    /**
     * Redireciona para outra rota.
     */
    private function redirect(
        ResponseInterface $response,
        string $location
    ): ResponseInterface {
        return $response
            ->withHeader('Location', $location)
            ->withStatus(302);
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        return is_string($ip) ? $ip : null;
    }
}
