<?php
declare(strict_types=1);
function status_label(string $status): string
{
    return [
        'pending' => 'Zahlung ausstehend', 'paid' => 'Bezahlt', 'processing' => 'In Bearbeitung', 'printing' => 'In Fertigung',
        'shipped' => 'Versendet', 'completed' => 'Abgeschlossen', 'cancelled' => 'Storniert',
        'expired' => 'Zahlung abgelaufen', 'failed' => 'Zahlung fehlgeschlagen', 'refunded' => 'Erstattet', 'open' => 'Offen',
        'answered' => 'Beantwortet', 'closed' => 'Geschlossen',
    ][$status] ?? $status;
}

function render_auth(bool $register): void
{
    ?>
<div class="container"><section class="panel auth-panel"><span class="eyebrow"><?= $register ? 'PLATZ FÜR IHRE IDEEN' : 'SCHÖN, DASS SIE DA SIND' ?></span><h1 class="h2"><?= $register ? 'Konto erstellen.' : 'Willkommen zurück.' ?></h1><p class="text-muted-dc small"><?= $register ? 'Bestellungen verfolgen, Rechnungen laden und eigene Projekte anfragen.' : 'Ihr Konto, Ihre Bestellungen, Ihre Projekte.' ?></p>
<form method="post" data-once><?= csrf_field() ?><input type="hidden" name="action" value="<?= $register ? 'register' : 'login' ?>">
  <?php if ($register): ?><div class="mb-3"><label class="form-label" for="name">Ihr Name</label><input class="form-control" name="name" id="name" maxlength="120" required autocomplete="name"></div><?php endif; ?>
  <div class="mb-3"><label class="form-label" for="email">E-Mail-Adresse</label><input class="form-control" id="email" name="email" type="email" maxlength="190" required autocomplete="email"></div>
  <div class="mb-3"><label class="form-label" for="password">Passwort</label><input class="form-control" id="password" name="password" type="password" <?= $register ? 'minlength="12"' : '' ?> maxlength="72" required autocomplete="<?= $register ? 'new-password' : 'current-password' ?>"><?php if ($register): ?><div class="form-text">12–72 Zeichen mit Groß- und Kleinbuchstaben sowie einer Zahl. Verwenden Sie ein einzigartiges Passwort.</div><?php endif; ?></div>
  <?php if ($register): ?><div class="mb-4 form-check"><input class="form-check-input" id="privacy" type="checkbox" name="privacy" value="1" required><label class="form-check-label small" for="privacy">Ich habe die <a class="text-decoration-underline" href="?page=legal#privacy">Datenschutzhinweise</a> gelesen.</label></div><?php endif; ?>
  <button class="btn btn-accent w-100" type="submit"><?= $register ? 'Konto erstellen' : 'Anmelden' ?> ↗</button>
</form>
<p class="small text-muted-dc mt-4 mb-0"><?= $register ? 'Schon ein Konto?' : 'Noch kein Konto?' ?> <a class="text-decoration-underline" href="?page=<?= $register ? 'login' : 'register' ?>"><?= $register ? 'Jetzt anmelden' : 'Jetzt registrieren' ?></a></p>
</section></div>
<?php
}

function render_cart(): void
{
    page_intro('IHRE AUSWAHL', 'Platz für Lieblingsstücke.');
    $items = cart_items();
    if (!$items) {
        echo '<div class="container pb-5"><div class="empty-state"><h2 class="h4">Hier ist noch Platz für Ihre Ideen.</h2><p>Entdecken Sie unsere Designs und finden Sie Ihr nächstes Lieblingsstück.</p><a class="btn btn-accent" href="?page=shop">Zum Shop ↗</a></div></div>';
        return;
    }
    ?>
<section class="container pb-5"><div class="row g-4"><div class="col-lg-8"><div class="panel">
<?php foreach ($items as $item): ?>
  <div class="d-flex gap-3 py-3 border-bottom">
    <img class="cart-thumb" src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" width="75" height="75">
    <div class="flex-grow-1"><a class="fw-semibold" href="?page=product&amp;id=<?= (int) $item['id'] ?>"><?= e($item['name']) ?></a><div class="small text-muted-dc"><?= money((int) $item['price_cents']) ?> / Stück</div>
      <div class="d-flex flex-wrap gap-3 mt-2"><form method="post" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="action" value="cart_update"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><label class="visually-hidden" for="qty-<?= (int) $item['id'] ?>">Menge für <?= e($item['name']) ?></label><input class="form-control py-1" style="width:75px" type="number" name="quantity" id="qty-<?= (int) $item['id'] ?>" value="<?= (int) $item['quantity'] ?>" min="1" max="99" required><button class="btn btn-outline py-1 px-2" type="submit">Ändern</button></form><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cart_remove"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><button type="submit" class="btn btn-outline py-1 px-2">Entfernen</button></form></div>
    </div><strong class="small text-nowrap"><?= money((int) $item['price_cents'] * (int) $item['quantity']) ?></strong>
  </div>
<?php endforeach; ?>
<a href="?page=shop" class="text-link d-inline-block mt-4">← Weiter entdecken</a></div></div>
<div class="col-lg-4"><div class="panel"><h2 class="h4 mb-4">Ihre Bestellung</h2><?php render_summary(); ?><a class="btn btn-accent w-100 mt-3" href="?page=checkout">Zur Kasse ↗</a><p class="form-text mt-3 mb-0">Sichere Bezahlung über Stripe. Ein Kundenkonto ist für Bestellungen erforderlich.</p></div></div></div></section>
<?php
}

function render_summary(): void
{
    $subtotal = cart_total();
    $shipping = $subtotal > 0 ? shipping_cents() : 0;
    ?>
<div class="summary-line"><span>Artikel</span><span><?= money($subtotal) ?></span></div>
<div class="summary-line"><span>Versand (Deutschland)</span><span><?= money($shipping) ?></span></div>
<div class="summary-line summary-total"><strong>Gesamt</strong><strong><?= money($subtotal + $shipping) ?></strong></div>
<div class="form-text">Inklusive <?= e((string) config('shop.vat_percent', 19)) ?> % Umsatzsteuer.</div>
<?php
}

function render_checkout(): void
{
    if (!cart_items()) { render_cart(); return; }
    $user = current_user();
    page_intro('FAST GESCHAFFT', 'Ein Schritt näher zum Unikat.', 'Lieferung innerhalb Deutschlands. Rechnungs- und Lieferadresse sind identisch.');
    ?>
<section class="container pb-5"><form method="post" class="row g-4" data-once><?= csrf_field() ?><input type="hidden" name="action" value="checkout">
  <div class="col-lg-7"><div class="panel"><h2 class="h4 mb-4">Rechnungs- &amp; Lieferadresse</h2><div class="row g-3">
    <div class="col-12"><label class="form-label" for="shipping_name">Vollständiger Name</label><input class="form-control" name="shipping_name" id="shipping_name" value="<?= e($user['name']) ?>" maxlength="120" autocomplete="name" required></div>
    <div class="col-12"><label class="form-label" for="address1">Straße und Hausnummer</label><input class="form-control" name="address1" id="address1" maxlength="160" autocomplete="address-line1" required></div>
    <div class="col-12"><label class="form-label" for="address2">Adresszusatz (optional)</label><input class="form-control" name="address2" id="address2" maxlength="160" autocomplete="address-line2"></div>
    <div class="col-sm-4"><label class="form-label" for="postal_code">Postleitzahl</label><input class="form-control" name="postal_code" id="postal_code" pattern="[0-9]{5}" maxlength="5" autocomplete="postal-code" inputmode="numeric" required></div>
    <div class="col-sm-8"><label class="form-label" for="city">Ort</label><input class="form-control" name="city" id="city" maxlength="100" autocomplete="address-level2" required></div>
    <div class="col-12"><label class="form-label" for="country">Land</label><select class="form-select" id="country" name="country" autocomplete="country"><option value="DE">Deutschland</option></select></div>
  </div></div></div>
  <div class="col-lg-5"><div class="panel"><h2 class="h4 mb-4">Bestellübersicht</h2>
    <?php foreach (cart_items() as $item): ?><div class="summary-line"><span><?= (int) $item['quantity'] ?> × <?= e($item['name']) ?></span><span class="text-nowrap"><?= money((int) $item['price_cents'] * (int) $item['quantity']) ?></span></div><?php endforeach; ?>
    <hr><?php render_summary(); ?>
    <div class="form-check my-4"><input class="form-check-input" type="checkbox" id="terms" name="terms" value="1" required><label class="form-check-label small" for="terms">Ich akzeptiere die <a href="?page=legal#terms" class="text-decoration-underline">AGB</a> und habe die <a href="?page=legal#withdrawal" class="text-decoration-underline">Widerrufsbelehrung</a> sowie die <a href="?page=legal#privacy" class="text-decoration-underline">Datenschutzhinweise</a> gelesen.</label></div>
    <button class="btn btn-accent w-100" type="submit">Zahlungspflichtig bestellen</button><p class="form-text mt-3 mb-0">Weiterleitung zu Stripe zur sicheren Zahlung. Die Bestellung wird erst nach bestätigter Zahlung zur Fertigung freigegeben.</p>
  </div></div>
</form></section>
<?php
}

function render_account(): void
{
    $user = current_user();
    page_intro('IHR KUNDENBEREICH', 'Hallo, ' . $user['name'] . '.', 'Ihre Bestellungen und Projekte – alles an einem Ort.');
    ?>
<section class="container pb-5"><div class="d-flex flex-wrap gap-3 mb-4"><a class="btn btn-accent" href="?page=tickets">Meine Projektanfragen ↗</a><?php if (is_admin()): ?><a class="btn btn-outline" href="?page=admin">Administration</a><?php endif; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="btn btn-outline" type="submit">Abmelden</button></form></div>
<div class="panel"><h2 class="h4 mb-4">Meine Bestellungen</h2><?php $orders = user_orders((int) $user['id']); if (!$orders): ?><p class="text-muted-dc">Noch keine Bestellungen. Ihr nächstes Lieblingsstück wartet im <a class="text-decoration-underline" href="?page=shop">Shop</a>.</p><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Bestellung</th><th>Datum</th><th>Status</th><th>Gesamt</th><th>Details</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td>#<?= (int) $order['id'] ?></td><td><?= e($order['created_at']) ?></td><td><span class="status"><?= e(status_label($order['status'])) ?></span></td><td><?= money((int) $order['total_cents']) ?></td><td><a class="text-link" href="?page=order&amp;id=<?= (int) $order['id'] ?>">Ansehen ↗</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
<?php
}

function render_order(int $id): void
{
    $order = order_details($id);
    if (!$order || ((int) $order['user_id'] !== (int) current_user()['id'] && !is_admin())) { http_response_code(404); page_intro('NICHT GEFUNDEN', 'Bestellung nicht gefunden.'); return; }
    page_intro('IHRE BESTELLUNG', 'Bestellung #' . $id, status_label($order['status']));
    ?>
<section class="container pb-5"><div class="row g-4"><div class="col-lg-8"><div class="panel"><p class="small">Zahlung: <?= e(status_label($order['payment_status'])) ?></p><div class="table-responsive"><table class="table"><thead><tr><th>Artikel</th><th>Menge</th><th>Gesamt</th></tr></thead><tbody><?php foreach ($order['items'] as $item): ?><tr><td><?= e($item['name']) ?></td><td><?= (int) $item['quantity'] ?></td><td><?= money((int) $item['line_total']) ?></td></tr><?php endforeach; ?></tbody></table></div><div class="summary-line"><span>Versand</span><span><?= money((int) $order['shipping_cents']) ?></span></div><div class="summary-line summary-total"><strong>Gesamt</strong><strong><?= money((int) $order['total_cents']) ?></strong></div><p class="form-text">Inklusive <?= e((string) $order['vat_percent']) ?> % Umsatzsteuer (<?= money((int) $order['vat_cents']) ?>).</p>
<?php if ($order['payment_status'] === 'paid'): ?><a class="btn btn-accent mt-3" href="?page=invoice&amp;id=<?= $id ?>">Rechnung als PDF herunterladen ↓</a><?php else: ?><p class="text-muted-dc small">Nach bestätigter Zahlung steht Ihre Rechnung hier zum Download bereit.</p><?php endif; ?>
</div></div><div class="col-lg-4"><div class="panel"><h2 class="h5">Liefer- &amp; Rechnungsadresse</h2><p class="text-muted-dc"><?= e($order['shipping_name']) ?><br><?= e($order['address1']) ?><br><?php if ($order['address2']): ?><?= e($order['address2']) ?><br><?php endif; ?><?= e($order['postal_code']) ?> <?= e($order['city']) ?><br>Deutschland</p><a class="text-link" href="?page=tickets">Frage zur Bestellung? ↗</a></div></div></div></section>
<?php
}

function render_tickets(): void
{
    page_intro('IHRE IDEEN-WERKSTATT', 'Lassen Sie Ideen Form annehmen.', 'Projekt anfragen oder Unterstützung erhalten – persönlich und übersichtlich.');
    $tickets = is_admin() ? admin_tickets() : user_tickets((int) current_user()['id']);
    ?>
<section class="container pb-5"><div class="row g-4"><div class="col-lg-7"><div class="panel"><h2 class="h4">Ihre Anfragen</h2><?php if (!$tickets): ?><p class="text-muted-dc mb-0">Noch keine Anfrage. Erzählen Sie uns von Ihrer Idee!</p><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Betreff</th><th>Status</th><th>Datum</th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><a href="?page=ticket&amp;id=<?= (int) $ticket['id'] ?>"><?= e($ticket['subject']) ?> ↗</a></td><td><span class="status"><?= e(status_label($ticket['status'])) ?></span></td><td><?= e($ticket['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
<div class="col-lg-5"><form method="post" class="panel" data-once><?= csrf_field() ?><input type="hidden" name="action" value="ticket_create"><h2 class="h4 mb-4">Neue Anfrage</h2><div class="mb-3"><label class="form-label" for="subject">Betreff</label><input class="form-control" id="subject" name="subject" maxlength="160" required></div><div class="mb-3"><label class="form-label" for="message">Ihre Idee oder Frage</label><textarea class="form-control" name="message" id="message" rows="6" minlength="10" maxlength="10000" required></textarea><div class="form-text">Beschreiben Sie Größe, Einsatzzweck, Farben und Wünsche. Dateiuploads werden aus Sicherheitsgründen nicht angeboten.</div></div><button class="btn btn-accent" type="submit">Anfrage senden ↗</button></form></div></div></section>
<?php
}

function render_ticket(int $id): void
{
    $ticket = ticket_details($id);
    if (!$ticket || ((int) $ticket['user_id'] !== (int) current_user()['id'] && !is_admin())) { http_response_code(404); page_intro('NICHT GEFUNDEN', 'Anfrage nicht gefunden.'); return; }
    page_intro('ANFRAGE #' . $id, $ticket['subject'], status_label($ticket['status']));
    ?>
<section class="container pb-5" style="max-width:900px"><a href="?page=tickets" class="text-link">← Alle Anfragen</a><div class="mt-4">
<?php foreach ($ticket['replies'] as $message): ?><article class="ticket-message"><div class="d-flex justify-content-between gap-3"><strong><?= e($message['name']) ?><?= $message['role'] === 'admin' ? ' · Drive-Craft-3D' : '' ?></strong><small><?= e($message['created_at']) ?></small></div><p class="mt-3 mb-0"><?= e($message['message']) ?></p></article><?php endforeach; ?></div>
<?php if ($ticket['status'] !== 'closed'): ?><form method="post" class="panel mt-4" data-once><?= csrf_field() ?><input type="hidden" name="action" value="ticket_reply"><input type="hidden" name="ticket_id" value="<?= $id ?>"><div class="mb-3"><label for="message" class="form-label">Ihre Antwort</label><textarea class="form-control" id="message" name="message" rows="5" maxlength="10000" required></textarea></div><button class="btn btn-accent" type="submit">Antwort senden ↗</button></form><?php else: ?><p class="text-muted-dc">Diese Anfrage wurde geschlossen. Für ein neues Anliegen erstellen Sie bitte eine neue Anfrage.</p><?php endif; ?>
<?php if (is_admin()): ?><form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="admin_ticket_update"><input type="hidden" name="ticket_id" value="<?= $id ?>"><input type="hidden" name="status" value="<?= $ticket['status'] === 'closed' ? 'open' : 'closed' ?>"><button class="btn btn-outline" type="submit"><?= $ticket['status'] === 'closed' ? 'Anfrage wieder öffnen' : 'Anfrage schließen' ?></button></form><?php endif; ?>
</section>
<?php
}

function render_payment_result(bool $cancelled): void
{
    page_intro('IHRE BESTELLUNG', $cancelled ? 'Zahlung nicht abgeschlossen.' : 'Vielen Dank für Ihre Bestellung.', $cancelled ? 'Die Zahlung wurde abgebrochen. Es wurde keine erfolgreiche Zahlung bestätigt.' : 'Wir warten gegebenenfalls noch auf die Zahlungsbestätigung von Stripe.');
    ?>
<section class="container pb-5"><div class="panel"><p class="text-muted-dc">Den verbindlichen Zahlungsstatus und Ihre Rechnung finden Sie in Ihrem Kundenkonto. Eine Rückkehr von Stripe allein ist keine Zahlungsbestätigung.</p><a class="btn btn-accent" href="?page=account">Zu meinen Bestellungen ↗</a><a class="btn btn-outline ms-2" href="?page=shop">Weiter entdecken</a></div></section>
<?php
}
