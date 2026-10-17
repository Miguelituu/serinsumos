<?php
require_once __DIR__ . '/lib.php';
require_admin();

/* ── Procesar POST ───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = (string)($_POST['act'] ?? '');
    try {
        if ($act === 'toggle_user') {
            $id       = (int)($_POST['user_id'] ?? 0);
            $current  = (int)($_POST['approved'] ?? 0);
            $approved = $current === 1 ? 0 : 1;
            $st = db()->prepare("UPDATE users SET wholesale_approved=? WHERE id=? AND role<>'admin'");
            $st->execute([$approved, $id]);
            set_flash('success', $approved ? 'Cliente aprobado.' : 'Se revocó el acceso mayorista.');

        } elseif ($act === 'save_whatsapp') {
            $number = preg_replace('/\D+/', '', (string)($_POST['whatsapp'] ?? ''));
            if ($number !== '' && (strlen($number) < 8 || strlen($number) > 15)) {
                throw new RuntimeException('El número debe incluir código de país y tener entre 8 y 15 dígitos.');
            }
            save_setting('whatsapp', $number);
            set_flash('success', 'Número de WhatsApp guardado.');

        } elseif ($act === 'create_document') {
            $owner       = (int)($_POST['owner_id'] ?? 0);
            $reference   = trim((string)($_POST['order_reference'] ?? ''));
            $instructions = trim((string)($_POST['instructions'] ?? ''));
            $check = db()->prepare("SELECT id FROM users WHERE id=? AND role<>'admin'");
            $check->execute([$owner]);
            if (!$check->fetchColumn() || $reference === '' || $instructions === '') {
                throw new RuntimeException('Completá el cliente, la referencia y las instrucciones de pago.');
            }
            $st = db()->prepare('INSERT INTO payment_documents (owner_id,order_reference,instructions,enabled) VALUES (?,?,?,1)');
            $st->execute([$owner, $reference, $instructions]);
            set_flash('success', 'Se habilitó la carga del comprobante para ese pedido.');

        } elseif ($act === 'edit_document') {
            $id           = (int)($_POST['document_id'] ?? 0);
            $reference    = trim((string)($_POST['order_reference'] ?? ''));
            $instructions = trim((string)($_POST['instructions'] ?? ''));
            if ($id < 1 || $reference === '' || $instructions === '') {
                throw new RuntimeException('Completá la referencia y las instrucciones.');
            }
            $st = db()->prepare('UPDATE payment_documents SET order_reference=?,instructions=?,updated_at=NOW() WHERE id=?');
            $st->execute([$reference, $instructions, $id]);
            set_flash('success', 'Comprobante actualizado.');

        } elseif ($act === 'toggle_document') {
            $id = (int)($_POST['document_id'] ?? 0);
            $st = db()->prepare('UPDATE payment_documents SET enabled=IF(enabled=1,0,1),updated_at=NOW() WHERE id=?');
            $st->execute([$id]);
            set_flash('success', 'Se actualizó el estado del comprobante.');

        } elseif ($act === 'save_override') {
            $handle = trim((string)($_POST['api_handle'] ?? ''));
            if ($handle === '') throw new RuntimeException('El handle del producto es obligatorio.');

            /* Imagen: un archivo subido manda; si no, se usa la URL del campo
               (vacío = volver a la imagen original de la API de Hostinger). */
            $imageUrl = trim((string)($_POST['image_url'] ?? ''));
            if (!empty($_FILES['image_file']['tmp_name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image_file'];
                if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('La imagen no debe superar los 10 MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $exts = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
                if (!isset($exts[$mime])) throw new RuntimeException('Formato de imagen inválido. Usá JPG, PNG o WebP.');
                $uDir = __DIR__ . '/uploads/products';
                if (!is_dir($uDir) && !mkdir($uDir, 0755, true) && !is_dir($uDir)) throw new RuntimeException('No se pudo preparar la carpeta para imágenes.');
                $imgName = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $exts[$mime];
                if (!move_uploaded_file($file['tmp_name'], $uDir . '/' . $imgName)) throw new RuntimeException('No se pudo guardar la imagen.');
                $imageUrl = site_url('uploads/products/' . $imgName);
            }

            save_override($handle, [
                'label'           => $_POST['label']          ?? '',
                'price_override'  => $_POST['price_override'] ?? '',
                'currency_symbol' => $_POST['currency_symbol'] ?? '$',
                'category'        => $_POST['category']        ?? '',
                'notes'           => $_POST['notes']           ?? '',
                'image_url'       => $imageUrl,
                'visible'         => isset($_POST['visible']) && $_POST['visible'] === '1' ? 1 : 0,
                'sort_order'      => $_POST['sort_order']      ?? 0,
            ]);
            set_flash('success', 'Producto actualizado.');

        } elseif ($act === 'create_local_product') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') throw new RuntimeException('El nombre del producto es obligatorio.');

            $imageUrl = trim((string)($_POST['image_url'] ?? ''));
            if (!empty($_FILES['image_file']['tmp_name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image_file'];
                if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('La imagen no debe superar los 10 MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $exts = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
                if (!isset($exts[$mime])) throw new RuntimeException('Formato de imagen inválido. Usá JPG, PNG o WebP.');
                $uDir = __DIR__ . '/uploads/products';
                if (!is_dir($uDir) && !mkdir($uDir, 0755, true) && !is_dir($uDir)) throw new RuntimeException('No se pudo preparar la carpeta para imágenes.');
                $imgName = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $exts[$mime];
                if (!move_uploaded_file($file['tmp_name'], $uDir . '/' . $imgName)) throw new RuntimeException('No se pudo guardar la imagen.');
                $imageUrl = site_url('uploads/products/' . $imgName);
            }

            save_local_product([
                'title'           => $title,
                'description'     => trim((string)($_POST['description'] ?? '')),
                'price'           => trim((string)($_POST['price'] ?? '')),
                'currency_symbol' => trim((string)($_POST['currency_symbol'] ?? '$')),
                'category'        => trim((string)($_POST['category'] ?? '')),
                'category_slug'   => trim((string)($_POST['category_slug'] ?? '')),
                'sku'             => trim((string)($_POST['sku'] ?? '')),
                'unit_of_sale'    => trim((string)($_POST['unit_of_sale'] ?? 'unidad')),
                'pack_quantity'   => trim((string)($_POST['pack_quantity'] ?? '')),
                'price_display'   => trim((string)($_POST['price_display'] ?? '')),
                'image_url'       => $imageUrl,
                'notes'           => trim((string)($_POST['notes'] ?? '')),
                'visible'         => isset($_POST['visible']) && $_POST['visible'] === '1' ? 1 : 0,
                'sort_order'      => (int)($_POST['sort_order'] ?? 0),
            ]);
            set_flash('success', 'Producto creado exitosamente.');

        } elseif ($act === 'edit_local_product') {
            $id = (int)($_POST['local_id'] ?? 0);
            if ($id < 1) throw new RuntimeException('ID de producto inválido.');

            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') throw new RuntimeException('El nombre del producto es obligatorio.');

            $imageUrl = trim((string)($_POST['image_url'] ?? ''));
            if (!empty($_FILES['image_file']['tmp_name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image_file'];
                if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('La imagen no debe superar los 10 MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $exts = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
                if (!isset($exts[$mime])) throw new RuntimeException('Formato de imagen inválido. Usá JPG, PNG o WebP.');
                $uDir = __DIR__ . '/uploads/products';
                if (!is_dir($uDir) && !mkdir($uDir, 0755, true) && !is_dir($uDir)) throw new RuntimeException('No se pudo preparar la carpeta para imágenes.');
                $imgName = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $exts[$mime];
                if (!move_uploaded_file($file['tmp_name'], $uDir . '/' . $imgName)) throw new RuntimeException('No se pudo guardar la imagen.');
                $imageUrl = site_url('uploads/products/' . $imgName);
            }

            save_local_product([
                'title'           => $title,
                'description'     => trim((string)($_POST['description'] ?? '')),
                'price'           => trim((string)($_POST['price'] ?? '')),
                'currency_symbol' => trim((string)($_POST['currency_symbol'] ?? '$')),
                'category'        => trim((string)($_POST['category'] ?? '')),
                'category_slug'   => trim((string)($_POST['category_slug'] ?? '')),
                'sku'             => trim((string)($_POST['sku'] ?? '')),
                'unit_of_sale'    => trim((string)($_POST['unit_of_sale'] ?? 'unidad')),
                'pack_quantity'   => trim((string)($_POST['pack_quantity'] ?? '')),
                'price_display'   => trim((string)($_POST['price_display'] ?? '')),
                'image_url'       => $imageUrl,
                'notes'           => trim((string)($_POST['notes'] ?? '')),
                'visible'         => isset($_POST['visible']) && $_POST['visible'] === '1' ? 1 : 0,
                'sort_order'      => (int)($_POST['sort_order'] ?? 0),
            ], $id);
            set_flash('success', 'Producto local actualizado.');

        } elseif ($act === 'delete_local_product') {
            $id = (int)($_POST['local_id'] ?? 0);
            delete_local_product($id);
            set_flash('success', 'Producto local eliminado definitivamente.');

        } elseif ($act === 'delete_api_product') {
            $handle = trim((string)($_POST['api_handle'] ?? ''));
            delete_api_product($handle);
            set_flash('success', 'Producto eliminado del catálogo.');

        } elseif ($act === 'restore_api_product') {
            $handle = trim((string)($_POST['api_handle'] ?? ''));
            restore_api_product($handle);
            set_flash('success', 'Producto restaurado al catálogo.');

        } elseif ($act === 'create_banner') {
            $imageUrl = trim((string)($_POST['image_url'] ?? ''));
            if (!empty($_FILES['image_file']['tmp_name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image_file'];
                if ($file['size'] > 12 * 1024 * 1024) throw new RuntimeException('La imagen no debe superar los 12 MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $exts = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
                if (!isset($exts[$mime])) throw new RuntimeException('Formato de imagen no permitido. Usá JPG, PNG o WebP.');
                $uDir = __DIR__ . '/uploads/banners';
                if (!is_dir($uDir) && !mkdir($uDir, 0755, true) && !is_dir($uDir)) throw new RuntimeException('No se pudo crear la carpeta para banners.');
                $imgName = 'banner_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $exts[$mime];
                if (!move_uploaded_file($file['tmp_name'], $uDir . '/' . $imgName)) throw new RuntimeException('No se pudo guardar la imagen del banner.');
                $imageUrl = site_url('uploads/banners/' . $imgName);
            }

            if ($imageUrl === '') throw new RuntimeException('Debés subir una imagen o ingresar un enlace de imagen.');

            save_banner([
                'title'                 => trim((string)($_POST['title'] ?? '')),
                'eyebrow'               => trim((string)($_POST['eyebrow'] ?? '')),
                'description'           => trim((string)($_POST['description'] ?? '')),
                'image_url'             => $imageUrl,
                'button_primary_text'   => trim((string)($_POST['button_primary_text'] ?? '')),
                'button_primary_link'   => trim((string)($_POST['button_primary_link'] ?? '')),
                'button_secondary_text' => trim((string)($_POST['button_secondary_text'] ?? '')),
                'button_secondary_link' => trim((string)($_POST['button_secondary_link'] ?? '')),
                'visible'               => isset($_POST['visible']) && $_POST['visible'] === '1' ? 1 : 0,
                'sort_order'            => (int)($_POST['sort_order'] ?? 0),
            ]);
            set_flash('success', 'Banner agregado exitosamente.');

        } elseif ($act === 'edit_banner') {
            $id = (int)($_POST['banner_id'] ?? 0);
            if ($id < 1) throw new RuntimeException('ID de banner inválido.');

            $imageUrl = trim((string)($_POST['image_url'] ?? ''));
            if (!empty($_FILES['image_file']['tmp_name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image_file'];
                if ($file['size'] > 12 * 1024 * 1024) throw new RuntimeException('La imagen no debe superar los 12 MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $exts = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
                if (!isset($exts[$mime])) throw new RuntimeException('Formato de imagen no permitido. Usá JPG, PNG o WebP.');
                $uDir = __DIR__ . '/uploads/banners';
                if (!is_dir($uDir) && !mkdir($uDir, 0755, true) && !is_dir($uDir)) throw new RuntimeException('No se pudo crear la carpeta para banners.');
                $imgName = 'banner_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $exts[$mime];
                if (!move_uploaded_file($file['tmp_name'], $uDir . '/' . $imgName)) throw new RuntimeException('No se pudo guardar la imagen del banner.');
                $imageUrl = site_url('uploads/banners/' . $imgName);
            }

            if ($imageUrl === '') throw new RuntimeException('El banner debe tener una imagen válida.');

            save_banner([
                'title'                 => trim((string)($_POST['title'] ?? '')),
                'eyebrow'               => trim((string)($_POST['eyebrow'] ?? '')),
                'description'           => trim((string)($_POST['description'] ?? '')),
                'image_url'             => $imageUrl,
                'button_primary_text'   => trim((string)($_POST['button_primary_text'] ?? '')),
                'button_primary_link'   => trim((string)($_POST['button_primary_link'] ?? '')),
                'button_secondary_text' => trim((string)($_POST['button_secondary_text'] ?? '')),
                'button_secondary_link' => trim((string)($_POST['button_secondary_link'] ?? '')),
                'visible'               => isset($_POST['visible']) && $_POST['visible'] === '1' ? 1 : 0,
                'sort_order'            => (int)($_POST['sort_order'] ?? 0),
            ], $id);
            set_flash('success', 'Banner actualizado.');

        } elseif ($act === 'delete_banner') {
            $id = (int)($_POST['banner_id'] ?? 0);
            delete_banner($id);
            set_flash('success', 'Banner eliminado.');

        } elseif ($act === 'toggle_banner') {
            $id = (int)($_POST['banner_id'] ?? 0);
            toggle_banner_visibility($id);
            set_flash('success', 'Estado del banner actualizado.');

        } elseif ($act === 'sync_csv_catalog') {
            require_once __DIR__ . '/storage/import_csv_data.php';
            $res = run_csv_import();
            set_flash('success', "Catálogo sincronizado exitosamente desde CSV: {$res['categories']} categorías y {$res['products']} productos procesados.");

        } elseif ($act === 'review_order_item') {
            $orderId     = (int)($_POST['order_id'] ?? 0);
            $itemId      = (int)($_POST['item_id'] ?? 0);
            $approvedQty = (int)($_POST['quantity_approved'] ?? 0);
            $itemStatus  = (string)($_POST['item_status'] ?? 'approved');
            $adminNote   = (string)($_POST['admin_item_note'] ?? '');
            update_order_item_review($itemId, $approvedQty, $itemStatus, $adminNote);
            set_flash('success', 'Artículo actualizado en el pedido.');

        } elseif ($act === 'confirm_order') {
            $orderId    = (int)($_POST['order_id'] ?? 0);
            $adminNotes = (string)($_POST['admin_notes'] ?? '');
            confirm_order_by_admin($orderId, $adminNotes);
            set_flash('success', '¡Pedido confirmado exitosamente! Se habilitaron los datos bancarios y la subida de comprobante al cliente.');

        } elseif ($act === 'complete_order') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            db()->prepare('UPDATE orders SET status="completed", updated_at=NOW() WHERE id=?')->execute([$orderId]);
            set_flash('success', 'Pedido marcado como completado y entregado.');

        } elseif ($act === 'cancel_order') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            db()->prepare('UPDATE orders SET status="cancelled", updated_at=NOW() WHERE id=?')->execute([$orderId]);
            set_flash('success', 'Pedido cancelado.');

        } elseif ($act === 'save_bank_settings') {
            save_bank_details($_POST);
            set_flash('success', 'Datos bancarios (Alias, CBU, etc.) guardados correctamente.');

        } elseif ($act === 'save_site_logo') {
            $logoUrl = trim((string)($_POST['logo_url'] ?? ''));

            if (!empty($_FILES['logo_file']['tmp_name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['logo_file'];
                if ($file['size'] > 8 * 1024 * 1024) throw new RuntimeException('El archivo de logo no debe superar los 8 MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $exts = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/svg+xml'=>'svg'];
                if (!isset($exts[$mime])) throw new RuntimeException('Formato de logo inválido. Usá PNG, JPG, WebP o SVG.');
                $uDir = __DIR__ . '/uploads/branding';
                if (!is_dir($uDir) && !mkdir($uDir, 0755, true) && !is_dir($uDir)) throw new RuntimeException('No se pudo preparar la carpeta para branding.');
                $imgName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $exts[$mime];
                if (!move_uploaded_file($file['tmp_name'], $uDir . '/' . $imgName)) throw new RuntimeException('No se pudo guardar el archivo del logo.');
                $logoUrl = site_url('uploads/branding/' . $imgName);
            }

            if (isset($_POST['reset_default_logo']) && $_POST['reset_default_logo'] === '1') {
                save_setting('site_logo', '');
                set_flash('success', 'Se restableció el logo por defecto.');
            } else {
                if ($logoUrl === '') throw new RuntimeException('Subí una imagen o ingresá una URL válida para el logo.');
                save_setting('site_logo', $logoUrl);
                set_flash('success', '¡Logo principal actualizado correctamente en todo el sitio web!');
            }

        } elseif ($act === 'save_site_favicon') {
            $favUrl = trim((string)($_POST['favicon_url'] ?? ''));

            if (!empty($_FILES['favicon_file']['tmp_name']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['favicon_file'];
                if ($file['size'] > 4 * 1024 * 1024) throw new RuntimeException('El archivo de favicon no debe superar los 4 MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $exts = [
                    'image/x-icon'      => 'ico',
                    'image/vnd.microsoft.icon' => 'ico',
                    'image/png'         => 'png',
                    'image/svg+xml'     => 'svg',
                    'image/webp'        => 'webp',
                    'image/jpeg'        => 'jpg'
                ];
                if (!isset($exts[$mime])) throw new RuntimeException('Formato inválido. Usá archivo ICO, PNG o SVG para el favicon.');
                $uDir = __DIR__ . '/uploads/branding';
                if (!is_dir($uDir) && !mkdir($uDir, 0755, true) && !is_dir($uDir)) throw new RuntimeException('No se pudo preparar la carpeta para branding.');
                $favName = 'favicon_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $exts[$mime];
                if (!move_uploaded_file($file['tmp_name'], $uDir . '/' . $favName)) throw new RuntimeException('No se pudo guardar el archivo del favicon.');
                $favUrl = site_url('uploads/branding/' . $favName);
            }

            if (isset($_POST['reset_default_favicon']) && $_POST['reset_default_favicon'] === '1') {
                save_setting('site_favicon', '');
                set_flash('success', 'Se restableció el favicon por defecto.');
            } else {
                if ($favUrl === '') throw new RuntimeException('Subí un archivo o ingresá una URL válida para el favicon.');
                save_setting('site_favicon', $favUrl);
                set_flash('success', '¡Favicon actualizado correctamente!');
            }

        } elseif ($act === 'toggle_maintenance') {
            $current = setting('maintenance_mode', '0');
            $newVal = ($current === '1') ? '0' : '1';
            save_setting('maintenance_mode', $newVal);
            set_flash('success', $newVal === '1'
                ? '¡Modo mantenimiento ACTIVADO! La web ahora está pausada para el público y solo es visible para administradores.'
                : '¡Modo mantenimiento DESACTIVADO! La web vuelve a estar abierta al público general.');

        } else {
            set_flash('error', 'Acción no reconocida.');
        }
    } catch (Throwable $ex) {
        set_flash('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'No se pudo guardar. Revisá los datos e intentá nuevamente.');
    }

    /* Redirigir a la pestaña activa */
    $tab = (string)($_POST['_tab'] ?? 'clientes');
    $extra = '';
    if (!empty($_POST['_order_id'])) {
        /* Volver a la vista de detalle de ese pedido (no al listado) */
        $extra = '&ver=' . (int)$_POST['_order_id'];
    }
    redirect('admin.php?tab=' . urlencode($tab) . $extra);
}

/* ── Datos ───────────────────────────────────────────────────────────── */
$users = db()->query(
    "SELECT id,name,business,phone,email,role,wholesale_approved,created_at
       FROM users WHERE role<>'admin' ORDER BY created_at DESC"
)->fetchAll();

$docs = db()->query(
    'SELECT d.*,u.name,u.business,u.email
       FROM payment_documents d
       JOIN users u ON u.id=d.owner_id
      ORDER BY d.created_at DESC'
)->fetchAll();

/* Estadísticas rápidas */
$total_clientes       = count($users);
$pendientes           = count(array_filter($users, fn($u) => (int)$u['wholesale_approved'] === 0));
$aprobados            = $total_clientes - $pendientes;
$docs_activos         = count(array_filter($docs, fn($d) => (int)$d['enabled'] === 1));
$docs_con_comprobante = count(array_filter($docs, fn($d) => !empty($d['receipt_path'])));

$allAdminOrders     = get_orders();
$orders_pendientes  = count(array_filter($allAdminOrders, fn($o) => $o['status'] === 'pending_review'));
$orders_confirmados = count(array_filter($allAdminOrders, fn($o) => $o['status'] === 'confirmed'));
$orders_pagados     = count(array_filter($allAdminOrders, fn($o) => $o['status'] === 'payment_uploaded'));

/* Pestaña activa */
$tab = in_array((string)($_GET['tab'] ?? ''), ['clientes', 'pedidos', 'productos', 'banners', 'comprobantes', 'configuracion'])
    ? (string)$_GET['tab']
    : 'clientes';

$bankSettings = get_bank_details();

/* Productos (sólo se carga si la pestaña está activa para no consumir API innecesariamente) */
$adminProducts = [];
$adminCatalogError = false;
if ($tab === 'productos') {
    $adminCatalog = get_products_with_overrides(200, true);
    $adminProducts = $adminCatalog['products'] ?? [];
    $adminCatalogError = !empty($adminCatalog['error']);
}

/* Banners (cargados en modo admin para gestionar) */
$adminBanners = [];
if ($tab === 'banners') {
    $adminBanners = get_banners(true);
}


site_header('Administración', 'Administración de clientes mayoristas, WhatsApp y comprobantes de Serinsumos.');
?>

<style>
/* ── Pestañas admin ─────────────────────────────────────────────────── */
.admin-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--line);
    margin: 32px 0 28px;
}
.admin-tabs a {
    padding: 11px 22px;
    font-size: 14px;
    font-weight: 600;
    color: var(--gray);
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: color .15s, border-color .15s;
}
.admin-tabs a:hover { color: var(--ink); }
.admin-tabs a.active { color: var(--blue); border-bottom-color: var(--blue); }

/* ── Tarjetas de estadísticas ───────────────────────────────────────── */
.admin-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}
.admin-stat {
    background: #f3f5f7;
    border-radius: 10px;
    padding: 18px 20px;
    border-left: 4px solid var(--blue);
}
.admin-stat.yellow { border-left-color: var(--yellow); }
.admin-stat.pink   { border-left-color: var(--pink); }
.admin-stat.green  { border-left-color: #20ba67; }
.admin-stat-num {
    font-family: Rubik, sans-serif;
    font-size: 32px;
    font-weight: 700;
    line-height: 1;
    color: var(--ink);
}
.admin-stat-label {
    font-size: 12px;
    color: var(--gray);
    margin-top: 5px;
}

/* ── Badges de estado ───────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .4px;
}
.badge-green  { background: #d4f5e5; color: #0d7a4a; }
.badge-yellow { background: #fff6cc; color: #8a6500; }
.badge-gray   { background: #eef2f5; color: var(--gray); }
.badge-blue   { background: #daeffe; color: #115b8a; }
.badge-pink   { background: #fde2ef; color: #9c1756; }

/* ── Fila admin mejorada ─────────────────────────────────────────────── */
.admin-row-info { flex: 1; min-width: 0; }
.admin-row-info b { display: block; font-size: 15px; margin-bottom: 3px; }
.admin-row-info p { margin: 3px 0; color: var(--gray); font-size: 13px; }
.admin-row-info small { font-size: 12px; color: var(--gray); }
.admin-row-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.admin-row-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto; }

/* ── Formulario editar comprobante ──────────────────────────────────── */
.edit-doc-form { display: none; margin-top: 14px; padding: 16px; background: #f8f9fb; border-radius: 8px; border: 1px solid var(--line); }
.edit-doc-form.open { display: block; }
.edit-doc-form label { margin-bottom: 10px; }
.edit-doc-form input, .edit-doc-form textarea { width: 100%; }
.edit-doc-form .btn-row { display: flex; gap: 10px; margin-top: 4px; }

/* ── Confirmación revocar ───────────────────────────────────────────── */
.confirm-revoke { display:none; }
input:checked ~ .confirm-revoke { display:inline; }

/* ── Listado compacto de pedidos ────────────────────────────────────── */
.orders-list { display: flex; flex-direction: column; gap: 12px; }
.order-row {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid var(--line);
    border-left: 5px solid var(--gray);
    border-radius: 10px;
    padding: 15px 18px;
    transition: box-shadow .15s ease;
}
.order-row:hover { box-shadow: 0 6px 18px #17253014; }
.order-row.st-pending_review   { border-left-color: #e0b400; }
.order-row.st-confirmed        { border-left-color: #20ba67; }
.order-row.st-payment_uploaded { border-left-color: var(--blue); }
.order-row.st-completed        { border-left-color: #0d7a4a; }
.order-row.st-cancelled        { border-left-color: var(--pink); opacity: .85; }
.or-main { flex: 1 1 340px; min-width: 0; }
.or-top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.or-num { font-family: Rubik, sans-serif; font-size: 16px; font-weight: 700; }
.or-sub { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; font-size: 13px; color: var(--gray); margin-top: 7px; }
.or-sub b { color: var(--ink); }
.or-right { display: flex; align-items: center; gap: 16px; margin-left: auto; }
.or-total { font-family: Rubik, sans-serif; font-size: 18px; font-weight: 700; text-align: right; line-height: 1.15; }
.or-total small { display: block; font: 400 11px Hind, sans-serif; color: var(--gray); margin-top: 3px; }
@media(max-width: 640px) {
    .order-row { padding: 14px; }
    .or-main { flex: 1 1 100%; }
    .or-right { margin-left: 0; width: 100%; justify-content: space-between; }
    .or-total { text-align: left; }
}

@media(max-width: 700px) {
    .admin-stats { grid-template-columns: 1fr 1fr; }
}
@media(max-width: 420px) {
    .admin-stats { grid-template-columns: 1fr; }
    .admin-tabs a { padding: 10px 14px; font-size: 13px; }
}
</style>

<main class="page-narrow admin-page">

    <p class="eyebrow blue">PANEL INTERNO</p>
    <h1>Administración</h1>

    <?php if (setting('maintenance_mode', '0') === '1'): ?>
        <div style="background:#fef2f2;border:2px solid #ef4444;border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;box-shadow:0 4px 12px rgba(239,68,68,0.12)">
            <div style="display:flex;align-items:center;gap:12px">
                <span style="font-size:28px;line-height:1">🛠️</span>
                <div>
                    <strong style="color:#991b1b;font-size:15px;display:block">Modo Mantenimiento está ACTIVADO</strong>
                    <span style="color:#7f1d1d;font-size:13px">Los visitantes y clientes externos solo ven la pantalla con el logo y el botón de contacto de emergencia.</span>
                </div>
            </div>
            <form method="post" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="toggle_maintenance">
                <input type="hidden" name="_tab" value="<?= e($tab) ?>">
                <button class="btn btn-small" type="submit" style="background:#16a34a;color:#fff;border:none;font-weight:700;padding:8px 16px;border-radius:6px;cursor:pointer">✓ Desactivar y abrir sitio</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Estadísticas rápidas -->
    <div class="admin-stats">
        <div class="admin-stat">
            <div class="admin-stat-num"><?= $total_clientes ?></div>
            <div class="admin-stat-label">Clientes registrados</div>
        </div>
        <div class="admin-stat yellow">
            <div class="admin-stat-num"><?= $orders_pendientes ?></div>
            <div class="admin-stat-label">Pedidos a confirmar</div>
        </div>
        <div class="admin-stat green">
            <div class="admin-stat-num"><?= $orders_pagados ?></div>
            <div class="admin-stat-label">Comprobantes a revisar</div>
        </div>
        <div class="admin-stat pink">
            <div class="admin-stat-num"><?= $pendientes ?></div>
            <div class="admin-stat-label">Clientes por aprobar</div>
        </div>
    </div>

    <!-- Pestañas -->
    <nav class="admin-tabs" aria-label="Secciones del panel">
        <a href="admin.php?tab=clientes"       class="<?= $tab === 'clientes'       ? 'active' : '' ?>">
            👥 Clientes
            <?php if ($pendientes > 0): ?>
                <span class="badge badge-yellow" style="margin-left:6px"><?= $pendientes ?></span>
            <?php endif; ?>
        </a>
        <a href="admin.php?tab=pedidos"        class="<?= $tab === 'pedidos'        ? 'active' : '' ?>">
            🛒 Pedidos
            <?php if ($orders_pendientes > 0): ?>
                <span class="badge badge-yellow" style="margin-left:6px"><?= $orders_pendientes ?></span>
            <?php elseif ($orders_pagados > 0): ?>
                <span class="badge badge-blue" style="margin-left:6px"><?= $orders_pagados ?></span>
            <?php endif; ?>
        </a>
        <a href="admin.php?tab=productos"      class="<?= $tab === 'productos'      ? 'active' : '' ?>">
            📦 Productos
        </a>
        <a href="admin.php?tab=banners"        class="<?= $tab === 'banners'        ? 'active' : '' ?>">
            🖼️ Banners
        </a>
        <a href="admin.php?tab=comprobantes"   class="<?= $tab === 'comprobantes'   ? 'active' : '' ?>">
            📄 Comprobantes
            <?php if ($docs_con_comprobante > 0): ?>
                <span class="badge badge-blue" style="margin-left:6px"><?= $docs_con_comprobante ?></span>
            <?php endif; ?>
        </a>
        <a href="admin.php?tab=configuracion"  class="<?= $tab === 'configuracion'  ? 'active' : '' ?>">
            ⚙️ Configuración
            <?php if (setting('maintenance_mode', '0') === '1'): ?>
                <span class="badge" style="margin-left:6px;background:#ef4444;color:#fff;font-weight:700">Mantenimiento ON</span>
            <?php endif; ?>
        </a>
    </nav>



    <!-- ══════════════════════════════════════════════════════════════════
         PESTAÑA: CLIENTES
    ═══════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'clientes'): ?>

        <h2>Clientes mayoristas</h2>
        <p>Aprobá o revocá el acceso mayorista a los clientes registrados. Los pendientes aparecen primero.</p>

        <?php
        // Mostrar pendientes primero
        usort($users, fn($a, $b) => (int)$a['wholesale_approved'] - (int)$b['wholesale_approved']);
        ?>

        <?php if (!$users): ?>
            <p>No hay clientes registrados.</p>
        <?php else: foreach ($users as $u): ?>

            <?php
            $isApproved = (int)$u['wholesale_approved'] === 1;
            $displayName = e($u['business'] ?: $u['name']);
            ?>
            <div class="admin-row">
                <div class="admin-row-info">
                    <b><?= $displayName ?></b>
                    <p><?= e($u['email']) ?> · <?= e($u['phone']) ?></p>
                    <div class="admin-row-meta" style="margin-top:6px;gap:8px">
                        <?php if ($isApproved): ?>
                            <span class="badge badge-green">✓ Aprobado</span>
                        <?php else: ?>
                            <span class="badge badge-yellow">⏳ Pendiente</span>
                        <?php endif; ?>
                        <small>Registrado el <?= e(date('d/m/Y', strtotime($u['created_at']))) ?></small>
                    </div>
                </div>

                <div class="admin-row-actions">
                    <?php if ($isApproved): ?>
                        <!-- Revocar con confirmación vía JS -->
                        <form method="post"
                              onsubmit="return confirm('¿Seguro que querés revocar el acceso mayorista a <?= addslashes($displayName) ?>?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act"      value="toggle_user">
                            <input type="hidden" name="_tab"     value="clientes">
                            <input type="hidden" name="user_id"  value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="approved" value="<?= (int)$u['wholesale_approved'] ?>">
                            <button class="btn outline" type="submit" style="color:var(--pink);border-color:var(--pink)">
                                Revocar acceso
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act"      value="toggle_user">
                            <input type="hidden" name="_tab"     value="clientes">
                            <input type="hidden" name="user_id"  value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="approved" value="<?= (int)$u['wholesale_approved'] ?>">
                            <button class="btn yellow" type="submit">Aprobar cliente</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php endforeach; endif; ?>

        <div style="margin-top:28px">
            <a class="btn yellow" href="https://ecommerce.hostinger.com/" target="_blank" rel="noopener noreferrer">
                Gestionar productos y pedidos ↗
            </a>
        </div>

    <!-- ══════════════════════════════════════════════════════════════════
         PESTAÑA: PEDIDOS (ÓRDENES DE COMPRA MAYORISTA)
    ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'pedidos'): ?>

        <?php
        $statusFilter = trim((string)($_GET['estado'] ?? ''));
        $viewOrderId  = (int)($_GET['ver'] ?? $_GET['pedido'] ?? 0);
        $ordersList = $allAdminOrders;
        if ($statusFilter !== '') {
            $ordersList = array_values(array_filter($ordersList, fn($o) => $o['status'] === $statusFilter));
        }
        $backUrl = 'admin.php?tab=pedidos' . ($statusFilter !== '' ? '&estado=' . urlencode($statusFilter) : '');
        $orderFound = $viewOrderId < 1;
        foreach ($ordersList as $__o) {
            if ((int)$__o['id'] === $viewOrderId) { $orderFound = true; break; }
        }
        ?>

        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
            <div>
                <?php if ($viewOrderId > 0): ?>
                    <a href="<?= e($backUrl) ?>" class="btn outline btn-small" style="margin:0 0 12px">← Volver al listado de pedidos</a>
                    <h2 style="margin:0">Gestionar pedido</h2>
                    <p style="margin:4px 0 0;color:var(--gray);font-size:14px">
                        Revisá el stock de cada artículo, ajustá cantidades, marcá agotados, confirmá el pedido o descargá el PDF para el cliente.
                    </p>
                <?php else: ?>
                    <h2>Gestión de pedidos mayoristas</h2>
                    <p style="margin:0;color:var(--gray);font-size:14px">
                        Entrá a <b>Gestionar</b> para revisar el stock, confirmar pedidos, registrar pagos o descargar el PDF para el cliente.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($viewOrderId < 1): ?>
        <!-- Filtros rápidos por estado -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">
            <a href="admin.php?tab=pedidos"
               class="btn outline btn-small <?= $statusFilter === '' ? 'active' : '' ?>"
               style="<?= $statusFilter === '' ? 'background:var(--ink);color:#fff;border-color:var(--ink)' : '' ?>">
                Todos (<?= count($allAdminOrders) ?>)
            </a>
            <a href="admin.php?tab=pedidos&estado=pending_review"
               class="btn outline btn-small"
               style="<?= $statusFilter === 'pending_review' ? 'background:var(--ink);color:#fff;border-color:var(--ink)' : '' ?>">
                ⏳ Pendientes de confirmación (<?= $orders_pendientes ?>)
            </a>
            <a href="admin.php?tab=pedidos&estado=confirmed"
               class="btn outline btn-small"
               style="<?= $statusFilter === 'confirmed' ? 'background:var(--ink);color:#fff;border-color:var(--ink)' : '' ?>">
                🟢 Confirmados / Esperando pago (<?= $orders_confirmados ?>)
            </a>
            <a href="admin.php?tab=pedidos&estado=payment_uploaded"
               class="btn outline btn-small"
               style="<?= $statusFilter === 'payment_uploaded' ? 'background:var(--ink);color:#fff;border-color:var(--ink)' : '' ?>">
                🟣 Comprobante recibido (<?= $orders_pagados ?>)
            </a>
            <a href="admin.php?tab=pedidos&estado=completed"
               class="btn outline btn-small"
               style="<?= $statusFilter === 'completed' ? 'background:var(--ink);color:#fff;border-color:var(--ink)' : '' ?>">
                ✓ Completados
            </a>
        </div>
        <?php endif; ?>

        <?php if ($viewOrderId > 0 && !$orderFound): ?>
            <div class="access-box">
                <p style="margin:0">No encontramos ese pedido. <a href="<?= e($backUrl) ?>"><b>Volver al listado</b></a>.</p>
            </div>
        <?php elseif (empty($ordersList)): ?>
            <div class="access-box">
                <p style="margin:0">No hay pedidos que coincidan con este filtro.</p>
            </div>
        <?php else: ?>

            <?php if ($viewOrderId < 1): ?><div class="orders-list"><?php endif; ?>

            <?php foreach ($ordersList as $ord):
                $ordId = (int)$ord['id'];
                $ordStatus = $ord['status'];
                $hasReceipt = !empty($ord['receipt_path']);
                $clientWa = preg_replace('/\D+/', '', (string)$ord['user_phone']);
                $clientWaUrl = $clientWa !== '' ? ('https://wa.me/' . $clientWa . '?text=' . rawurlencode('Hola ' . ($ord['user_name'] ?: 'cliente') . '! Nos comunicamos de Serinsumos por tu pedido ' . $ord['order_number'] . '.')) : '';
                $cardUrl = 'admin.php?tab=pedidos&ver=' . $ordId . ($statusFilter !== '' ? '&estado=' . urlencode($statusFilter) : '');
                if ($viewOrderId > 0 && $ordId !== $viewOrderId) { continue; }
            ?>

            <?php if ($viewOrderId < 1): ?>
                <!-- ── Fila compacta del pedido ───────────────────────────────── -->
                <div class="order-row st-<?= e($ordStatus) ?>">
                    <div class="or-main">
                        <div class="or-top">
                            <span class="or-num"><?= e($ord['order_number']) ?></span>
                            <?php if ($ordStatus === 'pending_review'): ?>
                                <span class="badge badge-yellow">⏳ Pendiente de revisión</span>
                            <?php elseif ($ordStatus === 'confirmed'): ?>
                                <span class="badge badge-green">🟢 Confirmado · esperando pago</span>
                            <?php elseif ($ordStatus === 'payment_uploaded'): ?>
                                <span class="badge badge-blue">🟣 Comprobante recibido · verificar pago</span>
                            <?php elseif ($ordStatus === 'completed'): ?>
                                <span class="badge badge-green">✓ Completado y entregado</span>
                            <?php else: ?>
                                <span class="badge badge-pink">✕ Cancelado</span>
                            <?php endif; ?>
                        </div>
                        <div class="or-sub">
                            <span>🏢 <b><?= e($ord['user_business'] ?: $ord['user_name']) ?></b></span>
                            <span>·</span>
                            <span><?= e($ord['user_name']) ?></span>
                            <span>·</span>
                            <span><?= e(date('d/m/Y H:i', strtotime($ord['created_at']))) ?></span>
                            <?php if ((int)($ord['out_of_stock_items'] ?? 0) > 0): ?>
                                <span class="badge badge-pink"><?= (int)$ord['out_of_stock_items'] ?> sin stock</span>
                            <?php endif; ?>
                            <?php if (!empty($ord['receipt_path'])): ?>
                                <span class="badge badge-blue">📎 Comprobante</span>
                            <?php elseif (!empty($ord['customer_notes'])): ?>
                                <span class="badge badge-gray">📝 Nota del cliente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="or-right">
                        <div class="or-total">
                            <?= e($ord['currency_symbol'] ?: '$') ?><?= number_format((float)$ord['total_amount'], 2, ',', '.') ?>
                            <small><?= (int)$ord['total_items'] ?> artículo<?= (int)$ord['total_items'] === 1 ? '' : 's' ?></small>
                        </div>
                        <a class="btn yellow btn-small" href="<?= e($cardUrl) ?>" style="margin:0">Gestionar →</a>
                    </div>
                </div>
            <?php else: ?>
                <?php $ordItems = get_order_items($ordId); ?>
                <div class="order-card status-<?= e($ordStatus) ?>" id="order-<?= $ordId ?>" style="margin-bottom:32px">

                    <!-- Cabecera del pedido -->
                    <div class="order-header-row">
                        <div>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                                <h3 style="margin:0;font-size:20px;font-family:Rubik,sans-serif">
                                    Pedido <?= e($ord['order_number']) ?>
                                </h3>
                                <?php if ($ordStatus === 'pending_review'): ?>
                                    <span class="badge badge-yellow" style="font-size:12px">⏳ Pendiente de revisión</span>
                                <?php elseif ($ordStatus === 'confirmed'): ?>
                                    <span class="badge badge-green" style="font-size:12px">🟢 Confirmado · Esperando pago</span>
                                <?php elseif ($ordStatus === 'payment_uploaded'): ?>
                                    <span class="badge badge-blue" style="font-size:12px">🟣 Comprobante recibido · Verificar pago</span>
                                <?php elseif ($ordStatus === 'completed'): ?>
                                    <span class="badge badge-green" style="font-size:12px">✓ Completado y entregado</span>
                                <?php elseif ($ordStatus === 'cancelled'): ?>
                                    <span class="badge badge-pink" style="font-size:12px">✕ Cancelado</span>
                                <?php endif; ?>
                            </div>

                            <p style="margin:6px 0 0;font-size:14px;color:var(--ink)">
                                <b><?= e($ord['user_business'] ?: $ord['user_name']) ?></b>
                                · Contacto: <?= e($ord['user_name']) ?>
                                · <?= e($ord['user_email']) ?>
                                <?php if (!empty($ord['user_phone'])): ?>
                                    · Tel: <b><?= e($ord['user_phone']) ?></b>
                                <?php endif; ?>
                            </p>
                            <small style="color:var(--gray)">Recibido el <?= e(date('d/m/Y H:i', strtotime($ord['created_at']))) ?></small>
                        </div>

                        <div style="text-align:right">
                            <div style="font-size:22px;font-weight:700;color:var(--ink)">
                                $<?= number_format((float)$ord['total_amount'], 2, ',', '.') ?>
                            </div>
                            <span style="font-size:12px;color:var(--gray)"><?= count($ordItems) ?> productos en total</span>

                            <?php if ($clientWaUrl !== ''): ?>
                                <div style="margin-top:8px">
                                    <a class="btn outline btn-small" href="<?= e($clientWaUrl) ?>" target="_blank" rel="noopener" style="color:#0d7a4a;border-color:#0d7a4a;margin:0">
                                        💬 WhatsApp cliente
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($ord['customer_notes'])): ?>
                        <div style="background:#fffdec;border-left:3px solid var(--yellow);padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:18px">
                            <b>Nota enviada por el cliente:</b> <?= nl2br(e($ord['customer_notes'])) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Lista interactiva de productos para revisión de stock -->
                    <h4 style="margin:16px 0 8px;font-size:15px">Artículos del pedido (Revisión de stock):</h4>
                    <div style="overflow-x:auto">
                        <table class="order-items-table" style="background:#fff">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>SKU</th>
                                    <th style="text-align:center">Pedidas</th>
                                    <th style="width:230px">Confirmadas / Estado</th>
                                    <th>Nota de stock</th>
                                    <th style="text-align:right">Precio</th>
                                    <th style="text-align:right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($ordItems as $it):
                                $itId = (int)$it['id'];
                                $isOutOfStock = in_array($it['item_status'], ['out_of_stock', 'removed'], true);
                                $unitP = $it['unit_price'] !== null ? (float)$it['unit_price'] : 0.0;
                                $qtyAppr = (int)$it['quantity_approved'];
                                $sub = $isOutOfStock ? 0.0 : ($unitP * $qtyAppr);
                            ?>
                                <tr class="<?= $isOutOfStock ? 'item-removed-row' : '' ?>">
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <?php if (!empty($it['image_url'])): ?>
                                                <img src="<?= e($it['image_url']) ?>" alt="" style="width:38px;height:38px;object-fit:cover;border-radius:4px">
                                            <?php endif; ?>
                                            <div>
                                                <span class="item-title" style="font-weight:600"><?= e($it['title']) ?></span>
                                                <?php if (!empty($it['variant_title']) && $it['variant_title'] !== 'Estándar'): ?>
                                                    <div style="font-size:11px;color:var(--gray)"><?= e($it['variant_title']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td style="font-family:monospace;font-size:12px;color:var(--gray)">
                                        <?= e($it['sku'] ?: '—') ?>
                                    </td>

                                    <td style="text-align:center;font-weight:600">
                                        <?= (int)$it['quantity_requested'] ?>
                                    </td>

                                    <!-- Formulario inline para ajustar ítem -->
                                    <td colspan="2" style="padding:4px 8px">
                                        <form method="post" style="display:flex;gap:6px;align-items:center;margin:0;flex-wrap:wrap">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="review_order_item">
                                            <input type="hidden" name="_tab" value="pedidos">
                                            <input type="hidden" name="_order_id" value="<?= $ordId ?>">
                                            <input type="hidden" name="order_id" value="<?= $ordId ?>">
                                            <input type="hidden" name="item_id" value="<?= $itId ?>">

                                            <input name="quantity_approved" type="number" min="0" max="500"
                                                   value="<?= $qtyAppr ?>"
                                                   style="width:55px;padding:4px;text-align:center;font-size:13px"
                                                   title="Cantidad en stock confirmada">

                                            <select name="item_status" style="padding:4px;font-size:12px;width:125px">
                                                <option value="approved"     <?= $it['item_status'] === 'approved' ? 'selected' : '' ?>>✓ Disponible</option>
                                                <option value="out_of_stock" <?= $it['item_status'] === 'out_of_stock' ? 'selected' : '' ?>>❌ Sin stock</option>
                                                <option value="removed"      <?= $it['item_status'] === 'removed' ? 'selected' : '' ?>>🗑️ Retirado</option>
                                            </select>

                                            <input name="admin_item_note" type="text"
                                                   value="<?= e($it['admin_item_note']) ?>"
                                                   placeholder="Motivo (opcional)"
                                                   style="padding:4px 8px;font-size:12px;width:130px">

                                            <button class="btn outline btn-small" type="submit" style="padding:4px 8px;margin:0;font-size:12px" title="Guardar ajuste de este ítem">
                                                Guardar
                                            </button>
                                        </form>
                                    </td>

                                    <td style="text-align:right;white-space:nowrap;font-size:13px">
                                        $<?= number_format($unitP, 2, ',', '.') ?>
                                    </td>

                                    <td style="text-align:right;white-space:nowrap;font-weight:600;font-size:14px">
                                        <?php if ($isOutOfStock): ?>
                                            <span style="color:var(--pink)">$0,00</span>
                                        <?php else: ?>
                                            $<?= number_format($sub, 2, ',', '.') ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Panel de acciones de confirmación y comprobantes del pedido -->
                    <div style="background:#f8f9fb;border-radius:8px;padding:18px 20px;margin-top:16px">

                        <?php if ($ordStatus === 'pending_review'): ?>
                            <!-- Formulario para confirmar pedido -->
                            <form method="post" style="max-width:100%;margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="confirm_order">
                                <input type="hidden" name="_tab" value="pedidos">
                                <input type="hidden" name="_order_id" value="<?= $ordId ?>">
                                <input type="hidden" name="order_id" value="<?= $ordId ?>">

                                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px">
                                    <div>
                                        <b style="font-size:15px">Confirmar pedido para pago</b>
                                        <div style="font-size:13px;color:var(--gray)">
                                            Al confirmar, el cliente verá en su cuenta el total actualizado ($<?= number_format((float)$ord['total_amount'], 2, ',', '.') ?>), qué artículos se sacaron si alguno quedó sin stock, el Alias bancario y el botón para subir el comprobante.
                                        </div>
                                    </div>
                                    <button class="btn yellow" type="submit" style="white-space:nowrap;padding:12px 24px">
                                        ✅ Confirmar pedido y habilitar pago
                                    </button>
                                </div>

                                <label style="margin:0;font-size:13px">
                                    Mensaje o aclaración para el cliente (opcional):
                                    <textarea name="admin_notes" placeholder="Ej: Stock verificado en depósito. Se retiraron X artículos sin stock." style="height:60px"><?= e($ord['admin_notes'] ?? '') ?></textarea>
                                </label>
                            </form>

                        <?php elseif ($ordStatus === 'confirmed'): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                                <div>
                                    <b style="color:#0d7a4a">✓ Pedido confirmado para transferencia</b>
                                    <div style="font-size:13px;color:var(--gray);margin-top:2px">
                                        El cliente tiene disponible en su cuenta el total de <b>$<?= number_format((float)$ord['total_amount'], 2, ',', '.') ?></b>, los datos para transferir y el formulario de comprobante.
                                    </div>
                                </div>

                                <div style="display:flex;gap:8px">
                                    <form method="post" onsubmit="return confirm('¿Seguro que querés cancelar este pedido?')" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="cancel_order">
                                        <input type="hidden" name="_tab" value="pedidos">
                                        <input type="hidden" name="_order_id" value="<?= $ordId ?>">
                                        <input type="hidden" name="order_id" value="<?= $ordId ?>">
                                        <button class="btn outline btn-small" type="submit" style="color:var(--pink);border-color:var(--pink)">
                                            Cancelar pedido
                                        </button>
                                    </form>
                                </div>
                            </div>

                        <?php elseif ($ordStatus === 'payment_uploaded'): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
                                <div>
                                    <b style="color:var(--blue);font-size:15px">📄 Comprobante de pago recibido</b>
                                    <div style="font-size:13px;color:var(--gray);margin-top:4px">
                                        Subido el <?= e(date('d/m/Y H:i', strtotime($ord['receipt_uploaded_at'] ?? $ord['updated_at']))) ?>.
                                        Revisá la acreditación bancaria antes de entregar los insumos.
                                    </div>
                                </div>

                                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                    <a class="btn outline" href="<?= e(site_url('ver-comprobante.php?order_id=' . $ordId)) ?>" target="_blank" rel="noopener">
                                        Ver comprobante adjuntado ↗
                                    </a>

                                    <form method="post" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="complete_order">
                                        <input type="hidden" name="_tab" value="pedidos">
                                        <input type="hidden" name="_order_id" value="<?= $ordId ?>">
                                        <input type="hidden" name="order_id" value="<?= $ordId ?>">
                                        <button class="btn yellow" type="submit">
                                            ✓ Pago verificado y pedido completado
                                        </button>
                                    </form>
                                </div>
                            </div>

                        <?php elseif ($ordStatus === 'completed'): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                                <span style="color:#0d7a4a;font-weight:600">✓ Pedido completado y entregado</span>
                                <?php if ($hasReceipt): ?>
                                    <a class="btn outline btn-small" href="<?= e(site_url('ver-comprobante.php?order_id=' . $ordId)) ?>" target="_blank" rel="noopener">
                                        Ver comprobante ↗
                                    </a>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($ordStatus === 'cancelled'): ?>
                            <span style="color:var(--pink);font-weight:600">✕ Pedido cancelado</span>
                        <?php endif; ?>

                    </div>

                    <!-- Documento PDF del pedido -->
                    <?php
                    $pdfDefault = match ($ordStatus) {
                        'confirmed', 'payment_uploaded' => 'pendiente',
                        'completed'                     => 'pagado',
                        default                         => 'presupuesto',
                    };
                    ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;border-top:1px dashed var(--line);margin-top:18px;padding-top:16px">
                        <div style="flex:1 1 270px;min-width:230px">
                            <b style="font-size:14px">📄 Documento del pedido (PDF)</b>
                            <div style="font-size:12.5px;color:var(--gray)">
                                Elegí el estado del documento y generá un resumen del pedido para enviarle al cliente.
                            </div>
                        </div>
                        <form method="get" action="pedido-pdf.php" style="display:flex;gap:8px;align-items:center;margin:0;flex-wrap:wrap;max-width:none">
                            <input type="hidden" name="id" value="<?= $ordId ?>">
                            <select name="estado" aria-label="Estado del documento" style="width:auto;min-width:200px;margin:0;flex:1 1 200px">
                                <option value="presupuesto" <?= $pdfDefault === 'presupuesto' ? 'selected' : '' ?>>📝 Presupuesto</option>
                                <option value="pendiente" <?= $pdfDefault === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente de pago</option>
                                <option value="pagado" <?= $pdfDefault === 'pagado' ? 'selected' : '' ?>>✅ Pagado</option>
                            </select>
                            <button class="btn yellow btn-small" type="submit" name="dl" value="0" formtarget="_blank" style="margin:0">Ver PDF ↗</button>
                            <button class="btn outline btn-small" type="submit" name="dl" value="1" style="margin:0">⬇ Descargar</button>
                        </form>
                    </div>

                </div>
            <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($viewOrderId < 1): ?></div><?php endif; ?>

        <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════
         PESTAÑA: PRODUCTOS
    ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'productos'): ?>

        <?php
        $total_prods      = count($adminProducts);
        $visibles_prods   = count(array_filter($adminProducts, fn($p) => !empty($p['_visible']) && empty($p['_deleted'])));
        $ocultos_prods    = count(array_filter($adminProducts, fn($p) => empty($p['_visible']) && empty($p['_deleted'])));
        $eliminados_prods = count(array_filter($adminProducts, fn($p) => !empty($p['_deleted'])));
        $locales_prods    = count(array_filter($adminProducts, fn($p) => ($p['_source'] ?? '') === 'local'));
        $hostinger_prods  = count(array_filter($adminProducts, fn($p) => ($p['_source'] ?? '') === 'api'));

        $dbCats = get_categories(false);
        $existingCategories = array_unique(array_filter(array_merge(
            array_column($dbCats, 'name'),
            array_map(fn($p) => (string)($p['_category'] ?? ''), $adminProducts)
        )));
        sort($existingCategories);
        ?>

        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
            <div>
                <h2>Gestión de productos</h2>
                <p style="margin:0;color:var(--gray);font-size:14px">
                    Administrá productos locales propios y sincronizados de Hostinger.
                    Los precios solo son visibles para clientes mayoristas verificados.
                </p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <form method="post" style="display:inline;margin:0" onsubmit="return confirm('¿Sincronizar el catálogo desde los archivos CSV (12 categorías y 154 productos)?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="act" value="sync_csv_catalog">
                    <input type="hidden" name="_tab" value="productos">
                    <button class="btn outline" type="submit" style="white-space:nowrap" title="Recarga o actualiza las categorías y productos desde los CSV">
                        🔄 Sincronizar catálogo CSV
                    </button>
                </form>
                <button class="btn yellow" type="button" onclick="toggleNewProdPanel()" style="white-space:nowrap">
                    + Agregar nuevo producto
                </button>
            </div>
        </div>

        <?php if ($adminCatalogError): ?>
            <div class="app-flash error" role="alert" style="margin-bottom:20px">
                No se pudo conectar con la API de Hostinger. Podés gestionar tus productos locales y los productos con ajustes guardados.
            </div>
        <?php endif; ?>

        <!-- Datalist para autocompletar categorías -->
        <datalist id="category-options">
            <?php foreach ($existingCategories as $cat): ?>
                <option value="<?= e($cat) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <!-- ── Panel Agregar Nuevo Producto (oculto por defecto) ────────── -->
        <div id="panel-nuevo-producto" style="display:none;background:#f8f9fb;border:2px solid var(--blue);border-radius:10px;padding:24px;margin-bottom:28px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h3 style="margin:0;font-size:18px">➕ Crear nuevo producto local</h3>
                <button class="btn outline btn-small" type="button" onclick="toggleNewProdPanel()" style="margin:0">Cerrar ✕</button>
            </div>
            <p style="font-size:13px;color:var(--gray);margin-top:0;margin-bottom:20px">
                Este producto se guardará directamente en tu base de datos y se mostrará en el catálogo a tus clientes.
            </p>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="act"  value="create_local_product">
                <input type="hidden" name="_tab" value="productos">

                <div class="prod-visibility-row" style="margin-bottom:18px">
                    <label>
                        <input type="checkbox" name="visible" value="1" checked>
                        Visible de inmediato en la tienda
                    </label>
                    <span style="font-size:12px;color:var(--gray)">Podés desmarcarlo si aún no querés que aparezca publicado.</span>
                </div>

                <div class="prod-edit-grid">
                    <label>Nombre del producto *
                        <input name="title" type="text" required placeholder="Ej: Vinilo Textil Termotransferible Premium">
                    </label>

                    <label>Categoría
                        <input name="category" type="text" list="category-options" placeholder="Ej: Vinilos, Tintas, Sublimables...">
                    </label>

                    <label>Código / SKU
                        <input name="sku" type="text" placeholder="Ej: VIN-TEX-01">
                    </label>

                    <label>Precio mayorista en ARS
                        <input name="price" type="number" min="0" step="0.01" placeholder="Ej: 14500.00 (dejar vacío si es a consultar)">
                    </label>

                    <label>Precio a mostrar (alternativo)
                        <input name="price_display" type="text" placeholder="Ej: A consultar, o Desde $1.500">
                    </label>

                    <label>Unidad de venta
                        <select name="unit_of_sale">
                            <option value="unidad" selected>Unidad</option>
                            <option value="pack">Pack / Bulto</option>
                            <option value="metro">Metro</option>
                            <option value="rollo">Rollo</option>
                            <option value="litro">Litro</option>
                            <option value="caja">Caja</option>
                        </select>
                    </label>

                    <label>Cantidad por pack / bulto (opcional)
                        <input name="pack_quantity" type="number" min="1" placeholder="Ej: 10, 50, 100">
                    </label>

                    <label>Símbolo de moneda
                        <input name="currency_symbol" type="text" maxlength="8" value="$">
                    </label>

                    <label>Subir imagen desde tu equipo
                        <input name="image_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                        <small style="color:var(--gray);font-size:11px">Formatos permitidos: JPG, PNG o WebP (máx. 10 MB)</small>
                    </label>

                    <label>O ingresar enlace URL de imagen
                        <input name="image_url" type="url" placeholder="https://ejemplo.com/foto.jpg">
                    </label>

                    <label>Orden en el catálogo
                        <input name="sort_order" type="number" value="0" placeholder="0">
                        <small style="color:var(--gray);font-size:11px">Menor número aparece primero.</small>
                    </label>

                    <div class="prod-edit-notes">
                        <label>Descripción detallada del producto
                            <textarea name="description" placeholder="Detalles técnicos, materiales, aplicaciones recomendadas..."></textarea>
                        </label>
                    </div>

                    <div class="prod-edit-notes">
                        <label>Nota interna (solo visible para administración)
                            <textarea name="notes" placeholder="Proveedor, costo de compra, ubicación en depósito..."></textarea>
                        </label>
                    </div>
                </div>

                <div class="prod-edit-actions" style="margin-top:20px">
                    <button class="btn yellow" type="submit">Guardar y publicar producto</button>
                    <button class="btn outline" type="button" onclick="toggleNewProdPanel()">Cancelar</button>
                </div>
            </form>
        </div>

        <style>
        /* ── Estilos de la tabla de productos ───────────────────────── */
        .prod-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .prod-table th { text-align: left; padding: 10px 10px; border-bottom: 2px solid var(--line); font-size: 11px; letter-spacing: .8px; color: var(--gray); font-weight: 600; }
        .prod-table td { padding: 12px 10px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .prod-table tr:last-child td { border-bottom: none; }
        .prod-table .prod-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; background: #f3f5f7; display: block; }
        .prod-table .prod-thumb-placeholder { width: 48px; height: 48px; background: #f3f5f7; border-radius: 4px; display: grid; place-items: center; color: var(--gray); font-size: 20px; }
        .prod-edit-form { display: none; }
        .prod-edit-form.open { display: block; }
        .prod-edit-panel { background: #f8f9fb; border: 1px solid var(--line); border-radius: 8px; padding: 20px; margin: 10px 0 16px; }
        .prod-edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
        .prod-edit-grid label { margin-bottom: 0; }
        .prod-edit-grid label input, .prod-edit-grid label select { width: 100%; }
        .prod-edit-notes { grid-column: 1 / -1; }
        .prod-edit-notes textarea { width: 100%; min-height: 70px; }
        .prod-edit-actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; align-items: center; }
        .prod-visibility-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; padding: 10px 14px; background: #fff; border: 1px solid var(--line); border-radius: 6px; }
        .prod-visibility-row label { flex-direction: row; align-items: center; gap: 8px; margin: 0; font-weight: 600; font-size: 13px; }
        .prod-api-price { font-size: 11px; color: var(--gray); margin-top: 3px; }

        /* Filtros rápidos */
        .prod-filter-bar { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 18px; }
        .prod-filter-pills { display: flex; gap: 6px; flex-wrap: wrap; }
        .prod-filter-pill { border: 1px solid var(--line); background: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; cursor: pointer; color: var(--gray); }
        .prod-filter-pill.active { background: var(--ink); color: #fff; border-color: var(--ink); font-weight: 600; }
        .prod-search-box { display: flex; align-items: center; gap: 8px; min-width: 0; flex: 1 1 250px; }
        .prod-search-box input { padding: 8px 12px; font-size: 13px; width: 100%; }
        @media(max-width:700px) { .prod-edit-grid { grid-template-columns: 1fr; } .prod-search-box { width: 100%; } }
        </style>

        <!-- ── Barra de búsqueda y filtros ──────────────────────────────── -->
        <div class="prod-filter-bar">
            <div class="prod-filter-pills" id="filter-pills">
                <button class="prod-filter-pill active" type="button" data-filter="all">
                    Todos (<?= $total_prods ?>)
                </button>
                <button class="prod-filter-pill" type="button" data-filter="visible">
                    Visibles (<?= $visibles_prods ?>)
                </button>
                <button class="prod-filter-pill" type="button" data-filter="hidden">
                    Ocultos (<?= $ocultos_prods ?>)
                </button>
                <?php if ($eliminados_prods > 0): ?>
                    <button class="prod-filter-pill" type="button" data-filter="deleted" style="color:var(--pink)">
                        Eliminados (<?= $eliminados_prods ?>)
                    </button>
                <?php endif; ?>
                <button class="prod-filter-pill" type="button" data-filter="local">
                    🏠 Locales (<?= $locales_prods ?>)
                </button>
                <button class="prod-filter-pill" type="button" data-filter="api">
                    🌐 Hostinger (<?= $hostinger_prods ?>)
                </button>
            </div>

            <div class="prod-search-box">
                <input type="search" id="admin-prod-search" placeholder="🔍 Filtrar productos por nombre o categoría..." oninput="filterAdminProducts()">
            </div>
        </div>

        <?php if (!$adminProducts): ?>
            <p>No hay productos registrados en el sistema.</p>
        <?php else: ?>

        <div style="overflow-x:auto">
        <table class="prod-table" id="admin-products-table">
            <thead>
                <tr>
                    <th style="width:52px"></th>
                    <th>Producto y Origen</th>
                    <th>Precio</th>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($adminProducts as $p):
                $isLocal  = ($p['_source'] ?? '') === 'local';
                $isDeleted = !empty($p['_deleted']);
                $isVisible = !empty($p['_visible']) && !$isDeleted;
                $pHandle  = e($p['handle'] ?? $p['id'] ?? '');
                $pTitle   = $p['_display_title'] !== '' ? $p['_display_title'] : (string)($p['title'] ?? '—');
                $ovr      = $p['_override'];
                $rowId    = 'prod-' . preg_replace('/[^a-z0-9]/i', '-', (string)($p['handle'] ?? $p['id'] ?? uniqid()));
                $localRow = $p['_local_row'] ?? [];
            ?>
                <tr id="row-<?= $rowId ?>"
                    class="admin-prod-row"
                    data-title="<?= strtolower(e($pTitle)) ?>"
                    data-category="<?= strtolower(e($p['_category'])) ?>"
                    data-status="<?= $isDeleted ? 'deleted' : ($isVisible ? 'visible' : 'hidden') ?>"
                    data-source="<?= $isLocal ? 'local' : 'api' ?>">
                    <td>
                        <?php if (!empty($p['image'])): ?>
                            <img class="prod-thumb" src="<?= e($p['image']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <div class="prod-thumb-placeholder">▧</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <b style="font-size:13px"><?= e($pTitle) ?></b>
                        <?php if (!empty($p['_sku'])): ?>
                            <span style="font-size:11px;color:var(--gray);font-family:monospace;margin-left:4px">[<?= e($p['_sku']) ?>]</span>
                        <?php endif; ?>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:3px;flex-wrap:wrap">
                            <?php if ($isLocal): ?>
                                <span class="badge badge-blue">🏠 Local</span>
                                <?php if (!empty($p['_unit_of_sale'])): ?>
                                    <span style="font-size:11px;color:var(--gray)"><?= e($p['_unit_of_sale']) ?><?= !empty($p['_pack_quantity']) ? ' (x' . (int)$p['_pack_quantity'] . ')' : '' ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-gray">🌐 Hostinger</span>
                                <?php if ($ovr && trim((string)$ovr['label']) !== ''): ?>
                                    <span class="prod-api-price">Orig: <?= e($p['title']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="white-space:nowrap">
                        <?php if ($isLocal): ?>
                            <?php if ($p['_local_price'] !== ''): ?>
                                <b style="color:var(--ink)"><?= e($p['_local_price']) ?></b>
                            <?php else: ?>
                                <span style="color:var(--gray);font-size:12px">A consultar</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($p['_local_price'] !== ''): ?>
                                <b style="color:var(--blue)"><?= e($p['_local_price']) ?></b>
                                <div class="prod-api-price">API: <?= e($p['price_formatted'] ?? '—') ?></div>
                            <?php else: ?>
                                <span><?= e($p['price_formatted'] ?? '—') ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['_category'] !== ''): ?>
                            <span class="badge badge-gray"><?= e($p['_category']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--gray);font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isDeleted): ?>
                            <span class="badge badge-pink">Eliminado</span>
                        <?php elseif ($isVisible): ?>
                            <span class="badge badge-green">Visible</span>
                        <?php else: ?>
                            <span class="badge badge-yellow">Oculto</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <div style="display:inline-flex;gap:6px;align-items:center;justify-content:flex-end">
                            <button class="btn outline btn-small" type="button"
                                    onclick="toggleProdEdit('<?= $rowId ?>')" style="margin:0">
                                Editar
                            </button>

                            <?php if ($isLocal): ?>
                                <!-- Eliminar producto local definitivamente -->
                                <form method="post"
                                      onsubmit="return confirm('¿Seguro que querés eliminar definitivamente <?= addslashes($pTitle) ?>? Esta acción no se puede deshacer.')"
                                      style="display:inline;margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act"      value="delete_local_product">
                                    <input type="hidden" name="_tab"     value="productos">
                                    <input type="hidden" name="local_id" value="<?= (int)$p['_local_id'] ?>">
                                    <button class="btn outline btn-small" type="submit"
                                            style="margin:0;color:var(--pink);border-color:var(--pink)">
                                        Eliminar
                                    </button>
                                </form>
                            <?php else: ?>
                                <?php if (!$isDeleted): ?>
                                    <!-- Eliminar de la tienda el producto de Hostinger -->
                                    <form method="post"
                                          onsubmit="return confirm('¿Quitar <?= addslashes($pTitle) ?> del catálogo? Podrás restaurarlo en cualquier momento.')"
                                          style="display:inline;margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act"        value="delete_api_product">
                                        <input type="hidden" name="_tab"       value="productos">
                                        <input type="hidden" name="api_handle" value="<?= $pHandle ?>">
                                        <button class="btn outline btn-small" type="submit"
                                                style="margin:0;color:var(--pink);border-color:var(--pink)"
                                                title="Quitar del catálogo">
                                            Eliminar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Restaurar producto de Hostinger eliminado -->
                                    <form method="post" style="display:inline;margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act"        value="restore_api_product">
                                        <input type="hidden" name="_tab"       value="productos">
                                        <input type="hidden" name="api_handle" value="<?= $pHandle ?>">
                                        <button class="btn outline btn-small" type="submit"
                                                style="margin:0;color:#0d7a4a;border-color:#0d7a4a"
                                                title="Volver a mostrar en el catálogo">
                                            Restaurar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>

                            <a class="btn outline btn-small"
                               href="<?= e(site_url('producto/' . rawurlencode((string)($p['handle'] ?? $p['id'] ?? '')))) ?>"
                               target="_blank" rel="noopener"
                               style="margin:0">
                                Ver ↗
                            </a>
                        </div>
                    </td>
                </tr>

                <!-- Fila de edición expandible -->
                <tr id="edit-row-<?= $rowId ?>">
                    <td colspan="6" style="padding:0;border-bottom:2px solid var(--line)">
                        <div class="prod-edit-form" id="edit-<?= $rowId ?>">
                            <div class="prod-edit-panel">

                            <?php if ($isLocal): ?>
                                <!-- ── Formulario edición PRODUCTO LOCAL ── -->
                                <form method="post" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act"      value="edit_local_product">
                                    <input type="hidden" name="_tab"     value="productos">
                                    <input type="hidden" name="local_id" value="<?= (int)$p['_local_id'] ?>">

                                    <div class="prod-visibility-row">
                                        <label>
                                            <input type="checkbox" name="visible" value="1" <?= $p['_visible'] ? 'checked' : '' ?>>
                                            Visible en el catálogo
                                        </label>
                                        <span style="font-size:12px;color:var(--gray)">Destildá para ocultarlo temporalmente de la tienda.</span>
                                    </div>

                                    <div class="prod-edit-grid">
                                        <label>Nombre del producto *
                                            <input name="title" type="text" required value="<?= e($localRow['title'] ?? $p['title']) ?>">
                                        </label>

                                        <label>Categoría
                                            <input name="category" type="text" list="category-options" value="<?= e($localRow['category'] ?? $p['_category']) ?>">
                                        </label>

                                        <label>Código / SKU
                                            <input name="sku" type="text" value="<?= e($localRow['sku'] ?? $p['_sku'] ?? '') ?>">
                                        </label>

                                        <label>Precio mayorista en ARS
                                            <input name="price" type="number" min="0" step="0.01" value="<?= e($localRow['price'] ?? '') ?>">
                                        </label>

                                        <label>Precio a mostrar (alternativo)
                                            <input name="price_display" type="text" value="<?= e($localRow['price_display'] ?? $p['_price_display'] ?? '') ?>" placeholder="Ej: A consultar, o Desde $1.500">
                                        </label>

                                        <label>Unidad de venta
                                            <select name="unit_of_sale">
                                                <?php $u = $localRow['unit_of_sale'] ?? $p['_unit_of_sale'] ?? 'unidad'; ?>
                                                <option value="unidad" <?= $u === 'unidad' ? 'selected' : '' ?>>Unidad</option>
                                                <option value="pack"   <?= $u === 'pack'   ? 'selected' : '' ?>>Pack / Bulto</option>
                                                <option value="metro"  <?= $u === 'metro'  ? 'selected' : '' ?>>Metro</option>
                                                <option value="rollo"  <?= $u === 'rollo'  ? 'selected' : '' ?>>Rollo</option>
                                                <option value="litro"  <?= $u === 'litro'  ? 'selected' : '' ?>>Litro</option>
                                                <option value="caja"   <?= $u === 'caja'   ? 'selected' : '' ?>>Caja</option>
                                            </select>
                                        </label>

                                        <label>Cantidad por pack / bulto (opcional)
                                            <input name="pack_quantity" type="number" min="1" value="<?= e($localRow['pack_quantity'] ?? $p['_pack_quantity'] ?? '') ?>">
                                        </label>

                                        <label>Símbolo de moneda
                                            <input name="currency_symbol" type="text" maxlength="8" value="<?= e($localRow['currency_symbol'] ?? '$') ?>">
                                        </label>

                                        <label>Actualizar imagen (subir archivo)
                                            <input name="image_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                                            <small style="color:var(--gray);font-size:11px">Dejar vacío si no querés cambiar la imagen actual.</small>
                                        </label>

                                        <label>O enlace URL de imagen
                                            <input name="image_url" type="url" value="<?= e($localRow['image_url'] ?? $p['image']) ?>">
                                        </label>

                                        <label>Orden en el catálogo
                                            <input name="sort_order" type="number" value="<?= e($localRow['sort_order'] ?? $p['_sort_order']) ?>">
                                        </label>

                                        <div class="prod-edit-notes">
                                            <label>Descripción del producto
                                                <textarea name="description"><?= e($localRow['description'] ?? $p['description']) ?></textarea>
                                            </label>
                                        </div>

                                        <div class="prod-edit-notes">
                                            <label>Nota interna (solo admin)
                                                <textarea name="notes"><?= e($localRow['notes'] ?? $p['_notes']) ?></textarea>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="prod-edit-actions">
                                        <button class="btn yellow btn-small" type="submit">Guardar cambios</button>
                                        <button class="btn outline btn-small" type="button" onclick="toggleProdEdit('<?= $rowId ?>')">Cancelar</button>
                                        <span style="font-size:12px;color:var(--gray)">
                                            Creado el <?= e(date('d/m/Y H:i', strtotime($p['created_at']))) ?>
                                        </span>
                                    </div>
                                </form>

                            <?php else: ?>
                                <!-- ── Formulario edición AJUSTE HOSTINGER ── -->
                                <form method="post" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act"        value="save_override">
                                    <input type="hidden" name="_tab"       value="productos">
                                    <input type="hidden" name="api_handle" value="<?= $pHandle ?>">

                                    <div class="prod-visibility-row">
                                        <label>
                                            <input type="checkbox" name="visible" value="1" <?= $p['_visible'] ? 'checked' : '' ?>>
                                            Visible en el catálogo
                                        </label>
                                        <span style="font-size:12px;color:var(--gray)">Destildá para ocultarlo de la tienda sin borrarlo de Hostinger.</span>
                                    </div>

                                    <div class="prod-edit-grid">
                                        <label>Nombre alternativo
                                            <input name="label" type="text" placeholder="Dejar vacío para usar el nombre de la API" value="<?= e($ovr['label'] ?? '') ?>">
                                        </label>

                                        <label>Categoría
                                            <input name="category" type="text" list="category-options" placeholder="Ej: Tintas, Maquinarias…" value="<?= e($ovr['category'] ?? '') ?>">
                                        </label>

                                        <label>Precio mayorista local
                                            <input name="price_override" type="number" min="0" step="0.01" placeholder="Dejar vacío para usar precio de la API" value="<?= e($ovr['price_override'] ?? '') ?>">
                                            <span class="prod-api-price">Precio API: <?= e($p['price_formatted'] ?? '—') ?></span>
                                        </label>

                                        <label>Símbolo de moneda
                                            <input name="currency_symbol" type="text" maxlength="8" value="<?= e($ovr['currency_symbol'] ?? '$') ?>">
                                        </label>

                                        <label>Orden en el catálogo
                                            <input name="sort_order" type="number" min="-999" max="9999" value="<?= e($ovr['sort_order'] ?? '0') ?>">
                                            <span class="prod-api-price">Menor número aparece primero.</span>
                                        </label>

                                        <div class="prod-edit-notes">
                                            <label>Imagen del producto
                                                <span style="display:flex;align-items:center;gap:12px;margin-top:6px">
                                                    <?php if (!empty($p['image'])): ?>
                                                        <img src="<?= e($p['image']) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid var(--line);flex-shrink:0">
                                                    <?php else: ?>
                                                        <span class="prod-thumb-placeholder">▧</span>
                                                    <?php endif; ?>
                                                    <small style="color:var(--gray);font-size:11px;line-height:1.5">
                                                        <?= !empty($p['_image_override'])
                                                            ? '🖼️ Imagen personalizada (ajuste local)'
                                                            : '🌐 Imagen original de Hostinger' ?>
                                                    </small>
                                                </span>
                                            </label>
                                        </div>

                                        <label>Subir nueva imagen desde tu equipo
                                            <input name="image_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                                            <small style="color:var(--gray);font-size:11px">JPG, PNG o WebP (máx. 10 MB). Reemplaza la imagen actual del producto.</small>
                                        </label>

                                        <label>O enlace URL de imagen
                                            <input name="image_url" type="url" value="<?= e($ovr['image_url'] ?? '') ?>" placeholder="Vacío = usar la imagen de Hostinger">
                                            <small style="color:var(--gray);font-size:11px">Dejalo vacío para volver a la imagen original de Hostinger.</small>
                                        </label>

                                        <div class="prod-edit-notes">
                                            <label>Nota interna (solo admin)
                                                <textarea name="notes" placeholder="Observaciones internas…"><?= e($ovr['notes'] ?? '') ?></textarea>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="prod-edit-actions">
                                        <button class="btn yellow btn-small" type="submit">Guardar cambios</button>
                                        <button class="btn outline btn-small" type="button" onclick="toggleProdEdit('<?= $rowId ?>')">Cancelar</button>
                                        <?php if ($ovr): ?>
                                            <span style="font-size:12px;color:var(--gray)">
                                                Último cambio: <?= e(date('d/m/Y H:i', strtotime($ovr['updated_at'] ?? $ovr['created_at'] ?? 'now'))) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            <?php endif; ?>

                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <script>
        function toggleNewProdPanel() {
            const p = document.getElementById('panel-nuevo-producto');
            if (p) {
                p.style.display = p.style.display === 'none' ? 'block' : 'none';
                if (p.style.display === 'block') p.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function toggleProdEdit(rowId) {
            const el = document.getElementById('edit-' + rowId);
            if (el) el.classList.toggle('open');
        }

        let currentProdFilter = 'all';

        document.querySelectorAll('#filter-pills .prod-filter-pill').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#filter-pills .prod-filter-pill').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentProdFilter = btn.dataset.filter;
                filterAdminProducts();
            });
        });

        function filterAdminProducts() {
            const query = (document.getElementById('admin-prod-search')?.value || '').trim().toLowerCase();
            const rows = document.querySelectorAll('.admin-prod-row');

            rows.forEach(row => {
                const title = row.dataset.title || '';
                const category = row.dataset.category || '';
                const status = row.dataset.status || '';
                const source = row.dataset.source || '';
                const editRow = document.getElementById('edit-' + row.id);

                // Comprobar filtro de categoría / estado
                let matchFilter = true;
                if (currentProdFilter === 'visible') matchFilter = status === 'visible';
                else if (currentProdFilter === 'hidden') matchFilter = status === 'hidden';
                else if (currentProdFilter === 'deleted') matchFilter = status === 'deleted';
                else if (currentProdFilter === 'local') matchFilter = source === 'local';
                else if (currentProdFilter === 'api') matchFilter = source === 'api';

                // Comprobar texto
                const matchQuery = query === '' || title.includes(query) || category.includes(query);

                if (matchFilter && matchQuery) {
                    row.style.display = '';
                    if (editRow) editRow.style.display = '';
                } else {
                    row.style.display = 'none';
                    if (editRow) editRow.style.display = 'none';
                }
            });
        }
        </script>

        <?php endif; // end $adminProducts ?>

    <!-- ══════════════════════════════════════════════════════════════════
         PESTAÑA: BANNERS
    ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'banners'): ?>

        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
            <div>
                <h2>Banners de la página principal</h2>
                <p style="margin:0;color:var(--gray);font-size:14px">
                    Modificá las imágenes, títulos, textos y botones del carrusel de inicio, o agregá nuevos banners promocionales.
                </p>
            </div>
            <button class="btn yellow" type="button" onclick="toggleNewBannerPanel()" style="white-space:nowrap">
                + Agregar nuevo banner
            </button>
        </div>

        <style>
        /* ── Estilos para banners admin ─────────────────────────────── */
        .banner-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .banner-card-top {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .banner-preview-img {
            width: 220px;
            height: 125px;
            object-fit: cover;
            border-radius: 8px;
            background: #f3f5f7;
            border: 1px solid var(--line);
            flex-shrink: 0;
        }
        .banner-card-body {
            flex: 1;
            min-width: 260px;
        }
        .banner-card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
        }
        .banner-edit-panel {
            display: none;
            background: #f8f9fb;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 20px;
            margin-top: 12px;
        }
        .banner-edit-panel.open { display: block; }
        .banner-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 20px;
        }
        .banner-form-grid label { margin-bottom: 0; }
        .banner-form-grid label input, .banner-form-grid label textarea { width: 100%; }
        .banner-form-full { grid-column: 1 / -1; }
        .banner-form-full textarea { width: 100%; min-height: 70px; }
        @media(max-width: 700px) {
            .banner-form-grid { grid-template-columns: 1fr; }
            .banner-preview-img { width: 100%; height: 160px; }
        }
        </style>

        <!-- ── Formulario de creación de banner (oculto por defecto) ───── -->
        <div id="panel-nuevo-banner" style="display:none;background:#f8f9fb;border:2px solid var(--blue);border-radius:10px;padding:24px;margin-bottom:28px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h3 style="margin:0;font-size:18px">➕ Crear nuevo banner para el carrusel</h3>
                <button class="btn outline btn-small" type="button" onclick="toggleNewBannerPanel()" style="margin:0">Cerrar ✕</button>
            </div>
            <p style="font-size:13px;color:var(--gray);margin-top:0;margin-bottom:20px">
                Podés subir una imagen o indicar una URL externa. Los textos y botones son opcionales y personalizables.
            </p>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="act"  value="create_banner">
                <input type="hidden" name="_tab" value="banners">

                <div class="prod-visibility-row" style="margin-bottom:18px">
                    <label>
                        <input type="checkbox" name="visible" value="1" checked>
                        Visible de inmediato en el carrusel
                    </label>
                    <span style="font-size:12px;color:var(--gray)">Destildá si deseás guardar el banner como borrador.</span>
                </div>

                <div class="banner-form-grid">
                    <label>Subir imagen desde tu equipo
                        <input name="image_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                        <small style="color:var(--gray);font-size:11px">Recomendado: 1920×700 o proporción horizontal similar (JPG, PNG, WebP)</small>
                    </label>

                    <label>O ingresar enlace URL de la imagen
                        <input name="image_url" type="url" placeholder="https://ejemplo.com/banner.webp">
                    </label>

                    <label>Epígrafe / Etiqueta superior
                        <input name="eyebrow" type="text" placeholder="Ej: EL COLOR ES SOLO EL COMIENZO">
                    </label>

                    <label>Orden en el carrusel
                        <input name="sort_order" type="number" value="<?= count($adminBanners) + 1 ?>" placeholder="1">
                        <small style="color:var(--gray);font-size:11px">Menor número aparece primero (1, 2, 3...)</small>
                    </label>

                    <div class="banner-form-full">
                        <label>Título principal (admite saltos de línea y resaltado &lt;span&gt;)
                            <input name="title" type="text" placeholder="Ej: Todo para crear.&lt;br&gt;Todo para &lt;span&gt;crecer.&lt;/span&gt;">
                        </label>
                    </div>

                    <div class="banner-form-full">
                        <label>Descripción / Texto explicativo
                            <textarea name="description" placeholder="Insumos y maquinarias para sublimación y estampado. Tu próximo gran proyecto empieza acá."></textarea>
                        </label>
                    </div>

                    <label>Botón principal: Texto
                        <input name="button_primary_text" type="text" value="Explorar catálogo" placeholder="Explorar catálogo">
                    </label>

                    <label>Botón principal: Enlace / URL
                        <input name="button_primary_link" type="text" value="catalogo.php" placeholder="catalogo.php">
                    </label>

                    <label>Botón secundario: Texto (opcional)
                        <input name="button_secondary_text" type="text" value="Quiero ser mayorista" placeholder="Quiero ser mayorista">
                    </label>

                    <label>Botón secundario: Enlace / URL
                        <input name="button_secondary_link" type="text" value="cuenta.php?registro=1" placeholder="cuenta.php?registro=1 o link de WhatsApp">
                    </label>
                </div>

                <div class="prod-edit-actions" style="margin-top:20px">
                    <button class="btn yellow" type="submit">Guardar y publicar banner</button>
                    <button class="btn outline" type="button" onclick="toggleNewBannerPanel()">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- ── Listado de banners existentes ───────────────────────────── -->
        <?php if (!$adminBanners): ?>
            <p>No hay banners configurados en el sistema.</p>
        <?php else: ?>

            <?php foreach ($adminBanners as $b):
                $bId      = (int)$b['id'];
                $bVisible = (int)$b['visible'] === 1;
            ?>
                <div class="banner-card" id="banner-card-<?= $bId ?>">
                    <div class="banner-card-top">
                        <img class="banner-preview-img" src="<?= e($b['image_url']) ?>" alt="Vista previa del banner" loading="lazy">

                        <div class="banner-card-body">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap">
                                <?php if ($bVisible): ?>
                                    <span class="badge badge-green">🟢 Visible</span>
                                <?php else: ?>
                                    <span class="badge badge-yellow">🟡 Oculto</span>
                                <?php endif; ?>
                                <span class="badge badge-gray">Orden #<?= (int)$b['sort_order'] ?></span>
                            </div>

                            <?php if (!empty($b['eyebrow'])): ?>
                                <p style="font-size:11px;font-weight:600;letter-spacing:1.5px;color:var(--blue);margin:0 0 4px">
                                    <?= e($b['eyebrow']) ?>
                                </p>
                            <?php endif; ?>

                            <h3 style="margin:0 0 8px;font-size:20px;line-height:1.25">
                                <?= ($b['title'] ?: '<i>(Sin título)</i>') ?>
                            </h3>

                            <?php if (!empty($b['description'])): ?>
                                <p style="font-size:13px;color:var(--gray);margin:0 0 10px;line-height:1.5">
                                    <?= e($b['description']) ?>
                                </p>
                            <?php endif; ?>

                            <div style="font-size:12px;color:var(--gray);display:flex;gap:14px;flex-wrap:wrap">
                                <?php if (!empty($b['button_primary_text'])): ?>
                                    <span><b>Botón 1:</b> <?= e($b['button_primary_text']) ?> (<code><?= e($b['button_primary_link']) ?></code>)</span>
                                <?php endif; ?>
                                <?php if (!empty($b['button_secondary_text'])): ?>
                                    <span><b>Botón 2:</b> <?= e($b['button_secondary_text']) ?> (<code><?= e($b['button_secondary_link']) ?></code>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="banner-card-actions">
                        <button class="btn outline btn-small" type="button" onclick="toggleBannerEdit('<?= $bId ?>')" style="margin:0">
                            Editar banner
                        </button>

                        <!-- Alternar visibilidad -->
                        <form method="post" style="display:inline;margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act"       value="toggle_banner">
                            <input type="hidden" name="_tab"      value="banners">
                            <input type="hidden" name="banner_id" value="<?= $bId ?>">
                            <button class="btn outline btn-small" type="submit" style="margin:0">
                                <?= $bVisible ? 'Ocultar' : 'Publicar' ?>
                            </button>
                        </form>

                        <!-- Eliminar banner -->
                        <form method="post" onsubmit="return confirm('¿Seguro que querés eliminar este banner?')" style="display:inline;margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act"       value="delete_banner">
                            <input type="hidden" name="_tab"      value="banners">
                            <input type="hidden" name="banner_id" value="<?= $bId ?>">
                            <button class="btn outline btn-small" type="submit" style="margin:0;color:var(--pink);border-color:var(--pink)">
                                Eliminar
                            </button>
                        </form>

                        <a class="btn outline btn-small" href="<?= e(site_url('index.php')) ?>" target="_blank" rel="noopener" style="margin:0;margin-left:auto">
                            Ver en la tienda ↗
                        </a>
                    </div>

                    <!-- Panel de edición desplegable para este banner -->
                    <div class="banner-edit-panel" id="edit-banner-<?= $bId ?>">
                        <form method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act"       value="edit_banner">
                            <input type="hidden" name="_tab"      value="banners">
                            <input type="hidden" name="banner_id" value="<?= $bId ?>">

                            <div class="prod-visibility-row" style="margin-bottom:14px">
                                <label>
                                    <input type="checkbox" name="visible" value="1" <?= $bVisible ? 'checked' : '' ?>>
                                    Visible en el carrusel
                                </label>
                            </div>

                            <div class="banner-form-grid">
                                <label>Cambiar imagen (subir archivo)
                                    <input name="image_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                                    <small style="color:var(--gray);font-size:11px">Dejar vacío si no querés cambiar la imagen actual.</small>
                                </label>

                                <label>O enlace URL de la imagen
                                    <input name="image_url" type="url" value="<?= e($b['image_url']) ?>" required>
                                </label>

                                <label>Epígrafe / Etiqueta superior
                                    <input name="eyebrow" type="text" value="<?= e($b['eyebrow']) ?>">
                                </label>

                                <label>Orden en el carrusel
                                    <input name="sort_order" type="number" value="<?= (int)$b['sort_order'] ?>">
                                </label>

                                <div class="banner-form-full">
                                    <label>Título principal (HTML permitido)
                                        <input name="title" type="text" value="<?= e($b['title']) ?>">
                                    </label>
                                </div>

                                <div class="banner-form-full">
                                    <label>Descripción / Texto explicativo
                                        <textarea name="description"><?= e($b['description']) ?></textarea>
                                    </label>
                                </div>

                                <label>Botón principal: Texto
                                    <input name="button_primary_text" type="text" value="<?= e($b['button_primary_text']) ?>">
                                </label>

                                <label>Botón principal: Enlace
                                    <input name="button_primary_link" type="text" value="<?= e($b['button_primary_link']) ?>">
                                </label>

                                <label>Botón secundario: Texto
                                    <input name="button_secondary_text" type="text" value="<?= e($b['button_secondary_text']) ?>">
                                </label>

                                <label>Botón secundario: Enlace
                                    <input name="button_secondary_link" type="text" value="<?= e($b['button_secondary_link']) ?>">
                                </label>
                            </div>

                            <div class="prod-edit-actions" style="margin-top:16px">
                                <button class="btn yellow btn-small" type="submit">Guardar cambios</button>
                                <button class="btn outline btn-small" type="button" onclick="toggleBannerEdit('<?= $bId ?>')">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <script>
        function toggleNewBannerPanel() {
            const p = document.getElementById('panel-nuevo-banner');
            if (p) {
                p.style.display = p.style.display === 'none' ? 'block' : 'none';
                if (p.style.display === 'block') p.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
        function toggleBannerEdit(id) {
            const el = document.getElementById('edit-banner-' + id);
            if (el) el.classList.toggle('open');
        }
        </script>

    <!-- ══════════════════════════════════════════════════════════════════
         PESTAÑA: COMPROBANTES
    ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'comprobantes'): ?>



        <h2>Habilitar comprobante de pago</h2>
        <p>Después de revisar el pedido, ingresá su referencia y las instrucciones con el importe final, el stock confirmado y los datos para transferir.</p>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="act"  value="create_document">
            <input type="hidden" name="_tab" value="comprobantes">

            <label>Cliente
                <select name="owner_id" required>
                    <option value="">Seleccionar cliente</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= e(($u['business'] ?: $u['name']) . ' · ' . $u['email']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Referencia del pedido
                <input name="order_reference" required placeholder="Ej: PED-2024-001">
            </label>

            <label>Stock confirmado, importe y datos de transferencia
                <textarea name="instructions" required placeholder="Detallá los artículos confirmados, el total a pagar y los datos bancarios para la transferencia."></textarea>
            </label>

            <button class="btn yellow" type="submit">Habilitar carga de comprobante</button>
        </form>

        <h2 style="margin-top:40px">Comprobantes solicitados</h2>

        <?php if (!$docs): ?>
            <p>No hay solicitudes de comprobantes.</p>
        <?php else: foreach ($docs as $d): ?>

            <?php
            $docEnabled      = (int)$d['enabled'] === 1;
            $hasReceipt      = !empty($d['receipt_path']);
            $docDisplayName  = e($d['business'] ?: $d['name']);
            $docId           = (int)$d['id'];
            ?>
            <div class="admin-row" style="flex-direction:column; align-items:stretch">

                <div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap">
                    <div class="admin-row-info" style="flex:1">
                        <b><?= e($d['order_reference']) ?></b>
                        <p><?= $docDisplayName ?> · <?= e($d['email']) ?></p>
                        <p style="white-space:pre-wrap; margin-top:6px"><?= nl2br(e($d['instructions'])) ?></p>

                        <div class="admin-row-meta" style="margin-top:8px; gap:8px">
                            <?php if ($hasReceipt): ?>
                                <span class="badge badge-green">✓ Comprobante recibido</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Sin comprobante</span>
                            <?php endif; ?>

                            <?php if ($docEnabled): ?>
                                <span class="badge badge-blue">Carga habilitada</span>
                            <?php else: ?>
                                <span class="badge badge-pink">Carga cerrada</span>
                            <?php endif; ?>

                            <small><?= e(date('d/m/Y', strtotime($d['created_at']))) ?></small>
                        </div>

                        <?php if ($hasReceipt): ?>
                            <p style="margin-top:8px">
                                <a href="<?= e(site_url('ver-comprobante.php?id=' . $docId)) ?>"
                                   target="_blank" rel="noopener"
                                   style="color:var(--blue); font-size:13px">
                                    Ver archivo ↗
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-row-actions" style="flex-direction:column; align-items:flex-end; gap:8px">
                        <!-- Toggle habilitado/cerrado -->
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act"         value="toggle_document">
                            <input type="hidden" name="_tab"        value="comprobantes">
                            <input type="hidden" name="document_id" value="<?= $docId ?>">
                            <button class="btn outline btn-small" type="submit">
                                <?= $docEnabled ? 'Cerrar carga' : 'Habilitar carga' ?>
                            </button>
                        </form>

                        <!-- Botón editar -->
                        <button class="btn outline btn-small"
                                onclick="document.getElementById('edit-<?= $docId ?>').classList.toggle('open')"
                                type="button">
                            Editar instrucciones
                        </button>
                    </div>
                </div>

                <!-- Formulario editar (inline, oculto por defecto) -->
                <div class="edit-doc-form" id="edit-<?= $docId ?>">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="act"         value="edit_document">
                        <input type="hidden" name="_tab"        value="comprobantes">
                        <input type="hidden" name="document_id" value="<?= $docId ?>">

                        <label>Referencia del pedido
                            <input name="order_reference" required value="<?= e($d['order_reference']) ?>">
                        </label>
                        <label>Instrucciones de pago
                            <textarea name="instructions" required><?= e($d['instructions']) ?></textarea>
                        </label>
                        <div class="btn-row">
                            <button class="btn yellow btn-small" type="submit">Guardar cambios</button>
                            <button class="btn outline btn-small" type="button"
                                    onclick="document.getElementById('edit-<?= $docId ?>').classList.remove('open')">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        <?php endforeach; endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════
         PESTAÑA: CONFIGURACIÓN
    ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'configuracion'): ?>

        <!-- ── MODO MANTENIMIENTO ────────────────────────────────────────── -->
        <div id="mantenimiento" style="background:<?= setting('maintenance_mode', '0') === '1' ? '#fff1f2' : '#f8fafc' ?>;border:2px solid <?= setting('maintenance_mode', '0') === '1' ? '#f43f5e' : 'var(--line)' ?>;border-radius:14px;padding:24px;margin-bottom:34px;max-width:760px;box-shadow:0 4px 14px rgba(0,0,0,0.03)">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:14px">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:26px">🛠️</span>
                    <div>
                        <h2 style="margin:0;font-size:19px;color:var(--ink)">Modo Mantenimiento</h2>
                        <span style="font-size:12px;color:var(--gray)">Control de acceso y visibilidad pública de la tienda</span>
                    </div>
                </div>
                <div>
                    <?php if (setting('maintenance_mode', '0') === '1'): ?>
                        <span style="background:#ef4444;color:#fff;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:0.5px;display:inline-block">● MANTENIMIENTO ACTIVO</span>
                    <?php else: ?>
                        <span style="background:#22c55e;color:#fff;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:0.5px;display:inline-block">● SITIO ONLINE Y PÚBLICO</span>
                    <?php endif; ?>
                </div>
            </div>

            <p style="font-size:13.5px;color:var(--ink);line-height:1.55;margin-bottom:18px">
                Al activar el modo mantenimiento, cualquier persona o cliente que visite la tienda verá exclusivamente una pantalla limpia con el <strong>logo del negocio</strong> y un <strong>botón que abre el modal con los datos de contacto de emergencia</strong> (WhatsApp directo, dirección física en San Vicente e Instagram).
                <br>
                <span style="color:#64748b;font-size:12.5px;display:inline-block;margin-top:4px">
                    🔒 <strong>Acceso restringido:</strong> Solo las cuentas con <strong>rol de administrador</strong> pueden ver y navegar el sitio completo mientras este modo esté activado.
                </span>
            </p>

            <form method="post" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="act"  value="toggle_maintenance">
                <input type="hidden" name="_tab" value="configuracion">

                <?php if (setting('maintenance_mode', '0') === '1'): ?>
                    <button class="btn btn-small" type="submit" style="background:#16a34a;color:#fff;border:none;font-weight:700;padding:12px 22px;border-radius:8px;cursor:pointer;font-size:14px;display:inline-flex;align-items:center;gap:8px">
                        ✓ Desactivar modo mantenimiento y abrir el sitio
                    </button>
                <?php else: ?>
                    <button class="btn btn-small" type="submit" onclick="return confirm('¿Confirmás activar el modo mantenimiento? Los clientes solo verán el logo y botón de emergencia hasta que lo desactives.')" style="background:#dc2626;color:#fff;border:none;font-weight:700;padding:12px 22px;border-radius:8px;cursor:pointer;font-size:14px;display:inline-flex;align-items:center;gap:8px">
                        ⚠️ Activar modo mantenimiento
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <hr style="border:none; border-top:1px solid var(--line); margin:36px 0 28px">

        <h2>Logo principal del negocio</h2>
        <p>Actualizá el logo de Serinsumos. Se aplicará automáticamente en la cabecera, menú móvil, pie de página y vistas públicas.</p>

        <?php $currentSiteLogo = site_logo(); ?>
        <div style="background:#f8fafc;border:1px solid var(--line);border-radius:12px;padding:22px;margin-bottom:32px;max-width:760px">
            <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;margin-bottom:20px">
                <div style="background:#fff;padding:12px 18px;border:1px solid var(--line);border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.04)">
                    <img src="<?= e($currentSiteLogo) ?>" alt="Logo actual" style="max-height:55px;max-width:220px;object-fit:contain;display:block">
                </div>
                <div>
                    <span style="font-size:12px;color:var(--gray);text-transform:uppercase;letter-spacing:1px;font-weight:700">Vista previa del logo activo</span>
                    <p style="margin:4px 0 0;font-size:13px;color:var(--ink)">
                        <?= setting('site_logo') ? '✓ Logo personalizado activo' : '★ Utilizando logo predeterminado de Serinsumos' ?>
                    </p>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="act"  value="save_site_logo">
                <input type="hidden" name="_tab" value="configuracion">

                <div class="prod-edit-grid">
                    <label>Subir archivo de logo desde tu equipo
                        <input name="logo_file" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                        <small style="color:var(--gray);font-size:11px">Formatos recomendados: PNG con fondo transparente, SVG o WebP (máx. 8 MB)</small>
                    </label>

                    <label>O ingresar enlace URL de imagen
                        <input name="logo_url" type="url" value="<?= e(setting('site_logo', '')) ?>" placeholder="https://ejemplo.com/logo.png">
                        <small style="color:var(--gray);font-size:11px">Si subís un archivo, este campo se completará automáticamente.</small>
                    </label>
                </div>

                <div style="display:flex;gap:12px;align-items:center;margin-top:16px;flex-wrap:wrap">
                    <button class="btn yellow" type="submit">Actualizar logo principal</button>
                    <?php if (setting('site_logo')): ?>
                        <label style="margin:0;display:flex;flex-direction:row;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="checkbox" name="reset_default_logo" value="1">
                            Restablecer al logo por defecto
                        </label>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <hr style="border:none; border-top:1px solid var(--line); margin:36px 0 28px">

        <h2>Favicon de la web (Ícono de pestaña del navegador)</h2>
        <p>Es el pequeño ícono que se muestra en la pestaña del navegador y marcadores de tus clientes.</p>

        <?php $currentSiteFavicon = site_favicon(); ?>
        <div style="background:#f8fafc;border:1px solid var(--line);border-radius:12px;padding:22px;margin-bottom:32px;max-width:760px">
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px">
                <div style="background:#fff;width:56px;height:56px;border:1px solid var(--line);border-radius:10px;display:grid;place-items:center;box-shadow:0 2px 8px rgba(0,0,0,0.04)">
                    <img src="<?= e($currentSiteFavicon) ?>" alt="Favicon actual" style="width:32px;height:32px;object-fit:contain;display:block">
                </div>
                <div>
                    <span style="font-size:12px;color:var(--gray);text-transform:uppercase;letter-spacing:1px;font-weight:700">Vista previa del favicon activo</span>
                    <p style="margin:4px 0 0;font-size:13px;color:var(--ink)">
                        <?= setting('site_favicon') ? '✓ Favicon personalizado activo' : '★ Utilizando favicon predeterminado (.ico)' ?>
                    </p>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="act"  value="save_site_favicon">
                <input type="hidden" name="_tab" value="configuracion">

                <div class="prod-edit-grid">
                    <label>Subir archivo de favicon
                        <input name="favicon_file" type="file" accept=".ico,image/x-icon,image/png,image/svg+xml,image/webp">
                        <small style="color:var(--gray);font-size:11px">Formatos admitidos: ICO, PNG (32x32 o 64x64 px), SVG o WebP (máx. 4 MB)</small>
                    </label>

                    <label>O ingresar enlace URL
                        <input name="favicon_url" type="url" value="<?= e(setting('site_favicon', '')) ?>" placeholder="https://ejemplo.com/favicon.png">
                    </label>
                </div>

                <div style="display:flex;gap:12px;align-items:center;margin-top:16px;flex-wrap:wrap">
                    <button class="btn yellow" type="submit">Actualizar favicon</button>
                    <?php if (setting('site_favicon')): ?>
                        <label style="margin:0;display:flex;flex-direction:row;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="checkbox" name="reset_default_favicon" value="1">
                            Restablecer al favicon original
                        </label>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <hr style="border:none; border-top:1px solid var(--line); margin:36px 0 28px">

        <h2>WhatsApp del negocio</h2>
        <p>Ingresá el número con el código de país (solo dígitos) para que los clientes puedan contactarte directamente.</p>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="act"  value="save_whatsapp">
            <input type="hidden" name="_tab" value="configuracion">

            <label>Número con código de país, solo dígitos
                <input name="whatsapp"
                       value="<?= e(setting('whatsapp', DEFAULT_WHATSAPP)) ?>"
                       inputmode="numeric"
                       pattern="[0-9]{8,15}"
                       placeholder="5493511234567">
            </label>
            <button class="btn outline" type="submit">Guardar número</button>
        </form>

        <hr style="border:none; border-top:1px solid var(--line); margin:36px 0 28px">

        <h2>Datos bancarios para transferencias (Alias y CBU)</h2>
        <p>Estos datos se muestran automáticamente a los clientes con pedidos <b>confirmados</b> para que realicen la transferencia y adjunten su comprobante.</p>

        <form method="post" style="max-width:760px">
            <?= csrf_field() ?>
            <input type="hidden" name="act"  value="save_bank_settings">
            <input type="hidden" name="_tab" value="configuracion">

            <div class="prod-edit-grid">
                <label>Alias para transferir *
                    <input name="bank_alias" required value="<?= e($bankSettings['alias']) ?>" placeholder="Ej: SERINSUMOS.CORDOBA">
                    <small style="color:var(--gray); font-size:11px">Es el dato principal que los clientes pueden copiar con 1 clic.</small>
                </label>

                <label>CBU o CVU (22 dígitos) *
                    <input name="bank_cbu" required value="<?= e($bankSettings['cbu']) ?>" placeholder="Ej: 0170099220000012345678">
                </label>

                <label>Titular de la cuenta
                    <input name="bank_holder" value="<?= e($bankSettings['holder']) ?>" placeholder="Ej: Serinsumos Mayorista / Razón Social">
                </label>

                <label>CUIT / CUIL
                    <input name="bank_cuit" value="<?= e($bankSettings['cuit']) ?>" placeholder="Ej: 30-71234567-9">
                </label>

                <label>Banco o Billetera Virtual
                    <input name="bank_name" value="<?= e($bankSettings['bank']) ?>" placeholder="Ej: Banco BBVA, Mercado Pago, etc.">
                </label>
            </div>

            <button class="btn yellow" type="submit" style="margin-top:16px">Guardar datos bancarios</button>
        </form>

        <div class="system-note" style="margin-top:40px; border-radius:8px">
            <b>Gestión de productos y stock</b>
            <p style="margin:8px 0 12px; color:var(--gray); font-size:14px">
                Los productos, el stock y los pedidos se administran en la tienda de Hostinger conectada al sitio.
            </p>
            <a class="btn yellow" href="https://ecommerce.hostinger.com/" target="_blank" rel="noopener noreferrer">
                Ir a la tienda de Hostinger ↗
            </a>
        </div>

    <?php endif; ?>

</main>

<?php site_footer(); ?>
