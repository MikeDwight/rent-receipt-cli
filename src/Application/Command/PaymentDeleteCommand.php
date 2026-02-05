<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\Port\RentPaymentRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class PaymentDeleteCommand extends Command
{
    protected static $defaultName = 'payment:delete';
    protected static $defaultDescription = 'Delete a rent payment';

    public function __construct(private readonly RentPaymentRepository $payments)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Payment id')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Do not ask for confirmation');
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

        $force = (bool) $input->getOption('force');
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf(
                    'Delete payment #%d (tenant #%d, property #%d, %s)? [y/N] ',
                    $payment['id'],
                    $payment['tenant_id'],
                    $payment['property_id'],
                    $payment['period']
                ),
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');
                return Command::SUCCESS;
            }
        }

        $this->payments->delete($id);
        $output->writeln("<info>Deleted payment #{$id}</info>");

        return Command::SUCCESS;
    }
}

