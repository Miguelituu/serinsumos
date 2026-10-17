<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

site_header(
    'Sobre nosotros',
    'Conocé Serinsumos: insumos, tintas, sublimables y maquinarias para sublimación y estampado en Córdoba. Venta mayorista y asesoramiento para tu taller.'
);

page_hero('Sobre nosotros', '<a href="' . e(site_url('index.php')) . '">Inicio</a> / Nosotros', 'SERINSUMOS');
?>

<style>
.about-page { max-width: 1150px; margin: 0 auto; padding: 0 36px; }

/* ── Intro: visual + texto ─────────────────────────────────────────── */
.about-intro {
    display: grid;
    grid-template-columns: 1fr 1.05fr;
    gap: 60px;
    align-items: center;
    margin: 70px 0 80px;
}
.about-visual {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    padding: 48px 40px;
    min-height: 340px;
    display: grid;
    place-items: center;
    background: linear-gradient(150deg, #172530 0%, #26333f 100%);
    color: #fff;
    box-shadow: 0 24px 50px rgba(23, 37, 48, .18);
}
.about-visual::before,
.about-visual::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    filter: blur(2px);
}
.about-visual::before {
    width: 260px; height: 260px;
    right: -80px; bottom: -90px;
    background: radial-gradient(circle at 35% 35%, rgba(237,50,133,.85), rgba(237,50,133,0) 70%);
}
.about-visual::after {
    width: 210px; height: 210px;
    left: -70px; top: -80px;
    background: radial-gradient(circle at 60% 60%, rgba(57,174,242,.8), rgba(57,174,242,0) 70%);
}
.about-visual img {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 250px;
    height: auto;
    background: #fff;
    border-radius: 16px;
    padding: 14px 16px;
}
.about-chips {
    position: relative;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
    margin-top: 26px;
}
.about-chips span {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .4px;
    padding: 7px 13px;
    border-radius: 20px;
    background: rgba(255, 255, 255, .1);
    border: 1px solid rgba(255, 255, 255, .18);
    color: #fff;
}
.about-copy h2 {
    font-size: clamp(28px, 3.4vw, 42px);
    line-height: 1.12;
    letter-spacing: -1px;
    margin: 12px 0 18px;
}
.about-copy p { color: #475569; font-size: 16px; line-height: 1.7; }
.about-copy ul {
    list-style: none;
    padding: 0;
    margin: 22px 0 28px;
    display: grid;
    gap: 12px;
}
.about-copy li {
    position: relative;
    padding-left: 32px;
    font-size: 15px;
    color: var(--ink);
}
.about-copy li::before {
    content: '✓';
    position: absolute;
    left: 0;
    top: -1px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--yellow);
    color: var(--ink);
    font-size: 13px;
    font-weight: 700;
    display: grid;
    place-items: center;
}

/* ── Valores ───────────────────────────────────────────────────────── */
.about-values {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 80px;
}
.about-value {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 32px 28px;
    box-shadow: 0 8px 24px rgba(23, 37, 48, .04);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.about-value:hover {
    transform: translateY(-4px);
    border-color: #d5dde4;
    box-shadow: 0 16px 34px rgba(23, 37, 48, .09);
}
.about-value .icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    margin-bottom: 18px;
}
.about-value .icon svg { width: 30px; height: 30px; display: block; }
.about-value h3 { font-size: 18px; margin: 0 0 8px; }
.about-value p { margin: 0; color: var(--gray); font-size: 14px; line-height: 1.6; }

/* ── Cifras ────────────────────────────────────────────────────────── */
.about-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    background: var(--ink);
    color: #fff;
    border-radius: 22px;
    padding: 44px 30px;
    margin-bottom: 80px;
    text-align: center;
}
.about-stats b {
    display: block;
    font-family: Rubik, sans-serif;
    font-size: clamp(26px, 3.4vw, 40px);
    letter-spacing: -1px;
    line-height: 1;
}
.about-stats span {
    display: block;
    margin-top: 10px;
    font-size: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #a3b0ba;
}
.about-stats .c-blue b { color: var(--blue); }
.about-stats .c-pink b { color: var(--pink); }
.about-stats .c-yellow b { color: var(--yellow); }

/* ── CTA final ─────────────────────────────────────────────────────── */
.about-cta {
    text-align: center;
    background: #f6f8fa;
    border: 1px solid var(--line);
    border-radius: 22px;
    padding: 56px 30px;
    margin-bottom: 90px;
}
.about-cta h2 { font-size: clamp(24px, 3vw, 36px); letter-spacing: -1px; margin: 0 0 12px; }
.about-cta p { color: var(--gray); font-size: 16px; max-width: 620px; margin: 0 auto 26px; line-height: 1.6; }
.about-cta .actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }

@media (max-width: 900px) {
    .about-page { padding: 0 22px; }
    .about-intro { grid-template-columns: 1fr; gap: 36px; margin: 44px 0 56px; }
    .about-values { grid-template-columns: 1fr; gap: 16px; }
    .about-stats { grid-template-columns: repeat(2, 1fr); gap: 30px 8px; }
    .about-cta { padding: 40px 22px; }
}
</style>

<main class="about-page">

    <!-- ── Presentación ─────────────────────────────────────────────── -->
    <section class="about-intro">
        <div class="about-visual">
            <img src="<?= e(site_logo()) ?>" alt="Serinsumos">
            <div class="about-chips">
                <span>Sublimación</span>
                <span>Estampado</span>
                <span>Tintas y papeles</span>
                <span>Maquinarias</span>
            </div>
        </div>

        <div class="about-copy">
            <p class="eyebrow blue">QUIÉNES SOMOS</p>
            <h2>Más que insumos,<br>un aliado para tu taller.</h2>
            <p>En <b>Serinsumos</b> acompañamos a emprendedores, talleres y comercios que viven del color: desde el primer emprendimiento hasta negocios que producen a escala. Somos de <b>San Vicente, Córdoba</b>, y despachamos a todo el país.</p>
            <p>Elegimos cada producto pensando en la calidad del resultado y en el rendimiento de tu producción, con precios mayoristas y asesoramiento real para que no pierdas tiempo ni material.</p>
            <ul>
                <li>Atención personalizada por WhatsApp</li>
                <li>Stock revisado antes de confirmar tu pedido</li>
                <li>Asesoramiento para elegir equipos e insumos</li>
            </ul>
            <a class="btn yellow" href="<?= e(site_url('catalogo.php')) ?>">Ver el catálogo →</a>
        </div>
    </section>

    <!-- ── Valores ──────────────────────────────────────────────────── -->
    <section class="about-values">
        <article class="about-value">
            <div class="icon" style="background:#eff6ff">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M9 12h30v18a6 6 0 0 1-6 6H15a6 6 0 0 1-6-6V12Z" fill="#39aef2"/><path d="M15 12l3-6h12l3 6" fill="none" stroke="#39aef2" stroke-width="3" stroke-linejoin="round"/><circle cx="18" cy="22" r="2.4" fill="#fff"/><circle cx="30" cy="22" r="2.4" fill="#fff"/><path d="M18 29h12" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/></svg>
            </div>
            <h3>Atención personalizada</h3>
            <p>Te respondemos de verdad, por WhatsApp o en el local, con recomendaciones según lo que necesitás producir.</p>
        </article>

        <article class="about-value">
            <div class="icon" style="background:#fffbeb">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 5 42 14v20L24 43 6 34V14L24 5Z" fill="#39aef2"/><path d="M6 14v20l18 9V23L6 14Z" fill="#2a93d0" opacity=".45"/><path d="M24 5 42 14 24 23 6 14 24 5Z" fill="#ffe72e"/><path d="M24 23v20" fill="none" stroke="#172530" stroke-width="2" opacity=".3"/></svg>
            </div>
            <h3>Stock revisado</h3>
            <p>Verificamos la disponibilidad física antes de confirmar cada pedido, para que no compres lo que no podemos entregar.</p>
        </article>

        <article class="about-value">
            <div class="icon" style="background:#fdf2f8">
                <svg viewBox="0 0 48 48" aria-hidden="true"><rect x="3" y="14" width="24" height="18" rx="2.5" fill="#ed3285"/><path d="M27 20h8l6 6v6h-14Z" fill="#ed3285" opacity=".75"/><circle cx="12" cy="35" r="4" fill="#172530"/><circle cx="34" cy="35" r="4" fill="#172530"/><rect x="8" y="18" width="7" height="6" rx="1" fill="#ffe72e"/></svg>
            </div>
            <h3>Envíos a todo el país</h3>
            <p>Despachamos por expresos y transportes, o coordinamos el retiro en el local cuando te queda más cómodo.</p>
        </article>
    </section>

    <!-- ── Cifras ───────────────────────────────────────────────────── -->
    <section class="about-stats">
        <div class="c-blue"><b>+150</b><span>Productos</span></div>
        <div class="c-pink"><b>+12</b><span>Categorías</span></div>
        <div class="c-yellow"><b>24 h</b><span>Respuesta</span></div>
        <div><b>AR</b><span>Envíos nacionales</span></div>
    </section>

    <!-- ── CTA ──────────────────────────────────────────────────────── -->
    <section class="about-cta">
        <h2>¿Arrancamos con tu proyecto?</h2>
        <p>Creá tu cuenta mayorista para acceder a precios exclusivos, o escribinos y te ayudamos a elegir lo que necesitás.</p>
        <div class="actions">
            <a class="btn yellow" href="<?= e(site_url('cuenta.php?registro=1')) ?>">Solicitar cuenta mayorista →</a>
            <a class="btn outline" href="<?= e(whatsapp_url('Hola Serinsumos, quisiera asesoramiento para mi proyecto.')) ?>" target="_blank" rel="noopener noreferrer">Hablar por WhatsApp</a>
        </div>
    </section>

</main>

<?php site_footer(); ?>
