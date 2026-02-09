<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ReceiptEnvCheckCommand extends Command
{
    protected static $defaultName = 'receipt:env:check';
    protected static $defaultDescription = 'Check if required environment variables are set (without exposing secrets)';

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Skip SMTP/Nextcloud checks (for dry-run mode)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        // Variables critiques pour SMTP
        $smtpVars = [
            'SMTP_HOST' => 'SMTP server hostname',
            'SMTP_PORT' => 'SMTP server port',
            'SMTP_USER' => 'SMTP username',
            'SMTP_PASS' => 'SMTP password',
            'SMTP_FROM' => 'SMTP sender email',
        ];

        // Variables critiques pour Nextcloud
        $nextcloudVars = [
            'NEXTCLOUD_BASE_URL' => 'Nextcloud base URL',
            'NEXTCLOUD_USER' => 'Nextcloud username',
            'NEXTCLOUD_PASS' => 'Nextcloud password',
            'NEXTCLOUD_BASE_PATH' => 'Nextcloud WebDAV base path',
            'NEXTCLOUD_TARGET_DIR' => 'Nextcloud target directory',
        ];

        // Variables optionnelles mais recommandées
        $optionalVars = [
            'SMTP_ENCRYPTION' => 'SMTP encryption (tls/ssl)',
            'SMTP_FROM_NAME' => 'SMTP sender name',
            'WKHTMLTOPDF_BIN' => 'wkhtmltopdf binary path',
            'RECEIPT_ISSUE_CITY' => 'Receipt issue city',
        ];

        $io->title('Environment Variables Check');

        $hasErrors = false;
        $missingCritical = [];

        // Check SMTP variables (only if not dry-run)
        if (!$dryRun) {
            $io->section('SMTP Configuration');
            foreach ($smtpVars as $var => $description) {
                $value = getenv($var);
                $isSet = $value !== false && $value !== '';
                
                if ($isSet) {
                    $io->writeln(sprintf('  <info>✓</info> %-25s <comment>SET</comment> (%s)', $var, $description));
                } else {
                    $io->writeln(sprintf('  <error>✗</error> %-25s <error>MISSING</error> (%s)', $var, $description));
                    $hasErrors = true;
                    $missingCritical[] = $var;
                }
            }
        } else {
            $io->section('SMTP Configuration (skipped in dry-run mode)');
            foreach ($smtpVars as $var => $description) {
                $value = getenv($var);
                $isSet = $value !== false && $value !== '';
                $status = $isSet ? '<comment>SET</comment>' : '<fg=gray>OPTIONAL</fg=gray>';
                $io->writeln(sprintf('  %-25s %s (%s)', $var, $status, $description));
            }
        }

        // Check Nextcloud variables (only if not dry-run)
        if (!$dryRun) {
            $io->section('Nextcloud Configuration');
            foreach ($nextcloudVars as $var => $description) {
                $value = getenv($var);
                $isSet = $value !== false && $value !== '';
                
                if ($isSet) {
                    $io->writeln(sprintf('  <info>✓</info> %-25s <comment>SET</comment> (%s)', $var, $description));
                } else {
                    $io->writeln(sprintf('  <error>✗</error> %-25s <error>MISSING</error> (%s)', $var, $description));
                    $hasErrors = true;
                    $missingCritical[] = $var;
                }
            }
        } else {
            $io->section('Nextcloud Configuration (skipped in dry-run mode)');
            foreach ($nextcloudVars as $var => $description) {
                $value = getenv($var);
                $isSet = $value !== false && $value !== '';
                $status = $isSet ? '<comment>SET</comment>' : '<fg=gray>OPTIONAL</fg=gray>';
                $io->writeln(sprintf('  %-25s %s (%s)', $var, $status, $description));
            }
        }

        // Check optional variables
        $io->section('Optional Configuration');
        foreach ($optionalVars as $var => $description) {
            $value = getenv($var);
            $isSet = $value !== false && $value !== '';
            $status = $isSet ? '<comment>SET</comment>' : '<fg=gray>NOT SET</fg=gray>';
            $io->writeln(sprintf('  %-25s %s (%s)', $var, $status, $description));
        }

        // Summary
        $io->newLine();
        if ($hasErrors) {
            $io->error(sprintf(
                'Missing %d critical environment variable(s): %s',
                count($missingCritical),
                implode(', ', $missingCritical)
            ));
            $io->writeln('');
            $io->writeln('Please set the missing variables before running receipt:send');
            $io->writeln('You can use .env.example as a reference or source env.local.sh');
            return Command::FAILURE;
        } else {
            $io->success('All critical environment variables are set');
            return Command::SUCCESS;
        }
    }
}
