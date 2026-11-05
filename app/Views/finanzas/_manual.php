<?php
/**
 * Manual de uso de Finanzas (contenido). Se usa en la pestaña Ayuda y en la
 * versión imprimible / PDF. $img = función que devuelve la URL de una captura.
 * $videos = vídeos tutoriales disponibles (solo en la pestaña Ayuda; vacío al imprimir).
 * Lenguaje llano: lo lee alguien de la academia sin contexto técnico.
 * Estructura: 1) empezar · 2) vídeos · 3) guías paso a paso · 4) reglas · 5) referencia por pestaña · 6) FAQ.
 */
$img = $img ?? static fn(string $n) => base_url('finanzas/ayuda/img/' . $n);
$videos = $videos ?? [];
$vid = $vid ?? static fn(string $n) => base_url('finanzas/ayuda/video/' . $n);
$shot = static function (string $file, string $caption) use ($img) {
    return '<figure class="fm-shot"><img src="' . esc($img($file), 'attr') . '" alt="' . esc($caption, 'attr') . '" loading="lazy"><figcaption>' . esc($caption) . '</figcaption></figure>';
};
?>
<style>
.fin-manual { color:#1e293b; font-size:15px; line-height:1.65; max-width:960px; }
.fin-manual h2 { font-size:24px; font-weight:800; color:#0f172a; margin:40px 0 10px; padding-top:10px; border-top:2px solid #e2e8f0; }
.fin-manual h2:first-of-type { border-top:0; margin-top:8px; }
.fin-manual h3 { font-size:18px; font-weight:700; color:#0f172a; margin:26px 0 8px; }
.fin-manual p { margin:0 0 12px; }
.fin-manual ul, .fin-manual ol { margin:0 0 14px; padding-left:22px; }
.fin-manual li { margin-bottom:6px; }
.fin-manual .fm-box { border-radius:10px; padding:14px 16px; margin:14px 0; font-size:14.5px; }
.fin-manual .fm-tip { background:#eff6ff; border:1px solid #bfdbfe; }
.fin-manual .fm-warn { background:#fffbeb; border:1px solid #fde68a; }
.fin-manual .fm-ok { background:#ecfdf5; border:1px solid #a7f3d0; }
.fin-manual .fm-rules { background:#faf5ff; border:1px solid #e9d5ff; }
.fin-manual .fm-box strong:first-child { display:block; margin-bottom:4px; color:#0f172a; }
.fin-manual table { width:100%; border-collapse:collapse; margin:10px 0 16px; font-size:14px; }
.fin-manual th, .fin-manual td { border:1px solid #e2e8f0; padding:8px 10px; text-align:left; vertical-align:top; }
.fin-manual th { background:#f1f5f9; font-weight:700; color:#0f172a; }
.fin-manual .fm-shot { margin:14px 0 20px; }
.fin-manual .fm-shot img { width:100%; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 2px 8px rgba(15,23,42,.08); }
.fin-manual .fm-shot figcaption { font-size:12.5px; color:#64748b; margin-top:6px; text-align:center; }
.fin-manual .fm-steps { counter-reset:step; list-style:none; padding-left:0; }
.fin-manual .fm-steps > li { counter-increment:step; position:relative; padding-left:38px; margin-bottom:10px; }
.fin-manual .fm-steps > li::before { content:counter(step); position:absolute; left:0; top:1px; width:26px; height:26px; border-radius:50%;
    background:#1d4ed8; color:#fff; font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; }
.fin-manual .fm-toc { columns:2; column-gap:32px; font-size:14px; }
.fin-manual .fm-toc a, .fin-manual .fm-go a { color:#1d4ed8; text-decoration:none; }
.fin-manual kbd, .fin-manual .fm-btn { display:inline-block; font-family:inherit; font-size:13px; font-weight:700; padding:1px 8px; border-radius:6px; background:#f1f5f9; border:1px solid #cbd5e1; color:#0f172a; white-space:nowrap; }
.fin-manual .fm-q { font-weight:700; color:#0f172a; margin:16px 0 4px; }
/* Tarjeta de guía: cuándo + pasos + qué verás */
.fin-manual .fm-guide { border:1px solid #cbd5e1; border-radius:12px; margin:18px 0 26px; overflow:hidden; background:#fff; page-break-inside:avoid; }
.fin-manual .fm-guide > header { background:#0f172a; color:#fff; padding:12px 16px; font-weight:800; font-size:17px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.fin-manual .fm-guide > header small { font-weight:600; font-size:12px; color:#93c5fd; margin-left:auto; }
.fin-manual .fm-guide .fm-when { background:#f1f5f9; padding:10px 16px; font-size:14px; border-bottom:1px solid #e2e8f0; }
.fin-manual .fm-guide .fm-body { padding:14px 16px 4px; }
.fin-manual .fm-guide .fm-result { background:#ecfdf5; border-top:1px solid #a7f3d0; padding:10px 16px; font-size:14px; }
.fin-manual .fm-guide .fm-result b:first-child, .fin-manual .fm-guide .fm-when b:first-child { color:#0f172a; }
/* Flujo del dinero */
.fin-manual .fm-flow { display:flex; flex-wrap:wrap; gap:8px; align-items:stretch; margin:14px 0 18px; }
.fin-manual .fm-flow > div { flex:1 1 140px; border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px; background:#fff; font-size:13.5px; }
.fin-manual .fm-flow > div b { display:block; font-size:15px; color:#0f172a; margin-bottom:2px; }
.fin-manual .fm-flow > i { align-self:center; color:#94a3b8; font-style:normal; font-weight:800; font-size:20px; }
/* Vídeos */
.fin-manual .fm-videos { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; margin:14px 0 8px; }
.fin-manual .fm-video { border:1px solid #cbd5e1; border-radius:12px; overflow:hidden; background:#fff; }
.fin-manual .fm-video video { width:100%; display:block; background:#0b1220; aspect-ratio:16/9; }
.fin-manual .fm-video div { padding:10px 12px; font-size:13.5px; line-height:1.4; }
.fin-manual .fm-video b { display:block; font-size:15px; color:#0f172a; }
.fin-manual .fm-video span { color:#64748b; font-size:12.5px; }
.fin-manual .fm-do { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.fin-manual .fm-do > div { border-radius:10px; padding:12px 14px; font-size:14px; }
.fin-manual .fm-do ul { margin:6px 0 0; }
.fin-manual .fm-do .yes { background:#ecfdf5; border:1px solid #a7f3d0; } .fin-manual .fm-do .no { background:#fef2f2; border:1px solid #fecaca; }
@media (max-width: 640px) { .fin-manual .fm-toc { columns:1; } .fin-manual { font-size:14.5px; } .fin-manual .fm-do { grid-template-columns:1fr; } }
</style>

<div class="fin-manual">

<p style="font-size:16px">Este manual te lleva de la mano por la sección <strong>Finanzas</strong> de la plataforma de JP Preparation. No hace falta saber de contabilidad: te explica <strong>qué es cada cosa, qué botón pulsar y qué vas a ver después</strong>.</p>

<div class="fm-box fm-tip"><strong>Cómo usar este manual</strong>
    <ul style="margin:4px 0 0">
        <li>¿Tienes prisa? Ve a <a href="#fm-queres">«¿Qué quieres hacer?»</a> y salta directo a tu tarea.</li>
        <li>¿Prefieres ver cómo se hace? Mira los <a href="#fm-videos">vídeos tutoriales</a>.</li>
        <li>En casi todas las pantallas verás un símbolo <span class="fm-btn">?</span>: pasa el ratón por encima (o tócalo en el móvil) y te explica qué es.</li>
        <li>Los <strong>importes salen difuminados</strong> por privacidad. Pulsa el ojo que hay junto a un importe (o el de la barra superior) para verlos.</li>
    </ul>
</div>

<h3>Contenido</h3>
<ol class="fm-toc">
    <li><a href="#fm-que-es">Qué es Finanzas y quién lo ve</a></li>
    <li><a href="#fm-ideas">Las ideas básicas (con un ejemplo)</a></li>
    <li><a href="#fm-queres">¿Qué quieres hacer?</a></li>
    <li><a href="#fm-videos">Vídeos tutoriales</a></li>
    <li><a href="#fm-guias">Guías paso a paso</a></li>
    <li><a href="#fm-auto">Descuento automático de sesiones</a></li>
    <li><a href="#fm-uso">Reglas de uso (qué hacer y qué no)</a></li>
    <li><a href="#fm-reglas">Reglas del sistema</a></li>
    <li><a href="#fm-mapa">Mapa de pestañas</a></li>
    <li><a href="#fm-pestanas">Cada pestaña en detalle</a></li>
    <li><a href="#fm-rutina">Rutina recomendada</a></li>
    <li><a href="#fm-faq">Preguntas frecuentes</a></li>
</ol>

<h2 id="fm-que-es">1. Qué es Finanzas y quién lo ve</h2>
<p>Finanzas es el lugar donde se ve y se controla <strong>todo el dinero de la academia</strong>: lo que se vende, lo que se cobra, lo que se gasta y lo que todavía se debe.</p>
<ul>
    <li>Se alimenta <strong>sola</strong> con lo que ya hacéis: vender bonos, pasar lista, descontar sesiones. Solo hay que añadir a mano los <strong>cobros</strong> y los <strong>gastos</strong>.</li>
    <li><strong>Nada se borra.</strong> Si algo está mal, se <em>anula</em> indicando el motivo: queda tachado y visible, y las cuentas se recalculan solas.</li>
    <li>Entrenadores, staff y alumnos <strong>no</strong> ven Finanzas. Cada alumno solo ve sus propios pagos en su perfil («Mis pagos»).</li>
</ul>
<table>
    <tr><th style="width:26%">Nivel de acceso</th><th>Qué ve esa persona</th></tr>
    <tr><td><strong>Acceso total</strong></td><td>Todo Finanzas: importes, cobros, gastos, cuentas de alumnos, análisis y exportaciones. Lo tiene el administrador principal y quienes él autorice (Configuración › «Quién ve las finanzas»).</td></tr>
    <tr><td><strong>Solo revisión</strong></td><td>El resto de administradores. Solo ven <em>Revisión de clases</em> (listas sin cerrar, clases sin entrenador…), <strong>sin ningún importe</strong>.</td></tr>
    <tr><td><strong>Sin acceso</strong></td><td>Entrenadores, staff y alumnos.</td></tr>
</table>

<h2 id="fm-ideas">2. Las ideas básicas (con un ejemplo)</h2>
<p>Para entender las pantallas solo hace falta tener claras estas palabras. Lo explicamos con una alumna inventada, Laura:</p>
<div class="fm-flow">
    <div><b>1 · Venta</b>Laura compra un bono de 4 sesiones por 180 €.</div><i>→</i>
    <div><b>2 · Cobro</b>Paga 100 € por Bizum. Debe 80 €.</div><i>→</i>
    <div><b>3 · Clase</b>Viene a clase: se gasta 1 sesión (45 €).</div><i>→</i>
    <div><b>4 · Saldo</b>Le quedan 3 sesiones pagadas sin dar.</div>
</div>
<ol class="fm-steps">
    <li>Laura compra un <strong>bono de 4 sesiones por 180 €</strong>. Eso es una <strong>venta</strong> (también la llamamos <em>cargo</em>): Laura se compromete a pagar 180 €.</li>
    <li>Laura paga <strong>100 € por Bizum</strong> hoy. Eso es un <strong>cobro</strong>. Ahora <strong>debe 80 €</strong> (pendiente de cobro).</li>
    <li>Laura viene a su primera clase. Al pasar lista se descuenta 1 sesión de su bono: es <strong>servicio prestado</strong> por valor de 45 € (180 € ÷ 4 sesiones).</li>
    <li>Le quedan 3 sesiones: son <strong>sesiones pagadas sin dar</strong>, algo que la academia todavía le debe a ella.</li>
    <li>Si su bono caduca y le quedaba 1 sesión sin usar, esos 45 € se dan por ganados pero aparecen como <strong>caducado sin usar</strong>.</li>
</ol>
<table>
    <tr><th style="width:30%">Palabra</th><th>Qué significa</th></tr>
    <tr><td><strong>Vendido</strong></td><td>Lo que los alumnos han comprado (bonos y cargos sueltos), lo hayan pagado o no.</td></tr>
    <tr><td><strong>Cobrado</strong></td><td>El dinero que ha entrado de verdad: efectivo, Bizum, transferencia, tarjeta…</td></tr>
    <tr><td><strong>Pendiente de cobro</strong></td><td>Lo vendido que aún no se ha pagado.</td></tr>
    <tr><td><strong>Servicio prestado</strong></td><td>El valor de las clases ya dadas y descontadas de un bono.</td></tr>
    <tr><td><strong>Sesiones pagadas sin dar</strong></td><td>Sesiones que los alumnos tienen compradas y aún no han usado.</td></tr>
    <tr><td><strong>Caducado sin usar</strong></td><td>Sesiones de bonos que caducaron sin usarse. Se dan por ganadas, pero se señalan.</td></tr>
    <tr><td><strong>Gastos</strong></td><td>Lo que gasta la academia: material, reparaciones, alquiler de campos, entrenadores…</td></tr>
    <tr><td><strong>Resultado</strong></td><td>Cobrado menos gastos: el dinero que queda en el periodo.</td></tr>
    <tr><td><strong>Clase sin bono</strong></td><td>Una clase que se dio pero no se descontó de ningún bono. Se salda cuando el alumno tiene bono.</td></tr>
    <tr><td><strong>A favor</strong></td><td>Cuando un alumno ha pagado de más. Se le aplica solo en su próximo bono.</td></tr>
    <tr><td><strong>Anular</strong></td><td>Corregir un error sin borrarlo: queda tachado, con el motivo, y no cuenta.</td></tr>
</table>

<h2 id="fm-queres">3. ¿Qué quieres hacer?</h2>
<p>Elige tu tarea y pulsa para ir a su guía paso a paso.</p>
<table class="fm-go">
    <tr><th style="width:55%">Quiero…</th><th>Guía</th></tr>
    <tr><td>Vender un bono a un alumno (con o sin descuento)</td><td><a href="#g-vender">G1 · Vender un bono</a></td></tr>
    <tr><td>Dar un bono nuevo a un alumno que ya tiene clases dadas o apuntadas sin bono</td><td><a href="#g-saldar">G2 · Saldar clases al dar un bono</a></td></tr>
    <tr><td>Apuntar un pago (completo o a plazos)</td><td><a href="#g-cobrar">G3 · Registrar un cobro</a></td></tr>
    <tr><td>Saber quién me debe dinero</td><td><a href="#g-deben">G4 · Ver quién debe</a></td></tr>
    <tr><td>Apuntar un gasto de la academia</td><td><a href="#g-gasto">G5 · Registrar un gasto</a></td></tr>
    <tr><td>Cobrar algo que no es un bono (sesión suelta, material…)</td><td><a href="#g-cargo">G6 · Añadir un cargo suelto</a></td></tr>
    <tr><td>Corregir un error</td><td><a href="#g-anular">G7 · Anular y corregir</a></td></tr>
    <tr><td>Ver todo lo de un alumno</td><td><a href="#g-alumno">G8 · Cuenta e historial de un alumno</a></td></tr>
    <tr><td>Saber qué pasa con el dinero cuando un alumno falta</td><td><a href="#fm-auto">Descuento automático</a> · <a href="#g-devolver">G9 · Devolver una sesión</a></td></tr>
    <tr><td>Dejar las cuentas al día cada semana</td><td><a href="#g-revision">G10 · Revisión semanal</a></td></tr>
    <tr><td>Cerrar el mes y pasar los números al gestor</td><td><a href="#g-cierre">G11 · Cierre de mes y exportar</a></td></tr>
</table>

<h2 id="fm-videos">4. Vídeos tutoriales</h2>
<?php if ($videos): ?>
<p>Vídeos cortos, sin sonido y con los pasos escritos en pantalla. Empieza por el general y después ve al de lo que necesites.</p>
<div class="fm-videos">
    <?php foreach ($videos as $v): ?>
    <div class="fm-video">
        <video controls preload="none" playsinline src="<?= esc($vid($v['file']), 'attr') ?>"></video>
        <div><b><?= esc($v['title']) ?></b><span><?= esc($v['desc']) ?></span></div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<p>Los vídeos tutoriales se ven en la pestaña <strong>Ayuda</strong> de Finanzas. Esta versión impresa solo incluye los pasos escritos.</p>
<?php endif; ?>

<h2 id="fm-guias">5. Guías paso a paso</h2>
<p>Cada guía dice <strong>cuándo usarla</strong>, <strong>qué pulsar</strong> y <strong>qué verás al terminar</strong>.</p>

<section class="fm-guide" id="g-vender">
    <header>G1 · Vender un bono <small>Bonos</small></header>
    <div class="fm-when"><b>Cuándo:</b> un alumno compra un bono nuevo (o se lo regalas / se lo cobras después).</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>En el menú de la izquierda entra en <span class="fm-btn">Bonos</span> y pulsa <span class="fm-btn">Asignar bono</span>. Se abre la ventana «Nuevo bono».</li>
        <li>Elige el <strong>tipo de bono</strong>. Verás su precio justo debajo.</li>
        <li>Elige el <strong>jugador</strong>. Si tiene clases sin bono o programadas, saldrá una lista (mira la guía G2).</li>
        <li>Si hay <strong>descuento</strong>, escribe el importe (<kbd>10</kbd>) o el porcentaje (<kbd>10%</kbd>) y el motivo (hermanos, promoción…). El precio final se recalcula al instante.</li>
        <li>Si paga en ese momento, marca <strong>Cobrado ahora</strong>, elige cómo paga (efectivo, Bizum…) y revisa el importe. Puede ser solo una parte. Si no paga, déjalo sin marcar.</li>
        <li>Pulsa <span class="fm-btn">Crear bono</span>.</li>
    </ol></div>
    <div class="fm-result"><b>Qué verás:</b> el bono aparece en la lista de Bonos, el alumno recibe un aviso de «Tienes un bono nuevo», y en <em>Finanzas › Movimientos</em> sale la venta (y el cobro, si lo marcaste). Si no cobraste, queda como <em>pendiente de cobro</em>.</div>
</section>

<section class="fm-guide" id="g-saldar">
    <header>G2 · Saldar clases al dar un bono <small>Bonos</small></header>
    <div class="fm-when"><b>Cuándo:</b> el alumno vino a clase (o está apuntado y confirmado) pero <em>no tenía bono con saldo</em>. Al darle un bono nuevo, puedes hacer que esas clases se descuenten de él.</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>En <span class="fm-btn">Bonos › Asignar bono</span>, elige el tipo de bono y el jugador.</li>
        <li>Si el alumno tiene clases pendientes aparece un recuadro naranja con <strong>casillas</strong>, en dos grupos:
            <ul>
                <li><strong>Ya dadas, sin bono</strong> (vienen marcadas): clases cerradas que se dieron sin descontar.</li>
                <li><strong>Programadas, confirmadas, sin descontar</strong> (vienen sin marcar): clases que aún no se han cerrado, hayan pasado ya de fecha o no.</li>
            </ul></li>
        <li><strong>Marca las que quieras saldar</strong> con este bono. Debajo ves el resumen: «de un bono de 4 sesiones, quedarán 2».</li>
        <li>Pulsa <span class="fm-btn">Crear bono y saldar N clases</span>. Si no marcas ninguna, no se descuenta nada.</li>
    </ol>
    <p>También puedes hacerlo <strong>después</strong>: abre el bono desde la lista y usa el cuadro «Clases para saldar con este bono».</p></div>
    <div class="fm-result"><b>Qué verás:</b> un mensaje con cuántas clases se saldaron. Se gasta 1 sesión por clase, de la más antigua a la más reciente, y se para solo cuando el bono se queda sin saldo. Las no marcadas siguen pendientes. Todo queda en el historial del bono y se puede devolver.</div>
</section>
<div class="fm-box fm-warn"><strong>Ojo con las clases programadas</strong>Al saldar una clase que aún no se ha dado, la sesión sale del bono por adelantado. Si luego el alumno avisa a tiempo y no viene, se puede <em>devolver</em> (guía G9). Las clases posteriores a la caducidad del bono se saltan solas.</div>

<section class="fm-guide" id="g-cobrar">
    <header>G3 · Registrar un cobro <small>Finanzas › Cobros</small></header>
    <div class="fm-when"><b>Cuándo:</b> un alumno te paga (todo o una parte).</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>Entra en <span class="fm-btn">Finanzas › Cobros</span> (o en la cuenta del alumno).</li>
        <li>En «Registrar cobro» elige el <strong>alumno</strong>, escribe el <strong>importe</strong>, la <strong>fecha</strong> y el <strong>medio de pago</strong>.</li>
        <li>Pulsa <span class="fm-btn">Guardar cobro</span>.</li>
    </ol></div>
    <div class="fm-result"><b>Qué verás:</b> el cobro entra en la lista de abajo y baja lo que el alumno debe. Se aplica a lo <strong>más antiguo</strong> que deba. Si paga de más, lo que sobra queda <em>a su favor</em> y se usa solo en su próximo bono. Si paga a plazos, registra un cobro por cada pago.</div>
</section>

<section class="fm-guide" id="g-deben">
    <header>G4 · Ver quién debe <small>Finanzas › Cobros</small></header>
    <div class="fm-when"><b>Cuándo:</b> quieres reclamar pagos pendientes.</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>Entra en <span class="fm-btn">Finanzas › Cobros</span>.</li>
        <li>A la derecha, <strong>«Quién debe ahora»</strong> lista a los alumnos con algo pendiente, de más a menos.</li>
        <li>Pulsa en un alumno para abrir su cuenta, o en el botón de cobrar si ya te ha pagado.</li>
    </ol>
    <p>Otra forma: <span class="fm-btn">Finanzas › Alumnos</span> con el filtro <em>«Solo los que deben»</em>.</p></div>
    <div class="fm-result"><b>Qué verás:</b> el nombre, cuánto debe y desde cuándo. En el Resumen, la tarjeta «Pendiente de cobro» tiene un enlace «Ver quién debe».</div>
</section>

<section class="fm-guide" id="g-gasto">
    <header>G5 · Registrar un gasto <small>Finanzas › Gastos</small></header>
    <div class="fm-when"><b>Cuándo:</b> la academia paga algo: material, alquiler de campo, reparación, entrenadores…</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>Entra en <span class="fm-btn">Finanzas › Gastos</span>.</li>
        <li>Escribe el <strong>importe</strong>, la <strong>fecha</strong> y elige la <strong>categoría</strong>. Añade una descripción (por ejemplo, «20 balones»).</li>
        <li>Si el gasto es de un <strong>entrenador</strong> o de una <strong>sede</strong>, elígelos (opcional).</li>
        <li>Si tienes el ticket o la factura, <strong>adjunta una foto</strong> (opcional, pero muy recomendable).</li>
        <li>Pulsa <span class="fm-btn">Guardar gasto</span>.</li>
    </ol></div>
    <div class="fm-result"><b>Qué verás:</b> el gasto en la lista, con un botón <span class="fm-btn">Ver</span> para abrir el ticket. Baja el <em>Resultado</em> del mes y suma en la categoría correspondiente.</div>
</section>

<section class="fm-guide" id="g-cargo">
    <header>G6 · Añadir un cargo suelto <small>Finanzas › Alumnos › cuenta</small></header>
    <div class="fm-when"><b>Cuándo:</b> quieres cobrar algo que no es un bono: una sesión suelta, equipación, un torneo…</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>Entra en <span class="fm-btn">Finanzas › Alumnos</span> y pulsa el nombre del alumno.</li>
        <li>A la derecha, en «Añadir cargo», escribe el concepto y el importe.</li>
        <li>Pulsa <span class="fm-btn">Añadir cargo</span>. Luego, cuando pague, regístralo como cobro (G3).</li>
    </ol></div>
    <div class="fm-result"><b>Qué verás:</b> el cargo en su estado de cuenta, sumado a lo que debe.</div>
</section>

<section class="fm-guide" id="g-anular">
    <header>G7 · Anular y corregir un error <small>Cobros · Gastos · cuenta del alumno</small></header>
    <div class="fm-when"><b>Cuándo:</b> te has equivocado de importe, de alumno o de fecha. <strong>No existe botón de borrar</strong> a propósito.</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>Busca la fila equivocada (en Cobros, Gastos o en la cuenta del alumno).</li>
        <li>Escribe el <strong>motivo</strong> en la casilla de la derecha (por ejemplo, «importe equivocado»).</li>
        <li>Pulsa el botón de anular <span class="fm-btn">⊘</span> y confirma.</li>
        <li>Vuelve a registrarlo bien.</li>
    </ol></div>
    <div class="fm-result"><b>Qué verás:</b> la fila queda <span style="text-decoration:line-through">tachada</span> con su motivo, quién la anuló y cuándo. Deja de contar en las cifras y todo se recalcula solo.</div>
</section>
<div class="fm-box fm-tip"><strong>Anular un bono</strong>También anula su venta. Si el alumno ya lo había pagado, ese dinero queda a su favor para su próximo bono.</div>

<section class="fm-guide" id="g-alumno">
    <header>G8 · Cuenta e historial de un alumno <small>Finanzas › Alumnos</small></header>
    <div class="fm-when"><b>Cuándo:</b> un padre pregunta «¿qué he pagado?», o quieres entender por qué a un alumno le sale una cifra.</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>Entra en <span class="fm-btn">Finanzas › Alumnos</span> y pulsa en el alumno.</li>
        <li><strong>Arriba</strong> ves: comprado, pagado, lo que debe (o tiene a favor), sesiones disponibles y clases sin descontar.</li>
        <li>Más abajo: <strong>Bonos</strong> (precio, fechas, sesiones usadas), <strong>Estado de cuenta</strong> (cada cargo y pago) y <strong>Clases</strong> (a qué bono se descontó cada una).</li>
        <li>Para verlo <strong>todo</strong>, pulsa <span class="fm-btn">Historial completo</span>.</li>
    </ol>
    <p>El historial completo se ordena en cuatro bloques: <strong>Qué revisar</strong> (alertas automáticas: saldos que no cuadran, clases sin descontar, deudas), <strong>Sus bonos paso a paso</strong>, <strong>Próximas clases</strong> y <strong>Todo lo que ha pasado</strong>. Puedes filtrarlo, buscarlo, descargarlo en Excel (CSV) o imprimirlo.</p></div>
    <div class="fm-result"><b>Qué verás:</b> si todo está bien, un aviso verde. Si no, alertas en rojo (saldo que no cuadra o posible doble descuento), naranja (clases sin descontar, pagos pendientes) o azul (sesiones que caducan).</div>
</section>
<div class="fm-box fm-warn"><strong>Datos personales</strong>Cada vez que alguien consulta o descarga el historial de un alumno queda anotado (quién y cuándo). Úsalo solo cuando haga falta y no compartas el fichero fuera de la academia.</div>

<section class="fm-guide" id="g-devolver">
    <header>G9 · Devolver una sesión a un alumno <small>Clases › Pasar lista</small></header>
    <div class="fm-when"><b>Cuándo:</b> se descontó una sesión que no debía (aviso a tiempo, error, excepción).</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>Abre la clase y entra en <strong>Pasar lista</strong>. Pueden hacerlo el entrenador de esa clase, el admin y el superadmin.</li>
        <li>En la fila del alumno pulsa <span class="fm-btn">Devolver bono</span> y confirma.</li>
        <li>Si no quieres que se vuelva a descontar al guardar, cambia su estado a <strong>Ausencia justificada</strong>.</li>
    </ol></div>
    <div class="fm-result"><b>Qué verás:</b> la sesión vuelve al bono al instante y queda anotada en su historial («devuelta») con quién lo hizo.</div>
</section>

<section class="fm-guide" id="g-revision">
    <header>G10 · Revisión semanal <small>Finanzas › Revisión</small></header>
    <div class="fm-when"><b>Cuándo:</b> una vez por semana. El número rojo de la pestaña te dice cuántas tareas quedan. <strong>El objetivo es dejarla a cero.</strong></div>
    <div class="fm-body">
    <table>
        <tr><th style="width:34%">Apartado</th><th>Qué hacer</th></tr>
        <tr><td><strong>Sesiones pasadas sin cerrar</strong></td><td>Ábrela: si se dio, pasa lista y ciérrala; si no se dio, cancélala.</td></tr>
        <tr><td><strong>Clases dadas sin descontar</strong></td><td>Pulsa <em>Revisarlas</em> y elige: saldar con un bono, «ya está pagada» (se cobró aparte) o «no cobrar».</td></tr>
        <tr><td><strong>Bonos con precio estimado</strong></td><td>Escribe lo que pagó de verdad el alumno y pulsa <em>Confirmar</em>.</td></tr>
        <tr><td><strong>Caducados sin usar</strong></td><td>Solo es un aviso. Si quieres darle más tiempo al alumno, amplía la caducidad desde la ficha del bono.</td></tr>
    </table></div>
    <div class="fm-result"><b>Qué verás:</b> al resolver cada punto desaparece de la lista y baja el número de la pestaña.</div>
</section>

<section class="fm-guide" id="g-cierre">
    <header>G11 · Cierre de mes y exportar <small>Resumen · Movimientos · Exportar</small></header>
    <div class="fm-when"><b>Cuándo:</b> a final de mes, o cuando el gestor pide los números.</div>
    <div class="fm-body"><ol class="fm-steps">
        <li>Deja la <strong>Revisión</strong> a cero (G10) y comprueba que todos los gastos del mes están apuntados.</li>
        <li>Mira el <span class="fm-btn">Resumen</span> del mes: vendido, cobrado, gastos y resultado.</li>
        <li>Para el gestor: <span class="fm-btn">Finanzas › Movimientos</span>, elige el mes y pulsa <span class="fm-btn">Exportar CSV</span> (se abre con Excel).</li>
        <li>Para una copia completa de todos los datos: pestaña <span class="fm-btn">Exportar</span>. Elige el periodo, el formato (un archivo JSON, o un ZIP de Excel) y, si vas a compartirlo, marca <strong>Ocultar nombres de alumnos</strong>. Pulsa <span class="fm-btn">Descargar</span>.</li>
    </ol></div>
    <div class="fm-result"><b>Qué verás:</b> un archivo descargado con todo el periodo. No lo compartas fuera de la academia sin ocultar los nombres.</div>
</section>

<h2 id="fm-auto">6. Descuento automático de sesiones</h2>
<p>Es una opción de <span class="fm-btn">Finanzas › Configuración › Reglas de asistencia y bono</span>. Sirve para que <strong>nadie tenga que acordarse</strong> de cobrar la sesión cuando un alumno no viene y no avisó a tiempo.</p>
<div class="fm-box fm-tip"><strong>En una frase</strong>Si está activada, al <em>guardar la lista</em> de una clase, un alumno con <strong>falta sin justificar</strong> o que <strong>avisó con menos de 24 horas</strong> pierde 1 sesión de su bono, automáticamente.</div>
<table>
    <tr><th style="width:55%">Lo que marcas al pasar lista</th><th>¿Gasta sesión?</th></tr>
    <tr><td>Presente</td><td>Sí (como siempre)</td></tr>
    <tr><td>Falta sin justificar</td><td><strong>Sí, automáticamente</strong></td></tr>
    <tr><td>Avisó que no viene con <strong>menos de 24 h</strong> (o sin hora de aviso)</td><td><strong>Sí, automáticamente</strong></td></tr>
    <tr><td>Avisó que no viene con 24 h o más</td><td>No</td></tr>
    <tr><td>Ausencia justificada</td><td>No</td></tr>
</table>
<p>Las 24 horas se pueden cambiar en la misma pantalla.</p>
<h3>Ejemplo</h3>
<p>Clase de 17:00. El alumno avisa a las 10:00 del mismo día (7 horas antes). Con el mínimo en 24 h avisó <strong>tarde</strong>: al guardar la lista se le descuenta 1 sesión. Si hubiera avisado el día anterior a las 16:00 (25 horas antes), no se le descuenta nada.</p>
<div class="fm-do">
    <div class="yes"><strong>Si está ACTIVADA</strong><ul>
        <li>El descuento se hace solo al guardar la lista y sale un mensaje diciendo a cuántos alumnos.</li>
        <li>Sale del bono <strong>que caduca antes</strong>.</li>
        <li>Queda en el historial como «Descuento automático» con su motivo.</li>
        <li>Cuenta como servicio prestado.</li></ul></div>
    <div class="no" style="background:#f8fafc;border-color:#e2e8f0"><strong>Si está DESACTIVADA</strong><ul>
        <li>No se descuenta nada solo por faltas ni avisos tardíos.</li>
        <li>Quien pasa lista descuenta a mano con <em>Descontar bono</em>.</li>
        <li>Lo que no se descuente queda como «clase sin bono» en Revisión.</li></ul></div>
</div>
<div class="fm-box fm-warn"><strong>Qué conviene saber</strong>
    <ul style="margin:4px 0 0">
        <li><strong>Solo actúa al guardar la lista.</strong> Las clases ya guardadas antes de activarlo no cambian hasta que se vuelva a guardar su lista.</li>
        <li>Si el alumno <strong>no tiene bono con saldo</strong>, no se descuenta nada: queda como «sin bono» y se salda al darle un bono (guía G2).</li>
        <li>Nunca se descuenta dos veces la misma clase.</li>
        <li>Siempre se puede deshacer: guía G9.</li>
    </ul>
</div>

<h2 id="fm-uso">7. Reglas de uso (qué hacer y qué no)</h2>
<p>Pequeñas costumbres que evitan el 90 % de los errores.</p>
<div class="fm-do">
    <div class="yes"><strong>Sí</strong><ul>
        <li>Pasar lista y <strong>cerrar la clase</strong> el mismo día.</li>
        <li>Apuntar cada cobro y cada gasto <strong>el día que ocurre</strong>, con su medio de pago.</li>
        <li>Adjuntar la <strong>foto del ticket</strong> en los gastos.</li>
        <li>Marcar «Avisó ausencia» <strong>solo si el alumno avisó</strong>; si no, «Falta sin justificar».</li>
        <li>Escribir un <strong>motivo claro</strong> al anular o corregir.</li>
        <li>Dejar la <strong>Revisión a cero</strong> cada semana.</li>
        <li>Al dar un bono, <strong>mirar si hay clases pendientes</strong> de saldar (G2).</li>
        <li>Avisar a las familias del <strong>aviso mínimo</strong> antes de activar el descuento automático.</li>
    </ul></div>
    <div class="no"><strong>No</strong><ul>
        <li>Guardar la lista <strong>a medias</strong>: la regla automática se aplica en cada guardado.</li>
        <li>Desactivar el descuento automático <strong>para todos</strong> por una sola excepción: usa <em>Devolver bono</em>.</li>
        <li>Intentar «borrar» algo: se anula, con motivo.</li>
        <li>Compartir exportaciones o historiales de alumnos <strong>con nombres</strong> fuera de la academia.</li>
        <li>Saldar clases con un bono que <strong>no es del alumno</strong> o que está caducado: el sistema no lo permite.</li>
        <li>Cambiar el precio de un tipo de bono esperando que cambie los ya vendidos: <strong>no cambia</strong> (precio congelado).</li>
        <li>Prestar tu usuario: cada cambio queda registrado con quién lo hizo.</li>
    </ul></div>
</div>

<h2 id="fm-reglas">8. Reglas del sistema</h2>
<ul>
    <li><strong>Nada se borra:</strong> bonos, cobros, cargos y gastos se anulan con motivo.</li>
    <li><strong>Precio congelado:</strong> si cambias la tarifa de un tipo de bono, los bonos ya vendidos mantienen su precio.</li>
    <li><strong>Varios bonos por alumno:</strong> un alumno puede tener varios bonos con saldo a la vez; al descontar a mano se elige de cuál. El descuento automático usa el que caduca antes.</li>
    <li><strong>Pagos:</strong> se aplican siempre a lo más antiguo que se deba. Lo que sobra queda a favor del alumno.</li>
    <li><strong>Bonos caducados:</strong> las sesiones no usadas se dan por ganadas y se señalan aparte.</li>
    <li><strong>Las finanzas cuentan desde una fecha</strong> (por defecto, el 1 de octubre de 2026). Lo anterior no sale en cifras ni en Revisión, pero no se borra.</li>
    <li><strong>Cuentas de prueba:</strong> el administrador principal puede marcar usuarios que <em>no cuentan</em> en las cifras (Configuración › «Usuarios que no cuentan en las finanzas»). Siguen funcionando en el resto de la plataforma.</li>
    <li><strong>Todo cambio queda registrado</strong> (quién, cuándo y qué) en la auditoría.</li>
</ul>

<h2 id="fm-mapa">9. Mapa de pestañas</h2>
<table>
    <tr><th style="width:20%">Pestaña</th><th style="width:40%">Para qué sirve</th><th>Cuándo la usas</th></tr>
    <tr><td><strong>Resumen</strong></td><td>Cómo va el mes de un vistazo.</td><td>Cada día o semana, y al cerrar el mes.</td></tr>
    <tr><td><strong>Movimientos</strong></td><td>Cada operación, una por fila.</td><td>Para buscar algo o pasar datos al gestor.</td></tr>
    <tr><td><strong>Cobros</strong></td><td>Apuntar pagos y ver quién debe.</td><td>Cada vez que un alumno paga.</td></tr>
    <tr><td><strong>Gastos</strong></td><td>Apuntar lo que gasta la academia.</td><td>Cada vez que se paga algo.</td></tr>
    <tr><td><strong>Alumnos</strong></td><td>La cuenta y el historial de cada alumno.</td><td>Para resolver dudas o reclamar pagos.</td></tr>
    <tr><td><strong>Entrenadores</strong></td><td>Clases y valor generado por cada entrenador.</td><td>A final de mes.</td></tr>
    <tr><td><strong>Análisis</strong></td><td>Qué bonos y clases funcionan mejor.</td><td>Para tomar decisiones.</td></tr>
    <tr><td><strong>Revisión</strong></td><td>Cosas pendientes para que las cuentas cuadren.</td><td>Una vez por semana.</td></tr>
    <tr><td><strong>Configuración</strong></td><td>Reglas, medios de pago y categorías.</td><td>Casi nunca (al principio).</td></tr>
    <tr><td><strong>Exportar</strong></td><td>Descargar todos los datos.</td><td>Para el gestor o para un estudio.</td></tr>
    <tr><td><strong>Ayuda</strong></td><td>Este manual y los vídeos.</td><td>Cuando tengas dudas.</td></tr>
</table>
<p>En las pestañas con datos de un periodo hay un selector de <strong>Mes</strong> (o un rango <em>desde / hasta</em>); por defecto, el mes en curso. Las listas largas van <strong>por páginas</strong>, con un <strong>buscador</strong> encima y se ordenan pulsando el título de una columna. Con <span class="fm-btn">☰</span> y <span class="fm-btn">▦</span> cambias entre lista y tarjetas.</p>

<h2 id="fm-pestanas">10. Cada pestaña en detalle</h2>

<h3>Resumen</h3>
<?= $shot('resumen.png', 'Finanzas › Resumen') ?>
<ul>
    <li><strong>Primera fila:</strong> vendido, cobrado, servicio prestado y gastos del periodo.</li>
    <li><strong>Segunda fila:</strong> resultado (cobrado − gastos) y la foto de hoy: pendiente de cobro, sesiones pagadas sin dar y caducado sin usar.</li>
    <li><strong>Gráfico:</strong> lo vendido (azul) y el servicio prestado (verde) de los últimos 12 meses. Abre <em>Ver como tabla</em> para ver las cifras.</li>
    <li><strong>A la derecha:</strong> cuánto se ha cobrado por cada medio de pago y los gastos por categoría.</li>
    <li><strong>Asistencia:</strong> cuántas veces los alumnos vinieron, faltaron o avisaron, y qué gasta sesión.</li>
</ul>

<h3>Movimientos</h3>
<?= $shot('movimientos.png', 'Finanzas › Movimientos') ?>
<ul>
    <li>Las tarjetas de arriba son filtros: pulsa, por ejemplo, <em>Cobro</em> para ver solo los cobros. Vuelve a pulsar para quitarlo.</li>
    <li>Lo anulado aparece <span style="text-decoration:line-through">tachado</span> y con el motivo.</li>
    <li><span class="fm-btn">Exportar CSV</span> descarga todo en un fichero que se abre con Excel.</li>
</ul>

<h3>Cobros</h3>
<?= $shot('cobros.png', 'Finanzas › Cobros') ?>
<p><strong>Registrar cobro</strong> (izquierda), <strong>Quién debe ahora</strong> (derecha) y <strong>Cobros del periodo</strong> (abajo, con la opción de anular uno con su motivo).</p>

<h3>Gastos</h3>
<?= $shot('gastos.png', 'Finanzas › Gastos') ?>
<p>Importe, fecha, categoría y forma de pago; opcionalmente entrenador, sede y foto del ticket. La lista se puede filtrar por categoría.</p>

<h3>Alumnos y cuenta del alumno</h3>
<?= $shot('alumnos.png', 'Finanzas › Alumnos') ?>
<?= $shot('cuenta.png', 'Cuenta de un alumno') ?>
<p>A la derecha de la cuenta: <em>Registrar cobro</em> (puedes elegir qué bono paga) y <em>Añadir cargo</em>.</p>
<?= $shot('historial.png', 'Historial completo de un alumno') ?>
<div class="fm-box fm-tip"><strong>Lo que ve el alumno</strong>Cada alumno ve en su perfil un apartado <em>Mis pagos</em> con lo que ha comprado, lo que ha pagado y lo que tiene pendiente. No ve nada de la academia ni de otros alumnos.</div>

<h3>Entrenadores</h3>
<?= $shot('entrenadores.png', 'Finanzas › Entrenadores') ?>
<p>Para cada entrenador: clases dadas en el periodo, asistencias, valor de las sesiones consumidas en sus clases y gastos asignados (por ejemplo, lo que se le paga).</p>

<h3>Análisis</h3>
<?= $shot('analisis.png', 'Finanzas › Análisis') ?>
<p>Qué bonos se venden más y cuánto dejan, cuánto se cobra de media por sesión, clases individuales o DUO, sedes y categorías de edad.</p>

<h3>Revisión</h3>
<?= $shot('revision.png', 'Finanzas › Revisión') ?>
<p>Mira la guía <a href="#g-revision">G10</a>.</p>

<h3>Configuración</h3>
<?= $shot('configuracion.png', 'Finanzas › Configuración (reglas de asistencia y bono)') ?>
<ul>
    <li><strong>Reglas de asistencia y bono:</strong> activar o desactivar el descuento automático (con toda su explicación a la vista), fijar las horas de aviso y la fecha desde la que cuentan las finanzas.</li>
    <li><strong>Quién ve las finanzas</strong> y <strong>Usuarios que no cuentan:</strong> solo los cambia el administrador principal.</li>
    <li><strong>Medios de pago</strong> y <strong>categorías</strong> de gasto e ingreso: añadir, cambiar el nombre o archivar. Archivar no borra lo ya registrado.</li>
    <li><strong>IVA y facturas:</strong> por ahora desactivado; se activará cuando el gestor lo confirme.</li>
</ul>

<h2 id="fm-rutina">11. Rutina recomendada</h2>
<table>
    <tr><th style="width:22%">Cuándo</th><th>Qué hacer</th></tr>
    <tr><td><strong>Cada día</strong></td><td>Pasar lista y cerrar las clases. Registrar los cobros y gastos del día.</td></tr>
    <tr><td><strong>Cada semana</strong></td><td>Dejar <em>Revisión</em> a cero. Mirar <em>Cobros › Quién debe ahora</em> y reclamar lo pendiente.</td></tr>
    <tr><td><strong>Cada mes</strong></td><td>Revisar el <em>Resumen</em>, comprobar que los gastos están todos y exportar para el gestor (guía G11).</td></tr>
</table>

<h2 id="fm-faq">12. Preguntas frecuentes</h2>
<p class="fm-q">Me he equivocado al registrar un cobro. ¿Lo borro?</p>
<p>No se puede borrar: anúlalo escribiendo el motivo y vuelve a registrarlo bien (guía G7). Las cuentas se recalculan solas.</p>
<p class="fm-q">Un alumno dice que ya pagó, pero le sale que debe.</p>
<p>Abre su cuenta (Finanzas › Alumnos) y mira el estado de cuenta. Si el pago no aparece, regístralo con la fecha en que pagó.</p>
<p class="fm-q">¿Por qué «vendido» y «cobrado» no coinciden?</p>
<p>Porque no todo lo vendido se paga el mismo día: puede haber pagos a plazos o pendientes. La diferencia acumulada es el «pendiente de cobro».</p>
<p class="fm-q">¿Qué es «Sin especificar» en los medios de pago?</p>
<p>Son los pagos anteriores a Finanzas, de los que no se sabe cómo se pagaron. Los nuevos siempre llevan su medio de pago.</p>
<p class="fm-q">Se ha descontado una sesión a un alumno que avisó a tiempo.</p>
<p>Abre Pasar lista de esa clase y pulsa <em>Devolver bono</em> en ese alumno, o cambia su estado a «ausencia justificada» (guía G9).</p>
<p class="fm-q">Un alumno no tiene bono y faltó sin justificar. ¿Qué pasa?</p>
<p>No se descuenta nada porque no hay de dónde. La clase queda como «sin bono» en Revisión y, al darle un bono, puedes saldarla (guía G2).</p>
<p class="fm-q">¿Los entrenadores ven estas cifras?</p>
<p>No. Finanzas solo lo ve administración, y los importes solo quien tiene acceso total. Los alumnos solo ven sus propios pagos.</p>
<p class="fm-q">¿Puedo ver meses anteriores?</p>
<p>Sí: usa el selector de mes, o un rango «desde / hasta», en la parte de arriba de cada pestaña.</p>

<div class="fm-box fm-ok" style="margin-top:28px"><strong>¿Dudas?</strong>Usa el botón de reportar un problema de la plataforma o contacta con el administrador. Cada cifra tiene su símbolo <span class="fm-btn">?</span> con una explicación corta.</div>

</div>
