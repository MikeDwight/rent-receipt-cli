<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\OwnerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class OwnerUpsertCommand extends Command
{
    protected static $defaultName = 'owner:upsert';
    protected static $defaultDescription = 'Create or update an owner';

    public function __construct(private readonly OwnerRepository $owners)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Owner id (if set, update)')
            ->addOption('full-name', null, InputOption::VALUE_REQUIRED, 'Full name')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email (optional)', '')
            ->addOption('address', null, InputOption::VALUE_REQUIRED, 'Address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idOpt = $input->getOption('id');
        $fullName = trim((string) $input->getOption('full-name'));
        $email = trim((string) $input->getOption('email'));
        $address = trim((string) $input->getOption('address'));

        if ($fullName === '') {
            $output->writeln('<error>--full-name is required.</error>');
            return Command::INVALID;
        }

        if ($address === '') {
            $output->writeln('<error>--address is required.</error>');
            return Command::INVALID;
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $output->writeln('<error>Invalid --email format.</error>');
            return Command::INVALID;
        }

        // CREATE
        if ($idOpt === null) {
            $id = $this->owners->create($fullName, $email, $address);
            $output->writeln("<info>Created owner #{$id}</info>");
            return Command::SUCCESS;
        }

        // UPDATE
        if (!ctype_digit((string) $idOpt)) {
            $output->writeln('<error>Invalid --id. Expected an integer.</error>');
            return Command::INVALID;
        }

        $id = (int) $idOpt;
        if ($this->owners->findById($id) === null) {
            $output->writeln("<error>Owner not found: #{$id}</error>");
            return Command::FAILURE;
        }

        $this->owners->update($id, $fullName, $email, $address);
        $output->writeln("<info>Updated owner #{$id}</info>");

        return Command::SUCCESS;
    }
}
