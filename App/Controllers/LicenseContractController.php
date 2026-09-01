<?php

declare(strict_types=1);

namespace App\Controllers;

    use App\Repositories\LicenseContractAccessRepository;
    use App\Repositories\UserRepository;
use App\Services\CurrentCompanyContext;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Entrega o contrato de licença preenchido para a empresa atualmente selecionada. */
final class LicenseContractController
{
    public function __construct(
            private readonly UserRepository $users,
            private readonly CurrentCompanyContext $companies,
            private readonly LicenseContractAccessRepository $accesses
    ) {}

    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $sessionUser = Session::user();

        if ($sessionUser === null) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = $this->users->findByCode((int) $sessionUser['cod002']);

            if ($user === null) {
                Session::logout();

                return $response->withHeader('Location', '/login')->withStatus(302);
            }

            if ((string) $user['rol002'] !== 'S') {
                return $response->withHeader('Location', '/dashboard')->withStatus(302);
            }

        $company = $this->companies->currentCompany($user);

            if ($company === null) {
                return $response->withHeader('Location', '/dashboard')->withStatus(302);
            }

            $this->accesses->registerView(
                (int) $company['cod001'],
                (int) $user['cod002']
            );

        $templatePath = dirname(__DIR__, 2)
            . '/Public/assets/contrato/contrato_licenca_nexora.html';

        $template = file_get_contents($templatePath);

        if ($template === false) {
            throw new \RuntimeException('Modelo do contrato de licença não encontrado.');
        }

        $html = strtr($template, [
                '{{VERSAO_CONTRATO}}' => $this->escape($this->environment('CONTRACT_VERSION', '1.0')),
                '{{VALOR_IMPLANTACAO}}' => $this->escape(
                    $this->environment('CONTRACT_IMPLEMENTATION_VALUE', 'R$ 2.000,00 (dois mil reais)')
                ),
            '{{RAZAO_SOCIAL_LICENCIANTE}}' => $this->escape(
                $this->environment('CONTRACT_LICENSOR_NAME', (string) ($_ENV['APP_NAME'] ?? 'Nexora'))
            ),
            '{{CPF_CNPJ_LICENCIANTE}}' => $this->escape(
                $this->environment('CONTRACT_LICENSOR_DOCUMENT', 'Não informado')
            ),
            '{{ENDERECO_LICENCIANTE}}' => $this->escape(
                $this->environment('CONTRACT_LICENSOR_ADDRESS', 'Não informado')
            ),
            '{{RAZAO_SOCIAL_CLIENTE}}' => $this->escape((string) ($company['des001'] ?? 'Não informado')),
            '{{CPF_CNPJ_CLIENTE}}' => $this->escape($this->valueOrDefault($company['doc001'] ?? null)),
            // n001 ainda não possui um campo de endereço. Não use log001, pois ele armazena a logo.
            '{{ENDERECO_CLIENTE}}' => $this->escape('Endereço não informado'),
            '{{CIDADE_FORO}}' => $this->escape($this->environment('CONTRACT_FORUM_CITY', 'Não informado')),
            '{{UF_FORO}}' => $this->escape($this->environment('CONTRACT_FORUM_STATE', 'Não informado')),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    private function environment(string $name, string $default): string
    {
        $value = trim((string) ($_ENV[$name] ?? ''));

        return $value !== '' ? $value : $default;
    }

    private function valueOrDefault(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : 'Não informado';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
