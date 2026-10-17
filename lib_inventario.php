<?php
declare(strict_types=1);

/**
 * Inventario: gestión de categorías y planilla Excel de productos locales.
 *
 * La planilla es la vía de carga masiva: se descarga con el estado actual,
 * se edita y al volver a subirla SOLO se aplican los cambios detectados
 * (nunca se pisa lo que no cambió). Cada importación deja un reporte con
 * el detalle de lo que tocó, que se puede consultar desde el panel.
 */

require_once __DIR__ . '/lib_xlsx.php';

const INV_REPORT_KEY       = 'inventario_ultimo_excel';
const INV_MAX_CHANGES      = 600;   /* cambios detallados que se guardan en el reporte */
const INV_MAX_NOTES        = 80;    /* avisos que se guardan en el reporte */

/** Agrega la columna nullable de stock a instalaciones existentes. */
function inv_ensure_stock_column(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $has = db()->query("SHOW COLUMNS FROM local_products LIKE 'stock'")->fetch();
    if (!$has) {
        try {
            db()->exec('ALTER TABLE local_products ADD COLUMN stock INT UNSIGNED NULL DEFAULT NULL AFTER price');
        } catch (Throwable $e) {
            /* Otra petición pudo aplicar la migración al mismo tiempo. */
            if (!db()->query("SHOW COLUMNS FROM local_products LIKE 'stock'")->fetch()) throw $e;
        }
    }
}

/* ══════════════════════════════════════════════════════════════════════
   CATEGORÍAS
   ══════════════════════════════════════════════════════════════════════ */

/** Texto → slug (minúsculas, sin acentos, con guiones). */
function inv_slugify(string $text): string {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, [
        'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
        'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
        'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
        'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
        'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
        'ñ'=>'n','ç'=>'c','ý'=>'y',
    ]);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function inv_category_by_id(int $id): ?array {
    if ($id < 1) return null;
    $st = db()->prepare('SELECT * FROM categories WHERE id=? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Productos locales que están usando una categoría (por slug o por nombre). */
function inv_category_products(string $slug, string $name): int {
    $conds = [];
    $params = [];
    if (trim($slug) !== '') { $conds[] = 'category_slug = ?'; $params[] = trim($slug); }
    if (trim($name) !== '') { $conds[] = 'category = ?';      $params[] = trim($name); }
    if (!$conds) return 0;
    $st = db()->prepare('SELECT COUNT(*) FROM local_products WHERE ' . implode(' OR ', $conds));
    $st->execute($params);
    return (int)$st->fetchColumn();
}

/** Cantidad de productos por categoría, indexada por slug y por nombre. */
function inv_category_counts(): array {
    $counts = ['slug' => [], 'name' => []];
    try {
        foreach (db()->query('SELECT category, category_slug, COUNT(*) c FROM local_products GROUP BY category, category_slug') as $r) {
            $slug = trim((string)$r['category_slug']);
            $name = trim((string)$r['category']);
            if ($slug !== '') $counts['slug'][$slug] = ($counts['slug'][$slug] ?? 0) + (int)$r['c'];
            if ($name !== '') $counts['name'][$name] = ($counts['name'][$name] ?? 0) + (int)$r['c'];
        }
    } catch (Throwable) {}
    return $counts;
}

/** Slug libre: si ya existe (en otra categoría) agrega -2, -3, … */
function inv_unique_slug(string $slug, int $ignoreId = 0): string {
    $base   = $slug !== '' ? $slug : 'categoria';
    $candid = $base;
    $n      = 2;
    while (true) {
        $st = db()->prepare('SELECT id FROM categories WHERE slug=? LIMIT 1');
        $st->execute([$candid]);
        $found = (int)$st->fetchColumn();
        if ($found === 0 || $found === $ignoreId) return $candid;
        $candid = $base . '-' . $n;
        $n++;
        if ($n > 500) return $base . '-' . bin2hex(random_bytes(3));
    }
}

/**
 * Crea o actualiza una categoría.
 * Devuelve ['id' => int, 'products_updated' => int].
 */
function inv_save_category(array $data): array {
    $id   = (int)($data['id'] ?? 0);
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') throw new RuntimeException('El nombre de la categoría es obligatorio.');
    if (mb_strlen($name) > 160) throw new RuntimeException('El nombre no puede superar los 160 caracteres.');

    $slug = inv_slugify((string)($data['slug'] ?? ''));
    if ($slug === '') $slug = inv_slugify($name);
    if ($slug === '') $slug = 'categoria';

    $sortOrder = max(-999, min(9999, (int)($data['sort_order'] ?? 0)));
    $active    = !empty($data['active']) ? 1 : 0;
    $sourceUrl = trim((string)($data['source_url'] ?? ''));
    if (mb_strlen($sourceUrl) > 500) $sourceUrl = mb_substr($sourceUrl, 0, 500);

    $slug = inv_unique_slug($slug, $id);

    if ($id > 0) {
        $old = inv_category_by_id($id);
        if (!$old) throw new RuntimeException('La categoría que intentás editar ya no existe.');

        db()->prepare('UPDATE categories SET slug=?, name=?, sort_order=?, active=?, source_url=?, updated_at=NOW() WHERE id=?')
            ->execute([$slug, $name, $sortOrder, $active, $sourceUrl, $id]);

        /* Los productos guardan el nombre y el slug de su categoría: si la
           categoría cambió de nombre o de slug, se acompasan. */
        $updated = 0;
        $oldSlug = trim((string)$old['slug']);
        $oldName = trim((string)$old['name']);
        if ($oldSlug !== '' || $oldName !== '') {
            $conds  = [];
            $params = [];
            if ($oldSlug !== '') { $conds[] = 'category_slug = ?'; $params[] = $oldSlug; }
            if ($oldName !== '') { $conds[] = 'category = ?';      $params[] = $oldName; }
            if ($conds) {
                $st = db()->prepare('UPDATE local_products SET category=?, category_slug=?, updated_at=NOW() WHERE ' . implode(' OR ', $conds));
                $st->execute(array_merge([$name, $slug], $params));
                $updated = $st->rowCount();
            }
        }
        return ['id' => $id, 'products_updated' => $updated];
    }

    db()->prepare('INSERT INTO categories (slug, name, sort_order, active, source_url) VALUES (?,?,?,?,?)')
        ->execute([$slug, $name, $sortOrder, $active, $sourceUrl]);
    return ['id' => (int)db()->lastInsertId(), 'products_updated' => 0];
}

/** Elimina una categoría. No permite borrar si tiene productos asignados. */
function inv_delete_category(int $id): array {
    $cat = inv_category_by_id($id);
    if (!$cat) throw new RuntimeException('La categoría que intentás eliminar ya no existe.');

    $used = inv_category_products((string)$cat['slug'], (string)$cat['name']);
    if ($used > 0) {
        throw new RuntimeException(
            'No se puede eliminar "' . $cat['name'] . '" porque tiene ' . $used
            . ' producto(s) asignado(s). Cambiales la categoría (desde Productos o desde el Excel) y volvé a intentar.'
        );
    }
    db()->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
    return $cat;
}

/** Categorías existentes como mapa nombre-normalizado → slug. */
function inv_category_lookup(): array {
    $map = [];
    foreach (get_categories(false) as $c) {
        $map[inv_norm_label((string)$c['name'])] = (string)$c['slug'];
        $map[inv_norm_label((string)$c['slug'])] = (string)$c['slug'];
    }
    return $map;
}

/** Normaliza un texto para comparar (minúsculas, sin acentos ni espacios dobles). */
function inv_norm_label(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u']);
    return preg_replace('/\s+/u', ' ', $s) ?? $s;
}

/**
 * Slug de categoría para guardar en un producto.
 * Si no viene uno explícito, lo busca entre las categorías del panel por nombre
 * y, si no existe, lo genera a partir del texto.
 */
function inv_resolve_category_slug(string $name, string $slug = ''): string {
    $slug = trim($slug);
    if ($slug !== '') return $slug;
    $name = trim($name);
    if ($name === '') return '';
    $lookup = inv_category_lookup();
    return $lookup[inv_norm_label($name)] ?? inv_slugify($name);
}

/* ══════════════════════════════════════════════════════════════════════
   PLANILLA: DESCARGA
   ══════════════════════════════════════════════════════════════════════ */

/** Precio lindo para mostrar en pantalla. */
function inv_money(?float $v): string {
    if ($v === null) return '—';
    return '$ ' . number_format($v, 2, ',', '.');
}

/** Hojas de la planilla descargable (inventario local actual). */
function inv_export_sheets(): array {
    inv_ensure_stock_column();
    $products = db()->query('SELECT * FROM local_products ORDER BY sort_order ASC, id ASC')->fetchAll();

    $rows = [];
    foreach ($products as $p) {
        $price = ($p['price'] !== null && (float)$p['price'] > 0) ? (float)$p['price'] : '';
        $rows[] = [
            (string)($p['sku'] ?? ''),
            (string)($p['title'] ?? ''),
            (string)($p['category'] ?? ''),
            $price,
            $p['stock'] === null ? '' : (int)$p['stock'],
            ((int)($p['visible'] ?? 0) === 1 ? 'Sí' : 'No'),
        ];
    }

    $cats = [];
    foreach (get_categories(false) as $c) {
        $cats[] = [
            (string)$c['name'],
            (string)$c['slug'],
            (int)$c['sort_order'],
            ((int)$c['active'] === 1 ? 'Sí' : 'No'),
        ];
    }

    return [
        [
            'name'        => 'Productos',
            'header'      => ['SKU', 'Nombre', 'Categoría', 'Precio', 'Stock', 'Visible'],
            'rows'        => $rows,
            'widths'      => [16, 58, 38, 15, 12, 10],
            'number_cols' => [3, 4],
            'center_cols' => [0, 5],
        ],
        [
            'name'        => 'Categorias',
            'header'      => ['Nombre', 'Slug', 'Orden', 'Activa'],
            'rows'        => $cats,
            'widths'      => [42, 42, 8, 10],
            'number_cols' => [2],
            'center_cols' => [3],
        ],
    ];
}

/* ══════════════════════════════════════════════════════════════════════
   PLANILLA: IMPORTACIÓN
   ══════════════════════════════════════════════════════════════════════ */

/** Lee el archivo subido (.xlsx o .csv) y devuelve las filas crudas. */
function inv_read_upload(string $path, string $filename): array {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') return xlsx_read_rows($path, 'Productos');
    if (in_array($ext, ['csv', 'txt'], true)) return csv_read_rows($path);
    throw new RuntimeException('Formato no soportado. Subí el Excel (.xlsx) que descargás desde acá, o un CSV.');
}

/** Normaliza encabezados para reconocerlos (sin acentos, minúsculas, sin símbolos). */
function inv_norm_header(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    return preg_replace('/[^a-z0-9]+/', '', $s) ?? '';
}

/** Mapea los encabezados de una fila a los campos que entiende el importador. */
function inv_map_header(array $row): array {
    $map = [];
    foreach (array_values($row) as $i => $raw) {
        $h = inv_norm_header((string)$raw);
        if ($h === '') continue;
        if (!isset($map['sku']) && (str_contains($h, 'sku') || $h === 'codigo' || str_starts_with($h, 'codig'))) {
            $map['sku'] = $i;
        } elseif (!isset($map['title']) && (str_contains($h, 'nombre') || str_contains($h, 'titulo') || $h === 'producto' || $h === 'name' || $h === 'detalle')) {
            $map['title'] = $i;
        } elseif (!isset($map['category']) && (str_contains($h, 'categoria') || str_contains($h, 'rubro') || $h === 'category')) {
            $map['category'] = $i;
        } elseif (!isset($map['price']) && (str_contains($h, 'precio') || $h === 'price')) {
            $map['price'] = $i;
        } elseif (!isset($map['stock']) && in_array($h, ['stock','cantidad','stockactual','existencia','existencias','inventario','inventoryquantity'], true)) {
            $map['stock'] = $i;
        } elseif (!isset($map['visible']) && (str_contains($h, 'visible') || in_array($h, ['activo','activa','publicado','estado','on'], true))) {
            $map['visible'] = $i;
        }
    }
    return $map;
}

/** "1.234,56" / "$ 1234.56" / "1234" → 1234.56 (null si no hay precio). */
function inv_parse_price(string $raw): ?float {
    $s = trim($raw);
    if ($s === '') return null;
    $s = preg_replace('/[^0-9,.\-]/u', '', $s) ?? '';
    if ($s === '' || $s === '-') return null;

    $lastComma = strrpos($s, ',');
    $lastDot   = strrpos($s, '.');
    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
        else                       { $s = str_replace(',', '', $s); }
    } elseif ($lastComma !== false) {
        $s = preg_match('/^\d{1,3}(,\d{3})+$/', $s) ? str_replace(',', '', $s) : str_replace(',', '.', $s);
    } elseif ($lastDot !== false) {
        $s = preg_match('/^\d{1,3}(\.\d{3})+$/', $s) ? str_replace('.', '', $s) : $s;
    }
    if (!is_numeric($s)) return null;
    return (float)$s;
}

/** "Sí", "No", "1", "0", "activo", "oculto"… → bool (null si no se entiende). */
function inv_parse_bool(string $raw): ?bool {
    $s = inv_norm_label($raw);
    $s = str_replace(['í', 'Í'], 'i', $s);
    if ($s === '') return null;
    if (in_array($s, ['si','s','1','true','verdadero','x','yes','y','on','activo','activa','visible','publicado','publicada','habilitado'], true)) return true;
    if (in_array($s, ['no','n','0','false','falso','off','inactivo','inactiva','oculto','oculta','hidden','despublicado','deshabilitado'], true)) return false;
    return null;
}

/** Stock como entero no negativo; celda vacía significa que no se controla. */
function inv_parse_stock(string $raw): ?int {
    $s = trim($raw);
    if ($s === '') return null;
    $s = preg_replace('/[\s\x{00A0}]/u', '', $s) ?? '';
    if (preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $s)) $s = str_replace([',', '.'], '', $s);
    if (!preg_match('/^\d+$/', $s)) return null;
    $value = (int)$s;
    return $value <= 10000000 ? $value : null;
}

/**
 * Aplica la planilla sobre el inventario local.
 *
 * @param array  $rows      filas crudas (con encabezado)
 * @param bool   $create    crear los productos que no existan
 * @param string $filename  nombre del archivo, para el reporte
 * @return array reporte con el detalle de los cambios
 */
function inv_apply_import(array $rows, bool $create, string $filename, bool $deleteMissing = false): array {
    /* 1. Encontrar la fila de encabezado */
    $headerIdx = -1;
    $map       = [];
    $limit     = min(8, count($rows));
    for ($i = 0; $i < $limit; $i++) {
        $m = inv_map_header($rows[$i] ?? []);
        if (isset($m['sku']) || isset($m['title'])) { $headerIdx = $i; $map = $m; break; }
    }
    if ($headerIdx < 0 || (!isset($map['sku']) && !isset($map['title']))) {
        throw new RuntimeException('No encontré las columnas de la planilla. Descargá el Excel desde este panel y editá ese archivo (SKU, Nombre, Categoría, Precio, Stock y Visible).');
    }

    inv_ensure_stock_column();

    $catLookup = inv_category_lookup();

    $stats   = ['read' => 0, 'updated' => 0, 'created' => 0, 'deleted' => 0, 'unchanged' => 0, 'ignored' => 0];
    $changes = [];
    $created = [];
    $deleted = [];
    $notes   = [];
    $truncated = false;
    $note = static function (string $msg) use (&$notes): void {
        if (count($notes) < INV_MAX_NOTES) $notes[] = $msg;
    };

    $val = static function (array $row, string $key) use ($map): string {
        if (!isset($map[$key])) return '';
        return trim((string)($row[$map[$key]] ?? ''));
    };

    /* Los productos nuevos entran al final del orden actual */
    $nextSort = (int)db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM local_products')->fetchColumn() + 1;
    $beforeRows = db()->query('SELECT id, sku, title FROM local_products')->fetchAll();
    $beforeIds = [];
    foreach ($beforeRows as $before) $beforeIds[(int)$before['id']] = $before;
    $matchedIds = [];

    $total = count($rows);
    for ($i = $headerIdx + 1; $i < $total; $i++) {
        $row     = $rows[$i];
        $rowNum  = $i + 1;                    /* número de fila tal como se ve en Excel */
        $sku     = $val($row, 'sku');
        $title   = $val($row, 'title');
        $catRaw  = $val($row, 'category');
        $priceRaw = $val($row, 'price');
        $stockRaw = $val($row, 'stock');
        $visRaw  = $val($row, 'visible');

        if ($sku === '' && $title === '' && $catRaw === '' && $priceRaw === '' && $stockRaw === '' && $visRaw === '') {
            continue;                          /* fila vacía, se ignora en silencio */
        }
        $stats['read']++;

        /* 2. Buscar el producto: por SKU y, si no hay, por nombre exacto */
        $existing = null;
        if ($sku !== '') {
            $st = db()->prepare('SELECT * FROM local_products WHERE UPPER(TRIM(sku)) = UPPER(?) LIMIT 1');
            $st->execute([$sku]);
            $existing = $st->fetch() ?: null;
        }
        if (!$existing && $title !== '') {
            $st = db()->prepare('SELECT * FROM local_products WHERE LOWER(TRIM(title)) = LOWER(?) LIMIT 1');
            $st->execute([$title]);
            $existing = $st->fetch() ?: null;
        }
        if ($existing) $matchedIds[(int)$existing['id']] = true;

        /* Precio */
        $price    = null;
        $priceSet = $priceRaw !== '';
        if ($priceSet) {
            $price = inv_parse_price($priceRaw);
            if ($price === null) {
                $note('Fila ' . $rowNum . ($sku !== '' ? ' (' . $sku . ')' : '') . ': el precio "' . $priceRaw . '" no es válido; se dejó el que ya tenía.');
                $priceSet = false;
            }
        }

        /* Un Excel antiguo sin encabezado Stock no modifica este dato.
           Con encabezado, vacío significa sin seguimiento; cero significa agotado. */
        $stockSet = isset($map['stock']);
        $stock = null;
        if ($stockSet && $stockRaw !== '') {
            $stock = inv_parse_stock($stockRaw);
            if ($stock === null) {
                $note('Fila ' . $rowNum . ($sku !== '' ? ' (' . $sku . ')' : '') . ': el stock "' . $stockRaw . '" no es un entero entre 0 y 10.000.000; se dejó como estaba.');
                $stockSet = false;
            }
        }

        /* Visible */
        $visible    = null;
        $visibleSet = $visRaw !== '';
        if ($visibleSet) {
            $visible = inv_parse_bool($visRaw);
            if ($visible === null) {
                $note('Fila ' . $rowNum . ($sku !== '' ? ' (' . $sku . ')' : '') . ': no entendí "' . $visRaw . '" en Visible; se dejó como estaba.');
                $visibleSet = false;
            }
        }

        /* Categoría */
        $catName = $catRaw;
        $catSlug = '';
        if ($catName !== '') {
            $catSlug = $catLookup[inv_norm_label($catName)] ?? '';
            if ($catSlug === '') {
                $note('Fila ' . $rowNum . ': la categoría "' . $catName . '" no existe en el panel (se guardó igual como texto).');
            }
        }

        /* 3. Producto nuevo */
        if (!$existing) {
            if (!$create) {
                $stats['ignored']++;
                $note('Fila ' . $rowNum . ': "' . ($title !== '' ? $title : $sku) . '" no está en el inventario y no se pidió crearlo.');
                continue;
            }
            if ($title === '') {
                $stats['ignored']++;
                $note('Fila ' . $rowNum . ': para crear un producto hace falta el nombre.');
                continue;
            }
            $slug = inv_product_slug($title);
            $st = db()->prepare(
                'INSERT INTO local_products
                    (title, description, price, stock, currency_symbol, category, category_slug, sku, slug, unit_of_sale, image_url, notes, visible, sort_order)
                 VALUES (?, \'\', ?, ?, \'$\', ?, ?, ?, ?, \'unidad\', \'\', \'\', ?, ?)'
            );
            $st->execute([$title, $price, ($stockSet ? $stock : null), $catName, $catSlug, $sku, $slug, ($visible === false ? 0 : 1), $nextSort]);
            $newId = (int)db()->lastInsertId();
            $nextSort++;
            $stats['created']++;
            if (count($created) < INV_MAX_CHANGES) {
                $created[] = [
                    'row'      => $rowNum,
                    'id'       => $newId,
                    'sku'      => $sku,
                    'title'    => $title,
                    'category' => $catName,
                    'price'    => inv_money($price),
                    'stock'    => $stockSet && $stock !== null ? (string)$stock : 'Sin seguimiento',
                    'visible'  => ($visible === false ? 'No' : 'Sí'),
                ];
            } else {
                $truncated = true;
            }
            continue;
        }

        /* 4. Producto existente: comparar y aplicar SOLO lo que cambió */
        $fields = [];
        $sets   = [];
        $params = [];

        if ($title !== '' && $title !== trim((string)$existing['title'])) {
            $fields[] = ['label' => 'Nombre', 'old' => (string)$existing['title'], 'new' => $title];
            $sets[]   = 'title=?';
            $params[] = $title;
        }

        if ($catRaw !== '' && inv_norm_label($catRaw) !== inv_norm_label((string)$existing['category'])) {
            $fields[] = ['label' => 'Categoría', 'old' => (string)$existing['category'], 'new' => $catName];
            $sets[]   = 'category=?';
            $params[] = $catName;
            $sets[]   = 'category_slug=?';
            $params[] = $catSlug;
        }

        if ($priceSet) {
            $oldPrice = ($existing['price'] !== null && (float)$existing['price'] > 0) ? (float)$existing['price'] : null;
            $same = ($oldPrice === null && ($price === null || $price <= 0))
                 || ($oldPrice !== null && $price !== null && abs($oldPrice - $price) < 0.005);
            if (!$same) {
                $oldLabel = inv_money($oldPrice);
                $newLabel = ($price !== null && $price > 0) ? inv_money($price) : 'A consultar';
                $fields[] = ['label' => 'Precio', 'old' => $oldLabel, 'new' => $newLabel];
                $sets[]   = 'price=?';
                $params[] = ($price !== null && $price > 0) ? $price : null;
            }
        }

        if ($stockSet) {
            $oldStock = $existing['stock'] !== null ? (int)$existing['stock'] : null;
            if ($oldStock !== $stock) {
                $fields[] = ['label' => 'Stock', 'old' => $oldStock === null ? 'Sin seguimiento' : (string)$oldStock, 'new' => $stock === null ? 'Sin seguimiento' : (string)$stock];
                $sets[]   = 'stock=?';
                $params[] = $stock;
            }
        }

        if ($visibleSet) {
            $newVis = $visible ? 1 : 0;
            if ((int)$existing['visible'] !== $newVis) {
                $fields[] = ['label' => 'Visible', 'old' => ((int)$existing['visible'] === 1 ? 'Sí' : 'No'), 'new' => ($newVis === 1 ? 'Sí' : 'No')];
                $sets[]   = 'visible=?';
                $params[] = $newVis;
            }
        }

        if (!$fields) {
            $stats['unchanged']++;
            continue;
        }

        $sets[]   = 'updated_at=NOW()';
        $params[] = (int)$existing['id'];
        db()->prepare('UPDATE local_products SET ' . implode(', ', $sets) . ' WHERE id=?')->execute($params);
        $stats['updated']++;

        if (count($changes) < INV_MAX_CHANGES) {
            $changes[] = [
                'row'    => $rowNum,
                'id'     => (int)$existing['id'],
                'sku'    => (string)$existing['sku'],
                'title'  => (string)$existing['title'],
                'fields' => $fields,
            ];
        } else {
            $truncated = true;
        }
    }

    if ($deleteMissing) {
        if ($stats['read'] < 1) {
            throw new RuntimeException('No se borró nada: la planilla no contiene filas de productos para sincronizar.');
        }
        if ($beforeIds && !$matchedIds) {
            throw new RuntimeException('No se eliminó nada: ninguna fila coincide con el inventario actual. Revisá que sea la planilla completa descargada desde este panel.');
        }
        foreach ($beforeIds as $id => $old) {
            if (isset($matchedIds[$id])) continue;
            db()->prepare('DELETE FROM local_products WHERE id=?')->execute([$id]);
            $stats['deleted']++;
            if (count($deleted) < INV_MAX_CHANGES) {
                $deleted[] = ['id' => $id, 'sku' => (string)$old['sku'], 'title' => (string)$old['title']];
            } else {
                $truncated = true;
            }
        }
    }

    return [
        'at'        => (string)date('Y-m-d H:i:s'),
        'file'      => $filename,
        'user'      => (string)(current_user()['name'] ?? ''),
        'create'    => $create,
        'delete_missing' => $deleteMissing,
        'stats'     => $stats,
        'changes'   => $changes,
        'created'   => $created,
        'deleted'   => $deleted,
        'notes'     => $notes,
        'truncated' => $truncated,
    ];
}

/** Slug único para un producto nuevo. */
function inv_product_slug(string $title): string {
    $slug = inv_slugify($title);
    if ($slug === '') $slug = 'producto';
    $slug = mb_substr($slug, 0, 200);
    $candid = $slug;
    $n = 2;
    while (true) {
        $st = db()->prepare('SELECT id FROM local_products WHERE slug=? LIMIT 1');
        $st->execute([$candid]);
        if (!$st->fetchColumn()) return $candid;
        $candid = $slug . '-' . $n;
        $n++;
        if ($n > 500) return $slug . '-' . bin2hex(random_bytes(3));
    }
}

/* ── Reporte del último Excel ───────────────────────────────────────── */

function inv_save_report(array $report): void {
    foreach (['notes', 'created', 'deleted', 'changes'] as $key) {
        if (!isset($report[$key]) || !is_array($report[$key])) $report[$key] = [];
    }
    $encode = static fn(array $r): string => (string)json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $json   = $encode($report);

    /* El valor se guarda en un campo TEXT (~64 KB): si el detalle no entra,
       se recorta a la mitad hasta que quepa y se marca el reporte. */
    for ($i = 0; $i < 40 && strlen($json) > 60000; $i++) {
        $report['truncated'] = true;
        if (count($report['notes']) > 0) {
            $report['notes']   = array_slice($report['notes'], 0, intdiv(count($report['notes']), 2));
        } elseif (count($report['created']) > 0) {
            $report['created'] = array_slice($report['created'], 0, intdiv(count($report['created']), 2));
        } elseif (count($report['deleted']) > 0) {
            $report['deleted'] = array_slice($report['deleted'], 0, intdiv(count($report['deleted']), 2));
        } elseif (count($report['changes']) > 0) {
            $report['changes'] = array_slice($report['changes'], 0, intdiv(count($report['changes']), 2));
        } else {
            $report['changes'] = [];
            $report['created'] = [];
            $report['deleted'] = [];
            $report['notes']   = [];
        }
        $json = $encode($report);
    }

    save_setting(INV_REPORT_KEY, $json);
}

function inv_last_report(): ?array {
    $raw = setting(INV_REPORT_KEY, '');
    if ($raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}
