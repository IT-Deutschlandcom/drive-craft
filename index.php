<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://checkout.stripe.com");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$titles = [
    'home' => 'Ihre Idee. Unser 3D-Druck.', 'shop' => 'Shop', 'product' => 'Produkt',
    'gallery' => 'Galerie', 'about' => 'Über uns', 'contact' => 'Kontakt', 'legal' => 'Rechtliches',
    'login' => 'Anmelden', 'register' => 'Konto erstellen', 'cart' => 'Warenkorb',
    'checkout' => 'Kasse', 'account' => 'Mein Konto', 'order' => 'Bestellung',
    'tickets' => 'Meine Anfragen', 'ticket' => 'Projektanfrage', 'admin' => 'Administration',
    'success' => 'Bestellung eingegangen', 'cancel' => 'Zahlung abgebrochen',
];

try {
    if ($page === 'webhook') handle_webhook();
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'POST'], true)) {
        header('Allow: GET, HEAD, POST');
        http_response_code(405);
        exit('Methode nicht erlaubt.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') handle_action();
    if (in_array($page, ['checkout', 'account', 'order', 'tickets', 'ticket', 'invoice', 'admin'], true) && !current_user()) {
        flash('info', 'Bitte melden Sie sich an, um Bestellungen und Projektanfragen zu verwalten.');
        redirect('login');
    }
    if ($page === 'admin' && !is_admin()) {
        http_response_code(403);
        $page = 'forbidden';
    }
    if ($page === 'invoice') serve_invoice($id);
    if (in_array($page, ['login', 'register'], true) && current_user()) redirect('account');

    require_once __DIR__ . '/templates/layout.php';
    require_once __DIR__ . '/templates/public.php';
    require_once __DIR__ . '/templates/customer.php';
    require_once __DIR__ . '/templates/admin.php';
    require_once __DIR__ . '/templates/legal.php';
    ob_start();
    render_header($titles[$page] ?? 'Seite nicht gefunden', $page);
    if (!config('db.name')) {
        echo '<div class="container pt-3"><div class="alert alert-info">Dieser Shop wird gerade eingerichtet. Produkte und Kundenfunktionen stehen nach der Datenbankinstallation bereit.</div></div>';
    }
    switch ($page) {
        case 'home': render_home(); break;
        case 'shop': render_shop(); break;
        case 'product': render_product($id); break;
        case 'gallery': render_gallery(); break;
        case 'about': render_about(); break;
        case 'contact': render_contact(); break;
        case 'legal': render_legal(); break;
        case 'login': render_auth(false); break;
        case 'register': render_auth(true); break;
        case 'cart': render_cart(); break;
        case 'checkout': render_checkout(); break;
        case 'account': render_account(); break;
        case 'order': render_order($id); break;
        case 'tickets': render_tickets(); break;
        case 'ticket': render_ticket($id); break;
        case 'admin':
            $tab = is_string($_GET['tab'] ?? null) ? $_GET['tab'] : 'dashboard';
            $edit = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            render_admin($tab, $edit);
            break;
        case 'success': render_payment_result(false); break;
        case 'cancel': render_payment_result(true); break;
        case 'forbidden': page_intro('KEIN ZUGRIFF', 'Dieser Bereich ist Administratoren vorbehalten.'); break;
        default: http_response_code(404); page_intro('404', 'Hier ist noch nichts gedruckt.', 'Die angefragte Seite wurde nicht gefunden.'); break;
    }
    render_footer();
    ob_end_flush();
} catch (Throwable $error) {
    if (ob_get_level()) ob_end_clean();
    app_log('request_failed', ['error_class' => get_class($error)]);
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Drive-Craft-3D · Wartung</title><link rel="stylesheet" href="assets/css/style.css"><body><main class="panel" style="max-width:650px;margin:10vh auto"><h1>Wir sind gleich wieder für Sie da.</h1><p>Der Shop ist momentan nicht erreichbar oder noch nicht vollständig eingerichtet. Bitte versuchen Sie es später erneut.</p><a href="mailto:info@drive-craft-3d.de">info@drive-craft-3d.de</a></main></body></html>';
}
