<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_mayoristas.php';

$mayoristas = mayoristas_content();

site_header(
    'Mayoristas',
    $mayoristas['intro_one']
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
            <p class="may-visual-title"><?= nl2br(e($mayoristas['visual_title'])) ?></p>
            <div class="may-chips">
                <?php foreach ($mayoristas['chips'] as $chip): ?>
                    <?php if (trim((string)$chip) !== ''): ?><span><?= e($chip) ?></span><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="may-copy">
            <p class="eyebrow blue"><?= e($mayoristas['eyebrow']) ?></p>
            <h2><?= nl2br(e($mayoristas['title'])) ?></h2>
            <p><?= nl2br(e($mayoristas['intro_one'])) ?></p>
            <p><?= nl2br(e($mayoristas['intro_two'])) ?></p>
            <ul>
                <?php foreach ($mayoristas['bullets'] as $bullet): ?>
                    <?php if (trim((string)$bullet) !== ''): ?><li><?= e($bullet) ?></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="may-actions">
                <a class="btn yellow" href="<?= e(site_url('cuenta.php?registro=1')) ?>"><?= e($mayoristas['register_button']) ?></a>
                <a class="btn outline" href="<?= e(whatsapp_url('Hola Serinsumos, quisiera consultar por la venta mayorista.')) ?>" target="_blank" rel="noopener noreferrer"><?= e($mayoristas['whatsapp_button']) ?></a>
            </div>
        </div>
    </section>

    <!-- ── Beneficios ───────────────────────────────────────────────── -->
    <section class="may-benefits">
        <article class="may-benefit">
            <div class="icon" style="background:#eff6ff">
                <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="18" fill="#39aef2"/><circle cx="17.5" cy="17.5" r="3.2" fill="#ffe72e"/><circle cx="30.5" cy="30.5" r="3.2" fill="#ffe72e"/><path d="M31 17 17 31" stroke="#fff" stroke-width="3.4" stroke-linecap="round"/></svg>
            </div>
            <h3><?= e($mayoristas['benefits'][0]['title']) ?></h3>
            <p><?= nl2br(e($mayoristas['benefits'][0]['text'])) ?></p>
        </article>

        <article class="may-benefit">
            <div class="icon" style="background:#fffbeb">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 5 42 14v20L24 43 6 34V14L24 5Z" fill="#39aef2"/><path d="M6 14v20l18 9V23L6 14Z" fill="#2a93d0" opacity=".45"/><path d="M24 5 42 14 24 23 6 14 24 5Z" fill="#ffe72e"/><path d="M24 23v20" fill="none" stroke="#172530" stroke-width="2" opacity=".3"/></svg>
            </div>
            <h3><?= e($mayoristas['benefits'][1]['title']) ?></h3>
            <p><?= nl2br(e($mayoristas['benefits'][1]['text'])) ?></p>
        </article>

        <article class="may-benefit">
            <div class="icon" style="background:#fdf2f8">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M9 12h30v18a6 6 0 0 1-6 6H15a6 6 0 0 1-6-6V12Z" fill="#39aef2"/><path d="M15 12l3-6h12l3 6" fill="none" stroke="#39aef2" stroke-width="3" stroke-linejoin="round"/><circle cx="18" cy="22" r="2.4" fill="#fff"/><circle cx="30" cy="22" r="2.4" fill="#fff"/><path d="M18 29h12" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/></svg>
            </div>
            <h3><?= e($mayoristas['benefits'][2]['title']) ?></h3>
            <p><?= nl2br(e($mayoristas['benefits'][2]['text'])) ?></p>
        </article>

        <article class="may-benefit">
            <div class="icon" style="background:#eff6ff">
                <svg viewBox="0 0 48 48" aria-hidden="true"><rect x="3" y="14" width="24" height="18" rx="2.5" fill="#ed3285"/><path d="M27 20h8l6 6v6h-14Z" fill="#ed3285" opacity=".75"/><circle cx="12" cy="35" r="4" fill="#172530"/><circle cx="34" cy="35" r="4" fill="#172530"/><rect x="8" y="18" width="7" height="6" rx="1" fill="#ffe72e"/></svg>
            </div>
            <h3><?= e($mayoristas['benefits'][3]['title']) ?></h3>
            <p><?= nl2br(e($mayoristas['benefits'][3]['text'])) ?></p>
        </article>
    </section>

    <!-- ── Cómo funciona ────────────────────────────────────────────── -->
    <section class="may-steps">
        <p class="eyebrow"><?= e($mayoristas['steps_eyebrow']) ?></p>
        <h2><?= nl2br(e($mayoristas['steps_title'])) ?></h2>
        <ol>
            <?php foreach ($mayoristas['steps'] as $i => $step): ?>
                <li>
                    <b><?= e(str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)) ?></b>
                    <h3><?= e($step['title']) ?></h3>
                    <p><?= nl2br(e($step['text'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <!-- ── Qué nos ayuda a asesorarte ───────────────────────────────── -->
    <section class="may-req">
        <div>
            <p class="eyebrow blue"><?= e($mayoristas['request_eyebrow']) ?></p>
            <h2><?= e($mayoristas['request_title']) ?></h2>
            <p><?= nl2br(e($mayoristas['request_body'])) ?></p>
        </div>
        <div class="may-req-card">
            <h3><?= e($mayoristas['request_card_title']) ?></h3>
            <ul>
                <?php foreach ($mayoristas['request_list'] as $item): ?>
                    <?php if (trim((string)$item) !== ''): ?><li><?= e($item) ?></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <!-- ── Preguntas frecuentes ─────────────────────────────────────── -->
    <section class="faq may-faq">
        <p class="eyebrow blue"><?= e($mayoristas['faq_eyebrow']) ?></p>
        <h2><?= e($mayoristas['faq_title']) ?></h2>
        <?php foreach ($mayoristas['faqs'] as $faq): ?>
            <?php if (trim((string)$faq['question']) !== '' || trim((string)$faq['answer']) !== ''): ?>
                <details>
                    <summary><?= e($faq['question']) ?></summary>
                    <p><?= nl2br(e($faq['answer'])) ?></p>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <!-- ── CTA final ────────────────────────────────────────────────── -->
    <section class="may-cta">
        <h2><?= e($mayoristas['cta_title']) ?></h2>
        <p><?= nl2br(e($mayoristas['cta_body'])) ?></p>
        <div class="actions">
            <a class="btn yellow" href="<?= e(site_url('cuenta.php?registro=1')) ?>"><?= e($mayoristas['cta_register_button']) ?></a>
            <a class="btn outline" href="<?= e(site_url('catalogo.php')) ?>"><?= e($mayoristas['cta_catalog_button']) ?></a>
        </div>
    </section>

</main>

<?php site_footer(); ?>
