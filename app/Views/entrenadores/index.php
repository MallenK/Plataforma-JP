<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Entrenadores';
$pageSubtitle = 'Gestión del equipo técnico';
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('created_password')): ?>
<div class="alert-jp success" style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px">
    <i class="bi bi-check-circle-fill" style="font-size:18px;margin-top:2px;flex-shrink:0"></i>
    <div>
        <strong>Entrenador "<?= esc(session()->getFlashdata('created_name')) ?>" creado correctamente.</strong><br>
        <span style="font-size:13px">
            Contraseña inicial:
            <code style="background:rgba(255,255,255,.15);padding:2px 8px;border-radius:4px;font-weight:700;letter-spacing:.5px">
                <?= esc(session()->getFlashdata('created_password')) ?>
            </code>
            — anótala antes de salir de esta página.
        </span>
    </div>
</div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-check-circle-fill"></i>
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>

<!-- Cabecera -->
<div class="page-header">
    <div class="page-header-text">
        <h2>Entrenadores</h2>
        <p>Equipo técnico de la academia</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('entrenadores/nuevo') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-person-plus-fill"></i> Nuevo entrenador
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="card-jp mb-3">
    <div class="card-jp-body py-3">
        <div class="search-bar">
            <div class="input-search">
                <i class="bi bi-search"></i>
                <input type="text" id="search-input" placeholder="Buscar por nombre o email...">
            </div>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-person-workspace me-2" style="color:var(--success)"></i>
            Equipo técnico
        </span>
        <span style="font-size:12px;color:var(--text-muted)" id="total-count">
            <?= count($coaches ?? []) ?> entrenador(es)
        </span>
    </div>

    <?php if (!empty($coaches)): ?>
    <div class="table-responsive">
        <table class="table-jp" id="coaches-table">
            <thead>
                <tr>
                    <th>Entrenador</th>
                    <th>Email</th>
                    <th style="text-align:center">Sesiones</th>
                    <th style="text-align:center">Alumnos</th>
                    <th>Estado</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coaches as $c): ?>
                <tr
                    data-name="<?= strtolower(esc($c['name'])) ?>"
                    data-email="<?= strtolower(esc($c['email'] ?? '')) ?>"
                >
                    <td>
                        <div class="td-user">
                            <div class="td-avatar" style="background:var(--success)"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
                            <div>
                                <div class="td-name"><?= esc($c['name']) ?></div>
                                <div class="td-sub">Entrenador</div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-muted)"><?= esc($c['email'] ?? '—') ?></td>
                    <td style="text-align:center">
                        <span style="font-weight:600;color:var(--text-h)"><?= (int)($c['sessions_count'] ?? 0) ?></span>
                    </td>
                    <td style="text-align:center">
                        <span style="font-weight:600;color:var(--text-h)"><?= (int)($c['players_count'] ?? 0) ?></span>
                    </td>
                    <td>
                        <span class="badge-status <?= esc($c['status'] ?? 'active') ?>">
                            <?= match($c['status'] ?? 'active') {
                                'active'   => 'Activo',
                                'inactive' => 'Inactivo',
                                'banned'   => 'Bloqueado',
                                default    => ucfirst($c['status'] ?? ''),
                            } ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="<?= base_url('entrenadores/' . $c['id']) ?>" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Ver perfil">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= base_url('entrenadores/' . $c['id'] . '/editar') ?>" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" action="<?= base_url('entrenadores/' . $c['id'] . '/eliminar') ?>" style="display:inline" onsubmit="return confirm('¿Dar de baja a <?= esc($c['name']) ?>? Esta acción cambia su estado a inactivo.')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Dar de baja">
                                    <i class="bi bi-person-x-fill"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-person-workspace"></i>
        <h3>Sin entrenadores registrados</h3>
        <p>Añade el primer miembro del equipo técnico para empezar.</p>
        <a href="<?= base_url('entrenadores/nuevo') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-person-plus-fill"></i> Añadir entrenador
        </a>
    </div>
    <?php endif; ?>
</div>

<?= console_debug('EntrenadoresController::index', [
    'total'         => count($coaches ?? []),
    'role_filter'   => 'coach',
    'status_filter' => 'active',
    'coaches'       => array_map(fn($c) => [
        'id'             => $c['id'],
        'name'           => $c['name'],
        'email'          => $c['email'],
        'status'         => $c['status'] ?? 'active',
        'sessions_count' => (int)($c['sessions_count'] ?? 0),
        'players_count'  => (int)($c['players_count'] ?? 0),
    ], $coaches ?? []),
], collapsed: true) ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const searchInput = document.getElementById('search-input');
    const rows        = document.querySelectorAll('#coaches-table tbody tr');
    const totalCount  = document.getElementById('total-count');

    function applyFilters() {
        const q = searchInput.value.toLowerCase().trim();
        let visible = 0;

        rows.forEach(row => {
            const name  = row.dataset.name  || '';
            const email = row.dataset.email || '';
            const show  = !q || name.includes(q) || email.includes(q);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        totalCount.textContent = visible + ' entrenador(es)';
    }

    searchInput.addEventListener('input', applyFilters);
})();
</script>
<?= $this->endSection() ?>
