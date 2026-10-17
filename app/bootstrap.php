<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$configurationFile = APP_ROOT . '/config.php';
$GLOBALS['app_config'] = is_file($configurationFile) ? require $configurationFile : [];
if (!is_array($GLOBALS['app_config'])) {
    throw new RuntimeException('Invalid configuration.');
}
date_default_timezone_set((string) config('app.timezone', 'Europe/Berlin'));
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name('drivecraft_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => session_cookie_secure(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
}
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/invoice.php';
require_once __DIR__ . '/actions.php';

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['app_config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function session_cookie_secure(): bool
{
    return (bool) (config('app.session_secure')
        ?? (parse_url((string) config('app.url', 'http://localhost'), PHP_URL_SCHEME) !== 'http'));
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        if (!config('db.name') || !config('db.user')) {
            throw new RuntimeException('Der Shop ist noch nicht eingerichtet.');
        }
        $pdo = new PDO(
            'mysql:host=' . config('db.host', '127.0.0.1') . ';port=' . (int) config('db.port', 3306)
            . ';dbname=' . config('db.name') . ';charset=utf8mb4',
            (string) config('db.user'), (string) config('db.password'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string) config('app.url', ''), '/') . '/' . ltrim($path, '/');
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url('index.php?' . http_build_query(['page' => $page] + $params)), true, 303);
    exit;
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if (!$loaded) {
        $loaded = true;
        if (config('db.name') && !empty($_SESSION['user_id'])) {
            $user = query('SELECT id,name,email,role,created_at FROM users WHERE id=?', [$_SESSION['user_id']])->fetch() ?: null;
        }
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        throw new DomainException('Bitte melde dich zuerst an.');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_user();
    if ($user['role'] !== 'admin') {
        throw new DomainException('Zugriff verweigert.');
    }
    return $user;
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function valid_csrf(mixed $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $messages;
}

function money(int $cents): string
{
    return number_format($cents / 100, 2, ',', '.') . ' €';
}

function vat_cents(int $gross, float $percent): int
{
    return (int) round($gross * $percent / (100 + $percent));
}

function products(bool $includeInactive = false): array
{
    if ($includeInactive) {
        require_admin();
    }
    if (!config('db.name')) {
        return catalog_preview();
    }
    return query('SELECT * FROM products' . ($includeInactive ? '' : ' WHERE active=1') . ' ORDER BY id')->fetchAll();
}

function product(int $id): ?array
{
    if (!config('db.name')) {
        foreach (catalog_preview() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }
        return null;
    }
    return query('SELECT * FROM products WHERE id=? AND active=1', [$id])->fetch() ?: null;
}

function gallery_items(bool $includeInactive = false): array
{
    if ($includeInactive) {
        require_admin();
    }
    if (!config('db.name')) {
        return array_map(static fn (array $item): array => ['id' => $item['id'], 'title' => $item['name'],
            'description' => $item['description'], 'image' => $item['image'], 'sort_order' => $item['id'] * 10,
            'active' => 1, 'preview' => true], catalog_preview());
    }
    return query('SELECT * FROM gallery' . ($includeInactive ? '' : ' WHERE active=1') . ' ORDER BY sort_order,id')->fetchAll();
}

function cart_items(): array
{
    synchronize_paid_cart();
    $items = [];
    foreach ((array) ($_SESSION['cart'] ?? []) as $id => $quantity) {
        $item = product((int) $id);
        if ($item) {
            $item['quantity'] = max(1, min(99, (int) $quantity));
            $item['line_total'] = (int) $item['price_cents'] * $item['quantity'];
            $items[] = $item;
        }
    }
    return $items;
}

function synchronize_paid_cart(): void
{
    $id = (int) ($_SESSION['checkout_order_id'] ?? 0);
    if (!config('db.name') || !$id || empty($_SESSION['user_id'])) {
        return;
    }
    $order = query('SELECT payment_status FROM orders WHERE id=? AND user_id=?', [$id, $_SESSION['user_id']])->fetch();
    if ($order && $order['payment_status'] === 'paid') {
        foreach (query('SELECT product_id,quantity FROM order_items WHERE order_id=?', [$id])->fetchAll() as $item) {
            $left = (int) ($_SESSION['cart'][$item['product_id']] ?? 0) - (int) $item['quantity'];
            if ($left > 0) {
                $_SESSION['cart'][$item['product_id']] = $left;
            } else {
                unset($_SESSION['cart'][$item['product_id']]);
            }
        }
        unset($_SESSION['checkout_order_id']);
    }
}

function cart_count(): int
{
    return array_sum(array_column(cart_items(), 'quantity'));
}

function cart_total(): int
{
    return array_sum(array_column(cart_items(), 'line_total'));
}

function shipping_cents(): int
{
    return max(0, min(100000, (int) config('shop.shipping_cents', 490)));
}

function checkout_total(): int
{
    $subtotal = cart_total();
    return $subtotal > 0 ? $subtotal + shipping_cents() : 0;
}

function user_orders(int $uid): array
{
    $user = require_user();
    if ((int) $user['id'] !== $uid && !is_admin()) {
        throw new DomainException('Zugriff verweigert.');
    }
    return array_map('with_invoice_number', query('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC', [$uid])->fetchAll());
}

function with_invoice_number(array $order): array
{
    $order['invoice_number'] = $order['payment_status'] === 'paid' && !empty($order['paid_at'])
        ? $order['order_number'] : null;
    return $order;
}

function order_details(int $id): ?array
{
    $user = require_user();
    $order = query('SELECT * FROM orders WHERE id=?', [$id])->fetch();
    if (!$order || ((int) $order['user_id'] !== (int) $user['id'] && !is_admin())) {
        return null;
    }
    $order['items'] = query('SELECT * FROM order_items WHERE order_id=? ORDER BY id', [$id])->fetchAll();
    return with_invoice_number($order);
}

function user_tickets(int $uid): array
{
    $user = require_user();
    if ((int) $user['id'] !== $uid && !is_admin()) {
        throw new DomainException('Zugriff verweigert.');
    }
    return query('SELECT * FROM tickets WHERE user_id=? ORDER BY updated_at DESC', [$uid])->fetchAll();
}

function ticket_details(int $id): ?array
{
    $user = require_user();
    $ticket = query('SELECT * FROM tickets WHERE id=?', [$id])->fetch();
    if (!$ticket || ((int) $ticket['user_id'] !== (int) $user['id'] && !is_admin())) {
        return null;
    }
    $ticket['replies'] = query('SELECT r.*,u.name,u.role FROM ticket_replies r JOIN users u ON u.id=r.user_id WHERE ticket_id=? ORDER BY r.id', [$id])->fetchAll();
    return $ticket;
}

function admin_stats(): array
{
    require_admin();
    return [
        'products' => (int) query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'orders' => (int) query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'users' => (int) query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'tickets' => (int) query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn(),
        'revenue_cents' => (int) query("SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE payment_status='paid'")->fetchColumn(),
    ];
}

function admin_orders(): array
{
    require_admin();
    return array_map('with_invoice_number', query('SELECT o.*,u.name AS customer_name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 500')->fetchAll());
}

function admin_users(): array
{
    require_admin();
    return query('SELECT id,name,email,role,created_at FROM users ORDER BY id DESC LIMIT 500')->fetchAll();
}

function admin_tickets(): array
{
    require_admin();
    return query('SELECT t.*,u.name AS customer_name,u.email FROM tickets t JOIN users u ON u.id=t.user_id ORDER BY t.updated_at DESC LIMIT 500')->fetchAll();
}

function contact_messages(): array
{
    require_admin();
    return query('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 500')->fetchAll();
}

function app_log(string $event, array $context = []): void
{
    // Call sites pass only internal IDs/error classes, never request bodies or credentials.
    $directory = (string) config('app.private_dir', '');
    $real = $directory !== '' ? realpath($directory) : false;
    $root = realpath(APP_ROOT);
    if (!$real || !$root || str_starts_with(strtolower(str_replace('\\', '/', $real)) . '/', strtolower(str_replace('\\', '/', $root)) . '/')) {
        error_log('DriveCraft: private logging directory unavailable');
        return;
    }
    $record = json_encode(['time' => gmdate('c'), 'event' => $event, 'context' => $context], JSON_UNESCAPED_SLASHES);
    if ($record !== false && file_put_contents($real . '/application.log', $record . "\n", FILE_APPEND | LOCK_EX) === false) {
        error_log('DriveCraft: application log write failed');
    }
}

function session_rate_limit(string $bucket, int $limit, int $seconds): void
{
    $now = time();
    $times = array_filter($_SESSION['rate'][$bucket] ?? [], static fn ($time) => $time > $now - $seconds);
    if (count($times) >= $limit) {
        throw new DomainException('Zu viele Anfragen. Bitte versuche es später erneut.');
    }
    $times[] = $now;
    $_SESSION['rate'][$bucket] = $times;
}
