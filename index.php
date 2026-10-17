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
<section class="hero" id="hero" aria-roledescription="carrusel" aria-label="Destacados de Serinsumos">
    <div class="hero-bg" aria-hidden="true"></div>
    <div class="hero-photo">
        <img class="hero-img" id="hero-img-a" src="<?= e($firstBanner['image_url']) ?>" alt="<?= e(trim(strip_tags($firstBanner['title'] ?? '')) ?: 'Insumos y herramientas para sublimación') ?>" fetchpriority="high" decoding="async">
        <img class="hero-img" id="hero-img-b" alt="" aria-hidden="true" decoding="async">
    </div>
    <div class="hero-copy" id="hero-copy">
        <p class="eyebrow" id="hero-eyebrow"><?= e($firstBanner['eyebrow'] ?: 'EL COLOR ES SOLO EL COMIENZO') ?></p>
        <h1 class="hero-title" id="hero-title"><?= ($firstBanner['title'] ?: 'Todo para crear.<br>Todo para <span>crecer.</span>') ?></h1>
        <p class="hero-description" id="hero-description"><?= nl2br(preg_replace('~&lt;br\s*/?&gt;~i', '<br>', e($firstBanner['description']))) ?></p>
        <div class="hero-buttons">
            <?php if (!empty($firstBanner['button_primary_text'])): ?>
                <a class="btn yellow" id="hero-btn-primary" href="<?= e(preg_match('~^https?://~i', $firstBanner['button_primary_link']) ? $firstBanner['button_primary_link'] : site_url($firstBanner['button_primary_link'] ?: 'catalogo.php')) ?>">
                    <span id="hero-btn-primary-text"><?= e($firstBanner['button_primary_text']) ?></span> <span class="hero-btn-arrow" aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
            <?php if (!empty($firstBanner['button_secondary_text'])): ?>
                <a class="hero-secondary" id="hero-btn-secondary" href="<?= e(preg_match('~^https?://~i', $firstBanner['button_secondary_link']) ? $firstBanner['button_secondary_link'] : site_url($firstBanner['button_secondary_link'] ?: 'cuenta.php?registro=1')) ?>">
                    <span id="hero-btn-secondary-text"><?= e($firstBanner['button_secondary_text']) ?></span>
                    <span class="hero-btn-arrow" aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
        </div>
        <p class="hero-proof">✓ Precios exclusivos para clientes verificados</p>
    </div>
    <div class="hero-bottom">
        <span>INSUMOS · TECNOLOGÍA · POSIBILIDADES</span>
        <?php if ($totalBanners > 1): ?>
        <div class="hero-dots" id="hero-dots" role="tablist" aria-label="Seleccionar diapositiva"></div>
        <div class="hero-nav">
            <button type="button" class="hero-arrow" aria-label="Foto anterior" onclick="heroSlide(-1)">‹</button>
            <b id="hero-number">01<small> / <?= sprintf('%02d', $totalBanners) ?></small></b>
            <button type="button" class="hero-arrow" aria-label="Foto siguiente" onclick="heroSlide(1)">›</button>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($totalBanners > 1): ?>
    <div class="hero-progress" aria-hidden="true"><span></span></div>
    <?php endif; ?>
</section>
<section class="categories">
    <div class="category-intro"><small>ENCONTRÁ LO QUE NECESITÁS</small><h2>Tu mundo,<br>a todo color.</h2></div>
    <a href="<?= e(site_url('catalogo.php?categoria=textiles-para-estampar')) ?>">
        <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 48 48" width="42" height="42"><path d="M18 7 7.5 12.5l4.3 9L17 19v21h14V19l5.2 2.5 4.3-9L30 7l-3 3.6h-6L18 7Z" fill="#39aef2"/><path d="M21 7h6a3 3 0 0 1-6 0Z" fill="#ed3285"/><rect x="21" y="27" width="6" height="6" rx="1.2" fill="#ffe72e"/></svg></span>
        <span class="category-label">Textiles para estampar</span>
        <span class="category-arrow" aria-hidden="true">↗</span>
    </a>
    <a href="<?= e(site_url('catalogo.php?categoria=pad-gamer-pad-mouse')) ?>">
        <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 48 48" width="42" height="42"><rect x="14" y="6" width="20" height="36" rx="10" fill="#39aef2"/><rect x="22.4" y="10" width="3.2" height="11" rx="1.6" fill="#ed3285"/><rect x="22.4" y="26" width="3.2" height="9" rx="1.6" fill="#ffffff" opacity=".85"/></svg></span>
        <span class="category-label">Pad gamer &amp; mouse</span>
        <span class="category-arrow" aria-hidden="true">↗</span>
    </a>
    <a href="<?= e(site_url('catalogo.php?categoria=accesorios-y-regalos-para-sublimar')) ?>">
        <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 48 48" width="42" height="42"><path d="M21 5c0 3-3 3-3 6s3 3 3 6" fill="none" stroke="#ffe72e" stroke-width="2.4" stroke-linecap="round"/><path d="M28 5c0 3-3 3-3 6s3 3 3 6" fill="none" stroke="#ffe72e" stroke-width="2.4" stroke-linecap="round"/><path d="M11 17h20v15a7 7 0 0 1-7 7h-6a7 7 0 0 1-7-7V17Z" fill="#ed3285"/><path d="M31 21h3a6 6 0 0 1 0 12h-3" fill="none" stroke="#ed3285" stroke-width="4"/><path d="M11 17h20" stroke="#ffffff" stroke-width="2.5" opacity=".5"/></svg></span>
        <span class="category-label">Regalos y accesorios</span>
        <span class="category-arrow" aria-hidden="true">↗</span>
    </a>
    <a href="<?= e(site_url('catalogo.php?categoria=packaging-bolsas-cajas-y-cartulina')) ?>">
        <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 48 48" width="42" height="42"><path d="M24 5 42 14v20L24 43 6 34V14L24 5Z" fill="#39aef2"/><path d="M6 14v20l18 9V23L6 14Z" fill="#2a93d0" opacity=".45"/><path d="M24 5 42 14 24 23 6 14 24 5Z" fill="#ffe72e"/><path d="M24 23v20" fill="none" stroke="#172530" stroke-width="2" opacity=".3"/></svg></span>
        <span class="category-label">Packaging y bolsas</span>
        <span class="category-arrow" aria-hidden="true">↗</span>
    </a>
    <a href="<?= e(site_url('catalogo.php?categoria=impresoras-sublimadoras-y-consumibles')) ?>">
        <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 48 48" width="42" height="42"><rect x="15" y="6" width="18" height="12" rx="1.5" fill="#172530"/><rect x="6" y="17" width="36" height="16" rx="3" fill="#39aef2"/><rect x="14" y="27" width="20" height="15" rx="1.5" fill="#ffffff"/><rect x="18" y="31" width="12" height="2.4" rx="1.2" fill="#ed3285"/><rect x="18" y="36" width="8" height="2.4" rx="1.2" fill="#ed3285"/><circle cx="37" cy="22" r="1.8" fill="#ffe72e"/></svg></span>
        <span class="category-label">Equipos y consumibles</span>
        <span class="category-arrow" aria-hidden="true">↗</span>
    </a>
</section>
<section class="products"><div class="section-heading"><div><p class="eyebrow blue">EQUIPÁ TU PRÓXIMO PROYECTO</p><h2>Elegidos para crear.</h2></div><a href="<?= e(site_url('catalogo.php')) ?>">Ver todos los productos →</a></div><?php if (!empty($catalog['error']) && !$products): ?><p class="system-note">No pudimos cargar el catálogo ahora. Revisá la conexión con la tienda online de Hostinger.</p><?php elseif (!$products): ?><p>Próximamente vas a encontrar nuestros productos en el catálogo.</p><?php else: ?><div class="product-grid"><?php foreach (array_slice($products, 0, 4) as $p) render_product_card($p); ?></div><?php endif; ?></section>
<section class="story" id="nosotros"><div class="story-image"><img loading="lazy" src="https://alpha-color.ancorathemes.com/wp-content/uploads/2017/04/image-30.jpg" alt="Colores y herramientas para impresión"><span>Más que insumos.<br><b>Un aliado para tu negocio.</b></span></div><div><p class="eyebrow blue">CREAMOS CON VOS</p><h2>Vos ponés la idea.<br>Nosotros, el color.</h2><p>Acompañamos a emprendedores, talleres y negocios con insumos y equipos para sublimación y estampado. Encontrá lo que necesitás para dar el siguiente paso.</p><ul><li>✓ Atención personalizada</li><li>✓ Compras mayoristas con revisión de stock</li><li>✓ Asesoramiento para elegir tu equipo</li></ul><a href="<?= e(whatsapp_url('Hola, quisiera asesoramiento para mi proyecto de sublimación.')) ?>" target="_blank" rel="noopener noreferrer" class="btn yellow">Hablemos de tu proyecto →</a></div></section>
<section class="wholesale" id="mayoristas"><div><p class="eyebrow">PENSADO PARA TU NEGOCIO</p><h2>Comprar al por mayor,<br>sin complicaciones.</h2><a class="btn yellow" href="<?= e(site_url('cuenta.php?registro=1')) ?>">Solicitar cuenta mayorista →</a></div><ol><li><b>01</b><div><h3>Registrá tu negocio</h3><p>Completá tus datos. Revisamos y aprobamos tu cuenta manualmente.</p></div></li><li><b>02</b><div><h3>Armá tu pedido</h3><p>Accedé a precios mayoristas y enviá tu pedido con el pago coordinado.</p></div></li><li><b>03</b><div><h3>Confirmamos y coordinamos</h3><p>Verificamos stock antes del pago. Cuando habilitemos tu pedido, podrás adjuntar el comprobante en tu cuenta.</p></div></li></ol></section>
<section class="faq"><p class="eyebrow blue">ANTES DE EMPEZAR</p><h2>Resolvemos tus dudas.</h2><details><summary>¿Por qué no puedo ver los precios?</summary><p>Los precios se muestran a clientes con cuenta mayorista aprobada. Registrate y esperá nuestra revisión.</p></details><details><summary>¿Cuándo tengo que pagar?</summary><p>No transfieras antes de que confirmemos el stock y el importe final. Te habilitaremos la carga del comprobante en tu cuenta.</p></details><details><summary>¿Puedo comprar por WhatsApp?</summary><p>Sí. Podés consultar por un producto o enviar el detalle de tu pedido para coordinar la compra.</p></details></section>
</main>
<script>
(function () {
    var hero = document.getElementById('hero');
    if (!hero) return;

    var banners = <?= json_encode($banners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    if (!banners || !banners.length) return;

    var total = banners.length;
    var DURATION = 7500;

    var imgA = document.getElementById('hero-img-a');
    var imgB = document.getElementById('hero-img-b');
    var titleEl = document.getElementById('hero-title');
    var eyebrowEl = document.getElementById('hero-eyebrow');
    var descEl = document.getElementById('hero-description');
    var btnPrimary = document.getElementById('hero-btn-primary');
    var btnPrimaryText = document.getElementById('hero-btn-primary-text');
    var btnSecondary = document.getElementById('hero-btn-secondary');
    var btnSecondaryText = document.getElementById('hero-btn-secondary-text');
    var numEl = document.getElementById('hero-number');
    var dotsEl = document.getElementById('hero-dots');
    var barEl = hero.querySelector('.hero-progress > span');

    var index = 0;
    var activeImg = imgA;
    var started = false;
    var animTimer = null;
    var autoTimer = null;
    var slideStart = 0;
    var remaining = DURATION;
    var paused = false;

    var reduceMotion = false;
    try { reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

    var rawTitle = (banners[0] && banners[0].title) || (titleEl ? titleEl.innerHTML : '');
    var canHover = false;
    try { canHover = window.matchMedia('(hover: hover)').matches; } catch (e) {}

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function now() { return (window.performance && performance.now) ? performance.now() : Date.now(); }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function nl2br(s) {
        return esc(s)
            .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
            .replace(/\r\n|\r|\n/g, '<br>');
    }

    /* Divide el titulo en lineas para revelarlas una por una */
    function renderTitle(html) {
        if (!titleEl) return;
        var parts = String(html == null ? '' : html).split(/<br\s*\/?>/i);
        var out = '';
        for (var i = 0; i < parts.length; i++) {
            out += '<span class="hero-line"><span class="hero-line-inner" style="--i:' + i + '">' + parts[i] + '</span></span>';
        }
        titleEl.innerHTML = out;
    }

    function renderCopy(b) {
        if (eyebrowEl && b.eyebrow !== undefined) eyebrowEl.textContent = b.eyebrow || '';
        renderTitle(b.title !== undefined ? b.title : rawTitle);
        if (descEl && b.description !== undefined) descEl.innerHTML = nl2br(b.description);

        if (btnPrimary && btnPrimaryText) {
            if (b.button_primary_text) {
                btnPrimary.style.display = '';
                btnPrimaryText.textContent = b.button_primary_text;
                btnPrimary.href = b.button_primary_link || 'catalogo.php';
            } else {
                btnPrimary.style.display = 'none';
            }
        }
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

    function updateChrome() {
        if (numEl) numEl.innerHTML = pad(index + 1) + '<small> / ' + pad(total) + '</small>';
        if (dotsEl) {
            for (var i = 0; i < dotsEl.children.length; i++) {
                var on = i === index;
                dotsEl.children[i].classList.toggle('is-active', on);
                dotsEl.children[i].setAttribute('aria-selected', on ? 'true' : 'false');
            }
        }
    }

    /* Cambia la imagen con crossfade + Ken Burns usando dos capas */
    function showImage(url, immediate) {
        if (!url || !imgA || !imgB) return;
        if (immediate) {
            if (imgA.getAttribute('src') !== url) imgA.src = url;
            imgA.classList.add('is-active');
            activeImg = imgA;
            return;
        }
        var incoming = (activeImg === imgA) ? imgB : imgA;
        var outgoing = activeImg;
        var go = function () {
            incoming.classList.add('is-active');
            outgoing.classList.remove('is-active');
            activeImg = incoming;
        };
        incoming.onload = go;
        incoming.onerror = go;
        incoming.src = url;
        if (incoming.complete && incoming.naturalWidth) go();
    }

    function preload(url) { if (url) { var im = new Image(); im.src = url; } }

    function restartProgress() {
        if (!barEl || total <= 1 || reduceMotion) return;
        hero.classList.remove('is-playing');
        void barEl.offsetWidth;
        hero.classList.add('is-playing');
    }

    function scheduleNext(ms) {
        window.clearTimeout(autoTimer);
        if (total <= 1 || reduceMotion) return;
        remaining = (typeof ms === 'number') ? ms : DURATION;
        slideStart = now();
        autoTimer = window.setTimeout(function () { goTo(index + 1); }, remaining);
    }

    function pauseAuto() {
        if (!started || total <= 1 || reduceMotion || paused) return;
        paused = true;
        window.clearTimeout(autoTimer);
        remaining -= (now() - slideStart);
        if (remaining < 800) remaining = 800;
        hero.classList.add('is-paused');
    }

    function resumeAuto() {
        if (total <= 1 || reduceMotion || !paused) return;
        paused = false;
        hero.classList.remove('is-paused');
        scheduleNext(remaining);
    }

    /* Reinicia la animacion de entrada de los textos */
    function playEnter() {
        hero.classList.remove('hero-anim');
        if (reduceMotion) return;
        void hero.offsetWidth;
        hero.classList.add('hero-anim');
    }

    function goTo(i) {
        if (total <= 1) return;
        i = ((i % total) + total) % total;
        if (i === index && started) { restartProgress(); scheduleNext(); return; }

        index = i;
        var b = banners[i];
        updateChrome();
        showImage(b.image_url);
        preload((banners[(i + 1) % total] || {}).image_url);

        window.clearTimeout(animTimer);

        if (reduceMotion) {
            renderCopy(b);
            restartProgress();
            scheduleNext();
            return;
        }

        /* Salida suave del texto actual y, al terminar, entrada del nuevo */
        hero.classList.remove('hero-anim');
        hero.classList.add('hero-leaving');
        animTimer = window.setTimeout(function () {
            renderCopy(b);
            hero.classList.remove('hero-leaving');
            playEnter();
        }, 260);

        restartProgress();
        scheduleNext();
    }

    function start() {
        if (started) return;
        started = true;
        renderTitle(rawTitle);
        showImage((banners[0] || {}).image_url, true);
        preload((banners[1] || {}).image_url);
        updateChrome();
        playEnter();
        restartProgress();
        scheduleNext();
    }

    /* Puntos de navegacion */
    if (dotsEl && total > 1) {
        for (var d = 0; d < total; d++) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'hero-dot';
            dot.setAttribute('role', 'tab');
            dot.setAttribute('aria-label', 'Ir a la diapositiva ' + (d + 1));
            dot.setAttribute('data-i', d);
            dotsEl.appendChild(dot);
        }
        dotsEl.addEventListener('click', function (e) {
            var t = e.target;
            while (t && t !== dotsEl && !t.classList.contains('hero-dot')) t = t.parentNode;
            if (t && t !== dotsEl) goTo(parseInt(t.getAttribute('data-i'), 10));
        });
    }

    /* Las flechas usan onclick="heroSlide(...)" en el HTML */
    window.heroSlide = function (dir) { goTo(index + (dir < 0 ? -1 : 1)); };

    /* Pausa al pasar el mouse (solo dispositivos con hover) */
    if (canHover) {
        hero.addEventListener('mouseenter', pauseAuto);
        hero.addEventListener('mouseleave', resumeAuto);
    }

    /* Swipe en tactil */
    var tx = 0, ty = 0;
    hero.addEventListener('touchstart', function (e) {
        var t = e.changedTouches[0]; tx = t.clientX; ty = t.clientY;
    }, { passive: true });
    hero.addEventListener('touchend', function (e) {
        var t = e.changedTouches[0];
        var dx = t.clientX - tx;
        var dy = t.clientY - ty;
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.4) {
            goTo(index + (dx < 0 ? 1 : -1));
        }
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { pauseAuto(); } else { resumeAuto(); }
    });

    /* Arranca cuando el overlay de carga termina, para que la entrada se vea */
    function boot() { try { start(); } catch (e) {} }
    if (document.getElementById('site-loader')) {
        document.addEventListener('site:loaderhidden', boot, { once: true });
        window.setTimeout(boot, 9000);
    } else if (document.readyState === 'complete') {
        window.setTimeout(boot, 0);
    } else {
        window.addEventListener('load', boot, { once: true });
    }
})();
</script>
<?php site_footer(); ?>
