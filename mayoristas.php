<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

site_header(
    'Mayoristas',
    'Comprá al por mayor en Serinsumos: insumos, papeles, tintas y maquinarias para sublimación y estampado con precios mayoristas, stock revisado, asesoramiento y envíos a todo el país.'
);

page_hero('Mayoristas', '<a href="' . e(site_url('index.php')) . '">Inicio</a> / Mayoristas', 'VENTA MAYORISTA');
?>

<style>
.may-page { max-width: 1150px; margin: 0 auto; padding: 0 36px; }

/* ── Intro: visual + texto ─────────────────────────────────────────── */
.may-intro {
    display: grid;
    grid-template-columns: 1fr 1.05fr;
    gap: 56px;
    align-items: center;
    margin: 70px 0 80px;
}
.may-visual {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    padding: 42px 34px;
    min-height: 350px;
    display: grid;
    place-items: center;
    align-content: center;
    text-align: center;
    background: linear-gradient(150deg, #172530 0%, #26333f 100%);
    color: #fff;
    box-shadow: 0 24px 50px rgba(23, 37, 48, .18);
}
.may-visual::before,
.may-visual::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    filter: blur(2px);
}
.may-visual::before {
    width: 260px; height: 260px;
    right: -80px; bottom: -90px;
    background: radial-gradient(circle at 35% 35%, rgba(237,50,133,.85), rgba(237,50,133,0) 70%);
}
.may-visual::after {
    width: 210px; height: 210px;
    left: -70px; top: -80px;
    background: radial-gradient(circle at 60% 60%, rgba(57,174,242,.8), rgba(57,174,242,0) 70%);
}
.may-visual img {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 240px;
    height: auto;
    background: #fff;
    border-radius: 16px;
    padding: 14px 16px;
}
.may-visual-title {
    position: relative;
    z-index: 1;
    margin: 22px 0 0;
    font-size: 15px;
    line-height: 1.6;
    color: #dce4eb;
}
.may-chips {
    position: relative;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
    margin-top: 22px;
}
.may-chips span {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .4px;
    padding: 7px 13px;
    border-radius: 20px;
    background: rgba(255, 255, 255, .1);
    border: 1px solid rgba(255, 255, 255, .18);
    color: #fff;
}
.may-copy h2 {
    font-size: clamp(28px, 3.4vw, 42px);
    line-height: 1.12;
    letter-spacing: -1px;
    margin: 12px 0 18px;
}
.may-copy p { color: #475569; font-size: 16px; line-height: 1.7; }
.may-copy ul {
    list-style: none;
    padding: 0;
    margin: 22px 0 28px;
    display: grid;
    gap: 12px;
}
.may-copy li {
    position: relative;
    padding-left: 32px;
    font-size: 15px;
    color: var(--ink);
}
.may-copy li::before {
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
.may-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}
.may-actions .btn { margin: 0; }

/* ── Beneficios ────────────────────────────────────────────────────── */
.may-benefits {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 80px;
}
.may-benefit {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 30px 26px;
    box-shadow: 0 8px 24px rgba(23, 37, 48, .04);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.may-benefit:hover {
    transform: translateY(-4px);
    border-color: #d5dde4;
    box-shadow: 0 16px 34px rgba(23, 37, 48, .09);
}
.may-benefit .icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    margin-bottom: 18px;
}
.may-benefit .icon svg { width: 30px; height: 30px; display: block; }
.may-benefit h3 { font-size: 17px; margin: 0 0 8px; }
.may-benefit p { margin: 0; color: var(--gray); font-size: 14px; line-height: 1.6; }

/* ── Cómo funciona ─────────────────────────────────────────────────── */
.may-steps {
    background: var(--ink);
    color: #fff;
    border-radius: 24px;
    padding: 56px 48px;
    margin-bottom: 80px;
}
.may-steps .eyebrow { color: var(--yellow); }
.may-steps h2 {
    font-size: clamp(26px, 3vw, 36px);
    letter-spacing: -1px;
    margin: 10px 0 34px;
}
.may-steps ol {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 34px;
}
.may-steps li {
    border-top: 2px solid rgba(255, 255, 255, .14);
    padding-top: 20px;
}
.may-steps b {
    display: block;
    font-family: Rubik, sans-serif;
    font-size: 30px;
    line-height: 1;
    color: var(--blue);
    margin-bottom: 12px;
}
.may-steps li:nth-child(2) b { color: var(--pink); }
.may-steps li:nth-child(3) b { color: var(--yellow); }
.may-steps h3 { font-size: 17px; margin: 0 0 8px; }
.may-steps p { color: #bbc6ce; font-size: 14px; line-height: 1.65; margin: 0; }

/* ── Qué nos ayuda a asesorarte ────────────────────────────────────── */
.may-req {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    align-items: center;
    margin-bottom: 80px;
}
.may-req h2 {
    font-size: clamp(24px, 3vw, 34px);
    letter-spacing: -1px;
    margin: 10px 0 16px;
}
.may-req p { color: #475569; font-size: 16px; line-height: 1.7; margin: 0; }
.may-req-card {
    background: #f6f8fa;
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 30px 28px;
}
.may-req-card h3 {
    font-size: 12px;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--gray);
    margin: 0 0 18px;
}
.may-req-card ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 14px; }
.may-req-card li {
    position: relative;
    padding-left: 34px;
    font-size: 15px;
    line-height: 1.55;
}
.may-req-card li::before {
    content: '✓';
    position: absolute;
    left: 0;
    top: 1px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--blue);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: grid;
    place-items: center;
}

/* ── Preguntas frecuentes ──────────────────────────────────────────── */
.may-faq { margin-top: 0; margin-bottom: 80px; }

/* ── CTA final ─────────────────────────────────────────────────────── */
.may-cta {
    text-align: center;
    background: #f6f8fa;
    border: 1px solid var(--line);
    border-radius: 22px;
    padding: 56px 30px;
    margin-bottom: 90px;
}
.may-cta h2 { font-size: clamp(24px, 3vw, 36px); letter-spacing: -1px; margin: 0 0 12px; }
.may-cta p { color: var(--gray); font-size: 16px; max-width: 620px; margin: 0 auto 26px; line-height: 1.6; }
.may-cta .actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
.may-cta .actions .btn { margin: 0; }

@media (max-width: 1000px) {
    .may-benefits { grid-template-columns: repeat(2, 1fr); }
    .may-steps ol { grid-template-columns: 1fr; gap: 24px; }
}
@media (max-width: 900px) {
    .may-page { padding: 0 22px; }
    .may-intro { grid-template-columns: 1fr; gap: 34px; margin: 44px 0 56px; }
    .may-req { grid-template-columns: 1fr; gap: 28px; }
    .may-steps { padding: 40px 24px; border-radius: 20px; }
    .may-cta { padding: 40px 22px; margin-bottom: 64px; }
}
@media (max-width: 560px) {
    .may-benefits { grid-template-columns: 1fr; gap: 16px; }
}
</style>

<main class="may-page">

    <!-- ── Presentación ─────────────────────────────────────────────── -->
    <section class="may-intro">
        <div class="may-visual">
            <img src="<?= e(site_logo()) ?>" alt="Serinsumos">
            <p class="may-visual-title">Tu cuenta mayorista,<br><b>lista para producir.</b></p>
            <div class="may-chips">
                <span>Talleres</span>
                <span>Gráficas</span>
                <span>Comercios</span>
                <span>Emprendedores</span>
            </div>
        </div>

        <div class="may-copy">
            <p class="eyebrow blue">COMPRÁ AL POR MAYOR</p>
            <h2>Precios mayoristas<br>para tu negocio.</h2>
            <p>En <b>Serinsumos</b> trabajamos con talleres, gráficas, comercios y emprendedores que reponen insumos todo el año. Con tu cuenta mayorista accedés a precios exclusivos y a un equipo que te ayuda a elegir lo que mejor rinde.</p>
            <p>Revisamos cada solicitud a mano, así que no vas a comprar nada que no podamos entregarte.</p>
            <ul>
                <li>Precios mayoristas en todo el catálogo</li>
                <li>Stock revisado antes de confirmar tu pedido</li>
                <li>Asesoramiento para elegir insumos y equipos</li>
                <li>Envíos a todo el país o retiro en el local</li>
            </ul>
            <div class="may-actions">
                <a class="btn yellow" href="<?= e(site_url('cuenta.php?registro=1')) ?>">Solicitar cuenta mayorista →</a>
                <a class="btn outline" href="<?= e(whatsapp_url('Hola Serinsumos, quisiera consultar por la venta mayorista.')) ?>" target="_blank" rel="noopener noreferrer">Consultar por WhatsApp</a>
            </div>
        </div>
    </section>

    <!-- ── Beneficios ───────────────────────────────────────────────── -->
    <section class="may-benefits">
        <article class="may-benefit">
            <div class="icon" style="background:#eff6ff">
                <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="18" fill="#39aef2"/><circle cx="17.5" cy="17.5" r="3.2" fill="#ffe72e"/><circle cx="30.5" cy="30.5" r="3.2" fill="#ffe72e"/><path d="M31 17 17 31" stroke="#fff" stroke-width="3.4" stroke-linecap="round"/></svg>
            </div>
            <h3>Precios mayoristas</h3>
            <p>Accedé a la lista de precios para clientes aprobados en todos los productos de la tienda.</p>
        </article>

        <article class="may-benefit">
            <div class="icon" style="background:#fffbeb">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 5 42 14v20L24 43 6 34V14L24 5Z" fill="#39aef2"/><path d="M6 14v20l18 9V23L6 14Z" fill="#2a93d0" opacity=".45"/><path d="M24 5 42 14 24 23 6 14 24 5Z" fill="#ffe72e"/><path d="M24 23v20" fill="none" stroke="#172530" stroke-width="2" opacity=".3"/></svg>
            </div>
            <h3>Stock revisado</h3>
            <p>Verificamos la disponibilidad física antes de confirmar cada pedido, para que no compres lo que no podemos entregar.</p>
        </article>

        <article class="may-benefit">
            <div class="icon" style="background:#fdf2f8">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M9 12h30v18a6 6 0 0 1-6 6H15a6 6 0 0 1-6-6V12Z" fill="#39aef2"/><path d="M15 12l3-6h12l3 6" fill="none" stroke="#39aef2" stroke-width="3" stroke-linejoin="round"/><circle cx="18" cy="22" r="2.4" fill="#fff"/><circle cx="30" cy="22" r="2.4" fill="#fff"/><path d="M18 29h12" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/></svg>
            </div>
            <h3>Atención personalizada</h3>
            <p>Te respondemos por WhatsApp o en el local, con recomendaciones según lo que producís.</p>
        </article>

        <article class="may-benefit">
            <div class="icon" style="background:#eff6ff">
                <svg viewBox="0 0 48 48" aria-hidden="true"><rect x="3" y="14" width="24" height="18" rx="2.5" fill="#ed3285"/><path d="M27 20h8l6 6v6h-14Z" fill="#ed3285" opacity=".75"/><circle cx="12" cy="35" r="4" fill="#172530"/><circle cx="34" cy="35" r="4" fill="#172530"/><rect x="8" y="18" width="7" height="6" rx="1" fill="#ffe72e"/></svg>
            </div>
            <h3>Envíos a todo el país</h3>
            <p>Despachamos por expresos y transportes, o coordinamos el retiro en el local cuando te queda más cómodo.</p>
        </article>
    </section>

    <!-- ── Cómo funciona ────────────────────────────────────────────── -->
    <section class="may-steps">
        <p class="eyebrow">PASO A PASO</p>
        <h2>Comprar al por mayor,<br>sin complicaciones.</h2>
        <ol>
            <li>
                <b>01</b>
                <h3>Registrá tu negocio</h3>
                <p>Completá tus datos en el registro mayorista. Revisamos y aprobamos tu cuenta manualmente.</p>
            </li>
            <li>
                <b>02</b>
                <h3>Armá tu pedido</h3>
                <p>Ya con la cuenta aprobada vas a ver los precios mayoristas. Cargá tu pedido desde la tienda o mandanos el detalle.</p>
            </li>
            <li>
                <b>03</b>
                <h3>Confirmamos y coordinamos</h3>
                <p>Verificamos stock e importe final antes del pago. Cuando habilitemos tu pedido, adjuntás el comprobante desde tu cuenta.</p>
            </li>
        </ol>
    </section>

    <!-- ── Qué nos ayuda a asesorarte ───────────────────────────────── -->
    <section class="may-req">
        <div>
            <p class="eyebrow blue">ANTES DE ARRANCAR</p>
            <h2>Contanos sobre tu negocio.</h2>
            <p>No hace falta que tengas todo resuelto: si nos contás cómo trabajás, te armamos una propuesta con los insumos y equipos que mejor se adaptan a tu producción.</p>
        </div>
        <div class="may-req-card">
            <h3>Nos ayuda saber</h3>
            <ul>
                <li>Tu rubro: sublimación, estampado, gráfica, marroquinería o regalería</li>
                <li>Si producís vos, revendés o hacés las dos cosas</li>
                <li>Tu zona, para calcular el envío y los tiempos de entrega</li>
                <li>Los insumos que necesitás reponer con más frecuencia</li>
            </ul>
        </div>
    </section>

    <!-- ── Preguntas frecuentes ─────────────────────────────────────── -->
    <section class="faq may-faq">
        <p class="eyebrow blue">ANTES DE EMPEZAR</p>
        <h2>Resolvemos tus dudas.</h2>
        <details>
            <summary>¿Cómo accedo a los precios mayoristas?</summary>
            <p>Registrá tu negocio desde el formulario de registro. Revisamos la solicitud manualmente y, cuando la aprobamos, vas a ver los precios en toda la tienda.</p>
        </details>
        <details>
            <summary>¿Puedo comprar sin tener cuenta?</summary>
            <p>Sí. Podés consultar por un producto o enviarnos el detalle de tu pedido por WhatsApp. Los precios mayoristas se muestran a los clientes con cuenta aprobada.</p>
        </details>
        <details>
            <summary>¿Cuándo tengo que pagar?</summary>
            <p>No transfieras antes de que confirmemos el stock y el importe final. Cuando habilitemos tu pedido, vas a poder cargar el comprobante desde tu cuenta.</p>
        </details>
        <details>
            <summary>¿Hacen envíos a todo el país?</summary>
            <p>Sí. Despachamos por expresos y transportes a todo el país y también podés retirar en el local de San Vicente, Córdoba.</p>
        </details>
        <details>
            <summary>¿Buscan insumos que no están en la tienda?</summary>
            <p>Escribinos con lo que necesitás. Si no lo tenemos publicado, lo buscamos y te avisamos disponibilidad y precio.</p>
        </details>
    </section>

    <!-- ── CTA final ────────────────────────────────────────────────── -->
    <section class="may-cta">
        <h2>¿Arrancamos con tu cuenta mayorista?</h2>
        <p>Registrate y empezá a ver precios pensados para tu negocio, con stock revisado y asesoramiento real.</p>
        <div class="actions">
            <a class="btn yellow" href="<?= e(site_url('cuenta.php?registro=1')) ?>">Solicitar cuenta mayorista →</a>
            <a class="btn outline" href="<?= e(site_url('catalogo.php')) ?>">Ver el catálogo</a>
        </div>
    </section>

</main>

<?php site_footer(); ?>
