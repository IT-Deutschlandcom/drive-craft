<?php
declare(strict_types=1);

// This script is deliberately unavailable over HTTP, even on an unconfigured host.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/app/bootstrap.php';

try {
    if (!is_file(__DIR__ . '/config.php')) {
        throw new RuntimeException('Copy config.example.php to config.php and configure it first.');
    }
    if (strlen((string) config('app.key', '')) < 32) {
        throw new RuntimeException('Set app.key to a random secret of at least 32 characters.');
    }
    $directory = (string) config('app.private_dir', '');
    if ($directory === '') {
        throw new RuntimeException('Set app.private_dir outside the website root.');
    }
    $normalized = str_replace('\\', '/', $directory);
    if (!str_starts_with($normalized, '/') && !preg_match('/^[A-Za-z]:\//', $normalized)) {
        throw new RuntimeException('app.private_dir must be an absolute path.');
    }
    $parent = realpath(dirname($directory));
    $root = str_replace('\\', '/', (string) realpath(__DIR__));
    if (!$parent) {
        throw new RuntimeException('Create the parent of app.private_dir first.');
    }
    $resolved = str_replace('\\', '/', $parent) . '/' . basename($directory);
    if (str_starts_with(strtolower($resolved) . '/', strtolower($root) . '/')
        || (is_dir($directory) && str_starts_with(strtolower(str_replace('\\', '/', (string) realpath($directory))) . '/', strtolower($root) . '/'))) {
        throw new RuntimeException('Private storage must be outside the website root.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0700)) {
        throw new RuntimeException('Cannot create private storage.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Private storage is not writable.');
    }
    if (in_array('--reconcile', $argv, true)) {
        $orders = query("SELECT id,order_number,stripe_session_id FROM orders WHERE payment_status='pending' ORDER BY id")->fetchAll();
        foreach ($orders as $order) {
            if (!$order['stripe_session_id']) {
                fwrite(STDOUT, $order['order_number'] . ": no session attached; inspect Stripe and application logs; do not release stock blindly.\n");
                continue;
            }
            $session = stripe_request('GET', 'checkout/sessions/' . rawurlencode($order['stripe_session_id']));
            if (($session['status'] ?? '') === 'expired' && ($session['payment_status'] ?? '') !== 'paid') {
                db()->beginTransaction();
                try {
                    $locked = query('SELECT * FROM orders WHERE id=? FOR UPDATE', [$order['id']])->fetch();
                    release_reservation($locked, 'expired');
                    db()->commit();
                } catch (Throwable $error) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    throw $error;
                }
                fwrite(STDOUT, $order['order_number'] . ": expired reservation released.\n");
            } elseif (($session['payment_status'] ?? '') === 'paid') {
                fwrite(STDOUT, $order['order_number'] . ": paid in Stripe; resend its signed webhook in Stripe Dashboard to fulfill.\n");
            }
        }
        exit(0);
    }
    $email = strtolower(trim((string) getenv('ADMIN_EMAIL')));
    $password = (string) getenv('ADMIN_PASSWORD');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || !strong_password($password)) {
        throw new RuntimeException('Provide ADMIN_EMAIL and ADMIN_PASSWORD (12–72 characters, uppercase, lowercase, digit) via environment.');
    }
    foreach (['schema.sql', 'seed.sql'] as $filename) {
        $sql = file_get_contents(__DIR__ . '/database/' . $filename);
        if ($sql === false) {
            throw new RuntimeException('Cannot read schema.');
        }
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                db()->exec($statement);
            }
        }
    }
    // MySQL advisory locking prevents concurrent first-admin creation.
    $lock = (int) query("SELECT GET_LOCK('drive_craft_first_admin',10)")->fetchColumn();
    if ($lock !== 1) {
        throw new RuntimeException('Cannot acquire installer lock.');
    }
    try {
        if ((int) query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() > 0) {
            fwrite(STDOUT, "Schema/catalog ready; administrator already exists, credentials not changed.\n");
        } else {
            query("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'admin')", [
                'Administrator', $email, password_hash($password, PASSWORD_DEFAULT),
            ]);
            app_log('first_admin_created', ['user_id' => (int) db()->lastInsertId()]);
            fwrite(STDOUT, "Installation complete. First administrator created.\n");
        }
    } finally {
        query("SELECT RELEASE_LOCK('drive_craft_first_admin')");
    }
} catch (Throwable $error) {
    app_log('installer_failed', ['error_class' => get_class($error)]);
    // Configuration and PDO exceptions may contain connection details; never echo them.
    fwrite(STDERR, $error instanceof PDOException ? "Database operation failed; check configuration and DB permissions.\n" : $error->getMessage() . "\n");
    exit(1);
}
