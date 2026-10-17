<?php
/**
 * Página de error de JP Preparation — UN solo diseño para todos los errores.
 *
 * Standalone a propósito: no usa el layout de la app ni dependencias de sesión,
 * assets o CDN (todo el CSS va incrustado), así que se ve igual aunque el error
 * sea de la propia plataforma. Mismo lenguaje visual que el login (cristal
 * oscuro sobre retícula con halos).
 *
 * Entrada (todas opcionales salvo code/title):
 *   $variant   notfound | forbidden | bad | error | session | maintenance
 *   $code      '404'…                 $kicker   etiqueta corta sobre el título
 *   $title     titular                $message  explicación (una o dos frases)
 *   $ref       referencia del error   $role     rol actual (403)
 *   $reload    true → botón «Reintentar»
 *   $report    ['origin' => 'error'|'permiso', 'msg' => '…'] → botón «Reportar» (solo con sesión)
 *   $altUrl / $altLabel   acción extra (p. ej. «Ir a la revisión de clases»)
 */
helper('url');

$variant = $variant ?? 'error';
$code    = $code    ?? '';
$kicker  = $kicker  ?? '';
$title   = $title   ?? 'Algo ha fallado';
$message = $message ?? '';
$ref     = $ref     ?? null;
$role    = $role    ?? null;
$reload  = !empty($reload);
$report  = $report  ?? null;
$altUrl  = $altUrl  ?? null;
$altLabel = $altLabel ?? '';

try {
    $loggedIn = (bool) session('isLoggedIn');
} catch (\Throwable $e) {
    $loggedIn = false;
}
$homeUrl   = base_url($loggedIn ? 'dashboard' : 'login');
$homeLabel = $loggedIn ? 'Volver al inicio' : 'Ir a iniciar sesión';
if ($variant === 'session') {
    $homeUrl   = base_url('login');
    $homeLabel = 'Iniciar sesión';
}

// Acento y dibujo por tipo de error (SVG propio, sin librerías de iconos)
$accents = [
    'notfound'    => ['#60a5fa', '96,165,250'],
    'forbidden'   => ['#f87171', '248,113,113'],
    'bad'         => ['#fbbf24', '251,191,36'],
    'error'       => ['#fb923c', '251,146,60'],
    'session'     => ['#a78bfa', '167,139,250'],
    'maintenance' => ['#34d399', '52,211,153'],
];
[$accent, $accentRgb] = $accents[$variant] ?? $accents['error'];

$icons = [
    // Balón que rueda fuera del campo
    'notfound' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="ico-ball"><circle cx="32" cy="32" r="22"/><path d="M32 22l9 6.5-3.5 10.5h-11L23 28.5z"/><path d="M32 22V10M41 28.5l11-3.6M37.5 39l7 9.4M26.5 39l-7 9.4M23 28.5L12 24.9"/></svg>',
    // Tarjeta roja
    'forbidden' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="ico-card"><rect x="18" y="9" width="28" height="42" rx="4" fill="currentColor" fill-opacity=".18"/><path d="M26 30v-4a6 6 0 0 1 12 0v4"/><rect x="24" y="30" width="16" height="12" rx="2.5"/><path d="M32 34.5v3.2"/></svg>',
    // Tarjeta amarilla / aviso
    'bad' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="ico-card"><rect x="18" y="9" width="28" height="42" rx="4" fill="currentColor" fill-opacity=".18"/><path d="M32 22v14M32 42.5v.5"/></svg>',
    // Botiquín: algo ha fallado de nuestra parte
    'error' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="ico-pulse"><rect x="10" y="18" width="44" height="32" rx="6" fill="currentColor" fill-opacity=".14"/><path d="M24 18v-4a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v4M32 28v14M25 35h14"/></svg>',
    // Reloj: tiempo cumplido
    'session' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="32" cy="34" r="21"/><path d="M26 8h12M32 8v5"/><path class="hand-min" d="M32 34V21"/><path class="hand-hour" d="M32 34l8 5"/></svg>',
    // Llave inglesa: mantenimiento
    'maintenance' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="ico-wrench"><path d="M42 13a11 11 0 0 0-10.4 14.6L12 47.2a4.2 4.2 0 0 0 5.8 5.8l19.6-19.6A11 11 0 0 0 51 22l-7 7-6-1-1-6 7-7a11 11 0 0 0-2-2z" fill="currentColor" fill-opacity=".14"/></svg>',
];
$icon = $icons[$variant] ?? $icons['error'];

$reportUrl = null;
if ($report && $loggedIn) {
    $reportUrl = base_url('tickets/create') . '?origin=' . rawurlencode($report['origin'] ?? 'error')
        . ($ref ? '&ref=' . rawurlencode((string) $ref) : '')
        . '&msg=' . rawurlencode((string) ($report['msg'] ?? $title));
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#060a10">
    <title><?= esc(trim($code . ' ' . $title)) ?> — JP Preparation</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚽</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Oswald:wght@600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after { box-sizing: border-box; }
        :root { --ac: <?= $accent ?>; --ac-rgb: <?= $accentRgb ?>; }
        html, body { margin: 0; min-height: 100%; }
        body {
            background: #060a10; color: #e6edf8;
            font-family: 'Montserrat', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .stage {
            position: relative; min-height: 100vh; min-height: 100dvh; overflow: hidden;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: max(32px, env(safe-area-inset-top)) 20px max(24px, env(safe-area-inset-bottom));
        }
        .layer { position: absolute; inset: 0; pointer-events: none; }
        .grid {
            background:
                linear-gradient(rgba(59,130,246,.13) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59,130,246,.13) 1px, transparent 1px);
            background-size: 60px 60px;
            -webkit-mask-image: radial-gradient(ellipse at 50% 40%, #000 20%, transparent 75%);
                    mask-image: radial-gradient(ellipse at 50% 40%, #000 20%, transparent 75%);
        }
        .glow-a { background: radial-gradient(circle at 50% 36%, rgba(var(--ac-rgb), .42) 0%, transparent 52%); filter: blur(120px); }
        .glow-b { background: radial-gradient(circle at 18% 86%, rgba(132,204,22,.22) 0%, transparent 44%); filter: blur(110px); }

        .card {
            position: relative; z-index: 1; width: 100%; max-width: 520px;
            padding: 38px 34px 30px; text-align: center;
            border-radius: 32px;
            background: rgba(15,26,43,.55);
            border: 1px solid rgba(148,178,255,.2);
            -webkit-backdrop-filter: blur(26px); backdrop-filter: blur(26px);
            box-shadow: 0 40px 80px -20px rgba(0,0,0,.6), 0 1px 0 rgba(255,255,255,.07) inset;
            animation: rise .6s cubic-bezier(.16,1,.3,1) both;
        }
        .brand { display: inline-flex; flex-direction: column; align-items: center; text-decoration: none; margin-bottom: 18px; }
        .brand img { width: 92px; height: auto; display: block; filter: drop-shadow(0 0 14px rgba(59,130,246,.5)); }
        .brand span { margin-top: 4px; font: 700 12px 'Oswald', sans-serif; letter-spacing: .18em; text-transform: uppercase; color: #cdd8ec; }

        .badge {
            width: 92px; height: 92px; margin: 4px auto 6px; border-radius: 50%;
            display: grid; place-items: center; color: var(--ac);
            background: radial-gradient(circle at 50% 35%, rgba(var(--ac-rgb), .22), rgba(var(--ac-rgb), .05) 70%);
            border: 1px solid rgba(var(--ac-rgb), .35);
            box-shadow: 0 0 40px -4px rgba(var(--ac-rgb), .45);
            animation: float 4.2s ease-in-out infinite;
        }
        .badge svg { width: 54px; height: 54px; overflow: visible; }

        .code {
            margin: 6px 0 0; font: 700 clamp(64px, 17vw, 96px)/1 'Oswald', sans-serif; letter-spacing: .04em;
            background: linear-gradient(180deg, #ffffff 10%, var(--ac) 120%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            -webkit-text-fill-color: transparent;
            text-shadow: none;
        }
        .kicker {
            display: inline-block; margin: 8px 0 0; padding: 4px 12px; border-radius: 999px;
            font-size: 10.5px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
            color: var(--ac); background: rgba(var(--ac-rgb), .12); border: 1px solid rgba(var(--ac-rgb), .3);
        }
        h1 { margin: 14px 0 8px; font-size: clamp(20px, 5vw, 24px); font-weight: 700; color: #f4f7fd; line-height: 1.25; }
        p.msg { margin: 0 auto; max-width: 400px; color: #9fb0cc; font-size: 14.5px; line-height: 1.65; }

        .chips { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 16px 0 0; }
        .chip { padding: 5px 12px; border-radius: 10px; font-size: 12px; color: #b9c6dd; background: rgba(255,255,255,.06); border: 1px solid rgba(148,178,255,.18); }
        .chip b { color: #f4f7fd; font-weight: 700; }
        .chip.mono { font-family: ui-monospace, Menlo, Consolas, monospace; letter-spacing: .08em; }

        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 26px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 0 22px;
            border-radius: 14px; border: 1px solid transparent; cursor: pointer; text-decoration: none;
            font: 700 13.5px 'Montserrat', system-ui, sans-serif; letter-spacing: .02em;
            transition: transform .12s ease, filter .12s ease, background .15s ease, border-color .15s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
        .btn.primary { color: #fff; background: linear-gradient(135deg, #5b9dff, #1d4ed8); box-shadow: 0 10px 20px -8px rgba(29,78,216,.7); }
        .btn.primary:hover { filter: brightness(1.08); }
        .btn.ghost { color: #dbe5f6; background: rgba(255,255,255,.05); border-color: rgba(148,178,255,.25); }
        .btn.ghost:hover { background: rgba(255,255,255,.1); }
        .btn.warn { color: #fff; background: linear-gradient(135deg, #fb923c, #ea580c); box-shadow: 0 10px 20px -8px rgba(234,88,12,.6); }
        .btn svg { width: 16px; height: 16px; flex: none; }

        .foot { position: relative; z-index: 1; margin-top: 22px; font-size: 11.5px; font-weight: 500; color: #7f93b4; text-align: center; }
        .foot span + span::before { content: '·'; margin: 0 8px; }

        /* Animaciones de cada dibujo */
        .ico-ball { animation: roll 5s linear infinite; transform-origin: 50% 50%; }
        .ico-card { animation: tilt 3s ease-in-out infinite; transform-origin: 50% 80%; }
        .ico-pulse { animation: pulse 1.8s ease-in-out infinite; transform-origin: 50% 50%; }
        .ico-wrench { animation: wrench 2.6s ease-in-out infinite; transform-origin: 30% 70%; }
        .hand-min  { animation: spin 6s linear infinite; transform-origin: 32px 34px; }
        .hand-hour { animation: spin 36s linear infinite; transform-origin: 32px 34px; }
        @keyframes rise  { from { opacity: 0; transform: translateY(16px) scale(.98); } to { opacity: 1; transform: none; } }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        @keyframes roll  { to { transform: rotate(360deg); } }
        @keyframes tilt  { 0%,100% { transform: rotate(-7deg); } 50% { transform: rotate(7deg); } }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        @keyframes wrench{ 0%,100% { transform: rotate(-10deg); } 50% { transform: rotate(14deg); } }
        @keyframes spin  { to { transform: rotate(360deg); } }

        @media (max-width: 480px) {
            .card { padding: 30px 22px 24px; border-radius: 26px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
        @media (forced-colors: active) {
            .code { background: none; color: CanvasText; -webkit-text-fill-color: CanvasText; }
            .card { border: 1px solid CanvasText; }
        }
    </style>
</head>
<body>
<main class="stage" role="main">
    <div class="layer grid" aria-hidden="true"></div>
    <div class="layer glow-a" aria-hidden="true"></div>
    <div class="layer glow-b" aria-hidden="true"></div>

    <section class="card" aria-labelledby="err-title">
        <a class="brand" href="<?= esc($homeUrl, 'attr') ?>" aria-label="JP Preparation">
            <img src="<?= esc(base_url('assets/img/logo-jp-preparation.webp'), 'attr') ?>" alt="" width="92" height="70">
            <span>JP Preparation</span>
        </a>

        <div class="badge" aria-hidden="true"><?= $icon ?></div>
        <?php if ($code !== ''): ?><p class="code" aria-hidden="true"><?= esc($code) ?></p><?php endif; ?>
        <?php if ($kicker !== ''): ?><span class="kicker"><?= esc($kicker) ?></span><?php endif; ?>

        <h1 id="err-title"><?= esc($title) ?></h1>
        <?php if ($message !== ''): ?><p class="msg"><?= esc($message) ?></p><?php endif; ?>
        <?php if (!empty($detail)): // solo desarrollo: el mensaje técnico de la excepción ?>
        <p class="msg" style="margin-top:10px;font:12px ui-monospace,Menlo,Consolas,monospace;color:#7f93b4;word-break:break-word"><?= nl2br(esc((string) $detail)) ?></p>
        <?php endif; ?>

        <?php if ($ref || $role): ?>
        <div class="chips">
            <?php if ($role): ?><span class="chip">Tu rol: <b><?= esc($role) ?></b></span><?php endif; ?>
            <?php if ($ref): ?><span class="chip mono" title="Referencia del error">Ref: <b><?= esc($ref) ?></b></span><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="actions">
            <?php if ($reload): ?>
            <button type="button" class="btn warn" onclick="location.reload()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.3L21 8"/><path d="M21 3v5h-5"/></svg>Reintentar
            </button>
            <?php endif; ?>
            <a class="btn primary" href="<?= esc($homeUrl, 'attr') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg><?= esc($homeLabel) ?>
            </a>
            <?php if ($altUrl): ?>
            <a class="btn ghost" href="<?= esc($altUrl, 'attr') ?>"><?= esc($altLabel ?: 'Continuar') ?></a>
            <?php endif; ?>
            <button type="button" class="btn ghost" id="err-back" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>Volver atrás
            </button>
            <?php if ($reportUrl): ?>
            <a class="btn ghost" id="err-report" href="<?= esc($reportUrl, 'attr') ?>">Reportar este problema</a>
            <?php endif; ?>
        </div>
    </section>

    <footer class="foot"><span>Sant Vicenç dels Horts · Barcelona</span><span>© <?= date('Y') ?> JP Preparation</span></footer>
</main>

<script>
(function () {
    // «Volver atrás» solo si hay a dónde volver (misma web)
    var back = document.getElementById('err-back');
    if (back && history.length > 1 && document.referrer && document.referrer.indexOf(location.host) !== -1) {
        back.hidden = false;
        back.addEventListener('click', function () { history.back(); });
    }
    // El reporte lleva la página de la que venía el usuario
    var rep = document.getElementById('err-report');
    if (rep) {
        rep.href += '&url=' + encodeURIComponent(document.referrer || location.href);
    }
})();
</script>
</body>
</html>
