<?php
require_once __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'login') {
        $email = lower_email(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT id, password_hash, role FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, (string)$row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$row['id'];
            set_flash('success', 'Ingresaste a tu cuenta.');
            /* Administradores y empleados van directo al panel de gestión. */
            if (in_array((string)$row['role'], ['admin', 'employee'], true)) {
                redirect('admin.php');
            }
            redirect('cuenta.php');
        }
        set_flash('error', 'Revisá tu correo y contraseña.');
        redirect('cuenta.php');
    }

    if ($act === 'register') {
        $name     = trim((string)($_POST['name'] ?? ''));
        $business = trim((string)($_POST['business'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $email    = lower_email(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || $business === '' || $phone === '' || strlen($password) < 8 || empty($_POST['consent'])) {
            set_flash('error', 'Completá todos los campos y usá una contraseña de al menos 8 caracteres.');
            redirect('cuenta.php?registro=1');
        }

        try {
            $stmt = db()->prepare('INSERT INTO users (name,business,phone,email,password_hash,role,wholesale_approved) VALUES (?,?,?,?,?,?,0)');
            $stmt->execute([$name, $business, $phone, $email, password_hash($password, PASSWORD_DEFAULT), 'customer']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            set_flash('success', 'Tu solicitud fue registrada. Revisaremos los datos del negocio para habilitar los precios mayoristas.');
            redirect('cuenta.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                set_flash('error', 'Ya existe una cuenta con ese correo electrónico.');
            } else {
                set_flash('error', 'No pudimos registrar la cuenta en este momento.');
            }
            redirect('cuenta.php?registro=1');
        }
    }

    if ($act === 'request_reset') {
        $email = lower_email(trim((string)($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $last = (int)($_SESSION['_reset_last'] ?? 0);
            if (time() - $last >= 30) {
                $_SESSION['_reset_last'] = time();
                $st = db()->prepare('SELECT email FROM users WHERE email=? LIMIT 1');
                $st->execute([$email]);
                if ($st->fetchColumn()) {
                    $token = bin2hex(random_bytes(32));
                    $st = db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE email=? AND used_at IS NULL');
                    $st->execute([$email]);
                    $st = db()->prepare('INSERT INTO password_resets (email,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
                    $st->execute([$email, hash('sha256', $token)]);
                    $link = site_url('cuenta.php?restablecer=' . rawurlencode($token));
                    $subject = 'Restablecer contraseña · Serinsumos';
                    $body = "Recibimos una solicitud para cambiar la contraseña de tu cuenta de Serinsumos.\n\nAbrí este enlace en los próximos 30 minutos para elegir una contraseña nueva:\n" . $link . "\n\nSi no hiciste esta solicitud, ignorá este correo.";
                    $headers = 'From: ' . MAIL_FROM . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
                    @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
                }
            }
        }
        set_flash('success', 'Si el correo está registrado, enviaremos instrucciones para restablecer la contraseña. Revisá también correo no deseado.');
        redirect('cuenta.php?recuperar=1');
    }

    if ($act === 'reset_password') {
        $token = trim((string)($_POST['token'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 8 || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            set_flash('error', 'El enlace no es válido o la contraseña tiene menos de 8 caracteres.');
            redirect('cuenta.php');
        }
        $st = db()->prepare('SELECT id,email FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
        $st->execute([hash('sha256', $token)]);
        $reset = $st->fetch();
        if (!$reset) {
            set_flash('error', 'El enlace expiró o ya fue utilizado. Solicitá uno nuevo.');
            redirect('cuenta.php?recuperar=1');
        }
        $st = db()->prepare('UPDATE users SET password_hash=? WHERE email=?');
        $st->execute([password_hash($password, PASSWORD_DEFAULT), $reset['email']]);
        $st = db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE email=?');
        $st->execute([$reset['email']]);
        session_regenerate_id(true);
        unset($_SESSION['user_id']);
        set_flash('success', 'Contraseña actualizada. Ya podés ingresar.');
        redirect('cuenta.php');
    }

    if ($act === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('success', 'Cerraste la sesión.');
        redirect('cuenta.php');
    }

    /* Subida de comprobante para un pedido mayorista */
    if ($act === 'upload_order_receipt') {
        require_login();
        $u = current_user();
        $orderId = (int)($_POST['order_id'] ?? 0);
        $order = get_order_by_id($orderId);

        if (!$order || (int)$order['user_id'] !== (int)$u['id']) {
            set_flash('error', 'Pedido no encontrado o no corresponde a tu cuenta.');
            redirect('cuenta.php');
        }

        if (!in_array($order['status'], ['confirmed', 'payment_uploaded'], true)) {
            set_flash('error', 'El pedido debe estar confirmado por administración antes de adjuntar el comprobante.');
            redirect('cuenta.php?pedido=' . $orderId);
        }

        $file = $_FILES['receipt'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$file['size'] > 10 * 1024 * 1024) {
            set_flash('error', 'Elegí un archivo válido de hasta 10 MB.');
            redirect('cuenta.php?pedido=' . $orderId);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $extensions = ['application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png'];
        if (!isset($extensions[$mime])) {
            set_flash('error', 'Formato no permitido. Usá PDF, JPG o PNG.');
            redirect('cuenta.php?pedido=' . $orderId);
        }

        $uploadDir = __DIR__ . '/uploads/receipts';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
            set_flash('error', 'No se pudo preparar la carpeta para comprobantes.');
            redirect('cuenta.php?pedido=' . $orderId);
        }

        $filename = 'receipt_order_' . $orderId . '_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
            set_flash('error', 'No se pudo guardar el archivo del comprobante.');
            redirect('cuenta.php?pedido=' . $orderId);
        }

        record_order_receipt($orderId, (int)$u['id'], $filename);
        set_flash('success', '¡Comprobante adjuntado con éxito! Ahora podés coordinar la entrega o envío directamente por WhatsApp con el botón indicado abajo.');
        redirect('cuenta.php?pedido=' . $orderId);
    }

}

$user = current_user();
$registerMode = isset($_GET['registro']) && $_GET['registro'] === '1';
$recoverMode  = isset($_GET['recuperar']) && $_GET['recuperar'] === '1';
$resetToken   = trim((string)($_GET['restablecer'] ?? ''));
$resetValid   = false;

if ($resetToken !== '' && preg_match('/^[a-f0-9]{64}$/', $resetToken)) {
    $st = db()->prepare('SELECT email FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
    $st->execute([hash('sha256', $resetToken)]);
    $resetValid = (bool)$st->fetchColumn();
}

$myOrders = [];
$selectedOrder = null;
$selectedOrderItems = [];
$bankDetails = get_bank_details();
if ($user) {
    $myOrders = get_orders((int)$user['id']);
    $reqOrderId = (int)($_GET['pedido'] ?? 0);
    if ($reqOrderId > 0) {
        $selectedOrder = get_order_by_id($reqOrderId);
        if ($selectedOrder && (int)$selectedOrder['user_id'] !== (int)$user['id']) {
            $selectedOrder = null;
        }
    }
    if (!$selectedOrder && !empty($myOrders)) {
        $selectedOrder = $myOrders[0];
    }
    if ($selectedOrder) {
        $selectedOrderItems = get_order_items((int)$selectedOrder['id']);
    }

}

site_header('Cuenta mayorista', 'Registrá tu negocio, revisá tus pedidos, realizá transferencias y adjuntá comprobantes.');
?>

<style>
.order-card {
    background: #fff;
    border: 2px solid var(--line);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 28px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}
.order-card.status-pending_review {
    border-color: var(--yellow);
}
.order-card.status-confirmed {
    border-color: #20ba67;
}
.order-card.status-payment_uploaded {
    border-color: var(--blue);
}
.order-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--line);
    padding-bottom: 18px;
    margin-bottom: 20px;
}
.order-status-banner {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 22px;
    font-size: 14px;
    line-height: 1.5;
}
.status-banner-pending {
    background: #fff9e6;
    border-left: 4px solid var(--yellow);
    color: #6c5200;
}
.status-banner-confirmed {
    background: #e8f8f0;
    border-left: 4px solid #20ba67;
    color: #0b673a;
}
.status-banner-payment {
    background: #eaf5fc;
    border-left: 4px solid var(--blue);
    color: #0d4a73;
}
.order-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 22px;
    font-size: 14px;
}
.order-items-table th {
    text-align: left;
    background: #f8f9fb;
    padding: 10px 12px;
    border-bottom: 2px solid var(--line);
    color: var(--gray);
    font-size: 13px;
}
.order-items-table td {
    padding: 12px;
    border-bottom: 1px solid var(--line);
    vertical-align: middle;
}
.item-removed-row {
    background: #fff5f5;
    color: #9c2727;
}
.item-removed-row .item-title {
    text-decoration: line-through;
    color: #888;
}
.bank-transfer-box {
    background: #f8fafd;
    border: 2px dashed var(--blue);
    border-radius: 10px;
    padding: 22px;
    margin: 22px 0;
}
.bank-copy-pill {
    background: #fff;
    border: 1px solid var(--blue);
    color: var(--blue);
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    margin-left: 8px;
    transition: all .15s;
}
.bank-copy-pill:hover {
    background: var(--blue);
    color: #fff;
}
.btn-whatsapp-order {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #20ba67;
    color: #fff;
    font-weight: 700;
    font-size: 15px;
    padding: 16px 28px;
    border-radius: 30px;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(32, 186, 103, 0.35);
    transition: transform .15s, background .15s;
}
.btn-whatsapp-order:hover {
    background: #189e55;
    color: #fff;
    transform: translateY(-2px);
}
.order-nav-pills {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 24px;
}
.order-nav-pill {
    padding: 8px 14px;
    border-radius: 20px;
    background: #f0f2f5;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    color: var(--ink);
    border: 1px solid transparent;
}
.order-nav-pill.active {
    background: var(--blue);
    color: #fff;
}
</style>

<main class="page-narrow" style="max-width:960px">
    <p class="eyebrow blue">ESPACIO MAYORISTA</p>

<?php if ($user): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:24px">
        <div>
            <h1 style="margin:0">Mi cuenta</h1>
            <p style="margin:4px 0 0;color:var(--gray);font-size:15px">
                Hola, <b><?= e($user['name'] ?: ($user['business'] ?: $user['email'])) ?></b>
                <?php if ($user['business']): ?> · <?= e($user['business']) ?><?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <?php if (in_array((string)$user['role'], ['admin', 'employee'], true)): ?>
                <a class="btn outline btn-small" href="<?= e(site_url('admin.php')) ?>">⚙️ Panel de Gestión</a>
            <?php endif; ?>
            <form method="post" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="logout">
                <button class="btn outline btn-small" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </div>

    <!-- Estado de cuenta -->
    <div class="access-box" style="margin-bottom:32px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
                <b><?= is_verified() ? '✓ Cuenta mayorista aprobada' : '⏳ Tu cuenta está pendiente de aprobación' ?></b>
                <p style="margin:6px 0 0;font-size:14px;color:var(--gray)">
                    <?= is_verified() ? 'Tenés acceso a la lista completa de precios mayoristas y podés enviar pedidos para revisión.' : 'Nuestro equipo está revisando los datos comerciales de tu negocio para habilitar precios mayoristas.' ?>
                </p>
            </div>
            <a href="<?= e(site_url('catalogo.php')) ?>" class="btn yellow btn-small" style="margin:0">
                Ir al catálogo
            </a>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         SECCIÓN: MIS PEDIDOS MAYORISTAS
    ═══════════════════════════════════════════════════════════════════ -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h2 style="margin:0;font-size:24px">Mis pedidos</h2>
        <?php if (!empty($myOrders)): ?>
            <span style="font-size:13px;color:var(--gray)"><?= count($myOrders) ?> pedidos registrados</span>
        <?php endif; ?>
    </div>

    <?php if (empty($myOrders)): ?>
        <div class="access-box" style="margin-bottom:40px">
            <p style="margin:0 0 14px">Todavía no registraste pedidos de compra.</p>
            <a class="btn yellow" href="<?= e(site_url('catalogo.php')) ?>">Armar mi primer pedido</a>
        </div>

    <?php else: ?>

        <!-- Selector de pedidos si hay más de 1 -->
        <?php if (count($myOrders) > 1): ?>
            <div class="order-nav-pills">
                <?php foreach ($myOrders as $ord):
                    $isActiveOrd = ($selectedOrder && (int)$selectedOrder['id'] === (int)$ord['id']);
                ?>
                    <a class="order-nav-pill <?= $isActiveOrd ? 'active' : '' ?>"
                       href="<?= e(site_url('cuenta.php?pedido=' . (int)$ord['id'])) ?>">
                        <?= e($ord['order_number']) ?>
                        <?php if ($ord['status'] === 'pending_review'): ?>
                            (⏳ Pendiente)
                        <?php elseif ($ord['status'] === 'confirmed'): ?>
                            (🟢 Confirmado)
                        <?php elseif ($ord['status'] === 'payment_uploaded'): ?>
                            (🟣 Pagado)
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($selectedOrder):
            $st = $selectedOrder['status'];
            $orderId = (int)$selectedOrder['id'];
        ?>
            <div class="order-card status-<?= e($st) ?>">

                <!-- Cabecera del pedido -->
                <div class="order-header-row">
                    <div>
                        <h3 style="margin:0;font-size:20px;font-family:Rubik,sans-serif">
                            Pedido <?= e($selectedOrder['order_number']) ?>
                        </h3>
                        <div style="font-size:13px;color:var(--gray);margin-top:4px">
                            Realizado el <?= e(date('d/m/Y H:i', strtotime($selectedOrder['created_at']))) ?>
                        </div>
                    </div>

                    <div style="text-align:right">
                        <?php if ($st === 'pending_review'): ?>
                            <span class="badge badge-yellow" style="font-size:13px;padding:6px 14px">
                                🟡 Pendiente de confirmación
                            </span>
                        <?php elseif ($st === 'confirmed'): ?>
                            <span class="badge badge-green" style="font-size:13px;padding:6px 14px">
                                🟢 Pedido confirmado · Listo para pagar
                            </span>
                        <?php elseif ($st === 'payment_uploaded'): ?>
                            <span class="badge badge-blue" style="font-size:13px;padding:6px 14px">
                                🟣 Comprobante enviado · En preparación
                            </span>
                        <?php elseif ($st === 'completed'): ?>
                            <span class="badge badge-green" style="font-size:13px;padding:6px 14px">
                                ✓ Pedido completado
                            </span>
                        <?php else: ?>
                            <span class="badge badge-gray" style="font-size:13px;padding:6px 14px">
                                <?= e(ucfirst($st)) ?>
                            </span>
                        <?php endif; ?>

                        <div style="font-size:18px;font-weight:700;margin-top:8px;color:var(--ink)">
                            Total: $<?= number_format((float)$selectedOrder['total_amount'], 2, ',', '.') ?>
                        </div>
                    </div>
                </div>

                <!-- Banners informativos según estado del pedido -->
                <?php if ($st === 'pending_review'): ?>
                    <div class="order-status-banner status-banner-pending">
                        <b>⏳ Tu pedido está pendiente de confirmación en administración.</b><br>
                        En nuestro depósito estamos verificando el stock físico y disponibilidad de cada artículo solicitado.
                        Te notificaremos en cuanto quede confirmado para que puedas realizar la transferencia y adjuntar tu comprobante.
                    </div>

                <?php elseif ($st === 'confirmed'): ?>
                    <div class="order-status-banner status-banner-confirmed">
                        <b>✅ ¡Tu pedido fue revisado y confirmado por la administración!</b><br>
                        A continuación te mostramos el detalle con los artículos confirmados y aquellos que fueron retirados o ajustados por falta de stock.
                        Por favor, realizá la transferencia por el importe final utilizando el <b>Alias bancario</b> indicado abajo y luego adjuntá tu comprobante para coordinar el envío.
                        <?php if (!empty($selectedOrder['admin_notes'])): ?>
                            <div style="margin-top:8px;padding-top:8px;border-top:1px solid #c2e8d3">
                                <b>Nota de administración:</b> <?= nl2br(e($selectedOrder['admin_notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($st === 'payment_uploaded'): ?>
                    <div class="order-status-banner status-banner-payment">
                        <b>🎉 ¡Comprobante de pago recibido!</b><br>
                        Hemos recibido tu comprobante de pago. Nuestro equipo está verificando la acreditación y preparando los insumos para el despacho.
                        <div style="margin-top:12px">
                            <a class="btn-whatsapp-order" href="<?= e(order_whatsapp_url($selectedOrder)) ?>" target="_blank" rel="noopener">
                                📲 Gestionar entrega o envío por WhatsApp →
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tabla de artículos del pedido -->
                <h4 style="margin:20px 0 10px;font-size:16px">Detalle de productos solicitados:</h4>
                <div style="overflow-x:auto">
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Artículo</th>
                                <th>SKU</th>
                                <th style="text-align:center">Cant. Pedida</th>
                                <th style="text-align:center">Cant. Confirmada</th>
                                <th style="text-align:right">Precio Unit.</th>
                                <th style="text-align:right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($selectedOrderItems as $item):
                            $isRemoved = in_array($item['item_status'], ['out_of_stock', 'removed'], true);
                            $approvedQty = (int)$item['quantity_approved'];
                            $reqQty = (int)$item['quantity_requested'];
                            $unitPrice = $item['unit_price'] !== null ? (float)$item['unit_price'] : 0.0;
                            $lineSubtotal = $isRemoved ? 0.0 : ($unitPrice * $approvedQty);
                        ?>
                            <tr class="<?= $isRemoved ? 'item-removed-row' : '' ?>">
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="<?= e($item['image_url']) ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:4px">
                                        <?php endif; ?>
                                        <div>
                                            <span class="item-title" style="font-weight:600"><?= e($item['title']) ?></span>
                                            <?php if (!empty($item['variant_title']) && $item['variant_title'] !== 'Estándar'): ?>
                                                <div style="font-size:12px;color:var(--gray)"><?= e($item['variant_title']) ?></div>
                                            <?php endif; ?>

                                            <?php if ($isRemoved): ?>
                                                <div style="margin-top:4px">
                                                    <span class="badge badge-pink">
                                                        ❌ Sin stock / Retirado del pedido
                                                    </span>
                                                    <?php if (!empty($item['admin_item_note'])): ?>
                                                        <span style="font-size:12px;color:#c02b2b;margin-left:6px">(<?= e($item['admin_item_note']) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($approvedQty < $reqQty): ?>
                                                <div style="margin-top:4px">
                                                    <span class="badge badge-yellow">
                                                        ⚠️ Cantidad ajustada por stock (<?= $approvedQty ?> de <?= $reqQty ?>)
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td style="font-family:monospace;font-size:12px;color:var(--gray)">
                                    <?= e($item['sku'] ?: '—') ?>
                                </td>

                                <td style="text-align:center">
                                    <?= $reqQty ?>
                                </td>

                                <td style="text-align:center;font-weight:600">
                                    <?php if ($isRemoved): ?>
                                        <span style="color:var(--pink)">0</span>
                                    <?php else: ?>
                                        <span style="color:#0d7a4a"><?= $approvedQty ?></span>
                                    <?php endif; ?>
                                </td>

                                <td style="text-align:right;white-space:nowrap">
                                    $<?= number_format($unitPrice, 2, ',', '.') ?>
                                </td>

                                <td style="text-align:right;font-weight:600;white-space:nowrap">
                                    <?php if ($isRemoved): ?>
                                        <span style="text-decoration:line-through;color:var(--gray)">$<?= number_format($unitPrice * $reqQty, 2, ',', '.') ?></span>
                                        <div style="color:var(--pink);font-size:12px">$0,00</div>
                                    <?php else: ?>
                                        $<?= number_format($lineSubtotal, 2, ',', '.') ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align:right;font-size:16px;font-weight:700;padding-top:16px">
                                    Importe total confirmado a transferir:
                                </td>
                                <td style="text-align:right;font-size:20px;font-weight:800;color:var(--ink);padding-top:16px;white-space:nowrap">
                                    $<?= number_format((float)$selectedOrder['total_amount'], 2, ',', '.') ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ── Si el pedido está CONFIRMADO: Mostrar datos bancarios y botón de comprobante ── -->
                <?php if ($st === 'confirmed'): ?>
                    <div class="bank-transfer-box">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                            <span style="font-size:22px">🏦</span>
                            <h4 style="margin:0;font-size:18px">Datos bancarios para realizar tu transferencia:</h4>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;font-size:14px">
                            <div>
                                <span style="color:var(--gray);font-size:12px;display:block">Alias para transferir:</span>
                                <b style="font-size:16px;letter-spacing:.5px"><?= e($bankDetails['alias']) ?></b>
                                <button type="button" class="bank-copy-pill" onclick="copyAlias('<?= e(addslashes($bankDetails['alias'])) ?>', this)">
                                    Copiar Alias 📋
                                </button>
                            </div>

                            <div>
                                <span style="color:var(--gray);font-size:12px;display:block">CBU / CVU:</span>
                                <b style="font-family:monospace;font-size:15px"><?= e($bankDetails['cbu']) ?></b>
                            </div>

                            <div>
                                <span style="color:var(--gray);font-size:12px;display:block">Titular de la cuenta:</span>
                                <b><?= e($bankDetails['holder']) ?></b> (<?= e($bankDetails['cuit']) ?>)
                            </div>

                            <div>
                                <span style="color:var(--gray);font-size:12px;display:block">Banco:</span>
                                <b><?= e($bankDetails['bank']) ?></b>
                            </div>
                        </div>

                        <!-- Formulario para adjuntar comprobante -->
                        <div style="margin-top:24px;padding-top:20px;border-top:1px dashed var(--line)">
                            <h4 style="margin:0 0 10px;font-size:16px">📎 Adjuntar comprobante de transferencia:</h4>
                            <p style="margin:0 0 14px;font-size:13px;color:var(--gray)">
                                Una vez realizada la transferencia bancaria por <b>$<?= number_format((float)$selectedOrder['total_amount'], 2, ',', '.') ?></b>, subí el comprobante emitido por tu banco para habilitar la coordinación inmediata del envío.
                            </p>

                            <form method="post" enctype="multipart/form-data" style="max-width:100%">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="upload_order_receipt">
                                <input type="hidden" name="order_id" value="<?= $orderId ?>">

                                <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
                                    <label style="flex:1;min-width:260px;margin-bottom:0">
                                        Seleccionar archivo (PDF, JPG o PNG · máx. 10 MB)
                                        <input type="file" name="receipt" accept="application/pdf,image/jpeg,image/png" required style="padding:10px">
                                    </label>
                                    <button class="btn yellow" type="submit" style="padding:14px 28px;margin:0">
                                        Subir comprobante de pago →
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($st === 'payment_uploaded'): ?>
                    <!-- Comprobante ya subido -->
                    <div style="background:#f8f9fb;border-radius:8px;padding:18px 20px;margin-top:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
                        <div>
                            <b>✓ Comprobante adjuntado correctamente</b>
                            <div style="font-size:12px;color:var(--gray);margin-top:2px">
                                Subido el <?= e(date('d/m/Y H:i', strtotime($selectedOrder['receipt_uploaded_at'] ?? $selectedOrder['updated_at']))) ?>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;align-items:center">
                            <a class="btn outline btn-small" href="<?= e(site_url('ver-comprobante.php?order_id=' . $orderId)) ?>" target="_blank" rel="noopener">
                                Ver mi comprobante ↗
                            </a>
                            <a class="btn-whatsapp-order" href="<?= e(order_whatsapp_url($selectedOrder)) ?>" target="_blank" rel="noopener" style="padding:10px 18px;font-size:13px">
                                📲 Coordinar por WhatsApp
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    <?php endif; ?>

<?php else: ?>
    <!-- ══════════════════════════════════════════════════════════════════
         VISITANTE NO AUTENTICADO
    ═══════════════════════════════════════════════════════════════════ -->
    <?php if ($resetToken !== ''): ?>
        <h1>Restablecer contraseña</h1>
        <?php if ($resetValid): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="reset_password">
                <input type="hidden" name="token" value="<?= e($resetToken) ?>">
                <label>Nueva contraseña
                    <input name="password" type="password" minlength="8" autocomplete="new-password" required>
                </label>
                <button class="btn yellow" type="submit">Guardar nueva contraseña</button>
            </form>
        <?php else: ?>
            <p>El enlace expiró o no es válido. Solicitá uno nuevo.</p>
            <a class="btn yellow" href="<?= e(site_url('cuenta.php?recuperar=1')) ?>">Solicitar otro enlace</a>
        <?php endif; ?>

    <?php elseif ($recoverMode): ?>
        <h1>Recuperar acceso</h1>
        <p>Ingresá el correo de tu cuenta y te enviaremos un enlace válido durante 30 minutos.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="request_reset">
            <label>Correo electrónico
                <input name="email" type="email" autocomplete="email" required>
            </label>
            <button class="btn yellow" type="submit">Enviar instrucciones</button>
        </form>
        <p><a href="<?= e(site_url('cuenta.php')) ?>">Volver a ingresar</a></p>

    <?php else: ?>
        <h1><?= $registerMode ? 'Tu negocio empieza acá.' : 'Bienvenido de nuevo.' ?></h1>
        <div class="auth-tabs">
            <a class="<?= !$registerMode ? 'selected' : '' ?>" href="<?= e(site_url('cuenta.php')) ?>">Ingresar</a>
            <a class="<?= $registerMode ? 'selected' : '' ?>" href="<?= e(site_url('cuenta.php?registro=1')) ?>">Crear cuenta mayorista</a>
        </div>

        <?php if ($registerMode): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="register">
                <label>Nombre y apellido
                    <input name="name" autocomplete="name" required placeholder="Tu nombre completo">
                </label>
                <label>Nombre del negocio / Taller
                    <input name="business" autocomplete="organization" required placeholder="Ej: Taller Creativo Estampas">
                </label>
                <label>Teléfono / WhatsApp
                    <input name="phone" type="tel" autocomplete="tel" required placeholder="Ej: 3512345678">
                </label>
                <label>Correo electrónico
                    <input name="email" type="email" autocomplete="email" required placeholder="tu@correo.com">
                </label>
                <label>Contraseña (mínimo 8 caracteres)
                    <input name="password" type="password" minlength="8" autocomplete="new-password" required>
                </label>
                <label class="consent">
                    <input type="checkbox" name="consent" value="1" required>
                    Acepto que revisen mis datos para aprobar mi cuenta mayorista.
                </label>
                <button class="btn yellow" type="submit">Solicitar acceso mayorista</button>
            </form>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="login">
                <label>Correo electrónico
                    <input name="email" type="email" autocomplete="email" required>
                </label>
                <label>Contraseña
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>
                <button class="btn yellow" type="submit">Ingresar</button>
            </form>
            <p class="text-button"><a href="<?= e(site_url('cuenta.php?recuperar=1')) ?>">Olvidé mi contraseña</a></p>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>
</main>

<script>
function copyAlias(text, btn) {
    navigator.clipboard.writeText(text).then(function() {
        const orig = btn.textContent;
        btn.textContent = '¡Copiado! ✓';
        btn.style.background = '#20ba67';
        btn.style.color = '#fff';
        btn.style.borderColor = '#20ba67';
        setTimeout(function() {
            btn.textContent = orig;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2500);
    }).catch(function() {
        prompt('Copiá el Alias manualmente:', text);
    });
}
</script>

<?php site_footer(); ?>
