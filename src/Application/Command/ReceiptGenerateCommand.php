<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Command;

use RentReceiptCli\Application\UseCase\GenerateReceiptsForMonth;
use RentReceiptCli\Application\Cli\ConsoleInputValidator;
use RentReceiptCli\Infrastructure\Database\DryRunReceiptRepository;
use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteReceiptRepository;
use RentReceiptCli\Infrastructure\Database\SqliteRentPaymentRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RentReceiptCli\Core\Service\ReceiptHtmlBuilder;
use RentReceiptCli\Infrastructure\Pdf\WkhtmltopdfPdfGenerator;
use RentReceiptCli\Infrastructure\Template\SimpleTemplateRenderer;
use RentReceiptCli\Core\Service\Pdf\PdfOptions;
use RentReceiptCli\Application\Port\Logger;





final class ReceiptGenerateCommand extends Command
{
    public function __construct(private readonly Logger $logger)
    {
        parent::__construct();
    }

    protected static $defaultName = 'receipt:generate';
    protected static $defaultDescription = 'Generate rent receipts PDFs for a given month';

    protected function configure(): void
    {
        $this
            ->addArgument('month', InputArgument::REQUIRED, 'Month to generate (format: YYYY-MM)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not generate anything, only show what would happen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $month = (string) $input->getArgument('month');
        $dryRun = (bool) $input->getOption('dry-run');

        
        if (!ConsoleInputValidator::isValidMonth($month)) {
            $output->writeln('<error>Invalid month format. Expected YYYY-MM (e.g. 2026-01)</error>');
            return Command::INVALID;
            }

        $config = require __DIR__ . '/../../../config/config.php';
            
        // Config
        $landlordName = (string) ($config['landlord']['name'] ?? 'Bailleur');
        $landlordAddress = (string) ($config['landlord']['address'] ?? '');

        
        $pdo = (new PdoConnectionFactory($config['paths']['database']))->create();

        // Repositories
        $paymentsRepo = new SqliteRentPaymentRepository($pdo);
        $receiptsRepo = new SqliteReceiptRepository($pdo);

        if ($dryRun) {
            $receiptsRepo = new DryRunReceiptRepository($receiptsRepo);
        }

        


        // Use case wiring (composition root)
        $renderer = new SimpleTemplateRenderer();
        $htmlBuilder = new ReceiptHtmlBuilder($renderer, __DIR__ . '/../../../templates/receipt.html');
        $pdfConfig = $config['pdf'] ?? [];

        $pdf = new WkhtmltopdfPdfGenerator(
            wkhtmltopdfBinary: (string)($pdfConfig['wkhtmltopdf_binary'] ?? 'wkhtmltopdf'),
            keepTempHtmlOnFailure: (bool)($pdfConfig['keep_temp_html_on_failure'] ?? true),
            tmpDir: $pdfConfig['tmp_dir'] ?? null,
        );

        $pdfDefaults = $pdfConfig['defaults'] ?? [];

        $pdfOptions = new PdfOptions(
            pageSize: (string)($pdfDefaults['page_size'] ?? 'A4'),
            orientation: (string)($pdfDefaults['orientation'] ?? 'Portrait'),
            marginTopMm: (int)($pdfDefaults['margin_top_mm'] ?? 10),
            marginRightMm: (int)($pdfDefaults['margin_right_mm'] ?? 10),
            marginBottomMm: (int)($pdfDefaults['margin_bottom_mm'] ?? 10),
            marginLeftMm: (int)($pdfDefaults['margin_left_mm'] ?? 10),
            enableLocalFileAccess: (bool)($pdfDefaults['enable_local_file_access'] ?? true),
        );



        $uc = new GenerateReceiptsForMonth(
            $paymentsRepo,
            $receiptsRepo,
            $htmlBuilder,
            $pdf,
            $pdfOptions,
            $landlordName,
            $landlordAddress,
            $this->logger
        );





        $result = $uc->execute($month);

        if ($dryRun) {
            $output->writeln("Dry-run: computed receipts for <info>{$month}</info> (no DB write)");
        } else {
            $output->writeln("Generated receipts for <info>{$month}</info>");
        }

        $output->writeln(sprintf('Created: <info>%d</info>', count($result->created)));
        $output->writeln(sprintf('Skipped: <comment>%d</comment>', count($result->skipped)));

        // Optional: show details (useful for debugging)
        foreach ($result->created as $row) {
            $output->writeln(sprintf(
                ' + created receipt for tenant #%d (payment #%d) -> %s',
                (int) $row['tenant_id'],
                (int) $row['rent_payment_id'],
                (string) $row['pdf_path']
            ));
        }

        foreach ($result->skipped as $row) {
            $output->writeln(sprintf(
                ' - skipped tenant #%d (%s)',
                (int) $row['tenant_id'],
                (string) $row['reason']
            ));
        }

        return Command::SUCCESS;
    }
}
