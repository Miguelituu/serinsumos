<?php
declare(strict_types=1);
/**
 * buscar-productos.php — Buscador de inventario para armar presupuestos.
 *
 *   ?q=<texto>&limit=<n>   → JSON { items: [...] }
 *
 * Devuelve también los artículos ocultos, eliminados o sin stock: son válidos
 * para cotizar (ítems administrativos, servicios o pedidos a pedido) y cada
 * resultado informa su estado real para que el operador decida.
 */
require_once __DIR__ . '/lib.php';

require_staff('presupuestos');

$query = (string)($_GET['q'] ?? '');
$limit = (int)($_GET['limit'] ?? 12);

$result = search_inventory($query, $limit);

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo json_encode([
    'query'         => $query,
    'count'         => count($result['items']),
    'catalog_error' => $result['catalog_error'],
    'items'         => $result['items'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
