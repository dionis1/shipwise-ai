<?php

namespace App\Quoting\Infrastructure;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:purge-quotes', description: 'Delete expired retention windows without logging shipment data')]
final class PurgeQuotesCommand extends Command
{
    public function __construct(private Connection $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->db->executeStatement('DELETE FROM shipping_quotes WHERE purge_at <= CURRENT_TIMESTAMP');
        $output->writeln('Purged quotes: '.$count);

        return Command::SUCCESS;
    }
}
