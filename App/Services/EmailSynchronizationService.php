<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OutboundMessageRepository;
use App\Support\Encryption;
use RuntimeException;

final class EmailSynchronizationService
{
    public function __construct(
        private readonly Encryption $encryption,
        private readonly WebhookMessageProcessor $processor,
        private readonly OutboundMessageRepository $outbound
    ) {}

    public function synchronize(array $channel): int
    {
        if (!function_exists('imap_open')) throw new RuntimeException('A extensão IMAP do PHP não está habilitada.');

        $imap = $this->openImap($channel);
        $processed = 0;
        try {
            $messages = imap_search($imap, 'UNSEEN') ?: [];
            foreach (array_slice($messages, 0, 100) as $number) {
                $header = imap_headerinfo($imap, $number);
                $from = $header->from[0] ?? null;
                $email = is_object($from) ? strtolower((string) ($from->mailbox ?? '') . '@' . (string) ($from->host ?? '')) : '';
                $message = $this->body($imap, $number);
                if ($email === '' || $message === '') continue;

                $messageId = trim((string) ($header->message_id ?? ''));
                if ($messageId === '') $messageId = 'imap-' . imap_uid($imap, $number);
                $name = is_object($from) ? $this->decode((string) ($from->personal ?? '')) : null;
                $subject = $this->decode((string) ($header->subject ?? ''));

                try {
                    $result = $this->processor->process((int) $channel['cod001'], (int) $channel['cod003'], [
                        'external_id' => $email,
                        'name' => $name !== '' ? $name : null,
                        'conversation_id' => $email,
                        'message_id' => $messageId,
                        'base_id' => (int) $channel['cod005'],
                        'message' => $message,
                    ]);
                } catch (\InvalidArgumentException) {
                    continue;
                }

                $reply = $result['body']['reply'] ?? null;
                if (!$result['body']['duplicate'] && $channel['outema003'] && is_array($reply) && isset($reply['message'], $reply['message_id'])) {
                    $this->send($channel, $email, $subject, (string) $reply['message'], (int) $reply['message_id']);
                }
                imap_setflag_full($imap, (string) $number, '\\Seen');
                $processed++;
            }
        } finally {
            imap_close($imap);
        }
        return $processed;
    }

    private function openImap(array $channel): \IMAP\Connection
    {
        $flags = match ((string) ($channel['ime003'] ?? 'ssl')) { 'tls' => '/imap/tls', 'none' => '/imap/notls', default => '/imap/ssl' };
        $mailbox = sprintf('{%s:%d%s}INBOX', $channel['imh003'], (int) $channel['imp003'], $flags);
        $connection = @imap_open($mailbox, (string) $channel['imu003'], $this->encryption->decrypt((string) $channel['imw003']), OP_READWRITE, 1);
        if ($connection === false) throw new RuntimeException('Não foi possível conectar ao IMAP: ' . (imap_last_error() ?: 'erro desconhecido.'));
        return $connection;
    }

    private function body(\IMAP\Connection $imap, int $number): string
    {
        $body = imap_body($imap, $number, FT_PEEK);
        $body = quoted_printable_decode((string) $body);
        $body = preg_replace('/<br\\s*\\/?\s*>/i', "\n", $body) ?? $body;
        $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\n{3,}/', "\n\n", $body) ?? $body);
    }

    private function decode(string $value): string
    {
        $decoded = '';
        foreach (imap_mime_header_decode($value) as $part) $decoded .= $part->text;
        return trim($decoded);
    }

    private function send(array $channel, string $to, string $subject, string $message, int $messageCode): void
    {
        try {
            $socket = $this->smtpConnection($channel);
            $from = (string) $channel['smu003'];
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            $content = 'From: ' . $from . "\r\n" . 'To: ' . $to . "\r\n" . 'Subject: ' . ($subject === '' ? 'Resposta Nexora' : 'Re: ' . $subject) . "\r\n" . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . str_replace("\n.", "\n..", $message) . "\r\n.";
            $this->command($socket, $content, [250]);
            $this->command($socket, 'QUIT', [221]);
            fclose($socket);
            $this->outbound->record((int) $channel['cod001'], (int) $channel['cod003'], $messageCode, 'Enviada');
        } catch (\Throwable) {
            $this->outbound->record((int) $channel['cod001'], (int) $channel['cod003'], $messageCode, 'Falha', null, 'Não foi possível enviar a resposta pelo SMTP.');
        }
    }

    private function smtpConnection(array $channel)
    {
        $security = (string) ($channel['sme003'] ?? 'ssl');
        $target = ($security === 'ssl' ? 'ssl://' : 'tcp://') . $channel['smh003'] . ':' . (int) $channel['smp003'];
        $socket = @stream_socket_client($target, $errorCode, $errorMessage, 15, STREAM_CLIENT_CONNECT);
        if ($socket === false) throw new RuntimeException('Não foi possível conectar ao SMTP.');
        stream_set_timeout($socket, 15);
        $this->expect($socket, [220]);
        $this->command($socket, 'EHLO nexora.local', [250]);
        if ($security === 'tls') {
            $this->command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Não foi possível iniciar TLS no SMTP.');
            $this->command($socket, 'EHLO nexora.local', [250]);
        }
        $this->command($socket, 'AUTH LOGIN', [334]);
        $this->command($socket, base64_encode((string) $channel['smu003']), [334]);
        $this->command($socket, base64_encode($this->encryption->decrypt((string) $channel['smw003'])), [235]);
        return $socket;
    }

    private function command($socket, string $command, array $accepted): void { fwrite($socket, $command . "\r\n"); $this->expect($socket, $accepted); }
    private function expect($socket, array $accepted): void { do { $line = fgets($socket, 4096); if ($line === false) throw new RuntimeException('O servidor SMTP não respondeu.'); } while (isset($line[3]) && $line[3] === '-'); if (!in_array((int) substr($line, 0, 3), $accepted, true)) throw new RuntimeException('SMTP recusou o envio: ' . trim($line)); }
}
