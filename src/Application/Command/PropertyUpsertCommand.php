<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\PropertyRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PropertyUpsertCommand extends Command
{
    protected static $defaultName = 'property:upsert';
    protected static $defaultDescription = 'Create or update a property';

    public function __construct(private readonly PropertyRepository $properties)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Property id (if set, update)')
            ->addOption('owner-id', null, InputOption::VALUE_REQUIRED, 'Owner id')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Label')
            ->addOption('address', null, InputOption::VALUE_REQUIRED, 'Address')
            ->addOption('rent', null, InputOption::VALUE_REQUIRED, 'Rent amount (integer)')
            ->addOption('charges', null, InputOption::VALUE_REQUIRED, 'Charges amount (integer)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idOpt = $input->getOption('id');
        $ownerIdRaw = (string) $input->getOption('owner-id');
        $label = trim((string) $input->getOption('label'));
        $address = trim((string) $input->getOption('address'));
        $rentRaw = (string) $input->getOption('rent');
        $chargesRaw = (string) $input->getOption('charges');

        // Validations
        if (!ctype_digit($ownerIdRaw)) {
            $output->writeln('<error>--owner-id must be an integer.</error>');
            return Command::INVALID;
        }

        if ($label === '') {
            $output->writeln('<error>--label is required.</error>');
            return Command::INVALID;
        }

        if ($address === '') {
            $output->writeln('<error>--address is required.</error>');
            return Command::INVALID;
        }

        if (!ctype_digit($rentRaw) || !ctype_digit($chargesRaw)) {
            $output->writeln('<error>--rent and --charges must be integers.</error>');
            return Command::INVALID;
        }

        $ownerId = (int) $ownerIdRaw;
        $rent = (int) $rentRaw;
        $charges = (int) $chargesRaw;

        if (!$this->properties->ownerExists($ownerId)) {
            $output->writeln("<error>Owner not found: #{$ownerId}</error>");
            return Command::FAILURE;
        }

        // CREATE
        if ($idOpt === null) {
            $id = $this->properties->create($ownerId, $label, $address, $rent, $charges);
            $output->writeln("<info>Created property #{$id}</info>");
            return Command::SUCCESS;
        }

        // UPDATE
        if (!ctype_digit((string) $idOpt)) {
            $output->writeln('<error>--id must be an integer.</error>');
            return Command::INVALID;
        }

        $id = (int) $idOpt;
        if ($this->properties->findById($id) === null) {
            $output->writeln("<error>Property not found: #{$id}</error>");
            return Command::FAILURE;
        }

        $this->properties->update($id, $ownerId, $label, $address, $rent, $charges);
        $output->writeln("<info>Updated property #{$id}</info>");

        return Command::SUCCESS;
    }
}
