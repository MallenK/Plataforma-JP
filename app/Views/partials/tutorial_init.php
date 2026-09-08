<?php
/**
 * Tutorial interactivo — inicialización.
 * Se incluye al final del layout app.php, antes de </body>.
 *
 * Auto-muestra el tutorial si el usuario no lo ha visto nunca
 * (la flag se guarda en localStorage por seguridad del lado cliente).
 */
$tutorialRole = session('role') ?? 'player';

// Normalizar: 'alumno' → 'player' para el tutorial
if ($tutorialRole === 'alumno') {
    $tutorialRole = 'player';
}

// Validar que el rol existe en el tutorial
$validRoles = ['player', 'coach', 'staff', 'admin', 'superadmin'];
if (!in_array($tutorialRole, $validRoles)) {
    $tutorialRole = 'player';
}

// Vertical activo (solo tiene efecto en demo_mode(); ver
// app/Helpers/vertical_helper.php). Fuera de la demo, demo_current_vertical()
// siempre devuelve 'futbol' — el guion original de JP Preparation — así que
// este partial se comporta igual que antes en producción real.
// Cada vertical tiene su propio guion completo en tutorial.js
// (STEPS_BY_VERTICAL), no una simple sustitución de palabras.
$tutorialVertical = demo_current_vertical();
?>

<script src="<?= base_url('assets/js/tutorial.js') ?>"></script>
<script>
(function () {
    const role     = <?= json_encode($tutorialRole) ?>;
    const vertical = <?= json_encode($tutorialVertical) ?>;
    const key      = 'jp_tutorial_seen_' + role;
    const seen     = localStorage.getItem(key) === '1';
    JPTutorial.init(role, !seen, vertical);
})();
</script>
