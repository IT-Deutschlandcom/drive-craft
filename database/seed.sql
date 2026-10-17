INSERT INTO products (name,slug,description,category,price_cents,stock,image) VALUES
('Spiral-Lampe','spiral-lampe','Skulpturaler, 3D-gedruckter Lampenschirm mit sanftem Lichtspiel. Leuchtmittel nicht enthalten.','Wohnen',3490,12,'assets/images/lamp.svg'),
('Desk Organizer','desk-organizer','Modularer Schreibtisch-Organizer für Stifte, Zubehör und kreative Ordnung.','Organisation',1890,25,'assets/images/organizer.svg'),
('Twist Vase','twist-vase','Dekorative Vase mit gedrehter Struktur für Trockenblumen. Nicht wasserdicht.','Wohnen',2490,18,'assets/images/vase.svg'),
('Custom Schlüsselanhänger','custom-schluesselanhaenger','Leichter, langlebiger Schlüsselanhänger aus dem 3D-Drucker. Personalisierung bitte vor Bestellung anfragen.','Accessoires',790,50,'assets/images/keychain.svg')
ON DUPLICATE KEY UPDATE slug=VALUES(slug);

INSERT INTO gallery (title,description,image,sort_order)
SELECT 'Licht & Struktur','Spiralförmiges Design aus dem 3D-Drucker.','assets/images/lamp.svg',10
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image='assets/images/lamp.svg');
INSERT INTO gallery (title,description,image,sort_order)
SELECT 'Ordnung nach Maß','Praktische Lösungen für deinen Arbeitsplatz.','assets/images/organizer.svg',20
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image='assets/images/organizer.svg');
INSERT INTO gallery (title,description,image,sort_order)
SELECT 'Form & Farbe','Dekorative Objekte mit Charakter.','assets/images/vase.svg',30
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image='assets/images/vase.svg');
INSERT INTO gallery (title,description,image,sort_order)
SELECT 'Kleine Begleiter','Individuelle Accessoires aus dem 3D-Drucker.','assets/images/keychain.svg',40
WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE image='assets/images/keychain.svg');
