<?php
declare(strict_types=1);
function render_admin(string $tab, int $editId): void
{
    require_admin();
    $tabs = ['dashboard' => 'Übersicht', 'products' => 'Produkte', 'orders' => 'Bestellungen', 'gallery' => 'Galerie', 'tickets' => 'Tickets', 'users' => 'Kunden', 'contact' => 'Kontakt'];
    if (!isset($tabs[$tab])) $tab = 'dashboard';
    page_intro('DRIVE-CRAFT-3D · BACKOFFICE', 'Ihre Werkstatt. Ihr Überblick.');
    ?>
<section class="container pb-5">
<nav class="admin-nav" aria-label="Administration"><?php foreach ($tabs as $key => $label): ?><a href="?page=admin&amp;tab=<?= $key ?>" <?= $tab === $key ? 'class="active" aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<?php
    switch ($tab) {
        case 'dashboard':
            $stats = admin_stats();
            ?>
<div class="row g-3 mb-4"><?php foreach (['orders' => 'Bestellungen', 'users' => 'Kundenkonten', 'products' => 'Produkte', 'tickets' => 'Offene Tickets'] as $key => $label): ?><div class="col-6 col-lg-3"><div class="panel"><div class="eyebrow mb-2"><?= e($label) ?></div><div class="stat-value"><?= (int) $stats[$key] ?></div></div></div><?php endforeach; ?></div>
<div class="row g-4"><div class="col-lg-6"><div class="panel"><span class="eyebrow">BESTÄTIGTE ZAHLUNGEN</span><div class="stat-value"><?= money((int) $stats['revenue_cents']) ?></div><p class="form-text mb-0">Bruttosumme bezahlter Bestellungen, inklusive Versand. Erstattungen sind im Stripe-Dashboard und der Buchhaltung abzugleichen.</p></div></div><div class="col-lg-6"><div class="panel"><h2 class="h5">Betriebsstatus</h2><p class="small"><?= checkout_enabled() ? '✓ Checkout ist konfiguriert und freigegeben.' : '○ Checkout ist gesperrt. Konfiguration, Rechtstexte und Stripe-Einrichtung prüfen.' ?></p><p class="small text-muted-dc mb-0">Produktpreise werden in Cent gepflegt. Bezahlte Bestellungen erhalten automatisch eine PDF-Rechnung. Stornierungen und Erstattungen bitte direkt in Stripe und der Buchhaltung bearbeiten.</p></div></div></div>
<?php
            break;
        case 'products':
            $items = products(true);
            $selected = null;
            foreach ($items as $item) if ((int) $item['id'] === $editId) $selected = $item;
            ?>
<div class="row g-4"><div class="col-lg-7"><div class="panel"><h2 class="h4">Sortiment</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produkt</th><th>Preis</th><th>Bestand</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($items as $p): ?><tr><td><?= e($p['name']) ?></td><td class="text-nowrap"><?= money((int) $p['price_cents']) ?></td><td><?= (int) $p['stock'] ?></td><td><?= (int) $p['active'] ? 'Aktiv' : 'Verborgen' ?></td><td><a href="?page=admin&amp;tab=products&amp;edit=<?= (int) $p['id'] ?>" class="text-link">Bearbeiten</a></td></tr><?php endforeach; ?></tbody></table></div></div></div><div class="col-lg-5"><form method="post" class="panel">
<?= csrf_field() ?><input type="hidden" name="action" value="admin_product_save"><input type="hidden" name="id" value="<?= (int) ($selected['id'] ?? 0) ?>"><?php if ($selected): ?><input type="hidden" name="original_stock" value="<?= (int) $selected['stock'] ?>"><?php endif; ?><h2 class="h4 mb-4"><?= $selected ? 'Produkt bearbeiten' : 'Neues Produkt' ?></h2>
<div class="mb-3"><label class="form-label" for="name">Name</label><input class="form-control" name="name" id="name" maxlength="160" value="<?= e($selected['name'] ?? '') ?>" required></div>
<div class="mb-3"><label class="form-label" for="category">Kategorie</label><input class="form-control" name="category" id="category" maxlength="80" value="<?= e($selected['category'] ?? '') ?>" required></div>
<div class="mb-3"><label class="form-label" for="description">Beschreibung inkl. Material, Größe und Lieferzeit</label><textarea class="form-control" name="description" id="description" rows="5" maxlength="20000" required><?= e($selected['description'] ?? '') ?></textarea></div>
<div class="row g-3 mb-3"><div class="col-6"><label class="form-label" for="price_cents">Bruttopreis in Cent</label><input class="form-control" type="number" name="price_cents" id="price_cents" min="50" max="9999999" value="<?= (int) ($selected['price_cents'] ?? 1990) ?>" required></div><div class="col-6"><label class="form-label" for="stock">Verfügbarer Bestand</label><input class="form-control" type="number" name="stock" id="stock" min="0" max="1000000" value="<?= (int) ($selected['stock'] ?? 0) ?>" required></div></div>
<?php image_select($selected['image'] ?? 'assets/images/lamp.svg'); ?>
<div class="form-check my-3"><input class="form-check-input" type="checkbox" name="active" id="active" value="1" <?= !$selected || (int) $selected['active'] ? 'checked' : '' ?>><label class="form-check-label small" for="active">Im Shop sichtbar</label></div><button class="btn btn-accent" type="submit">Produkt speichern</button><?php if ($selected): ?><a class="btn btn-outline mt-2" href="?page=admin&amp;tab=products">Neues Produkt</a><?php endif; ?>
</form></div></div>
<?php
            break;
        case 'orders':
            ?>
<div class="panel"><h2 class="h4">Bestellungen &amp; Rechnungen</h2><p class="form-text">Zahlungsstatus wird ausschließlich über signierte Stripe-Ereignisse gesetzt. Maximal die letzten 500 Bestellungen.</p><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Bestellung</th><th>Kunde</th><th>Gesamt</th><th>Zahlung</th><th>Bearbeitung</th><th>Beleg</th></tr></thead><tbody>
<?php foreach (admin_orders() as $order): ?><tr><td><a href="?page=order&amp;id=<?= (int) $order['id'] ?>"><?= e($order['order_number']) ?> ↗</a><div class="form-text"><?= e($order['created_at']) ?></div></td><td><?= e($order['customer_name']) ?></td><td class="text-nowrap"><?= money((int) $order['total_cents']) ?></td><td><span class="status"><?= e(status_label($order['payment_status'])) ?></span></td><td><?php if ($order['payment_status'] === 'paid'): ?><form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="admin_order_update"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><label class="visually-hidden" for="status-<?= (int) $order['id'] ?>">Bestellstatus</label><select class="form-select py-1" id="status-<?= (int) $order['id'] ?>" name="status"><?php foreach (['processing', 'printing', 'shipped', 'completed'] as $status): ?><option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(status_label($status)) ?></option><?php endforeach; ?></select><button class="btn btn-outline px-2 py-1" type="submit">Speichern</button></form><?php else: ?><?= e(status_label($order['status'])) ?><?php endif; ?></td><td><?php if ($order['payment_status'] === 'paid'): ?><a class="text-link" href="?page=invoice&amp;id=<?= (int) $order['id'] ?>">PDF ↓</a><?php else: ?>–<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php
            break;
        case 'gallery':
            $items = gallery_items(true);
            $selected = null;
            foreach ($items as $item) if ((int) $item['id'] === $editId) $selected = $item;
            ?>
<div class="row g-4"><div class="col-lg-7"><div class="panel"><h2 class="h4">Galerieeinträge</h2><div class="table-responsive"><table class="table"><thead><tr><th>Titel</th><th>Sortierung</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= e($item['title']) ?></td><td><?= (int) $item['sort_order'] ?></td><td><?= (int) $item['active'] ? 'Sichtbar' : 'Verborgen' ?></td><td><a class="text-link" href="?page=admin&amp;tab=gallery&amp;edit=<?= (int) $item['id'] ?>">Bearbeiten</a></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="col-lg-5"><form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="action" value="admin_gallery_save"><input type="hidden" name="id" value="<?= (int) ($selected['id'] ?? 0) ?>"><h2 class="h4 mb-4"><?= $selected ? 'Eintrag bearbeiten' : 'Neuer Eintrag' ?></h2><div class="mb-3"><label class="form-label" for="title">Titel</label><input class="form-control" name="title" id="title" maxlength="160" value="<?= e($selected['title'] ?? '') ?>" required></div><div class="mb-3"><label class="form-label" for="description">Beschreibung</label><textarea class="form-control" id="description" name="description" rows="4" maxlength="10000"><?= e($selected['description'] ?? '') ?></textarea></div><?php image_select($selected['image'] ?? 'assets/images/lamp.svg'); ?><div class="mb-3"><label class="form-label" for="sort_order">Sortierung (kleinere Zahl zuerst)</label><input class="form-control" type="number" name="sort_order" id="sort_order" min="-1000000" max="1000000" value="<?= (int) ($selected['sort_order'] ?? 0) ?>"></div><div class="form-check mb-3"><input class="form-check-input" name="active" id="active" type="checkbox" value="1" <?= !$selected || (int) $selected['active'] ? 'checked' : '' ?>><label class="form-check-label small" for="active">Sichtbar</label></div><button class="btn btn-accent" type="submit">Eintrag speichern</button><?php if ($selected): ?><a class="btn btn-outline mt-2" href="?page=admin&amp;tab=gallery">Neuer Eintrag</a><?php endif; ?></form></div></div>
<?php
            break;
        case 'tickets':
            ?>
<div class="panel"><h2 class="h4">Projektanfragen &amp; Support</h2><div class="table-responsive"><table class="table"><thead><tr><th>Betreff</th><th>Kunde</th><th>Status</th><th>Aktualisiert</th></tr></thead><tbody><?php foreach (admin_tickets() as $ticket): ?><tr><td><a href="?page=ticket&amp;id=<?= (int) $ticket['id'] ?>"><?= e($ticket['subject']) ?> ↗</a></td><td><?= e($ticket['customer_name']) ?></td><td><?= e(status_label($ticket['status'])) ?></td><td><?= e($ticket['updated_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php
            break;
        case 'users':
            ?>
<div class="panel"><h2 class="h4">Kundenübersicht</h2><p class="form-text">Konten sind hier nur lesbar. Administratorrechte werden ausschließlich per CLI vergeben.</p><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>E-Mail-Adresse</th><th>Rolle</th><th>Registriert</th></tr></thead><tbody><?php foreach (admin_users() as $user): ?><tr><td><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e($user['role']) ?></td><td><?= e($user['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php
            break;
        case 'contact':
            ?>
<div class="panel"><h2 class="h4">Kontaktpostfach</h2><p class="form-text">Nachrichten werden auch bei fehlgeschlagenem E-Mail-Versand gespeichert. Antworten über Ihr E-Mail-Programm senden.</p><?php foreach (contact_messages() as $message): ?><article class="ticket-message mt-3"><div class="d-flex justify-content-between gap-3"><strong><?= e($message['subject']) ?></strong><small><?= e($message['created_at']) ?></small></div><p class="small text-muted-dc mb-2"><?= e($message['name']) ?> · <a class="text-decoration-underline" href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a> · E-Mail-Versand: <?= (int) $message['mail_sent'] ? 'erfolgreich' : 'nicht bestätigt' ?></p><p class="mb-0"><?= e($message['message']) ?></p></article><?php endforeach; ?></div>
<?php
    }
    echo '</section>';
}

function image_select(string $selected): void
{
    $paths = array_filter(glob(APP_ROOT . '/assets/images/*') ?: [], static fn ($path) => preg_match('~^[a-zA-Z0-9_-]+\.(svg|png|jpe?g|webp)$~', basename($path)));
    ?>
<div class="mb-3"><label class="form-label" for="image">Lokales Produktbild</label><select class="form-select" id="image" name="image" required><?php foreach ($paths as $path): $relative = 'assets/images/' . basename($path); ?><option value="<?= e($relative) ?>" <?= $relative === $selected ? 'selected' : '' ?>><?= e(basename($path)) ?></option><?php endforeach; ?></select><div class="form-text">Eigene Bilder per Deployment in assets/images ablegen. Dateinamen nur mit Buchstaben, Zahlen, Bindestrich oder Unterstrich.</div></div>
<?php
}
