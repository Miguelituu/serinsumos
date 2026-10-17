<?php
require_once __DIR__ . '/lib.php';

/* ── Detalle de producto con override ────────────────────────────────── */
$handle  = (string)($_GET['handle'] ?? '');
$product = get_product_by_handle_with_override($handle);

if (!$product) {
    http_response_code(404);
    site_header('Producto no encontrado');
    echo '<main class="page-narrow">
            <h1>Producto no encontrado</h1>
            <p>Este producto no está disponible o la tienda online no respondió.</p>
            <a class="btn yellow" href="' . e(site_url('catalogo.php')) . '">Volver al catálogo</a>
          </main>';
    site_footer();
    exit;
}

/* Verificar visibilidad: clientes no ven productos ocultos */
if (!$product['_visible'] && !is_staff()) {
    http_response_code(404);
    site_header('Producto no disponible');
    echo '<main class="page-narrow">
            <h1>Producto no disponible</h1>
            <p>Este producto no está disponible en este momento.</p>
            <a class="btn yellow" href="' . e(site_url('catalogo.php')) . '">Volver al catálogo</a>
          </main>';
    site_footer();
    exit;
}

$displayTitle = $product['_display_title'] !== '' ? $product['_display_title'] : (string)($product['title'] ?? 'Producto');
$displayPrice = $product['_display_price'];   // precio local si existe, si no precio de API
$localPrice   = $product['_local_price'];     // precio local formateado o ''
$category     = $product['_category'];
$isVerified   = is_verified();
$variants     = $product['variants'] ?? [];
$defaultVariant = $variants[0] ?? null;

$pageDesc = trim(strip_tags((string)($product['description'] ?? '')));

/* ── Productos similares de la misma categoría o catálogo ──────────── */
$allProductsData = get_products_with_overrides(60, false)['products'] ?? [];
$currentId = (string)($product['id'] ?? $handle);

// Filtrar mismo producto
$otherProducts = array_filter($allProductsData, function($p) use ($currentId, $handle) {
    $pId = (string)($p['id'] ?? $p['handle'] ?? '');
    return $pId !== $currentId && (string)($p['handle'] ?? '') !== $handle;
});

// Priorizar misma categoría
$similarProducts = [];
if ($category !== '') {
    $similarProducts = array_values(array_filter($otherProducts, function($p) use ($category) {
        return strcasecmp((string)($p['_category'] ?? ''), $category) === 0;
    }));
}

// Si hay menos de 4, completar con otros productos del catálogo
if (count($similarProducts) < 4) {
    $existingIds = array_map(fn($p) => (string)($p['id'] ?? $p['handle'] ?? ''), $similarProducts);
    foreach ($otherProducts as $op) {
        $opId = (string)($op['id'] ?? $op['handle'] ?? '');
        if (!in_array($opId, $existingIds, true)) {
            $similarProducts[] = $op;
            $existingIds[] = $opId;
            if (count($similarProducts) >= 4) break;
        }
    }
}
$similarProducts = array_slice($similarProducts, 0, 4);

site_header($displayTitle, $pageDesc);
?>

<main class="detail">

    <!-- Imagen -->
    <div>
        <?php if (!empty($product['image'])): ?>
            <img class="detail-main-image"
                 src="<?= e($product['image']) ?>"
                 alt="<?= e($displayTitle) ?>">
        <?php else: ?>
            <div class="detail-main-image image-placeholder">Imagen no disponible</div>
        <?php endif; ?>

        <?php if (can('productos') && $product['_override']): ?>
            <!-- Nota interna para admin -->
            <?php $adminNote = $product['_notes']; ?>
            <?php if ($adminNote !== ''): ?>
                <div class="system-note" style="margin-top:16px;font-size:13px;border-radius:6px">
                    <b>Nota interna:</b> <?= e($adminNote) ?>
                </div>
            <?php endif; ?>
            <?php if (!$product['_visible']): ?>
                <div class="app-flash error" style="margin-top:10px" role="alert">
                    Este producto está <b>oculto</b> del catálogo público (solo visible para admins).
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Información -->
    <div>
        <a href="<?= e(site_url('catalogo.php')) ?>">Catálogo</a>
        <?php if ($category !== ''): ?>
            / <a href="<?= e(site_url('catalogo.php?categoria=' . rawurlencode($category))) ?>"><?= e($category) ?></a>
        <?php endif; ?>

        <?php if ($category !== ''): ?>
            <p class="eyebrow blue"><?= e(strtoupper($category)) ?></p>
        <?php else: ?>
            <p class="eyebrow blue">SUBLIMACIÓN Y ESTAMPADO</p>
        <?php endif; ?>

        <h1><?= e($displayTitle) ?></h1>

        <?php if (!empty($product['_sku'])): ?>
            <p style="font-size:12px;color:var(--gray);margin-top:-8px;margin-bottom:12px">
                Código / SKU: <b><?= e($product['_sku']) ?></b>
            </p>
        <?php endif; ?>

        <?php if ($product['title'] !== $displayTitle): ?>
            <p style="font-size:12px;color:var(--gray);margin-top:-10px;margin-bottom:14px">
                Ref. Hostinger: <?= e($product['title']) ?>
            </p>
        <?php endif; ?>

        <div class="html-description" style="line-height:1.6;margin-bottom:20px"><?= nl2br(e($product['description'] ?? '')) ?></div>

        <!-- Sección de precio y compra -->
        <?php if ($isVerified): ?>

            <?php if ($defaultVariant): ?>

                <!-- Precio -->
                <div class="detail-price" id="variant-price-block">
                    <?php if ($localPrice !== ''): ?>
                        <h2 style="margin-bottom:4px"><?= e($localPrice) ?></h2>
                        <?php if ($product['price_formatted'] && $product['price_formatted'] !== $localPrice): ?>
                            <small style="color:var(--gray);text-decoration:line-through">
                                Precio Hostinger: <?= e($product['price_formatted']) ?>
                            </small>
                        <?php endif; ?>
                    <?php else: ?>
                        <h2 id="variant-price"><?= e($defaultVariant['effective_price_formatted']) ?></h2>
                    <?php endif; ?>
                </div>

                <!-- Formulario de agregar al pedido -->
                <form id="product-add-form" method="post" action="<?= e(site_url('carrito.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="act"        value="add">
                    <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                    <input type="hidden" name="handle"     value="<?= e($product['handle']) ?>">

                    <?php if (count($variants) <= 1): ?>
                        <input type="hidden" name="variant_id" value="<?= e($defaultVariant['id']) ?>">
                    <?php endif; ?>

                    <label>Presentación
                        <select name="variant_id" id="variant-select"
                                data-variants="<?= e(json_encode(
                                    array_map(static fn($v) => [
                                        'id'    => $v['id'],
                                        'title' => $v['title'],
                                        'price' => $v['effective_price_formatted'],
                                        'stock' => $v['inventory_quantity'],
                                        'tracked' => $v['manage_inventory'],
                                    ], $variants),
                                    JSON_UNESCAPED_UNICODE
                                )) ?>"
                                <?= count($variants) <= 1 ? 'disabled' : '' ?>>
                            <?php foreach ($variants as $v): ?>
                                <option value="<?= e($v['id']) ?>"
                                        data-price="<?= e($v['effective_price_formatted']) ?>"
                                        data-stock="<?= e((string)($v['inventory_quantity'] ?? '')) ?>"
                                        data-tracked="<?= !empty($v['manage_inventory']) ? '1' : '0' ?>"
                                        <?= $v['id'] === $defaultVariant['id'] ? 'selected' : '' ?>>
                                    <?= e($v['title']) ?><?= !empty($v['sku']) ? ' · cód. ' . e($v['sku']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Cantidad
                        <input type="number" id="quantity-input" name="quantity" min="1" max="500" value="1" required>
                    </label>

                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                        <button class="btn yellow" id="btn-add-cart" type="submit" style="margin:0">
                            🛒 Agregar al pedido
                        </button>
                        <a href="<?= e(site_url('carrito.php')) ?>" class="btn outline" style="margin:0">
                            Ver mi pedido →
                        </a>
                    </div>
                </form>

            <?php else: ?>
                <p>Este producto no tiene presentaciones disponibles.</p>
            <?php endif; ?>

        <?php else: ?>
            <!-- No verificado: bloquear precio y compra -->
            <div class="access-box">
                <h3>🔒 Precios exclusivos para mayoristas</h3>
                <p>
                    Registrá tu negocio y esperá la aprobación de tu cuenta para
                    consultar precios y armar pedidos.
                </p>
                <a class="btn yellow" href="<?= e(site_url('cuenta.php?registro=1')) ?>">
                    Solicitar acceso mayorista
                </a>
                <p style="margin-top:14px;font-size:13px;color:var(--gray)">
                    ¿Ya tenés cuenta? <a href="<?= e(site_url('cuenta.php')) ?>" style="color:var(--blue)">Ingresá aquí</a>
                </p>
            </div>
        <?php endif; ?>

        <!-- WhatsApp y nota de stock -->
        <a class="btn outline"
           href="<?= e(whatsapp_url('Hola, quisiera consultar por ' . $displayTitle . '.')) ?>"
           target="_blank" rel="noopener noreferrer"
           style="margin-top:16px">
            Consultar este producto por WhatsApp
        </a>

        <small style="display:block;margin-top:12px;color:var(--gray)">
            Stock e importe sujetos a confirmación antes del pago.
        </small>

        <!-- Enlace al admin -->
        <?php if (can('productos')): ?>
            <div style="margin-top:20px;padding:14px;background:#f3f5f7;border-radius:8px;font-size:13px">
                <b>Panel:</b>
                <a href="<?= e(site_url('admin.php?tab=productos')) ?>" style="color:var(--blue)">
                    Editar override en el panel →
                </a>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- ══════════════════════════════════════════════════════════════════════
     SECCIÓN: PRODUCTOS SIMILARES / RELACIONADOS
     ══════════════════════════════════════════════════════════════════════ -->
<?php if (!empty($similarProducts)): ?>
<section class="products" style="padding-top:40px;border-top:1px solid var(--line);margin-top:20px">
    <div class="section-heading">
        <div>
            <p class="eyebrow blue"><?= $category !== '' ? e(strtoupper($category)) : 'RECOMENDADOS' ?></p>
            <h2>Productos similares</h2>
        </div>
        <a href="<?= e(site_url('catalogo.php' . ($category !== '' ? '?categoria=' . rawurlencode($category) : ''))) ?>">
            Ver más en esta categoría →
        </a>
    </div>

    <div class="product-grid">
        <?php foreach ($similarProducts as $sp):
            render_product_card($sp);
        endforeach; ?>
    </div>
</section>
<?php endif; ?>

<script>
/* Actualizar precio al cambiar variante */
const variantSelect = document.getElementById('variant-select');
const variantPrice  = document.getElementById('variant-price');
if (variantSelect && variantPrice) {
    variantSelect.addEventListener('change', () => {
        const option = variantSelect.options[variantSelect.selectedIndex];
        if (option.dataset.price) variantPrice.textContent = option.dataset.price;
    });
}

/* Agregar al carrito vía AJAX sin salir de la página */
const addForm = document.getElementById('product-add-form');
const btnAdd  = document.getElementById('btn-add-cart');
const CSRF    = '<?= e(csrf_token()) ?>';
const CART_URL = '<?= e(site_url('carrito.php')) ?>';

if (addForm && btnAdd) {
    addForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const origText = btnAdd.innerHTML;
        btnAdd.disabled = true;
        btnAdd.innerHTML = '⏳ Agregando…';

        const formData = new FormData(addForm);
        formData.append('_ajax', '1');

        try {
            const res = await fetch(CART_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(formData)
            });

            const data = await res.json().catch(() => null);

            // Actualizar badge en header
            if (data && typeof data.cart_count === 'number') {
                updateCartBadgeTotal(data.cart_count);
            }

            btnAdd.innerHTML = '✓ ¡Agregado al pedido!';
            btnAdd.style.background = '#1a9e60';
            btnAdd.style.color = '#fff';

            showProductToast('🛒 <?= e(addslashes($displayTitle)) ?> agregado a tu pedido');

            setTimeout(() => {
                btnAdd.disabled = false;
                btnAdd.innerHTML = origText;
                btnAdd.style.background = '';
                btnAdd.style.color = '';
            }, 2500);

        } catch (err) {
            btnAdd.disabled = false;
            btnAdd.innerHTML = origText;
            showProductToast('No se pudo agregar al pedido. Intentá nuevamente.', 'var(--pink)');
        }
    });
}

function updateCartBadgeTotal(total) {
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

let toastTimer;
function showProductToast(msg, color = '#1a9e60') {
    let toast = document.getElementById('prod-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'prod-toast';
        toast.style.cssText = [
            'position:fixed', 'bottom:88px', 'left:50%', 'transform:translateX(-50%)',
            'background:var(--ink)', 'color:white', 'padding:12px 24px', 'border-radius:24px',
            'font-size:14px', 'font-weight:600', 'z-index:1000', 'pointer-events:none',
            'box-shadow:0 6px 20px rgba(23,37,48,0.3)', 'transition:opacity .25s ease',
            'border-left:4px solid ' + color,
        ].join(';');
        document.body.appendChild(toast);
    }
    toast.style.borderLeftColor = color;
    toast.textContent = msg;
    toast.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}
</script>

<?php site_footer(); ?>
