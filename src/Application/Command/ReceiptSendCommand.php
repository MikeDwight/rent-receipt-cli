<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;


final class ReceiptSendCommand extends Command
{
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


        $config = require dirname(__DIR__, 3) . '/config/config.php';
$pdo = (new \RentReceiptCli\Infrastructure\Database\PdoConnectionFactory($config['paths']['database']))->create();

$receiptsRepo = new \RentReceiptCli\Infrastructure\Database\SqliteReceiptRepository($pdo);

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
$archiver = new \RentReceiptCli\Infrastructure\Storage\FallbackArchiver($nextcloud, $local);


$uc = new \RentReceiptCli\Application\UseCase\SendReceiptsForMonth($receiptsRepo, $sender, $archiver);

$monthVo = \RentReceiptCli\Core\Domain\ValueObject\Month::fromString($month);
$res = $uc->execute($monthVo, $dryRun);

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
