<?php
declare(strict_types=1);
function render_header(string $title, string $page): void
{
    $user = current_user();
    $flashes = take_flashes();
    ?>
<!doctype html>
<html lang="de" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Individueller 3D-Druck von Drive-Craft-3D: persönliche Dekoration, praktische Alltagshelfer und einzigartige Lampen. Ihre Idee. Ihr Design. Unser 3D-Druck.">
  <meta name="color-scheme" content="dark light">
  <title><?= e($title) ?> · Drive-Craft-3D</title>
  <script src="assets/js/theme.js"></script>
  <link rel="icon" type="image/svg+xml" href="assets/images/keychain.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<a class="visually-hidden-focusable" href="#main">Zum Inhalt springen</a>
<div class="announcement">IHRE IDEE. IHR DESIGN. UNSER 3D-DRUCK.</div>
<header class="site-header">
  <div class="container">
    <div class="nav-row">
      <a class="brand" href="?page=home" aria-label="Drive-Craft-3D Startseite"><span class="brand-mark" aria-hidden="true">dc.</span>drive-craft<small>-3d</small></a>
      <nav id="main-nav" class="main-nav" aria-label="Hauptnavigation">
        <?php foreach (['home' => 'Startseite', 'shop' => 'Shop', 'gallery' => 'Galerie', 'about' => 'Über uns', 'contact' => 'Kontakt'] as $key => $label): ?>
          <a href="?page=<?= e($key) ?>" <?= $page === $key ? 'class="active" aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="?page=<?= $user ? 'account' : 'login' ?>"><?= $user ? 'Mein Konto' : 'Anmelden' ?></a>
        <?php if (is_admin()): ?><a href="?page=admin">Administration</a><?php endif; ?>
      </nav>
      <div class="nav-tools">
        <button class="icon-button" type="button" data-theme-toggle aria-label="Hellen Modus aktivieren">☀</button>
        <a class="cart-link" href="?page=cart" aria-label="Warenkorb, <?= cart_count() ?> Artikel">Bag <span class="cart-badge"><?= cart_count() ?></span></a>
        <button class="icon-button mobile-toggle" data-menu-toggle type="button" aria-controls="main-nav" aria-expanded="false" aria-label="Menü öffnen">☰</button>
      </div>
    </div>
  </div>
</header>
<main id="main">
  <?php if ($flashes): ?><div class="container pt-3" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : (in_array($flash['type'], ['success', 'warning', 'info', 'danger'], true) ? e($flash['type']) : 'info') ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
  </div><?php endif; ?>
<?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-6">
        <a class="brand" href="?page=home"><span class="brand-mark" aria-hidden="true">dc.</span>drive-craft<small>-3d</small></a>
        <p class="footer-copy">Aus Ideen werden echte Unikate.<br>Individueller 3D-Druck mit Charakter – für Ihr Zuhause, Ihren Alltag und Ihre Marke.</p>
      </div>
      <div class="col-6 col-lg-3"><div class="footer-title">ENTDECKEN</div><nav class="footer-links" aria-label="Weitere Seiten"><a href="?page=shop">Unsere Produkte</a><a href="?page=gallery">Inspiration &amp; Galerie</a><a href="?page=about">Die Idee dahinter</a><a href="?page=tickets">Individuelles Projekt anfragen</a></nav></div>
      <div class="col-6 col-lg-3"><div class="footer-title">WIR SIND FÜR SIE DA</div><nav class="footer-links" aria-label="Service"><a href="mailto:info@drive-craft-3d.de">info@drive-craft-3d.de</a><a href="?page=account">Konto &amp; Bestellungen</a><a href="?page=contact">Kontakt</a><a href="?page=legal">Rechtliches &amp; Datenschutz</a></nav></div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Drive-Craft-3D. Mit Ideen gemacht.</span><span>3D-gedruckt. Individuell. Besonders.</span></div>
  </div>
</footer>
<button class="icon-button back-top" type="button" data-back-top aria-label="Zurück nach oben">↑</button>
<script src="assets/js/app.js" defer></script>
</body>
</html>
<?php
}

function page_intro(string $eyebrow, string $heading, string $description = ''): void
{
    ?>
<div class="container page-intro"><span class="eyebrow"><?= e($eyebrow) ?></span><h1><?= e($heading) ?></h1><?php if ($description !== ''): ?><p class="text-muted-dc"><?= e($description) ?></p><?php endif; ?></div>
<?php
}

function product_card(array $product): void
{
    ?>
<article class="product-card">
  <a href="?page=product&amp;id=<?= (int) $product['id'] ?>" class="product-image">
    <span class="product-tag"><?= e($product['category']) ?></span>
    <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy" width="600" height="520">
  </a>
  <div class="product-info"><div class="d-flex justify-content-between gap-2"><h3><a href="?page=product&amp;id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3><span class="product-price"><?= money((int) $product['price_cents']) ?></span></div><p class="category">3D-gedruckt · Mit Liebe zum Detail</p></div>
</article>
<?php
}
