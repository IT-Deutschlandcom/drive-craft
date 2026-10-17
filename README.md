# Drive-Craft-3D

Buildfreier, deutschsprachiger Onlineshop für individuellen 3D-Druck: **PHP, HTML, CSS, JavaScript und MySQL**, ausgelegt für **Windows IIS mit FastCGI**. Kein Node.js, Composer, Framework oder Build-Schritt erforderlich. Bootstrap 5.3.8 wird als versioniertes Stylesheet mit Integritätsprüfung vom jsDelivr-CDN geladen; die eigenen JavaScript-Effekte benötigen keine Bibliothek.

## Funktionen

- Responsive Dark UI mit dauerhaft umschaltbarem Light-Modus, Animationen mit Berücksichtigung reduzierter Bewegung, Produktfiltern und Galerie-Lightbox.
- Startseite mit Produkt- und Galerievorschau, Shop, Produktdetails, Galerie, Über uns, Kontakt und konfigurierbaren Rechtstexten.
- Registrierung, Anmeldung, Kundenbestellungen, Warenkorb und persönliche Projekt-/Supporttickets mit Antworten.
- Administrationsbereich für Produkte, Lagerbestand, Galerie, Bestellbearbeitung, Kundenübersicht, Tickets und gespeicherte Kontaktanfragen.
- Stripe Hosted Checkout in EUR. Preise und Versand werden serverseitig in Cent berechnet; Lagerreservierungen und Bestell-Snapshots erfolgen transaktional.
- Zahlungsbestätigung nur über signierte Stripe-Webhooks, einschließlich asynchroner Zahlungen und wiederholter Zustellungen. Rückleitungs-URLs bestätigen **keine** Zahlung.
- PDF-Rechnungen für bezahlte Bestellungen, ausschließlich für den jeweiligen Kunden und Administratoren zugänglich.
- Kontaktformular mit Empfänger **info@drive-craft-3d.de**. Nachrichten bleiben auch bei fehlgeschlagenem Mailversand im Adminpostfach erhalten.

Die mitgelieferten Produkte, Preise und SVG-Illustrationen sind **Beispieldaten**, keine tatsächlichen Produktangebote. Material, Abmessungen, Lieferzeiten, Produktsicherheit und tatsächliche Bilder sind vor Verkaufsstart zu ergänzen.

## Voraussetzungen

- Windows Server mit IIS, CGI/FastCGI, Request Filtering und HTTPS-Zertifikat.
- PHP **8.2 oder neuer**, passende NTS-Version für IIS; aktivierte Erweiterungen `pdo_mysql`, `curl`, `iconv`, `openssl` und Sessions.
- MySQL **8.0 oder neuer**, InnoDB und `utf8mb4`.
- Ausgehendes HTTPS zu `api.stripe.com`; Besucher benötigen Zugriff auf `cdn.jsdelivr.net` für Bootstrap.
- Für den Mailversand ein in PHP/Windows eingerichteter SMTP-Relay. Es werden keine SMTP-Zugangsdaten in Quellcode oder Formularen hinterlegt.

## Installation auf IIS

Die folgenden Pfade sind Beispiele für eine Installation unter `C:\inetpub\wwwroot\drive-craft-3d`.

1. Repository in dieses Verzeichnis kopieren. In IIS eine Website mit diesem physischen Pfad und HTTPS-Bindung einrichten. PHP über FastCGI auf `php-cgi.exe` abbilden, z. B. `C:\PHP\php-cgi.exe`. PHP-Dateien dürfen niemals als statischer Quelltext ausgeliefert werden.
2. MySQL-Datenbank `drive_craft` und einen eigenen Datenbankbenutzer anlegen. Der Installer benötigt `CREATE`, `ALTER`, `INDEX`, `REFERENCES` sowie `SELECT`, `INSERT`, `UPDATE`, `DELETE` auf dieser Datenbank. Für den laufenden Shop anschließend möglichst auf `SELECT`, `INSERT`, `UPDATE`, `DELETE` einschränken.
3. `C:\inetpub\wwwroot\drive-craft-3d\config.example.php` nach `C:\inetpub\wwwroot\drive-craft-3d\config.php` kopieren. Diese lokale Datei ist von Git ausgeschlossen.
4. Datenbankverbindung, öffentliche `app.url` inklusive eventuellem Unterverzeichnis und einen zufälligen `app.key` konfigurieren. Schlüssel erzeugen:

   ```powershell
   C:\PHP\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

5. `app.private_dir` auf ein **absolutes Verzeichnis außerhalb des Webroots** setzen, z. B. `C:\DriveCraftPrivate`. Das übergeordnete Verzeichnis muss existieren. Der Installer legt das Verzeichnis an. Der IIS-App-Pool benötigt dort Änderungsrechte sowie auf PHPs Session-Verzeichnis Schreibrechte. Für den Anwendungscode nur Leserechte vergeben. Das private Verzeichnis darf weder als IIS-Anwendung noch als virtuelles Verzeichnis veröffentlicht werden.
6. Ersten Administrator ausschließlich auf der Kommandozeile anlegen:

   ```powershell
   $env:ADMIN_EMAIL = Read-Host "Administrator-E-Mail"
   $env:ADMIN_PASSWORD = [Net.NetworkCredential]::new('', (Read-Host "Administrator-Passwort" -AsSecureString)).Password
   C:\PHP\php.exe C:\inetpub\wwwroot\drive-craft-3d\install.php
   Remove-Item Env:ADMIN_PASSWORD
   Remove-Item Env:ADMIN_EMAIL
   ```

   Das Passwort benötigt 12–72 Zeichen mit Groß-/Kleinbuchstaben und einer Zahl. Es gibt **keine Standard-Zugangsdaten**. Ein erneuter Aufruf überschreibt keine vorhandenen Administrator-Zugangsdaten. Der Installer ist über HTTP grundsätzlich gesperrt.

7. Prüfen, dass IIS `C:\inetpub\wwwroot\drive-craft-3d\web.config` anwendet. Sie sperrt interne Verzeichnisse, Konfiguration, Git-Dateien und Installer. Es sind **keine Rewrite-Regeln und kein URL-Rewrite-Modul** erforderlich; Navigation erfolgt über `index.php?page=…`.
8. In PHP `display_errors=Off`, `log_errors=On` und einen privaten Fehlerlog konfigurieren. `app.session_secure` in Produktion auf `true` belassen. HTTPS erzwingen, HTTP-Bindung entsprechend absichern und Backups verschlüsselt ablegen.

Wenn keine Datenbank konfiguriert ist, sind die öffentlichen Informationsseiten mit Einrichtungshinweis aufrufbar. Bei nicht erreichbarer oder noch nicht installierter Datenbank wird eine neutrale Wartungsseite statt interner Fehlermeldungen ausgeliefert.

## Vor dem Verkaufsstart

Der Checkout ist standardmäßig gesperrt. Keine Platzhalterdaten als echte Unternehmensangaben verwenden.

- Unter `company` vollständige Firmierung, Inhaber, ladungsfähige Anschrift, Kontaktangaben sowie Steuernummer bzw. USt-IdNr. hinterlegen.
- Betreiberbezogene, fachlich geprüfte Rechtstexte unter `shop.legal_notice`, `shop.privacy_text`, `shop.terms_text` und `shop.withdrawal_text` eintragen. Diese werden als Text, nicht als ungeprüftes HTML, ausgegeben.
- Die tatsächlichen Datenschutzprozesse einschließlich Hosting, Stripe, jsDelivr, Aufbewahrung, Betroffenenrechten und Kontaktformular dokumentieren. Die mitgelieferte Anwendung ersetzt keine Rechtsberatung.
- `shop.shipping_cents` (Vorgabe: 490 Cent) und `shop.vat_percent` (Vorgabe: 19 %) prüfen. Lieferung und Rechnungsadresse sind identisch; Versand wird ausschließlich nach Deutschland angeboten. Die Anwendung verwendet einen einheitlichen Umsatzsteuersatz, keine Kleinunternehmerregelung oder gemischten Steuersätze.
- Produkte und Lagerbestände im Adminbereich prüfen. Eigene Bilder können beim Deployment in `C:\inetpub\wwwroot\drive-craft-3d\assets\images` abgelegt und dann ausgewählt werden. Erlaubte Namen: Buchstaben, Ziffern, Bindestrich/Unterstrich; SVG, PNG, JPG, JPEG oder WebP. Nur vertrauenswürdige eigene SVGs einsetzen.
- Stripe im Testmodus vollständig prüfen. Anschließend reale Stripe-Schlüssel konfigurieren, `company.legal_complete` bestätigen und `shop.allow_sale` auf `true` setzen.

## Stripe einrichten

1. `stripe.secret_key` und `stripe.webhook_secret` in der nicht versionierten Konfiguration hinterlegen.
2. Einen HTTPS-Webhook auf `https://IHRE-DOMAIN/index.php?page=webhook` registrieren, bei Unterverzeichnisinstallation entsprechend angepasst.
3. Diese Ereignisse abonnieren:
   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
   - `checkout.session.async_payment_failed`
   - `checkout.session.expired`
4. Im Stripe-Dashboard die gewünschten Zahlungsarten freischalten. Kartendaten werden ausschließlich auf Stripe-Seiten verarbeitet.
5. Erfolgreiche Zahlung, Abbruch, Ablauf, verzögerte Zahlung und erneute Webhook-Zustellung im Stripe-Testmodus durchspielen. Produktions- und Test-Webhook-Geheimnisse nicht verwechseln.

Ein fehlerhafter API-Aufruf gibt reservierten Bestand nicht blind frei: Bei Netzwerkfehlern könnte Stripe die Session bereits angelegt haben. Wiederholungen verwenden denselben Idempotenzschlüssel. Fehlende Webhooks im Stripe-Dashboard erneut senden.

Für die Bestandsabstimmung lässt sich `C:\inetpub\wwwroot\drive-craft-3d\install.php --reconcile` als Windows-Aufgabe ausführen. Dabei werden nur von Stripe bestätigte, abgelaufene, unbezahlte Sessions freigegeben. Unklare Sessions ohne gespeicherte Stripe-ID müssen anhand der Bestellnummer im Stripe-Dashboard und privaten Anwendungslog geprüft werden. Keine Reservierungen allein anhand eines Zeitstempels oder einer Browser-Rückleitung löschen.

### Rechnungen und Buchhaltung

Nach bestätigter Zahlung stehen PDF-Rechnungen im Kundenkonto und in der Admin-Bestellübersicht bereit. Preis-, Steuer-, Kunden- und Verkäuferdaten werden bei Bestellung eingefroren. Die PDF-Funktion benötigt keine externe Bibliothek. Beide Rollen erhalten denselben Beleg.

Die PDFs sind **keine XRechnung/ZUGFeRD-Dateien**; Anforderungen an E-Rechnung, Leistungsdatum, Aufbewahrung und GoBD müssen für den tatsächlichen Geschäftsbetrieb geprüft werden. Erstattungen erfolgen im Stripe-Dashboard und sind separat buchhalterisch mit passenden Korrekturbelegen zu bearbeiten; eine automatische Erstattungs-/Gutschriftenverwaltung ist nicht enthalten.

## Sicherheit und Betrieb

- PDO mit vorbereiteten Abfragen, Passwort-Hashes, erneuerte Session-IDs, CSRF-Token und rollen-/eigentümerbezogene Zugriffsprüfung.
- Datenbankgestützte Login-Drosselung sowie zusätzliche Session-Limits. Auf IIS/Reverse-Proxy zusätzlich IP-basierte Missbrauchsbegrenzung vorsehen.
- Keine öffentlichen Uploads und keine vom Kunden vorgegebenen Zahlungspreise.
- Geschäftsdaten nicht aus dem HTTP-Host-Header ableiten. Stripe-Signaturen haben eine begrenzte Zeitgültigkeit.
- Regelmäßige Sicherung von MySQL, privatem Verzeichnis und sicher verwahrter Konfiguration; Wiederherstellung testen. Secrets nicht ins Repository aufnehmen.
- Datensparsame private Betriebslogs und definierte Löschfristen für Kontaktanfragen, Tickets und Login-Drosselung einrichten. Rechnungs-/Bestellaufbewahrung getrennt bewerten.
- Keine automatischen Passwort-Resets, E-Mail-Verifikation, Rabattcodes, Versanddienstleisterintegration oder Kundendateiuploads. Kundenübersicht ist bewusst nur lesbar; Rollenvergabe darf nicht durch öffentliche Formulare erfolgen.
- PHPs Entwicklungsserver ist **kein Produktionsserver** und wertet IIS-Schutzregeln nicht aus.

## Prüfungen

Es ist kein Build erforderlich. PHP-Syntaxprüfung und die enthaltenen Backendprüfungen können direkt ausgeführt werden:

```powershell
Get-ChildItem C:\inetpub\wwwroot\drive-craft-3d -Filter *.php -Recurse |
    Where-Object { $_.Name -ne 'config.php' } |
    ForEach-Object { & C:\PHP\php.exe -l $_.FullName }
C:\PHP\php.exe C:\inetpub\wwwroot\drive-craft-3d\tests\backend.php
```

Vor Livebetrieb außerdem auf dem tatsächlichen IIS-Host prüfen: nicht autorisierte Rechnungs-/Ticketzugriffe, CSRF-Abweisung, gesperrte interne Pfade, HTTPS-Session-Cookies, Mobilansicht, Dark-/Light-Umschaltung, SMTP-Zustellung und den vollständigen Stripe-Testablauf.