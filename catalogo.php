<?php
require_once __DIR__ . '/lib.php';

/* ── Catálogo con overrides ──────────────────────────────────────────── */
$catalog = get_products_with_overrides(200, false); // filtra ocultos para clientes
$all     = $catalog['products'] ?? [];

$q        = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
$catParam = trim((string)($_GET['categoria'] ?? ''));
$order    = (string)($_GET['orden'] ?? 'latest');
$page     = max(1, (int)($_GET['pagina'] ?? 1));

/* Filtro por categoría y búsqueda */
$filtered = array_values(array_filter($all, static function ($p) use ($q, $catParam) {
    if ($catParam !== '') {
        $cSlug = (string)($p['_category_slug'] ?? '');
        $cName = (string)($p['_category'] ?? '');
        if (strcasecmp($cSlug, $catParam) !== 0 && strcasecmp($cName, $catParam) !== 0) {
            return false;
        }
    }
    if ($q === '') return true;
    return contains_ci((string)($p['_display_title'] ?? $p['title'] ?? ''), $q)
        || contains_ci((string)($p['title'] ?? ''), $q)
        || contains_ci((string)($p['_category'] ?? ''), $q)
        || contains_ci((string)($p['_sku'] ?? ''), $q);
}));

/* Precio de referencia para el filtro (usa display_price si no hay precio de la API) */
$priceValues = array_map(static fn($p) => (int)($p['price_in_cents'] ?? 0), $all ?: [['price_in_cents' => 1]]);
$ceiling     = max(1, ...$priceValues);

$minPrice = max(0, min(100, (int)($_GET['min'] ?? 0)));
$maxPrice = max(0, min(100, (int)($_GET['max'] ?? 100)));
if ($minPrice > $maxPrice) [$minPrice, $maxPrice] = [$maxPrice, $minPrice];

if ($minPrice > 0 || $maxPrice < 100) {
    $filtered = array_values(array_filter($filtered, static function ($p) use ($ceiling, $minPrice, $maxPrice) {
        $percent = ((int)($p['price_in_cents'] ?? 0) / $ceiling) * 100;
        return $percent >= $minPrice && $percent <= $maxPrice;
    }));
}

/* Ordenar */
switch ($order) {
    case 'name':
        usort($filtered, static fn($a, $b) => strnatcasecmp(
            (string)($a['_display_title'] ?? $a['title']),
            (string)($b['_display_title'] ?? $b['title'])
        ));
        break;
    case 'price':
        usort($filtered, static fn($a, $b) => (int)$a['price_in_cents'] <=> (int)$b['price_in_cents']);
        break;
    case 'price-desc':
        usort($filtered, static fn($a, $b) => (int)$b['price_in_cents'] <=> (int)$a['price_in_cents']);
        break;
    default:
        // 'latest': mantiene el orden por sort_order aplicado en get_products_with_overrides
        break;
}

/* Paginación (12 productos por página) */
$perPage = 12;
$total   = count($filtered);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min($page, $pages);
$slice   = array_slice($filtered, ($page - 1) * $perPage, $perPage);
$from    = $total ? (($page - 1) * $perPage + 1) : 0;
$to      = min($page * $perPage, $total);

/* Categorías disponibles para el sidebar (desde tabla categories y catálogo) */
$dbCats = get_categories(true);
$categoriesList = [];
if ($dbCats) {
    foreach ($dbCats as $dbc) {
        $categoriesList[] = [
            'slug' => $dbc['slug'],
            'name' => $dbc['name'],
        ];
    }
} else {
    $rawCats = array_unique(array_filter(array_map(fn($p) => (string)$p['_category'], $all)));
    sort($rawCats);
    foreach ($rawCats as $cName) {
        $categoriesList[] = [
            'slug' => $cName,
            'name' => $cName,
        ];
    }
}

$cart = cart_items();
$isVerified = is_verified();

site_header('Tienda', 'Tienda mayorista de Serinsumos: maquinarias, tintas, papeles y sublimables para sublimación y estampado.');

function catalog_query(array $changes = []): string {
    $params = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
        else $params[$k] = $v;
    }
    return '?' . http_build_query($params);
}
?>

<style>
.category-filter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    font-size: 14px;
    color: var(--ink);
    transition: color .15s;
}
.category-filter:hover, .category-filter.on {
    color: var(--blue);
    font-weight: 600;
}
.category-filter.on span {
    color: var(--blue);
}

/* ── Tarjeta de producto: overlays ─────────────────────────────────── */
.pc-thumb-wrap {
    position: relative;
    overflow: hidden;
}

/* Botones flotantes (ojo + corazón) */
.pc-overlay-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    flex-direction: column;
    gap: 7px;
    opacity: 0;
    transform: translateX(8px);
    transition: opacity .22s ease, transform .22s ease;
    z-index: 3;
}
.pc:hover .pc-overlay-actions,
.pc:focus-within .pc-overlay-actions {
    opacity: 1;
    transform: translateX(0);
}
.pc-icon-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 0;
    background: white;
    display: grid;
    place-items: center;
    box-shadow: 0 2px 8px #17253025;
    cursor: pointer;
    transition: transform .15s, background .15s;
}
.pc-icon-btn svg { width: 16px; height: 16px; color: var(--ink); transition: color .15s; }
.pc-icon-btn:hover { transform: scale(1.12); background: var(--yellow); }

/* Favorito activo */
.pc-fav.active svg { fill: var(--pink); stroke: var(--pink); color: var(--pink); }
.pc-fav.active { background: #fff0f5; }

/* Botón carrito en barra inferior */
.pc-cart-wrap {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    transform: translateY(100%);
    transition: transform .22s ease;
    z-index: 3;
}
.pc:hover .pc-cart-wrap,
.pc:focus-within .pc-cart-wrap {
    transform: translateY(0);
}
.pc-cart-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    padding: 10px 14px;
    border: 0;
    background: var(--ink);
    color: white;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    text-decoration: none;
}
.pc-cart-btn:hover { background: #2a3d4d; color: white; }
.pc-cart-btn.in-cart { background: #1a9e60; }
.pc-cart-btn.in-cart:hover { background: #148050; }
.pc-lock-btn { background: var(--gray); }
.pc-lock-btn:hover { background: var(--gray); opacity: .88; }
.pc-cart-icon { width: 17px; height: 17px; flex-shrink: 0; }
.pc-cart-label { white-space: nowrap; }

/* Feedback animación al agregar */
@keyframes pc-pop {
    0%   { transform: scale(1); }
    50%  { transform: scale(1.06); }
    100% { transform: scale(1); }
}
.pc-cart-btn.pop { animation: pc-pop .25s ease; }

/* ── Modal Vista Rápida ──────────────────────────────────────────────── */
#qv-overlay {
    position: fixed;
    inset: 0;
    background: #17253099;
    z-index: 999;
    display: grid;
    place-items: center;
    padding: 20px;
}
#qv-overlay[hidden] { display: none; }
#qv-modal {
    background: white;
    border-radius: 12px;
    max-width: 780px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 12px 48px #17253040;
    animation: qv-in .22s ease;
}
@keyframes qv-in {
    from { transform: translateY(18px) scale(.97); opacity: 0; }
    to   { transform: translateY(0)    scale(1);   opacity: 1; }
}
#qv-close {
    position: absolute;
    top: 14px;
    right: 16px;
    border: 0;
    background: #eef2f5;
    border-radius: 50%;
    width: 34px;
    height: 34px;
    font-size: 20px;
    display: grid;
    place-items: center;
    cursor: pointer;
    z-index: 2;
    line-height: 1;
}
#qv-close:hover { background: #dde2e8; }
#qv-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}
#qv-img-wrap {
    background: #f3f5f7;
    border-radius: 12px 0 0 12px;
    overflow: hidden;
    min-height: 320px;
    display: grid;
    place-items: center;
}
#qv-img { width: 100%; height: 100%; object-fit: cover; display: block; }
#qv-info {
    padding: 32px 28px;
}
#qv-cat {
    font-size: 10px;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    color: var(--gray);
    margin: 0 0 8px;
}
#qv-title {
    font-size: 22px;
    font-family: Rubik, sans-serif;
    margin: 0 0 8px;
    line-height: 1.3;
}
#qv-sku {
    font-size: 12px;
    color: var(--gray);
    margin: 0 0 12px;
}
#qv-price {
    font-size: 22px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 12px;
}
#qv-desc {
    font-size: 13px;
    color: var(--gray);
    line-height: 1.7;
    margin: 0 0 20px;
}
#qv-actions .pc-cart-btn {
    border-radius: 30px;
    width: auto;
    padding: 12px 24px;
    font-size: 14px;
}
@media (max-width: 600px) {
    #qv-content { grid-template-columns: 1fr; }
    #qv-img-wrap { border-radius: 12px 12px 0 0; min-height: 200px; }
    #qv-info { padding: 22px 20px; }
}
</style>

<main class="shop">

    <section class="shop-banner">
        <div>
            <h1>Tienda</h1>
            <p><a href="<?= e(site_url('index.php')) ?>">Inicio</a> / Tienda</p>
        </div>
    </section>

    <!-- Aviso de precios sólo para verificados -->
    <?php if (!$isVerified): ?>
    <div style="background:#fff8d5;border-left:4px solid var(--yellow);padding:14px 20px;max-width:1180px;margin:18px auto 0;font-size:14px">
        🔒 <b>Los precios son exclusivos para clientes mayoristas verificados.</b>
        <a href="<?= e(site_url('cuenta.php?registro=1')) ?>" style="color:var(--blue);margin-left:6px">Solicitar acceso →</a>
    </div>
    <?php endif; ?>

    <div class="shop-layout">

        <!-- Productos -->
        <div>
            <div class="shop-toolbar">
                <div class="shop-views">
                    <button type="button" id="grid-view" class="on" aria-label="Ver en grilla">▦</button>
                    <button type="button" id="list-view" aria-label="Ver en lista">☷</button>
                    <span>Mostrando <?= $from ?>–<?= $to ?> de <?= $total ?></span>
                </div>

                <form method="get" class="sort-form">
                    <input type="hidden" name="q"         value="<?= e($q) ?>">
                    <input type="hidden" name="categoria" value="<?= e($catParam) ?>">
                    <input type="hidden" name="min"       value="<?= $minPrice ?>">
                    <input type="hidden" name="max"       value="<?= $maxPrice ?>">
                    <label class="sort-label">Ordenar
                        <select name="orden" onchange="this.form.submit()">
                            <option value="latest"     <?= $order === 'latest'     ? 'selected' : '' ?>>Más recientes</option>
                            <option value="name"       <?= $order === 'name'       ? 'selected' : '' ?>>Nombre</option>
                            <option value="price"      <?= $order === 'price'      ? 'selected' : '' ?>>Precio: menor a mayor</option>
                            <option value="price-desc" <?= $order === 'price-desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
                        </select>
                    </label>
                </form>
            </div>

            <?php if (!empty($catalog['error']) && !$all): ?>
                <p class="system-note">No pudimos cargar la tienda. Verificá que la API de Hostinger esté disponible.</p>

            <?php elseif (!$slice): ?>
                <p>No hay productos para esta búsqueda.</p>

            <?php else: ?>

                <!-- Modal de Vista Rápida -->
                <div id="qv-overlay" role="dialog" aria-modal="true" aria-label="Vista rápida del producto" hidden>
                    <div id="qv-modal">
                        <button id="qv-close" aria-label="Cerrar">&times;</button>
                        <div id="qv-content">
                            <div id="qv-img-wrap">
                                <img id="qv-img" src="" alt="">
                            </div>
                            <div id="qv-info">
                                <p id="qv-cat"></p>
                                <h3 id="qv-title"></h3>
                                <p id="qv-sku"></p>
                                <p id="qv-price"></p>
                                <p id="qv-desc"></p>
                                <div id="qv-actions"></div>
                                <a id="qv-link" href="#" class="btn outline" style="margin-top:10px">Ver detalle completo →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="shop-grid" id="shop-products">
                <?php foreach ($slice as $p):
                    $handle       = (string)($p['handle'] ?? $p['id']);
                    $displayTitle = e($p['_display_title'] !== '' ? $p['_display_title'] : ($p['title'] ?? 'Producto'));
                    $displayPrice = $p['_display_price'];
                    $category     = $p['_category'];
                    $productId    = (string)($p['id'] ?? $handle);
                    $variantId    = (string)($p['variants'][0]['id'] ?? '');
                    $sku          = (string)($p['_sku'] ?? '');
                    $descRaw      = trim(strip_tags((string)($p['description'] ?? '')));
                    $descShort    = mb_strlen($descRaw) > 120 ? mb_substr($descRaw, 0, 120) . '…' : $descRaw;
                    $priceRaw     = $p['_display_price'] ?? '';
                    $inCartCount  = isset($cart[$variantId]) ? (int)$cart[$variantId]['quantity'] : 0;
                ?>
                    <li class="pc" data-id="<?= e($productId) ?>">
                        <!-- Imagen con overlays de acciones -->
                        <div class="pc-thumb-wrap">
                            <a href="<?= e(site_url('producto/' . rawurlencode($handle))) ?>" class="shop-thumb">
                                <?php if (!empty($p['image'])): ?>
                                    <img src="<?= e($p['image']) ?>" alt="<?= $displayTitle ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="image-placeholder">Imagen no disponible</span>
                                <?php endif; ?>
                                <span class="shop-stripe"></span>
                            </a>

                            <!-- Acciones superpuestas -->
                            <div class="pc-overlay-actions">
                                <!-- Botón Favorito -->
                                <button type="button"
                                        class="pc-icon-btn pc-fav"
                                        data-pid="<?= e($productId) ?>"
                                        aria-label="Guardar en favoritos"
                                        title="Guardar en favoritos">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                </button>

                                <!-- Botón Vista Rápida -->
                                <button type="button"
                                        class="pc-icon-btn pc-quickview"
                                        data-pid="<?= e($productId) ?>"
                                        data-handle="<?= e($handle) ?>"
                                        data-title="<?= $displayTitle ?>"
                                        data-category="<?= e($category) ?>"
                                        data-sku="<?= e($sku) ?>"
                                        data-price="<?= e($priceRaw) ?>"
                                        data-desc="<?= e($descShort) ?>"
                                        data-img="<?= e($p['image'] ?? '') ?>"
                                        data-variant="<?= e($variantId) ?>"
                                        data-verified="<?= $isVerified ? '1' : '0' ?>"
                                        aria-label="Vista rápida"
                                        title="Vista rápida">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>

                            <!-- Botón Agregar al Carrito (rápido, sin salir del catálogo) -->
                            <?php if ($isVerified): ?>
                            <div class="pc-cart-wrap">
                                <button type="button"
                                        class="pc-cart-btn <?= $inCartCount > 0 ? 'in-cart' : '' ?>"
                                        data-pid="<?= e($productId) ?>"
                                        data-vid="<?= e($variantId) ?>"
                                        data-title="<?= $displayTitle ?>"
                                        aria-label="Agregar al pedido"
                                        title="Agregar al pedido">
                                    <svg class="pc-cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                    <span class="pc-cart-label"><?= $inCartCount > 0 ? 'En pedido (' . $inCartCount . ')' : 'Agregar' ?></span>
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="pc-cart-wrap">
                                <a href="<?= e(site_url('cuenta.php')) ?>" class="pc-cart-btn pc-lock-btn" title="Iniciar sesión para agregar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    <span class="pc-cart-label">Solo mayoristas</span>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info del producto -->
                        <div>
                            <?php if ($category !== ''): ?>
                                <p style="font-size:10px;letter-spacing:1.2px;color:var(--gray);margin:10px 0 4px;text-transform:uppercase">
                                    <?= e($category) ?>
                                </p>
                            <?php endif; ?>

                            <h2>
                                <a href="<?= e(site_url('producto/' . rawurlencode($handle))) ?>">
                                    <?= $displayTitle ?>
                                </a>
                            </h2>

                            <?php if ($isVerified): ?>
                                <p class="shop-price">
                                    <?php if ($p['_local_price'] !== ''): ?>
                                        <b><?= e($p['_local_price']) ?></b>
                                        <?php if ($p['price_formatted'] && $p['price_formatted'] !== $p['_local_price']): ?>
                                            <small style="color:var(--gray);font-size:11px;margin-left:6px;text-decoration:line-through">
                                                <?= e($p['price_formatted']) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Desde <?= e($displayPrice) ?>
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <a class="locked" href="<?= e(site_url('cuenta.php')) ?>">
                                    🔒 Precio para clientes verificados
                                </a>
                            <?php endif; ?>

                            <a class="btn yellow shop-add"
                               href="<?= e(site_url('producto/' . rawurlencode($handle))) ?>">
                                Ver producto
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Paginación -->
            <?php if ($pages > 1): ?>
                <nav class="shop-pages" aria-label="Páginas de productos">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(site_url('catalogo.php' . catalog_query(['pagina' => $page - 1]))) ?>"
                           aria-label="Anterior">‹</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <a class="<?= $i === $page ? 'on' : '' ?>"
                           href="<?= e(site_url('catalogo.php' . catalog_query(['pagina' => $i]))) ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $pages): ?>
                        <a href="<?= e(site_url('catalogo.php' . catalog_query(['pagina' => $page + 1]))) ?>"
                           aria-label="Siguiente">›</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside>
            <!-- Búsqueda -->
            <section class="shop-widget">
                <h2>Buscar</h2>
                <form method="get">
                    <label class="sr-only" for="search-input">Buscar productos</label>
                    <input id="search-input" name="q" type="search"
                           placeholder="Buscar por nombre, categoría o SKU…"
                           value="<?= e($q) ?>"
                           style="width:100%">
                    <?php if ($catParam !== ''): ?>
                        <input type="hidden" name="categoria" value="<?= e($catParam) ?>">
                    <?php endif; ?>
                    <button class="btn yellow" type="submit" style="margin-top:10px;width:100%">Buscar</button>
                    <?php if ($q !== '' || $catParam !== ''): ?>
                        <a href="<?= e(site_url('catalogo.php')) ?>"
                           style="display:block;margin-top:8px;font-size:12px;color:var(--gray)">
                            × Limpiar filtros
                        </a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- Pedido en curso -->
            <section class="shop-widget pink">
                <h2>Tu pedido</h2>
                <?php if (!$cart): ?>
                    <p>No hay productos en el pedido.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($cart as $line): ?>
                            <li><b><?= (int)$line['quantity'] ?>×</b> <?= e($line['title']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="btn yellow" href="<?= e(site_url('carrito.php')) ?>">Ir al pedido</a>
                <?php endif; ?>
            </section>

            <!-- Filtro de precio (solo para verificados) -->
            <?php if ($isVerified): ?>
            <section class="shop-widget yellow-bar">
                <h2>Filtrar por precio</h2>
                <form method="get" id="price-filter">
                    <input type="hidden" name="q"         value="<?= e($q) ?>">
                    <input type="hidden" name="categoria" value="<?= e($catParam) ?>">
                    <input type="hidden" name="orden"     value="<?= e($order) ?>">
                    <div class="shop-range">
                        <input type="range" id="min-range" name="min" min="0" max="100"
                               value="<?= $minPrice ?>" aria-label="Precio mínimo">
                        <input type="range" id="max-range" name="max" min="0" max="100"
                               value="<?= $maxPrice ?>" aria-label="Precio máximo">
                    </div>
                    <div class="shop-filter-row">
                        <button class="btn yellow" type="submit">Filtrar</button>
                        <span id="price-label">Precio: <?= $minPrice ?>% — <?= $maxPrice ?>%</span>
                    </div>
                </form>
                <?php if ($minPrice > 0 || $maxPrice < 100): ?>
                    <p><a href="<?= e(site_url('catalogo.php' . catalog_query(['min' => null, 'max' => null]))) ?>">
                        Limpiar filtro de precio
                    </a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <!-- Categorías -->
            <?php if ($categoriesList): ?>
            <section class="shop-widget blue-bar">
                <h2>Categorías</h2>
                <ul>
                    <li>
                        <a class="category-filter <?= $catParam === '' ? 'on' : '' ?>"
                           href="<?= e(site_url('catalogo.php' . catalog_query(['categoria' => null, 'pagina' => 1]))) ?>">
                            Todos <span>›</span>
                        </a>
                    </li>
                    <?php foreach ($categoriesList as $catItem):
                        $isActive = ($catParam !== '' && (strcasecmp($catParam, $catItem['slug']) === 0 || strcasecmp($catParam, $catItem['name']) === 0));
                    ?>
                    <li>
                        <a class="category-filter <?= $isActive ? 'on' : '' ?>"
                           href="<?= e(site_url('catalogo.php' . catalog_query(['categoria' => $catItem['slug'], 'pagina' => 1]))) ?>">
                            <?= e($catItem['name']) ?> <span>›</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <!-- Instagram -->
            <section class="shop-widget">
                <h2>Seguinos</h2>
                <a class="shop-ig" href="https://www.instagram.com/ser.insumos/"
                   target="_blank" rel="noopener noreferrer">
                    ◎ @ser.insumos
                </a>
            </section>
        </aside>

    </div><!-- /.shop-layout -->

    <a class="shop-top" href="#top" aria-label="Volver arriba">↑</a>
</main>

<script>
/* ── Vista grilla / lista ────────────────────────────────────────── */
const productsList = document.getElementById('shop-products');
const gridButton   = document.getElementById('grid-view');
const listButton   = document.getElementById('list-view');
if (productsList && gridButton && listButton) {
    gridButton.addEventListener('click', () => {
        productsList.className = 'shop-grid';
        gridButton.classList.add('on');
        listButton.classList.remove('on');
    });
    listButton.addEventListener('click', () => {
        productsList.className = 'shop-list';
        listButton.classList.add('on');
        gridButton.classList.remove('on');
    });
}

/* ── Slider de precio ────────────────────────────────────────────── */
const minRange   = document.getElementById('min-range');
const maxRange   = document.getElementById('max-range');
const priceLabel = document.getElementById('price-label');
function updatePriceLabel(changed) {
    if (!minRange || !maxRange || !priceLabel) return;
    if (+minRange.value > +maxRange.value) {
        if (changed === 'min') maxRange.value = minRange.value;
        else minRange.value = maxRange.value;
    }
    priceLabel.textContent = 'Precio: ' + minRange.value + '% — ' + maxRange.value + '%';
}
if (minRange && maxRange) {
    minRange.addEventListener('input', () => updatePriceLabel('min'));
    maxRange.addEventListener('input', () => updatePriceLabel('max'));
}

/* ── CSRF token (para fetch) ─────────────────────────────────────── */
const CSRF = '<?= e(csrf_token()) ?>';
const CART_URL = '<?= e(site_url('carrito.php')) ?>';

/* ── Favoritos (localStorage) ────────────────────────────────────── */
const FAV_KEY = 'serinsumos_favs';
function getFavs() {
    try { return JSON.parse(localStorage.getItem(FAV_KEY) || '[]'); } catch { return []; }
}
function saveFavs(favs) {
    localStorage.setItem(FAV_KEY, JSON.stringify(favs));
}
function applyFavUI() {
    const favs = getFavs();
    document.querySelectorAll('.pc-fav').forEach(btn => {
        const pid = btn.dataset.pid;
        btn.classList.toggle('active', favs.includes(pid));
        btn.title = favs.includes(pid) ? 'Quitar de favoritos' : 'Guardar en favoritos';
    });
}
document.addEventListener('click', e => {
    const btn = e.target.closest('.pc-fav');
    if (!btn) return;
    const pid = btn.dataset.pid;
    let favs = getFavs();
    if (favs.includes(pid)) {
        favs = favs.filter(f => f !== pid);
        showToast('Eliminado de favoritos', '#6f7980');
    } else {
        favs.push(pid);
        showToast('❤️ Guardado en favoritos');
    }
    saveFavs(favs);
    applyFavUI();
});
applyFavUI();

/* ── Agregar al carrito (AJAX) ───────────────────────────────────── */
document.addEventListener('click', async e => {
    const btn = e.target.closest('.pc-cart-btn');
    if (!btn || btn.tagName === 'A') return; // skip anchor (lock btn)
    const pid = btn.dataset.pid;
    const vid = btn.dataset.vid;
    const title = btn.dataset.title || '';
    if (!pid) return;

    btn.disabled = true;
    const label = btn.querySelector('.pc-cart-label');
    const origLabel = label ? label.textContent : '';
    if (label) label.textContent = '⏳ Agregando…';

    try {
        const body = new URLSearchParams({
            act: 'add',
            _csrf: CSRF,
            _ajax: '1',
            product_id: pid,
            variant_id: vid,
            quantity: '1'
        });
        const res = await fetch(CART_URL, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        });

        const data = await res.json().catch(() => null);

        btn.classList.add('in-cart', 'pop');
        btn.removeEventListener('animationend', null);
        btn.addEventListener('animationend', () => btn.classList.remove('pop'), { once: true });

        const newQty = data && data.item_qty ? data.item_qty : ((parseInt(origLabel.match(/\((\d+)\)/)?.[1]) || 0) + 1);
        if (label) label.textContent = 'En pedido (' + newQty + ')';

        if (data && typeof data.cart_count === 'number') {
            setCartBadgeTotal(data.cart_count);
        } else {
            updateCartBadge(1);
        }

        showToast('🛒 ' + (title || 'Producto') + ' agregado al pedido');
    } catch (err) {
        if (label) label.textContent = origLabel;
        showToast('No se pudo agregar. Intentá nuevamente.', 'var(--pink)');
    } finally {
        btn.disabled = false;
    }
});

function setCartBadgeTotal(total) {
    let badge = document.querySelector('.cart-link b');
    if (total > 0) {
        if (badge) {
            badge.textContent = total;
        } else {
            const link = document.querySelector('.cart-link');
            if (link) {
                const b = document.createElement('b');
                b.textContent = total;
                link.appendChild(b);
            }
        }
    } else if (badge) {
        badge.remove();
    }
}

function updateCartBadge(delta) {
    const badge = document.querySelector('.cart-link b');
    if (badge) {
        badge.textContent = Math.max(0, (parseInt(badge.textContent) || 0) + delta);
    } else {
        // No hay badge, agregar uno
        const link = document.querySelector('.cart-link');
        if (link) {
            const b = document.createElement('b');
            b.textContent = delta;
            link.appendChild(b);
        }
    }
}

/* ── Toast de notificación ───────────────────────────────────────── */
let toastTimer;
function showToast(msg, color = '#1a9e60') {
    let toast = document.getElementById('pc-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'pc-toast';
        toast.style.cssText = [
            'position:fixed', 'bottom:88px', 'left:50%', 'transform:translateX(-50%)',
            'background:var(--ink)', 'color:white', 'padding:11px 22px', 'border-radius:24px',
            'font-size:14px', 'font-weight:600', 'z-index:1000', 'pointer-events:none',
            'box-shadow:0 4px 16px #17253040', 'transition:opacity .25s ease',
            'border-left:4px solid ' + color,
        ].join(';');
        document.body.appendChild(toast);
    }
    toast.style.borderLeftColor = color;
    toast.textContent = msg;
    toast.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.style.opacity = '0'; }, 2600);
}

/* ── Modal Vista Rápida ──────────────────────────────────────────── */
const qvOverlay = document.getElementById('qv-overlay');
const qvClose   = document.getElementById('qv-close');
const qvImg     = document.getElementById('qv-img');
const qvCat     = document.getElementById('qv-cat');
const qvTitle   = document.getElementById('qv-title');
const qvSku     = document.getElementById('qv-sku');
const qvPrice   = document.getElementById('qv-price');
const qvDesc    = document.getElementById('qv-desc');
const qvActions = document.getElementById('qv-actions');
const qvLink    = document.getElementById('qv-link');

function openQuickView(btn) {
    const d = btn.dataset;
    if (!qvOverlay) return;

    qvImg.src = d.img || '';
    qvImg.alt = d.title || '';
    qvCat.textContent = d.category || '';
    qvTitle.textContent = d.title || '';
    qvSku.textContent = d.sku ? 'SKU: ' + d.sku : '';
    qvLink.href = '<?= e(site_url('producto/')) ?>' + encodeURIComponent(d.handle || d.pid);

    const verified = d.verified === '1';
    if (verified && d.price) {
        qvPrice.textContent = d.price;
        qvPrice.style.display = '';
    } else if (!verified) {
        qvPrice.textContent = '🔒 Precio para clientes verificados';
        qvPrice.style.color = 'var(--gray)';
        qvPrice.style.fontSize = '14px';
        qvPrice.style.display = '';
    } else {
        qvPrice.style.display = 'none';
    }

    qvDesc.textContent = d.desc || '';

    // Botón agregar al carrito en el modal
    qvActions.innerHTML = '';
    if (verified) {
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'pc-cart-btn';
        addBtn.dataset.pid = d.pid;
        addBtn.dataset.vid = d.variant;
        addBtn.dataset.title = d.title;
        addBtn.innerHTML = '<svg class="pc-cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg><span class="pc-cart-label">Agregar al pedido</span>';
        qvActions.appendChild(addBtn);
    }

    qvOverlay.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
    qvClose.focus();
}

function closeQuickView() {
    if (qvOverlay) qvOverlay.setAttribute('hidden', '');
    document.body.style.overflow = '';
}

document.addEventListener('click', e => {
    const qvBtn = e.target.closest('.pc-quickview');
    if (qvBtn) { openQuickView(qvBtn); return; }
    if (e.target === qvOverlay) closeQuickView();
});
if (qvClose) qvClose.addEventListener('click', closeQuickView);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeQuickView();
});
</script>

<?php site_footer(); ?>
