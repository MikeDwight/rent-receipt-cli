<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Cli;

use Symfony\Component\Console\Application;
use RentReceiptCli\Application\Command\ReceiptListCommand;
use RentReceiptCli\Application\Command\ReceiptGenerateCommand;
use RentReceiptCli\Application\Command\ReceiptSendCommand;
use RentReceiptCli\Application\Command\ReceiptSendStatusCommand;
use RentReceiptCli\Application\Command\ReceiptEnvCheckCommand;
use RentReceiptCli\CLI\Command\SeedImportCommand;
use RentReceiptCli\Application\Port\Logger;
use RentReceiptCli\Application\Command\DbMigrateCommand;
use RentReceiptCli\Application\Command\DbMigrateStatusCommand;
use RentReceiptCli\Application\Command\DbMigrateMarkCommand;
use RentReceiptCli\Application\Command\DbStatusCommand;
use RentReceiptCli\Application\Command\OwnerListCommand;
use RentReceiptCli\Application\Command\OwnerShowCommand;
use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteOwnerRepository;
use RentReceiptCli\Application\Command\OwnerUpsertCommand;
use RentReceiptCli\Application\Command\OwnerDeleteCommand;
use RentReceiptCli\Application\Command\TenantListCommand;
use RentReceiptCli\Application\Command\TenantShowCommand;
use RentReceiptCli\Application\Command\TenantUpsertCommand;
use RentReceiptCli\Application\Command\TenantDeleteCommand;
use RentReceiptCli\Infrastructure\Database\SqliteTenantRepository;
use RentReceiptCli\Application\Command\PropertyListCommand;
use RentReceiptCli\Application\Command\PropertyShowCommand;
use RentReceiptCli\Application\Command\PropertyUpsertCommand;
use RentReceiptCli\Application\Command\PropertyDeleteCommand;
use RentReceiptCli\Infrastructure\Database\SqlitePropertyRepository;
use RentReceiptCli\Application\Command\PaymentListCommand;
use RentReceiptCli\Application\Command\PaymentShowCommand;
use RentReceiptCli\Application\Command\PaymentUpsertCommand;
use RentReceiptCli\Application\Command\PaymentDeleteCommand;
use RentReceiptCli\Application\UseCase\GenerateReceiptsForMonth;
use RentReceiptCli\Application\UseCase\SendReceiptsForMonth;
use RentReceiptCli\Infrastructure\Database\SqliteRentPaymentRepository;
use RentReceiptCli\Infrastructure\Database\SqliteReceiptRepository;
use RentReceiptCli\Infrastructure\Pdf\WkhtmltopdfPdfGenerator;
use RentReceiptCli\Infrastructure\Template\SimpleTemplateRenderer;
use RentReceiptCli\Core\Service\ReceiptHtmlBuilder;
use RentReceiptCli\Core\Service\Pdf\PdfOptions;
use RentReceiptCli\Infrastructure\Mail\SmtpReceiptSender;
use RentReceiptCli\Infrastructure\Storage\LocalReceiptArchiver;
use RentReceiptCli\Infrastructure\Storage\NextcloudWebdavArchiver;
use RentReceiptCli\Infrastructure\Storage\FallbackArchiver;




final class ConsoleKernel
{
    public static function build(Logger $logger): Application
    {
        $app = new Application('rent-receipt', '0.1.0');
        $config = require __DIR__ . '/../../../config/config.php';
        $pdo = self::buildPdo($config);

        $ownerRepo = new SqliteOwnerRepository($pdo);
        $tenantRepo = new SqliteTenantRepository($pdo);
        $propertyRepo = new SqlitePropertyRepository($pdo);
        $rentPaymentRepo = new SqliteRentPaymentRepository($pdo);

        $generateUseCase = self::buildGenerateReceiptsForMonthUseCase($config, $pdo, $ownerRepo, $logger);
        $sendUseCase = self::buildSendReceiptsForMonthUseCase($config, $pdo, $logger);

        $app->add(new OwnerListCommand($ownerRepo));
        $app->add(new OwnerShowCommand($ownerRepo));
        $app->add(new ReceiptListCommand());
        $app->add(new ReceiptGenerateCommand($logger, $generateUseCase));
        $app->add(new ReceiptSendCommand($logger, $sendUseCase));
        $app->add(new ReceiptSendStatusCommand());
        $app->add(new ReceiptEnvCheckCommand());
        $app->add(new SeedImportCommand());
        $app->add(new DbMigrateCommand());
        $app->add(new DbMigrateStatusCommand());
        $app->add(new DbMigrateMarkCommand());
        $app->add(new DbStatusCommand());
        $app->add(new OwnerUpsertCommand($ownerRepo));
        $app->add(new OwnerDeleteCommand($ownerRepo));
        $app->add(new TenantListCommand($tenantRepo));
        $app->add(new TenantShowCommand($tenantRepo));
        $app->add(new TenantUpsertCommand($tenantRepo));
        $app->add(new TenantDeleteCommand($tenantRepo));   
        $app->add(new PropertyListCommand($propertyRepo));
        $app->add(new PropertyShowCommand($propertyRepo));
        $app->add(new PropertyUpsertCommand($propertyRepo));
        $app->add(new PropertyDeleteCommand($propertyRepo));
        $app->add(new PaymentListCommand($rentPaymentRepo));
        $app->add(new PaymentShowCommand($rentPaymentRepo));
        $app->add(new PaymentUpsertCommand($rentPaymentRepo, $tenantRepo, $propertyRepo));
        $app->add(new PaymentDeleteCommand($rentPaymentRepo));

        return $app;
    }

    private static function buildPdo(array $config): \PDO
    {
        return (new PdoConnectionFactory($config['paths']['database']))->create();
    }

    private static function buildReceiptRepository(\PDO $pdo): SqliteReceiptRepository
    {
        return new SqliteReceiptRepository($pdo);
    }

    private static function buildReceiptSender(array $config): SmtpReceiptSender
    {
        return new SmtpReceiptSender($config['smtp']);
    }

    private static function buildReceiptArchiver(array $config, Logger $logger): FallbackArchiver
    {
        $local = new LocalReceiptArchiver($config['paths']['storage_pdf']);

        $ncCfg = $config['nextcloud'] ?? [];
        $nextcloud = new NextcloudWebdavArchiver(
            (string) ($ncCfg['base_url'] ?? ''),
            (string) ($ncCfg['username'] ?? ''),
            (string) ($ncCfg['password'] ?? ''),
            (string) ($ncCfg['base_path'] ?? '/remote.php/dav/files'),
        );

        return new FallbackArchiver($nextcloud, $local, $logger);
    }

    private static function buildSendReceiptsForMonthUseCase(array $config, \PDO $pdo, Logger $logger): SendReceiptsForMonth
    {
        $receiptsRepo = self::buildReceiptRepository($pdo);
        $sender = self::buildReceiptSender($config);
        $archiver = self::buildReceiptArchiver($config, $logger);
        $ncCfg = $config['nextcloud'] ?? [];

        return new SendReceiptsForMonth(
            receipts: $receiptsRepo,
            sender: $sender,
            archiver: $archiver,
            logger: $logger,
            nextcloudTargetDir: (string) ($ncCfg['target_dir'] ?? ''),
        );
    }

    private static function buildGenerateReceiptsForMonthUseCase(array $config, \PDO $pdo, SqliteOwnerRepository $ownerRepo, Logger $logger): GenerateReceiptsForMonth
    {
        $paymentsRepo = new SqliteRentPaymentRepository($pdo);
        $receiptsRepo = new SqliteReceiptRepository($pdo);

        $templatesPath = (string) ($config['paths']['templates'] ?? (__DIR__ . '/../../../templates'));

        $renderer = new SimpleTemplateRenderer();
        $htmlBuilder = new ReceiptHtmlBuilder($renderer, $templatesPath . '/receipt.html');
        $pdfConfig = $config['pdf'] ?? [];

        $pdf = new WkhtmltopdfPdfGenerator(
            wkhtmltopdfBinary: (string) ($pdfConfig['wkhtmltopdf_binary'] ?? 'wkhtmltopdf'),
            keepTempHtmlOnFailure: (bool) ($pdfConfig['keep_temp_html_on_failure'] ?? true),
            tmpDir: $pdfConfig['tmp_dir'] ?? null,
        );

        $pdfDefaults = $pdfConfig['defaults'] ?? [];

        $pdfOptions = new PdfOptions(
            pageSize: (string) ($pdfDefaults['page_size'] ?? 'A4'),
            orientation: (string) ($pdfDefaults['orientation'] ?? 'Portrait'),
            marginTopMm: (int) ($pdfDefaults['margin_top_mm'] ?? 10),
            marginRightMm: (int) ($pdfDefaults['margin_right_mm'] ?? 10),
            marginBottomMm: (int) ($pdfDefaults['margin_bottom_mm'] ?? 10),
            marginLeftMm: (int) ($pdfDefaults['margin_left_mm'] ?? 10),
            enableLocalFileAccess: (bool) ($pdfDefaults['enable_local_file_access'] ?? true),
        );

        // Règle de source de vérité : DB (owners) par défaut, env (LANDLORD_NAME/ADDRESS) en override optionnel
        $landlordName = (string) ($config['landlord']['name'] ?? '');
        $landlordAddress = (string) ($config['landlord']['address'] ?? '');

        // Si override env non défini, charger depuis DB (premier owner)
        if (empty($landlordName) || empty($landlordAddress)) {
            $owners = $ownerRepo->listAll();
            if (empty($owners)) {
                throw new \RuntimeException(
                    'Aucun bailleur trouvé en base de données. ' .
                    'Veuillez soit : (1) importer un owner via seed ou owner:upsert, ' .
                    'soit (2) définir LANDLORD_NAME et LANDLORD_ADDRESS dans votre fichier .env'
                );
            }
            // Utiliser le premier owner de la DB
            $dbOwner = $owners[0];
            if (empty($landlordName)) {
                $landlordName = $dbOwner['full_name'];
            }
            if (empty($landlordAddress)) {
                $landlordAddress = $dbOwner['address'];
            }
        }

        $landlordCity = (string) ($config['landlord']['city'] ?? '');

        return new GenerateReceiptsForMonth(
            $paymentsRepo,
            $receiptsRepo,
            $htmlBuilder,
            $pdf,
            $pdfOptions,
            $landlordName,
            $landlordAddress,
            $landlordCity,
            $logger,
        );
    }
}
