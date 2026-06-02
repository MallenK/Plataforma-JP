<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>
<?php helper('avatar'); ?>

<?php $role = session('role'); $isAdmin = in_array($role, ['superadmin', 'admin']); ?>

<!-- ── Métricas ──────────────────────────────────────────── -->
<?php if ($role === 'player' && !empty($playerFullProfile)): ?>
<?php
$dbPfp = $playerFullProfile;
$dbToday = date('Y-m-d');
// Bono activo FIFO
$dbActiveBono = null;
foreach ($dbPfp['plans'] ?? [] as $p) {
    if ((int)$p['sessions_remaining'] > 0 && (empty($p['expires_at']) || $p['expires_at'] >= $dbToday)) {
        if ((int)$p['id'] === (int)($dbPfp['active_bono_id'] ?? 0)) { $dbActiveBono = $p; break; }
    }
}
if (!$dbActiveBono) {
    foreach ($dbPfp['plans'] ?? [] as $p) {
        if ((int)$p['sessions_remaining'] > 0 && (empty($p['expires_at']) || $p['expires_at'] >= $dbToday)) {
            $dbActiveBono = $p; break;
        }
    }
}
$dbActiveRem   = (int)($dbActiveBono['sessions_remaining'] ?? 0);
$dbActiveTotal = (int)($dbActiveBono['sessions_total']     ?? 0);
$dbCatLabel = match($dbPfp['category'] ?? '') {
    'prebenjamin' => 'Prebenjamín', 'benjamin' => 'Benjamín', 'alevin' => 'Alevín',
    'infantil' => 'Infantil', 'cadete' => 'Cadete', 'juvenil' => 'Juvenil',
    'junior' => 'Júnior', 'senior' => 'Sénior', 'veterano' => 'Veterano', default => '—',
};
$dbLevelLabel = match($dbPfp['level'] ?? '') {
    'beginner' => 'Principiante', 'intermediate' => 'Intermedio', 'advanced' => 'Avanzado', default => '—',
};
$dbAge = null;
if (!empty($dbPfp['birth_date'])) {
    $dbAge = (int)(new \DateTime($dbPfp['birth_date']))->diff(new \DateTime())->y;
}
$dbRemPct   = $dbActiveTotal > 0 ? min(100, round($dbActiveRem / $dbActiveTotal * 100)) : 0;
$dbRemColor = $dbRemPct <= 25 ? 'var(--danger)' : ($dbRemPct <= 50 ? '#f97316' : 'var(--success)');
?>
<!-- Métricas del player -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Clases asistidas</span>
                <div class="metric-icon blue"><i class="bi bi-calendar-check-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($dbPfp['classes_count'] ?? 0) ?></div>
            <div class="metric-footer"><span class="metric-footer-label">total historial</span></div>
            <div class="metric-progress"><div class="metric-progress-bar" style="width:100%;background:var(--accent)"></div></div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Próximas clases</span>
                <div class="metric-icon green"><i class="bi bi-calendar3"></i></div>
            </div>
            <div class="metric-value"><?= (int)($dbPfp['upcoming_count'] ?? 0) ?></div>
            <div class="metric-footer"><span class="metric-footer-label">programadas</span></div>
            <div class="metric-progress"><div class="metric-progress-bar" style="width:<?= min(100, (int)($dbPfp['upcoming_count'] ?? 0) * 20) ?>%;background:var(--success)"></div></div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Sesiones bono</span>
                <div class="metric-icon orange"><i class="bi bi-ticket-perforated-fill"></i></div>
            </div>
            <div class="metric-value" style="color:<?= $dbRemColor ?>"><?= $dbActiveBono ? $dbActiveRem : '—' ?></div>
            <div class="metric-footer">
                <span class="metric-footer-label"><?= $dbActiveBono ? 'de ' . $dbActiveTotal . ' restantes' : 'sin bono activo' ?></span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" style="width:<?= $dbRemPct ?>%;background:<?= $dbRemColor ?>"></div></div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Bonos activos</span>
                <div class="metric-icon" style="background:rgba(139,92,246,.15);color:#8b5cf6"><i class="bi bi-collection-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($dbPfp['active_bonos'] ?? 0) ?></div>
            <div class="metric-footer"><span class="metric-footer-label">con sesiones</span></div>
            <div class="metric-progress"><div class="metric-progress-bar" style="width:<?= min(100, (int)($dbPfp['active_bonos'] ?? 0) * 25) ?>%;background:#8b5cf6"></div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<div
    class="row g-3 mb-4"
    id="stats-container"
    data-url="<?= route_to('dashboard_stats') ?>"
    data-csrf-name="<?= csrf_token() ?>"
    data-csrf-hash="<?= csrf_hash() ?>"
>
    <!-- Alumnos activos -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Alumnos activos</span>
                <div class="metric-icon blue"><i class="bi bi-people-fill"></i></div>
            </div>
            <div class="metric-value" id="alumnos-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend up" id="alumnos-trend"><i class="bi bi-arrow-up-short"></i>—</span>
                <span class="metric-footer-label">vs mes anterior</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="alumnos-bar" style="width:0%;background:var(--accent)"></div></div>
        </div>
    </div>

    <!-- Entrenadores -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Entrenadores</span>
                <div class="metric-icon green"><i class="bi bi-person-workspace"></i></div>
            </div>
            <div class="metric-value" id="entrenadores-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend neutral" id="entrenadores-trend">—</span>
                <span class="metric-footer-label">activos</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="entrenadores-bar" style="width:0%;background:var(--success)"></div></div>
        </div>
    </div>

    <!-- Ingresos del mes -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label color-red">Ingresos mes</span>
                <div class="metric-icon orange"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="metric-value" id="ingresos-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend up" id="ingresos-trend"><i class="bi bi-arrow-up-short"></i>—</span>
                <span class="metric-footer-label">meta mensual</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="ingresos-bar" style="width:0%;background:#f97316"></div></div>
        </div>
    </div>

    <!-- Alertas -->
    <div class="col-12 col-sm-6 col-xl-3">
        <a href="<?= base_url('bonos?filtro=agotados') ?>" style="text-decoration:none;color:inherit">
        <div class="metric-card" style="cursor:pointer">
            <div class="metric-card-header">
                <span class="metric-label">Alertas de bonos</span>
                <div class="metric-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            </div>
            <div class="metric-value" id="alertas-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend down" id="alertas-trend"></span>
                <span class="metric-footer-label">agotados / 1 sesión / por vencer</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="alertas-bar" style="width:0%;background:var(--danger)"></div></div>
        </div>
        </a>
    </div>
</div>
<?php elseif (!empty($showWelcome)): ?>

<!-- Bienvenida (solo la primera vez en la vida del usuario) -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-jp">
            <div class="card-jp-body text-center py-4">
                <i class="bi bi-person-check-fill" style="font-size:2.5rem;color:var(--accent)"></i>
                <h5 class="mt-3 mb-1" style="color:var(--text-h);font-weight:700">
                    Bienvenido, <?= esc(session('name')) ?>
                </h5>
                <p style="color:var(--text-muted);margin:0">
                    <?php if ($role === 'player'): ?>
                        Accede a tu <a href="<?= base_url('alumno') ?>">ficha</a> o consulta la <a href="<?= base_url('documentacion') ?>">documentación</a>.
                    <?php elseif ($role === 'coach'): ?>
                        Gestiona tus alumnos desde <a href="<?= base_url('alumnos') ?>">Alumnos</a> o revisa las <a href="<?= base_url('clases') ?>">Clases</a>.
                    <?php else: ?>
                        Consulta la <a href="<?= base_url('documentacion') ?>">documentación</a> o revisa las <a href="<?= base_url('clases') ?>">clases</a>.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ── Cuerpo: Calendario + Paneles laterales ─────────────── -->
<div class="row g-3">

    <!-- Calendario -->
    <?php $dbCanManage = in_array($role, ['superadmin', 'admin', 'staff', 'coach']); ?>
    <div class="col-12 col-xl-8">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-calendar3 me-2" style="color:var(--accent)"></i>Calendario</span>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="calendar-view-tabs">
                        <button class="calendar-view-tab active" onclick="DBCAL.switchView('month', this)">Mes</button>
                        <button class="calendar-view-tab" onclick="DBCAL.switchView('week', this)">Semana</button>
                        <button class="calendar-view-tab" onclick="DBCAL.switchView('day', this)">Día</button>
                    </div>
                    <?php if ($dbCanManage): ?>
                    <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="ClaseModal.open()">
                        <i class="bi bi-plus-lg me-1"></i>Nueva clase
                    </button>
                    <?php endif; ?>
                    <a href="<?= base_url('clases') ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                        <i class="bi bi-arrow-right me-1"></i>Ver todo
                    </a>
                </div>
            </div>
            <div class="card-jp-body">
                <div class="calendar-toolbar">
                    <div class="calendar-nav">
                        <button onclick="DBCAL.prev()"><i class="bi bi-chevron-left"></i></button>
                        <button onclick="DBCAL.today()" style="width:auto;padding:0 12px;font-size:12px;font-weight:600">Hoy</button>
                        <span class="calendar-nav-label" id="db-cal-label">Cargando…</span>
                        <button onclick="DBCAL.next()"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
                <div id="db-cal-grid"></div>
            </div>
        </div>
    </div>

    <!-- Panel lateral derecho -->
    <div class="col-12 col-xl-4 d-flex flex-column gap-3">

        <!-- Próximas clases -->
        <?php if ($dbCanManage): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-collection-play-fill me-2" style="color:var(--accent)"></i>Próximas clases
                </span>
                <a href="<?= base_url('clases') ?>" style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:600">Ver todas</a>
            </div>
            <div id="db-proximas-clases" class="card-jp-body py-2">
                <div style="font-size:13px;color:var(--text-muted);text-align:center;padding:10px">Cargando…</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Accesos rápidos (coach / alumno) -->
        <?php if ($role === 'coach'): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">Accesos rápidos</span>
            </div>
            <div class="card-jp-body d-flex flex-column gap-2">
                <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary w-100">
                    <i class="bi bi-people-fill"></i> Mis alumnos
                </a>
                <a href="<?= base_url('clases') ?>" class="btn-jp btn-jp-secondary w-100">
                    <i class="bi bi-collection-play-fill"></i> Mis clases
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'player' && !empty($playerFullProfile)): ?>

        <!-- ── Tarjeta de identidad ────────────────────── -->
        <div class="card-jp">
            <div class="card-jp-body" style="padding:16px">
                <div class="d-flex gap-3 align-items-center mb-3">
                    <?= avatar_html($dbPfp['avatar'] ?? null, $dbPfp['name'] ?? '?', 'profile-avatar-md') ?>
                    <div style="min-width:0">
                        <div style="font-size:15px;font-weight:700;color:var(--text-h);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= esc($dbPfp['name'] ?? '—') ?></div>
                        <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= esc($dbPfp['email'] ?? '') ?></div>
                        <div class="d-flex gap-1 mt-1 flex-wrap">
                            <span class="badge-status active">Activo</span>
                            <?php if (!empty($dbPfp['category'])): ?>
                            <span style="font-size:10px;background:var(--accent-light,#e0edff);color:var(--accent);border-radius:4px;padding:2px 7px;font-weight:600"><?= esc($dbCatLabel) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($dbPfp['level'])): ?>
                            <span style="font-size:10px;background:rgba(245,158,11,.12);color:#d97706;border-radius:4px;padding:2px 7px;font-weight:600"><?= esc($dbLevelLabel) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2" style="font-size:13px">
                    <?php if (!empty($dbPfp['position'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:var(--text-muted)"><i class="bi bi-geo-alt me-1"></i>Posición</span>
                        <span style="font-weight:600;color:var(--text-h)"><?= esc($dbPfp['position']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dbPfp['team'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:var(--text-muted)"><i class="bi bi-shield-fill me-1"></i>Equipo</span>
                        <span style="font-weight:600;color:var(--text-h)"><?= esc($dbPfp['team']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dbPfp['league'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:var(--text-muted)"><i class="bi bi-trophy me-1"></i>Liga</span>
                        <span style="font-weight:600;color:var(--text-h)"><?= esc($dbPfp['league']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($dbAge !== null): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:var(--text-muted)"><i class="bi bi-cake2 me-1"></i>Edad</span>
                        <span style="font-weight:600;color:var(--text-h)"><?= $dbAge ?> años</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dbPfp['height']) || !empty($dbPfp['weight'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:var(--text-muted)"><i class="bi bi-person me-1"></i>Físico</span>
                        <span style="font-weight:600;color:var(--text-h)">
                            <?= !empty($dbPfp['height']) ? esc($dbPfp['height']) . ' cm' : '' ?>
                            <?= (!empty($dbPfp['height']) && !empty($dbPfp['weight'])) ? ' · ' : '' ?>
                            <?= !empty($dbPfp['weight']) ? esc($dbPfp['weight']) . ' kg' : '' ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <a href="<?= base_url('alumno') ?>" class="btn-jp btn-jp-primary btn-jp-sm flex-grow-1 justify-content-center">
                        <i class="bi bi-person-badge-fill"></i> Mi ficha
                    </a>
                    <a href="<?= base_url('perfil') ?>" class="btn-jp btn-jp-secondary btn-jp-sm flex-grow-1 justify-content-center">
                        <i class="bi bi-gear-fill"></i> Perfil
                    </a>
                </div>
            </div>
        </div>

        <!-- ── Bono activo destacado ───────────────────── -->
        <?php if ($dbActiveBono): ?>
        <?php
        $dbExpDays = null;
        if (!empty($dbActiveBono['expires_at'])) {
            $dbExpDays = (int)(new \DateTime())->diff(new \DateTime($dbActiveBono['expires_at']))->days;
            if ($dbActiveBono['expires_at'] < $dbToday) $dbExpDays = -1;
        }
        ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--accent)"></i>Bono activo
                </span>
                <a href="<?= base_url('perfil') ?>" style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:600">Ver todos</a>
            </div>
            <div class="card-jp-body">
                <div style="font-size:13px;font-weight:700;color:var(--text-h);margin-bottom:8px"><?= esc($dbActiveBono['bono_name'] ?? '—') ?></div>

                <!-- Sesiones grandes -->
                <div class="d-flex align-items-end gap-2 mb-2">
                    <span style="font-size:40px;font-weight:800;line-height:1;color:<?= $dbRemColor ?>"><?= $dbActiveRem ?></span>
                    <span style="font-size:14px;color:var(--text-muted);padding-bottom:4px">/ <?= $dbActiveTotal ?> sesiones</span>
                </div>

                <!-- Barra de progreso gruesa -->
                <div style="height:10px;background:var(--border);border-radius:5px;margin-bottom:8px">
                    <div style="height:10px;border-radius:5px;background:<?= $dbRemColor ?>;width:<?= $dbRemPct ?>%;transition:width .4s"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center" style="font-size:12px">
                    <?php if (!empty($dbActiveBono['start_date'])): ?>
                    <span style="color:var(--text-muted)">Desde <?= date('d/m/Y', strtotime($dbActiveBono['start_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($dbActiveBono['expires_at'])): ?>
                    <span style="color:<?= ($dbExpDays !== null && $dbExpDays <= 7) ? 'var(--danger)' : 'var(--text-muted)' ?>;font-weight:<?= ($dbExpDays !== null && $dbExpDays <= 7) ? '700' : '400' ?>">
                        <?php if ($dbExpDays !== null && $dbExpDays <= 7 && $dbExpDays >= 0): ?>
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Vence en <?= $dbExpDays ?> días
                        <?php else: ?>
                            Vence <?= date('d/m/Y', strtotime($dbActiveBono['expires_at'])) ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($dbRemPct <= 25 && $dbActiveRem > 0): ?>
                <div style="margin-top:8px;padding:6px 10px;background:rgba(239,68,68,.08);border-radius:6px;font-size:12px;color:var(--danger);font-weight:600">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Quedan pocas sesiones. Renueva tu bono pronto.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Otros bonos activos -->
        <?php
        $dbOtherActive = array_filter($dbPfp['plans'] ?? [], function($p) use ($dbToday, $dbActiveBono) {
            return (int)$p['sessions_remaining'] > 0
                && (empty($p['expires_at']) || $p['expires_at'] >= $dbToday)
                && (int)$p['id'] !== (int)($dbActiveBono['id'] ?? 0);
        });
        ?>
        <?php if (!empty($dbOtherActive)): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-collection-fill me-2" style="color:#8b5cf6"></i>Otros bonos
                </span>
            </div>
            <div class="card-jp-body d-flex flex-column gap-3">
                <?php foreach (array_slice($dbOtherActive, 0, 3) as $dbPlan):
                    $dbPRem   = (int)($dbPlan['sessions_remaining'] ?? 0);
                    $dbPTotal = (int)($dbPlan['sessions_total']     ?? 0);
                    $dbPPct   = $dbPTotal > 0 ? min(100, round($dbPRem / $dbPTotal * 100)) : 0;
                    $dbPColor = $dbPPct <= 25 ? 'var(--danger)' : ($dbPPct <= 50 ? '#f97316' : 'var(--accent)');
                ?>
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size:12px;font-weight:600;color:var(--text-h)"><?= esc($dbPlan['bono_name'] ?? '—') ?></span>
                        <span style="font-size:12px;font-weight:700;color:<?= $dbPColor ?>"><?= $dbPRem ?>/<?= $dbPTotal ?></span>
                    </div>
                    <div style="height:5px;background:var(--border);border-radius:3px">
                        <div style="height:5px;border-radius:3px;background:<?= $dbPColor ?>;width:<?= $dbPPct ?>%"></div>
                    </div>
                    <?php if (!empty($dbPlan['expires_at'])): ?>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px">Vence: <?= date('d/m/Y', strtotime($dbPlan['expires_at'])) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- ── Próximas clases del player ─────────────── -->
        <?php if (!empty($dbPfp['upcoming'])): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-collection-play-fill me-2" style="color:var(--accent)"></i>Próximas clases
                </span>
                <a href="<?= base_url('clases') ?>" style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:600">Ver todas</a>
            </div>
            <div class="card-jp-body py-1">
                <?php foreach (array_slice($dbPfp['upcoming'], 0, 4) as $up):
                    $upDate = !empty($up['session_date']) ? new \DateTime($up['session_date']) : null;
                    $mn = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
                    <?php if ($upDate): ?>
                    <div style="min-width:38px;text-align:center;background:var(--accent-light,#e0edff);color:var(--accent);border-radius:6px;padding:4px 0;font-size:12px;font-weight:700;flex-shrink:0">
                        <?= $upDate->format('d') ?><br>
                        <span style="font-size:10px"><?= $mn[(int)$upDate->format('n') - 1] ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="flex:1;min-width:0">
                        <a href="<?= base_url('clases/' . (int)$up['id']) ?>" style="font-size:13px;font-weight:600;color:var(--text-h);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= esc($up['title'] ?? '—') ?>
                        </a>
                        <div style="font-size:11px;color:var(--text-muted)">
                            <?= !empty($up['start_time']) ? substr($up['start_time'], 0, 5) : '' ?>
                            <?= (!empty($up['start_time']) && !empty($up['end_time'])) ? '–' . substr($up['end_time'], 0, 5) : '' ?>
                            <?php $loc = $up['location_name'] ?? $up['location_custom'] ?? ''; if ($loc): ?>
                            · <?= esc($loc) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Acceso rápido docs ──────────────────────── -->
        <div class="card-jp">
            <div class="card-jp-body d-flex gap-2">
                <a href="<?= base_url('documentacion') ?>" class="btn-jp btn-jp-secondary btn-jp-sm flex-grow-1 justify-content-center">
                    <i class="bi bi-folder2-open"></i> Documentos
                </a>
                <a href="<?= base_url('mensajes') ?>" class="btn-jp btn-jp-secondary btn-jp-sm flex-grow-1 justify-content-center">
                    <i class="bi bi-chat-dots"></i> Mensajes
                </a>
            </div>
        </div>

        <?php endif; ?>

    </div>
</div>

<!-- ── Modal unificado de creación (Dashboard) ────────────── -->
<?php if ($dbCanManage): ?>
    <?= $this->include('clases/_modal_create') ?>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
.cal-month-headers { display:grid;grid-template-columns:repeat(7,1fr);background:var(--bg-app);border:1px solid var(--border);border-bottom:none;border-radius:var(--radius-sm) var(--radius-sm) 0 0; }
.cal-day-header { padding:7px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted); }
.cal-month-grid { display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:0 0 var(--radius-sm) var(--radius-sm);overflow:hidden; }
.cal-cell { background:var(--bg-card);min-height:80px;padding:5px;transition:background .1s; }
.cal-cell:hover { background:#f8fafc; }
.cal-cell.other { background:#f8fafc;opacity:.5; }
.cal-cell.today { background:var(--accent-light); }
.cal-day-num { font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:3px; }
.cal-cell.today .cal-day-num { background:var(--accent);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center; }
.cal-chip { display:block;font-size:10.5px;font-weight:600;padding:2px 5px;border-radius:4px;margin-bottom:2px;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.cal-chip:hover { opacity:.85; }
.cal-more { font-size:10px;color:var(--text-muted);padding:1px 4px; }
.cal-week-wrap { overflow-x:auto; }
.cal-week-grid { display:grid;grid-template-columns:48px repeat(7,1fr);min-width:560px; }
.cal-week-head { padding:7px;text-align:center;border-bottom:2px solid var(--border);background:var(--bg-card); }
.cal-week-head.time-col { border-right:1px solid var(--border); }
.cal-wday-name { font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px; }
.cal-wday-num  { font-size:18px;font-weight:800;color:var(--text-h); }
.cal-wday-today .cal-wday-num { color:var(--accent); }
.cal-time-label { font-size:10px;color:var(--text-muted);padding:2px 4px 0;border-right:1px solid var(--border);border-bottom:1px solid #f1f5f9;height:52px;text-align:right; }
.cal-hour-slot { position:relative;border-bottom:1px solid #f1f5f9;height:52px; }
.cal-event-block { position:absolute;left:2px;right:2px;border-radius:4px;padding:2px 5px;font-size:10.5px;font-weight:600;text-decoration:none;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;z-index:1; }
.cal-day-grid { display:grid;grid-template-columns:48px 1fr;min-width:200px; }
</style>
<script>
const DB_CSRF_NAME = '<?= csrf_token() ?>';
const DB_CSRF_HASH = '<?= csrf_hash() ?>';
const dbCanManage  = <?= $dbCanManage ? 'true' : 'false' ?>;

// ── Mini-calendario dashboard ─────────────────────────────────
const DBCAL = {
    view: 'month',
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    weekStart: null,
    day: null,
    events: [],

    async load() {
        let year = this.year, month = this.month;
        if (this.view === 'week') {
            year = this.weekStart ? parseInt(this.weekStart.split('-')[0]) : this.year;
            month = this.weekStart ? parseInt(this.weekStart.split('-')[1]) : this.month;
        } else if (this.view === 'day' && this.day) {
            const p = this.day.split('-');
            year = parseInt(p[0]); month = parseInt(p[1]);
        }
        const url = `/clases/api/calendario?year=${year}&month=${month}`;
        try {
            const res = await fetch(url);
            this.events = await res.json();
        } catch(e) { this.events = []; }
        this.render();
        this.loadProximas();
    },

    render() {
        if (this.view === 'month') this.renderMonth();
        else if (this.view === 'week') this.renderWeek();
        else this.renderDay();
    },

    switchView(v, btn) {
        this.view = v;
        document.querySelectorAll('.calendar-view-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (v === 'week' && !this.weekStart) this.weekStart = this.getMonday(new Date());
        if (v === 'day'  && !this.day)       this.day = this.fmt(new Date());
        this.load();
    },

    prev() {
        if (this.view === 'month') { if (--this.month < 1) { this.month = 12; this.year--; } }
        else if (this.view === 'week') { const d = new Date(this.weekStart+'T00:00:00'); d.setDate(d.getDate()-7); this.weekStart = this.fmt(d); }
        else { const d = new Date(this.day+'T00:00:00'); d.setDate(d.getDate()-1); this.day = this.fmt(d); }
        this.load();
    },
    next() {
        if (this.view === 'month') { if (++this.month > 12) { this.month = 1; this.year++; } }
        else if (this.view === 'week') { const d = new Date(this.weekStart+'T00:00:00'); d.setDate(d.getDate()+7); this.weekStart = this.fmt(d); }
        else { const d = new Date(this.day+'T00:00:00'); d.setDate(d.getDate()+1); this.day = this.fmt(d); }
        this.load();
    },
    today() {
        const n = new Date(); this.year = n.getFullYear(); this.month = n.getMonth()+1;
        this.weekStart = this.getMonday(n); this.day = this.fmt(n); this.load();
    },

    renderMonth() {
        const mn = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        document.getElementById('db-cal-label').textContent = mn[this.month-1] + ' ' + this.year;
        const first = new Date(this.year, this.month-1, 1).getDay();
        const offset = (first+6)%7;
        const dim = new Date(this.year, this.month, 0).getDate();
        const todayStr = this.fmt(new Date());
        let html = '<div class="cal-month-headers">';
        ['L','M','X','J','V','S','D'].forEach(d => html += `<div class="cal-day-header">${d}</div>`);
        html += '</div><div class="cal-month-grid">';
        for (let i=0; i<offset; i++) html += '<div class="cal-cell other"></div>';
        for (let day=1; day<=dim; day++) {
            const ds = `${this.year}-${String(this.month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const isT = ds === todayStr;
            const evts = this.events.filter(e=>e.date===ds);
            html += `<div class="cal-cell${isT?' today':''}${dbCanManage?' cal-can-create':''}" onclick="dbHandleClick(event,'${ds}')">`;
            html += `<div class="cal-day-num">${day}</div>`;
            evts.slice(0,2).forEach(ev => {
                html += `<a href="/clases/${ev.id}" class="cal-chip" style="background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44" onclick="event.stopPropagation()">${ev.start} ${ev.title}</a>`;
            });
            if (evts.length>2) html += `<div class="cal-more">+${evts.length-2}</div>`;
            html += '</div>';
        }
        const fill = (7 - ((offset+dim)%7))%7;
        for (let i=0; i<fill; i++) html += '<div class="cal-cell other"></div>';
        html += '</div>';
        document.getElementById('db-cal-grid').innerHTML = html;
    },

    renderWeek() {
        if (!this.weekStart) this.weekStart = this.getMonday(new Date());
        const ws = new Date(this.weekStart+'T00:00:00');
        const we = new Date(ws); we.setDate(we.getDate()+6);
        const todayStr = this.fmt(new Date());
        const mn = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        document.getElementById('db-cal-label').textContent =
            `${ws.getDate()} ${mn[ws.getMonth()]} – ${we.getDate()} ${mn[we.getMonth()]} ${we.getFullYear()}`;
        const days=[],dates=[];
        for(let i=0;i<7;i++){const d=new Date(ws);d.setDate(d.getDate()+i);days.push(d);dates.push(this.fmt(d));}
        const dn=['L','M','X','J','V','S','D'];
        const HS=7, HE=20, SH=52;
        let html='<div class="cal-week-wrap"><div class="cal-week-grid">';
        html+='<div class="cal-week-head time-col"></div>';
        days.forEach((d,i)=>{
            const isT=dates[i]===todayStr;
            html+=`<div class="cal-week-head${isT?' cal-wday-today':''}"><div class="cal-wday-name">${dn[i]}</div><div class="cal-wday-num">${d.getDate()}</div></div>`;
        });
        for(let h=HS;h<HE;h++){
            html+=`<div class="cal-time-label">${String(h).padStart(2,'0')}:00</div>`;
            dates.forEach(ds=>{
                const evts=this.events.filter(e=>{if(e.date!==ds)return false;return parseInt(e.start.split(':')[0])===h;});
                html+=`<div class="cal-hour-slot${dbCanManage?' cal-can-create':''}" onclick="dbHandleSlot(event,'${ds}',${h})">`;
                evts.forEach(ev=>{
                    const [sh2,sm]=ev.start.split(':').map(Number);
                    const [eh2,em]=(ev.end||ev.start).split(':').map(Number);
                    const top=(sm/60)*SH;
                    const dur=Math.max(((eh2*60+em)-(sh2*60+sm))/60*SH,20);
                    html+=`<a href="/clases/${ev.id}" class="cal-event-block" style="top:${top}px;height:${dur}px;background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44" onclick="event.stopPropagation()">${ev.start} ${ev.title}</a>`;
                });
                html+='</div>';
            });
        }
        html+='</div></div>';
        document.getElementById('db-cal-grid').innerHTML = html;
    },

    async loadProximas() {
        const el = document.getElementById('db-proximas-clases');
        if (!el) return;
        try {
            const res  = await fetch(`/clases/api/calendario?year=${this.year}&month=${this.month}`);
            const data = await res.json();
            const today = this.fmt(new Date());
            const upcoming = data.filter(e => e.date >= today).slice(0, 4);
            if (!upcoming.length) {
                el.innerHTML = '<div style="font-size:13px;color:var(--text-muted);text-align:center;padding:10px">Sin clases próximas</div>';
                return;
            }
            const mn=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            el.innerHTML = upcoming.map(e => {
                const d = new Date(e.date+'T00:00:00');
                return `<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
                    <div style="min-width:38px;text-align:center;background:${e.color}22;color:${e.color};border-radius:6px;padding:4px 0;font-size:12px;font-weight:700">${d.getDate()}<br><span style="font-size:10px">${mn[d.getMonth()]}</span></div>
                    <div style="flex:1;min-width:0">
                        <a href="/clases/${e.id}" style="font-size:13px;font-weight:600;color:var(--text-h);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${e.title}</a>
                        <div style="font-size:11px;color:var(--text-muted)">${e.start}–${e.end}</div>
                    </div>
                </div>`;
            }).join('');
        } catch(e) {}
    },

    renderDay() {
        if (!this.day) this.day = this.fmt(new Date());
        const d        = new Date(this.day+'T00:00:00');
        const dnames   = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        const mn       = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        const todayStr = this.fmt(new Date());
        const isToday  = this.day === todayStr;

        document.getElementById('db-cal-label').textContent =
            `${dnames[d.getDay()]}, ${d.getDate()} de ${mn[d.getMonth()]} ${d.getFullYear()}`;

        const HS=7, HE=20, SH=52;
        const dayEvts = this.events.filter(e => e.date === this.day);

        let html='<div class="cal-week-wrap"><div class="cal-day-grid">';
        html+=`<div class="cal-week-head time-col"></div>`;
        html+=`<div class="cal-week-head${isToday?' cal-wday-today':''}"><div class="cal-wday-name">${dnames[d.getDay()].substring(0,3)}</div><div class="cal-wday-num">${d.getDate()}</div></div>`;

        for(let h=HS;h<HE;h++){
            html+=`<div class="cal-time-label">${String(h).padStart(2,'0')}:00</div>`;
            const slotEvts=dayEvts.filter(e=>parseInt(e.start.split(':')[0])===h);
            html+=`<div class="cal-hour-slot${dbCanManage?' cal-can-create':''}" onclick="dbHandleSlot(event,'${this.day}',${h})">`;
            slotEvts.forEach(ev=>{
                const [sh2,sm]=ev.start.split(':').map(Number);
                const [eh2,em]=(ev.end||ev.start).split(':').map(Number);
                const top=(sm/60)*SH;
                const dur=Math.max(((eh2*60+em)-(sh2*60+sm))/60*SH,20);
                html+=`<a href="/clases/${ev.id}" class="cal-event-block" style="top:${top}px;height:${dur}px;background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44" onclick="event.stopPropagation()">${ev.start} ${ev.title}</a>`;
            });
            html+='</div>';
        }
        html+='</div></div>';
        document.getElementById('db-cal-grid').innerHTML = html;
    },

    getMonday(d) { const day=d.getDay(),diff=d.getDate()-day+(day===0?-6:1);return this.fmt(new Date(d.setDate(diff))); },
    fmt(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; },
};

function dbHandleClick(e, date) {
    if (e.target.closest('a')) return;
    if (dbCanManage) ClaseModal.open({ date });
}
function dbHandleSlot(e, date, hour) {
    if (e.target.closest('a')) return;
    if (dbCanManage) ClaseModal.open({ date, time: String(hour).padStart(2,'0') + ':00' });
}

// Inicializar
DBCAL.load();
</script>
<?php if ($dbCanManage): ?>
<script src="<?= base_url('assets/js/clase-modal.js') ?>"></script>
<script>
ClaseModal.init({
    csrfName: DB_CSRF_NAME,
    csrfHash: DB_CSRF_HASH,
    onCreated: () => DBCAL.load(),
});
</script>
<?php endif; ?>
<?php if ($isAdmin): ?>
<?= view('dashboard/scripts') ?>
<?php endif; ?>
<?= $this->endSection() ?>
