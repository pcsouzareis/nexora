<?php

declare(strict_types=1);

namespace App\Support;

final class Permission
{
    /*
     * ---------------------------------------------------------
     * Dashboard
     * ---------------------------------------------------------
     */

    public const DASHBOARD_ACCESS = 'D0';

    /*
     * ---------------------------------------------------------
     * Inteligência artificial
     * ---------------------------------------------------------
     */

    public const AI_ACCESS = 'I0';
    public const AI_UPDATE = 'I1';
    public const AI_WEBHOOK_TEST = 'I2';

    public const KNOWLEDGE_ACCESS = 'K0';
    public const KNOWLEDGE_UPDATE = 'K1';

    /*
     * ---------------------------------------------------------
     * Empresas
     * ---------------------------------------------------------
     */

    public const COMPANY_ACCESS = 'E0';
    public const COMPANY_CREATE = 'E1';
    public const COMPANY_VIEW = 'E2';
    public const COMPANY_UPDATE = 'E3';


    /*
     * ---------------------------------------------------------
     * Usuários
     * ---------------------------------------------------------
     */

    public const USER_ACCESS = 'U0';
    public const USER_CREATE = 'U1';
    public const USER_VIEW = 'U2';
    public const USER_UPDATE = 'U3';

    /* Conversas */
    public const CONVERSATION_ACCESS = 'C0';
    public const CONVERSATION_UPDATE = 'C1';

    /* Canais */
    public const CHANNEL_ACCESS = 'N0';
    public const CHANNEL_CREATE = 'N1';
    public const CHANNEL_VIEW = 'N2';
    public const CHANNEL_UPDATE = 'N3';

    /* Auditoria */
    public const AUDIT_ACCESS = 'A0';


    /**
     * Verifica se o usuário possui determinada permissão.
     *
     * Regras:
     *
     * NULL ou vazio:
     *   - acesso administrativo total.
     *
     * Códigos:
     *   - acesso somente às permissões informadas.
     */
    public static function allows(
        array $user,
        string $permission
    ): bool {

        /*
         * Se a consulta não carregou ace014,
         * o acesso deve ser negado.
         */
        if (!array_key_exists('ace014', $user)) {
            return false;
        }

        $access = $user['ace014'];

        /*
         * NULL representa acesso total.
         */
        if ($access === null) {
            return true;
        }

        $access = trim((string) $access);

        /*
         * Texto vazio também representa acesso total.
         */
        if ($access === '') {
            return true;
        }

        /*
         * Separa e normaliza os códigos cadastrados.
         */
        $permissions = array_values(
            array_filter(
                array_map(
                    'trim',
                    explode('|', $access)
                ),
                static fn (string $item): bool =>
                    $item !== ''
            )
        );

        /*
         * Verifica a permissão solicitada.
         */
        return in_array(
            $permission,
            $permissions,
            true
        );
    }
}
