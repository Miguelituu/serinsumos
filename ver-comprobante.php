<?php
require_once __DIR__ . '/lib.php';
require_login();
$user = current_user();

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId > 0) {
    $st = db()->prepare('SELECT user_id AS owner_id, receipt_path FROM orders WHERE id=? LIMIT 1');
    $st->execute([$orderId]);
    $doc = $st->fetch();
} else {
    $id = (int)($_GET['id'] ?? 0);
    $st = db()->prepare('SELECT owner_id, receipt_path FROM payment_documents WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $doc = $st->fetch();
}

if (!$doc || empty($doc['receipt_path']) || ((int)$doc['owner_id'] !== (int)$user['id'] && !is_admin())) {
    http_response_code(404);
    exit('Comprobante no encontrado o sin permisos para visualizarlo.');
}

$filename = basename((string)$doc['receipt_path']);
$path = __DIR__ . '/uploads/receipts/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('El archivo ya no está disponible en el servidor.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path);
if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit('Formato no permitido.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
