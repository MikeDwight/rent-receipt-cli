<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;
use RentReceiptCli\Application\Port\Logger;
use RentReceiptCli\Application\UseCase\SendReceiptsForMonth;
use RentReceiptCli\Core\Domain\ValueObject\Month;
use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteReceiptRepository;
use RentReceiptCli\Infrastructure\Mail\SmtpReceiptSender;
use RentReceiptCli\Infrastructure\Storage\FallbackArchiver;
use RentReceiptCli\Infrastructure\Storage\LocalReceiptArchiver;
use RentReceiptCli\Infrastructure\Storage\NextcloudWebdavArchiver;




final class ReceiptSendCommand extends Command
{
    public function __construct(private readonly Logger $logger)
    {
        parent::__construct();
    }

    protected static $defaultName = 'receipt:send';
    protected static $defaultDescription = 'Send generated receipts by email and archive them';

    protected function configure(): void
    {
        $this
            ->addArgument('month', InputArgument::REQUIRED, 'Month to send (format: YYYY-MM)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not send anything, only show what would happen')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force sending even if already marked as sent (future use)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $month = (string) $input->getArgument('month');
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        if (!ConsoleInputValidator::isValidMonth($month)) {
            $output->writeln('<error>Invalid month format. Expected YYYY-MM (e.g. 2026-01)</error>');
            return Command::INVALID;
        }

        $this->logger->info('receipts.send.start', [
            'month' => $month,
            'dry_run' => $dryRun ? 1 : 0,
            'force' => $force ? 1 : 0,
        ]);

        try {



        $config = require dirname(__DIR__, 3) . '/config/config.php';
$pdo = (new PdoConnectionFactory($config['paths']['database']))->create();




$receiptsRepo = new SqliteReceiptRepository($pdo);




// TEMP adapters
$sender = new \RentReceiptCli\Infrastructure\Mail\SmtpReceiptSender($config['smtp']);
$local = new \RentReceiptCli\Infrastructure\Storage\LocalReceiptArchiver($config['paths']['storage_pdf']);
$ncCfg = $config['nextcloud'] ?? [];
$nextcloud = new \RentReceiptCli\Infrastructure\Storage\NextcloudWebdavArchiver(
    (string)($ncCfg['base_url'] ?? ''),
    (string)($ncCfg['username'] ?? ''),
    (string)($ncCfg['password'] ?? ''),
    (string)($ncCfg['base_path'] ?? '/Remote.php/dav/files'),
);
$archiver = new FallbackArchiver($nextcloud, $local, $this->logger);


$uc = new SendReceiptsForMonth($receiptsRepo, $sender, $archiver);

$monthVo = Month::fromString($month);
$res = $uc->execute($monthVo, $dryRun);

$this->logger->info('receipts.send.done', [
    'month' => $month,
    'processed' => $res['processed'] ?? null,
    'sent' => $res['sent'] ?? null,
    'failed' => $res['failed'] ?? null,
    'skipped' => $res['skipped'] ?? null,
    'dry_run' => $dryRun ? 1 : 0,
]);

} catch (\Throwable $e) {
    $this->logger->error('receipts.send.failed', [
        'month' => $month,
        'dry_run' => $dryRun ? 1 : 0,
        'force' => $force ? 1 : 0,
        'error' => $e->getMessage(),
        'type' => $e::class,
    ]);
    throw $e;
}



$output->writeln(sprintf('Processed pending: %d', $res['processed']));
$output->writeln(sprintf('Sent: %d', $res['sent']));
$output->writeln(sprintf('Failed: %d', $res['failed']));
$output->writeln(sprintf('Dry-run skipped: %d', $res['skipped']));


if ($force) {
    $output->writeln('<comment>--force is not implemented yet.</comment>');
}

return Command::SUCCESS;

    }
}
