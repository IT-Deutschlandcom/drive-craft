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
        'RECHNUNG ' . $order['order_number'],
        'Rechnungsdatum: ' . gmdate('d.m.Y', strtotime($order['paid_at'] . ' UTC')),
        'Bestelldatum: ' . gmdate('d.m.Y', strtotime($order['created_at'] . ' UTC')),
        'Leistungsdatum: siehe Versandbestaetigung / Lieferschein',
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
        $pdf = invoice_pdf($order);
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
