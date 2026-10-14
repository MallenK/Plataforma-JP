<?= $this->extend('layouts/app') ?>

<?php
$hours  = (int) ($noticePolicy['hours'] ?? 24);
$autoOn = !empty($noticePolicy['auto']);
?>

<?= $this->section('page_content') ?>

<div class="ay-wrap">
    <h2 class="fw-bold mb-1" style="font-size:1.5rem">Cómo funciona</h2>
    <p class="ay-lead">Toca la pregunta que tengas. Si sigues con dudas, escribe a la academia desde <a href="/mensajes">Mensajes</a>.</p>

    <details class="ay-item" open>
        <summary><i class="bi bi-calendar-x-fill ay-ico"></i> ¿Cómo aviso de que no puedo ir a una clase?</summary>
        <div class="ay-body">
            <ol class="ay-steps">
                <li><span>Entra en <a href="/clases"><strong>Clases</strong></a> y toca la clase a la que no puedes ir.</span></li>
                <li><span>Arriba verás tu clase con el botón rojo <strong>«No puedo ir a esta clase»</strong>. Tócalo.</span></li>
                <li><span>Elige el motivo (enfermo, colegio, viaje u otro) y, si quieres, añade una frase.</span></li>
                <li><span>Lee el recuadro «Qué pasará si avisas ahora» y toca <strong>«Sí, avisar que no voy»</strong>.</span></li>
            </ol>
            <div class="ay-ok"><i class="bi bi-check-circle-fill"></i> Sabrás que ha salido bien porque la clase pasará a mostrar «Aviso enviado».</div>
        </div>
    </details>

    <details class="ay-item">
        <summary><i class="bi bi-clock-history ay-ico"></i> ¿Qué pasa si aviso tarde?</summary>
        <div class="ay-body">
            <p>Hay que avisar con al menos <strong><?= $hours ?> horas</strong> de antelación al inicio de la clase.</p>
            <p><strong>Si avisas a tiempo:</strong> no se descuenta nada de tu bono.</p>
            <p><strong>Si avisas con menos de <?= $hours ?> horas:</strong>
                <?= $autoOn
                    ? 'se descuenta una sesión de tu bono en el momento, y se avisa a la administración. Cuando el entrenador cierre la clase decide si esa sesión te la devuelve o no.'
                    : 'queda registrado como aviso tardío y se avisa a la administración. El entrenador decide al cerrar la clase si cuenta como sesión.' ?></p>
            <div class="ay-note"><i class="bi bi-info-circle-fill"></i> Siempre te lo explicamos en pantalla antes de confirmar el aviso.</div>
        </div>
    </details>

    <details class="ay-item">
        <summary><i class="bi bi-arrow-counterclockwise ay-ico"></i> Me he equivocado o puedo ir al final</summary>
        <div class="ay-body">
            <p>Un aviso enviado no se puede deshacer desde la app. Lo que hay que hacer es <strong>escribir a la administración</strong>:</p>
            <ol class="ay-steps">
                <li><span>Abre la clase en <a href="/clases">Clases</a>.</span></li>
                <li><span>Toca <strong>«Decirle algo a mi entrenador»</strong>, escribe qué ha cambiado y envíalo. Lo ven tu entrenador y la administración.</span></li>
            </ol>
            <p>Tu asistencia final siempre la decide el entrenador o la administración.</p>
        </div>
    </details>

    <details class="ay-item">
        <summary><i class="bi bi-chat-dots-fill ay-ico"></i> ¿Cómo le digo algo a mi entrenador?</summary>
        <div class="ay-body">
            <p>En la clase, toca <strong>«Decirle algo a mi entrenador»</strong>. Hay atajos para lo más habitual: <em>Llegaré tarde</em>, <em>Voy con molestias</em> o <em>Tengo una duda</em>.</p>
            <p>Este mensaje no cambia tu asistencia ni tu bono; es solo para que lo sepan.</p>
        </div>
    </details>

    <details class="ay-item">
        <summary><i class="bi bi-clipboard-check-fill ay-ico"></i> ¿Dónde veo lo que ha escrito el entrenador?</summary>
        <div class="ay-body">
            <p>Dentro de cada clase, debajo de tu panel, aparece <strong>«Lo que tu entrenador ha escrito para ti»</strong> (antes y después de la clase) y el mensaje para todo el grupo. También ahí están las fotos y vídeos que suba.</p>
            <p>Cuando haya algo nuevo te llegará una notificación (la campana de arriba).</p>
        </div>
    </details>

    <details class="ay-item">
        <summary><i class="bi bi-ticket-perforated-fill ay-ico"></i> ¿Cómo veo cuántas sesiones me quedan?</summary>
        <div class="ay-body">
            <p>Entra en <a href="/perfil"><strong>tu perfil</strong></a> (tu nombre abajo a la izquierda, en el menú). Allí aparece tu bono con las sesiones que te quedan y hasta cuándo es válido.</p>
        </div>
    </details>

    <details class="ay-item">
        <summary><i class="bi bi-phone-fill ay-ico"></i> Instalar la app en el móvil</summary>
        <div class="ay-body">
            <p>Puedes tener la app como un icono más en tu móvil y recibir avisos al instante.</p>
            <p><a class="mc-btn mc-btn-primary" href="/configuracion/instalar" style="display:inline-flex;width:auto">Ver cómo se instala</a></p>
        </div>
    </details>
</div>

<?= $this->endSection() ?>
