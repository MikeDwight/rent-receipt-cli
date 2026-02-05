<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\TenantRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TenantListCommand extends Command
{
    protected static $defaultName = 'tenant:list';
    protected static $defaultDescription = 'List tenants';

    public function __construct(private readonly TenantRepository $tenants)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->tenants->listAll();

        if (count($rows) === 0) {
            $output->writeln('<comment>No tenants found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Tenants</info>');
        foreach ($rows as $r) {
            $email = trim((string) $r['email']);

            $line = sprintf(
                '  #%d  %s',
                (int) $r['id'],
                (string) $r['full_name']
            );

            if ($email !== '') {
                $line .= sprintf('  <%s>', $email);
            }

            $output->writeln($line);
        }

        return Command::SUCCESS;
    }
}
