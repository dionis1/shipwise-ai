<?php

namespace App\Administration\Infrastructure;

use Doctrine\DBAL\Connection;

final class AdminQueries
{
    public function __construct(private Connection $db)
    {
    }

    /** @param array<string,string> $filters
     * @return array<string,mixed> */
    public function staff(array $filters, int $page): array
    {
        $where = ["u.kind='staff'"];
        $params = [];
        if (!empty($filters['search'])) {
            $where[] = '(LOWER(u.name) LIKE ? OR LOWER(u.email) LIKE ?)';
            $params[] = '%'.strtolower($filters['search']).'%';
            $params[] = '%'.strtolower($filters['search']).'%';
        }
        if (!empty($filters['status'])) {
            $where[] = 'u.status=?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['role'])) {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id=u.id AND ur.role_code=?)';
            $params[] = $filters['role'];
        }
        $clause = implode(' AND ', $where);
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM users u WHERE $clause", $params);
        $items = $this->db->fetchAllAssociative("SELECT u.id,u.email,u.name,u.status,u.created_at FROM users u WHERE $clause ORDER BY u.created_at DESC,u.id LIMIT 20 OFFSET ".(($page - 1) * 20), $params);
        foreach ($items as &$item) {
            $item['created_at'] = (new \DateTimeImmutable($item['created_at']))->setTimezone(new \DateTimeZone('UTC'))->format('c');
            $item['roles'] = $this->db->fetchFirstColumn('SELECT role_code FROM user_roles WHERE user_id=? ORDER BY role_code', [$item['id']]);
        }

        return ['items' => $items, 'page' => $page, 'pageSize' => 20, 'total' => $total];
    }

    /**
     * @return array<string,mixed> */
    public function overview(): array
    {
        return ['customerCount' => (int) $this->db->fetchOne("SELECT COUNT(*) FROM users WHERE kind='customer'"), 'activeStaffCount' => (int) $this->db->fetchOne("SELECT COUNT(*) FROM users WHERE kind='staff' AND status='active'"), 'accountStatuses' => array_map(static fn (array $row): array => ['status' => $row['status'], 'count' => (int) $row['count']], $this->db->fetchAllAssociative('SELECT status,COUNT(*) AS count FROM users GROUP BY status ORDER BY status'))];
    }

    /**
     * @return array<string,mixed> */
    public function customers(string $search, int $page): array
    {
        $params = ['%'.strtolower($search).'%'];
        $where = "kind='customer' AND LOWER(email) LIKE ?";

        return ['items' => $this->db->fetchAllAssociative("SELECT id,name,email,status FROM users WHERE $where ORDER BY id LIMIT 20 OFFSET ".(($page - 1) * 20), $params), 'total' => (int) $this->db->fetchOne("SELECT COUNT(*) FROM users WHERE $where", $params), 'page' => $page, 'pageSize' => 20];
    }
}
