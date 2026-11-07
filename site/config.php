<?php
/**
 * Sai Sreenivasa Decorin — App Configuration
 * Configuration for lead notifications, transactional email & admin dashboard
 */

declare(strict_types=1);

// 1. Auto-load .env file if present
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $trimmed, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
    }
}

return [
    // Primary recipient for customer enquiries
    'admin_email' => 'loknath.surabhi@gmail.com',
    'admin_name'  => 'Sai Sreenivasa Decorin Admin',

    // All admin emails that receive instant lead alerts
    'admin_emails' => [
        'loknath.surabhi@gmail.com',
        'jacketking.loknath@gmail.com'
    ],

    // Password to access https://ssdecorin.com/leads
    'leads_password' => 'Decorin@2026',

    // Transactional Email API (Resend.com - instant 1-2 second delivery)
    'resend_api_key' => getenv('RESEND_API_KEY') ?: '',
    
    // Official Verified Sender Address
    'resend_from' => 'Sai Sreenivasa Decorin <noreply@ssdecorin.com>',
    'resend_sandbox_email' => 'jacketking.loknath@gmail.com',

    // MilesWeb PHP mail() fallback enabled
    'use_php_mail' => true,

    // Storage path for lead vault
    'storage_dir' => __DIR__ . '/logs'
];
