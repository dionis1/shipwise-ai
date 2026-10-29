<?php

namespace App\Quoting\Infrastructure;

use App\Quoting\Application\QuoteStore;
use Doctrine\DBAL\Connection;

final class SqlQuoteStore implements QuoteStore
{
    public function __construct(private Connection $db)
    {
    }

    public function diagnostics(): array
    {
        return $this->db->fetchAllAssociative("SELECT outcome->>'status' AS status,COUNT(*)::int AS count FROM shipping_quotes q CROSS JOIN LATERAL jsonb_array_elements(q.outcomes) outcome WHERE q.created_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours' GROUP BY outcome->>'status' ORDER BY status");
    }

    public function exclusive(string $scope, callable $operation): mixed
    {
        return $this->db->transactional(function () use ($scope, $operation): mixed {
            $this->db->executeQuery('SELECT pg_advisory_xact_lock(hashtext(?))', [$scope]);

            return $operation();
        });
    }

    public function find(string $id): ?array
    {
        return $this->hydrate($this->db->fetchAssociative('SELECT * FROM shipping_quotes WHERE id=?', [$id]));
    }

    public function duplicate(string $scope, string $key): ?array
    {
        return $this->hydrate($this->db->fetchAssociative('SELECT * FROM shipping_quotes WHERE scope_hash=? AND idempotency_key=?', [$scope, $key]));
    }

    /**
     * @param array<string,mixed>|false $row
     *
     * @return array<string,mixed>|null */
    private function hydrate(array|false $row): ?array
    {
        if (!$row) {
            return null;
        }
        $row['request'] = json_decode($row['request'], true, 32, JSON_THROW_ON_ERROR);
        $row['outcomes'] = json_decode($row['outcomes'], true, 32, JSON_THROW_ON_ERROR);
        $row['offers'] = array_map(static fn (array $offer): array => json_decode($offer['snapshot'], true, 32, JSON_THROW_ON_ERROR), $this->db->fetchAllAssociative('SELECT snapshot FROM shipping_quote_offers WHERE quote_id=? ORDER BY id', [$row['id']]));

        return $row;
    }

    public function save(array $quote): void
    {
        $offers = $quote['offers'];
        unset($quote['offers']);
        $quote['request'] = json_encode($quote['request'], JSON_THROW_ON_ERROR);
        $quote['outcomes'] = json_encode($quote['outcomes'], JSON_THROW_ON_ERROR);
        $this->db->insert('shipping_quotes', $quote);
        foreach ($offers as $offer) {
            $this->db->insert('shipping_quote_offers', ['id' => $offer['id'], 'quote_id' => $quote['id'], 'snapshot' => json_encode($offer, JSON_THROW_ON_ERROR), 'selling_minor' => $offer['price']['totalMinor'], 'currency' => $offer['price']['currency']]);
        }
    }
}
