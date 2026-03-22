<div class="bg-dark text-white p-3" style="width: 250px; min-height: 100vh;">

    <h4 class="mb-4">JP Prep</h4>

    <!-- USER INFO -->
    <div class="mb-4 p-3 bg-secondary rounded">
        <div class="fw-bold"><?= session('name') ?></div>
        <div class="small text-light"><?= session('role') ?></div>
    </div>

    <ul class="nav flex-column">

        <li><a href="/dashboard" class="nav-link text-white">Dashboard</a></li>
        <li><a href="/alumnos" class="nav-link text-white">Alumnos</a></li>
        <li><a href="/entrenadores" class="nav-link text-white">Entrenadores</a></li>
        <li><a href="/torneos" class="nav-link text-white">Torneos</a></li>
        <li><a href="/documentacion" class="nav-link text-white">Documentación</a></li>
        <li><a href="/configuracion" class="nav-link text-white">Configuración</a></li>
        <li><a href="/perfil" class="nav-link text-white">Perfil</a></li>

        <li class="mt-4">
            <a href="/logout" class="nav-link text-danger">Logout</a>
        </li>

    </ul>

</div>