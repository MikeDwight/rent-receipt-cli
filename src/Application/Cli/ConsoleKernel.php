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





final class ConsoleKernel
{
    public static function build(Logger $logger): Application
    {
        $app = new Application('rent-receipt', '0.1.0');
        $config = require __DIR__ . '/../../../config/config.php';
        $pdo = (new PdoConnectionFactory($config['paths']['database']))->create();

        $ownerRepo = new SqliteOwnerRepository($pdo);

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



        return $app;
    }
}
