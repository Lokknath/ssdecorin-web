<?php
/**
 * Sai Sreenivasa Decorin — App Configuration
 * Configuration for lead notifications, transactional email & admin dashboard
 */

declare(strict_types=1);

return [
    // Primary recipient for new enquiries
    'admin_email' => 'loknath.surabhi@gmail.com',
    'admin_name'  => 'Sai Sreenivasa Decorin Admin',

    // Password to access https://ssdecorin.com/leads
    'leads_password' => 'Decorin@2026',

    // Transactional Email API (Resend.com - instant 2-second delivery)
    // Sign up for free at https://resend.com (3,000 free emails/mo) and paste your key here
    'resend_api_key' => getenv('RESEND_API_KEY') ?: '',

    // Telegram Instant Phone Alerts
    // 1. Message @BotFather on Telegram -> /newbot -> get TOKEN
    // 2. Message @userinfobot -> get your numerical Chat ID
    'telegram_bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'telegram_chat_id'   => getenv('TELEGRAM_CHAT_ID') ?: '',

    // MilesWeb PHP mail() fallback enabled
    'use_php_mail' => true,

    // Storage path for lead vault
    'storage_dir' => __DIR__ . '/logs'
];
