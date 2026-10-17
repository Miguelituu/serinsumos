<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

site_header('Contacto y Ubicación', 'Contactate con Serinsumos. Visitá nuestro local en Córdoba o escribinos por WhatsApp para cotizaciones de insumos y equipos.');
?>

<style>
.contact-page {
    max-width: 1200px;
    margin: 40px auto 80px;
    padding: 0 24px;
}
.contact-header {
    text-align: center;
    margin-bottom: 48px;
}
.contact-header h1,
.contact-header h2 {
    font-size: clamp(32px, 4vw, 48px);
    margin: 10px 0 16px;
    letter-spacing: -1px;
}
.contact-header p {
    font-size: 16px;
    color: var(--gray);
    max-width: 620px;
    margin: 0 auto;
    line-height: 1.6;
}
.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 48px;
    align-items: start;
}
.contact-cards-col {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.contact-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 24px 26px;
    display: flex;
    gap: 20px;
    align-items: flex-start;
    box-shadow: 0 4px 14px rgba(23,37,48,0.03);
    transition: transform .2s ease, border-color .2s ease;
}
.contact-card:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
}
.contact-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #eff6ff;
    color: var(--blue);
    display: grid;
    place-items: center;
    font-size: 24px;
    flex-shrink: 0;
}
.contact-card-icon.wa {
    background: #f0fdf4;
    color: #16a34a;
}
.contact-card-icon.ig {
    background: #fdf2f8;
    color: var(--pink);
}
.contact-card-body h3 {
    font-size: 17px;
    margin: 0 0 4px;
    font-family: Rubik, sans-serif;
}
.contact-card-body p {
    margin: 0 0 8px;
    color: #475569;
    font-size: 14px;
    line-height: 1.5;
}
.contact-card-body a {
    font-size: 13px;
    font-weight: 600;
    color: var(--blue);
}
.contact-card-body a:hover {
    text-decoration: underline;
}

/* Formulario */
.contact-form-box {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 36px 32px;
    box-shadow: 0 10px 30px rgba(23,37,48,0.04);
}
.contact-form-box h2 {
    font-size: 24px;
    margin: 0 0 8px;
}
.contact-form-box p {
    color: var(--gray);
    font-size: 13px;
    margin-bottom: 24px;
}
.contact-form-box label {
    margin-bottom: 16px;
}

/* Sección de Mapa */
.map-section {
    margin-top: 60px;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(23,37,48,0.04);
}
.map-header {
    padding: 24px 28px;
    border-bottom: 1px solid var(--line);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    background: #f8fafc;
}
.map-header h3 {
    margin: 0 0 4px;
    font-size: 18px;
}
.map-header p {
    margin: 0;
    color: var(--gray);
    font-size: 13px;
}
.map-iframe-container {
    width: 100%;
    height: 420px;
    position: relative;
    background: #e2e8f0;
}
.map-iframe-container iframe {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}

@media (max-width: 900px) {
    .contact-grid {
        grid-template-columns: 1fr;
        gap: 36px;
    }
    .contact-form-box {
        padding: 24px 20px;
    }
    .map-iframe-container {
        height: 320px;
    }
}
</style>

<?php page_hero('Contacto y ubicación', '<a href="' . e(site_url('index.php')) . '">Inicio</a> / Contacto'); ?>

<main class="contact-page">

    <div class="contact-header">
        <p class="eyebrow blue">ATENCIÓN PERSONALIZADA</p>
        <h2>Estamos para ayudarte</h2>
        <p>¿Tenés dudas sobre insumos, compras mayoristas o querés retirar tu pedido personalmente? Contactanos por WhatsApp o visitanos en nuestro local en Córdoba.</p>
    </div>

    <div class="contact-grid">

        <!-- Columna de Datos de Contacto y Local -->
        <div class="contact-cards-col">

            <!-- Ubicación -->
            <div class="contact-card">
                <div class="contact-card-icon">📍</div>
                <div class="contact-card-body">
                    <h3>Nuestro Local</h3>
                    <p><b>Ramón Ocampo 1309</b><br>Barrio San Vicente, Córdoba, Argentina (CP 5006)</p>
                    <a href="https://maps.google.com/?q=Ram%C3%B3n+Ocampo+1309,+San+Vicente,+C%C3%B3rdoba,+Argentina" target="_blank" rel="noopener noreferrer">
                        Abrir en Google Maps ↗
                    </a>
                </div>
            </div>

            <!-- WhatsApp -->
            <div class="contact-card">
                <div class="contact-card-icon wa">💬</div>
                <div class="contact-card-body">
                    <h3>WhatsApp Comercial</h3>
                    <p>Atención directa para consultas técnicas, confirmación de pedidos y presupuestos mayoristas.</p>
                    <p style="font-weight:700;color:#166534">
                        <?= e(setting('whatsapp', DEFAULT_WHATSAPP) ?: '3518198163') ?>
                    </p>
                    <a href="<?= e(whatsapp_url('Hola Serinsumos, quisiera hacerles una consulta.')) ?>" target="_blank" rel="noopener noreferrer" style="color:#16a34a">
                        Chatear por WhatsApp ahora →
                    </a>
                </div>
            </div>

            <!-- Instagram -->
            <div class="contact-card">
                <div class="contact-card-icon ig">📷</div>
                <div class="contact-card-body">
                    <h3>Instagram Oficial</h3>
                    <p>Novedades, ingresos de insumos, tutoriales y videos de máquinas en funcionamiento.</p>
                    <a href="https://www.instagram.com/ser.insumos/" target="_blank" rel="noopener noreferrer" style="color:var(--pink)">
                        Seguinos en @ser.insumos →
                    </a>
                </div>
            </div>

            <!-- Horarios y Envíos -->
            <div class="contact-card">
                <div class="contact-card-icon" style="background:#fffbeb;color:#d97706">📦</div>
                <div class="contact-card-body">
                    <h3>Venta y Envíos</h3>
                    <p>Realizamos despachos a expresos y transportes a todo el país. Retiro en local previa coordinación del pedido.</p>
                </div>
            </div>

        </div>

        <!-- Columna de Formulario para Dudas directo a WhatsApp -->
        <div class="contact-form-box">
            <h2>Envianos tu consulta</h2>
            <p>Completá el formulario y se redactará automáticamente tu mensaje listo para enviar a nuestro WhatsApp oficial.</p>

            <form id="contact-page-form" onsubmit="submitContactForm(event)">
                <label>Nombre y Apellido *
                    <input id="cp-name" type="text" required placeholder="Ej: Marcela Gómez">
                </label>

                <label>Taller, Emprendimiento o Negocio (opcional)
                    <input id="cp-business" type="text" placeholder="Ej: Estampados Córdoba">
                </label>

                <label>Motivo de consulta
                    <select id="cp-topic">
                        <option value="Duda sobre insumos y papeles de sublimación">Duda sobre insumos y papeles de sublimación</option>
                        <option value="Consulta sobre maquinarias y prensas">Consulta sobre maquinarias y prensas</option>
                        <option value="Lista de precios y condiciones mayoristas">Lista de precios y condiciones mayoristas</option>
                        <option value="Disponibilidad o stock de productos">Disponibilidad o stock de productos</option>
                        <option value="Consulta sobre un pedido en curso">Consulta sobre un pedido en curso</option>
                        <option value="Otra consulta comercial o técnica">Otra consulta comercial o técnica</option>
                    </select>
                </label>

                <label>Mensaje o detalle de la consulta *
                    <textarea id="cp-message" rows="4" required placeholder="Escribí aquí tus dudas o los productos por los que te gustaría consultar..."></textarea>
                </label>

                <button class="btn yellow" type="submit" style="width:100%;justify-content:center;font-size:15px;margin-top:8px">
                    💬 Enviar consulta por WhatsApp
                </button>

                <small style="display:block;text-align:center;margin-top:12px;color:var(--gray)">
                    Te responderemos en el horario comercial a la brevedad.
                </small>
            </form>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         MAPA DE UBICACIÓN
    ═══════════════════════════════════════════════════════════════════ -->
    <section class="map-section">
        <div class="map-header">
            <div>
                <h3>📍 Ubicación de Serinsumos</h3>
                <p>Ramón Ocampo 1309 San Vicente, Córdoba, Argentina (CP 5006)</p>
            </div>
            <a class="btn outline btn-small"
               href="https://maps.google.com/?q=Ram%C3%B3n+Ocampo+1309,+San+Vicente,+C%C3%B3rdoba,+Argentina"
               target="_blank"
               rel="noopener noreferrer"
               style="margin:0">
                Cómo llegar en Google Maps ↗
            </a>
        </div>

        <div class="map-iframe-container">
            <iframe
                title="Mapa de ubicación Serinsumos"
                src="https://maps.google.com/maps?q=Ram%C3%B3n+Ocampo+1309,+San+Vicente,+C%C3%B3rdoba,+Argentina&t=&z=16&ie=UTF8&iwloc=&output=embed"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </section>

</main>

<script>
function submitContactForm(e) {
    e.preventDefault();
    const name     = document.getElementById('cp-name').value.trim();
    const business = document.getElementById('cp-business').value.trim();
    const topic    = document.getElementById('cp-topic').value;
    const msg      = document.getElementById('cp-message').value.trim();

    let text = '👋 Hola Serinsumos! Mi nombre es ' + name;
    if (business) text += ' (Negocio: ' + business + ')';
    text += '.\n\n📌 Motivo: ' + topic;
    text += '\n💬 Consulta:\n' + msg;

    const rawPhone = '<?= preg_replace('/\D+/', '', setting('whatsapp', DEFAULT_WHATSAPP)) ?>';
    const waBase = rawPhone ? 'https://wa.me/' + rawPhone : 'https://wa.me/5493518198163';
    const targetUrl = waBase + '?text=' + encodeURIComponent(text);

    window.open(targetUrl, '_blank');
}
</script>

<?php site_footer(); ?>
