<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Alumnos';
$pageSubtitle = 'Gestión de alumnos registrados';
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('created_password')): ?>
<div class="alert-jp success" style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px">
    <i class="bi bi-check-circle-fill" style="font-size:18px;margin-top:2px;flex-shrink:0"></i>
    <div>
        <strong>Alumno "<?= esc(session()->getFlashdata('created_name')) ?>" creado correctamente.</strong><br>
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
        <h2>Alumnos</h2>
        <p>Listado de todos los alumnos de la academia</p>
    </div>
    <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
    <div class="d-flex gap-2">
        <a href="<?= base_url('alumnos/nuevo') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-person-plus-fill"></i> Nuevo alumno
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card-jp mb-3">
    <div class="card-jp-body py-3">
        <div class="search-bar">
            <div class="input-search">
                <i class="bi bi-search"></i>
                <input type="text" id="search-input" placeholder="Buscar por nombre o email...">
            </div>
            <select class="form-control-jp" id="filter-status" style="width:auto;min-width:140px">
                <option value="">Todos los estados</option>
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
            </select>
            <select class="form-control-jp" id="filter-profile" style="width:auto;min-width:150px">
                <option value="">Todas las fichas</option>
                <option value="1">Con ficha</option>
                <option value="0">Sin ficha</option>
            </select>
        </div>
    </div>
</div>

<!-- Tabla de alumnos -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-people-fill me-2" style="color:var(--accent)"></i>
            Alumnos registrados
        </span>
        <span style="font-size:12px;color:var(--text-muted)" id="total-count">
            <?= count($players ?? []) ?> alumnos
        </span>
    </div>

    <?php if (!empty($players)): ?>
    <div class="table-responsive">
        <table class="table-jp" id="alumnos-table">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Ficha</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($players as $p): ?>
                <tr
                    data-name="<?= strtolower(esc($p['name'])) ?>"
                    data-email="<?= strtolower(esc($p['email'] ?? '')) ?>"
                    data-status="<?= esc($p['status'] ?? 'active') ?>"
                    data-profile="<?= $p['profile_id'] ? '1' : '0' ?>"
                >
                    <td>
                        <div class="td-user">
                            <div class="td-avatar"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                            <div>
                                <div class="td-name"><?= esc($p['name']) ?></div>
                                <?php if (!empty($p['position'])): ?>
                                <div class="td-sub"><?= esc($p['position']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-muted)"><?= esc($p['email'] ?? '—') ?></td>
                    <td>
                        <span class="badge-status <?= esc($p['status'] ?? 'active') ?>">
                            <?= match($p['status'] ?? 'active') {
                                'active'   => 'Activo',
                                'inactive' => 'Inactivo',
                                'banned'   => 'Bloqueado',
                                default    => ucfirst($p['status'] ?? '')
                            } ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($p['profile_id']): ?>
                            <span class="badge-status active"><i class="bi bi-check-circle-fill me-1"></i>Completa</span>
                        <?php else: ?>
                            <span class="badge-status inactive"><i class="bi bi-dash-circle me-1"></i>Sin ficha</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="<?= base_url('alumnos/' . $p['id']) ?>" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Ver perfil">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
                            <a href="<?= base_url('alumnos/' . $p['id'] . '/editar') ?>" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" action="<?= base_url('alumnos/' . $p['id'] . '/eliminar') ?>" style="display:inline" onsubmit="return confirm('¿Dar de baja a <?= esc($p['name']) ?>? Esta acción cambia su estado a inactivo.')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Dar de baja">
                                    <i class="bi bi-person-x-fill"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-people"></i>
        <h3>Sin alumnos registrados</h3>
        <p>Todavía no hay alumnos en el sistema.</p>
        <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
        <a href="<?= base_url('alumnos/nuevo') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-person-plus-fill"></i> Añadir primer alumno
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?= console_debug('PlayerController::index', [
    'total'     => count($players ?? []),
    'role_filter' => 'player',
    'status_filter' => 'active',
    'players'   => array_map(fn($p) => [
        'id'         => $p['id'],
        'name'       => $p['name'],
        'email'      => $p['email'],
        'status'     => $p['status'] ?? 'active',
        'profile_id' => $p['profile_id'] ?? null,
        'position'   => $p['position'] ?? null,
    ], $players ?? []),
], collapsed: true) ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Filtro live de la tabla
(function () {
    const searchInput   = document.getElementById('search-input');
    const filterStatus  = document.getElementById('filter-status');
    const filterProfile = document.getElementById('filter-profile');
    const rows          = document.querySelectorAll('#alumnos-table tbody tr');
    const totalCount    = document.getElementById('total-count');

    function applyFilters() {
        const q       = searchInput.value.toLowerCase().trim();
        const status  = filterStatus.value;
        const profile = filterProfile.value;
        let visible   = 0;

        rows.forEach(row => {
            const name    = row.dataset.name  || '';
            const email   = row.dataset.email || '';
            const rowSt   = row.dataset.status  || '';
            const rowProf = row.dataset.profile || '';

            const matchSearch  = !q || name.includes(q) || email.includes(q);
            const matchStatus  = !status  || rowSt   === status;
            const matchProfile = !profile || rowProf === profile;

            const show = matchSearch && matchStatus && matchProfile;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        totalCount.textContent = visible + ' alumnos';
    }

    searchInput.addEventListener('input', applyFilters);
    filterStatus.addEventListener('change', applyFilters);
    filterProfile.addEventListener('change', applyFilters);
})();
</script>
<?= $this->endSection() ?>
