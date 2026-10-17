<?php
declare(strict_types=1);

function input(string $name, int $max, bool $required = true): string
{
    $raw = $_POST[$name] ?? '';
    if (!is_string($raw)) {
        throw new DomainException('Ungültige Eingabe.');
    }
    $value = trim($raw);
    if (($required && $value === '') || strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
        || preg_match('//u', $value) !== 1) {
        throw new DomainException('Bitte fülle alle Felder gültig aus.');
    }
    return $value;
}

function positive_id(string $name): int
{
    $value = filter_var($_POST[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false || $value === null) {
        throw new DomainException('Ungültige Kennung.');
    }
    return $value;
}

function email_input(): string
{
    $email = strtolower(input('email', 254));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpbrk($email, "\r\n") !== false) {
        throw new DomainException('Bitte gib eine gültige E-Mail-Adresse ein.');
    }

    function require_consent(string $field, string $message): void
    {
        if (($_POST[$field] ?? null) !== '1') {
            throw new DomainException($message);
        }
    }
    return $email;
}

function strong_password(string $password): bool
{
    return strlen($password) >= 12 && strlen($password) <= 72
        && preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password) && preg_match('/[0-9]/', $password);
}

function throttle_login(string $email): void
{
    $secret = (string) config('app.key', '');
    if (strlen($secret) < 32) {
        throw new RuntimeException('Application key not configured.');
    }
    // Separate email and IP buckets resist session resets and distributed guessing.
    $keys = [
        [hash_hmac('sha256', 'email:' . $email, $secret), 10],
        [hash_hmac('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $secret), 50],
    ];
    foreach ($keys as [$key, $limit]) {
        db()->beginTransaction();
        try {
            query('INSERT IGNORE INTO login_throttles (throttle_key,window_start) VALUES (?,UTC_TIMESTAMP())', [$key]);
            $row = query('SELECT *,UNIX_TIMESTAMP(window_start) AS started,UNIX_TIMESTAMP(blocked_until) AS blocked FROM login_throttles WHERE throttle_key=? FOR UPDATE', [$key])->fetch();
            if ((int) ($row['blocked'] ?? 0) > time()) {
                db()->commit();
                throw new DomainException('Zu viele Anmeldeversuche. Bitte warte 15 Minuten.');
            }
            $attempts = (int) $row['started'] < time() - 900 ? 1 : (int) $row['attempts'] + 1;
            query('UPDATE login_throttles SET attempts=?,window_start=?,blocked_until=? WHERE throttle_key=?', [
                $attempts, $attempts === 1 ? gmdate('Y-m-d H:i:s') : $row['window_start'],
                $attempts >= $limit ? gmdate('Y-m-d H:i:s', time() + 900) : null, $key,
            ]);
            db()->commit();
            if ($attempts >= $limit) {
                throw new DomainException('Zu viele Anmeldeversuche. Bitte warte 15 Minuten.');
            }
        } catch (Throwable $error) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $error;
        }
    }
}

function authenticate_session(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['checkout_order_id']);
}

function image_input(): string
{
    $image = input('image', 255);
    if (!preg_match('~^assets/images/[a-zA-Z0-9_-]+\.(?:svg|png|jpe?g|webp)$~D', $image)
        || !is_file(APP_ROOT . '/' . $image)) {
        throw new DomainException('Bitte wähle ein vorhandenes lokales Produktbild.');
    }
    return $image;
}

function handle_action(): never
{
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $destination = match ($action) {
        'login', 'register' => $action,
        'cart_add', 'cart_update', 'cart_remove', 'checkout' => 'cart',
        'ticket_create', 'ticket_reply' => 'tickets',
        'admin_product_save', 'admin_gallery_save', 'admin_order_update', 'admin_ticket_update' => 'admin',
        'contact' => 'contact',
        default => 'home',
    };
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new DomainException('Diese Aktion benötigt eine POST-Anfrage.');
        }
        if (!valid_csrf($_POST['csrf_token'] ?? null)) {
            throw new DomainException('Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.');
        }
        session_rate_limit('actions', 100, 60);
        switch ($action) {
            case 'register':
                session_rate_limit('register', 5, 3600);
                require_consent('privacy', 'Bitte bestätige, dass du die Datenschutzhinweise gelesen hast.');
                $name = input('name', 120);
                $email = email_input();
                $password = $_POST['password'] ?? '';
                if (!is_string($password) || !strong_password($password)) {
                    throw new DomainException('Dein Passwort benötigt 12–72 Zeichen, Groß- und Kleinbuchstaben sowie eine Zahl.');
                }
                throttle_login($email);
                try {
                    query('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                } catch (PDOException $error) {
                    if ($error->getCode() === '23000') {
                        throw new DomainException('Registrierung nicht möglich. Bitte nutze die Anmeldung oder eine andere E-Mail-Adresse.');
                    }
                    throw $error;
                }
                authenticate_session((int) db()->lastInsertId());
                flash('success', 'Dein Konto wurde erstellt.');
                redirect('account');
            case 'login':
                session_rate_limit('login', 10, 900);
                $email = email_input();
                throttle_login($email);
                $password = $_POST['password'] ?? '';
                $user = query('SELECT * FROM users WHERE email=?', [$email])->fetch();
                $valid = is_string($password) && strlen($password) <= 72 && password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
                if (!$valid || !$user) {
                    throw new DomainException('E-Mail-Adresse oder Passwort ist nicht korrekt.');
                }
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                authenticate_session((int) $user['id']);
                flash('success', 'Willkommen zurück!');
                redirect($user['role'] === 'admin' ? 'admin' : 'account');
            case 'logout':
                $_SESSION = [];
                session_regenerate_id(true);
                flash('success', 'Du wurdest abgemeldet.');
                redirect('home');
            case 'cart_add':
            case 'cart_update':
            case 'cart_remove':
                if (!config('db.name')) {
                    throw new DomainException('Der Shop ist noch nicht eingerichtet. Der Katalog ist derzeit nur eine Vorschau.');
                }
                prepare_cart_change();
                $id = positive_id('product_id');
                $item = product($id);
                $quantity = $action === 'cart_remove' ? 0 : filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
                if ($quantity === false || $quantity === null) {
                    throw new DomainException('Bitte wähle eine Menge zwischen 0 und 99.');
                }
                if ($action === 'cart_add') {
                    $quantity += (int) ($_SESSION['cart'][$id] ?? 0);
                }
                if ($quantity > 0 && (!$item || $quantity > 99 || $quantity > (int) $item['stock'])) {
                    throw new DomainException('Diese Menge ist derzeit nicht verfügbar.');
                }
                if ($quantity === 0) {
                    unset($_SESSION['cart'][$id]);
                } else {
                    if (count($_SESSION['cart'] ?? []) >= 50 && !isset($_SESSION['cart'][$id])) {
                        throw new DomainException('Der Warenkorb enthält zu viele verschiedene Artikel.');
                    }
                    $_SESSION['cart'][$id] = $quantity;
                }
                flash('success', 'Dein Warenkorb wurde aktualisiert.');
                redirect('cart');
            case 'checkout':
                $user = require_user();
                require_consent('terms', 'Bitte bestätige die AGB und Widerrufsbelehrung, bevor du bestellst.');
                session_rate_limit('checkout', 5, 300);
                $address = [
                    'shipping_name' => input('shipping_name', 120), 'address1' => input('address1', 160),
                    'address2' => input('address2', 160, false), 'postal_code' => input('postal_code', 20),
                    'city' => input('city', 100), 'country' => strtoupper(input('country', 2)),
                ];
                if (!in_array($address['country'], ['DE'], true) || !preg_match('/^[0-9]{5}$/D', $address['postal_code'])) {
                    throw new DomainException('Der Versand ist derzeit nur an gültige deutsche Adressen möglich.');
                }
                $target = checkout_create($user, $address);
                header('Location: ' . $target, true, 303);
                exit;
            case 'ticket_create':
                $user = require_user();
                session_rate_limit('tickets', 10, 3600);
                $subject = input('subject', 160);
                $message = input('message', 10000);
                db()->beginTransaction();
                try {
                    query('INSERT INTO tickets (user_id,subject) VALUES (?,?)', [$user['id'], $subject]);
                    $id = (int) db()->lastInsertId();
                    query('INSERT INTO ticket_replies (ticket_id,user_id,message) VALUES (?,?,?)', [$id, $user['id'], $message]);
                    db()->commit();
                } catch (Throwable $error) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    throw $error;
                }
                flash('success', 'Deine Anfrage wurde gespeichert.');
                redirect('ticket', ['id' => $id]);
            case 'ticket_reply':
                $user = require_user();
                session_rate_limit('ticket_replies', 30, 3600);
                $id = positive_id('ticket_id');
                $ticket = ticket_details($id);
                if (!$ticket || $ticket['status'] !== 'open') {
                    throw new DomainException('Dieses Ticket ist nicht verfügbar oder geschlossen.');
                }
                $message = input('message', 10000);
                db()->beginTransaction();
                try {
                    query('INSERT INTO ticket_replies (ticket_id,user_id,message) VALUES (?,?,?)', [$id, $user['id'], $message]);
                    query('UPDATE tickets SET updated_at=UTC_TIMESTAMP() WHERE id=?', [$id]);
                    db()->commit();
                } catch (Throwable $error) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    throw $error;
                }
                flash('success', 'Deine Antwort wurde gespeichert.');
                redirect('ticket', ['id' => $id]);
            case 'contact':
                session_rate_limit('contact', 5, 3600);
                require_consent('privacy', 'Bitte bestätige, dass du die Datenschutzhinweise gelesen hast.');
                $name = input('name', 120);
                $email = email_input();
                $subject = input('subject', 160);
                $message = input('message', 10000);
                query('INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)', [$name, $email, $subject, $message]);
                $id = (int) db()->lastInsertId();
                $sent = false;
                $from = (string) config('shop.mail_from', 'info@drive-craft-3d.de');
                if (filter_var($from, FILTER_VALIDATE_EMAIL) && strpbrk($from, "\r\n") === false) {
                    try {
                        $sent = @mail('info@drive-craft-3d.de', 'Neue Kontaktanfrage #' . $id,
                            "Name: $name\nE-Mail: $email\nBetreff: $subject\n\n$message",
                            ['From' => $from, 'Reply-To' => $email, 'Content-Type' => 'text/plain; charset=UTF-8']);
                    } catch (Throwable) {
                        $sent = false;
                    }
                }
                query('UPDATE contact_messages SET mail_sent=? WHERE id=?', [(int) $sent, $id]);
                app_log('contact_saved', ['message_id' => $id, 'mail_sent' => $sent]);
                flash('success', 'Vielen Dank! Deine Nachricht wurde gespeichert. Wir melden uns bei dir.');
                redirect('contact');
            case 'admin_product_save':
                require_admin();
                $id = empty($_POST['id']) ? null : positive_id('id');
                $name = input('name', 160);
                $description = input('description', 20000);
                $category = input('category', 80);
                $price = filter_var($_POST['price_cents'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 50, 'max_range' => 9999999]]);
                $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
                if ($price === false || $price === null || $stock === false || $stock === null) {
                    throw new DomainException('Preis und Lagerbestand müssen gültige Ganzzahlen sein.');
                }
                $image = image_input();
                $active = isset($_POST['active']) && $_POST['active'] !== '0' ? 1 : 0;
                if ($id) {
                    query('UPDATE products SET name=?,description=?,category=?,price_cents=?,stock=?,image=?,active=? WHERE id=?', [$name, $description, $category, $price, $stock, $image, $active, $id]);
                } else {
                    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'produkt';
                    $slug .= '-' . bin2hex(random_bytes(4));
                    query('INSERT INTO products (name,slug,description,category,price_cents,stock,image,active) VALUES (?,?,?,?,?,?,?,?)', [$name, $slug, $description, $category, $price, $stock, $image, $active]);
                }
                flash('success', 'Produkt gespeichert.');
                redirect('admin', ['tab' => 'products']);
            case 'admin_gallery_save':
                require_admin();
                $id = empty($_POST['id']) ? null : positive_id('id');
                $title = input('title', 160);
                $description = input('description', 10000, false);
                $image = image_input();
                $sort = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1000000, 'max_range' => 1000000]]);
                if ($sort === false) {
                    throw new DomainException('Ungültige Sortierung.');
                }
                $active = isset($_POST['active']) && $_POST['active'] !== '0' ? 1 : 0;
                if ($id) {
                    query('UPDATE gallery SET title=?,description=?,image=?,sort_order=?,active=? WHERE id=?', [$title, $description, $image, $sort, $active, $id]);
                } else {
                    query('INSERT INTO gallery (title,description,image,sort_order,active) VALUES (?,?,?,?,?)', [$title, $description, $image, $sort, $active]);
                }
                flash('success', 'Galerieeintrag gespeichert.');
                redirect('admin', ['tab' => 'gallery']);
            case 'admin_order_update':
                require_admin();
                $id = positive_id('order_id');
                $status = input('status', 30);
                if (!in_array($status, ['processing', 'printing', 'shipped', 'completed'], true)) {
                    throw new DomainException('Ungültiger Bestellstatus. Stornierungen und Erstattungen erfolgen nach Abgleich in Stripe.');
                }
                $changed = query("UPDATE orders SET status=? WHERE id=? AND payment_status='paid'", [$status, $id])->rowCount();
                if (!$changed) {
                    throw new DomainException('Nur bezahlte Bestellungen können bearbeitet werden; der Status ist eventuell bereits gesetzt.');
                }
                app_log('admin_order_updated', ['order_id' => $id, 'admin_id' => current_user()['id']]);
                flash('success', 'Bestellstatus gespeichert.');
                redirect('admin', ['tab' => 'orders']);
            case 'admin_ticket_update':
                require_admin();
                $id = positive_id('ticket_id');
                $status = input('status', 10);
                if (!in_array($status, ['open', 'closed'], true)) {
                    throw new DomainException('Ungültiger Ticketstatus.');
                }
                query('UPDATE tickets SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?', [$status, $id]);
                flash('success', 'Ticketstatus gespeichert.');
                redirect('ticket', ['id' => $id]);
            default:
                throw new DomainException('Unbekannte Aktion.');
        }
    } catch (DomainException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        app_log('action_failed', ['action' => substr($action, 0, 50), 'error_class' => get_class($error)]);
        flash('error', 'Die Anfrage konnte nicht verarbeitet werden. Bitte versuche es später erneut.');
    }
    redirect($destination);
}
