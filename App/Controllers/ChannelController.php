<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ChannelRepository;
use App\Repositories\AuditRepository;
use App\Repositories\MetaChannelRepository;
use App\Repositories\EmailChannelRepository;
use App\Repositories\TelegramChannelRepository;
use App\Repositories\UserRepository;
use App\Services\EmailConnectionTester;
use App\Services\TelegramService;
use App\Services\CurrentCompanyContext;
use App\Support\Permission;
use App\Support\Encryption;
use App\Support\Session;
use League\CommonMark\CommonMarkConverter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class ChannelController
{
    private const TYPES = ['WhatsApp', 'Web', 'Instagram', 'Facebook', 'E-Mail', 'Telegram', 'Outro'];

    public function __construct(private readonly Twig $view, private readonly UserRepository $users, private readonly ChannelRepository $channels, private readonly CurrentCompanyContext $companies, private readonly AuditRepository $audit, private readonly Encryption $encryption, private readonly MetaChannelRepository $meta, private readonly EmailChannelRepository $email, private readonly EmailConnectionTester $emailTester, private readonly TelegramChannelRepository $telegram, private readonly TelegramService $telegramService) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_ACCESS);
        if ($user instanceof ResponseInterface) return $user;
        return $this->view->render($response, 'channels/index.twig', $this->context($user, ['canais' => $this->channels->findAllByCompany($this->companies->companyCode($user)), 'can_create' => Permission::allows($user, Permission::CHANNEL_CREATE)]));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_CREATE);
        if ($user instanceof ResponseInterface) return $user;
        return $this->form($response, $user, [], '/canais', 'Novo canal');
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_CREATE);
        if ($user instanceof ResponseInterface) return $user;
        $data = $this->data((array) $request->getParsedBody());
        if ($data === null || !$this->channels->baseBelongsToCompany($this->companies->companyCode($user), $data['base_code'])) return $this->form($response, $user, (array) $request->getParsedBody(), '/canais', 'Novo canal', 'Dados inválidos.');
        if ($data['type'] === 'WhatsApp' && $data['zapi_enabled'] && ($data['zapi_instance'] === '' || $data['zapi_token'] === '' || $data['zapi_client_token'] === '')) return $this->form($response, $user, (array) $request->getParsedBody(), '/canais', 'Novo canal', 'Informe ID da instância, Token e Client-Token da Z-API para habilitar o envio.');
        if ($data['type'] === 'Telegram' && $data['telegram_token_encrypted'] === null) return $this->form($response, $user, (array) $request->getParsedBody(), '/canais', 'Novo canal', 'Informe o token do bot Telegram.');
        $data['zapi_token_encrypted'] = $data['type'] === 'WhatsApp' && $data['zapi_token'] !== '' ? $this->encryption->encrypt($data['zapi_token']) : null;
        $data['zapi_client_token_encrypted'] = $data['type'] === 'WhatsApp' && $data['zapi_client_token'] !== '' ? $this->encryption->encrypt($data['zapi_client_token']) : null;
        $secret = 'nexora_' . bin2hex(random_bytes(24));
        $code = $this->channels->create($this->companies->companyCode($user), $data, password_hash($secret, PASSWORD_DEFAULT), in_array($data['type'], ['Web', 'WhatsApp', 'Facebook', 'Instagram'], true) ? bin2hex(random_bytes(20)) : null);
        if (in_array($data['type'], ['Facebook', 'Instagram'], true)) $this->meta->save($this->companies->companyCode($user), $code, $data['meta_page'], $data['meta_token'] === '' ? null : $this->encryption->encrypt($data['meta_token']), $data['meta_secret'] === '' ? null : $this->encryption->encrypt($data['meta_secret']), $data['meta_enabled']);
        if ($data['type'] === 'E-Mail') $this->email->save($this->companies->companyCode($user), $code, $data);
        if ($data['type'] === 'Telegram') $this->telegram->save($this->companies->companyCode($user), $code, $data['telegram_token_encrypted'], $data['telegram_enabled']);
        $this->audit->record($this->companies->companyCode($user), (int) $user['cod002'], 'CREATE', 'Canal', $code, 'Canal criado: ' . $data['description'] . '.', $this->clientIp($request));
        return $this->showChannel($response, $user, $code, $secret);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_VIEW);
        if ($user instanceof ResponseInterface) return $user;
        return $this->showChannel($response, $user, (int) ($args['id'] ?? 0));
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_UPDATE);
        if ($user instanceof ResponseInterface) return $user;
        $channel = $this->channels->findByCompany($this->companies->companyCode($user), (int) ($args['id'] ?? 0));
        if ($channel === null) return $this->redirect($response, '/canais');
        return $this->form($response, $user, $channel, '/canais/' . $channel['cod003'], 'Editar canal');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_UPDATE);
        if ($user instanceof ResponseInterface) return $user;
        $code = (int) ($args['id'] ?? 0); $data = $this->data((array) $request->getParsedBody());
        if ($data === null || !$this->channels->baseBelongsToCompany($this->companies->companyCode($user), $data['base_code'])) return $this->redirect($response, '/canais/' . $code . '/editar');
        $this->channels->update($this->companies->companyCode($user), $code, $data);
        if ($data['type'] === 'WhatsApp') {
            $this->channels->ensureZapiWebhookToken($this->companies->companyCode($user), $code, bin2hex(random_bytes(20)));
            $this->channels->updateZapiConfiguration(
                $this->companies->companyCode($user), $code, $data['zapi_instance'],
                $data['zapi_token'] === '' ? null : $this->encryption->encrypt($data['zapi_token']),
                $data['zapi_client_token'] === '' ? null : $this->encryption->encrypt($data['zapi_client_token']),
                $data['zapi_enabled']
            );
        }
        if (in_array($data['type'], ['Facebook', 'Instagram'], true)) {
            $this->channels->ensureFacebookWebhookToken($this->companies->companyCode($user), $code, bin2hex(random_bytes(20)));
            $this->meta->save($this->companies->companyCode($user), $code, $data['meta_page'], $data['meta_token'] === '' ? null : $this->encryption->encrypt($data['meta_token']), $data['meta_secret'] === '' ? null : $this->encryption->encrypt($data['meta_secret']), $data['meta_enabled']);
        }
        if ($data['type'] === 'E-Mail') $this->email->save($this->companies->companyCode($user), $code, $data);
        if ($data['type'] === 'Telegram') $this->telegram->save($this->companies->companyCode($user), $code, $data['telegram_token_encrypted'], $data['telegram_enabled']);
        $this->audit->record($this->companies->companyCode($user), (int) $user['cod002'], 'UPDATE', 'Canal', $code, 'Canal atualizado: ' . $data['description'] . '.', $this->clientIp($request));
        return $this->redirect($response, '/canais/' . $code);
    }

    public function testEmail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_UPDATE);
        if ($user instanceof ResponseInterface) return $user;
        $code = (int) ($args['id'] ?? 0);
        $channel = $this->channels->findByCompany($this->companies->companyCode($user), $code);
        $email = $this->email->findByCompany($this->companies->companyCode($user), $code);
        if ($channel === null || $email === null) return $this->redirect($response, '/canais');
        try {
            $success = $this->emailTester->test($email);
            $this->audit->record($this->companies->companyCode($user), (int) $user['cod002'], 'TEST', 'Canal E-Mail', $code, 'Teste de conexão IMAP/SMTP concluído.', $this->clientIp($request));
            return $this->form($response, $user, $channel, '/canais/' . $code, 'Editar canal', null, $success);
        } catch (\RuntimeException $exception) {
            return $this->form($response, $user, $channel, '/canais/' . $code, 'Editar canal', $exception->getMessage());
        }
    }

    public function testTelegram(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->telegramAction($request, $response, (int) ($args['id'] ?? 0), false);
    }

    public function synchronizeTelegram(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->telegramAction($request, $response, (int) ($args['id'] ?? 0), true);
    }

    public function regenerateWebhookKey(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_UPDATE);
        if ($user instanceof ResponseInterface) return $user;
        $secret = 'nexora_' . bin2hex(random_bytes(24)); $code = (int) ($args['id'] ?? 0);
        $this->channels->replaceWebhookHash($this->companies->companyCode($user), $code, password_hash($secret, PASSWORD_DEFAULT));
        $this->audit->record($this->companies->companyCode($user), (int) $user['cod002'], 'UPDATE', 'Canal', $code, 'Chave do webhook regenerada.', $this->clientIp($request));
        return $this->showChannel($response, $user, $code, $secret);
    }

    public function regenerateWebchatToken(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_UPDATE);
        if ($user instanceof ResponseInterface) return $user;
        $code = (int) ($args['id'] ?? 0);
        $this->channels->replacePublicToken($this->companies->companyCode($user), $code, bin2hex(random_bytes(20)));
        $this->audit->record($this->companies->companyCode($user), (int) $user['cod002'], 'UPDATE', 'Canal', $code, 'Token público do webchat regenerado.', $this->clientIp($request));
        return $this->redirect($response, '/canais/' . $code);
    }

    private function data(array $body): ?array
    {
        $type = trim((string) ($body['tip003'] ?? '')); $description = trim((string) ($body['des003'] ?? ''));
        $base = (int) ($body['cod005'] ?? 0); $baseCode = $base > 0 ? $base : null;
        if ($description === '' || strlen($description) > 100 || !in_array($type, self::TYPES, true) || (in_array($type, ['Web', 'WhatsApp', 'Facebook', 'Instagram', 'Telegram'], true) && $baseCode === null)) return null;
        $instance = trim((string) ($body['ins003'] ?? ''));
        if ($type === 'WhatsApp' && strlen($instance) > 120) return null;
        $imapHost = trim((string) ($body['imh003'] ?? '')); $smtpHost = trim((string) ($body['smh003'] ?? ''));
        $imapPort = (int) ($body['imp003'] ?? 993); $smtpPort = (int) ($body['smp003'] ?? 465);
        $imapSecurity = (string) ($body['ime003'] ?? 'ssl'); $smtpSecurity = (string) ($body['sme003'] ?? 'ssl');
        if ($type === 'E-Mail' && ($imapHost === '' || $smtpHost === '' || $imapPort < 1 || $imapPort > 65535 || $smtpPort < 1 || $smtpPort > 65535 || !in_array($imapSecurity, ['ssl', 'tls', 'none'], true) || !in_array($smtpSecurity, ['ssl', 'tls', 'none'], true))) return null;
        $telegramToken = trim((string) ($body['bot003'] ?? ''));
        if ($type === 'Telegram' && $telegramToken !== '' && strlen($telegramToken) > 255) return null;
        return ['description' => $description, 'type' => $type, 'base_code' => $baseCode, 'active' => isset($body['sts003']), 'zapi_instance' => $instance, 'zapi_token' => trim((string) ($body['tok003'] ?? '')), 'zapi_client_token' => trim((string) ($body['cli003'] ?? '')), 'zapi_enabled' => isset($body['out003']), 'meta_page' => trim((string) ($body['pag003'] ?? '')), 'meta_token' => trim((string) ($body['met003'] ?? '')), 'meta_secret' => trim((string) ($body['sec003'] ?? '')), 'meta_enabled' => isset($body['outmet003']), 'imap_host' => $imapHost, 'imap_port' => $imapPort, 'imap_security' => $imapSecurity, 'imap_user' => trim((string) ($body['imu003'] ?? '')), 'imap_password_encrypted' => trim((string) ($body['imw003'] ?? '')) === '' ? null : $this->encryption->encrypt(trim((string) $body['imw003'])), 'smtp_host' => $smtpHost, 'smtp_port' => $smtpPort, 'smtp_security' => $smtpSecurity, 'smtp_user' => trim((string) ($body['smu003'] ?? '')), 'smtp_password_encrypted' => trim((string) ($body['smw003'] ?? '')) === '' ? null : $this->encryption->encrypt(trim((string) $body['smw003'])), 'email_enabled' => isset($body['outema003']), 'telegram_token_encrypted' => $telegramToken === '' ? null : $this->encryption->encrypt($telegramToken), 'telegram_enabled' => isset($body['outtel003'])];
    }

    private function form(ResponseInterface $response, array $user, array $channel, string $action, string $title, ?string $error = null, ?string $success = null): ResponseInterface
    {
        return $this->view->render($response, 'channels/form.twig', $this->context($user, ['canal' => $channel, 'action' => $action, 'title' => $title, 'types' => self::TYPES, 'bases' => $this->channels->findActiveBasesByCompany($this->companies->companyCode($user)), 'erro' => $error, 'sucesso' => $success, 'configuracao_canais' => $this->channelConfigurationDocument()]));
    }

    private function channelConfigurationDocument(): string
    {
        $path = dirname(__DIR__, 2) . '/Storage/documents/configuracao-canais.md';
        $document = file_get_contents($path);

        if ($document === false) {
            return '<p>Documento de configuração não encontrado.</p>';
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($document)->getContent();
    }

    private function showChannel(ResponseInterface $response, array $user, int $code, ?string $secret = null): ResponseInterface
    {
        $channel = $this->channels->findByCompany($this->companies->companyCode($user), $code);
        if ($channel === null) return $this->redirect($response, '/canais');
        return $this->view->render($response, 'channels/show.twig', $this->context($user, ['canal' => $channel, 'webhook_secret' => $secret, 'can_update' => Permission::allows($user, Permission::CHANNEL_UPDATE)]));
    }

    private function telegramAction(ServerRequestInterface $request, ResponseInterface $response, int $code, bool $synchronize): ResponseInterface
    {
        $user = $this->user($response, Permission::CHANNEL_UPDATE);
        if ($user instanceof ResponseInterface) return $user;
        $companyCode = $this->companies->companyCode($user);
        $channel = $this->channels->findByCompany($companyCode, $code);
        $telegram = $this->telegram->findByCompany($companyCode, $code);
        if ($channel === null || $telegram === null) return $this->redirect($response, '/canais');
        try {
            $success = $synchronize ? $this->telegramService->synchronize($telegram) . ' mensagem(ns) sincronizada(s).' : $this->telegramService->test($telegram);
            $this->audit->record($companyCode, (int) $user['cod002'], $synchronize ? 'SYNC' : 'TEST', 'Canal Telegram', $code, $success, $this->clientIp($request));
            return $this->form($response, $user, $channel, '/canais/' . $code, 'Editar canal', null, $success);
        } catch (\RuntimeException $exception) { return $this->form($response, $user, $channel, '/canais/' . $code, 'Editar canal', $exception->getMessage()); }
    }

    private function user(ResponseInterface $response, string $permission): array|ResponseInterface
    {
        $session = Session::user(); if ($session === null) return $this->redirect($response, '/login');
        $user = $this->users->findByCode((int) $session['cod002']);
        if ($user === null || !Permission::allows($user, $permission)) return $this->redirect($response, '/dashboard');
        return $user;
    }

    private function context(array $user, array $data): array { return $data + ['app_name' => $_ENV['APP_NAME'] ?? 'Nexora', 'usuario' => ['codigo' => $user['cod002'], 'nome' => $user['des002'], 'email' => $user['ema002'], 'perfil' => $user['rol002']]]; }
    private function redirect(ResponseInterface $response, string $location): ResponseInterface { return $response->withHeader('Location', $location)->withStatus(302); }
    private function clientIp(ServerRequestInterface $request): ?string { $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null; return is_string($ip) ? $ip : null; }
}
