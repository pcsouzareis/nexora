<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class EmailChannelRepository
{
    public function __construct(private readonly Database $database) {}

    public function findByCompany(int $companyCode, int $channelCode): ?array
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            SELECT cod003, cod001, tip003, imh003, imp003, ime003, imu003, imw003,
                   smh003, smp003, sme003, smu003, smw003, outema003
            FROM n003
            WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 = 'E-Mail'
            LIMIT 1
        SQL);
        $statement->execute(['companyCode' => $companyCode, 'channelCode' => $channelCode]);
        $email = $statement->fetch(PDO::FETCH_ASSOC);
        return $email === false ? null : $email;
    }

    public function save(int $companyCode, int $channelCode, array $data): void
    {
        $statement = $this->database->pdo()->prepare(<<<'SQL'
            UPDATE n003
            SET imh003 = :imapHost, imp003 = :imapPort, ime003 = :imapSecurity,
                imu003 = :imapUser, imw003 = COALESCE(:imapPassword, imw003),
                smh003 = :smtpHost, smp003 = :smtpPort, sme003 = :smtpSecurity,
                smu003 = :smtpUser, smw003 = COALESCE(:smtpPassword, smw003),
                outema003 = :outbound, atu003 = CURRENT_TIMESTAMP
            WHERE cod001 = :companyCode AND cod003 = :channelCode AND tip003 = 'E-Mail'
        SQL);
        $statement->execute([
            'companyCode' => $companyCode, 'channelCode' => $channelCode,
            'imapHost' => $data['imap_host'], 'imapPort' => $data['imap_port'],
            'imapSecurity' => $data['imap_security'], 'imapUser' => $data['imap_user'],
            'imapPassword' => $data['imap_password_encrypted'],
            'smtpHost' => $data['smtp_host'], 'smtpPort' => $data['smtp_port'],
            'smtpSecurity' => $data['smtp_security'], 'smtpUser' => $data['smtp_user'],
            'smtpPassword' => $data['smtp_password_encrypted'],
            'outbound' => $data['email_enabled'] ? 'true' : 'false',
        ]);
    }
}
