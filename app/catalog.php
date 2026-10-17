<?php
declare(strict_types=1);

// Read-only installation preview, matching database/seed.sql; never used to accept orders.
function catalog_preview(): array
{
    $rows = [
        [1, 'Spiral-Lampe', 'spiral-lampe', 'Skulpturaler, 3D-gedruckter Lampenschirm mit sanftem Lichtspiel. Leuchtmittel nicht enthalten.', 'Wohnen', 3490, 12, 'lamp.svg'],
        [2, 'Desk Organizer', 'desk-organizer', 'Modularer Schreibtisch-Organizer für Stifte, Zubehör und kreative Ordnung.', 'Organisation', 1890, 25, 'organizer.svg'],
        [3, 'Twist Vase', 'twist-vase', 'Dekorative Vase mit gedrehter Struktur für Trockenblumen. Nicht wasserdicht.', 'Wohnen', 2490, 18, 'vase.svg'],
        [4, 'Custom Schlüsselanhänger', 'custom-schluesselanhaenger', 'Leichter, langlebiger Schlüsselanhänger aus dem 3D-Drucker. Personalisierung bitte vor Bestellung anfragen.', 'Accessoires', 790, 50, 'keychain.svg'],
    ];
    return array_map(static function (array $row): array {
        [$id, $name, $slug, $description, $category, $price, $stock, $image] = $row;
        return ['id' => $id, 'name' => $name, 'slug' => $slug, 'description' => $description,
            'category' => $category, 'price_cents' => $price, 'stock' => $stock,
            'image' => 'assets/images/' . $image, 'active' => 1, 'preview' => true];
    }, $rows);
}
