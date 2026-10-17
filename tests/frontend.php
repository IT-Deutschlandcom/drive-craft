<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Template-only fixtures deliberately do not load the database or payment backend.
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/templates/layout.php';
require APP_ROOT . '/templates/public.php';
require APP_ROOT . '/templates/customer.php';
require APP_ROOT . '/templates/admin.php';
require APP_ROOT . '/templates/legal.php';

$GLOBALS['test_admin'] = true;
$GLOBALS['test_preview'] = false;
$GLOBALS['test_closed'] = false;
$GLOBALS['test_paid'] = true;
$GLOBALS['test_empty'] = false;
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function config(string $key, mixed $default = null): mixed { return $default; }
function current_user(): array { return ['id' => 1, 'name' => '<script>alert(1)</script>', 'email' => 'customer@example.invalid', 'role' => 'admin']; }
function is_admin(): bool { return $GLOBALS['test_admin']; }
function require_admin(): array { if (!is_admin()) throw new DomainException('Forbidden'); return current_user(); }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="test-only">'; }
function take_flashes(): array { return [['type' => 'error', 'message' => '<script>unsafe</script>']]; }
function money(int $cents): string { return number_format($cents / 100, 2, ',', '.') . ' €'; }
function cart_count(): int { return 2; }
function cart_total(): int { return 3980; }
function shipping_cents(): int { return 490; }
function checkout_enabled(): bool { return false; }
function products(bool $all = false): array {
    return $GLOBALS['test_empty'] ? [] : [[
        'id' => 1, 'name' => 'Design <script>unsafe</script>', 'category' => 'Deko',
        'image' => 'assets/images/lamp.svg', 'description' => '<img src=x onerror=alert(1)>',
        'price_cents' => 1990, 'stock' => 10, 'active' => 1, 'preview' => $GLOBALS['test_preview'],
    ]];
}
function product(int $id): ?array { return $id === 1 ? (products()[0] ?? null) : null; }
function gallery_items(bool $all = false): array {
    return $GLOBALS['test_empty'] ? [] : [['id' => 1, 'title' => 'Lampe <script>unsafe</script>', 'description' => 'Idee', 'image' => 'assets/images/lamp.svg', 'active' => 1, 'sort_order' => 0]];
}
function cart_items(): array { return array_map(static fn ($p) => $p + ['quantity' => 2], products()); }
function user_orders(int $id): array { return $GLOBALS['test_empty'] ? [] : [order_details(1)]; }
function order_details(int $id): ?array {
    if ($id !== 1) return null;
    return [
        'id' => 1, 'order_number' => 'TEST-1', 'user_id' => 1, 'status' => 'processing',
        'payment_status' => $GLOBALS['test_paid'] ? 'paid' : 'pending', 'created_at' => '2026-01-01',
        'total_cents' => 4470, 'shipping_cents' => 490, 'vat_percent' => 19, 'vat_cents' => 714,
        'shipping_name' => 'Test <script>unsafe</script>', 'address1' => 'Teststraße 1', 'address2' => '',
        'postal_code' => '12345', 'city' => 'Teststadt', 'customer_name' => 'Test',
        'items' => [['name' => 'Lampe <script>unsafe</script>', 'quantity' => 2, 'line_total' => 3980]],
    ];
}
function user_tickets(int $id): array { return $GLOBALS['test_empty'] ? [] : [ticket_details(1)]; }
function ticket_details(int $id): ?array {
    if ($id !== 1) return null;
    return [
        'id' => 1, 'user_id' => 1, 'subject' => 'Projekt <script>unsafe</script>',
        'status' => $GLOBALS['test_closed'] ? 'closed' : 'open', 'created_at' => '2026-01-01',
        'updated_at' => '2026-01-01', 'customer_name' => 'Test',
        'replies' => [['name' => 'Test', 'role' => 'admin', 'created_at' => '2026-01-01', 'message' => '<script>unsafe</script>']],
    ];
}
function admin_stats(): array { return ['products' => 1, 'orders' => 1, 'users' => 1, 'tickets' => 1, 'revenue_cents' => 4470]; }
function admin_orders(): array { return user_orders(1); }
function admin_users(): array { return [current_user() + ['created_at' => '2026-01-01']]; }
function admin_tickets(): array { return user_tickets(1); }
function contact_messages(): array { return [['subject' => 'Hallo', 'created_at' => '2026-01-01', 'name' => 'Test', 'email' => 'customer@example.invalid', 'mail_sent' => 0, 'message' => '<script>unsafe</script>']]; }

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function capture(callable $render): string {
    ob_start();
    $render();
    $html = (string) ob_get_clean();
    check(!str_contains($html, '<script>'), 'Unescaped script in template');
    check(!str_contains($html, '<img src=x'), 'Unescaped image in template');
    check($html !== '', 'Empty template');
    return $html;
}
set_error_handler(static function (int $level, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $level, $file, $line);
});

capture(static function (): void { render_header('Test', 'home'); render_footer(); });
foreach (['render_home', 'render_shop', 'render_gallery', 'render_about', 'render_contact', 'render_cart', 'render_checkout', 'render_account', 'render_tickets', 'render_legal'] as $render) capture($render);
capture(static fn () => render_auth(false));
capture(static fn () => render_auth(true));
capture(static fn () => render_product(1));
capture(static fn () => render_order(1));
capture(static fn () => render_ticket(1));
capture(static fn () => render_payment_result(false));
capture(static fn () => render_payment_result(true));
foreach (['dashboard', 'products', 'orders', 'gallery', 'tickets', 'users', 'contact'] as $tab) capture(static fn () => render_admin($tab, 1));

$checkout = capture('render_checkout');
foreach (['shipping_name', 'address1', 'address2', 'postal_code', 'city', 'country', 'terms', 'csrf_token'] as $field) check(str_contains($checkout, 'name="' . $field . '"'), 'Missing checkout field: ' . $field);
check(str_contains($checkout, '44,70'), 'Shipping missing from total');
check(str_contains(capture(static fn () => render_order(1)), 'page=invoice'), 'Paid invoice link absent');
$GLOBALS['test_paid'] = false;
check(!str_contains(capture(static fn () => render_order(1)), 'page=invoice'), 'Unpaid invoice link exposed');
$GLOBALS['test_preview'] = true;
check(!str_contains(capture(static fn () => render_product(1)), 'value="cart_add"'), 'Preview can be purchased');
$GLOBALS['test_closed'] = true;
check(!str_contains(capture(static fn () => render_ticket(1)), 'value="ticket_reply"'), 'Closed ticket accepts reply');
$GLOBALS['test_empty'] = true;
foreach (['render_home', 'render_shop', 'render_gallery', 'render_cart', 'render_account', 'render_tickets'] as $render) capture($render);
echo "Frontend: $checks checks passed.\n";
