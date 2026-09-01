<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CompanyRepository;
use App\Repositories\AuditRepository;
use App\Repositories\SupervisorCompanyRepository;
use App\Repositories\UserRepository;
use App\Support\Permission;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use App\Support\UserRole;

final class CompanyController
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly CompanyRepository $company,
        private readonly SupervisorCompanyRepository $supervisorCompanies,
        private readonly AuditRepository $audit
    ) {}

    /**
     * Lista as empresas.
     *
     * Administrador (D):
     *   - visualiza todas as empresas.
     *
     * Atendente (A) e Supervisor (S):
     *   - visualizam somente a própria empresa.
     */
    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {

        $user = $this->authenticatedUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::COMPANY_ACCESS)) {
            return $response
                ->withHeader('Location', '/dashboard')
                ->withStatus(302);
        }

        $perfil = (string) $user['rol002'];

        if ($perfil === 'D') {
            $empresas = $this->company->findAll();
        } elseif ($perfil === 'S') {
            $empresas = $this->supervisorCompanies->findCompanies(
                (int) $user['cod002']
            );
        } else {
            $empresa = $this->company->findByCode(
                (int) $user['cod001']
            );

            $empresas = $empresa !== null
                ? [$empresa]
                : [];
        }

        $perfilNome =UserRole::PERFIL[$perfil] ?? 'Desconhecido';
        
        return $this->view->render(
            $response,
            'company/index.twig',
            [
                'app_name' => $_ENV['APP_NAME'],

                'usuario' => $this->usuarioView($user),

                'empresas' => $empresas,
                'perfil_nome' => $perfilNome,
                'can_create' => Permission::allows(
                    $user,
                    Permission::COMPANY_CREATE
                ),
                'can_view' => Permission::allows(
                    $user,
                    Permission::COMPANY_VIEW
                ),
                'can_update' => Permission::allows(
                    $user,
                    Permission::COMPANY_UPDATE
                ),
            ]
        );
    }

    /**
     * Exibe o formulário de cadastro de empresa.
     *
     * Somente Administrador pode criar novas empresas.
     */
    public function create(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {

        $user = $this->authenticatedUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::COMPANY_CREATE)) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        return $this->view->render(
            $response,
            'company/create.twig',
            [
                'app_name' => $_ENV['APP_NAME'],

                'usuario' => $this->usuarioView($user),

                'dados' => [],
                'erro' => null,
            ]
        );
    }

    /**
     * Grava uma nova empresa.
     *
     * Somente Administrador pode criar empresas.
     */
    public function store(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {

        $user = $this->authenticatedUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::COMPANY_CREATE)) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->renderCreateError(
                $response,
                $user,
                'Dados inválidos para cadastro.',
                []
            );
        }

        $data = [
            'des001' => trim((string) ($body['des001'] ?? '')),
            'doc001' => trim((string) ($body['doc001'] ?? '')),
            'ema001' => trim((string) ($body['ema001'] ?? '')),
            'tel001' => trim((string) ($body['tel001'] ?? '')),
            'log001' => trim((string) ($body['log001'] ?? '')),
            'sts001' => false,
        ];

        if ($data['des001'] === '') {
            return $this->renderCreateError(
                $response,
                $user,
                'O nome da empresa é obrigatório.',
                $data
            );
        }

        if ($data['ema001'] === '') {
            return $this->renderCreateError(
                $response,
                $user,
                'O e-mail da empresa é obrigatório.',
                $data
            );
        }

        if (!filter_var($data['ema001'], FILTER_VALIDATE_EMAIL)) {
            return $this->renderCreateError(
                $response,
                $user,
                'Informe um e-mail válido.',
                $data
            );
        }

        if ($this->company->emailExists($data['ema001'])) {
            return $this->renderCreateError(
                $response,
                $user,
                'Este e-mail já está cadastrado para outra empresa.',
                $data
            );
        }

        $companyCode = $this->company->create($data);
        $this->audit->record($companyCode, (int) $user['cod002'], 'CREATE', 'Empresa', $companyCode, 'Empresa criada: ' . $data['des001'] . '.', $this->clientIp($request));

        if ((string) $user['rol002'] === 'S') {
            $this->supervisorCompanies->assign(
                (int) $user['cod002'],
                $companyCode
            );
        }

        return $response
            ->withHeader('Location', '/empresas')
            ->withStatus(302);
    }

    /**
     * Exibe uma empresa.
     */
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,        
        array $args
    ): ResponseInterface {

        $user = $this->authenticatedUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::COMPANY_VIEW)) {
            return $response
                ->withHeader('Location', '/dashboard')
                ->withStatus(302);
        }

        $codigo = (int) ($args['id'] ?? 0);

        if ($codigo <= 0) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        $empresa = $this->company->findByCode($codigo);

        if ($empresa === null) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        if (!$this->canAccessCompany($user, $empresa)) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        return $this->view->render(
            $response,
            'company/show.twig',
            [
                'app_name' => $_ENV['APP_NAME'],

                'usuario' => $this->usuarioView($user),

                'empresa' => $empresa,
                'can_update' => Permission::allows(
                    $user,
                    Permission::COMPANY_UPDATE
                ),
            ]
        );
    }

    /**
     * Exibe o formulário de edição da empresa.
     */
    public function edit(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {

        $user = $this->authenticatedUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::COMPANY_UPDATE)) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        $codigo = (int) ($args['id'] ?? 0);

        if ($codigo <= 0) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        $empresa = $this->company->findByCode($codigo);

        if ($empresa === null) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        if (!$this->canAccessCompany($user, $empresa)) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        return $this->view->render(
            $response,
            'company/edit.twig',
            [
                'app_name' => $_ENV['APP_NAME'],

                'usuario' => $this->usuarioView($user),

                'empresa' => $empresa,

                'is_admin' => (string) $user['rol002'] === 'D',
            ]
        );
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
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $user = $this->users->findByCode(
            (int) $sessionUser['cod002']
        );

        if ($user === null) {
            Session::logout();

            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        return $user;
    }

    /**
     * Verifica se o usuário pode acessar a empresa.
     */
    private function canAccessCompany(
        array $user,
        array $empresa
    ): bool {

        $perfil = (string) $user['rol002'];

        /*
         * Administrador possui acesso global.
         */
        if ($perfil === 'D') {
            return true;
        }

        if ($perfil === 'S') {
            return $this->supervisorCompanies->hasActiveCompany(
                (int) $user['cod002'],
                (int) $empresa['cod001']
            );
        }

        /* Atendente fica restrito à própria empresa. */
        return (int) $empresa['cod001'] === (int) $user['cod001'];
    }

    /**
     * Dados do usuário enviados para o Twig.
     */
    private function usuarioView(array $user): array
    {
        return [
            'codigo' => (int) $user['cod002'],
            'nome' => (string) $user['des002'],
            'email' => (string) $user['ema002'],
            'perfil' => (string) $user['rol002'],
        ];
    }

    /**
     * Atualiza uma empresa.
     *
     * Administrador:
     *   - pode alterar qualquer empresa.
     *
     * Supervisor:
     *   - pode alterar somente a própria empresa.
     *
     * Atendente:
     *   - não pode alterar empresas.
     */
    public function update(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {

        $user = $this->authenticatedUser($response);

        if ($user instanceof ResponseInterface) {
            return $user;
        }

        if (!Permission::allows($user, Permission::COMPANY_UPDATE)) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        $codigo = (int) ($args['id'] ?? 0);

        if ($codigo <= 0) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        $empresa = $this->company->findByCode($codigo);

        if ($empresa === null) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        /*
     * Verifica se o usuário pode acessar a empresa.
     */
        if (!$this->canAccessCompany($user, $empresa)) {
            return $response
                ->withHeader('Location', '/empresas')
                ->withStatus(302);
        }

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->renderEditError(
                $response,
                $user,
                $empresa,
                'Dados inválidos para atualização.'
            );
        }

        $data = [
            'des001' => trim((string) ($body['des001'] ?? '')),
            'doc001' => trim((string) ($body['doc001'] ?? '')),
            'ema001' => trim((string) ($body['ema001'] ?? '')),
            'tel001' => trim((string) ($body['tel001'] ?? '')),
            'log001' => trim((string) ($body['log001'] ?? '')),
            'sts001' => isset($body['sts001']),
        ];

        /* Somente o Administrador pode alterar o status da empresa. */
        if ((string) $user['rol002'] !== 'D') {
            $data['sts001'] = (bool) $empresa['sts001'];
        }

        /*
     * Nome obrigatório.
     */
        if ($data['des001'] === '') {
            return $this->renderEditError(
                $response,
                $user,
                $empresa,
                'O nome da empresa é obrigatório.',
                $data
            );
        }

        /*
     * E-mail obrigatório.
     */
        if ($data['ema001'] === '') {
            return $this->renderEditError(
                $response,
                $user,
                $empresa,
                'O e-mail da empresa é obrigatório.',
                $data
            );
        }

        /*
     * Validação do e-mail.
     */
        if (!filter_var($data['ema001'], FILTER_VALIDATE_EMAIL)) {
            return $this->renderEditError(
                $response,
                $user,
                $empresa,
                'Informe um e-mail válido.',
                $data
            );
        }

        /*
     * Verifica se o e-mail pertence a outra empresa.
     */
        if (
            $this->company->emailExistsForAnotherCompany(
                $data['ema001'],
                $codigo
            )
        ) {
            return $this->renderEditError(
                $response,
                $user,
                $empresa,
                'Este e-mail já está cadastrado para outra empresa.',
                $data
            );
        }



        /*
     * Atualiza a empresa.
     */
        $this->company->update(
            $codigo,
            $data
        );
        $this->audit->record($codigo, (int) $user['cod002'], 'UPDATE', 'Empresa', $codigo, 'Empresa atualizada: ' . $data['des001'] . '.', $this->clientIp($request));

        /*
     * Retorna para a visualização da empresa.
     */
        return $response
            ->withHeader(
                'Location',
                '/empresas/' . $codigo
            )
            ->withStatus(302);
    }


    /**
     * Renderiza o formulário de edição com erro.
     */
    private function renderEditError(
        ResponseInterface $response,
        array $user,
        array $empresa,
        string $erro,
        ?array $dados = null
    ): ResponseInterface {

        return $this->view->render(
            $response,
            'company/edit.twig',
            [
                'app_name' => $_ENV['APP_NAME'],

                'usuario' => $this->usuarioView($user),

                'empresa' => $empresa,

                'is_admin' => (string) $user['rol002'] === 'D',

                'erro' => $erro,

                'dados' => $dados ?? $empresa,
            ]
        );
    }


    /**
     * Renderiza o formulário de criação com erro.
     */
    private function renderCreateError(
        ResponseInterface $response,
        array $user,
        string $erro,
        array $dados
    ): ResponseInterface {

        return $this->view->render(
            $response,
            'company/create.twig',
            [
                'app_name' => $_ENV['APP_NAME'],

                'usuario' => $this->usuarioView($user),

                'erro' => $erro,

                'dados' => $dados,
            ]
        );
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        return is_string($ip) ? $ip : null;
    }
}
