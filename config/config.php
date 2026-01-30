<?php

declare(strict_types=1);

return [
    // Absolute paths are safer in CLI contexts
    'paths' => [
        'root' => dirname(__DIR__),
        'database' => dirname(__DIR__) . '/database/database.sqlite',
        'templates' => dirname(__DIR__) . '/templates',
        'storage_pdf' => dirname(__DIR__) . '/storage/pdf',
        'storage_logs' => dirname(__DIR__) . '/storage/logs',
    ],
];
