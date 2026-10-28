<?php

namespace App\Identity\Infrastructure;

use App\Identity\Application\TokenRepository;
use Doctrine\DBAL\Connection;

final class SqlTokenRepository implements TokenRepository
{
    private const TABLES = ['verify' => 'email_verification_tokens', 'reset' => 'password_reset_tokens', 'invite' => 'staff_invitations'];

    public function __construct(private Connection $db)
    {
    }

    public function issue(string $userId, string $purpose, int $lifetime): string
    {
        $table = self::TABLES[$purpose];
        $raw = bin2hex(random_bytes(32));
        $this->db->executeStatement("DELETE FROM $table WHERE user_id = ?", [$userId]);
        $this->db->insert($table, ['token_hash' => hash('sha256', $raw), 'user_id' => $userId, 'expires_at' => gmdate('Y-m-d H:i:s', time() + $lifetime)]);

        return $raw;
    }

    public function consume(string $token, string $purpose): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $table = self::TABLES[$purpose];
        $id = $this->db->fetchOne("DELETE FROM $table WHERE token_hash = ? AND expires_at > CURRENT_TIMESTAMP RETURNING user_id", [hash('sha256', $token)]);

        return $id === false ? null : (string) $id;
    }
}
