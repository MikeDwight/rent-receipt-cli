<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RentReceiptCli\Application\UseCase\GenerateReceiptsForMonth;
use RentReceiptCli\Infrastructure\Database\PdoConnectionFactory;
use RentReceiptCli\Infrastructure\Database\SqliteReceiptRepository;
use RentReceiptCli\Infrastructure\Database\SqliteRentPaymentRepository;

$pdo = (new PdoConnectionFactory(__DIR__ . '/../database.sqlite'))->create();

$payments = new SqliteRentPaymentRepository($pdo);
$receipts = new SqliteReceiptRepository($pdo);

$uc = new GenerateReceiptsForMonth($payments, $receipts);

$result = $uc->execute($argv[1] ?? '2026-01');

echo "Created: " . count($result->created) . PHP_EOL;
echo "Skipped: " . count($result->skipped) . PHP_EOL;

print_r($result->created);
print_r($result->skipped);
