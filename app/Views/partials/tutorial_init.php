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

// Vocabulario del vertical activo (solo tiene efecto en demo_mode(); ver
// app/Helpers/vertical_helper.php). El contenido del tutorial se escribió
// pensando en fútbol, así que aquí solo pasamos las palabras que cambian de
// negocio a negocio (alumno/entrenador, singular/plural/minúscula) para que
// tutorial.js las sustituya al vuelo — el resto del guion no se toca.
$tutorialVertWords = null;
if (demo_mode()) {
    $tutorialVertWords = [
        'alumno_plural'        => demo_label('alumno_plural'),
        'alumno_plural_lc'     => mb_strtolower(demo_label('alumno_plural')),
        'alumno'                => demo_label('alumno'),
        'alumno_lc'             => mb_strtolower(demo_label('alumno')),
        'entrenador_plural'    => demo_label('entrenador_plural'),
        'entrenador_plural_lc' => mb_strtolower(demo_label('entrenador_plural')),
        'entrenador'            => demo_label('entrenador'),
        'entrenador_lc'         => mb_strtolower(demo_label('entrenador')),
    ];
}
?>

<script src="<?= base_url('assets/js/tutorial.js') ?>"></script>
<script>
(function () {
    const role  = <?= json_encode($tutorialRole) ?>;
    const words = <?= json_encode($tutorialVertWords) ?>;
    const key   = 'jp_tutorial_seen_' + role;
    const seen  = localStorage.getItem(key) === '1';
    JPTutorial.init(role, !seen, words);
})();
</script>
