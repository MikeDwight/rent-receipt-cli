<?php

declare(strict_types=1);

namespace RentReceiptCli\Application\Cli;

use Symfony\Component\Console\Application;
use RentReceiptCli\Application\Command\ReceiptListCommand;
use RentReceiptCli\Application\Command\ReceiptGenerateCommand;
use RentReceiptCli\Application\Command\ReceiptSendCommand;
use RentReceiptCli\CLI\Command\SeedImportCommand;
use RentReceiptCli\Application\Command\ReceiptSendStatusCommand;






final class ConsoleKernel
{
    public static function build(): Application
    {
        $app = new Application('rent-receipt', '0.1.0');
        $app->add(new ReceiptListCommand());
        $app->add(new ReceiptGenerateCommand());
        $app->add(new ReceiptSendCommand());
        $app->add(new SeedImportCommand());
        $app->add(new ReceiptSendStatusCommand());





        return $app;
    }
}
