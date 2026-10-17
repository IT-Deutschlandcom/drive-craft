<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

check(e('<script>"&') === '&lt;script&gt;&quot;&amp;', 'HTML escaping');
check(money(3490) === '34,90 €', 'EUR integer cents');
check(money(0) === '0,00 €', 'zero price');
check(vat_cents(11900, 19) === 1900, 'inclusive VAT');
check(vat_cents(100, 0) === 0, 'zero VAT');
check(count(catalog_preview()) === 4, 'read-only seeded catalog preview');
check(is_bool(checkout_enabled()), 'explicit checkout availability');
check(strong_password('LongPassword123!'), 'strong password');
check(!strong_password('short123A'), 'short password rejected');
check(!strong_password(str_repeat('A', 73)), 'bcrypt truncation avoided');
check(!strong_password('longpassword12345'), 'uppercase required');
check(with_invoice_number(['payment_status' => 'paid', 'paid_at' => '2026-10-01', 'order_number' => 'DC-TEST'])['invoice_number'] === 'DC-TEST', 'paid invoice number');
check(with_invoice_number(['payment_status' => 'pending', 'paid_at' => null, 'order_number' => 'DC-TEST'])['invoice_number'] === null, 'pending invoice unavailable');
$_POST = ['privacy' => '1'];
require_consent('privacy', 'Privacy required');
try {
    require_consent('terms', 'Terms required');
    check(false, 'missing terms should throw');
} catch (DomainException) {
    check(true, 'consent required server-side');
}

$_SESSION = [];
$token = csrf_token();
check(strlen($token) === 64 && valid_csrf($token), 'CSRF generation and verification');
check(!valid_csrf('wrong') && !valid_csrf([]), 'invalid CSRF rejected');
check(str_contains(csrf_field(), 'name="csrf_token"'), 'CSRF field contract');
flash('success', 'Hello');
check(take_flashes() === [['type' => 'success', 'message' => 'Hello']], 'flash retrieval');
check(take_flashes() === [], 'flash is consumed');
session_rate_limit('test', 1, 60);
try {
    session_rate_limit('test', 1, 60);
    check(false, 'rate limit should throw');
} catch (DomainException) {
    check(true, 'session rate limit');
}

$now = 1700000000;
$secret = 'whsec_unit_test_not_a_real_credential';
$payload = '{"id":"evt_unit","type":"checkout.session.completed"}';
$signature = hash_hmac('sha256', $now . '.' . $payload, $secret);
check(stripe_signature_valid($payload, "t=$now,v1=$signature", $secret, $now), 'valid Stripe signature');
check(stripe_signature_valid($payload, "t=$now,v1=" . str_repeat('0', 64) . ",v1=$signature", $secret, $now), 'rotated signatures');
check(!stripe_signature_valid($payload . ' ', "t=$now,v1=$signature", $secret, $now), 'payload tampering');
check(!stripe_signature_valid($payload, "t=$now,v1=$signature", $secret, $now + 301), 'replay tolerance');
check(!stripe_signature_valid($payload, "t=$now,v1=$signature", $secret, $now - 301), 'future timestamp tolerance');
check(!stripe_signature_valid($payload, "t=$now,v1=$signature", '', $now), 'empty webhook key disabled');
check(!stripe_signature_valid($payload, 'v1=' . $signature, $secret, $now), 'timestamp required');

$order = [
    'seller_name' => 'Drive Craft 3D', 'seller_address' => "Musterstraße 1\n10115 Berlin", 'seller_tax_id' => 'TEST',
    'order_number' => 'DC-UNIT', 'paid_at' => '2026-10-01 12:00:00', 'created_at' => '2026-10-01 11:00:00',
    'shipping_name' => 'Test (Kunde) \\ Beispiel', 'address1' => 'Teststraße 2', 'address2' => '',
    'postal_code' => '10115', 'city' => 'Berlin', 'country' => 'DE', 'total_cents' => 11900,
    'vat_cents' => 1900, 'vat_percent' => 19,
    'items' => [['name' => 'Produkt', 'unit_price_cents' => 5950, 'quantity' => 2, 'line_total' => 11900]],
];
$pdf = invoice_pdf($order);
check(str_starts_with($pdf, '%PDF-1.4'), 'PDF header');
check(str_ends_with($pdf, "%%EOF\n"), 'PDF terminator');
check(str_contains($pdf, '\\(Kunde\\) \\\\ Beispiel'), 'PDF injection escaping');
check(str_contains($pdf, '119,00'), 'invoice gross total');
preg_match('/startxref\n(\d+)\n/', $pdf, $match);
check(substr($pdf, (int) $match[1], 4) === 'xref', 'PDF xref location');
preg_match_all('/(\d{10}) 00000 n/', $pdf, $offsets);
foreach ($offsets[1] as $index => $offset) {
    check(str_starts_with(substr($pdf, (int) $offset), ($index + 1) . ' 0 obj'), 'PDF object byte offset');
}
$order['items'] = array_fill(0, 100, $order['items'][0]);
$pdf = invoice_pdf($order);
check(preg_match('/\/Type \/Pages .*\/Count [2-9]/', $pdf) === 1, 'multipage PDF');
check(!str_contains(pdf_string("bad\n(text)"), "\n"), 'PDF control characters removed');

echo "Backend helper checks passed: $checks\n";
