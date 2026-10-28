<?php

namespace App\Identity\Infrastructure;

use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function byEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => strtolower(trim($email))]);
    }

    public function byId(string $id): ?User
    {
        return $this->em->find(User::class, $id);
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
