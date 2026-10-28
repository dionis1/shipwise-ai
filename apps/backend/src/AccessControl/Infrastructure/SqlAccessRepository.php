<?php

namespace App\AccessControl\Infrastructure;

use App\AccessControl\Application\AccessRepository;
use App\Shared\Domain\RuleViolation;
use Doctrine\DBAL\Connection;

final class SqlAccessRepository implements AccessRepository
{
    public function __construct(private Connection $db)
    {
    }

    public function roles(string $userId): array
    {
        return $this->db->fetchFirstColumn('SELECT role_code FROM user_roles WHERE user_id = ? ORDER BY role_code', [$userId]);
    }

    public function permissions(string $userId): array
    {
        return $this->db->fetchFirstColumn('SELECT DISTINCT permission_code FROM role_permissions rp JOIN user_roles ur ON ur.role_code = rp.role_code WHERE ur.user_id = ? ORDER BY permission_code', [$userId]);
    }

    public function assign(string $userId, array $roles): void
    {
        foreach ($roles as $role) {
            if (!$this->db->fetchOne('SELECT code FROM roles WHERE code = ?', [$role])) {
                throw new RuleViolation('Unknown role.');
            }
        }
        $this->db->delete('user_roles', ['user_id' => $userId]);
        foreach ($roles as $role) {
            $this->db->insert('user_roles', ['user_id' => $userId, 'role_code' => $role]);
        }
    }

    public function activeAdminCount(): int
    {
        return (int) $this->db->fetchOne("SELECT COUNT(*) FROM users u JOIN user_roles ur ON u.id=ur.user_id WHERE ur.role_code='SUPER_ADMIN' AND u.status='active'");
    }

    public function matrix(): array
    {
        $roles = $this->db->fetchAllAssociative('SELECT * FROM roles ORDER BY code');
        foreach ($roles as &$role) {
            $role['permissions'] = $this->db->fetchFirstColumn('SELECT permission_code FROM role_permissions WHERE role_code = ? ORDER BY permission_code', [$role['code']]);
        }

        return $roles;
    }

    public function allPermissions(): array
    {
        return $this->db->fetchFirstColumn('SELECT code FROM permissions ORDER BY code');
    }

    public function updatePermissions(string $role, array $permissions): void
    {
        if ($role === 'SUPER_ADMIN' || $role === 'CUSTOMER') {
            throw new RuleViolation('System role permissions are immutable.');
        }
        if (!$this->db->fetchOne('SELECT code FROM roles WHERE code = ?', [$role])) {
            throw new RuleViolation('Unknown role.');
        }
        if (array_diff($permissions, $this->allPermissions())) {
            throw new RuleViolation('Unknown permission.');
        }
        $this->db->delete('role_permissions', ['role_code' => $role]);
        foreach (array_unique($permissions) as $permission) {
            $this->db->insert('role_permissions', ['role_code' => $role, 'permission_code' => $permission]);
        }
        $this->db->executeStatement('UPDATE users SET session_version = session_version + 1 WHERE id IN (SELECT user_id FROM user_roles WHERE role_code = ?)', [$role]);
    }
}
