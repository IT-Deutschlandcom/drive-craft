<?php
declare(strict_types=1);
function render_home(): void
{
    ?>
<section class="hero">
  <div class="container"><div class="row align-items-center gx-lg-5">
    <div class="col-lg-6">
      <span class="eyebrow"><span class="dot"></span>KLEINE IDEEN. GROSSE MÖGLICHKEITEN.</span>
      <h1>Alles beginnt<br>mit <span class="accent-text">Ihrer Idee.</span></h1>
      <p class="hero-copy">Wir machen daraus etwas Echtes. Entdecken Sie einzigartige 3D-Drucke – oder gestalten Sie mit uns Ihr ganz persönliches Unikat.</p>
      <div class="hero-actions"><a class="btn btn-accent" href="?page=shop">Unikate entdecken <span class="ms-3" aria-hidden="true">↗</span></a><a class="btn btn-outline" href="?page=tickets">Eigene Idee umsetzen</a></div>
      <div class="hero-note"><div class="mini-stack" aria-hidden="true"><span>✦</span><span>◈</span><span>↗</span></div><div><strong>Kein Massenprodukt. Ihr Lieblingsstück.</strong>Persönlich geplant. Schicht für Schicht gefertigt.</div></div>
    </div>
    <div class="col-lg-6"><a class="hero-art" href="?page=shop" aria-label="3D-Druck-Lampen im Shop entdecken">
      <div class="hero-grid" aria-hidden="true"></div><span class="art-label">DESIGN TRIFFT 3D-DRUCK</span><span class="art-number">01 / STUDIO</span>
      <img src="assets/images/lamp.svg" width="600" height="520" alt="Entwurfsillustration einer geriffelten 3D-Druck-Lampe in warmem Beige">
      <div class="art-caption"><div><small>Licht mit Persönlichkeit</small><strong>Ein Statement für Ihr Zuhause.</strong></div><span class="circle-arrow" aria-hidden="true">↗</span></div>
    </a></div>
  </div></div>
</section>
<div class="container"><div class="trust-strip"><div><span aria-hidden="true">✧</span> Individuell statt gewöhnlich</div><div><span aria-hidden="true">▱</span> Präziser 3D-Druck</div><div><span aria-hidden="true">♡</span> Mit Liebe zum Detail</div><div><span aria-hidden="true">↗</span> Ihre Idee im Mittelpunkt</div></div></div>
<section class="section"><div class="container">
  <div class="section-heading reveal"><div><span class="eyebrow">FRISCH AUS DEM DRUCKER</span><h2>Besonders. Bis ins Detail.</h2></div><a class="text-link" href="?page=shop">Alle Produkte ansehen ↗</a></div>
  <div class="row g-4"><?php foreach (array_slice(products(), 0, 4) as $p): ?><div class="col-6 col-lg-3 reveal"><?php product_card($p); ?></div><?php endforeach; ?></div>
</div></section>
<section class="container reveal"><div class="custom-banner"><div class="row align-items-center g-4"><div class="col-lg-8"><span class="eyebrow">NICHT VON DER STANGE. SONDERN VON IHNEN.</span><h2>Sie haben die Idee.<br>Wir haben den Drucker.</h2><p class="mb-0">Ein Logo als Lampe? Ein Helfer, den es noch nicht gibt? Bringen Sie Ihre Skizzen, Designs oder einfach eine gute Idee mit. Gemeinsam machen wir sie greifbar.</p></div><div class="col-lg-4 text-lg-end"><a class="btn btn-accent" href="?page=tickets">Projekt anfragen <span class="ms-3" aria-hidden="true">↗</span></a><div class="small text-muted-dc mt-3">Persönliche Beratung · Individuelle Umsetzung</div></div></div></div></section>
<section class="section"><div class="container">
  <div class="section-heading reveal"><div><span class="eyebrow">EIN KLEINER EINBLICK</span><h2>Ideen, die Form annehmen.</h2></div><a class="text-link" href="?page=gallery">Zur Galerie ↗</a></div>
  <div class="row g-4"><?php foreach (array_slice(gallery_items(), 0, 3) as $item): ?><div class="col-md-4 reveal"><?php gallery_card($item); ?></div><?php endforeach; ?></div>
</div></section>
<section class="container pb-5"><div class="section-heading reveal"><div><span class="eyebrow">VON DER VORSTELLUNG ZUM LIEBLINGSSTÜCK</span><h2>So einfach wird’s persönlich.</h2></div></div>
  <div class="row g-4"><?php foreach ([['01', 'Idee teilen', 'Erzählen Sie uns von Ihrem Wunsch. Eine Skizze, ein Logo oder ein Gedanke reichen für den Anfang.'], ['02', 'Gemeinsam gestalten', 'Wir besprechen Form, Material und Details – und finden die passende Lösung für Ihr Projekt.'], ['03', 'Freude auspacken', 'Ihr Design wird Schicht für Schicht zum echten Einzelstück. Bereit für seinen Platz bei Ihnen.']] as [$num, $title, $text]): ?><div class="col-md-4 reveal"><div class="process-card"><span class="process-num"><?= $num ?> /</span><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div></div><?php endforeach; ?></div>
</section>
<?php
}

function render_shop(): void
{
    page_intro('DER SHOP', 'Nicht einfach Dinge. Lieblingsstücke.', 'Entdecken Sie kreative Designs für Ihr Zuhause und Ihren Alltag.');
    $items = products();
    $categories = array_unique(array_column($items, 'category'));
    ?>
<section class="container pb-5">
  <div class="filter-bar" data-filter-group aria-label="Produkte filtern"><button type="button" class="filter-button active" data-filter="*" aria-pressed="true">Alle Produkte</button><?php foreach ($categories as $category): ?><button type="button" class="filter-button" data-filter="<?= e($category) ?>" aria-pressed="false"><?= e($category) ?></button><?php endforeach; ?></div>
  <div class="row g-4"><?php foreach ($items as $p): ?><div class="col-6 col-lg-3" data-category="<?= e($p['category']) ?>"><?php product_card($p); ?></div><?php endforeach; ?></div>
  <?php if (!$items): ?><div class="empty-state">Unsere nächsten Lieblingsstücke sind in Vorbereitung.</div><?php endif; ?>
  <p class="form-text mt-4">Alle Preise in EUR, inklusive gesetzlicher Umsatzsteuer, zuzüglich Versand. Die Abbildungen sind Designillustrationen; maßgeblich ist die jeweilige Produktbeschreibung.</p>
</section>
<?php
}

function render_product(int $id): void
{
    $p = product($id);
    if (!$p || !(int) $p['active']) { http_response_code(404); page_intro('NICHT GEFUNDEN', 'Dieses Produkt ist nicht verfügbar.'); return; }
    ?>
<section class="container section"><a href="?page=shop" class="text-link">← Zurück zum Shop</a><div class="row g-5 mt-1"><div class="col-md-6"><div class="product-image"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" width="600" height="520"></div></div><div class="col-md-6"><span class="eyebrow"><?= e($p['category']) ?></span><h1 class="display-5"><?= e($p['name']) ?></h1><div class="fs-3 my-3"><?= money((int) $p['price_cents']) ?></div><p class="text-muted-dc" style="white-space:pre-line"><?= e($p['description']) ?></p>
  <p class="small text-muted-dc">Inklusive Umsatzsteuer · Versand wird im Warenkorb ausgewiesen.<br>Verfügbar: <?= (int) $p['stock'] ?> Stück</p>
  <?php if (!empty($p['preview'])): ?><p class="alert alert-info">Designvorschau mit Beispielpreis. Bestellungen sind erst nach Einrichtung und Verkaufsfreigabe möglich.</p>
  <?php elseif ((int) $p['stock'] > 0): ?><form method="post" action="?page=product&amp;id=<?= $id ?>" class="mt-4">
    <?= csrf_field() ?><input type="hidden" name="action" value="cart_add"><input type="hidden" name="product_id" value="<?= $id ?>">
    <label class="form-label" for="quantity">Menge</label><div class="d-flex gap-3"><input class="form-control" style="max-width:90px" type="number" id="quantity" name="quantity" value="1" min="1" max="<?= min(99, (int) $p['stock']) ?>" required><button class="btn btn-accent" type="submit">In den Warenkorb ↗</button></div>
  </form><?php else: ?><p class="alert alert-info">Aktuell ausverkauft. Fragen Sie uns nach einer individuellen Fertigung.</p><?php endif; ?>
  <hr class="my-4"><h2 class="h5">Darf’s noch persönlicher sein?</h2><p class="text-muted-dc small">Andere Farbe, eigenes Logo oder eine neue Idee? Wir sprechen gerne mit Ihnen darüber.</p><a class="text-link" href="?page=tickets">Individuelle Anfrage stellen ↗</a>
</div></div></section>
<?php
}

function gallery_card(array $item): void
{
    ?>
<article class="gallery-card"><a href="<?= e($item['image']) ?>" data-lightbox data-title="<?= e($item['title']) ?>"><img src="<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>" width="600" height="520" loading="lazy"></a><div class="gallery-caption"><h3><?= e($item['title']) ?></h3><p><?= e($item['description']) ?></p></div></article>
<?php
}

function render_gallery(): void
{
    page_intro('INSPIRATION IN JEDER SCHICHT', 'Unsere Ideenwelt.', 'Einblicke in Formen, Möglichkeiten und Designs. Die Illustrationen zeigen Entwurfsideen für Ihre nächsten Projekte.');
    ?>
<section class="container pb-5"><div class="row g-4"><?php foreach (gallery_items() as $item): ?><div class="col-sm-6 col-lg-4 reveal"><?php gallery_card($item); ?></div><?php endforeach; ?></div>
<div class="custom-banner mt-5"><h2>Und was schwebt Ihnen vor?</h2><p>Die schönste Idee ist vielleicht Ihre. Lassen Sie uns darüber sprechen.</p><a class="btn btn-accent" href="?page=tickets">Meine Idee teilen ↗</a></div></section>
<dialog class="lightbox" id="gallery-lightbox"><div class="d-flex align-items-center justify-content-between"><h2 class="h5 mb-0"></h2><button class="icon-button" type="button" data-close aria-label="Bild schließen">×</button></div><img src="assets/images/lamp.svg" alt=""></dialog>
<?php
}

function render_about(): void
{
    page_intro('DAS IST DRIVE-CRAFT-3D', 'Wir machen aus Ihren Ideen echte Unikate.');
    ?>
<section class="container pb-5"><div class="row g-5 align-items-center"><div class="col-lg-6"><div class="hero-art"><div class="hero-grid" aria-hidden="true"></div><img src="assets/images/vase.svg" alt="Illustration einer individuell gestalteten 3D-Druck-Vase" width="600" height="520"></div></div><div class="col-lg-6"><span class="eyebrow">KREATIVITÄT TRIFFT TECHNIK</span><h2 class="display-6">Etwas Besonderes.<br>Genau für Sie.</h2><p class="text-muted-dc">Bei Drive-Craft-3D dreht sich alles um individuellen 3D-Druck und kreative Lösungen. Ob persönliche Dekoration für Ihr Zuhause, praktische Alltagsgegenstände oder einzigartige 3D-Druck-Lampen mit Ihrem eigenen Logo – wir setzen Ihre Wünsche individuell um.</p><p class="text-muted-dc">Sie haben eine eigene Idee oder einen eigenen Entwurf? Perfekt! Gemeinsam bringen wir Ihre Vorstellungen in die digitale Welt und verwandeln sie anschließend mit modernem 3D-Druck in ein echtes Produkt.</p><p class="text-muted-dc">Eigene Designs, Logos, Formen und individuelle Wünsche können zu einzigartigen Einzelstücken werden. Erzählen Sie uns, was Sie bewegt.</p><a href="?page=contact" class="btn btn-accent">Lernen wir uns kennen ↗</a></div></div></section>
<?php
}

function render_contact(): void
{
    page_intro('GUTE IDEEN BEGINNEN MIT EINEM HALLO', 'Lassen Sie uns etwas gestalten.', 'Fragen zum Shop oder eine erste Idee? Schreiben Sie uns.');
    ?>
<section class="container pb-5"><div class="row g-5"><div class="col-lg-5"><h2 class="h4">Ihr direkter Draht zu uns.</h2><p class="text-muted-dc">Für Nachrichten und allgemeine Fragen erreichen Sie uns unter:</p><a class="text-link" href="mailto:info@drive-craft-3d.de">info@drive-craft-3d.de ↗</a><div class="panel mt-5"><h3 class="h5">Ein individuelles Projekt?</h3><p class="small text-muted-dc">Im Kundenbereich bleiben Ihre Projektanfrage und alle Antworten übersichtlich an einem Ort.</p><a class="btn btn-outline" href="?page=tickets">Zum Ticketsystem ↗</a></div></div><div class="col-lg-7"><form method="post" class="panel" data-once>
  <?= csrf_field() ?><input type="hidden" name="action" value="contact">
  <div class="mb-3"><label class="form-label" for="name">Ihr Name</label><input class="form-control" id="name" name="name" maxlength="120" required autocomplete="name"></div>
  <div class="mb-3"><label class="form-label" for="email">E-Mail-Adresse</label><input class="form-control" id="email" name="email" type="email" maxlength="190" required autocomplete="email"></div>
  <div class="mb-3"><label class="form-label" for="subject">Betreff</label><input class="form-control" id="subject" name="subject" maxlength="160" required></div>
  <div class="mb-3"><label class="form-label" for="message">Ihre Nachricht</label><textarea class="form-control" id="message" name="message" rows="6" minlength="10" maxlength="5000" required></textarea></div>
  <div class="mb-4 form-check"><input class="form-check-input" type="checkbox" name="privacy" id="privacy" value="1" required><label class="form-check-label small" for="privacy">Ich habe die <a class="text-decoration-underline" href="?page=legal#privacy">Datenschutzhinweise</a> gelesen. Meine Angaben werden zur Bearbeitung meiner Anfrage verarbeitet.</label></div>
  <button class="btn btn-accent" type="submit">Nachricht senden ↗</button>
</form></div></div></section>
<?php
}
