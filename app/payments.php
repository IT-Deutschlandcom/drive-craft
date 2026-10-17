<?php
declare(strict_types=1);

function checkout_enabled(): bool
{
    return config('db.name') && config('db.user')
        && config('shop.allow_sale', false) === true
        && config('company.legal_complete', false) === true
        && trim((string) config('shop.legal_notice', '')) !== ''
        && trim((string) config('shop.privacy_text', '')) !== ''
        && trim((string) config('shop.terms_text', '')) !== ''
        && trim((string) config('shop.withdrawal_text', '')) !== ''
        && config('company.owner', '') !== '' && config('company.address', '') !== ''
        && filter_var(config('company.email', ''), FILTER_VALIDATE_EMAIL) !== false
        && strlen((string) config('app.key', '')) >= 32
        && config('stripe.secret_key', '') !== '' && config('stripe.webhook_secret', '') !== ''
        && config('company.name', '') !== ''
        && (config('company.tax_id', '') !== '' || config('company.vat_id', '') !== '')
        && str_starts_with(url(), 'https://') && extension_loaded('curl');
}

function stripe_request(string $method, string $path, array $data = [], ?string $idempotencyKey = null): array
{
    if (!extension_loaded('curl')) {
        throw new RuntimeException('cURL unavailable.');
    }
    $handle = curl_init('https://api.stripe.com/v1/' . $path);
    $headers = ['Authorization: Bearer ' . (string) config('stripe.secret_key'), 'Stripe-Version: 2024-06-20'];
    if ($idempotencyKey !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    if ($method === 'POST') {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        app_log('stripe_api_error', ['http_status' => $status]);
        throw new RuntimeException('Der Zahlungsdienst ist gerade nicht erreichbar. Bitte versuche es später erneut.');
    }
    return $decoded;
}

function stripe_signature_valid(string $payload, string $header, string $secret, ?int $now = null): bool
{
    if ($secret === '' || strlen($header) > 4096) {
        return false;
    }
    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't' && ctype_digit($value)) {
            $timestamp = $value;
        } elseif ($key === 'v1' && preg_match('/^[a-f0-9]{64}$/D', $value)) {
            $signatures[] = $value;
        }
    }
    if ($timestamp === null || abs(($now ?? time()) - (int) $timestamp) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }
    return false;
}

function prepare_cart_change(): void
{
    synchronize_paid_cart();
    $id = (int) ($_SESSION['checkout_order_id'] ?? 0);
    if (!$id || empty($_SESSION['user_id'])) {
        return;
    }
    $order = query('SELECT * FROM orders WHERE id=? AND user_id=?', [$id, $_SESSION['user_id']])->fetch();
    if (!$order || $order['payment_status'] !== 'pending') {
        unset($_SESSION['checkout_order_id']);
        return;
    }
    if (!$order['stripe_session_id']) {
        throw new DomainException('Die bestehende Zahlung wird noch geprüft. Bitte kontaktiere uns, bevor du den Warenkorb änderst.');
    }
    // Never return stock merely because the browser visited the cancellation URL.
    $session = stripe_request('GET', 'checkout/sessions/' . rawurlencode($order['stripe_session_id']));
    if (($session['status'] ?? '') === 'open') {
        $session = stripe_request('POST', 'checkout/sessions/' . rawurlencode($order['stripe_session_id']) . '/expire');
    }
    if (($session['status'] ?? '') !== 'expired' || ($session['payment_status'] ?? '') === 'paid') {
        throw new DomainException('Die bestehende Zahlung wird verarbeitet. Bitte warte auf die Zahlungsbestätigung.');
    }
    db()->beginTransaction();
    try {
        $locked = query('SELECT * FROM orders WHERE id=? FOR UPDATE', [$id])->fetch();
        release_reservation($locked, 'expired');
        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
    unset($_SESSION['checkout_order_id']);
}

function checkout_create(array $user, array $address): string
{
    synchronize_paid_cart();
    if (!checkout_enabled()) {
        throw new DomainException('Online-Zahlungen sind derzeit nicht verfügbar. Bitte kontaktiere uns.');
    }
    $orderId = (int) ($_SESSION['checkout_order_id'] ?? 0);
    $order = $orderId ? query("SELECT * FROM orders WHERE id=? AND user_id=? AND payment_status='pending'", [$orderId, $user['id']])->fetch() : false;
    if ($order) {
        foreach ($address as $field => $value) {
            if ((string) $order[$field] !== (string) $value) {
                prepare_cart_change();
                $order = false;
                break;
            }
        }
    }
    if (!$order) {
        $cart = (array) ($_SESSION['cart'] ?? []);
        if (!$cart || count($cart) > 50) {
            throw new DomainException('Dein Warenkorb ist leer oder zu groß.');
        }
        ksort($cart, SORT_NUMERIC);
        db()->beginTransaction();
        try {
            $items = [];
            $total = 0;
            foreach ($cart as $id => $quantity) {
                $quantity = (int) $quantity;
                $item = query('SELECT * FROM products WHERE id=? FOR UPDATE', [(int) $id])->fetch();
                if (!$item || !$item['active'] || $quantity < 1 || $quantity > 99 || (int) $item['stock'] < $quantity) {
                    throw new DomainException('Ein Artikel ist nicht mehr in der gewünschten Menge verfügbar.');
                }
                $lineTotal = (int) $item['price_cents'] * $quantity;
                $total += $lineTotal;
                $items[] = [$item, $quantity, $lineTotal];
            }
            $shipping = shipping_cents();
            $total += $shipping;
            if ($total < 50 || $total > 99999999) {
                throw new DomainException('Der Bestellwert liegt außerhalb des zulässigen Bereichs.');
            }
            $vat = (float) config('shop.vat_percent', 19);
            if ($vat < 0 || $vat > 100) {
                throw new RuntimeException('Invalid VAT configuration.');
            }
            $number = 'DC-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
            query('INSERT INTO orders (order_number,user_id,total_cents,shipping_cents,vat_cents,vat_percent,shipping_name,address1,address2,postal_code,city,country,customer_email,seller_name,seller_address,seller_tax_id,checkout_key,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $number, $user['id'], $total, $shipping, vat_cents($total, $vat), $vat,
                $address['shipping_name'], $address['address1'], $address['address2'], $address['postal_code'], $address['city'], $address['country'],
                $user['email'], config('company.name', 'Drive Craft 3D'), config('company.address'),
                implode(' / ', array_filter([(string) config('company.tax_id', ''), (string) config('company.vat_id', '')])),
                bin2hex(random_bytes(32)), gmdate('Y-m-d H:i:s', time() + 3600),
            ]);
            $orderId = (int) db()->lastInsertId();
            foreach ($items as [$item, $quantity, $lineTotal]) {
                query('INSERT INTO order_items (order_id,product_id,name,unit_price_cents,quantity,line_total) VALUES (?,?,?,?,?,?)', [$orderId, $item['id'], $item['name'], $item['price_cents'], $quantity, $lineTotal]);
                query('UPDATE products SET stock=stock-? WHERE id=?', [$quantity, $item['id']]);
            }
            db()->commit();
            $_SESSION['checkout_order_id'] = $orderId;
            $order = query('SELECT * FROM orders WHERE id=?', [$orderId])->fetch();
        } catch (Throwable $error) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $error;
        }
    }
    if (!empty($order['stripe_session_id'])) {
        $session = stripe_request('GET', 'checkout/sessions/' . rawurlencode($order['stripe_session_id']));
    } else {
        // An uncertain network result must retain stock, and retries must reuse the same key and payload.
        if (strtotime($order['expires_at'] . ' UTC') < time() + 1800) {
            throw new DomainException('Diese Zahlung wird noch geprüft. Bitte kontaktiere uns mit deiner Bestellnummer ' . $order['order_number'] . '.');
        }
        $items = query('SELECT * FROM order_items WHERE order_id=? ORDER BY id', [$orderId])->fetchAll();
        $lines = [];
        foreach ($items as $item) {
            $lines[] = ['price_data' => ['currency' => 'eur', 'unit_amount' => (int) $item['unit_price_cents'], 'product_data' => ['name' => $item['name']]], 'quantity' => (int) $item['quantity']];
        }
        if ((int) $order['shipping_cents'] > 0) {
            $lines[] = ['price_data' => ['currency' => 'eur', 'unit_amount' => (int) $order['shipping_cents'], 'product_data' => ['name' => 'Versand Deutschland']], 'quantity' => 1];
        }
        $session = stripe_request('POST', 'checkout/sessions', [
            'mode' => 'payment', 'client_reference_id' => (string) $orderId,
            'customer_email' => $order['customer_email'],
            'metadata' => ['order_id' => (string) $orderId, 'checkout_key' => $order['checkout_key']],
            'line_items' => $lines,
            'expires_at' => strtotime($order['expires_at'] . ' UTC'),
            'success_url' => url('index.php?page=success&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('index.php?page=cart'),
        ], $order['checkout_key']);
        if (!is_string($session['id'] ?? null)) {
            throw new RuntimeException('Invalid Stripe response.');
        }
        query('UPDATE orders SET stripe_session_id=? WHERE id=? AND stripe_session_id IS NULL', [$session['id'], $orderId]);
    }
    $target = $session['url'] ?? '';
    if (($session['status'] ?? '') !== 'open' || !is_string($target)
        || parse_url($target, PHP_URL_SCHEME) !== 'https' || parse_url($target, PHP_URL_HOST) !== 'checkout.stripe.com') {
        throw new DomainException('Diese Zahlung ist nicht mehr offen. Den aktuellen Stand findest du in deinen Bestellungen.');
    }
    return $target;
}

function release_reservation(array $order, string $paymentStatus): void
{
    if ($order['payment_status'] === 'paid' || (int) $order['reservation_released'] === 1) {
        return;
    }
    $items = query('SELECT product_id,quantity FROM order_items WHERE order_id=? ORDER BY product_id', [$order['id']])->fetchAll();
    foreach ($items as $item) {
        query('UPDATE products SET stock=stock+? WHERE id=?', [$item['quantity'], $item['product_id']]);
    }
    query("UPDATE orders SET reservation_released=1,payment_status=?,status='cancelled' WHERE id=?", [$paymentStatus, $order['id']]);
}

function process_stripe_event(array $event): void
{
    $supported = ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired'];
    if (!in_array($event['type'] ?? '', $supported, true)) {
        return;
    }
    $session = $event['data']['object'] ?? [];
    if (!is_string($event['id'] ?? null) || !str_starts_with($event['id'], 'evt_')
        || !is_string($session['id'] ?? null) || ($session['object'] ?? '') !== 'checkout.session') {
        throw new RuntimeException('Invalid event structure.');
    }
    db()->beginTransaction();
    try {
        $insert = query('INSERT IGNORE INTO stripe_events (id,event_type) VALUES (?,?)', [$event['id'], $event['type']]);
        if ($insert->rowCount() === 0) {
            $existingOrderId = query('SELECT order_id FROM stripe_events WHERE id=?', [$event['id']])->fetchColumn();
            db()->commit();
            if ($existingOrderId && query("SELECT id FROM orders WHERE id=? AND payment_status='paid'", [$existingOrderId])->fetchColumn()) {
                ensure_invoice_archived((int) $existingOrderId);
            }
            return;
        }
        $id = filter_var($session['metadata']['order_id'] ?? '', FILTER_VALIDATE_INT);
        $order = $id ? query('SELECT * FROM orders WHERE id=? FOR UPDATE', [$id])->fetch() : false;
        if (!$order || !is_string($session['metadata']['checkout_key'] ?? null)
            || !hash_equals($order['checkout_key'], $session['metadata']['checkout_key'])
            || ($order['stripe_session_id'] !== null && $order['stripe_session_id'] !== $session['id'])
            || (string) ($session['client_reference_id'] ?? '') !== (string) $order['id']
            || ($session['mode'] ?? '') !== 'payment'
            || ($session['currency'] ?? '') !== 'eur'
            || !is_int($session['amount_total'] ?? null) || $session['amount_total'] !== (int) $order['total_cents']) {
            throw new RuntimeException('Payment/order mismatch.');
        }
        query('UPDATE stripe_events SET order_id=? WHERE id=?', [$id, $event['id']]);
        query('UPDATE orders SET stripe_session_id=? WHERE id=?', [$session['id'], $id]);
        $paidEvent = in_array($event['type'], ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && ($session['payment_status'] ?? '') === 'paid';
        if ($paidEvent && $order['payment_status'] !== 'paid') {
            if ((int) $order['reservation_released'] === 1) {
                throw new RuntimeException('Paid order reservation was already released; manual reconciliation required.');
            }
            query("UPDATE orders SET payment_status='paid',status='processing',paid_at=UTC_TIMESTAMP(),stripe_payment_intent=? WHERE id=?", [
                is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null, $id,
            ]);
        } elseif ($event['type'] === 'checkout.session.expired') {
            release_reservation($order, 'expired');
        } elseif ($event['type'] === 'checkout.session.async_payment_failed') {
            release_reservation($order, 'failed');
        }
        db()->commit();
        app_log('stripe_event_processed', ['event_id' => $event['id'], 'order_id' => $id]);
        if (query("SELECT id FROM orders WHERE id=? AND payment_status='paid'", [$id])->fetchColumn()) {
            ensure_invoice_archived((int) $id);
        }
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
}

function handle_webhook(): never
{
    header('Content-Type: application/json; charset=utf-8');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo '{"error":"Method not allowed"}';
        exit;
    }
    $payload = file_get_contents('php://input', false, null, 0, 1048577);
    if (!is_string($payload) || strlen($payload) > 1048576
        || !stripe_signature_valid($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), (string) config('stripe.webhook_secret', ''))) {
        http_response_code(400);
        echo '{"error":"Invalid signature"}';
        exit;
    }
    try {
        $event = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($event)) {
            throw new RuntimeException('Invalid JSON event.');
        }
        process_stripe_event($event);
        echo '{"received":true}';
    } catch (Throwable $error) {
        $eventId = isset($event) && is_array($event) && is_string($event['id'] ?? null)
            && preg_match('/^evt_[A-Za-z0-9_]{1,240}$/D', $event['id']) ? $event['id'] : null;
        app_log('webhook_failed', ['event_id' => $eventId, 'error_class' => get_class($error)]);
        http_response_code(500);
        echo '{"error":"Processing failed"}';
    }
    exit;
}
