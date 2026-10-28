<?php

namespace App\Audit\Infrastructure;

use App\Audit\Application\AuditRepository;
use App\Shared\Domain\Identifier;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

final class SqlAuditRepository implements AuditRepository
{
    public function __construct(private Connection $db, private RequestStack $requests)
    {
    }

    public function record(?string $actor, string $action, ?string $target, array $context = []): void
    {
        $safe = array_intersect_key($context, array_flip(['status', 'roles', 'purpose']));
        $this->db->insert('audit_events', ['id' => Identifier::new(), 'actor_id' => $actor, 'action' => $action, 'target_id' => $target, 'context' => json_encode($safe, JSON_THROW_ON_ERROR), 'correlation_id' => $this->requests->getCurrentRequest()?->attributes->get('correlationId') ?? Identifier::new(), 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function list(array $filters, int $page): array
    {
        $where = ['1=1'];
        $params = [];
        foreach (['action', 'actor_id', 'target_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "$key = ?";
                $params[] = $filters[$key];
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (!empty($filters[$key])) {
                $where[] = "created_at $operator ?";
                $params[] = $filters[$key];
            }
        }
        $clause = implode(' AND ', $where);
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM audit_events WHERE $clause", $params);
        $items = $this->db->fetchAllAssociative("SELECT * FROM audit_events WHERE $clause ORDER BY created_at DESC, id LIMIT 20 OFFSET ".(($page - 1) * 20), $params);
        foreach ($items as &$item) {
            $item['created_at'] = (new \DateTimeImmutable($item['created_at']))->setTimezone(new \DateTimeZone('UTC'))->format('c');
            $item['context'] = json_decode($item['context'], true);
        }

        return ['items' => $items, 'page' => $page, 'pageSize' => 20, 'total' => $total];
    }
}
