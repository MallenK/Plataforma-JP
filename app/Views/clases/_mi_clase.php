<?php
/**
 * «Tu clase» — panel del ALUMNO en la ficha de una clase.
 * Pensado para familias poco familiarizadas con la tecnología: una pregunta,
 * un botón grande, y antes de confirmar se explica qué pasará.
 *
 * Variables: $session, $myPlayer, $statusColor, $attendanceMap,
 *            $noticePolicy ['auto' => bool, 'hours' => int]
 */
use App\Services\ClasesService;

$hours      = (int) ($noticePolicy['hours'] ?? 24);
$autoOn     = !empty($noticePolicy['auto']);
$scheduled  = $session['status'] === 'scheduled';
$noticed    = !empty($myPlayer['student_noted_at']) || !empty($myPlayer['student_note']);
$deadline   = ClasesService::absenceNoticeDeadline($session, $hours);
$late       = ClasesService::isLateAbsenceNotice($session, null, $hours);
$deducted   = !empty($myPlayer['bono_deducted_at']);
$att        = $myPlayer['attendance'] ?? 'pending';
[$aLabel, $aColor, $aIcon] = $attendanceMap[$att] ?? $attendanceMap['pending'];

$ts       = strtotime($session['session_date']);
$dayNames = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$today    = date('Y-m-d');
$rel      = $session['session_date'] === $today ? 'Hoy'
          : ($session['session_date'] === date('Y-m-d', strtotime('+1 day')) ? 'Mañana' : $dayNames[(int) date('N', $ts)]);
$place    = $session['location_name'] ?? $session['location_custom'] ?? null;
$who      = $myPlayer['coach_name'] ?? ($session['coaches'][0]['name'] ?? null);
$sid      = (int) $session['id'];

$reasonKinds = [
    'enfermo' => ['bi-thermometer-half', 'Enfermo o lesionado'],
    'colegio' => ['bi-backpack2',        'Colegio o exámenes'],
    'viaje'   => ['bi-suitcase-lg',      'Viaje o familia'],
    'otro'    => ['bi-three-dots',       'Otro motivo'],
];
?>
<section class="mc-card" aria-labelledby="mc-title-<?= $sid ?>" style="--mc-accent:<?= esc($statusColor, 'attr') ?>">
    <!-- Lo primero que ve la familia: avisar de que no va a la clase (o el estado del aviso) -->
    <div class="mc-aviso">
    <?php if ($session['status'] === 'cancelled'): ?>
        <div class="mc-state is-info"><i class="bi bi-x-circle-fill"></i>
            <div><strong>Esta clase se ha cancelado.</strong><p>No tienes que hacer nada.</p></div></div>

    <?php elseif (!$scheduled): ?>
        <div class="mc-state is-info"><i class="bi bi-check-circle-fill"></i>
            <div><strong>Clase cerrada.</strong>
                <p>Tu asistencia: <span class="badge-status" style="background:<?= $aColor ?>22;color:<?= $aColor ?>;border:1px solid <?= $aColor ?>44"><i class="bi <?= $aIcon ?> me-1"></i><?= $aLabel ?></span></p></div></div>

    <?php elseif ($noticed): ?>
        <div class="mc-state <?= $late ? 'is-warn' : 'is-ok' ?>" role="status">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>Aviso enviado: no vas a venir.</strong>
                <p>Tu entrenador y la administración ya lo saben<?= !empty($myPlayer['student_noted_at']) ? ' (' . date('d/m H:i', strtotime($myPlayer['student_noted_at'])) . ')' : '' ?>.</p>
                <?php if (!empty($myPlayer['student_note'])): ?><p class="mc-quote">“<?= esc($myPlayer['student_note']) ?>”</p><?php endif; ?>
                <?php if ($deducted && $late): ?>
                    <p><strong>Aviso tardío:</strong> se ha descontado una sesión de tu bono. Tu entrenador lo revisará cuando cierre la clase.</p>
                <?php elseif ($late): ?>
                    <p><strong>Aviso tardío:</strong> tu entrenador decidirá cuando cierre la clase.</p>
                <?php endif; ?>
                <p class="mc-hint">¿Te has equivocado o ha cambiado algo? Escribe un mensaje abajo y la administración te ayudará.</p>
            </div>
        </div>

    <?php else: ?>
        <details class="mc-cancel">
            <summary class="mc-btn mc-btn-danger" role="button">
                <i class="bi bi-calendar-x-fill"></i> No puedo ir
            </summary>

            <form action="/clases/<?= $sid ?>/ausencia" method="POST" class="mc-form">
                <?= csrf_field() ?>
                <fieldset class="mc-step">
                    <legend><span>1</span> ¿Por qué no puedes venir?</legend>
                    <div class="mc-chips">
                        <?php foreach ($reasonKinds as $key => [$icon, $label]): ?>
                        <label class="mc-chip">
                            <input type="radio" name="reason_kind" value="<?= $key ?>" <?= $key === 'enfermo' ? 'required' : '' ?>>
                            <span><i class="bi <?= $icon ?>"></i><?= $label ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <label for="mc-note-<?= $sid ?>" class="mc-label">Quieres añadir algo <small>(opcional)</small></label>
                    <textarea id="mc-note-<?= $sid ?>" name="student_note" class="form-control-jp" rows="2" maxlength="300"
                              placeholder="Por ejemplo: tiene fiebre desde ayer"></textarea>
                </fieldset>

                <fieldset class="mc-step">
                    <legend><span>2</span> Qué pasará si avisas ahora</legend>
                    <?php if ($late): ?>
                    <div class="mc-state is-warn">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Faltan menos de <?= $hours ?> horas para la clase.</strong>
                            <p><?php if ($autoOn): ?>Se te descontará <strong>una sesión de tu bono</strong> hasta que tu entrenador cierre la clase y decida si vuelve a tu bono.<?php else: ?>Se registrará como <strong>aviso tardío</strong> y tu entrenador decidirá si cuenta como sesión.<?php endif; ?></p>
                            <p>Se avisará también a la administración.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mc-state is-ok">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <strong>Avisas con tiempo.</strong>
                            <p>No se descuenta ninguna sesión de tu bono.<?php if ($deadline): ?> Puedes avisar hasta el <?= $deadline->format('d/m') ?> a las <?= $deadline->format('H:i') ?>.<?php endif; ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <p class="mc-hint"><i class="bi bi-info-circle"></i> Una vez enviado no se puede deshacer desde aquí; si cambia algo, escribe un mensaje a la administración.</p>
                </fieldset>

                <div class="mc-actions">
                    <button type="submit" class="mc-btn mc-btn-danger"><i class="bi bi-send-fill"></i> Sí, avisar que no voy</button>
                    <button type="button" class="mc-btn mc-btn-ghost" onclick="this.closest('details').open=false">Volver</button>
                </div>
            </form>
        </details>
    <?php endif; ?>
    </div>

    <div class="mc-head">
        <div class="mc-when">
            <span class="mc-rel"><?= esc($rel) ?></span>
            <span class="mc-date"><?= date('d/m/Y', $ts) ?></span>
        </div>
        <h2 class="mc-title" id="mc-title-<?= $sid ?>">
            <?= esc(substr($session['start_time'], 0, 5)) ?> – <?= esc(substr($session['end_time'], 0, 5)) ?>
        </h2>
        <ul class="mc-facts">
            <?php if ($place): ?><li><i class="bi bi-geo-alt-fill"></i><?= esc($place) ?></li><?php endif; ?>
            <?php if ($who): ?><li><i class="bi bi-person-workspace"></i><?= esc($who) ?></li><?php endif; ?>
        </ul>
    </div>


    <?php if ($scheduled): ?>
    <details class="mc-msg">
        <summary><i class="bi bi-chat-dots-fill"></i> Decirle algo a mi entrenador</summary>
        <form action="/clases/<?= $sid ?>/mensaje" method="POST" class="mc-form">
            <?= csrf_field() ?>
            <div class="mc-chips mc-chips-sm" data-mc-quick="#mc-msg-<?= $sid ?>">
                <button type="button" class="mc-quick" data-text="Llegaré tarde a la clase.">Llegaré tarde</button>
                <button type="button" class="mc-quick" data-text="Voy con molestias: ">Voy con molestias</button>
                <button type="button" class="mc-quick" data-text="Tengo una duda: ">Tengo una duda</button>
            </div>
            <textarea id="mc-msg-<?= $sid ?>" name="student_message" class="form-control-jp" rows="3" required
                      maxlength="<?= ClasesService::STUDENT_MESSAGE_MAX ?>" placeholder="Escribe aquí tu mensaje…"></textarea>
            <p class="mc-hint">Lo verán tu entrenador y la administración. No cambia tu asistencia ni tu bono.</p>
            <p class="mc-hint"><i class="bi bi-bandaid-fill"></i> ¿Una lesión o algo médico que deban saber siempre? Añádelo en <a href="/alumno">tus notas médicas</a>.</p>
            <button type="submit" class="mc-btn mc-btn-primary"><i class="bi bi-send-fill"></i> Enviar mensaje</button>
        </form>
        <?php if (!empty($myPlayer['student_message'])): ?>
        <p class="mc-quote" style="margin-top:10px">Último mensaje enviado
            <?= !empty($myPlayer['student_message_at']) ? '(' . date('d/m H:i', strtotime($myPlayer['student_message_at'])) . ')' : '' ?>:
            “<?= esc($myPlayer['student_message']) ?>”</p>
        <?php endif; ?>
    </details>
    <script>
    document.querySelectorAll('[data-mc-quick]').forEach(function (box) {
        var ta = document.querySelector(box.getAttribute('data-mc-quick'));
        box.querySelectorAll('.mc-quick').forEach(function (b) {
            b.addEventListener('click', function () { if (ta) { ta.value = b.getAttribute('data-text'); ta.focus(); } });
        });
    });
    </script>
    <?php endif; ?>

    <p class="mc-help"><a href="/ayuda"><i class="bi bi-question-circle-fill"></i> ¿Necesitas ayuda para usar la app?</a></p>
</section>
