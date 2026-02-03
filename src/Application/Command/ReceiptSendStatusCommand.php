<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteReceiptRepository;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReceiptSendStatusCommand extends Command
{
    protected static $defaultName = 'receipt:send:status';

    protected function configure(): void
    {
        $this
            ->setDescription('Show receipt send/archive status for a given month')
            ->addArgument('month', InputArgument::REQUIRED, 'Month (YYYY-MM)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $monthString = $input->getArgument('month');

        if (!preg_match('/^\d{4}-\d{2}$/', $monthString)) {
            $output->writeln('<error>Invalid month format. Expected YYYY-MM.</error>');
            return Command::FAILURE;
        }

        // Load config
        $config = require dirname(__DIR__, 3) . '/config/config.php';

        // Create PDO
        $pdoFactory = new PdoConnectionFactory($config['paths']['database']);
        $pdo = $pdoFactory->create();

        // Repository
        $repository = new SqliteReceiptRepository($pdo);

        // Month VO
        $month = Month::fromString($monthString);

        $receipts = $repository->findByMonth($month);

        if (empty($receipts)) {
            $output->writeln('<comment>No receipts found.</comment>');
            return Command::SUCCESS;
        }

        foreach ($receipts as $r) {
            $status = 'PENDING';

            if ($r['sent_at']) {
                $status = 'SENT';
            } elseif ($r['send_error']) {
                $status = 'ERROR';
            }

            $output->writeln(sprintf(
                '#%d | payment:%d | pdf:%s | sent:%s | archived:%s | %s',
                $r['id'],
                $r['rent_payment_id'],
                $r['pdf_path'],
                $r['sent_at'] ?? '-',
                $r['archived_at'] ?? '-',
                $status
            ));
        }

        return Command::SUCCESS;
    }
}
