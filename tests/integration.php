<?php
declare(strict_types=1);

// Run only against an explicitly isolated local installation. No Stripe API calls occur.
if (getenv('RUN_LOCAL_INTEGRATION') !== '1') {
    fwrite(STDERR, "Set RUN_LOCAL_INTEGRATION=1 for the isolated local test database.\n");
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';
if (!str_starts_with((string) config('app.url'), 'http://127.0.0.1:') || checkout_enabled()) {
    throw new RuntimeException('Integration tests require localhost and disabled checkout.');
}

$checks = 0;
function verify(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

$http = curl_init();
curl_setopt_array($http, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
function request(string $path, ?array $fields = null, array $headers = [], ?string $raw = null): array
{
    global $http;
    curl_setopt($http, CURLOPT_URL, url('index.php' . $path));
    curl_setopt($http, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($http, CURLOPT_HTTPGET, true);
    if ($fields !== null || $raw !== null) {
        curl_setopt($http, CURLOPT_POST, true);
        curl_setopt($http, CURLOPT_POSTFIELDS, $raw ?? http_build_query($fields));
    }
    $response = curl_exec($http);
    if (!is_string($response)) {
        throw new RuntimeException('HTTP request failed.');
    }
    $size = (int) curl_getinfo($http, CURLINFO_HEADER_SIZE);
    return ['status' => (int) curl_getinfo($http, CURLINFO_RESPONSE_CODE), 'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
}
function token(): string
{
    $response = request('?page=contact');
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $response['body'], $match)) {
        throw new RuntimeException('CSRF field missing.');
    }
    return $match[1];
}
function post_action(string $action, array $data = []): array
{
    return request('', ['action' => $action, 'csrf_token' => token()] + $data);
}

$suffix = bin2hex(random_bytes(5));
$email = 'integration-' . $suffix . '@example.invalid';
$userId = null;
$orderIds = [];
$eventIds = [];
$initialStock = (int) query('SELECT stock FROM products WHERE id=1')->fetchColumn();
function fixture_order(int $uid, string $suffix): array
{
    global $orderIds;
    db()->beginTransaction();
    try {
        $product = query('SELECT * FROM products WHERE id=1 FOR UPDATE')->fetch();
        $total = (int) $product['price_cents'] + shipping_cents();
        query('INSERT INTO orders (order_number,user_id,total_cents,shipping_cents,vat_cents,vat_percent,shipping_name,address1,address2,postal_code,city,country,customer_email,seller_name,seller_address,seller_tax_id,checkout_key,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            'TEST-' . $suffix, $uid, $total, shipping_cents(), vat_cents($total, 19), 19,
            'Integration Test', 'Testweg 1', '', '10115', 'Berlin', 'DE', 'integration@example.invalid',
            'Local Test Fixture', 'Testweg 1', 'TEST', bin2hex(random_bytes(32)), gmdate('Y-m-d H:i:s', time() + 3600),
        ]);
        $id = (int) db()->lastInsertId();
        $orderIds[] = $id;
        query('INSERT INTO order_items (order_id,product_id,name,unit_price_cents,quantity,line_total) VALUES (?,?,?,?,?,?)',
            [$id, 1, $product['name'], $product['price_cents'], 1, $product['price_cents']]);
        query('UPDATE products SET stock=stock-1 WHERE id=1');
        db()->commit();
        return query('SELECT * FROM orders WHERE id=?', [$id])->fetch();
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
        throw $error;
    }
}
function webhook(array $order, string $type, string $payment = 'unpaid', int $amountOffset = 0, ?string $eventId = null): array
{
    global $eventIds;
    $id = $eventId ?? 'evt_local_' . bin2hex(random_bytes(8));
    $eventIds[] = $id;
    $event = ['id' => $id, 'type' => $type, 'data' => ['object' => [
        'object' => 'checkout.session', 'id' => 'cs_local_' . $order['id'], 'mode' => 'payment',
        'client_reference_id' => (string) $order['id'], 'currency' => 'eur',
        'amount_total' => (int) $order['total_cents'] + $amountOffset, 'payment_status' => $payment,
        'metadata' => ['order_id' => (string) $order['id'], 'checkout_key' => $order['checkout_key']],
    ]]];
    $payload = json_encode($event, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, (string) config('stripe.webhook_secret'));
    $response = request('?page=webhook', null, ['Content-Type: application/json', 'Stripe-Signature: t=' . $timestamp . ',v1=' . $signature], $payload);
    $response['event_id'] = $id;
    return $response;
}

try {
    foreach (['home', 'shop', 'gallery', 'contact', 'login', 'register', 'legal'] as $page) {
        verify(request('?page=' . $page)['status'] === 200, 'public page ' . $page);
    }
    verify(request('', ['action' => 'register'])['status'] === 303, 'missing CSRF rejected');
    $registration = ['name' => 'Integration Test', 'email' => $email, 'password' => 'LocalTestPass123!'];
    post_action('register', $registration);
    verify(!(bool) query('SELECT id FROM users WHERE email=?', [$email])->fetchColumn(), 'privacy consent enforced');
    $beforeCookies = curl_getinfo($http, CURLINFO_COOKIELIST);
    $registered = post_action('register', $registration + ['privacy' => '1']);
    $userId = (int) query('SELECT id FROM users WHERE email=?', [$email])->fetchColumn();
    verify($userId > 0 && str_contains($registered['headers'], 'page=account'), 'registration succeeds');
    verify($beforeCookies !== curl_getinfo($http, CURLINFO_COOKIELIST), 'session ID rotated after registration');
    $hash = query('SELECT password_hash FROM users WHERE id=?', [$userId])->fetchColumn();
    verify(password_verify('LocalTestPass123!', $hash), 'password is securely hashed');
    verify(request('?page=admin')['status'] === 403, 'customer cannot access admin');
    verify(post_action('cart_add', ['product_id' => 1, 'quantity' => 2])['status'] === 303, 'cart add');
    verify(str_contains(request('?page=cart')['body'], 'Spiral-Lampe'), 'cart includes product');
    $count = (int) query('SELECT COUNT(*) FROM orders')->fetchColumn();
    post_action('checkout', ['shipping_name' => 'Test', 'address1' => 'Testweg 1', 'address2' => '', 'postal_code' => '10115', 'city' => 'Berlin', 'country' => 'DE']);
    verify((int) query('SELECT COUNT(*) FROM orders')->fetchColumn() === $count, 'checkout consent enforced');
    post_action('checkout', ['terms' => '1', 'shipping_name' => 'Test', 'address1' => 'Testweg 1', 'address2' => '', 'postal_code' => '10115', 'city' => 'Berlin', 'country' => 'DE']);
    verify((int) query('SELECT COUNT(*) FROM orders')->fetchColumn() === $count, 'disabled Stripe never creates order');
    $order = fixture_order($userId, $suffix . 'A');
    verify((int) query('SELECT stock FROM products WHERE id=1')->fetchColumn() === $initialStock - 1, 'stock reservation');
    verify(request('?page=invoice&id=' . $order['id'])['status'] === 404, 'unpaid invoice blocked');
    verify(request('?page=webhook', null, ['Stripe-Signature: t=0,v1=bad'], '{}')['status'] === 400, 'invalid webhook signature rejected');
    verify(webhook($order, 'checkout.session.completed', 'paid', 1)['status'] === 500, 'amount mismatch rejected');
    verify(query('SELECT payment_status FROM orders WHERE id=?', [$order['id']])->fetchColumn() === 'pending', 'mismatch cannot mark paid');
    verify(webhook($order, 'checkout.session.completed')['status'] === 200, 'asynchronous completion accepted');
    verify(query('SELECT payment_status FROM orders WHERE id=?', [$order['id']])->fetchColumn() === 'pending', 'unpaid completed event remains pending');
    $paid = webhook($order, 'checkout.session.async_payment_succeeded', 'paid');
    verify($paid['status'] === 200, 'asynchronous paid webhook');
    verify(query('SELECT payment_status FROM orders WHERE id=?', [$order['id']])->fetchColumn() === 'paid', 'verified paid transition');
    $archivedPath = invoice_archive_path($order);
    verify(is_file($archivedPath), 'paid webhook automatically archives invoice');
    $archiveHash = hash_file('sha256', $archivedPath);
    verify(webhook($order, 'checkout.session.async_payment_succeeded', 'paid', 0, $paid['event_id'])['status'] === 200, 'duplicate webhook idempotent');
    verify(hash_file('sha256', $archivedPath) === $archiveHash, 'duplicate preserves archived PDF');
    verify((int) query('SELECT stock FROM products WHERE id=1')->fetchColumn() === $initialStock - 1, 'duplicate does not consume stock twice');
    $invoice = request('?page=invoice&id=' . $order['id']);
    verify($invoice['status'] === 200 && str_starts_with($invoice['body'], '%PDF-1.4'), 'owner PDF download');
    verify(hash('sha256', $invoice['body']) === $archiveHash, 'download serves identical archived PDF');
    unlink($archivedPath);
    $regenerated = request('?page=invoice&id=' . $order['id']);
    verify($regenerated['status'] === 200 && is_file($archivedPath) && hash_file('sha256', $archivedPath) === $archiveHash, 'missing archive regenerated deterministically');
    $expired = fixture_order($userId, $suffix . 'B');
    verify(webhook($expired, 'checkout.session.expired')['status'] === 200, 'expired webhook');
    verify((int) query('SELECT stock FROM products WHERE id=1')->fetchColumn() === $initialStock - 1, 'expired reservation restored once');
    verify(webhook($expired, 'checkout.session.expired')['status'] === 200, 'repeated expiration accepted');
    verify((int) query('SELECT stock FROM products WHERE id=1')->fetchColumn() === $initialStock - 1, 'repeated expiration cannot inflate stock');
    post_action('ticket_create', ['subject' => 'Local integration fixture', 'message' => 'Test-only message.']);
    $ticketId = (int) query('SELECT id FROM tickets WHERE user_id=?', [$userId])->fetchColumn();
    verify($ticketId > 0, 'customer ticket creation');
    verify(post_action('ticket_reply', ['ticket_id' => $ticketId, 'message' => 'Second test-only message.'])['status'] === 303, 'customer reply');
    verify((int) query('SELECT COUNT(*) FROM ticket_replies WHERE ticket_id=?', [$ticketId])->fetchColumn() === 2, 'ticket replies persisted');
    post_action('logout');
    verify(request('?page=invoice&id=' . $order['id'])['status'] === 303, 'guest invoice blocked');
    $logged = post_action('login', ['email' => $email, 'password' => 'LocalTestPass123!']);
    verify(str_contains($logged['headers'], 'page=account'), 'login after logout');
    verify((int) query('SELECT COUNT(*) FROM login_throttles')->fetchColumn() >= 2, 'durable login throttles persisted');
    echo "Local integration checks passed: $checks\n";
} finally {
    foreach (array_unique($eventIds) as $id) query('DELETE FROM stripe_events WHERE id=?', [$id]);
    foreach ($orderIds as $id) {
        $order = query('SELECT * FROM orders WHERE id=?', [$id])->fetch();
        if ($order && !(int) $order['reservation_released']) {
            query('UPDATE products SET stock=stock+1 WHERE id=1');
        }
        if ($order) {
            $archive = invoice_archive_path($order);
            if (is_file($archive)) unlink($archive);
            if (is_file($archive . '.lock')) unlink($archive . '.lock');
        }
        query('DELETE FROM order_items WHERE order_id=?', [$id]);
        query('DELETE FROM orders WHERE id=?', [$id]);
    }
    if ($userId) {
        query('DELETE r FROM ticket_replies r JOIN tickets t ON t.id=r.ticket_id WHERE t.user_id=?', [$userId]);
        query('DELETE FROM tickets WHERE user_id=?', [$userId]);
        query('DELETE FROM users WHERE id=?', [$userId]);
    }
    curl_close($http);
}
