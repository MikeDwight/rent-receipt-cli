<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\TenantRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TenantShowCommand extends Command
{
    protected static $defaultName = 'tenant:show';
    protected static $defaultDescription = 'Show a single tenant by id';

    public function __construct(private readonly TenantRepository $tenants)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Tenant id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idRaw = (string) $input->getArgument('id');
        if (!ctype_digit($idRaw)) {
            $output->writeln('<error>Invalid id. Expected an integer.</error>');
            return Command::INVALID;
        }

        $id = (int) $idRaw;
        $tenant = $this->tenants->findById($id);

        if ($tenant === null) {
            $output->writeln("<error>Tenant not found: #{$id}</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Tenant #%d</info>', $tenant['id']));
        $output->writeln('  Name:    ' . $tenant['full_name']);
        $output->writeln('  Email:   ' . $tenant['email']);
        $output->writeln('  Address: ' . $tenant['address']);
        $output->writeln('  Created: ' . $tenant['created_at']);

        return Command::SUCCESS;
    }
}

