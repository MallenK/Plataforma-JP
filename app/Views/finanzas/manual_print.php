<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manual de Finanzas — JP Preparation</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4; margin: 18mm 16mm 20mm; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Montserrat', 'Segoe UI', system-ui, sans-serif; background: #fff; color: #1e293b; }
.fm-page { max-width: 900px; margin: 0 auto; padding: 24px; }
.fm-cover { min-height: 250mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; break-after: page; padding: 0 8mm; }
.fm-cover .k { font-size: 13px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: #1d4ed8; }
.fm-cover h1 { font-size: 46px; line-height: 1.1; font-weight: 800; color: #0f172a; margin: 14px 0 18px; }
.fm-cover p { font-size: 17px; color: #475569; max-width: 520px; line-height: 1.6; margin: 0 0 10px; }
.fm-cover .bar { width: 90px; height: 6px; border-radius: 3px; background: #1d4ed8; margin: 28px 0; }
.fm-cover .meta { margin-top: 48px; font-size: 13px; color: #64748b; }
.fin-manual h2 { break-before: page; page-break-before: always; border-top: 0 !important; margin-top: 0 !important; }
.fin-manual h2#fm-que-es { break-before: auto; page-break-before: auto; }
.fin-manual .fm-shot, .fin-manual table, .fin-manual .fm-box, .fin-manual .fm-steps > li { break-inside: avoid; page-break-inside: avoid; }
.fin-manual .fm-shot img { max-height: 150mm; object-fit: contain; object-position: top; }
.fin-manual a { color: #1d4ed8; }
.no-print { text-align: center; padding: 12px; background: #eff6ff; font-size: 14px; }
@media print { .no-print { display: none; } .fm-page { padding: 0; } }
</style>
</head>
<body>
<div class="no-print">Versión para imprimir · usa <strong>Imprimir → Guardar como PDF</strong> de tu navegador.</div>
<div class="fm-page">
    <section class="fm-cover">
        <div class="k">JP Preparation · Plataforma</div>
        <h1>Manual de uso<br>de Finanzas</h1>
        <div class="bar"></div>
        <p>Cómo ver y controlar el dinero de la academia: ventas, cobros, gastos, lo que se debe y lo que hay que revisar.</p>
        <p>Pensado para el equipo de administración. No hace falta ningún conocimiento técnico.</p>
        <div class="meta">Versión 2.0 · <?= esc($date ?? date('d/m/Y')) ?></div>
    </section>
    <?= view('finanzas/_manual', isset($img) ? ['img' => $img] : []) ?>
</div>
</body>
</html>
