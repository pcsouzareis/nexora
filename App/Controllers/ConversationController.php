<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ConversationRepository;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Services\CurrentCompanyContext;
use App\Services\OutboundMessageService;
use App\Support\Permission;
use App\Support\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class ConversationController
{
    private const STATUSES = ['Aberta', 'Em Atendimento', 'Aguardando', 'Encerrada', 'Cancelada'];

    public function __construct(
        private readonly Twig $view,
        private readonly UserRepository $users,
        private readonly ConversationRepository $conversations,
        private readonly AuditRepository $audit,
        private readonly CurrentCompanyContext $companies,
        private readonly OutboundMessageService $outbound
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response);
        if ($user instanceof ResponseInterface) return $user;

        $query = $request->getQueryParams();
        $status = trim((string) ($query['status'] ?? ''));
        $search = trim((string) ($query['q'] ?? ''));
        $startDateInput = trim((string) ($query['inicio'] ?? ''));
        $startDate = '';
        if (!in_array($status, self::STATUSES, true)) $status = '';
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $startDateInput) === 1) {
            $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $startDateInput);
            if ($date !== false && $date->format('d/m/Y') === $startDateInput) $startDate = $date->format('Y-m-d');
        }

        return $this->view->render($response, 'conversations/index.twig', $this->context($user, [
            'conversas' => $this->conversations->findAllByCompany(
                $this->companies->companyCode($user), $status, mb_substr($search, 0, 120), $startDate
            ),
            'status_atual' => $status,
            'busca' => $search,
            'inicio' => $startDate === '' ? '' : $startDateInput,
            'status_opcoes' => self::STATUSES,
            'fila' => $this->conversations->queueSummary($this->companies->companyCode($user)),
        ]));
    }

    public function summary(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response);
        if ($user instanceof ResponseInterface) return $this->json($user, ['error' => 'Não autorizado.']);
        return $this->json($response, $this->conversations->queueSummary($this->companies->companyCode($user)));
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($response);
        if ($user instanceof ResponseInterface) return $user;
        $conversation = $this->conversation($user, (int) ($args['id'] ?? 0));
        if ($conversation === null) return $this->redirect($response, '/conversas');

        return $this->view->render($response, 'conversations/show.twig', $this->context($user, [
            'conversa' => $conversation,
            'mensagens' => $this->conversations->findMessages((int) $conversation['cod008']),
            'can_manage' => Permission::allows($user, Permission::CONVERSATION_UPDATE),
        ]));
    }

    public function take(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->manager($response);
        if ($user instanceof ResponseInterface) return $user;
        $this->conversations->take($this->companies->companyCode($user), (int) ($args['id'] ?? 0), (int) $user['cod002']);
        $this->audit->record($this->companies->companyCode($user), (int) $user['cod002'], 'UPDATE', 'Conversa', (int) ($args['id'] ?? 0), 'Atendimento assumido.', $this->clientIp($request));
        return $this->redirect($response, '/conversas/' . (int) ($args['id'] ?? 0));
    }

    public function reply(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->manager($response);
        if ($user instanceof ResponseInterface) return $user;
        $message = trim((string) (((array) $request->getParsedBody())['message'] ?? ''));
        if ($message !== '' && strlen($message) <= 10000) {
            $messageCode = $this->conversations->addHumanMessage(
                $this->companies->companyCode($user), (int) ($args['id'] ?? 0), (int) $user['cod002'], $message
            );
            if ($messageCode !== null) {
                $this->outbound->deliverMessage($this->companies->companyCode($user), (int) ($args['id'] ?? 0), $messageCode, $message);
                $this->audit->record($this->companies->companyCode($user), (int) $user['cod002'], 'REPLY', 'Conversa', (int) ($args['id'] ?? 0), 'Mensagem enviada no atendimento manual.', $this->clientIp($request));
            }
        }
        return $this->redirect($response, '/conversas/' . (int) ($args['id'] ?? 0));
    }

    public function close(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->manager($response);
        if ($user instanceof ResponseInterface) return $user;
        $this->conversations->close($this->companies->companyCode($user), (int) ($args['id'] ?? 0));
        $this->audit->record($this->companies->companyCode($user), (int) $user['cod002'], 'CLOSE', 'Conversa', (int) ($args['id'] ?? 0), 'Conversa encerrada.', $this->clientIp($request));
        return $this->redirect($response, '/conversas/' . (int) ($args['id'] ?? 0));
    }

    private function user(ResponseInterface $response): array|ResponseInterface
    {
        $session = Session::user();
        if ($session === null) return $this->redirect($response, '/login');
        $user = $this->users->findByCode((int) $session['cod002']);
        if ($user === null || !Permission::allows($user, Permission::CONVERSATION_ACCESS)) return $this->redirect($response, '/dashboard');
        return $user;
    }

    private function manager(ResponseInterface $response): array|ResponseInterface
    {
        $user = $this->user($response);
        if ($user instanceof ResponseInterface || !Permission::allows($user, Permission::CONVERSATION_UPDATE)) return $user instanceof ResponseInterface ? $user : $this->redirect($response, '/dashboard');
        return $user;
    }

    private function conversation(array $user, int $code): ?array
    {
        return $this->conversations->findByCompany($this->companies->companyCode($user), $code);
    }

    private function context(array $user, array $data): array
    {
        return $data + ['app_name' => $_ENV['APP_NAME'] ?? 'Nexora', 'usuario' => ['codigo' => $user['cod002'], 'nome' => $user['des002'], 'email' => $user['ema002'], 'perfil' => $user['rol002']]];
    }

    private function redirect(ResponseInterface $response, string $location): ResponseInterface
    {
        return $response->withHeader('Location', $location)->withStatus(302);
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        return is_string($ip) ? $ip : null;
    }

    private function json(ResponseInterface $response, array $data): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
