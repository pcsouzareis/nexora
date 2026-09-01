<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Encryption;
use RuntimeException;

final class EmailConnectionTester
{
    public function __construct(private readonly Encryption $encryption) {}

    public function test(array $email): string
    {
        foreach (['imh003', 'imp003', 'imu003', 'imw003', 'smh003', 'smp003', 'smu003', 'smw003'] as $field) {
            if (empty($email[$field])) throw new RuntimeException('Salve os dados IMAP e SMTP antes de testar a conexão.');
        }
        if (!function_exists('imap_open')) throw new RuntimeException('A extensão IMAP do PHP não está habilitada. Habilite php_imap para testar e receber e-mails.');

        $imapPassword = $this->encryption->decrypt((string) $email['imw003']);
        $smtpPassword = $this->encryption->decrypt((string) $email['smw003']);
        $imapFlags = match ((string) ($email['ime003'] ?? 'ssl')) {
            'tls' => '/imap/tls', 'none' => '/imap/notls', default => '/imap/ssl',
        };
        $mailbox = sprintf('{%s:%d%s}INBOX', $email['imh003'], (int) $email['imp003'], $imapFlags);
        $imap = @imap_open($mailbox, (string) $email['imu003'], $imapPassword, OP_READONLY, 1);
        if ($imap === false) throw new RuntimeException('Não foi possível conectar ao IMAP: ' . (imap_last_error() ?: 'credenciais ou servidor inválidos.'));
        imap_close($imap);

        $this->testSmtp($email, $smtpPassword);
        return 'Conexões IMAP e SMTP validadas com sucesso.';
    }

    private function testSmtp(array $email, string $password): void
    {
        $host = (string) $email['smh003'];
        $port = (int) $email['smp003'];
        $security = (string) ($email['sme003'] ?? 'ssl');
        $target = ($security === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($target, $errorCode, $errorMessage, 12, STREAM_CLIENT_CONNECT);
        if ($socket === false) throw new RuntimeException('Não foi possível conectar ao SMTP: ' . $errorMessage);
        stream_set_timeout($socket, 12);
        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO nexora.local', [250]);
            if ($security === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Não foi possível iniciar TLS no SMTP.');
                $this->command($socket, 'EHLO nexora.local', [250]);
            }
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode((string) $email['smu003']), [334]);
            $this->command($socket, base64_encode($password), [235]);
            $this->command($socket, 'QUIT', [221]);
        } finally { fclose($socket); }
    }

    private function command($socket, string $command, array $accepted): void { fwrite($socket, $command . "\r\n"); $this->expect($socket, $accepted); }
    private function expect($socket, array $accepted): void { $line = ''; do { $part = fgets($socket, 1024); if ($part === false) throw new RuntimeException('O servidor SMTP não respondeu.'); $line = $part; } while (isset($part[3]) && $part[3] === '-'); $code = (int) substr($line, 0, 3); if (!in_array($code, $accepted, true)) throw new RuntimeException('SMTP recusou a conexão: ' . trim($line)); }
}
