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

final class ConsoleKernel
{
    public static function build(Logger $logger): Application
    {
        $app = new Application('rent-receipt', '0.1.0');

        $app->add(new ReceiptListCommand());
        $app->add(new ReceiptGenerateCommand($logger));
        $app->add(new ReceiptSendCommand($logger));
        $app->add(new SeedImportCommand());
        $app->add(new ReceiptSendStatusCommand());

        return $app;
    }
}
