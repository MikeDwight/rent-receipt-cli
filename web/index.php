<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RentReceiptCli\Application\Web\WebKernel;

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name('rent_web');
session_start();

$app = WebKernel::build();
$app->run();
