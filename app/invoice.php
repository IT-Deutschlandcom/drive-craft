<?php
declare(strict_types=1);

function pdf_string(string $text): string
{
    $text = str_replace(["\r", "\n", "\0"], ' ', $text);
    $converted = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) : false;
    if ($converted === false) {
        $converted = (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
}

function invoice_pdf(array $order): string
{
    $lines = [
        (string) $order['seller_name'],
        ...preg_split('/\R/u', (string) $order['seller_address']),
        'Steuernummer / USt-IdNr.: ' . $order['seller_tax_id'],
        '',
        'VORAUSZAHLUNGSRECHNUNG ' . $order['order_number'],
        'Rechnungsdatum: ' . gmdate('d.m.Y', strtotime($order['paid_at'] . ' UTC')),
        'Bestelldatum: ' . gmdate('d.m.Y', strtotime($order['created_at'] . ' UTC')),
        'Leistungs-/Lieferdatum: zum Rechnungsdatum noch nicht erfolgt.',
        'Vorauszahlung; das tatsaechliche Lieferdatum dokumentiert der Lieferschein.',
        '',
        'Rechnungsempfaenger:',
        $order['shipping_name'], $order['address1'],
    ];
    if ($order['address2'] !== '') {
        $lines[] = $order['address2'];
    }
    $lines[] = $order['postal_code'] . ' ' . $order['city'];
    $lines[] = $order['country'];
    $lines[] = '';
    foreach ($order['items'] as $item) {
        $lines[] = $item['quantity'] . ' x ' . $item['name'];
        $lines[] = '  Einzelpreis: ' . money((int) $item['unit_price_cents']) . ' | Gesamt: ' . money((int) $item['line_total']);
    }
    $lines[] = 'Versand Deutschland: ' . money((int) ($order['shipping_cents'] ?? 0));
    array_push($lines, '',
        'Nettobetrag: ' . money((int) $order['total_cents'] - (int) $order['vat_cents']),
        'Enthaltene Umsatzsteuer (' . number_format((float) $order['vat_percent'], 2, ',', '.') . ' %): ' . money((int) $order['vat_cents']),
        'Gesamtbetrag: ' . money((int) $order['total_cents']),
        'Waehrung: EUR | Versand: im Gesamtbetrag enthalten',
        'Zahlung erhalten am ' . gmdate('d.m.Y', strtotime($order['paid_at'] . ' UTC')),
        '', 'Vielen Dank fuer deine Bestellung bei Drive Craft 3D!'
    );
    $wrapped = [];
    foreach ($lines as $line) {
        $words = preg_split('/\s+/u', trim((string) $line)) ?: [];
        $current = '';
        foreach ($words as $word) {
            if (strlen($current . ' ' . $word) > 88 && $current !== '') {
                $wrapped[] = $current;
                $current = '';
            }
            // Split long unbroken user-supplied strings without breaking UTF-8.
            $chunks = preg_split('/(.{1,80})/us', $word, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$word];
            foreach ($chunks as $index => $chunk) {
                $current .= ($current === '' ? '' : ' ') . $chunk;
                if ($index < count($chunks) - 1) {
                    $wrapped[] = $current;
                    $current = '';
                }
            }
        }
        $wrapped[] = $current;
    }
    $pages = array_chunk($wrapped, 46);
    $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
    $pageIds = [];
    foreach ($pages as $index => $page) {
        $pageId = 4 + $index * 2;
        $contentId = $pageId + 1;
        $pageIds[] = $pageId . ' 0 R';
        $stream = "BT\n/F1 11 Tf\n14 TL\n50 790 Td\n";
        foreach ($page as $line) {
            $stream .= '(' . pdf_string($line) . ") Tj\nT*\n";
        }
        $stream .= "ET\nBT /F1 9 Tf 50 35 Td (" . pdf_string('Seite ' . ($index + 1) . ' / ' . count($pages)) . ") Tj ET\n";
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . count($pages) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
}

function invoice_archive_path(array $order): string
{
    $private = realpath((string) config('app.private_dir', ''));
    $root = realpath(APP_ROOT);
    if (!$private || !$root || str_starts_with(strtolower(str_replace('\\', '/', $private)) . '/', strtolower(str_replace('\\', '/', $root)) . '/')) {
        throw new RuntimeException('Invoice storage must exist outside the website root.');
    }
    $directory = $private . '/invoices';
    if (!is_dir($directory) && !mkdir($directory, 0700) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create invoice archive directory.');
    }
    $resolved = realpath($directory);
    if (!$resolved || !str_starts_with(strtolower(str_replace('\\', '/', $resolved)) . '/', strtolower(str_replace('\\', '/', $private)) . '/')) {
        throw new RuntimeException('Invalid invoice storage path.');
    }
    $number = (string) $order['order_number'];
    if (!preg_match('/^[A-Za-z0-9-]{1,40}$/D', $number)) {
        throw new RuntimeException('Invalid invoice number.');
    }
    return $resolved . '/Rechnung-' . $number . '.pdf';
}

function archive_invoice(int $id): string
{
    $order = query("SELECT * FROM orders WHERE id=? AND payment_status='paid' AND paid_at IS NOT NULL", [$id])->fetch();
    if (!$order) {
        throw new RuntimeException('Only confirmed paid orders have invoices.');
    }
    $path = invoice_archive_path($order);
    $lock = fopen($path . '.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Cannot lock invoice archive.');
    }
    $temporary = null;
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock invoice archive.');
        }
        $existing = is_file($path) ? file_get_contents($path) : false;
        if (is_string($existing) && str_starts_with($existing, '%PDF-1.4') && str_ends_with($existing, "%%EOF\n")) {
            return $path;
        }
        $order['items'] = query('SELECT * FROM order_items WHERE order_id=? ORDER BY id', [$id])->fetchAll();
        $pdf = invoice_pdf($order);
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.partial';
        if (file_put_contents($temporary, $pdf, LOCK_EX) !== strlen($pdf)) {
            throw new RuntimeException('Cannot write invoice archive.');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Cannot publish invoice archive.');
        }
        $temporary = null;
        app_log('invoice_archived', ['order_id' => $id]);
        return $path;
    } finally {
        if ($temporary !== null && is_file($temporary)) {
            unlink($temporary);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function ensure_invoice_archived(int $id): void
{
    try {
        archive_invoice($id);
    } catch (Throwable $error) {
        // Payment is already committed; a later webhook/download can recover the archive.
        app_log('invoice_archive_failed', ['order_id' => $id, 'error_class' => get_class($error)]);
    }
}

function serve_invoice(int $id): never
{
    try {
        $order = order_details($id);
        if (!$order || $order['payment_status'] !== 'paid' || !$order['paid_at']) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Rechnung nicht verfügbar.';
            exit;
        }
        try {
            $path = archive_invoice($id);
            $pdf = file_get_contents($path);
            if (!is_string($pdf)) {
                throw new RuntimeException('Cannot read invoice archive.');
            }
        } catch (Throwable $error) {
            app_log('invoice_download_regenerated', ['order_id' => $id, 'error_class' => get_class($error)]);
            $pdf = invoice_pdf($order);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Rechnung-' . preg_replace('/[^A-Za-z0-9-]/', '', $order['order_number']) . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
    } catch (DomainException) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Zugriff verweigert.';
    } catch (Throwable $error) {
        app_log('invoice_failed', ['order_id' => $id, 'error_class' => get_class($error)]);
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Rechnung derzeit nicht verfügbar.';
    }
    exit;
}
