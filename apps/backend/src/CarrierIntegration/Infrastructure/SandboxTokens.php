<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Domain\IntegrationFailure;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;

final class SandboxTokens
{
    public function __construct(private CacheItemPoolInterface $carrierTokens, private LockFactory $lockFactory)
    {
    }

    /** @param callable():array{token:string,ttl:int} $authenticate */
    public function get(string $key, callable $authenticate): string
    {
        $key = 'carrier_'.hash('sha256', $key);
        $lock = $this->lockFactory->createLock($key, 15);
        if (!$lock->acquire(true)) {
            throw new IntegrationFailure('temporarily_unavailable');
        }
        try {
            $item = $this->carrierTokens->getItem($key);
            if ($item->isHit() && is_string($item->get())) {
                return $item->get();
            }
            $value = $authenticate();
            if ('' === $value['token'] || strlen($value['token']) > 8192 || $value['ttl'] < 1) {
                throw new IntegrationFailure('response_invalid');
            }
            $item->set($value['token'])->expiresAfter(min(1800, $value['ttl']));
            $this->carrierTokens->save($item);

            return $value['token'];
        } finally {
            $lock->release();
        }
    }

    public function forget(string $key): void
    {
        $this->carrierTokens->deleteItem('carrier_'.hash('sha256', $key));
    }
}
