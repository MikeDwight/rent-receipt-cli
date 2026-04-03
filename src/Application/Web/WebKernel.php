<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Web;

use RentReceiptCli\Application\Web\Controller\AuthController;
use RentReceiptCli\Application\Web\Controller\DashboardController;
use RentReceiptCli\Application\Web\Controller\OwnerController;
use RentReceiptCli\Application\Web\Controller\TenantController;
use RentReceiptCli\Application\Web\Controller\PropertyController;
use RentReceiptCli\Application\Web\Controller\PaymentController;
use RentReceiptCli\Application\Web\Controller\ReceiptController;
use RentReceiptCli\Application\Web\Controller\SettingsController;
use RentReceiptCli\Application\Web\Middleware\AuthMiddleware;
use RentReceiptCli\Application\UseCase\ProcessReceiptForPayment;
use RentReceiptCli\Core\Service\Pdf\PdfOptions;
use RentReceiptCli\Core\Service\ReceiptHtmlBuilder;
use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteOwnerRepository;
use RentReceiptCli\Infrastructure\Database\SqliteTenantRepository;
use RentReceiptCli\Infrastructure\Database\SqlitePropertyRepository;
use RentReceiptCli\Infrastructure\Database\SqliteRentPaymentRepository;
use RentReceiptCli\Infrastructure\Database\SqliteReceiptRepository;
use RentReceiptCli\Infrastructure\Database\SqliteSettingsRepository;
use RentReceiptCli\Infrastructure\Logging\FileLogger;
use RentReceiptCli\Infrastructure\Mail\SmtpReceiptSender;
use RentReceiptCli\Infrastructure\Pdf\WkhtmltopdfPdfGenerator;
use RentReceiptCli\Infrastructure\Process\SinglePaymentReceiptGenerator;
use RentReceiptCli\Infrastructure\Process\SingleReceiptSenderAndArchiver;
use RentReceiptCli\Infrastructure\Process\SqliteUpsertPaymentForPeriod;
use RentReceiptCli\Infrastructure\Storage\FallbackArchiver;
use RentReceiptCli\Infrastructure\Storage\LocalReceiptArchiver;
use RentReceiptCli\Infrastructure\Storage\NextcloudWebdavArchiver;
use RentReceiptCli\Infrastructure\Template\SimpleTemplateRenderer;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

final class WebKernel
{
    public static function build(): App
    {
        $config = require __DIR__ . '/../../../config/config.php';
        $pdo = (new PdoConnectionFactory($config['paths']['database']))->create();

        $logger = new FileLogger(
            (string) ($config['logging']['dir'] ?? __DIR__ . '/../../../var/log'),
            (string) ($config['logging']['level'] ?? 'info'),
        );

        $ownerRepo    = new SqliteOwnerRepository($pdo);
        $tenantRepo   = new SqliteTenantRepository($pdo);
        $propertyRepo = new SqlitePropertyRepository($pdo);
        $paymentRepo  = new SqliteRentPaymentRepository($pdo);
        $receiptRepo  = new SqliteReceiptRepository($pdo);
        $settingsRepo = new SqliteSettingsRepository($pdo);

        $processUseCase = self::buildProcessUseCase($config, $pdo, $ownerRepo, $logger, $settingsRepo);
        $htmlBuilder    = self::buildHtmlBuilder($config);

        $twig = Twig::create(
            __DIR__ . '/../../../templates/web',
            ['cache' => false],
        );

        $passwordHash = (string) (getenv('WEB_PASSWORD') ?: '');

        $authCtrl     = new AuthController($twig, $passwordHash);
        $dashCtrl     = new DashboardController($twig, $ownerRepo, $tenantRepo, $propertyRepo, $paymentRepo, $receiptRepo);
        $ownerCtrl    = new OwnerController($twig, $ownerRepo);
        $tenantCtrl   = new TenantController($twig, $tenantRepo);
        $propertyCtrl = new PropertyController($twig, $propertyRepo, $ownerRepo);
        $paymentCtrl  = new PaymentController($twig, $paymentRepo, $receiptRepo, $tenantRepo, $propertyRepo);
        $sendPort     = self::buildSendPort($config, $receiptRepo, $logger, $settingsRepo);
        $landlordCity = (string) ($config['landlord']['city'] ?? '');
        $receiptCtrl  = new ReceiptController($twig, $receiptRepo, $paymentRepo, $processUseCase, $sendPort, $htmlBuilder, $landlordCity);
        $settingsCtrl = new SettingsController($twig, $settingsRepo);

        $app = AppFactory::create();
        $app->add(TwigMiddleware::create($app, $twig));
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, true);

        // Public routes
        $app->get('/login', [$authCtrl, 'showLogin']);
        $app->post('/login', [$authCtrl, 'handleLogin']);
        $app->post('/logout', [$authCtrl, 'handleLogout']);

        // Protected routes
        $app->group('', function (RouteCollectorProxy $group) use (
            $dashCtrl, $ownerCtrl, $tenantCtrl, $propertyCtrl, $paymentCtrl, $receiptCtrl, $settingsCtrl
        ) {
            $group->get('/', [$dashCtrl, 'index']);

            // Owners
            $group->get('/owners', [$ownerCtrl, 'index']);
            $group->get('/owners/new', [$ownerCtrl, 'create']);
            $group->post('/owners', [$ownerCtrl, 'store']);
            $group->get('/owners/{id}/edit', [$ownerCtrl, 'edit']);
            $group->post('/owners/{id}', [$ownerCtrl, 'update']);
            $group->post('/owners/{id}/delete', [$ownerCtrl, 'destroy']);

            // Tenants
            $group->get('/tenants', [$tenantCtrl, 'index']);
            $group->get('/tenants/new', [$tenantCtrl, 'create']);
            $group->post('/tenants', [$tenantCtrl, 'store']);
            $group->get('/tenants/{id}/edit', [$tenantCtrl, 'edit']);
            $group->post('/tenants/{id}', [$tenantCtrl, 'update']);
            $group->post('/tenants/{id}/delete', [$tenantCtrl, 'destroy']);

            // Properties
            $group->get('/properties', [$propertyCtrl, 'index']);
            $group->get('/properties/new', [$propertyCtrl, 'create']);
            $group->post('/properties', [$propertyCtrl, 'store']);
            $group->get('/properties/{id}/edit', [$propertyCtrl, 'edit']);
            $group->post('/properties/{id}', [$propertyCtrl, 'update']);
            $group->post('/properties/{id}/delete', [$propertyCtrl, 'destroy']);

            // Payments
            $group->get('/payments', [$paymentCtrl, 'index']);
            $group->get('/payments/new', [$paymentCtrl, 'create']);
            $group->post('/payments', [$paymentCtrl, 'store']);
            $group->get('/payments/{id}/edit', [$paymentCtrl, 'edit']);
            $group->post('/payments/{id}', [$paymentCtrl, 'update']);
            $group->post('/payments/{id}/delete', [$paymentCtrl, 'destroy']);

            // Receipts
            $group->get('/receipts/preview', [$receiptCtrl, 'preview']);
            $group->get('/receipts', [$receiptCtrl, 'index']);

            $group->get('/receipts/{id}/download', [$receiptCtrl, 'download']);
            $group->get('/receipts/{id}/view', [$receiptCtrl, 'view']);
            $group->post('/receipts/{id}/send', [$receiptCtrl, 'sendReceipt']);
            $group->post('/receipts/{id}/delete', [$receiptCtrl, 'destroy']);
            $group->post('/payments/{id}/process-receipt', [$receiptCtrl, 'processFromPayment']);

            // Settings
            $group->get('/settings', [$settingsCtrl, 'index']);
            $group->post('/settings', [$settingsCtrl, 'update']);
        })->add(new AuthMiddleware());

        return $app;
    }

    private static function buildProcessUseCase(
        array $config,
        \PDO $pdo,
        SqliteOwnerRepository $ownerRepo,
        FileLogger $logger,
        SqliteSettingsRepository $settingsRepo,
    ): ProcessReceiptForPayment {
        $rentPaymentRepo = new SqliteRentPaymentRepository($pdo);
        $receiptRepo     = new SqliteReceiptRepository($pdo);
        $propertyRepo    = new SqlitePropertyRepository($pdo);

        $htmlBuilder = self::buildHtmlBuilder($config);

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

        $landlordName = (string) ($config['landlord']['name'] ?? '');
        $landlordAddress = (string) ($config['landlord']['address'] ?? '');
        if (empty($landlordName) || empty($landlordAddress)) {
            $owners = $ownerRepo->listAll();
            if (!empty($owners)) {
                $dbOwner = $owners[0];
                if (empty($landlordName)) $landlordName = $dbOwner['full_name'];
                if (empty($landlordAddress)) $landlordAddress = $dbOwner['address'];
            }
        }
        $landlordCity = (string) ($config['landlord']['city'] ?? '');

        $sendPort = self::buildSendPort($config, $receiptRepo, $logger, $settingsRepo);

        $upsertPort = new SqliteUpsertPaymentForPeriod($rentPaymentRepo, $propertyRepo);
        $genPort    = new SinglePaymentReceiptGenerator(
            $rentPaymentRepo, $receiptRepo, $ownerRepo,
            $htmlBuilder, $pdf, $pdfOptions,
            $landlordName, $landlordAddress, $landlordCity,
        );

        return new ProcessReceiptForPayment($upsertPort, $genPort, $sendPort);
    }

    private static function buildHtmlBuilder(array $config): ReceiptHtmlBuilder
    {
        $templatesPath = (string) ($config['paths']['templates'] ?? (__DIR__ . '/../../../templates'));
        return new ReceiptHtmlBuilder(new SimpleTemplateRenderer(), $templatesPath . '/receipt.html');
    }

    private static function buildSendPort(
        array $config,
        SqliteReceiptRepository $receiptRepo,
        FileLogger $logger,
        SqliteSettingsRepository $settingsRepo,
    ): SingleReceiptSenderAndArchiver {
        $ncCfg     = $config['nextcloud'] ?? [];
        $local     = new LocalReceiptArchiver($config['paths']['storage_pdf']);
        $nextcloud = new NextcloudWebdavArchiver(
            (string) ($ncCfg['base_url'] ?? ''),
            (string) ($ncCfg['username'] ?? ''),
            (string) ($ncCfg['password'] ?? ''),
            (string) ($ncCfg['base_path'] ?? '/remote.php/dav/files'),
        );
        $archiver = new FallbackArchiver($nextcloud, $local, $logger);
        $sender   = new SmtpReceiptSender($config['smtp']);

        // DB setting takes priority over env variable
        $targetDir = $settingsRepo->get('nextcloud_target_dir')
            ?: (string) ($ncCfg['target_dir'] ?? '');

        return new SingleReceiptSenderAndArchiver(
            $receiptRepo, $sender, $archiver,
            $targetDir,
        );
    }
}
