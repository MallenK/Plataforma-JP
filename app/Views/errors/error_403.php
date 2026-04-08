<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin permisos — JP Preparation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /*
         * Página de error 403 — sin acceso.
         * Diseño standalone (no usa el layout de la app) para evitar
         * dependencias de sesión en la carga de sidebar/navbar.
         * El estilo sigue el tema oscuro de la plataforma.
         */

        body {
            background-color: #020617;
            color: #e2e8f0;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .error-card {
            background-color: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 12px;
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }

        .error-code {
            font-size: 6rem;
            font-weight: 800;
            color: #ef4444;
            line-height: 1;
            margin-bottom: 8px;
        }

        .error-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: #f1f5f9;
        }

        .error-desc {
            color: #94a3b8;
            font-size: 0.95rem;
            margin-bottom: 32px;
            line-height: 1.6;
        }

        .role-badge {
            display: inline-block;
            background-color: #1e293b;
            color: #94a3b8;
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 28px;
            border: 1px solid #334155;
        }

        .btn-back {
            background-color: #e2e8f0;
            color: #020617;
            border: none;
            padding: 10px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }

        .btn-back:hover {
            background-color: #fff;
            color: #020617;
        }
    </style>
</head>
<body>

<div class="error-card">

    <div class="error-code">403</div>

    <h1 class="error-title">Sin permisos</h1>

    <p class="error-desc">
        No tienes acceso a esta sección.<br>
        Si crees que es un error, contacta con un administrador.
    </p>

    <?php if (!empty($role)): ?>
        <!-- Muestra el rol actual para ayudar al usuario a entender por qué no tiene acceso -->
        <div class="role-badge">
            Tu rol actual: <strong><?= esc($role) ?></strong>
        </div>
    <?php endif; ?>

    <br>

    <a href="<?= base_url('dashboard') ?>" class="btn-back">
        Volver al dashboard
    </a>

</div>

</body>
</html>
