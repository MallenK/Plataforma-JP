<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<?php $role = session('role'); $isAdmin = in_array($role, ['superadmin', 'admin']); ?>

<!-- ── Métricas ──────────────────────────────────────────── -->
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
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Alertas</span>
                <div class="metric-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            </div>
            <div class="metric-value" id="alertas-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend down" id="alertas-trend"><i class="bi bi-arrow-up-short"></i>—</span>
                <span class="metric-footer-label">acción requerida</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="alertas-bar" style="width:0%;background:var(--danger)"></div></div>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Vista reducida para coach / staff / alumno -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-jp">
            <div class="card-jp-body text-center py-4">
                <i class="bi bi-person-check-fill" style="font-size:2.5rem;color:var(--accent)"></i>
                <h5 class="mt-3 mb-1" style="color:var(--text-h);font-weight:700">
                    Bienvenido, <?= esc(session('name')) ?>
                </h5>
                <p style="color:var(--text-muted);margin:0">
                    <?php if ($role === 'alumno'): ?>
                        Accede a tu <a href="<?= base_url('alumno') ?>">ficha</a> o consulta la <a href="<?= base_url('documentacion') ?>">documentación</a>.
                    <?php elseif ($role === 'coach'): ?>
                        Gestiona tus alumnos desde <a href="<?= base_url('alumnos') ?>">Alumnos</a> o revisa el <a href="<?= base_url('organizador') ?>">Organizador</a>.
                    <?php else: ?>
                        Consulta los <a href="<?= base_url('torneos') ?>">torneos</a> o la <a href="<?= base_url('documentacion') ?>">documentación</a>.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ── Cuerpo: Calendario + Paneles laterales ─────────────── -->
<div class="row g-3">

    <!-- Calendario / Organizador -->
    <div class="col-12 col-xl-8">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-calendar3 me-2" style="color:var(--accent)"></i>Calendario Maestro</span>
                <div class="d-flex align-items-center gap-2">
                    <div class="calendar-view-tabs">
                        <button class="calendar-view-tab active">Semana</button>
                        <button class="calendar-view-tab">Mes</button>
                        <button class="calendar-view-tab">Día</button>
                    </div>
                    <?php if (in_array($role, ['superadmin', 'admin', 'coach'])): ?>
                    <a href="<?= base_url('clases') ?>" class="btn-jp btn-jp-primary btn-jp-sm">
                        <i class="bi bi-plus-lg"></i> Nueva clase
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-jp-body">
                <div class="calendar-toolbar">
                    <div class="calendar-nav">
                        <button><i class="bi bi-chevron-left"></i></button>
                        <span class="calendar-nav-label" id="cal-label">Cargando...</span>
                        <button><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
                <!-- Placeholder del calendario — se sustituirá con funcionalidad real -->
                <div id="calendar-placeholder" style="min-height:300px;background:#f8fafc;border-radius:var(--radius-sm);border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;color:var(--text-muted)">
                    <i class="bi bi-calendar3" style="font-size:2rem;color:#cbd5e1"></i>
                    <span style="font-size:13px">El calendario se cargará aquí</span>
                    <a href="<?= base_url('organizador') ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                        Ir al Organizador
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel lateral derecho -->
    <div class="col-12 col-xl-4 d-flex flex-column gap-3">

        <!-- Próximos torneos -->
        <?php if (in_array($role, ['superadmin', 'admin', 'coach', 'staff'])): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-trophy-fill me-2" style="color:var(--warning)"></i>Próximos Torneos</span>
                <a href="<?= base_url('torneos') ?>" style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:600">Ver todos</a>
            </div>
            <div class="card-jp-body py-0">
                <div id="proximos-torneos">
                    <div class="list-item-jp">
                        <div class="list-item-icon blue"><i class="bi bi-calendar-event"></i></div>
                        <div class="list-item-info">
                            <div class="list-item-title">—</div>
                            <div class="list-item-sub">Pendiente de carga</div>
                        </div>
                        <i class="bi bi-chevron-right list-item-action"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estado de pagos / bonos -->
        <?php if ($isAdmin): ?>
        <div class="card-jp" style="background:#0f172a;border-color:#1e293b">
            <div class="card-jp-header" style="border-color:#1e293b">
                <span class="card-jp-title" style="color:#f1f5f9">
                    <i class="bi bi-graph-up-arrow me-2" style="color:var(--success)"></i>Estado de Pagos
                </span>
                <a href="<?= base_url('finanzas') ?>" style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:600">Gestionar</a>
            </div>
            <div class="card-jp-body">
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Completados</span>
                    <span style="font-size:13px;font-weight:700;color:#f1f5f9" id="pagos-pct">—%</span>
                </div>
                <div class="progress-jp mb-3">
                    <div class="progress-jp-bar" id="pagos-bar" style="width:0%;background:var(--success)"></div>
                </div>
                <p style="font-size:12px;color:#64748b;margin:0" id="pagos-desc">Cargando información de pagos...</p>
                <a href="<?= base_url('finanzas') ?>" class="btn-jp btn-jp-primary w-100 mt-3 justify-content-center">
                    Gestionar Finanzas
                </a>
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
                <a href="<?= base_url('organizador') ?>" class="btn-jp btn-jp-secondary w-100">
                    <i class="bi bi-calendar3"></i> Organizador
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'alumno'): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">Mi espacio</span>
            </div>
            <div class="card-jp-body d-flex flex-column gap-2">
                <a href="<?= base_url('alumno') ?>" class="btn-jp btn-jp-primary w-100 justify-content-center">
                    <i class="bi bi-person-badge-fill"></i> Ver mi ficha
                </a>
                <a href="<?= base_url('documentacion') ?>" class="btn-jp btn-jp-secondary w-100 justify-content-center">
                    <i class="bi bi-folder2-open"></i> Documentación
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Actualiza la etiqueta del calendario con la semana actual
(function() {
    const now = new Date();
    const options = { month: 'short', year: 'numeric' };
    document.getElementById('cal-label').textContent =
        now.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
})();
</script>
<?php if ($isAdmin): ?>
<?= view('dashboard/scripts') ?>
<?php endif; ?>
<?= $this->endSection() ?>
