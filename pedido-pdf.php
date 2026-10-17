<?php
declare(strict_types=1);
/**
 * pedido-pdf.php — Resumen de pedido en PDF (panel de gestión).
 *
 *   ?id=<orderId>&estado=presupuesto|pendiente|pagado&dl=0|1
 *
 * Usa el renderer compartido (lib_doc_pdf.php) para generar un documento A4
 * con los artículos, totales, datos del cliente y el estado elegido.
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_doc_pdf.php';

require_staff('pedidos');

$orderId   = (int)($_GET['id'] ?? $_GET['order_id'] ?? 0);
$estadoKey = (string)($_GET['estado'] ?? 'presupuesto');
$download  = (string)($_GET['dl'] ?? '0') === '1';

$order = get_order_by_id($orderId);
if (!$order) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Pedido no encontrado.');
}

$ESTADOS = document_estados();
if (!isset($ESTADOS[$estadoKey])) $estadoKey = 'presupuesto';
$estado = $ESTADOS[$estadoKey];

$STATUS_LABELS = [
    'pending_review'   => 'Pendiente de revisión de stock',
    'confirmed'        => 'Confirmado · esperando pago',
    'payment_uploaded' => 'Comprobante recibido · verificando',
    'completed'        => 'Completado y entregado',
    'cancelled'        => 'Cancelado',
];

/* ── Artículos ───────────────────────────────────────────────────────── */
$items    = get_order_items($orderId);
$reviewed = !in_array((string)$order['status'], ['pending_review', 'cancelled'], true);

$lines    = [];
$excluded = [];
foreach ($items as $it) {
    $price = $it['unit_price'] !== null ? (float)$it['unit_price'] : 0.0;

    if ($reviewed) {
        if (in_array($it['item_status'], ['out_of_stock', 'removed'], true) || (int)$it['quantity_approved'] < 1) {
            $excluded[] = $it;
            continue;
        }
        $qty = (int)$it['quantity_approved'];
    } else {
        $qty = (int)$it['quantity_requested'];
        if ($qty < 1) $qty = 1;
    }

    $lines[] = [
        'title'   => (string)$it['title'],
        'sku'     => (string)$it['sku'],
        'variant' => trim((string)($it['variant_title'] ?? '')),
        'unit'    => trim((string)($it['unit_of_sale'] ?? '')),
        'qty'     => $qty,
        'price'   => $price,
        'sub'     => $price * $qty,
    ];
}
$total = 0.0;
foreach ($lines as $l) $total += $l['sub'];

$sym   = (string)($order['currency_symbol'] ?: '$');
$money = static fn(float $v): string => $sym . number_format($v, 2, ',', '.');

$siteName = setting('site_name', APP_NAME);
$whatsapp = trim(setting('whatsapp', DEFAULT_WHATSAPP));

/* ── Panel de cliente y datos ────────────────────────────────────────── */
$clientBig = trim((string)($order['user_business'] ?? '')) !== '' ? (string)$order['user_business'] : (string)$order['user_name'];

$clientLines = [];
if (trim((string)$order['user_name']) !== '' && $clientBig !== (string)$order['user_name']) {
    $clientLines[] = 'Contacto: ' . (string)$order['user_name'];
}
if (trim((string)$order['user_email']) !== '') $clientLines[] = (string)$order['user_email'];
if (trim((string)$order['user_phone']) !== '') $clientLines[] = 'Tel: ' . (string)$order['user_phone'];

$metaRows = [
    ['N°: ', (string)$order['order_number']],
    ['Fecha: ', date('d/m/Y', strtotime((string)$order['created_at']))],
    ['Artículos: ', (string)count($lines)],
    ['Estado: ', (string)($STATUS_LABELS[$order['status']] ?? $order['status'])],
];

/* ── Observaciones ───────────────────────────────────────────────────── */
$notes = [];
if (trim((string)($order['admin_notes'] ?? '')) !== '') {
    $notes[] = ['Mensaje de ' . $siteName, (string)$order['admin_notes']];
}
if (trim((string)($order['customer_notes'] ?? '')) !== '') {
    $notes[] = ['Nota del cliente', (string)$order['customer_notes']];
}

/* ── Bloque de estado / pago ─────────────────────────────────────────── */
if ($estadoKey === 'pendiente') {
    $block = [
        'kind'  => 'bank',
        'title' => 'Datos para transferir',
        'bank'  => get_bank_details(),
        'line'  => 'Total a transferir: ' . $money($total) . '  ·  Luego enviá el comprobante por WhatsApp.',
    ];
} elseif ($estadoKey === 'pagado') {
    $extra = !empty($order['receipt_uploaded_at'])
        ? 'Comprobante recibido el ' . date('d/m/Y', strtotime((string)$order['receipt_uploaded_at']))
        : 'Pago registrado por ' . $siteName;
    $block = [
        'kind'  => 'paid',
        'title' => 'Pago acreditado · ¡Gracias por tu compra!',
        'line'  => $extra . '  ·  Total abonado: ' . $money($total),
    ];
} else {
    $block = [
        'kind'  => 'quote',
        'title' => 'Presupuesto sujeto a confirmación',
        'line'  => 'Los precios y el stock están sujetos a verificación. Validez: 7 días desde la fecha de emisión.',
    ];
}

$excludedRows = [];
foreach ($excluded as $it) {
    $excludedRows[] = ['title' => (string)$it['title'], 'qty' => (int)$it['quantity_requested']];
}

/* ── Generar ─────────────────────────────────────────────────────────── */
$bytes = render_document_pdf([
    'title'         => 'Resumen de pedido',
    'subtitle'      => 'Pedido ' . $order['order_number'] . '  ·  Generado el ' . date('d/m/Y H:i'),
    'reference'     => 'Pedido ' . $order['order_number'],
    'estadoKey'     => $estadoKey,
    'estadoLabel'   => $estado['label'],
    'estadoColor'   => $estado['color'],
    'siteName'      => $siteName,
    'whatsapp'      => $whatsapp,
    'metaTitle'     => 'DATOS DEL PEDIDO',
    'clientBig'     => $clientBig,
    'clientLines'   => $clientLines,
    'metaRows'      => $metaRows,
    'items'         => $lines,
    'total'         => $total,
    'money'         => $money,
    'excluded'      => $excludedRows,
    'excludedLabel' => $reviewed ? 'Pedidos' : 'Solicitados',
    'excludedTitle' => 'Artículos no incluidos (sin stock)',
    'notes'         => $notes,
    'statusBlock'   => $block,
    'footerNote'    => 'Documento generado automáticamente por ' . $siteName . '. No constituye factura fiscal.',
    'emptyText'     => 'Este pedido no tiene artículos aprobados.',
]);

$fileBase = $siteName . '-' . preg_replace('/\s+/', '-', $estado['label']) . '-'
    . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$order['order_number']) . '.pdf';
$fileBase = preg_replace('/-+/', '-', $fileBase);

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $fileBase . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo $bytes;
exit;
