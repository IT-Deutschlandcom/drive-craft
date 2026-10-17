<?php
declare(strict_types=1);
function render_legal(): void
{
    page_intro('TRANSPARENT & VERSTÄNDLICH', 'Rechtliches & Datenschutz.');
    ?>
<section class="container pb-5" style="max-width:900px">
<?php foreach (['legal_notice' => ['imprint', 'Impressum'], 'privacy_text' => ['privacy', 'Datenschutzhinweise'], 'terms_text' => ['terms', 'Allgemeine Geschäftsbedingungen'], 'withdrawal_text' => ['withdrawal', 'Widerrufsbelehrung']] as $key => [$anchor, $title]): ?>
<article class="panel mb-4" id="<?= $anchor ?>"><h2 class="h4"><?= e($title) ?></h2><?php $text = trim((string) config('shop.' . $key, '')); if ($text !== ''): ?><div style="white-space:pre-wrap;overflow-wrap:anywhere"><?= e($text) ?></div><?php else: ?><p class="text-muted-dc mb-0">Diese Angaben werden vom Betreiber vor der Freischaltung des Verkaufs ergänzt. Der Shop ist noch nicht für rechtsverbindliche Bestellungen freigegeben.</p><?php endif; ?></article>
<?php endforeach; ?>
</section>
<?php
}
