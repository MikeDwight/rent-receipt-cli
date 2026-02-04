<?php

declare(strict_types=1);

return [
    // Absolute paths are safer in CLI contexts
    'paths' => [
        'root' => dirname(__DIR__),
        'database' => __DIR__ . '/../database.sqlite',
        'templates' => dirname(__DIR__) . '/templates',
        'storage_pdf' => dirname(__DIR__) . '/storage/pdf',
        'storage_logs' => dirname(__DIR__) . '/storage/logs',
    ],
    'landlord' => [
        'name' => 'Mike Dwight',
        'address' => '10 rue Exemple, 75000 Paris',
    ],
    'pdf' => [
    'wkhtmltopdf_binary' => getenv('WKHTMLTOPDF_BIN') ?: 'wkhtmltopdf',
    'keep_temp_html_on_failure' => (getenv('PDF_KEEP_TEMP_HTML') ?: '1') === '1',
    'tmp_dir' => getenv('PDF_TMP_DIR') ?: null,

    'defaults' => [
        'page_size' => 'A4',
        'orientation' => 'Portrait',
        'margin_top_mm' => 10,
        'margin_right_mm' => 10,
        'margin_bottom_mm' => 10,
        'margin_left_mm' => 10,
        'enable_local_file_access' => true,
    ],
    ],
    'smtp' => [
    'enabled' => true,
    'host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
    'port' => (int) (getenv('SMTP_PORT') ?: 587),
    'username' => getenv('SMTP_USERNAME') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // 'tls' | 'ssl' | '' (none)
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'no-reply@example.com',
    'from_name' => getenv('SMTP_FROM_NAME') ?: ($landlordName ?? 'Bailleur'),
    ],
    'nextcloud' => [
    'base_url' => getenv('NEXTCLOUD_BASE_URL') ?: '',
    'username' => getenv('NEXTCLOUD_USERNAME') ?: '',
    'password' => getenv('NEXTCLOUD_PASSWORD') ?: '',
    'base_path' => getenv('NEXTCLOUD_BASE_PATH') ?: '/Remote.php/dav/files',
    ],
    'logging' => [
    'path' => __DIR__ . '/../var/logs',
    'level' => 'info',
    ],
];
