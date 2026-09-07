<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Algo ha fallado — Tu Plataforma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #020617; color: #e2e8f0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; margin: 0; padding: 20px;
        }
        .box {
            background: #0f172a; border: 1px solid #1e293b; border-radius: 14px;
            padding: 44px 40px; max-width: 460px; width: 100%; text-align: center;
        }
        .ico { font-size: 3rem; color: #f97316; margin-bottom: 8px; }
        h1 { font-size: 1.35rem; font-weight: 700; color: #f1f5f9; margin: 0 0 10px; }
        p  { color: #94a3b8; font-size: .95rem; line-height: 1.5; margin: 0 0 8px; }
        .ref {
            display: inline-block; margin: 14px 0 22px; padding: 6px 14px;
            background: #1e293b; border-radius: 8px; font-family: ui-monospace, Menlo, monospace;
            font-size: .9rem; letter-spacing: 1px; color: #e2e8f0;
        }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn-report { background: #f97316; border-color: #f97316; }
        .btn-report:hover { background: #ea580c; border-color: #ea580c; }
    </style>
</head>
<body>
    <div class="box">
        <div class="ico">⚠️</div>
        <h1>Algo ha fallado</h1>
        <p>Se ha producido un error inesperado. No es culpa tuya.</p>
        <p>Repórtalo y lo revisamos cuanto antes.</p>
        <div class="ref">Ref: <?= esc($ref ?? '—') ?></div>
        <div class="actions">
            <a href="#" id="report-btn" class="btn btn-report">
                <i class="bi"></i> Reportar este problema
            </a>
            <a href="/dashboard" class="btn btn-outline-light">Volver al inicio</a>
        </div>
    </div>

    <script>
    (function () {
        var ref = <?= json_encode($ref ?? '') ?>;
        var url = document.referrer || window.location.href;
        var qs  = 'origin=error&ref=' + encodeURIComponent(ref) +
                  '&url=' + encodeURIComponent(url) +
                  '&msg=' + encodeURIComponent('Error inesperado (500)');
        document.getElementById('report-btn').href = '/tickets/create?' + qs;
    })();
    </script>
</body>
</html>
