<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function lower_email(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value); }
function contains_ci(string $haystack, string $needle): bool { return $needle === '' || (function_exists('mb_stripos') ? mb_stripos($haystack, $needle, 0, 'UTF-8') !== false : stripos($haystack, $needle) !== false); }

function site_url(string $path = ''): string {
    if (APP_BASE_URL !== '') $base = rtrim(APP_BASE_URL, '/');
    else {
        $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        if ($dir === '/' || $dir === '.' || $dir === '\\') $dir = '';
        $base = $scheme . '://' . $host . rtrim($dir, '/');
    }
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never { header('Location: ' . (preg_match('~^https?://~i', $path) ? $path : site_url($path))); exit; }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return (string)$_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    $sent = (string)($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals((string)($_SESSION['_csrf'] ?? ''), $sent)) {
        http_response_code(419); exit('La sesión del formulario venció. Volvé atrás, recargá y probá nuevamente.');
    }
}
function set_flash(string $type, string $message): void { $_SESSION['_flash'][] = [$type, $message]; }
function take_flashes(): array { $items = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return is_array($items) ? $items : []; }

function installation_complete(): bool {
    try {
        $q = db()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $q->execute(['installation_complete']);
        return (string)$q->fetchColumn() === '1';
    } catch (Throwable) { return false; }
}
function require_installation(): void {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php' && !installation_complete()) redirect('install.php');
}
function setting(string $key, string $default = ''): string {
    try {
        $q = db()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $q->execute([$key]);
        $v = $q->fetchColumn();
        return $v === false ? $default : (string)$v;
    } catch (Throwable) { return $default; }
}
function save_setting(string $key, string $value): void {
    $q = db()->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
    $q->execute([$key, $value]);
}
function current_user(): ?array {
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id < 1) return null;
    try {
        $cols = 'id,name,business,phone,email,role,wholesale_approved,created_at';
        if (users_have_perms_column()) $cols .= ',perms';
        $q = db()->prepare("SELECT $cols FROM users WHERE id=? LIMIT 1");
        $q->execute([$id]);
        $u = $q->fetch();
        if (!$u) { unset($_SESSION['user_id']); return null; }
        if (!isset($u['perms'])) $u['perms'] = '';
        return $u;
    } catch (Throwable) { return null; }
}
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function is_employee(): bool { return (current_user()['role'] ?? '') === 'employee'; }
/** ¿La sesión actual pertenece a alguien que puede entrar al panel (admin o empleado)? */
function is_staff(): bool { $r = (string)(current_user()['role'] ?? ''); return $r === 'admin' || $r === 'employee'; }
function is_verified(): bool { $u = current_user(); return (bool)($u && ((int)$u['wholesale_approved'] === 1 || $u['role'] === 'admin')); }
function require_login(): void { if (!current_user()) { set_flash('error', 'Ingresá con tu cuenta para continuar.'); redirect('cuenta.php'); } }
function require_admin(): void { require_login(); if (!is_admin()) { http_response_code(403); exit('No tenés permisos para acceder a esta sección.'); } }

/* ── Panel: pestañas y permisos por usuario ──────────────────────────── */

/** Todas las pestañas del panel, en orden de aparición. */
function panel_tabs(): array {
    return ['clientes', 'pedidos', 'productos', 'banners', 'presupuestos', 'mayoristas', 'configuracion'];
}
/** Pestañas que un administrador puede delegar a un empleado. */
function delegable_tabs(): array {
    return ['clientes', 'pedidos', 'productos', 'presupuestos'];
}
/** Etiquetas legibles de cada pestaña (para la gestión de usuarios). */
function panel_tab_labels(): array {
    return [
        'clientes'      => '👥 Clientes',
        'pedidos'       => '🛒 Pedidos',
        'productos'     => '📦 Productos',
        'banners'       => '🖼️ Banners',
        'presupuestos'  => '📝 Presupuestos',
        'mayoristas'    => '🏪 Página Mayoristas',
        'configuracion' => '⚙️ Configuración',
    ];
}
/**
 * Pestañas permitidas para un usuario. Los administradores ven todo;
 * los empleados solo lo que se les habilitó (columna users.perms).
 */
function staff_perms(?array $user = null): array {
    $u = $user ?? current_user();
    if (!$u) return [];
    if ((string)($u['role'] ?? '') === 'admin') return panel_tabs();
    $list = array_filter(array_map('trim', explode(',', (string)($u['perms'] ?? ''))));
    return array_values(array_intersect($list, delegable_tabs()));
}
/** ¿El usuario actual puede ver/operar la pestaña $tab? */
function can(string $tab, ?array $user = null): bool {
    return in_array($tab, staff_perms($user), true);
}
/** Exige sesión de panel (y, si se indica, permiso sobre esa pestaña). */
function require_staff(string $tab = ''): void {
    require_login();
    if (!is_staff()) { http_response_code(403); exit('No tenés permisos para acceder a esta sección.'); }
    if ($tab !== '' && !can($tab)) { http_response_code(403); exit('No tenés permisos para acceder a esta sección.'); }
}

/* ── Migraciones de esquema (idempotentes, se auto-reparan) ──────────── */

/** ¿La tabla users ya tiene la columna perms? Si no, la crea. */
function users_have_perms_column(): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $has = (bool)db()->query("SHOW COLUMNS FROM users LIKE 'perms'")->fetch();
        if (!$has) {
            db()->exec("ALTER TABLE users ADD COLUMN perms VARCHAR(255) NOT NULL DEFAULT '' AFTER role");
            $has = (bool)db()->query("SHOW COLUMNS FROM users LIKE 'perms'")->fetch();
        }
    } catch (Throwable) { $has = false; }
    return $has;
}

/** ¿El enum role admite 'employee'? Si no, lo amplía. */
function users_support_employee_role(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $col = db()->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        $ok = (bool)($col && stripos((string)($col['Type'] ?? ''), 'employee') !== false);
        if (!$ok) {
            db()->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','customer','employee') NOT NULL DEFAULT 'customer'");
            $ok = true;
        }
    } catch (Throwable) { $ok = false; }
    return $ok;
}

/** Crea la tabla de presupuestos si no existe. */
function quotes_table_ready(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS quotes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(40) NOT NULL,
            token VARCHAR(64) NOT NULL,
            client_name VARCHAR(190) NOT NULL DEFAULT '',
            client_business VARCHAR(190) NOT NULL DEFAULT '',
            client_phone VARCHAR(60) NOT NULL DEFAULT '',
            client_email VARCHAR(190) NOT NULL DEFAULT '',
            client_address VARCHAR(255) NOT NULL DEFAULT '',
            items_json LONGTEXT NOT NULL,
            note TEXT NOT NULL,
            total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            currency_symbol VARCHAR(10) NOT NULL DEFAULT '$',
            valid_days INT NOT NULL DEFAULT 7,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_quotes_token (token),
            UNIQUE KEY uniq_quotes_number (number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ok = true;
    } catch (Throwable) { $ok = false; }
    return $ok;
}

/** Ejecuta las migraciones de esquema una sola vez (marcador en settings). */
function ensure_extended_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        if (setting('schema_extended', '') === '1') return;
        users_support_employee_role();
        users_have_perms_column();
        quotes_table_ready();
        save_setting('schema_extended', '1');
    } catch (Throwable) { /* se reintenta en el próximo request */ }
}
function require_verified(): void { require_login(); if (!is_verified()) { set_flash('error', 'Tu cuenta debe estar aprobada para consultar precios y armar pedidos.'); redirect('cuenta.php'); } }
function cart_items(): array { $cart = $_SESSION['cart'] ?? []; return is_array($cart) ? $cart : []; }
function cart_count(): int { $total = 0; foreach (cart_items() as $item) $total += max(0, (int)($item['quantity'] ?? 0)); return $total; }

function whatsapp_url(string $message = 'Hola, quisiera consultar sobre insumos para sublimación.'): string {
    $number = preg_replace('/\D+/', '', setting('whatsapp', DEFAULT_WHATSAPP));
    if ($number === '') return 'https://www.instagram.com/ser.insumos/';
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}

function http_json(string $url, string $method = 'GET', ?array $body = null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('El hosting no tiene habilitada la extensión cURL de PHP.');
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'Serinsumos-PHP/1.0'];
    if ($method !== 'GET') {
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        $opts[CURLOPT_HTTPHEADER] = array_merge($headers, ['Content-Type: application/json']);
        $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('No se pudo conectar con la tienda online: ' . $curlError);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) throw new RuntimeException('La tienda online devolvió una respuesta no válida.');
    if ($status >= 400) throw new RuntimeException((string)($data['message'] ?? 'Error de la tienda online (HTTP ' . $status . ').'));
    return $data;
}

function money_format_api(int|float|null $amount, ?array $currencyInfo): string {
    if ($amount === null || !$currencyInfo) return '';
    $digits = isset($currencyInfo['decimal_digits']) && is_numeric($currencyInfo['decimal_digits']) ? (int)$currencyInfo['decimal_digits'] : 2;
    $num = number_format(((float)$amount) / (10 ** $digits), $digits, '.', ',');
    $template = (string)($currencyInfo['template'] ?? '');
    if ($template !== '' && str_contains($template, '$1')) return str_replace('$1', $num, $template);
    return (string)($currencyInfo['symbol'] ?? $currencyInfo['code'] ?? '€') . $num;
}
function normalize_product(array $p): array {
    $variants = [];
    foreach (($p['variants'] ?? []) as $v) {
        $raw = $v['prices'][0] ?? [];
        $regular = isset($raw['amount']) ? (int)$raw['amount'] : 0;
        $sale = isset($raw['sale_amount']) && $raw['sale_amount'] !== null ? (int)$raw['sale_amount'] : null;
        $effective = ($sale !== null && $sale > 0) ? $sale : $regular;
        $info = is_array($raw['currency'] ?? null) ? $raw['currency'] : null;
        $variants[] = [
            'id' => (string)($v['id'] ?? ''), 'title' => (string)($v['title'] ?? 'Predeterminado'),
            'image_url' => (string)($v['image_url'] ?? ''), 'sku' => (string)($v['sku'] ?? ''),
            'price_in_cents' => $effective, 'regular_price' => $regular, 'sale_price' => $sale,
            'currency' => (string)($raw['currency_code'] ?? 'eur'), 'currency_info' => $info,
            'price_formatted' => money_format_api($regular, $info),
            'sale_price_formatted' => ($sale !== null && $sale > 0) ? money_format_api($sale, $info) : '',
            'effective_price_formatted' => money_format_api($effective, $info),
            'manage_inventory' => (bool)($v['manage_inventory'] ?? false),
            'inventory_quantity' => isset($v['inventory_quantity']) ? (int)$v['inventory_quantity'] : null,
            'options' => $v['options'] ?? [],
        ];
    }
    $best = null;
    foreach ($variants as $variant) {
        if ($best === null || $variant['price_in_cents'] < $best['price_in_cents']) $best = $variant;
    }
    $handle = (string)($p['url_handle'] ?? $p['slug'] ?? $p['page_settings']['seoSlug'] ?? $p['seo_settings']['slug'] ?? $p['id'] ?? '');
    return array_merge($p, [
        'id' => (string)($p['id'] ?? ''), 'handle' => $handle,
        'title' => (string)($p['title'] ?? ''), 'image' => (string)($p['thumbnail'] ?? ''),
        'description' => (string)($p['description'] ?? ''), 'purchasable' => (bool)($p['purchasable'] ?? false),
        'price_in_cents' => $best['price_in_cents'] ?? 0,
        'price_formatted' => $best['effective_price_formatted'] ?? '',
        'currency_info' => $best['currency_info'] ?? null, 'variants' => $variants,
        'seo_settings' => $p['seo_settings'] ?? [], 'type' => $p['type']['value'] ?? '',
    ]);
}
function product_api_url(string $suffix): string { return rtrim(ECOMMERCE_API_BASE, '/') . '/store/' . rawurlencode(ECOMMERCE_STORE_ID) . '/products' . $suffix; }
function get_products(int $limit = 24): array {
    $limit = max(1, min(200, $limit));
    $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/serinsumos-products-' . $limit . '.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < PRODUCT_CACHE_SECONDS) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)) return $cached;
    }
    try {
        $data = http_json(product_api_url('?limit=' . $limit . '&exclude_types=subscription'));
        $items = array_map('normalize_product', is_array($data['products'] ?? null) ? $data['products'] : []);
        $result = ['count' => (int)($data['count'] ?? count($items)), 'products' => $items, 'error' => false];
        if (is_writable(dirname($cacheFile))) @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $result;
    } catch (Throwable $ex) {
        if (is_file($cacheFile)) { $cached = json_decode((string)file_get_contents($cacheFile), true); if (is_array($cached)) { $cached['stale'] = true; return $cached; } }
        return ['count' => 0, 'products' => [], 'error' => true, 'error_message' => $ex->getMessage()];
    }
}
function get_product_by_handle(string $handle): ?array {
    $handle = trim($handle);
    if ($handle === '' || strlen($handle) > 180) return null;
    try {
        $isId = str_starts_with($handle, 'prod_');
        $url = product_api_url('/' . rawurlencode($handle) . ($isId ? '' : '?field=slug'));
        $data = http_json($url);
        if (!is_array($data['product'] ?? null)) return null;
        return normalize_product($data['product']);
    } catch (Throwable) { return null; }
}
function get_product_by_id(string $id): ?array { return get_product_by_handle_with_override($id); }
function find_variant(array $product, string $variantId): ?array {
    foreach (($product['variants'] ?? []) as $v) if (hash_equals((string)$v['id'], $variantId)) return $v;
    return null;
}
function start_hostinger_checkout(array $items): string {
    $data = http_json(rtrim(ECOMMERCE_API_BASE, '/') . '/store/' . rawurlencode(ECOMMERCE_STORE_ID) . '/checkout', 'POST', [
        'items' => $items,
        'successUrl' => site_url('checkout-result.php?result=success'),
        'cancelUrl' => site_url('checkout-result.php?result=cancelled'),
        'locale' => 'es',
    ]);
    $url = (string)($data['url'] ?? '');
    if (!preg_match('~^https://~i', $url)) throw new RuntimeException('La tienda no devolvió un enlace de pago válido.');
    return $url;
}

function render_flashes(): void {
    foreach (take_flashes() as [$type, $msg]) echo '<div class="app-flash ' . e($type) . '" role="status">' . e($msg) . '</div>';
}
function site_logo(): string {
    $custom = trim(setting('site_logo', ''));
    if ($custom !== '') {
        if (preg_match('~/(uploads/branding/[^"\'\s]+)$~i', $custom, $m)) {
            return site_url($m[1]);
        }
        return $custom;
    }
    return 'https://horizons-cdn.hostinger.com/b099170c-e218-4fd7-9bf8-fe7a8698762b/57722fa4f7c62dff5fa9bb24d5ec7180.jpg';
}

function site_favicon(): string {
    $custom = trim(setting('site_favicon', ''));
    if ($custom !== '') {
        if (preg_match('~/(uploads/branding/[^"\'\s]+)$~i', $custom, $m)) {
            return site_url($m[1]);
        }
        return $custom;
    }
    return site_url('favicon.ico');
}

function is_maintenance_mode(): bool {
    return setting('maintenance_mode', '0') === '1';
}

function render_maintenance_page(): void {
    $logo = site_logo();
    $favicon = site_favicon();
    $siteName = setting('site_name', APP_NAME);
    $waUrl = whatsapp_url('Hola Serinsumos, me comunico de urgencia durante el mantenimiento del sitio web.');
    $igHandle = instagram_handle();
    $igDisplay = '@' . ltrim($igHandle, '@');
    $igUrl = instagram_profile_url();
    ?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1622">
    <meta name="robots" content="noindex">
    <title>Sitio en mantenimiento · <?= e($siteName) ?></title>
    <link rel="icon" href="<?= e($favicon) ?>">
    <link rel="shortcut icon" href="<?= e($favicon) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@500;600;700;800&family=Hind:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #070f18;
            --panel: rgba(18, 34, 49, 0.97);
            --text: #f6f9fc;
            --muted: #a3b2c0;
            --line: rgba(255, 255, 255, 0.11);
            --blue: #39aef2;
            --pink: #ed3285;
            --yellow: #ffe72e;
            --radius: clamp(20px, 3vw, 30px);
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        html.modal-open { overflow: hidden; }
        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: clamp(14px, 4vw, 42px) clamp(12px, 4vw, 32px);
            padding-top: max(clamp(14px, 4vw, 42px), env(safe-area-inset-top));
            padding-bottom: max(clamp(14px, 4vw, 42px), env(safe-area-inset-bottom));
            font-family: 'Hind', system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--text);
            background:
                radial-gradient(1100px 620px at 50% -12%, #1a3c56 0%, #0c1a27 52%, var(--bg-deep) 100%);
            overflow-x: clip;
        }
        h1, h2, h3 { font-family: 'Rubik', system-ui, sans-serif; margin: 0; }
        p { margin: 0; }
        a { color: inherit; }
        img { max-width: 100%; height: auto; }
        .skip-link {
            position: absolute;
            left: 12px;
            top: -80px;
            z-index: 1200;
            background: #fff;
            color: #0b1622;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: top .18s ease;
        }
        .skip-link:focus { top: 12px; }

        /* Fondo decorativo */
        .maint-bg { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
        .maint-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(circle at 50% 35%, #000 0%, transparent 78%);
            -webkit-mask-image: radial-gradient(circle at 50% 35%, #000 0%, transparent 78%);
        }
        .maint-bg .orb { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .55; }
        .maint-bg .orb-1 { width: 380px; height: 380px; top: -140px; left: -110px; background: rgba(57, 174, 242, .28); }
        .maint-bg .orb-2 { width: 340px; height: 340px; bottom: -150px; right: -100px; background: rgba(237, 50, 133, .22); }
        .maint-bg .orb-3 { width: 260px; height: 260px; top: 42%; right: 6%; background: rgba(255, 231, 46, .10); }

        .maint-wrap { position: relative; z-index: 1; width: 100%; max-width: 1020px; }

        .maint-card {
            position: relative;
            width: 100%;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: linear-gradient(158deg, rgba(25, 44, 61, .97) 0%, rgba(14, 28, 41, .99) 58%, rgba(10, 21, 32, .99) 100%);
            box-shadow: 0 34px 70px -30px rgba(0, 0, 0, .85), 0 0 0 1px rgba(255, 255, 255, .04) inset;
            animation: maintIn .5s cubic-bezier(.22, .8, .3, 1) both;
        }
        @keyframes maintIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .maint-stripe {
            height: 5px;
            background: linear-gradient(90deg, var(--pink) 0 33.3%, var(--yellow) 33.3% 66.6%, var(--blue) 66.6% 100%);
        }

        /* Encabezado de marca */
        .maint-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px 20px;
            flex-wrap: wrap;
            padding: clamp(18px, 3vw, 28px) clamp(20px, 4vw, 40px) 0;
        }
        .maint-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: clamp(8px, 1.4vw, 12px) clamp(14px, 2.4vw, 22px);
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, .32);
        }
        .maint-logo { display: block; height: clamp(34px, 6.4vw, 48px); width: auto; max-width: min(230px, 46vw); object-fit: contain; }
        .maint-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255, 231, 46, .38);
            background: rgba(255, 231, 46, .10);
            color: var(--yellow);
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .9px;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .maint-badge-dot {
            width: 8px;
            height: 8px;
            flex: 0 0 auto;
            border-radius: 50%;
            background: var(--yellow);
            box-shadow: 0 0 0 0 rgba(255, 231, 46, .55);
            animation: pulseDot 2.2s ease-out infinite;
        }
        @keyframes pulseDot {
            0% { box-shadow: 0 0 0 0 rgba(255, 231, 46, .5); }
            70% { box-shadow: 0 0 0 8px rgba(255, 231, 46, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 231, 46, 0); }
        }

        /* Cuerpo */
        .maint-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, .8fr);
            gap: clamp(24px, 4vw, 52px);
            align-items: start;
            padding: clamp(24px, 4vw, 40px) clamp(20px, 4vw, 40px) clamp(26px, 4vw, 42px);
        }
        .maint-kicker {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2.4px;
            text-transform: uppercase;
            color: var(--blue);
            margin-bottom: 12px;
        }
        .maint-title {
            font-size: clamp(1.7rem, 4.2vw, 2.7rem);
            line-height: 1.14;
            letter-spacing: -.8px;
            font-weight: 800;
            color: #fff;
            text-wrap: balance;
            overflow-wrap: break-word;
        }
        .maint-desc {
            margin-top: 16px;
            font-size: clamp(14.5px, 1.6vw, 16px);
            line-height: 1.65;
            color: var(--muted);
            max-width: 46ch;
        }
        .maint-desc strong { color: #e5eef6; font-weight: 600; }

        .maint-cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: clamp(22px, 3.4vw, 30px); }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 52px;
            padding: 14px 22px;
            border-radius: 14px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            flex: 1 1 210px;
            transition: transform .18s ease, background .18s ease, border-color .18s ease, box-shadow .18s ease;
        }
        .btn svg { width: 20px; height: 20px; flex: 0 0 auto; }
        .btn-primary {
            background: var(--yellow);
            color: #0b1319;
            box-shadow: 0 14px 30px -14px rgba(255, 231, 46, .7);
        }
        .btn-outline {
            background: rgba(255, 255, 255, .04);
            border-color: rgba(255, 255, 255, .22);
            color: var(--text);
        }
        @media (hover: hover) {
            .btn-primary:hover { background: #fff04f; transform: translateY(-2px); }
            .btn-outline:hover { border-color: var(--blue); color: #fff; background: rgba(57, 174, 242, .12); }
            .maint-facts a:hover { border-color: rgba(57, 174, 242, .45); background: rgba(57, 174, 242, .08); }
            .admin-link:hover { color: var(--blue); }
            .contact-action:hover { text-decoration: underline; }
        }
        .btn:active { transform: scale(.99); }
        .btn:focus-visible, .admin-link:focus-visible, .modal-close:focus-visible, .contact-action:focus-visible {
            outline: 3px solid var(--blue);
            outline-offset: 3px;
        }

        .maint-note {
            margin-top: 16px;
            font-size: 12.5px;
            line-height: 1.5;
            color: #8496a6;
        }

        /* Panel de datos rápidos */
        .maint-aside {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255, 255, 255, .035);
            padding: clamp(16px, 2.4vw, 22px);
        }
        .aside-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: #8fa6b8;
            margin-bottom: 6px;
        }
        .maint-facts { list-style: none; margin: 0; padding: 0; }
        .maint-facts li { border-bottom: 1px solid var(--line); }
        .maint-facts li:last-child { border-bottom: 0; }
        .maint-facts a, .maint-facts .fact {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 2px;
            min-height: 56px;
            text-decoration: none;
            border-radius: 10px;
            border: 1px solid transparent;
            transition: background .18s ease, border-color .18s ease;
        }
        .fact-ico {
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: rgba(57, 174, 242, .12);
            color: #8ed4ff;
        }
        .fact-ico svg { width: 18px; height: 18px; display: block; }
        .fact-txt { min-width: 0; }
        .fact-txt b { display: block; font-size: 13px; font-weight: 600; color: #dbe7f0; line-height: 1.3; }
        .fact-txt span { display: block; font-size: 12.5px; color: var(--muted); line-height: 1.4; overflow-wrap: anywhere; }
        .fact-arrow { margin-left: auto; color: #6e8294; font-size: 16px; line-height: 1; flex: 0 0 auto; }

        /* Pie */
        .maint-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px 18px;
            flex-wrap: wrap;
            padding: 12px clamp(20px, 4vw, 40px) 14px;
            border-top: 1px solid var(--line);
            background: rgba(6, 13, 21, .35);
            font-size: 12px;
            color: #8496a6;
        }
        .admin-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 44px;
            padding: 8px 0;
            color: #b9c8d5;
            font-weight: 600;
            text-decoration: none;
        }

        /* Modal de contacto */
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(3, 8, 14, .76);
            opacity: 0;
            visibility: hidden;
            transition: opacity .22s ease, visibility .22s ease;
            overflow-y: auto;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card {
            width: 100%;
            max-width: 460px;
            max-height: calc(100vh - 40px);
            max-height: calc(100dvh - 40px);
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            background: #fff;
            color: #0f172a;
            border-radius: 22px;
            padding: clamp(20px, 4vw, 28px);
            box-shadow: 0 30px 70px -20px rgba(0, 0, 0, .6);
            transform: translateY(12px) scale(.97);
            transition: transform .24s cubic-bezier(.34, 1.3, .64, 1);
        }
        .modal-overlay.active .modal-card { transform: translateY(0) scale(1); }
        .modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 16px; }
        .modal-header h2 { font-size: 20px; line-height: 1.25; font-weight: 800; color: #0f172a; }
        .modal-header .modal-sub { margin-top: 4px; font-size: 13px; line-height: 1.45; color: #64748b; }
        .modal-close {
            flex: 0 0 auto;
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            transition: background .18s ease, color .18s ease;
        }
        .modal-close:hover { background: #e2e8f0; color: #0f172a; }
        .btn-whatsapp {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 50px;
            padding: 14px;
            margin-bottom: 14px;
            border-radius: 12px;
            background: #25d366;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 20px -8px rgba(37, 211, 102, .7);
        }
        .btn-whatsapp:hover { background: #20ba5a; }
        .contact-list { display: grid; gap: 10px; }
        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 13px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            text-align: left;
        }
        .contact-icon { flex: 0 0 auto; width: 20px; height: 20px; margin-top: 2px; color: #0284c7; }
        .contact-icon svg { width: 20px; height: 20px; display: block; }
        .contact-details { min-width: 0; }
        .contact-details h3 { font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
        .contact-details p { font-size: 13px; line-height: 1.45; color: #475569; overflow-wrap: anywhere; }
        .contact-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            font-size: 12.5px;
            font-weight: 700;
            color: #0284c7;
            text-decoration: none;
        }

        /* Tablets y móviles */
        @media (max-width: 880px) {
            .maint-grid { grid-template-columns: minmax(0, 1fr); gap: 26px; }
            .maint-aside { padding: 16px 18px; }
            .maint-desc { max-width: none; }
            .maint-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 10px; }
            .maint-facts li { border-bottom: 0; }
            .maint-facts a, .maint-facts .fact { align-items: flex-start; padding: 10px 8px; height: 100%; }
            .fact-arrow { display: none; }
        }
        @media (max-width: 640px) {
            body { justify-content: flex-start; }
            .maint-wrap { max-width: 520px; }
            .maint-head { gap: 12px; }
            .maint-badge { font-size: 10.5px; padding: 6px 11px; }
            .maint-cta { flex-direction: column; }
            .btn { width: 100%; flex: 1 1 auto; }
            .maint-aside { display: none; }
            .maint-facts { grid-template-columns: 1fr; }
            .maint-facts li { border-bottom: 1px solid var(--line); }
            .maint-facts li:last-child { border-bottom: 0; }
            .maint-facts a, .maint-facts .fact { align-items: center; padding: 11px 2px; }
            .maint-foot { font-size: 11.5px; padding-bottom: max(12px, env(safe-area-inset-bottom)); }
            .modal-overlay { align-items: flex-end; padding: 0; }
            .modal-card {
                max-width: none;
                max-height: 92vh;
                max-height: 92dvh;
                border-radius: 22px 22px 0 0;
                padding-bottom: max(22px, env(safe-area-inset-bottom));
                transform: translateY(24px);
            }
        }
        @media (max-width: 380px) {
            .maint-badge { font-size: 10px; letter-spacing: .6px; }
            .maint-logo { max-width: 54vw; }
        }
        @media (max-height: 560px) and (min-width: 641px) {
            body { justify-content: flex-start; padding-top: 12px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
            }
        }
    </style>
</head>
<body>
<a class="skip-link" href="#maint-main">Saltar al contenido</a>

<div class="maint-bg" aria-hidden="true">
    <span class="orb orb-1"></span>
    <span class="orb orb-2"></span>
    <span class="orb orb-3"></span>
</div>

<main class="maint-wrap" id="maint-main">
    <section class="maint-card" aria-labelledby="maint-title">
        <div class="maint-stripe" aria-hidden="true"></div>

        <header class="maint-head">
            <div class="maint-logo-wrap">
                <img src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" class="maint-logo">
            </div>
            <span class="maint-badge">
                <span class="maint-badge-dot" aria-hidden="true"></span>
                Sitio en mantenimiento
            </span>
        </header>

        <div class="maint-grid">
            <div class="maint-copy">
                <p class="maint-kicker">Insumos y maquinarias para sublimación</p>
                <h1 class="maint-title" id="maint-title">Estamos actualizando nuestro sitio</h1>
                <p class="maint-desc">
                    Estamos realizando tareas técnicas y mejoras para brindarte una mejor experiencia.
                    <strong>Volvemos muy pronto.</strong>
                </p>

                <div class="maint-cta">
                    <a class="btn btn-primary" href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 11.05 0a11.82 11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        Escribinos por WhatsApp
                    </a>
                    <button type="button" class="btn btn-outline" onclick="toggleModal(true)" aria-haspopup="dialog" aria-controls="emergencyModal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Datos de contacto
                    </button>
                </div>

                <p class="maint-note">Atención de urgencias: lunes a viernes, de 9 a 18 hs.</p>
            </div>

            <aside class="maint-aside" aria-label="Información de contacto">
                <h2 class="aside-title">Mientras volvemos</h2>
                <ul class="maint-facts">
                    <li>
                        <div class="fact">
                            <span class="fact-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            </span>
                            <span class="fact-txt"><b>Horarios</b><span>Lun a Vie · 9 a 18 hs</span></span>
                        </div>
                    </li>
                    <li>
                        <a href="https://www.google.com/maps/search/?api=1&query=Ram%C3%B3n+Ocampo+1309+San+Vicente+C%C3%B3rdoba+Argentina+5006" target="_blank" rel="noopener noreferrer">
                            <span class="fact-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>
                            </span>
                            <span class="fact-txt"><b>Taller y depósito</b><span>Ramón Ocampo 1309, Córdoba</span></span>
                            <span class="fact-arrow" aria-hidden="true">↗</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($igUrl) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="fact-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>
                            </span>
                            <span class="fact-txt"><b>Instagram</b><span><?= e($igDisplay) ?></span></span>
                            <span class="fact-arrow" aria-hidden="true">↗</span>
                        </a>
                    </li>
                </ul>
            </aside>
        </div>

        <footer class="maint-foot">
            <span>© <?= date('Y') ?> <?= e($siteName) ?></span>
            <a class="admin-link" href="<?= e(site_url('cuenta.php?admin=1')) ?>">
                Acceso administrador
                <span aria-hidden="true">→</span>
            </a>
        </footer>
    </section>
</main>

<div class="modal-overlay" id="emergencyModal" role="dialog" aria-modal="true" aria-labelledby="emergencyTitle" aria-hidden="true" onclick="handleBackdropClick(event)">
    <div class="modal-card" role="document">
        <div class="modal-header">
            <div>
                <h2 id="emergencyTitle">Contacto de emergencia</h2>
                <p class="modal-sub">Comunicate directamente con nuestro equipo ante cualquier consulta o urgencia.</p>
            </div>
            <button type="button" class="modal-close" onclick="toggleModal(false)" aria-label="Cerrar">&times;</button>
        </div>

        <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 11.05 0a11.82 11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            Chatear por WhatsApp
        </a>

        <div class="contact-list">
            <div class="contact-item">
                <span class="contact-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="2.6"/></svg>
                </span>
                <div class="contact-details">
                    <h3>Taller y depósito</h3>
                    <p>Ramón Ocampo 1309, B° San Vicente, Córdoba, Argentina (CP 5006)</p>
                    <a href="https://www.google.com/maps/search/?api=1&query=Ram%C3%B3n+Ocampo+1309+San+Vicente+C%C3%B3rdoba+Argentina+5006" target="_blank" rel="noopener noreferrer" class="contact-action">Abrir en Google Maps →</a>
                </div>
            </div>

            <div class="contact-item">
                <span class="contact-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>
                </span>
                <div class="contact-details">
                    <h3>Instagram</h3>
                    <p>Avisos, novedades y canales de contacto directo.</p>
                    <a href="<?= e($igUrl) ?>" target="_blank" rel="noopener noreferrer" class="contact-action"><?= e($igDisplay) ?> →</a>
                </div>
            </div>

            <div class="contact-item">
                <span class="contact-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <div class="contact-details">
                    <h3>Horarios de atención</h3>
                    <p>Lunes a Viernes de 9:00 a 18:00 hs.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var maintLastFocus = null;

function toggleModal(show) {
    var modal = document.getElementById('emergencyModal');
    if (!modal) return;
    var isOpen = modal.classList.contains('active');
    if (show === isOpen) return;

    if (show) {
        maintLastFocus = document.activeElement;
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('modal-open');
        var closeBtn = modal.querySelector('.modal-close');
        if (closeBtn) closeBtn.focus();
    } else {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('modal-open');
        if (maintLastFocus && typeof maintLastFocus.focus === 'function') maintLastFocus.focus();
        maintLastFocus = null;
    }
}

function handleBackdropClick(event) {
    if (event.target === document.getElementById('emergencyModal')) toggleModal(false);
}

document.addEventListener('keydown', function (event) {
    var modal = document.getElementById('emergencyModal');
    if (!modal || !modal.classList.contains('active')) return;

    if (event.key === 'Escape') {
        toggleModal(false);
        return;
    }
    if (event.key !== 'Tab') return;

    var focusables = modal.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    var first = focusables[0];
    var last = focusables[focusables.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});
</script>

</body>
</html>
<?php
}

function check_maintenance_mode(): void {
    if (php_sapi_name() === 'cli') {
        return;
    }
    if (!is_maintenance_mode()) {
        return;
    }
    if (is_staff()) {
        return;
    }
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === 'install.php') {
        return;
    }
    // Permitir el acceso al formulario de login en cuenta.php para que el administrador pueda ingresar
    if ($script === 'cuenta.php') {
        if (!current_user()) {
            return;
        }
    }
    // Si es petición AJAX, devolver 503 JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sitio en mantenimiento. Solo accesible para administradores.', 'maintenance' => true]);
        exit;
    }
    http_response_code(503);
    render_maintenance_page();
    exit;
}

/**
 * Banda "hero" de página interna: degradado a todo el ancho con título y migas.
 * Reutilizable en contacto.php, nosotros.php y cualquier página interna.
 *
 * @param string $title  Título principal (se escapa).
 * @param string $crumb  Migas de pan (HTML ya construido/escapado por el llamador).
 * @param string $kicker Texto pequeño superior (se escapa).
 */
function page_hero(string $title, string $crumb = '', string $kicker = ''): void {
    ?>
<section class="page-hero">
    <div class="page-hero-inner">
        <?php if ($kicker !== ''): ?>
            <p class="page-hero-kicker"><?= e($kicker) ?></p>
        <?php endif; ?>
        <h1><?= e($title) ?></h1>
        <?php if ($crumb !== ''): ?>
            <p class="page-hero-crumb"><?= $crumb ?></p>
        <?php endif; ?>
    </div>
</section>
    <?php
}

function site_header(string $title = '', string $description = ''): void {
    $name = setting('site_name', APP_NAME);
    $user = current_user();
    $pageTitle = $title !== '' ? $title . ' · ' . $name : $name . ' · Insumos y maquinarias para sublimación';
    $desc = $description !== '' ? $description : 'Insumos, tintas, sublimables y maquinarias para sublimación y estampado. Venta mayorista para clientes verificados.';
    $logo = site_logo();
    $favicon = site_favicon();
    $waText = 'Hola, quisiera consultar sobre insumos para sublimación.';
    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <meta name="theme-color" content="#172530">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= e(site_url('assets/site.css')) ?>?v=3.2">
    <link rel="icon" href="<?= e($favicon) ?>">
    <link rel="shortcut icon" href="<?= e($favicon) ?>">
    <script>document.documentElement.className += ' has-js';</script>
    <style>
        /* Overlay de carga: CSS critico en linea para que se vea bien aunque site.css tarde en cargar */
        #site-loader{position:fixed;inset:0;z-index:999999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:24px;background:#ffffff;transition:opacity .45s ease,visibility .45s ease}
        #site-loader.site-loader--hidden{opacity:0;visibility:hidden;pointer-events:none}
        #site-loader .site-loader-logo{width:60%;max-width:200px;height:auto;display:block;animation:siteLoaderPulse 1.5s ease-in-out infinite}
        #site-loader .site-loader-bar{width:150px;max-width:60%;height:4px;border-radius:4px;background:#eef2f6;overflow:hidden;position:relative}
        #site-loader .site-loader-bar::after{content:"";position:absolute;top:0;bottom:0;left:-40%;width:40%;border-radius:4px;background:linear-gradient(90deg,#ed3285,#39aef2);animation:siteLoaderBar 1.1s ease-in-out infinite}
        @keyframes siteLoaderPulse{0%,100%{opacity:1}50%{opacity:.5}}
        @keyframes siteLoaderBar{0%{left:-40%}100%{left:100%}}
        @media (prefers-reduced-motion:reduce){#site-loader .site-loader-logo{animation:none}#site-loader .site-loader-bar::after{animation:none;left:0;width:100%}}
    </style>
</head>
<body id="top">

<div id="site-loader" role="status" aria-live="polite" aria-label="Cargando sitio">
    <img class="site-loader-logo" src="<?= e($logo) ?>" alt="Serinsumos" fetchpriority="high">
    <span class="site-loader-bar" aria-hidden="true"></span>
</div>
<noscript><style>#site-loader{display:none!important}</style></noscript>

<?php if (is_maintenance_mode() && is_staff()): ?>
<div style="background:#dc2626;color:#ffffff;padding:9px 16px;text-align:center;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;z-index:99999;box-shadow:0 2px 8px rgba(0,0,0,0.25);">
    <span>🛠️ <strong>MODO MANTENIMIENTO ACTIVO</strong> — La tienda está cerrada al público general (solo el equipo del panel puede verla).</span>
    <a href="<?= e(site_url('admin.php?tab=configuracion#mantenimiento')) ?>" style="background:#ffffff;color:#dc2626;padding:4px 12px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:800;white-space:nowrap;">Desactivar en Admin &rarr;</a>
</div>
<?php endif; ?>

<div class="topline">
    <div class="topline-inner">
        <span>✨ Insumos para sublimar, estampar y hacer crecer tu taller</span>
        <span class="topline-tag">SERINSUMOS · VENTA MAYORISTA</span>
    </div>
</div>

<header class="header">
    <div class="header-container">
        <a href="<?= e(site_url('index.php')) ?>" class="brand" aria-label="Serinsumos, inicio">
            <img src="<?= e($logo) ?>" alt="Serinsumos" width="160" height="48">
        </a>

        <nav id="main-nav" class="main-nav" aria-label="Navegación principal">
            <a href="<?= e(site_url('index.php')) ?>" class="<?= $currentScript === 'index.php' ? 'active' : '' ?>">Inicio</a>
            <a href="<?= e(site_url('catalogo.php')) ?>" class="<?= $currentScript === 'catalogo.php' ? 'active' : '' ?>">Tienda</a>
            <a href="<?= e(site_url('mayoristas.php')) ?>" class="<?= $currentScript === 'mayoristas.php' ? 'active' : '' ?>">Mayoristas</a>
            <a href="<?= e(site_url('nosotros.php')) ?>" class="<?= $currentScript === 'nosotros.php' ? 'active' : '' ?>">Nosotros</a>
            <a href="<?= e(site_url('contacto.php')) ?>" class="<?= $currentScript === 'contacto.php' ? 'active' : '' ?>">Contacto</a>
            <a href="https://www.instagram.com/ser.insumos/" target="_blank" rel="noopener noreferrer">Instagram</a>
        </nav>

        <div class="header-actions">
            <a class="header-wa" href="<?= e(whatsapp_url($waText)) ?>" target="_blank" rel="noopener noreferrer" title="Consultar por WhatsApp">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.6a8.7 8.7 0 0 1-12.9 7.6L3 20.5l1.3-4.8A8.7 8.7 0 1 1 21 11.6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8.2 8.1c.3-.6.6-.6.9-.6h.4c.2 0 .4 0 .5.4l.7 1.6c.1.3.1.4-.1.6l-.5.6c-.2.2-.2.4-.1.6.5.9 1.2 1.5 2.1 1.9.2.1.4.1.6-.1l.7-.8c.2-.2.4-.2.7-.1l1.5.7c.3.1.4.3.4.5 0 .3-.2 1.2-.7 1.5-.5.4-1.1.6-1.8.4-1-.2-2.2-.8-3.4-1.9-1-.9-1.8-2.1-2-2.8-.3-.8 0-1.7.3-2.5Z" fill="currentColor"/></svg>
                <span>¿Te ayudamos?<b>WhatsApp</b></span>
            </a>

            <a href="<?= e(site_url('cuenta.php')) ?>" class="header-user-btn" aria-label="Mi cuenta" title="<?= $user ? e($user['name']) : 'Mi cuenta' ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M5 20c.3-3.5 3.1-5.5 7-5.5s6.7 2 7 5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            </a>

            <a href="<?= e(site_url('carrito.php')) ?>" class="cart-link" aria-label="Mi pedido" title="Ver pedido en curso">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1.3 12H6.3L5 8Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 8V6a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                <?php if (cart_count() > 0): ?><b><?= cart_count() ?></b><?php endif; ?>
            </a>

            <button class="mobile-menu" type="button" aria-expanded="false" aria-controls="mobile-drawer" onclick="toggleMobileMenu()" aria-label="Abrir menú de navegación">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Navigation Drawer -->
<div id="mobile-drawer-backdrop" class="mobile-drawer-backdrop" onclick="toggleMobileMenu()"></div>
<aside id="mobile-drawer" class="mobile-drawer" aria-label="Menú móvil">
    <div class="mobile-drawer-header">
        <a href="<?= e(site_url('index.php')) ?>" class="brand"><img src="<?= e($logo) ?>" alt="Serinsumos" height="38"></a>
        <button type="button" class="mobile-drawer-close" onclick="toggleMobileMenu()" aria-label="Cerrar menú">&times;</button>
    </div>
    <div class="mobile-drawer-links">
        <a href="<?= e(site_url('index.php')) ?>" class="<?= $currentScript === 'index.php' ? 'active' : '' ?>">🏠 Inicio</a>
        <a href="<?= e(site_url('catalogo.php')) ?>" class="<?= $currentScript === 'catalogo.php' ? 'active' : '' ?>">📦 Tienda / Catálogo</a>
        <a href="<?= e(site_url('mayoristas.php')) ?>" class="<?= $currentScript === 'mayoristas.php' ? 'active' : '' ?>">💼 Venta Mayorista</a>
        <a href="<?= e(site_url('nosotros.php')) ?>">👥 Nosotros</a>
        <a href="<?= e(site_url('contacto.php')) ?>" class="<?= $currentScript === 'contacto.php' ? 'active' : '' ?>">💬 Contacto / Ubicación</a>
        <a href="<?= e(site_url('cuenta.php')) ?>">👤 Mi cuenta mayorista</a>
        <a href="<?= e(site_url('carrito.php')) ?>">🛒 Mi pedido <?= cart_count() > 0 ? '(' . cart_count() . ')' : '' ?></a>
        <?php if (is_staff()): ?>
            <a href="<?= e(site_url('admin.php')) ?>" style="color:var(--pink);font-weight:700">⚙️ Panel de Gestión</a>
        <?php endif; ?>
    </div>
    <div class="mobile-drawer-footer">
        <a class="btn yellow" href="<?= e(whatsapp_url($waText)) ?>" target="_blank" rel="noopener noreferrer" style="width:100%;margin-bottom:8px">
            Hablemos por WhatsApp
        </a>
    </div>
</aside>

<script>
function toggleMobileMenu() {
    const drawer = document.getElementById('mobile-drawer');
    const backdrop = document.getElementById('mobile-drawer-backdrop');
    const btn = document.querySelector('.mobile-menu');
    if (!drawer || !backdrop) return;
    const isOpen = drawer.classList.contains('open');
    drawer.classList.toggle('open', !isOpen);
    backdrop.classList.toggle('open', !isOpen);
    if (btn) btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
    document.body.style.overflow = !isOpen ? 'hidden' : '';
}
</script>

<?php render_flashes(); ?>
<?php
}

function site_footer(): void {
    $logo = site_logo();
    ?>
<footer class="footer-main">
    <div class="footer-container">
        <div class="footer-col-brand">
            <a class="brand" href="<?= e(site_url('index.php')) ?>"><img src="<?= e($logo) ?>" alt="Serinsumos" loading="lazy"></a>
            <p>Especialistas en insumos, papeles, tintas y maquinarias para sublimación, estampado y talleres gráficos en toda la Argentina.</p>
            <div class="footer-badges">
                <span class="footer-badge">✓ Envíos a todo el país</span>
                <span class="footer-badge">✓ Venta mayorista</span>
            </div>
        </div>

        <div class="footer-col-links">
            <h4>Navegación</h4>
            <a href="<?= e(site_url('catalogo.php')) ?>">Catálogo de productos</a>
            <a href="<?= e(site_url('mayoristas.php')) ?>">Cómo comprar mayorista</a>
            <a href="<?= e(site_url('nosotros.php')) ?>">Sobre Serinsumos</a>
            <a href="<?= e(site_url('carrito.php')) ?>">Mi pedido</a>
        </div>

        <div class="footer-col-links">
            <h4>Mi Cuenta</h4>
            <a href="<?= e(site_url('cuenta.php')) ?>">Ingresar a mi cuenta</a>
            <a href="<?= e(site_url('cuenta.php?registro=1')) ?>">Registrar mi negocio</a>
            <a href="<?= e(site_url('cuenta.php?recuperar=1')) ?>">Recuperar contraseña</a>
            <?php if (is_staff()): ?>
                <a href="<?= e(site_url('admin.php')) ?>" style="color:var(--blue);font-weight:600">Panel de Gestión</a>
            <?php endif; ?>
        </div>

        <div class="footer-col-links">
            <h4>Contacto</h4>
            <a href="<?= e(site_url('contacto.php')) ?>">📍 Dónde estamos / Ubicación</a>
            <a href="<?= e(whatsapp_url('Hola, quisiera realizar una consulta.')) ?>" target="_blank" rel="noopener noreferrer">💬 WhatsApp comercial</a>
            <a href="https://www.instagram.com/ser.insumos/" target="_blank" rel="noopener noreferrer">📷 Instagram @ser.insumos</a>
            <p style="margin-top:12px;font-size:12px;color:var(--gray)">Ramón Ocampo 1309 San Vicente, Córdoba, Argentina.</p>
        </div>
    </div>

    <div class="footer-bottom">
        <small>© <?= date('Y') ?> Serinsumos. Todos los derechos reservados. Precios mayoristas para clientes verificados.</small>
    </div>
</footer>

<button class="to-top" id="to-top" type="button" aria-label="Volver arriba" title="Volver arriba">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M5.5 12.5 12 6l6.5 6.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>

<a class="floating-wa" href="<?= e(whatsapp_url()) ?>" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.6a8.7 8.7 0 0 1-12.9 7.6L3 20.5l1.3-4.8A8.7 8.7 0 1 1 21 11.6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8.2 8.1c.3-.6.6-.6.9-.6h.4c.2 0 .4 0 .5.4l.7 1.6c.1.3.1.4-.1.6l-.5.6c-.2.2-.2.4-.1.6.5.9 1.2 1.5 2.1 1.9.2.1.4.1.6-.1l.7-.8c.2-.2.4-.2.7-.1l1.5.7c.3.1.4.3.4.5 0 .3-.2 1.2-.7 1.5-.5.4-1.1.6-1.8.4-1-.2-2.2-.8-3.4-1.9-1-.9-1.8-2.1-2-2.8-.3-.8 0-1.7.3-2.5Z" fill="currentColor"/></svg>
</a>

<script>
(function () {
    var loader = document.getElementById('site-loader');
    if (!loader) return;
    var done = false;
    function hideLoader() {
        if (done) return;
        done = true;
        loader.classList.add('site-loader--hidden');
        /* Avisa al resto del sitio (p. ej. el hero) que ya puede animar la entrada */
        try { document.dispatchEvent(new Event('site:loaderhidden')); } catch (e) {}
        window.setTimeout(function () {
            if (loader.parentNode) loader.parentNode.removeChild(loader);
        }, 500);
    }
    function hideIfComplete() {
        if (document.readyState === 'complete') hideLoader();
    }
    if (document.readyState === 'complete') {
        hideLoader();
    } else {
        window.addEventListener('load', hideLoader, { once: true });
        document.addEventListener('DOMContentLoaded', hideIfComplete);
        document.addEventListener('readystatechange', hideIfComplete);
        /* Respaldo: sondeo por si los eventos no llegan (evita carrera con 'load') */
        var poll = window.setInterval(function () {
            if (document.readyState === 'complete') {
                window.clearInterval(poll);
                hideLoader();
            }
        }, 120);
    }
    /* Red de seguridad: nunca dejar el overlay pegado si un recurso tarda demasiado */
    window.setTimeout(hideLoader, 6000);
})();
</script>

<script>
/* Botón "volver arriba": aparece al bajar y sube suave */
(function () {
    var btn = document.getElementById('to-top');
    if (!btn) return;
    var UMBRAL = 340;
    var visible = false;
    function update() {
        var should = (window.pageYOffset || document.documentElement.scrollTop || 0) > UMBRAL;
        if (should === visible) return;
        visible = should;
        btn.classList.toggle('is-visible', should);
    }
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    update();
    btn.addEventListener('click', function () {
        var reduce = false;
        try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
        btn.blur();
    });
})();
</script>

<script>
/* Línea superior del footer: el color recorre la paleta de la marca al hacer scroll.
   La fase depende de la posición, así que baja un color al bajar y lo devuelve al subir. */
(function () {
    var root = document.documentElement;
    if (!root || !window.requestAnimationFrame) return;

    var PALETA = [
        [57, 174, 242],   /* azul     #39aef2 */
        [237, 50, 133],   /* rosa     #ed3285 */
        [255, 231, 46],   /* amarillo #ffe72e */
        [237, 50, 133],   /* rosa     #ed3285 */
        [57, 174, 242]    /* vuelve al azul (sin pasar por el verde) */
    ];
    var TRAMOS = PALETA.length - 1;
    var CICLO = 1.5;      /* vueltas de paleta por cada altura de pantalla recorrida */
    var frame = 0;

    function mezcla(a, b, t) {
        return 'rgb(' + Math.round(a[0] + (b[0] - a[0]) * t) + ',' +
                        Math.round(a[1] + (b[1] - a[1]) * t) + ',' +
                        Math.round(a[2] + (b[2] - a[2]) * t) + ')';
    }
    function colorDe(fase) {
        var x = (fase - Math.floor(fase)) * TRAMOS;
        var i = Math.floor(x);
        if (i > TRAMOS - 1) i = TRAMOS - 1;
        return mezcla(PALETA[i], PALETA[i + 1], x - i);
    }
    function pintar() {
        frame = 0;
        var y = window.pageYOffset || root.scrollTop || 0;
        var alto = window.innerHeight || 800;
        root.style.setProperty('--footer-line', colorDe(y / (alto * CICLO)));
    }
    function pedirPintado() {
        if (frame) return;
        /* requestAnimationFrame no corre en pestañas ocultas: ahí pintamos al instante */
        if (document.hidden) { pintar(); return; }
        frame = window.requestAnimationFrame(pintar);
    }
    window.addEventListener('scroll', pedirPintado, { passive: true });
    window.addEventListener('resize', pedirPintado, { passive: true });
    pintar();
})();
</script>
</body>
</html>
<?php }

function render_product_card(array $p): void {
    $image    = (string)($p['image'] ?? '');
    $title    = (string)($p['_display_title'] ?? $p['title'] ?? 'Producto');
    $handle   = (string)($p['handle'] ?? $p['id'] ?? '');
    $price    = (string)($p['_display_price'] ?? $p['price_formatted'] ?? '');
    $approved = is_verified();
    $cat      = (string)($p['_category'] ?? '');
    ?>
<article class="home-product-card">
    <a class="product-image" href="<?= e(site_url('producto/' . rawurlencode($handle))) ?>">
        <?php if ($image !== ''): ?>
            <img loading="lazy" src="<?= e($image) ?>" alt="<?= e($title) ?>">
        <?php else: ?>
            <span class="image-placeholder">Imagen no disponible</span>
        <?php endif; ?>
        <span class="badge-wholesale">VENTA MAYORISTA</span>
    </a>
    <div class="home-product-info">
        <?php if ($cat !== ''): ?>
            <p class="home-product-cat"><?= e($cat) ?></p>
        <?php endif; ?>
        <h3><a href="<?= e(site_url('producto/' . rawurlencode($handle))) ?>"><?= e($title) ?></a></h3>
        <?php if ($approved): ?>
            <p class="price"><?= e($price) ?></p>
        <?php else: ?>
            <a class="locked" href="<?= e(site_url('cuenta.php')) ?>">🔒 Precio para clientes verificados</a>
        <?php endif; ?>
        <a class="btn outline home-product-btn" href="<?= e(site_url('producto/' . rawurlencode($handle))) ?>">Ver producto →</a>
    </div>
</article>
<?php }

/* ── Product overrides (tabla product_overrides) ─────────────────────────
 * Permite al admin sobreescribir precio, nombre, categoría y visibilidad
 * de cualquier producto proveniente de la API de Hostinger.
 * Los datos se fusionan sobre el array normalizado de cada producto.
 * ─────────────────────────────────────────────────────────────────────── */

/**
 * ¿La tabla product_overrides ya tiene la columna image_url?
 * Si no existe, la crea (migración idempotente) y devuelve true.
 * Cacheado una sola vez por petición.
 */
function overrides_have_image_column(): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $has = (bool)db()->query("SHOW COLUMNS FROM product_overrides LIKE 'image_url'")->fetch();
        if (!$has) {
            db()->exec("ALTER TABLE product_overrides ADD COLUMN image_url VARCHAR(500) NOT NULL DEFAULT '' AFTER notes");
            $has = (bool)db()->query("SHOW COLUMNS FROM product_overrides LIKE 'image_url'")->fetch();
        }
    } catch (Throwable) {
        $has = false;
    }
    return $has;
}

/**
 * Carga todos los overrides indexados por api_handle.
 * Resultado cacheado en memoria por petición.
 */
function get_all_overrides(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $hasImage = overrides_have_image_column();
    $cols = 'api_handle, label, price_override, currency_symbol,
                    category, notes, visible, deleted, sort_order';
    if ($hasImage) $cols .= ', image_url';
    try {
        $rows = db()->query("SELECT $cols FROM product_overrides")->fetchAll();
        $cache = [];
        foreach ($rows as $r) {
            if (!isset($r['image_url'])) $r['image_url'] = '';
            $cache[$r['api_handle']] = $r;
        }
    } catch (Throwable) { $cache = []; }
    return $cache;
}

/** Devuelve el override de un handle específico o null si no existe. */
function get_override(string $handle): ?array {
    return get_all_overrides()[$handle] ?? null;
}

/**
 * Fusiona un override sobre un producto normalizado de la API.
 * Agrega las claves: _override (array|null), _visible (bool),
 * _local_price (string formateada o ''), _display_price (string final),
 * _category (string), _sort_order (int).
 */
function apply_override(array $product): array {
    $handle   = (string)($product['handle'] ?? $product['id'] ?? '');
    $override = get_override($handle);

    $product['_override']    = $override;
    $product['_deleted']     = $override !== null && (int)($override['deleted'] ?? 0) === 1;
    $product['_visible']     = !$product['_deleted'] && ($override === null || (int)$override['visible'] === 1);
    $product['_category']    = $override['category'] ?? '';
    $product['_sort_order']  = isset($override['sort_order']) ? (int)$override['sort_order'] : 0;
    $product['_notes']       = $override['notes'] ?? '';
    $product['_source']      = 'api'; // distingue productos de API vs locales

    /* Precio local */
    $localPrice = null;
    if ($override !== null && $override['price_override'] !== null && (float)$override['price_override'] > 0) {
        $sym        = trim((string)($override['currency_symbol'] ?? '$'));
        $localPrice = $sym . number_format((float)$override['price_override'], 2, ',', '.');
    }
    $product['_local_price']   = $localPrice ?? '';
    /* Precio a mostrar: local si existe, si no el de la API */
    $product['_display_price'] = $localPrice ?? ($product['price_formatted'] ?? '');

    /* Nombre a mostrar */
    if ($override !== null && trim((string)$override['label']) !== '') {
        $product['_display_title'] = (string)$override['label'];
    } else {
        $product['_display_title'] = (string)($product['title'] ?? '');
    }

    /* Imagen local: reemplaza la de la API cuando el admin cargó una propia */
    $overrideImage = $override !== null ? trim((string)($override['image_url'] ?? '')) : '';
    $product['_image_override'] = $overrideImage;
    if ($overrideImage !== '') {
        $product['image'] = $overrideImage;
    }

    return $product;
}

/**
 * Obtiene todos los productos de la API con overrides fusionados,
 * más los productos locales creados directamente en el admin.
 * En modo cliente filtra ocultos y eliminados.
 */
function get_products_with_overrides(int $limit = 200, bool $adminMode = false): array {
    $catalog = get_products($limit);
    $items   = $catalog['products'] ?? [];

    $items = array_map('apply_override', $items);

    /* Filtrar eliminados y ocultos (salvo admin) */
    if (!$adminMode) {
        $items = array_values(array_filter($items, fn($p) => $p['_visible'] && !$p['_deleted']));
    }

    /* Agregar productos locales */
    $localItems = get_local_products($adminMode);
    $items = array_merge($items, $localItems);

    /* Ordenar por sort_order ASC */
    usort($items, fn($a, $b) => ($a['_sort_order'] <=> $b['_sort_order']) ?: 0);

    $catalog['products'] = $items;
    $catalog['count']    = count($items);
    return $catalog;
}



/* ── Buscador de inventario (para armar presupuestos) ────────────────── */

/**
 * Normaliza un producto del inventario para el buscador de presupuestos.
 *
 * A propósito NO filtra por visibilidad ni por stock: los artículos ocultos,
 * eliminados o sin stock también se pueden cotizar (ítems administrativos,
 * servicios o pedidos a pedido). Cada fila avisa su estado real.
 */
function inventory_row(array $p): array {
    $title    = trim((string)($p['_display_title'] ?? ''));
    if ($title === '') $title = trim((string)($p['title'] ?? ''));
    $original = trim((string)($p['title'] ?? ''));
    $isLocal  = ($p['_source'] ?? '') === 'local';

    /* SKU: el propio del producto local o el de la primera variante de la API */
    $sku = trim((string)($p['_sku'] ?? ''));
    if ($sku === '') {
        foreach (($p['variants'] ?? []) as $v) {
            $vs = trim((string)($v['sku'] ?? ''));
            if ($vs !== '') { $sku = $vs; break; }
        }
    }

    /* Stock: sólo cuando la tienda lleva inventario de esa variante */
    $tracked  = false;
    $qty      = 0;
    $anyAvail = false;
    foreach (($p['variants'] ?? []) as $v) {
        $q = $v['inventory_quantity'] ?? null;
        if (!empty($v['manage_inventory']) && $q !== null) {
            $tracked = true;
            $q       = max(0, (int)$q);
            $qty    += $q;
            if ($q > 0) $anyAvail = true;
        } elseif (empty($v['manage_inventory'])) {
            $anyAvail = true; /* sin control de stock se considera disponible */
        }
    }
    $inStock = !$tracked || $anyAvail;

    /* Precio: el cargado a mano (local u override) o el de la tienda si está en pesos */
    $override  = $p['_override'] ?? null;
    $priceNum  = null;
    $priceFrom = '';
    if ($isLocal) {
        $raw = $p['_local_row']['price'] ?? null;
        if ($raw !== null && (float)$raw > 0) { $priceNum = (float)$raw; $priceFrom = 'propio'; }
    } elseif (is_array($override) && ($override['price_override'] ?? null) !== null && (float)$override['price_override'] > 0) {
        $priceNum  = (float)$override['price_override'];
        $priceFrom = 'propio';
    } else {
        $info   = is_array($p['currency_info'] ?? null) ? $p['currency_info'] : null;
        $code   = is_array($info) ? strtolower((string)($info['code'] ?? '')) : '';
        $digits = is_array($info) && is_numeric($info['decimal_digits'] ?? null) ? (int)$info['decimal_digits'] : 2;
        $cents  = (int)($p['price_in_cents'] ?? 0);
        if ($code === 'ars' && $cents > 0) { $priceNum = $cents / (10 ** $digits); $priceFrom = 'tienda'; }
    }
    $priceLabel = trim((string)($p['_local_price'] ?? ''));
    if ($priceLabel === '') $priceLabel = trim((string)($p['price_formatted'] ?? ''));

    $deleted = !empty($p['_deleted']);
    $visible = !empty($p['_visible']) && !$deleted;

    $flags = [];
    if ($isLocal)      $flags[] = 'Local';
    if ($deleted)      $flags[] = 'Eliminado';
    elseif (!$visible) $flags[] = 'Oculto';
    if (!$inStock)     $flags[] = 'Sin stock';
    if (!$isLocal && ($p['purchasable'] ?? true) === false) $flags[] = 'No publicable';

    $unit = trim((string)($p['_unit_of_sale'] ?? ''));
    $pack = isset($p['_pack_quantity']) && $p['_pack_quantity'] !== null ? (int)$p['_pack_quantity'] : null;

    $detailBits = [];
    if ($sku !== '') $detailBits[] = 'SKU ' . $sku;
    if ($unit !== '' && $unit !== 'unidad') $detailBits[] = $unit;
    if ($pack !== null && $pack > 1) $detailBits[] = 'x' . $pack;

    return [
        'title'       => $title,
        'original'    => ($original !== '' && $original !== $title) ? $original : '',
        'sku'         => $sku,
        'unit'        => $unit,
        'pack'        => $pack,
        'source'      => $isLocal ? 'local' : 'api',
        'price'       => $priceNum,
        'price_from'  => $priceFrom,
        'price_label' => $priceLabel,
        'stock'       => $tracked ? $qty : null,
        'stock_label' => !$tracked ? 'Sin control de stock' : ($qty > 0 ? $qty . ' en stock' : 'Sin stock'),
        'in_stock'    => $inStock,
        'active'      => $visible,
        'flags'       => $flags,
        'detail_hint' => implode(' · ', $detailBits),
    ];
}

/** Puntaje de coincidencia: cuanto más bajo, mejor posición en la lista. */
function inventory_search_score(array $row, string $needle): int {
    $t = mb_strtolower($row['title']);
    if (str_starts_with($t, $needle)) return 0;
    foreach (preg_split('/\s+/', $t) ?: [] as $word) {
        if ($word !== '' && str_starts_with($word, $needle)) return 1;
    }
    if (str_contains($t, $needle)) return 2;
    if ($row['sku'] !== '' && str_contains(mb_strtolower($row['sku']), $needle)) return 3;
    return 4;
}

/**
 * Busca artículos en el inventario (incluye ocultos, eliminados y sin stock).
 * Devuelve ['items' => [...], 'catalog_error' => bool].
 */
function search_inventory(string $query, int $limit = 12): array {
    $query = trim($query);
    if (mb_strlen($query) < 2) return ['items' => [], 'catalog_error' => false];
    $needle = mb_strtolower($query);
    $limit  = max(1, min(40, $limit));

    $catalog = get_products_with_overrides(200, true);
    $rows    = [];

    foreach (($catalog['products'] ?? []) as $p) {
        $row = inventory_row($p);
        $hay = mb_strtolower($row['title'] . ' ' . $row['original'] . ' ' . $row['sku'] . ' ' . (string)($p['_category'] ?? ''));
        if (!str_contains($hay, $needle)) continue;
        $row['_score'] = inventory_search_score($row, $needle);
        $rows[] = $row;
    }

    usort($rows, static function (array $a, array $b): int {
        if ($a['_score'] !== $b['_score']) return $a['_score'] <=> $b['_score'];
        if ($a['active'] !== $b['active'])  return $a['active'] ? -1 : 1;   /* activos primero */
        if ($a['in_stock'] !== $b['in_stock']) return $a['in_stock'] ? -1 : 1;
        return strcmp($a['title'], $b['title']);
    });

    $out = [];
    foreach (array_slice($rows, 0, $limit) as $r) {
        unset($r['_score']);
        $out[] = $r;
    }

    return ['items' => $out, 'catalog_error' => !empty($catalog['error'])];
}

/**
 * Guarda (insert o update) un override en la BD.
 * @param array $data Claves: label, price_override, currency_symbol, category, notes, visible, sort_order, image_url
 */
function save_override(string $handle, array $data): void {
    $handle = trim($handle);
    if ($handle === '') throw new RuntimeException('El handle del producto no puede estar vacío.');

    $label          = trim((string)($data['label'] ?? ''));
    $priceRaw       = trim((string)($data['price_override'] ?? ''));
    $priceOverride  = $priceRaw !== '' ? (float)str_replace(',', '.', $priceRaw) : null;
    $currencySymbol = trim((string)($data['currency_symbol'] ?? '$')) ?: '$';
    $category       = trim((string)($data['category'] ?? ''));
    $notes          = trim((string)($data['notes'] ?? ''));
    $visible        = isset($data['visible']) ? (int)(bool)$data['visible'] : 1;
    $sortOrder      = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

    if (overrides_have_image_column()) {
        /* Si el llamador no manda 'image_url', se conserva la imagen guardada */
        if (array_key_exists('image_url', $data)) {
            $imageUrl = trim((string)$data['image_url']);
        } else {
            $existing = get_override($handle);
            $imageUrl = trim((string)($existing['image_url'] ?? ''));
        }

        db()->prepare(
            'INSERT INTO product_overrides
                (api_handle, label, price_override, currency_symbol, category, notes, image_url, visible, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                label=VALUES(label),
                price_override=VALUES(price_override),
                currency_symbol=VALUES(currency_symbol),
                category=VALUES(category),
                notes=VALUES(notes),
                image_url=VALUES(image_url),
                visible=VALUES(visible),
                sort_order=VALUES(sort_order),
                updated_at=NOW()'
        )->execute([$handle, $label, $priceOverride, $currencySymbol, $category, $notes, $imageUrl, $visible, $sortOrder]);
        return;
    }

    db()->prepare(
        'INSERT INTO product_overrides
            (api_handle, label, price_override, currency_symbol, category, notes, visible, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            label=VALUES(label),
            price_override=VALUES(price_override),
            currency_symbol=VALUES(currency_symbol),
            category=VALUES(category),
            notes=VALUES(notes),
            visible=VALUES(visible),
            sort_order=VALUES(sort_order),
            updated_at=NOW()'
    )->execute([$handle, $label, $priceOverride, $currencySymbol, $category, $notes, $visible, $sortOrder]);
}

/* ── Productos locales (tabla local_products) ─────────────────────────
 * Productos gestionados 100% desde el admin, sin pasar por Hostinger.
 * Su handle virtual es 'lcl-{id}' para que producto.php los detecte.
 * ─────────────────────────────────────────────────────────────────────── */

/**
 * Normaliza una fila de local_products al mismo shape que un producto de API.
 */
function normalize_local_product(array $row): array {
    $id    = (int)$row['id'];
    $price = $row['price'] !== null ? (float)$row['price'] : null;
    $sym   = trim((string)($row['currency_symbol'] ?? '$')) ?: '$';
    $priceDisplay = trim((string)($row['price_display'] ?? ''));
    $sku = trim((string)($row['sku'] ?? ''));

    if ($price !== null) {
        $priceFmt = $sym . number_format($price, 2, ',', '.');
    } elseif ($priceDisplay !== '') {
        $priceFmt = $priceDisplay;
    } else {
        $priceFmt = 'A consultar';
    }

    $stock = isset($row['stock']) ? max(0, (int)$row['stock']) : null;
    $defaultVariant = [
        'id'                        => 'lcl-' . $id . '-default',
        'title'                     => 'Estándar',
        'image_url'                 => (string)($row['image_url'] ?? ''),
        'sku'                       => $sku !== '' ? $sku : ('LCL-' . $id),
        'price_in_cents'            => $price !== null ? (int)round($price * 100) : 0,
        'regular_price'             => $price !== null ? (int)round($price * 100) : 0,
        'sale_price'                => null,
        'currency'                  => 'ars',
        'currency_info'             => null,
        'price_formatted'           => $priceFmt,
        'sale_price_formatted'      => '',
        'effective_price_formatted' => $priceFmt,
        'manage_inventory'          => $stock !== null,
        'inventory_quantity'        => $stock,
        'options'                   => [],
    ];

    return [
        /* Campos compatibles con productos de la API */
        'id'                       => 'lcl-' . $id,
        'handle'                   => 'lcl-' . $id,
        'title'                    => (string)$row['title'],
        'description'              => (string)$row['description'],
        'image'                    => (string)($row['image_url'] ?? ''),
        'purchasable'              => true,
        'price_in_cents'           => $price !== null ? (int)round($price * 100) : 0,
        'price_formatted'          => $priceFmt,
        'variants'                 => [$defaultVariant],
        'currency_info'            => null,
        'seo_settings'             => [],
        'type'                     => 'local',
        /* Campos de override / enriquecidos */
        '_source'                  => 'local',
        '_override'                => null,
        '_deleted'                 => false,
        '_visible'                 => (int)$row['visible'] === 1,
        '_category'                => (string)$row['category'],
        '_category_slug'           => (string)($row['category_slug'] ?? ''),
        '_sort_order'              => (int)$row['sort_order'],
        '_notes'                   => (string)$row['notes'],
        '_local_price'             => $priceFmt,
        '_display_price'           => $priceFmt,
        '_display_title'           => (string)$row['title'],
        '_sku'                     => $sku,
        '_unit_of_sale'            => (string)($row['unit_of_sale'] ?? 'unidad'),
        '_pack_quantity'           => $row['pack_quantity'] ?? null,
        '_price_display'           => $priceDisplay,
        /* Datos propios del local product */
        '_local_id'                => $id,
        '_local_row'               => $row,
        'created_at'               => (string)$row['created_at'],
        'updated_at'               => (string)($row['updated_at'] ?? ''),
    ];
}

/**
 * Devuelve todas las categorías de la base de datos ordenadas por sort_order.
 */
function get_categories(bool $activeOnly = true): array {
    try {
        $sql = 'SELECT * FROM categories';
        if ($activeOnly) $sql .= ' WHERE active=1';
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        return db()->query($sql)->fetchAll();
    } catch (Throwable) { return []; }
}



/**
 * Devuelve todos los productos locales normalizados.
 * En modo cliente filtra los ocultos.
 */
function get_local_products(bool $adminMode = false): array {
    try {
        $sql = 'SELECT * FROM local_products';
        if (!$adminMode) $sql .= ' WHERE visible=1';
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = db()->query($sql)->fetchAll();
        return array_map('normalize_local_product', $rows);
    } catch (Throwable) { return []; }
}

/**
 * Devuelve un producto local por ID, normalizado. Null si no existe.
 */
function get_local_product_by_id(int $id): ?array {
    if ($id < 1) return null;
    try {
        $st = db()->prepare('SELECT * FROM local_products WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? normalize_local_product($row) : null;
    } catch (Throwable) { return null; }
}

/**
 * Crea o actualiza un producto local.
 * Devuelve el ID del producto guardado.
 */
function save_local_product(array $data, ?int $id = null): int {
    $title      = trim((string)($data['title'] ?? ''));
    if ($title === '') throw new RuntimeException('El nombre del producto es obligatorio.');

    $description  = trim((string)($data['description'] ?? ''));
    $priceRaw     = trim((string)($data['price'] ?? ''));
    $price        = $priceRaw !== '' ? (float)str_replace(',', '.', $priceRaw) : null;
    $sym          = trim((string)($data['currency_symbol'] ?? '$')) ?: '$';
    $category     = trim((string)($data['category'] ?? ''));
    $categorySlug = trim((string)($data['category_slug'] ?? ''));
    $sku          = trim((string)($data['sku'] ?? ''));
    $unitOfSale   = trim((string)($data['unit_of_sale'] ?? 'unidad')) ?: 'unidad';
    $packQty      = isset($data['pack_quantity']) && $data['pack_quantity'] !== '' ? (int)$data['pack_quantity'] : null;
    $priceDisplay = trim((string)($data['price_display'] ?? ''));
    $imageUrl     = trim((string)($data['image_url'] ?? ''));
    $notes        = trim((string)($data['notes'] ?? ''));
    $visible      = isset($data['visible']) ? (int)(bool)$data['visible'] : 1;
    $sortOrder    = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

    if ($id !== null && $id > 0) {
        db()->prepare(
            'UPDATE local_products
                SET title=?, description=?, price=?, currency_symbol=?, category=?, category_slug=?,
                    sku=?, unit_of_sale=?, pack_quantity=?, price_display=?,
                    image_url=?, notes=?, visible=?, sort_order=?, updated_at=NOW()
              WHERE id=?'
        )->execute([$title, $description, $price, $sym, $category, $categorySlug ?: null, $sku ?: null, $unitOfSale, $packQty, $priceDisplay ?: null, $imageUrl, $notes, $visible, $sortOrder, $id]);
        return $id;
    }

    $st = db()->prepare(
        'INSERT INTO local_products
            (title, description, price, currency_symbol, category, category_slug, sku, unit_of_sale, pack_quantity, price_display, image_url, notes, visible, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$title, $description, $price, $sym, $category, $categorySlug ?: null, $sku ?: null, $unitOfSale, $packQty, $priceDisplay ?: null, $imageUrl, $notes, $visible, $sortOrder]);
    return (int)db()->lastInsertId();
}

/**
 * Elimina un producto local definitivamente.
 */
function delete_local_product(int $id): void {
    if ($id < 1) throw new RuntimeException('ID de producto inválido.');
    db()->prepare('DELETE FROM local_products WHERE id=?')->execute([$id]);
}

/**
 * Marca un producto de la API como eliminado del catálogo local
 * (crea o actualiza su override con deleted=1, visible=0).
 */
function delete_api_product(string $handle): void {
    $handle = trim($handle);
    if ($handle === '') throw new RuntimeException('Handle inválido.');
    db()->prepare(
        'INSERT INTO product_overrides (api_handle, visible, deleted)
         VALUES (?, 0, 1)
         ON DUPLICATE KEY UPDATE visible=0, deleted=1, updated_at=NOW()'
    )->execute([$handle]);
}

/**
 * Restaura un producto de la API eliminado (quita el flag deleted).
 */
function restore_api_product(string $handle): void {
    $handle = trim($handle);
    if ($handle === '') throw new RuntimeException('Handle inválido.');
    db()->prepare(
        'UPDATE product_overrides SET deleted=0, visible=1, updated_at=NOW() WHERE api_handle=?'
    )->execute([$handle]);
}

/**
 * Extiende get_product_by_handle_with_override para detectar handles locales (lcl-{id}).
 */
function get_product_by_handle_with_override(string $handle): ?array {
    $handle = trim($handle);
    if ($handle === '') return null;

    /* Producto local por prefijo lcl- */
    if (str_starts_with($handle, 'lcl-')) {
        $id = (int)substr($handle, 4);
        return get_local_product_by_id($id);
    }

    /* Producto local por slug o SKU */
    try {
        $st = db()->prepare('SELECT * FROM local_products WHERE slug = ? OR sku = ? LIMIT 1');
        $st->execute([$handle, $handle]);
        $row = $st->fetch();
        if ($row) {
            return normalize_local_product($row);
        }
    } catch (Throwable) {}

    /* Producto de API */
    $product = get_product_by_handle($handle);
    if ($product === null) return null;
    return apply_override($product);
}

/* ── Banners principales (tabla banners) ─────────────────────────────────
 * Gestiona los banners rotativos del carrusel de la página de inicio.
 * ─────────────────────────────────────────────────────────────────────── */

/**
 * Devuelve todos los banners. En modo público solo los visibles.
 */
function get_banners(bool $adminMode = false): array {
    try {
        $sql = 'SELECT * FROM banners';
        if (!$adminMode) $sql .= ' WHERE visible=1';
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return db()->query($sql)->fetchAll();
    } catch (Throwable) { return []; }
}

/**
 * Devuelve un banner específico por ID o null.
 */
function get_banner_by_id(int $id): ?array {
    if ($id < 1) return null;
    try {
        $st = db()->prepare('SELECT * FROM banners WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable) { return null; }
}

/**
 * Guarda (crea o actualiza) un banner en la base de datos.
 */
function save_banner(array $data, ?int $id = null): int {
    $imageUrl = trim((string)($data['image_url'] ?? ''));
    if ($imageUrl === '') throw new RuntimeException('La imagen del banner es obligatoria.');

    $title         = trim((string)($data['title'] ?? ''));
    $eyebrow       = trim((string)($data['eyebrow'] ?? ''));
    $description   = trim((string)($data['description'] ?? ''));
    $btnPrimText   = trim((string)($data['button_primary_text'] ?? 'Explorar catálogo')) ?: 'Explorar catálogo';
    $btnPrimLink   = trim((string)($data['button_primary_link'] ?? 'catalogo.php')) ?: 'catalogo.php';
    $btnSecText    = trim((string)($data['button_secondary_text'] ?? ''));
    $btnSecLink    = trim((string)($data['button_secondary_link'] ?? ''));
    $visible       = isset($data['visible']) ? (int)(bool)$data['visible'] : 1;
    $sortOrder     = (int)($data['sort_order'] ?? 0);

    if ($id !== null && $id > 0) {
        db()->prepare(
            'UPDATE banners
                SET title=?, eyebrow=?, description=?, image_url=?,
                    button_primary_text=?, button_primary_link=?,
                    button_secondary_text=?, button_secondary_link=?,
                    visible=?, sort_order=?, updated_at=NOW()
              WHERE id=?'
        )->execute([
            $title, $eyebrow, $description, $imageUrl,
            $btnPrimText, $btnPrimLink, $btnSecText, $btnSecLink,
            $visible, $sortOrder, $id
        ]);
        return $id;
    }

    $st = db()->prepare(
        'INSERT INTO banners
            (title, eyebrow, description, image_url,
             button_primary_text, button_primary_link,
             button_secondary_text, button_secondary_link,
             visible, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([
        $title, $eyebrow, $description, $imageUrl,
        $btnPrimText, $btnPrimLink, $btnSecText, $btnSecLink,
        $visible, $sortOrder
    ]);
    return (int)db()->lastInsertId();
}

/**
 * Elimina un banner por ID.
 */
function delete_banner(int $id): void {
    if ($id < 1) throw new RuntimeException('ID de banner inválido.');
    db()->prepare('DELETE FROM banners WHERE id=?')->execute([$id]);
}

/**
 * Alterna la visibilidad de un banner.
 */
function toggle_banner_visibility(int $id): void {
    if ($id < 1) throw new RuntimeException('ID de banner inválido.');
    db()->prepare('UPDATE banners SET visible=IF(visible=1,0,1), updated_at=NOW() WHERE id=?')->execute([$id]);
}

/* ── Pedidos mayoristas (tablas orders y order_items) ───────────────────
 * Gestiona el ciclo completo de órdenes de clientes mayoristas:
 * 1. Cliente envía pedido desde carrito (status: pending_review)
 * 2. Admin revisa stock y confirma o descarta productos (status: confirmed)
 * 3. Cliente ve ajuste, datos bancarios (alias/CBU) y adjunta comprobante (status: payment_uploaded)
 * 4. Notificación y coordinación de envío por WhatsApp
 * ─────────────────────────────────────────────────────────────────────── */

/**
 * Obtiene los datos bancarios para transferencias configurados en settings.
 */
function get_bank_details(): array {
    return [
        'alias'  => setting('bank_alias', 'SERINSUMOS.CORDOBA'),
        'cbu'    => setting('bank_cbu', '0170099220000012345678'),
        'holder' => setting('bank_holder', 'Serinsumos Mayorista'),
        'cuit'   => setting('bank_cuit', '30-71234567-9'),
        'bank'   => setting('bank_name', 'Banco BBVA'),
    ];
}

/**
 * Guarda los datos bancarios en la tabla settings.
 */
function save_bank_details(array $data): void {
    foreach (['bank_alias', 'bank_cbu', 'bank_holder', 'bank_cuit', 'bank_name'] as $key) {
        if (isset($data[$key])) {
            $val = trim((string)$data[$key]);
            db()->prepare(
                'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)'
            )->execute([$key, $val]);
        }
    }
}

/**
 * Crea una orden a partir del carrito del cliente.
 */
function create_order(int $userId, array $cartItems, string $customerNotes = ''): int {
    if ($userId < 1) throw new RuntimeException('Usuario no válido.');
    if (empty($cartItems)) throw new RuntimeException('El pedido no tiene artículos.');

    $total = 0.0;
    $orderItemsData = [];

    foreach ($cartItems as $line) {
        $prodId = (string)($line['product_id'] ?? '');
        $qty = max(1, (int)($line['quantity'] ?? 1));
        $product = get_product_by_id($prodId);

        $title = $line['title'] ?? ($product['title'] ?? 'Producto');
        $vTitle = $line['variant_title'] ?? 'Estándar';
        $image = $line['image'] ?? ($product['image'] ?? '');
        $sku = (string)($product['_sku'] ?? ($line['sku'] ?? ''));
        $unitOfSale = (string)($product['_unit_of_sale'] ?? ($line['unit_of_sale'] ?? 'unidad'));

        $unitPrice = null;
        if (isset($line['unit_price']) && is_numeric($line['unit_price'])) {
            $unitPrice = (float)$line['unit_price'];
        } elseif (isset($line['price']) && is_numeric($line['price'])) {
            $unitPrice = (float)$line['price'];
        } elseif (isset($line['price_in_cents']) && is_numeric($line['price_in_cents'])) {
            $unitPrice = (float)($line['price_in_cents'] / 100);
        } elseif (isset($product['price_in_cents']) && (int)$product['price_in_cents'] > 0) {
            $unitPrice = (float)($product['price_in_cents'] / 100);
        } elseif (isset($product['_local_row']['price']) && $product['_local_row']['price'] !== null) {
            $unitPrice = (float)$product['_local_row']['price'];
        }

        if ($unitPrice !== null) {
            $total += ($unitPrice * $qty);
        }

        $orderItemsData[] = [
            'product_id'        => $prodId,
            'sku'               => $sku,
            'title'             => $title,
            'variant_title'     => $vTitle,
            'image_url'         => $image,
            'unit_of_sale'      => $unitOfSale,
            'unit_price'        => $unitPrice,
            'quantity_requested'=> $qty,
            'quantity_approved' => $qty,
            'item_status'       => 'approved',
        ];
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $randSuffix = strtoupper(bin2hex(random_bytes(2)));
        $orderNumTemp = 'PED-' . date('Ymd') . '-' . $randSuffix;

        $st = $pdo->prepare(
            'INSERT INTO orders (order_number, user_id, status, total_amount, currency_symbol, customer_notes)
             VALUES (?, ?, "pending_review", ?, "$", ?)'
        );
        $st->execute([$orderNumTemp, $userId, $total, trim($customerNotes)]);
        $orderId = (int)$pdo->lastInsertId();

        $orderNumFinal = 'PED-' . date('Y') . '-' . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE orders SET order_number=? WHERE id=?')->execute([$orderNumFinal, $orderId]);

        $stItem = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, product_id, sku, title, variant_title, image_url, unit_of_sale, unit_price, quantity_requested, quantity_approved, item_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($orderItemsData as $item) {
            $stItem->execute([
                $orderId,
                $item['product_id'],
                $item['sku'],
                $item['title'],
                $item['variant_title'],
                $item['image_url'],
                $item['unit_of_sale'],
                $item['unit_price'],
                $item['quantity_requested'],
                $item['quantity_approved'],
                $item['item_status'],
            ]);
        }

        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Obtiene órdenes con datos de usuario y resumen.
 */
function get_orders(?int $userId = null, ?string $statusFilter = null): array {
    $sql = 'SELECT o.*, u.name AS user_name, u.business AS user_business, u.phone AS user_phone, u.email AS user_email,
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS total_items,
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.item_status="out_of_stock") AS out_of_stock_items
              FROM orders o
              JOIN users u ON u.id = o.user_id';

    $params = [];
    $where = [];

    if ($userId !== null && $userId > 0) {
        $where[] = 'o.user_id = ?';
        $params[] = $userId;
    }

    if ($statusFilter !== null && $statusFilter !== '') {
        $where[] = 'o.status = ?';
        $params[] = $statusFilter;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY o.created_at DESC, o.id DESC';

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Obtiene una orden por ID.
 */
function get_order_by_id(int $orderId): ?array {
    if ($orderId < 1) return null;
    $st = db()->prepare(
        'SELECT o.*, u.name AS user_name, u.business AS user_business, u.phone AS user_phone, u.email AS user_email
           FROM orders o
           JOIN users u ON u.id = o.user_id
          WHERE o.id = ? LIMIT 1'
    );
    $st->execute([$orderId]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Obtiene los artículos de una orden.
 */
function get_order_items(int $orderId): array {
    if ($orderId < 1) return [];
    $st = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

/**
 * Revisa y actualiza un artículo de una orden (por parte del administrador).
 */
function update_order_item_review(int $itemId, int $approvedQty, string $status, string $note = ''): void {
    if ($itemId < 1) return;
    $status = in_array($status, ['approved', 'out_of_stock', 'removed'], true) ? $status : 'approved';
    $approvedQty = max(0, $approvedQty);
    if ($status !== 'approved') {
        $approvedQty = 0;
    }

    db()->prepare(
        'UPDATE order_items
            SET quantity_approved=?, item_status=?, admin_item_note=?
          WHERE id=?'
    )->execute([$approvedQty, $status, trim($note), $itemId]);
}

/**
 * Confirma una orden, recalcula el total según productos aprobados y actualiza su estado.
 */
function confirm_order_by_admin(int $orderId, ?string $adminNotes = null): void {
    $order = get_order_by_id($orderId);
    if (!$order) throw new RuntimeException('Orden no encontrada.');

    $items = get_order_items($orderId);
    $newTotal = 0.0;
    foreach ($items as $it) {
        if ($it['item_status'] === 'approved' && (int)$it['quantity_approved'] > 0) {
            $price = $it['unit_price'] !== null ? (float)$it['unit_price'] : 0.0;
            $newTotal += ($price * (int)$it['quantity_approved']);
        }
    }

    $st = db()->prepare(
        'UPDATE orders
            SET status="confirmed", total_amount=?, admin_notes=?, updated_at=NOW()
          WHERE id=?'
    );
    $st->execute([$newTotal, $adminNotes !== null ? trim($adminNotes) : $order['admin_notes'], $orderId]);
}

/**
 * Registra comprobante de pago subido por el cliente para su orden.
 */
function record_order_receipt(int $orderId, int $userId, string $filename): void {
    if ($orderId < 1 || $userId < 1) throw new RuntimeException('Datos de comprobante inválidos.');
    $st = db()->prepare(
        'UPDATE orders
            SET receipt_path=?, receipt_uploaded_at=NOW(), status="payment_uploaded", updated_at=NOW()
          WHERE id=? AND user_id=?'
    );
    $st->execute([$filename, $orderId, $userId]);
}

/**
 * Genera el enlace de WhatsApp con mensaje personalizado para coordinar entrega.
 */
function order_whatsapp_url(array $order): string {
    $orderNum = $order['order_number'] ?? ('#' . ($order['id'] ?? ''));
    $clientName = trim((string)($order['user_name'] ?? ''));
    $business = trim((string)($order['user_business'] ?? ''));
    $total = isset($order['total_amount']) ? ('$' . number_format((float)$order['total_amount'], 2, ',', '.')) : '';

    $msg = "¡Hola Serinsumos! Acabo de transferir y adjuntar el comprobante de pago de mi pedido {$orderNum}.\n";
    if ($clientName !== '') $msg .= "• Cliente: {$clientName}\n";
    if ($business !== '') $msg .= "• Negocio: {$business}\n";
    if ($total !== '') $msg .= "• Importe transferido: {$total}\n";
    $msg .= "\nQuisiera coordinar la entrega o envío de los productos. ¡Muchas gracias!";

    return whatsapp_url($msg);
}

/* ── Gestión de usuarios (clientes, empleados y administradores) ─────── */

function user_email_exists(string $email, int $exceptId = 0): bool {
    $st = db()->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
    $st->execute([$email, $exceptId]);
    return (bool)$st->fetchColumn();
}

/** Crea un cliente mayorista. Devuelve el id. */
function create_customer(array $d): int {
    $name  = trim((string)($d['name'] ?? ''));
    $email = mb_strtolower(trim((string)($d['email'] ?? '')));
    $pass  = (string)($d['password'] ?? '');
    if ($name === '' || $email === '' || $pass === '') throw new RuntimeException('Completá nombre, email y contraseña.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El email no es válido.');
    if (strlen($pass) < 6) throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
    if (user_email_exists($email)) throw new RuntimeException('Ya existe un usuario con ese email.');
    db()->prepare('INSERT INTO users (name,business,phone,email,password_hash,role,wholesale_approved) VALUES (?,?,?,?,?,?,?)')
        ->execute([
            $name,
            trim((string)($d['business'] ?? '')),
            trim((string)($d['phone'] ?? '')),
            $email,
            password_hash($pass, PASSWORD_DEFAULT),
            'customer',
            !empty($d['approved']) ? 1 : 0,
        ]);
    return (int)db()->lastInsertId();
}

function update_user_profile(int $id, array $d): void {
    $name  = trim((string)($d['name'] ?? ''));
    $email = mb_strtolower(trim((string)($d['email'] ?? '')));
    if ($name === '' || $email === '') throw new RuntimeException('Nombre y email son obligatorios.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El email no es válido.');
    if (user_email_exists($email, $id)) throw new RuntimeException('Ya existe otro usuario con ese email.');
    db()->prepare('UPDATE users SET name=?, business=?, phone=?, email=? WHERE id=?')
        ->execute([$name, trim((string)($d['business'] ?? '')), trim((string)($d['phone'] ?? '')), $email, $id]);
}

function set_user_password(int $id, string $pass): void {
    if ($id < 1) throw new RuntimeException('Usuario inválido.');
    if (strlen($pass) < 6) throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')
        ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
}

/** Elimina un usuario. Los pedidos asociados se eliminan en cascada. */
function delete_user_account(int $id): void {
    $me = (int)(current_user()['id'] ?? 0);
    if ($id < 1) throw new RuntimeException('Usuario inválido.');
    if ($id === $me) throw new RuntimeException('No podés eliminar tu propia cuenta.');
    db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}

function user_orders_count(int $id): int {
    $st = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?');
    $st->execute([$id]);
    return (int)$st->fetchColumn();
}

/** Crea un empleado o administrador del panel. */
function create_staff_user(array $d): int {
    $name  = trim((string)($d['name'] ?? ''));
    $email = mb_strtolower(trim((string)($d['email'] ?? '')));
    $pass  = (string)($d['password'] ?? '');
    $role  = ((string)($d['role'] ?? 'employee')) === 'admin' ? 'admin' : 'employee';
    if ($name === '' || $email === '' || $pass === '') throw new RuntimeException('Completá nombre, email y contraseña.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El email no es válido.');
    if (strlen($pass) < 6) throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
    if (user_email_exists($email)) throw new RuntimeException('Ya existe un usuario con ese email.');
    $perms = $role === 'employee'
        ? implode(',', array_values(array_intersect((array)($d['perms'] ?? []), delegable_tabs())))
        : '';
    db()->prepare('INSERT INTO users (name,business,phone,email,password_hash,role,perms,wholesale_approved) VALUES (?,?,?,?,?,?,?,1)')
        ->execute([$name, '', '', $email, password_hash($pass, PASSWORD_DEFAULT), $role, $perms]);
    return (int)db()->lastInsertId();
}

function update_staff_user(int $id, array $d): void {
    $st = db()->prepare('SELECT id,role FROM users WHERE id=? LIMIT 1');
    $st->execute([$id]);
    if (!$st->fetch()) throw new RuntimeException('Usuario no encontrado.');
    $role = ((string)($d['role'] ?? 'employee')) === 'admin' ? 'admin' : 'employee';
    $me   = (int)(current_user()['id'] ?? 0);
    if ($id === $me && $role !== 'admin') throw new RuntimeException('No podés quitarte a vos mismo el rol de administrador.');
    $perms = $role === 'employee'
        ? implode(',', array_values(array_intersect((array)($d['perms'] ?? []), delegable_tabs())))
        : '';
    db()->prepare('UPDATE users SET role=?, perms=? WHERE id=?')->execute([$role, $perms, $id]);
}

/* ── Presupuestos manuales (tabla quotes) ────────────────────────────── */

function quote_normalize_items(array $raw): array {
    $out = [];
    foreach ($raw as $it) {
        $title = trim((string)($it['title'] ?? ''));
        if ($title === '') continue;
        $qty   = (float)str_replace(',', '.', (string)($it['qty'] ?? 1));
        $price = (float)str_replace(',', '.', (string)($it['price'] ?? 0));
        if ($qty <= 0) $qty = 1;
        if ($price < 0) $price = 0;
        $out[] = [
            'title'  => $title,
            'detail' => trim((string)($it['detail'] ?? '')),
            'qty'    => round($qty, 2),
            'price'  => round($price, 2),
        ];
    }
    return $out;
}

function quote_total(array $items): float {
    $t = 0.0;
    foreach ($items as $it) $t += ((float)$it['qty']) * ((float)$it['price']);
    return round($t, 2);
}

/** Formatea una cantidad sin decimales innecesarios: 2 -> "2", 1.5 -> "1,5". */
function quote_fmt_qty(float $q): string {
    $s = number_format($q, 2, ',', '.');
    return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
}

function next_quote_number(): string {
    $year = date('Y');
    try {
        $row = db()->query("SELECT number FROM quotes WHERE number LIKE 'PRE-$year-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $n = 1;
        if ($row && preg_match('/-(\d+)$/', (string)$row, $m)) $n = (int)$m[1] + 1;
        return sprintf('PRE-%s-%04d', $year, $n);
    } catch (Throwable) {
        return 'PRE-' . $year . '-' . sprintf('%04d', random_int(1, 9999));
    }
}

/** Crea ($id=0) o actualiza un presupuesto. Devuelve el id. */
function save_quote(array $data, int $id = 0): int {
    if (!quotes_table_ready()) throw new RuntimeException('No se pudo preparar la tabla de presupuestos.');
    $items = quote_normalize_items((array)($data['items'] ?? []));
    if (!$items) throw new RuntimeException('Agregá al menos un artículo al presupuesto.');

    $common = [
        trim((string)($data['client_name'] ?? '')),
        trim((string)($data['client_business'] ?? '')),
        trim((string)($data['client_phone'] ?? '')),
        trim((string)($data['client_email'] ?? '')),
        trim((string)($data['client_address'] ?? '')),
        json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        trim((string)($data['note'] ?? '')),
        quote_total($items),
        (string)($data['currency_symbol'] ?? '$'),
        max(1, min(365, (int)($data['valid_days'] ?? 7))),
    ];

    if ($id > 0) {
        $st = db()->prepare('UPDATE quotes SET client_name=?,client_business=?,client_phone=?,client_email=?,client_address=?,items_json=?,note=?,total=?,currency_symbol=?,valid_days=?,updated_at=NOW() WHERE id=?');
        $st->execute([...$common, $id]);
        return $id;
    }

    $st = db()->prepare('INSERT INTO quotes (client_name,client_business,client_phone,client_email,client_address,items_json,note,total,currency_symbol,valid_days,number,token,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        ...$common,
        next_quote_number(),
        bin2hex(random_bytes(16)),
        (int)(current_user()['id'] ?? 0),
    ]);
    return (int)db()->lastInsertId();
}

function quote_decode_items(?string $json): array {
    $list = json_decode((string)$json, true);
    if (!is_array($list)) return [];
    $out = [];
    foreach ($list as $it) {
        if (!is_array($it)) continue;
        $out[] = [
            'title'  => (string)($it['title'] ?? ''),
            'detail' => (string)($it['detail'] ?? ''),
            'qty'    => (float)($it['qty'] ?? 1),
            'price'  => (float)($it['price'] ?? 0),
        ];
    }
    return $out;
}

function get_quotes(): array {
    if (!quotes_table_ready()) return [];
    try {
        return db()->query('SELECT * FROM quotes ORDER BY id DESC')->fetchAll() ?: [];
    } catch (Throwable) { return []; }
}

function get_quote_by_id(int $id): ?array {
    if ($id < 1 || !quotes_table_ready()) return null;
    try {
        $st = db()->prepare('SELECT * FROM quotes WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable) { return null; }
}

function get_quote_by_token(string $token): ?array {
    $token = trim($token);
    if ($token === '' || !quotes_table_ready()) return null;
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    try {
        $st = db()->prepare('SELECT * FROM quotes WHERE token=? LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable) { return null; }
}

function delete_quote_by_id(int $id): void {
    if ($id < 1) return;
    db()->prepare('DELETE FROM quotes WHERE id=?')->execute([$id]);
}

/* ── Instagram (grilla administrable) ────────────────────────────────── */

/** Guarda una imagen subida dentro de uploads/<subdir> y devuelve su URL pública. */
function save_uploaded_image(array $file, string $subdir = 'general', int $maxMb = 8): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo recibir el archivo.');
    if ((int)($file['size'] ?? 0) > $maxMb * 1024 * 1024) throw new RuntimeException('La imagen no debe superar los ' . $maxMb . ' MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string)$file['tmp_name']);
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($exts[$mime])) throw new RuntimeException('Formato de imagen inválido. Usá JPG, PNG o WebP.');
    $dir = __DIR__ . '/uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar la carpeta de imágenes.');
    $name = $subdir . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $exts[$mime];
    if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('No se pudo guardar la imagen.');
    return site_url('uploads/' . $subdir . '/' . $name);
}

/** Máximo de publicaciones de Instagram que se pueden cargar desde el panel. */
const INSTAGRAM_MAX_POSTS = 18;
/** Cuántas publicaciones se muestran siempre en la portada. */
const INSTAGRAM_HOME_LIMIT = 6;

/** Usuario de Instagram del negocio (se usa en los enlaces y en el pie). */
function instagram_handle(): string {
    $h = trim(setting('instagram_handle', ''));
    return $h !== '' ? $h : '@ser.insumos';
}

function instagram_profile_url(): string {
    $h = ltrim(instagram_handle(), '@');
    return 'https://www.instagram.com/' . rawurlencode($h) . '/';
}

/**
 * Publicaciones cargadas a mano desde el panel, en orden: primero las más recientes.
 * Las guardadas antes de existir el mostrar/ocultar y el fijar se toman visibles.
 */
function instagram_posts(): array {
    $list = json_decode(setting('instagram_posts', ''), true);
    if (!is_array($list)) return [];
    $out = [];
    foreach ($list as $p) {
        if (!is_array($p)) continue;
        $img = trim((string)($p['image'] ?? ''));
        if ($img === '') continue;
        $out[] = [
            'image'   => $img,
            'link'    => trim((string)($p['link'] ?? '')),
            'caption' => trim((string)($p['caption'] ?? '')),
            'visible' => !array_key_exists('visible', $p) || (bool)$p['visible'],
            'pinned'  => (bool)($p['pinned'] ?? false),
        ];
        if (count($out) >= INSTAGRAM_MAX_POSTS) break;
    }
    return $out;
}

/**
 * Índices (sobre la lista completa) que van a la portada:
 * primero las fijadas y después las más recientes, hasta completar el cupo.
 */
function instagram_public_indexes(int $limit = INSTAGRAM_HOME_LIMIT): array {
    $pinned = [];
    $rest   = [];
    foreach (instagram_posts() as $i => $p) {
        if (!$p['visible']) continue;
        if ($p['pinned']) $pinned[] = $i; else $rest[] = $i;
    }
    return array_slice(array_merge($pinned, $rest), 0, max(0, $limit));
}

/** Publicaciones que se ven en la portada: las fijadas + las más recientes. */
function instagram_public_posts(int $limit = INSTAGRAM_HOME_LIMIT): array {
    $posts = instagram_posts();
    $out   = [];
    foreach (instagram_public_indexes($limit) as $i) {
        if (isset($posts[$i])) $out[] = $posts[$i];
    }
    return $out;
}

function save_instagram_posts(array $posts): void {
    $clean = [];
    foreach ($posts as $p) {
        if (!is_array($p)) continue;
        $img = trim((string)($p['image'] ?? ''));
        if ($img === '') continue;
        $clean[] = [
            'image'   => $img,
            'link'    => trim((string)($p['link'] ?? '')),
            'caption' => trim((string)($p['caption'] ?? '')),
            'visible' => !empty($p['visible']),
            'pinned'  => !empty($p['pinned']),
        ];
        if (count($clean) >= INSTAGRAM_MAX_POSTS) break;
    }
    save_setting('instagram_posts', json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

require_installation();
ensure_extended_schema();
check_maintenance_mode();
