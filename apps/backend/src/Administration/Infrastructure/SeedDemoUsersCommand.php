<?php

namespace App\Administration\Infrastructure;

use App\AccessControl\Application\AccessRepository;
use App\Audit\Application\AuditRepository;
use App\Identity\Application\Passwords;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Transactions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-demo-users', description: 'Create local-only demo accounts with a generated password')]
final class SeedDemoUsersCommand extends Command
{
    public function __construct(private string $environment, private UserRepository $users, private Passwords $passwords, private AccessRepository $access, private AuditRepository $audit, private Transactions $transactions)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->environment !== 'dev') {
            $io->error('Demo accounts are allowed only in the dev environment.');

            return Command::FAILURE;
        }

        $password = bin2hex(random_bytes(12));
        $created = $this->transactions->run(function () use ($password): array {
            $rows = [];
            foreach ($this->access->matrix() as $role) {
                $code = (string) $role['code'];
                $email = strtolower(str_replace('_', '-', $code)).'@demo.shipwise.test';
                if ($this->users->byEmail($email)) {
                    continue;
                }
                $name = 'Demo '.ucwords(strtolower(str_replace('_', ' ', $code)));
                $user = new User($email, $name, '', $code === 'CUSTOMER' ? 'customer' : 'staff');
                $user->changePassword($this->passwords->hash($user, $password));
                $user->activate();
                $this->users->save($user);
                $this->access->assign($user->id(), [$code]);
                $this->audit->record(null, 'account.demo_created', $user->id(), ['roles' => $code]);
                $rows[] = [$code, $email];
            }

            return $rows;
        });
        if (!$created) {
            $io->note('Demo accounts already exist. Existing passwords were not changed.');

            return Command::SUCCESS;
        }

        $io->table(['Role', 'Email'], $created);
        $io->writeln('Generated password for these new local demo accounts: '.$password);
        $io->warning('Local testing only. Never create these accounts in production or commit their credentials.');

        return Command::SUCCESS;
    }
}
