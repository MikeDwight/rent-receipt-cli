<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\RentPaymentRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PaymentShowCommand extends Command
{
    protected static $defaultName = 'payment:show';
    protected static $defaultDescription = 'Show a single rent payment by id';

    public function __construct(private readonly RentPaymentRepository $payments)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Payment id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idRaw = (string) $input->getArgument('id');
        if (!ctype_digit($idRaw)) {
            $output->writeln('<error>Invalid id. Expected an integer.</error>');
            return Command::INVALID;
        }

        $id = (int) $idRaw;
        $payment = $this->payments->findById($id);

        if ($payment === null) {
            $output->writeln("<error>Payment not found: #{$id}</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Payment #%d</info>', $payment['id']));
        $output->writeln('  Tenant id:   ' . $payment['tenant_id']);
        $output->writeln('  Property id: ' . $payment['property_id']);
        $output->writeln('  Period:      ' . $payment['period']);
        $output->writeln('  Rent:        ' . $payment['rent_amount']);
        $output->writeln('  Charges:     ' . $payment['charges_amount']);
        $output->writeln('  Paid at:     ' . $payment['paid_at']);
        $output->writeln('  Created at:  ' . $payment['created_at']);

        return Command::SUCCESS;
    }
}

