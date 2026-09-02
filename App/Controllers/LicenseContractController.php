<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\LicenseContractAccessRepository;
use App\Repositories\AuditRepository;
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
        private readonly LicenseContractAccessRepository $accesses,
        private readonly AuditRepository $audit
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

        $contractVersion = $this->environment('CONTRACT_VERSION', '1.0');

        $html = strtr($template, [
            '{{VERSAO_CONTRATO}}' => $this->escape($contractVersion),
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

        $access = $this->accesses->find(
            (int) $company['cod001'],
            (int) $user['cod002']
        );

        $html = str_replace(
            '</main>',
            $this->acceptanceSection($access, $user, $contractVersion) . "\n</main>",
            $html
        );

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function accept(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $sessionUser = Session::user();

        if ($sessionUser === null) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = $this->users->findByCode((int) $sessionUser['cod002']);

        if ($user === null || (string) $user['rol002'] !== 'S') {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        $body = $request->getParsedBody();
        $token = is_array($body) ? ($body['csrf_token'] ?? null) : null;

        if (!Session::validCsrfToken($token) || ($body['accepted'] ?? null) !== '1') {
            return $response->withHeader('Location', '/contrato/licenca')->withStatus(302);
        }

        $company = $this->companies->currentCompany($user);

        if ($company === null) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        $this->accesses->accept(
            (int) $company['cod001'],
            (int) $user['cod002'],
            $this->environment('CONTRACT_VERSION', '1.0'),
            $this->clientIp($request)
        );

        $this->audit->record(
            (int) $company['cod001'],
            (int) $user['cod002'],
            'ACCEPT',
            'Contrato de licença',
            null,
            'Aceite formal do contrato, versão ' . $this->environment('CONTRACT_VERSION', '1.0') . '.',
            $this->clientIp($request)
        );

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    private function acceptanceSection(?array $access, array $user, string $version): string
    {
        if (($access['ace021'] ?? null) !== null) {
            $acceptedAt = $this->escape($this->formatDate((string) $access['ace021']));
            $acceptedVersion = $this->escape((string) ($access['ver021'] ?? $version));
            $ipAddress = $this->escape((string) ($access['ip021'] ?? 'Não informado'));
            $name = $this->escape((string) $user['des002']);

            return <<<HTML
                <section class="acceptance">
                    <strong>Aceite do contrato registrado.</strong>
                    <p>Supervisor responsável: {$name}. Data e hora: {$acceptedAt}. Versão: {$acceptedVersion}. IP: {$ipAddress}.</p>
                </section>
            HTML;
        }

        $token = $this->escape(Session::csrfToken());

        return <<<HTML
            <section class="acceptance">
                <h2>Aceite do contrato</h2>
                <p>Leia o contrato e confirme que está autorizado a aceitar suas condições em nome da empresa.</p>
                <form method="post" action="/contrato/licenca/aceite">
                    <input type="hidden" name="csrf_token" value="{$token}">
                    <p><label><input type="checkbox" name="accepted" value="1" required> Li e aceito os termos deste contrato.</label></p>
                    <button type="submit" class="back-button">Li e aceito o contrato</button>
                </form>
            </section>
        HTML;
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    private function formatDate(string $date): string
    {
        try {
            return (new \DateTimeImmutable($date))->format('d/m/Y H:i');
        } catch (\Exception) {
            return $date;
        }
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
