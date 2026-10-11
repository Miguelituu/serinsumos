<?php
require_once __DIR__ . '/lib.php';
$catalog = get_products_with_overrides(24, false);
$products = $catalog['products'] ?? [];
site_header('', 'Serinsumos: insumos, tintas, sublimables y maquinarias para sublimación y estampado. Venta mayorista para clientes verificados.');
$banners = get_banners(false);
if (!$banners) {
    $banners = [
        [
            'id' => 1,
            'title' => 'Todo para crear.<br>Todo para <span>crecer.</span>',
            'eyebrow' => 'EL COLOR ES SOLO EL COMIENZO',
            'description' => 'Insumos y maquinarias para sublimación y estampado.<br>Tu próximo gran proyecto empieza acá.',
            'image_url' => 'https://alpha-color.ancorathemes.com/wp-content/uploads/revslider/o/2/50c06332296e98de62b9a15f555f5031.webp',
            'button_primary_text' => 'Explorar catálogo',
            'button_primary_link' => 'catalogo.php',
            'button_secondary_text' => 'Quiero ser mayorista',
            'button_secondary_link' => 'cuenta.php?registro=1',
        ]
    ];
}
$firstBanner = $banners[0];
$totalBanners = count($banners);
?>
<main>
<section class="hero">
    <div class="hero-photo">
        <img id="hero-slide" src="<?= e($firstBanner['image_url']) ?>" alt="Insumos y herramientas para sublimación">
    </div>
    <div class="hero-copy">
        <p class="eyebrow" id="hero-eyebrow"><?= e($firstBanner['eyebrow'] ?: 'EL COLOR ES SOLO EL COMIENZO') ?></p>
        <h1 id="hero-title"><?= ($firstBanner['title'] ?: 'Todo para crear.<br>Todo para <span>crecer.</span>') ?></h1>
        <p class="hero-description" id="hero-description"><?= nl2br(e($firstBanner['description'])) ?></p>
        <div class="hero-buttons">
            <?php if (!empty($firstBanner['button_primary_text'])): ?>
                <a class="btn yellow" id="hero-btn-primary" href="<?= e(preg_match('~^https?://~i', $firstBanner['button_primary_link']) ? $firstBanner['button_primary_link'] : site_url($firstBanner['button_primary_link'] ?: 'catalogo.php')) ?>">
                    <span id="hero-btn-primary-text"><?= e($firstBanner['button_primary_text']) ?></span> <span>→</span>
                </a>
            <?php endif; ?>
            <?php if (!empty($firstBanner['button_secondary_text'])): ?>
                <a class="hero-secondary" id="hero-btn-secondary" href="<?= e(preg_match('~^https?://~i', $firstBanner['button_secondary_link']) ? $firstBanner['button_secondary_link'] : site_url($firstBanner['button_secondary_link'] ?: 'cuenta.php?registro=1')) ?>">
                    <span id="hero-btn-secondary-text"><?= e($firstBanner['button_secondary_text']) ?></span> →
                </a>
            <?php endif; ?>
        </div>
        <p class="hero-proof">✓ Precios exclusivos para clientes verificados</p>
    </div>
    <div class="hero-bottom">
        <span>INSUMOS · TECNOLOGÍA · POSIBILIDADES</span>
        <?php if ($totalBanners > 1): ?>
        <div>
            <button type="button" aria-label="Imagen anterior" onclick="heroSlide(-1)">‹</button>
            <b id="hero-number">01<small> / <?= sprintf('%02d', $totalBanners) ?></small></b>
            <button type="button" aria-label="Imagen siguiente" onclick="heroSlide(1)">›</button>
        </div>
        <?php endif; ?>
    </div>
</section>
<section class="categories"><div class="category-intro"><small>ENCONTRÁ LO QUE NECESITÁS</small><h2>Tu mundo,<br>a todo color.</h2></div><a href="<?= e(site_url('catalogo.php?categoria=textiles-para-estampar')) ?>"><span class="category-icon">♧</span><span>Textiles para estampar</span><span class="category-arrow">↗</span></a><a href="<?= e(site_url('catalogo.php?categoria=pad-gamer-pad-mouse')) ?>"><span class="category-icon">▣</span><span>Pad gamer & mouse</span><span class="category-arrow">↗</span></a><a href="<?= e(site_url('catalogo.php?categoria=accesorios-y-regalos-para-sublimar')) ?>"><span class="category-icon">☕</span><span>Regalos y accesorios</span><span class="category-arrow">↗</span></a><a href="<?= e(site_url('catalogo.php?categoria=packaging-bolsas-cajas-y-cartulina')) ?>"><span class="category-icon">▤</span><span>Packaging y bolsas</span><span class="category-arrow">↗</span></a><a href="<?= e(site_url('catalogo.php?categoria=impresoras-sublimadoras-y-consumibles')) ?>"><span class="category-icon">◉</span><span>Equipos y consumibles</span><span class="category-arrow">↗</span></a></section>
<section class="products"><div class="section-heading"><div><p class="eyebrow blue">EQUIPÁ TU PRÓXIMO PROYECTO</p><h2>Elegidos para crear.</h2></div><a href="<?= e(site_url('catalogo.php')) ?>">Ver todos los productos →</a></div><?php if (!empty($catalog['error']) && !$products): ?><p class="system-note">No pudimos cargar el catálogo ahora. Revisá la conexión con la tienda online de Hostinger.</p><?php elseif (!$products): ?><p>Próximamente vas a encontrar nuestros productos en el catálogo.</p><?php else: ?><div class="product-grid"><?php foreach (array_slice($products, 0, 4) as $p) render_product_card($p); ?></div><?php endif; ?></section>
<section class="story" id="nosotros"><div class="story-image"><img loading="lazy" src="https://alpha-color.ancorathemes.com/wp-content/uploads/2017/04/image-30.jpg" alt="Colores y herramientas para impresión"><span>Más que insumos.<br><b>Un aliado para tu negocio.</b></span></div><div><p class="eyebrow blue">CREAMOS CON VOS</p><h2>Vos ponés la idea.<br>Nosotros, el color.</h2><p>Acompañamos a emprendedores, talleres y negocios con insumos y equipos para sublimación y estampado. Encontrá lo que necesitás para dar el siguiente paso.</p><ul><li>✓ Atención personalizada</li><li>✓ Compras mayoristas con revisión de stock</li><li>✓ Asesoramiento para elegir tu equipo</li></ul><a href="<?= e(whatsapp_url('Hola, quisiera asesoramiento para mi proyecto de sublimación.')) ?>" target="_blank" rel="noopener noreferrer" class="btn yellow">Hablemos de tu proyecto →</a></div></section>
<section class="wholesale" id="mayoristas"><div><p class="eyebrow">PENSADO PARA TU NEGOCIO</p><h2>Comprar al por mayor,<br>sin complicaciones.</h2><a class="btn yellow" href="<?= e(site_url('cuenta.php?registro=1')) ?>">Solicitar cuenta mayorista →</a></div><ol><li><b>01</b><div><h3>Registrá tu negocio</h3><p>Completá tus datos. Revisamos y aprobamos tu cuenta manualmente.</p></div></li><li><b>02</b><div><h3>Armá tu pedido</h3><p>Accedé a precios mayoristas y enviá tu pedido con el pago coordinado.</p></div></li><li><b>03</b><div><h3>Confirmamos y coordinamos</h3><p>Verificamos stock antes del pago. Cuando habilitemos tu pedido, podrás adjuntar el comprobante en tu cuenta.</p></div></li></ol></section>
<section class="faq"><p class="eyebrow blue">ANTES DE EMPEZAR</p><h2>Resolvemos tus dudas.</h2><details><summary>¿Por qué no puedo ver los precios?</summary><p>Los precios se muestran a clientes con cuenta mayorista aprobada. Registrate y esperá nuestra revisión.</p></details><details><summary>¿Cuándo tengo que pagar?</summary><p>No transfieras antes de que confirmemos el stock y el importe final. Te habilitaremos la carga del comprobante en tu cuenta.</p></details><details><summary>¿Puedo comprar por WhatsApp?</summary><p>Sí. Podés consultar por un producto o enviar el detalle de tu pedido para coordinar la compra.</p></details></section>
</main>
<script>
const heroBanners = <?= json_encode($banners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
let heroIndex = 0;
const totalBanners = heroBanners.length;

function heroSlide(direction) {
    if (totalBanners <= 1) return;
    heroIndex = (heroIndex + direction + totalBanners) % totalBanners;
    const b = heroBanners[heroIndex];

    const imgEl = document.getElementById('hero-slide');
    if (imgEl && b.image_url) imgEl.src = b.image_url;

    const numEl = document.getElementById('hero-number');
    if (numEl) numEl.innerHTML = String(heroIndex + 1).padStart(2, '0') + '<small> / ' + String(totalBanners).padStart(2, '0') + '</small>';

    const eyebrowEl = document.getElementById('hero-eyebrow');
    if (eyebrowEl && b.eyebrow !== undefined) eyebrowEl.textContent = b.eyebrow;

    const titleEl = document.getElementById('hero-title');
    if (titleEl && b.title !== undefined) titleEl.innerHTML = b.title;

    const descEl = document.getElementById('hero-description');
    if (descEl && b.description !== undefined) descEl.textContent = b.description;

    const btnPrimary = document.getElementById('hero-btn-primary');
    const btnPrimaryText = document.getElementById('hero-btn-primary-text');
    if (btnPrimary && btnPrimaryText) {
        if (b.button_primary_text) {
            btnPrimary.style.display = '';
            btnPrimaryText.textContent = b.button_primary_text;
            btnPrimary.href = b.button_primary_link || 'catalogo.php';
        } else {
            btnPrimary.style.display = 'none';
        }
    }

    const btnSecondary = document.getElementById('hero-btn-secondary');
    const btnSecondaryText = document.getElementById('hero-btn-secondary-text');
    if (btnSecondary && btnSecondaryText) {
        if (b.button_secondary_text) {
            btnSecondary.style.display = '';
            btnSecondaryText.textContent = b.button_secondary_text;
            btnSecondary.href = b.button_secondary_link || 'cuenta.php?registro=1';
        } else {
            btnSecondary.style.display = 'none';
        }
    }
}

if (totalBanners > 1) {
    setInterval(() => { heroSlide(1); }, 7500);
}
</script>
<?php site_footer(); ?>
