<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'Drive Craft 3D',
        // Public HTTPS URL, without a trailing slash; never derived from Host.
        'url' => 'https://drive-craft-3d.de',
        'timezone' => 'Europe/Berlin',
        'session_secure' => true,
        // Must be outside the IIS website/document root and writable by PHP.
        'private_dir' => dirname(__DIR__) . '/drive-craft-private',
        // Generate with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
        'key' => '',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'drive_craft',
        'user' => 'drive_craft',
        'password' => '',
    ],
    'shop' => [
        // Enable only after completing legal texts, company data and payment setup.
        'sales_enabled' => false,
        // Plain text supplied/reviewed by the shop owner; no legal text is generated.
        'legal_notice' => '',
        'privacy_text' => '',
        'terms_text' => '',
        'withdrawal_text' => '',
        // Flat-rate German shipping, gross EUR cents; included in the VAT calculation.
        'shipping_cents' => 490,
        'vat_percent' => 19,
        'currency' => 'eur',
        'contact_email' => 'info@drive-craft-3d.de',
        'mail_from' => 'info@drive-craft-3d.de',
    ],
    'company' => [
        'name' => 'Drive Craft 3D',
        'owner' => '',
        'address' => '',
        'email' => 'info@drive-craft-3d.de',
        'phone' => '',
        'register' => '',
        'vat_id' => '',
        'tax_id' => '',
        // Owner confirmation that imprint/privacy/withdrawal/terms are legally complete.
        'legal_complete' => false,
    ],
    'stripe' => [
        // Empty credentials disable checkout. Use test credentials only for testing.
        'secret_key' => '',
        'webhook_secret' => '',
        // Register checkout.session.completed, checkout.session.async_payment_succeeded,
        // checkout.session.async_payment_failed, checkout.session.expired.
    ],
];
