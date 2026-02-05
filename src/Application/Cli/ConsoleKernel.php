<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Cli;

use Symfony\Component\Console\Application;
use RentReceiptCli\Application\Command\ReceiptListCommand;
use RentReceiptCli\Application\Command\ReceiptGenerateCommand;
use RentReceiptCli\Application\Command\ReceiptSendCommand;
use RentReceiptCli\Application\Command\ReceiptSendStatusCommand;
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




final class ConsoleKernel
{
    public static function build(Logger $logger): Application
    {
        $app = new Application('rent-receipt', '0.1.0');
        $config = require __DIR__ . '/../../../config/config.php';
        $pdo = (new PdoConnectionFactory($config['paths']['database']))->create();

        $ownerRepo = new SqliteOwnerRepository($pdo);
        $tenantRepo = new SqliteTenantRepository($pdo);
        $propertyRepo = new SqlitePropertyRepository($pdo);

        $app->add(new OwnerListCommand($ownerRepo));
        $app->add(new OwnerShowCommand($ownerRepo));
        $app->add(new ReceiptListCommand());
        $app->add(new ReceiptGenerateCommand($logger));
        $app->add(new ReceiptSendCommand($logger));
        $app->add(new SeedImportCommand());
        $app->add(new ReceiptSendStatusCommand());
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



        return $app;
    }
}
