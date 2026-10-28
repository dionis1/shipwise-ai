<?php

namespace App\Administration\Infrastructure;

use App\AccessControl\Application\AccessRepository;
use App\Audit\Application\AuditRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Infrastructure\Principal;
use App\Shared\Application\Transactions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:bootstrap-admin', description: 'Securely create the first Super Administrator')]
final class BootstrapAdminCommand extends Command
{
    public function __construct(private UserRepository $users, private AccessRepository $access, private AuditRepository $audit, private Transactions $transactions, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED)->addArgument('name', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('Use an interactive terminal to enter the password securely.');

            return Command::FAILURE;
        }
        $password = $io->askHidden('Password (12–128 bytes)');
        if (!is_string($password)) {
            return Command::FAILURE;
        }User::validatePassword($password);
        $this->transactions->run(function () use ($input, $password) {
            if ($this->access->activeAdminCount() > 0) {
                throw new \RuntimeException('Bootstrap is disabled once an active Super Administrator exists.');
            }
            $email = (string) $input->getArgument('email');
            if ($this->users->byEmail($email)) {
                throw new \RuntimeException('Email already exists.');
            }
            $user = new User($email, (string) $input->getArgument('name'), '', 'staff');
            $user->changePassword($this->hasher->hashPassword(new Principal($user), $password));
            $user->activate();
            $this->users->save($user);
            $this->access->assign($user->id(), ['SUPER_ADMIN']);
            $this->audit->record(null, 'staff.bootstrapped', $user->id());
        });
        $io->success('Super Administrator created. Staff MFA is required before production.');

        return Command::SUCCESS;
    }
}
