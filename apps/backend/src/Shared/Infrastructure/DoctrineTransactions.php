<?php

namespace App\Shared\Infrastructure;

use App\Shared\Application\Transactions;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTransactions implements Transactions
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function run(callable $operation): mixed
    {
        return $this->em->wrapInTransaction(function () use ($operation) {
            // Serialize account/security mutations, including concurrent last-admin changes.
            $this->em->getConnection()->executeStatement('SELECT pg_advisory_xact_lock(73401901)');

            return $operation();
        });
    }
}
