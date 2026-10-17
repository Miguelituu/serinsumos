<?php
declare(strict_types=1);
/**
 * presupuesto-pdf.php — PDF de un presupuesto (panel de gestión).
 *
 *   ?id=<quoteId>&estado=presupuesto|pendiente|pagado&dl=0|1
 *
 * Requiere sesión con permiso sobre la pestaña Presupuestos.
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_doc_pdf.php';

require_staff('presupuestos');

$quoteId   = (int)($_GET['id'] ?? 0);
$estadoKey = (string)($_GET['estado'] ?? 'presupuesto');
$download  = (string)($_GET['dl'] ?? '0') === '1';

$quote = get_quote_by_id($quoteId);
if (!$quote) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Presupuesto no encontrado.');
}

$bytes = quote_pdf_bytes($quote, $estadoKey);
$file  = quote_pdf_filename($quote, $estadoKey);

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $file . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo $bytes;
exit;
