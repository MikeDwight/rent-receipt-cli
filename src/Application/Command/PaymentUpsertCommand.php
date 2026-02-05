<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use DateTimeImmutable;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;
use RentReceiptCli\Application\Port\PropertyRepository;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Application\Port\TenantRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PaymentUpsertCommand extends Command
{
    protected static $defaultName = 'payment:upsert';
    protected static $defaultDescription = 'Create or update a rent payment';

    public function __construct(
        private readonly RentPaymentRepository $payments,
        private readonly TenantRepository $tenants,
        private readonly PropertyRepository $properties
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Payment id (if set, update)')
            ->addOption('tenant-id', null, InputOption::VALUE_REQUIRED, 'Tenant id')
            ->addOption('property-id', null, InputOption::VALUE_REQUIRED, 'Property id')
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'Period (YYYY-MM)')
            ->addOption('rent', null, InputOption::VALUE_REQUIRED, 'Rent amount in cents (integer >= 0)')
            ->addOption('charges', null, InputOption::VALUE_REQUIRED, 'Charges amount in cents (integer >= 0)')
            ->addOption('paid-at', null, InputOption::VALUE_REQUIRED, 'Payment date (YYYY-MM-DD, required)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idOpt = $input->getOption('id');

        $tenantIdRaw = (string) $input->getOption('tenant-id');
        $propertyIdRaw = (string) $input->getOption('property-id');
        $periodRaw = (string) $input->getOption('period');
        $rentRaw = (string) $input->getOption('rent');
        $chargesRaw = (string) $input->getOption('charges');
        $paidAtRaw = (string) $input->getOption('paid-at');

        // Required options
        if ($tenantIdRaw === '' || $propertyIdRaw === '' || $periodRaw === '' || $rentRaw === '' || $chargesRaw === '' || $paidAtRaw === '') {
            $output->writeln('<error>--tenant-id, --property-id, --period, --rent, --charges and --paid-at are all required.</error>');
            return Command::INVALID;
        }

        // tenant-id / property-id
        if (!ctype_digit($tenantIdRaw)) {
            $output->writeln('<error>--tenant-id must be an integer.</error>');
            return Command::INVALID;
        }
        if (!ctype_digit($propertyIdRaw)) {
            $output->writeln('<error>--property-id must be an integer.</error>');
            return Command::INVALID;
        }

        $tenantId = (int) $tenantIdRaw;
        $propertyId = (int) $propertyIdRaw;

        if ($this->tenants->findById($tenantId) === null) {
            $output->writeln("<error>Tenant not found: #{$tenantId}</error>");
            return Command::FAILURE;
        }

        if ($this->properties->findById($propertyId) === null) {
            $output->writeln("<error>Property not found: #{$propertyId}</error>");
            return Command::FAILURE;
        }

        // period
        if (!ConsoleInputValidator::isValidMonth($periodRaw)) {
            $output->writeln('<error>Invalid --period format. Expected YYYY-MM.</error>');
            return Command::INVALID;
        }

        try {
            $period = Month::fromString($periodRaw);
        } catch (\Throwable $e) {
            $output->writeln('<error>Invalid --period value: ' . $e->getMessage() . '</error>');
            return Command::INVALID;
        }

        // rent / charges
        if (!ctype_digit($rentRaw) || !ctype_digit($chargesRaw)) {
            $output->writeln('<error>--rent and --charges must be integers (in cents).</error>');
            return Command::INVALID;
        }

        $rent = (int) $rentRaw;
        $charges = (int) $chargesRaw;

        if ($rent < 0 || $charges < 0) {
            $output->writeln('<error>--rent and --charges must be >= 0.</error>');
            return Command::INVALID;
        }

        // paid-at (YYYY-MM-DD strict)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAtRaw)) {
            $output->writeln('<error>Invalid --paid-at format. Expected YYYY-MM-DD.</error>');
            return Command::INVALID;
        }

        try {
            $paidAt = new DateTimeImmutable($paidAtRaw);
        } catch (\Throwable $e) {
            $output->writeln('<error>Invalid --paid-at date: ' . $e->getMessage() . '</error>');
            return Command::INVALID;
        }

        // CREATE
        if ($idOpt === null) {
            try {
                $id = $this->payments->create($tenantId, $propertyId, $period, $rent, $charges, $paidAt);
            } catch (\Throwable $e) {
                $output->writeln('<error>Failed to create payment: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            $output->writeln("<info>Created payment #{$id}</info>");
            return Command::SUCCESS;
        }

        // UPDATE
        if (!ctype_digit((string) $idOpt)) {
            $output->writeln('<error>--id must be an integer.</error>');
            return Command::INVALID;
        }

        $id = (int) $idOpt;
        if ($this->payments->findById($id) === null) {
            $output->writeln("<error>Payment not found: #{$id}</error>");
            return Command::FAILURE;
        }

        try {
            $this->payments->update($id, $tenantId, $propertyId, $period, $rent, $charges, $paidAt);
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to update payment: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Updated payment #{$id}</info>");

        return Command::SUCCESS;
    }
}

