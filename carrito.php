<?php
require_once __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'add') {
        require_verified();
        $productId = trim((string)($_POST['product_id'] ?? ''));
        $variantId = trim((string)($_POST['variant_id'] ?? ''));
        $quantity  = max(1, min(500, (int)($_POST['quantity'] ?? 1)));

        $product = get_product_by_id($productId);

        /* Si el producto no carga de la API pero tenemos datos del POST,
           igual permitimos agregarlo (API puede fallar temporalmente). */
        if (!$product) {
            // Intentar encontrarlo en catálogo local
            if (str_starts_with($productId, 'lcl-')) {
                $product = get_local_product_by_id((int)substr($productId, 4));
            }
        }

        if (!$product) {
            set_flash('error', 'No pudimos encontrar ese producto. Intentá desde el catálogo nuevamente.');
            redirect('catalogo.php');
        }

        /* Verificar que el producto sea visible (no oculto por el admin) */
        if (isset($product['_visible']) && !$product['_visible'] && !is_admin()) {
            set_flash('error', 'Este producto no está disponible actualmente.');
            redirect('catalogo.php');
        }

        /* Encontrar la variante seleccionada */
        $variant = find_variant($product, $variantId);

        /* Si no hay variante por ID (local products o ID vacío), usar la primera */
        if (!$variant && !empty($product['variants'])) {
            $variant = $product['variants'][0];
            $variantId = (string)($variant['id'] ?? $productId . '-default');
        }

        if (!$variant) {
            set_flash('error', 'No pudimos identificar la presentación del producto.');
            redirect('catalogo.php');
        }

        /* Acumular cantidad si ya estaba en el carrito */
        $cart = cart_items();
        if (isset($cart[$variantId])) {
            $quantity += (int)$cart[$variantId]['quantity'];
        }
        $quantity = min(500, $quantity);

        $cart[$variantId] = [
            'product_id'    => $product['id'],
            'variant_id'    => $variantId,
            'title'         => $product['_display_title'] ?? $product['title'],
            'image'         => $product['image'],
            'variant_title' => $variant['title'],
            'quantity'      => $quantity,
        ];
        $_SESSION['cart'] = $cart;

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
               || (isset($_POST['_ajax']) && $_POST['_ajax'] === '1');

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'         => true,
                'message'    => 'Producto agregado a tu pedido.',
                'cart_count' => cart_count(),
                'item_qty'   => $quantity,
                'variant_id' => $variantId,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        set_flash('success', 'Producto agregado a tu pedido.');
        redirect('carrito.php');
    }

    if ($act === 'update') {
        require_login();
        $id   = (string)($_POST['variant_id'] ?? '');
        $qty  = (int)($_POST['quantity'] ?? 0);
        $cart = cart_items();
        if (isset($cart[$id])) {
            if ($qty <= 0) unset($cart[$id]);
            else $cart[$id]['quantity'] = min(500, $qty);
            $_SESSION['cart'] = $cart;
        }
        redirect('carrito.php');
    }

    if ($act === 'clear') {
        $_SESSION['cart'] = [];
        set_flash('success', 'Se vació el pedido.');
        redirect('carrito.php');
    }

    if ($act === 'checkout') {
        require_verified();
        $user = current_user();
        $cart = cart_items();
        if (!$cart) {
            set_flash('error', 'Tu pedido está vacío.');
            redirect('carrito.php');
        }

        try {
            $customerNotes = trim((string)($_POST['customer_notes'] ?? ''));
            $orderId = create_order((int)$user['id'], $cart, $customerNotes);
            $order = get_order_by_id($orderId);
            $orderNum = $order ? $order['order_number'] : ('#' . $orderId);

            $_SESSION['cart'] = [];
            set_flash('success', "¡Tu pedido {$orderNum} ha sido registrado! Está pendiente de confirmación. En administración revisaremos el stock y disponibilidad para habilitarte el pago.");
            redirect('cuenta.php?pedido=' . $orderId);
        } catch (Throwable $ex) {
            set_flash('error', $ex->getMessage() ?: 'No pudimos registrar tu pedido. Intentá nuevamente.');
            redirect('carrito.php');
        }
    }
}

$cart = cart_items();
$isVerified = is_verified();

/* Enriquecer líneas de carrito con datos de precio y presentación */
$enrichedCart = [];
$totalEstimated = 0.0;
$hasKnownPrices = false;

foreach ($cart as $vId => $line) {
    $p = get_product_by_id((string)$line['product_id']);
    $unitPrice = null;
    $sku = '';
    $unitOfSale = 'unidad';

    if ($p) {
        $sku = (string)($p['_sku'] ?? '');
        $unitOfSale = (string)($p['_unit_of_sale'] ?? 'unidad');

        if (isset($p['price_in_cents']) && (int)$p['price_in_cents'] > 0) {
            $unitPrice = (float)($p['price_in_cents'] / 100);
        } elseif (isset($p['_local_row']['price']) && $p['_local_row']['price'] !== null) {
            $unitPrice = (float)$p['_local_row']['price'];
        }
    }

    $qty = (int)$line['quantity'];
    $subtotal = $unitPrice !== null ? ($unitPrice * $qty) : null;
    if ($subtotal !== null) {
        $totalEstimated += $subtotal;
        $hasKnownPrices = true;
    }

    $enrichedCart[$vId] = array_merge($line, [
        'sku'          => $sku,
        'unit_of_sale' => $unitOfSale,
        'unit_price'   => $unitPrice,
        'subtotal'     => $subtotal,
    ]);
}

site_header('Mi pedido', 'Revisá los productos de tu pedido mayorista y coordiná la disponibilidad y el pago.');
?>

<style>
.cart-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0 28px;
}
.cart-table th {
    text-align: left;
    padding: 12px 14px;
    background: #f8f9fb;
    border-bottom: 2px solid var(--line);
    font-size: 13px;
    font-weight: 600;
    color: var(--gray);
}
.cart-table td {
    padding: 16px 14px;
    border-bottom: 1px solid var(--line);
    vertical-align: middle;
}
.cart-prod-cell {
    display: flex;
    gap: 14px;
    align-items: center;
}
.cart-prod-cell img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    background: #f3f5f7;
    flex-shrink: 0;
}
.cart-prod-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    background: #eef2f5;
    display: grid;
    place-items: center;
    color: var(--gray);
    font-size: 20px;
    flex-shrink: 0;
}
.cart-summary-box {
    background: #fdfdfd;
    border: 2px solid var(--line);
    border-radius: 10px;
    padding: 24px;
    margin-top: 24px;
}
.cart-pending-note {
    background: #fff8e6;
    border-left: 4px solid var(--yellow);
    padding: 14px 18px;
    border-radius: 6px;
    font-size: 14px;
    color: var(--ink);
    margin: 18px 0;
}
</style>

<main class="page-narrow" style="max-width:960px">
    <p class="eyebrow blue">TU PRÓXIMO PROYECTO</p>
    <h1>Mi pedido</h1>

    <?php if (!$cart): ?>
        <p>Todavía no agregaste productos a tu pedido.</p>
        <a class="btn yellow" href="<?= e(site_url('catalogo.php')) ?>">Explorar catálogo mayorista</a>

    <?php else: ?>

        <div style="overflow-x:auto">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th style="width:130px">Precio Unit.</th>
                        <th style="width:140px">Cantidad</th>
                        <th style="width:130px;text-align:right">Subtotal</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($enrichedCart as $vId => $line): ?>
                    <tr>
                        <td>
                            <div class="cart-prod-cell">
                                <?php if (!empty($line['image'])): ?>
                                    <img src="<?= e($line['image']) ?>" alt="">
                                <?php else: ?>
                                    <div class="cart-prod-placeholder">▧</div>
                                <?php endif; ?>
                                <div>
                                    <b style="font-size:15px"><?= e($line['title']) ?></b>
                                    <?php if (!empty($line['sku'])): ?>
                                        <div style="font-size:12px;color:var(--gray);font-family:monospace">SKU: <?= e($line['sku']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($line['variant_title']) && $line['variant_title'] !== 'Estándar'): ?>
                                        <div style="font-size:12px;color:var(--blue)"><?= e($line['variant_title']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td style="white-space:nowrap;font-size:14px">
                            <?php if ($isVerified): ?>
                                <?php if ($line['unit_price'] !== null): ?>
                                    $<?= number_format($line['unit_price'], 2, ',', '.') ?>
                                <?php else: ?>
                                    <span style="color:var(--gray);font-size:12px">A confirmar</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="locked">🔒 Solo mayoristas</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <form method="post" style="display:flex;align-items:center;gap:6px;margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="update">
                                <input type="hidden" name="variant_id" value="<?= e($vId) ?>">
                                <input name="quantity" type="number" min="1" max="500"
                                       value="<?= (int)$line['quantity'] ?>"
                                       style="width:65px;padding:6px 8px;text-align:center"
                                       aria-label="Cantidad">
                                <button class="btn outline btn-small" type="submit" style="margin:0;padding:6px 10px" title="Actualizar cantidad">✓</button>
                            </form>
                        </td>

                        <td style="white-space:nowrap;text-align:right;font-size:15px;font-weight:600">
                            <?php if ($isVerified): ?>
                                <?php if ($line['subtotal'] !== null): ?>
                                    $<?= number_format($line['subtotal'], 2, ',', '.') ?>
                                <?php else: ?>
                                    <span style="color:var(--gray);font-weight:normal;font-size:12px">—</span>
                                <?php endif; ?>
                            <?php else: ?>
                                🔒
                            <?php endif; ?>
                        </td>

                        <td style="text-align:right">
                            <form method="post" style="display:inline;margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="update">
                                <input type="hidden" name="variant_id" value="<?= e($vId) ?>">
                                <input type="hidden" name="quantity" value="0">
                                <button type="submit" class="text-button" title="Quitar de mi pedido" style="color:var(--pink);font-size:16px;padding:4px">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
            <a class="btn outline btn-small" href="<?= e(site_url('catalogo.php')) ?>" style="margin:0">
                ← Continuar comprando
            </a>
            <form method="post" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="clear">
                <button class="text-button" type="submit" style="color:var(--gray);font-size:13px">
                    Vaciar todo el pedido
                </button>
            </form>
        </div>

        <!-- ── Resumen y Paso a Pagar ───────────────────────────────────── -->
        <div class="cart-summary-box">
            <?php if ($isVerified && $hasKnownPrices): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:16px">
                    <span style="font-size:18px;font-weight:600">Total estimado del pedido:</span>
                    <span style="font-size:24px;font-weight:700;color:var(--ink)">
                        $<?= number_format($totalEstimated, 2, ',', '.') ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="cart-pending-note">
                <b>ℹ️ Proceso de compra mayorista:</b>
                <p style="margin:6px 0 0;font-size:13px;line-height:1.5">
                    Al presionar <b>Pagar pedido</b>, tu orden ingresa al sistema en estado <b>Pendiente de confirmación</b>.
                    Nuestro equipo verificará el stock físico disponible en el depósito de cada artículo.
                    Una vez aprobado, tu pedido se actualizará automáticamente indicándote qué artículos fueron confirmados,
                    los datos bancarios (Alias y CBU) para transferir y el botón para adjuntar tu comprobante de pago.
                </p>
            </div>

            <?php if ($isVerified): ?>
                <form method="post" style="margin-top:20px;max-width:100%">
                    <?= csrf_field() ?>
                    <input type="hidden" name="act" value="checkout">

                    <label style="margin-bottom:16px">
                        Aclaraciones o notas para la entrega (opcional):
                        <textarea name="customer_notes" placeholder="Ej: Horario de entrega preferido, expreso o transporte de preferencia, etc." style="height:70px"></textarea>
                    </label>

                    <button class="btn yellow" type="submit" style="width:100%;font-size:16px;padding:16px;letter-spacing:.3px">
                        💳 Pagar pedido (Enviar a confirmación) →
                    </button>
                </form>

            <?php else: ?>
                <div style="margin-top:16px">
                    <p style="font-size:14px;color:var(--gray)">Para confirmar y pagar pedidos mayoristas necesitás iniciar sesión con una cuenta aprobada.</p>
                    <a class="btn yellow" href="<?= e(site_url('cuenta.php')) ?>">Ingresar con cuenta mayorista</a>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</main>

<?php site_footer(); ?>
