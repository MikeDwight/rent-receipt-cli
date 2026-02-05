<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Cli\ConsoleInputValidator;
use RentReceiptCli\Application\Port\RentPaymentRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PaymentListCommand extends Command
{
    protected static $defaultName = 'payment:list';
    protected static $defaultDescription = 'List rent payments';

    public function __construct(private readonly RentPaymentRepository $payments)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('month', null, InputOption::VALUE_REQUIRED, 'Filter by period (YYYY-MM)')
            ->addOption('tenant-id', null, InputOption::VALUE_REQUIRED, 'Filter by tenant id')
            ->addOption('property-id', null, InputOption::VALUE_REQUIRED, 'Filter by property id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $monthOpt = $input->getOption('month');
        $tenantIdRaw = $input->getOption('tenant-id');
        $propertyIdRaw = $input->getOption('property-id');

        $month = null;
        if ($monthOpt !== null && $monthOpt !== '') {
            $monthStr = (string) $monthOpt;
            if (!ConsoleInputValidator::isValidMonth($monthStr)) {
                $output->writeln('<error>Invalid --month format. Expected YYYY-MM.</error>');
                return Command::INVALID;
            }
            $month = Month::fromString($monthStr);
        }

        $tenantId = null;
        if ($tenantIdRaw !== null && $tenantIdRaw !== '') {
            $tenantIdStr = (string) $tenantIdRaw;
            if (!ctype_digit($tenantIdStr)) {
                $output->writeln('<error>--tenant-id must be an integer.</error>');
                return Command::INVALID;
            }
            $tenantId = (int) $tenantIdStr;
        }

        $propertyId = null;
        if ($propertyIdRaw !== null && $propertyIdRaw !== '') {
            $propertyIdStr = (string) $propertyIdRaw;
            if (!ctype_digit($propertyIdStr)) {
                $output->writeln('<error>--property-id must be an integer.</error>');
                return Command::INVALID;
            }
            $propertyId = (int) $propertyIdStr;
        }

        $rows = $this->payments->list($month, $tenantId, $propertyId);

        if (count($rows) === 0) {
            $output->writeln('<comment>No rent payments found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Rent payments</info>');
        foreach ($rows as $r) {
            $line = sprintf(
                '  #%d  tenant #%d  property #%d  %s  rent=%d  charges=%d  paid_at=%s',
                (int) $r['id'],
                (int) $r['tenant_id'],
                (int) $r['property_id'],
                (string) $r['period'],
                (int) $r['rent_amount'],
                (int) $r['charges_amount'],
                (string) $r['paid_at']
            );

            $output->writeln($line);
        }

        return Command::SUCCESS;
    }
}

