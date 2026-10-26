<?php
/**
 * Ayuda de la «Revisión de clases» para los administradores SIN acceso total a
 * Finanzas. Solo texto: nada de importes, bonos ni capturas del manual
 * completo (esas llevan cifras reales de la academia).
 * Lenguaje llano: lo lee alguien de la academia sin contexto técnico.
 */
?>
<?= view('finanzas/_manual_css') ?>

<div class="fin-manual">

<p style="font-size:16px">Esta ayuda explica cómo usar la <strong>Revisión de clases</strong>: la lista de clases que han quedado a medias y que conviene dejar al día. No hace falta saber nada de finanzas ni ver ningún importe.</p>

<h3>Contenido</h3>
<ol class="fm-toc">
    <li><a href="#rv-que-es">Para qué sirve</a></li>
    <li><a href="#rv-apartados">Los cuatro apartados</a></li>
    <li><a href="#rv-tareas">Tareas paso a paso</a></li>
    <li><a href="#rv-no-ves">Lo que no ves, y por qué</a></li>
    <li><a href="#rv-rutina">Rutina recomendada</a></li>
    <li><a href="#rv-faq">Preguntas frecuentes</a></li>
</ol>

<h2 id="rv-que-es">1. Para qué sirve</h2>
<p>Para que las cuentas de la academia cuadren, cada clase tiene que estar bien «cerrada»: que se dio, que se pasó lista y que tiene entrenador. Esta pantalla te enseña, en un solo sitio, las clases en las que falta algo.</p>
<ul>
    <li>Entras desde el menú de la izquierda, en <span class="fm-btn">Revisión de clases</span>. El número de la pestaña te dice cuántas cosas quedan pendientes.</li>
    <li>Cuando está a cero, todo está al día: aparece un mensaje en verde.</li>
    <li>Los administradores ven solo esta parte. Los importes, bonos y cobros los ve únicamente el administrador principal y quien él autorice.</li>
</ul>

<h2 id="rv-apartados">2. Los cuatro apartados</h2>
<p>Cada apartado es una tarjeta con su contador. Si un apartado está vacío, te lo dice con un mensaje en verde. Pasa el ratón por el símbolo <span class="fm-btn">?</span> (o tócalo en el móvil) para ver una explicación corta.</p>
<table>
    <tr><th style="width:30%">Apartado</th><th>Qué significa</th><th style="width:34%">Qué hacer</th></tr>
    <tr><td><strong>Falta pasar lista</strong></td><td>La clase ya pasó, pero nadie marcó quién vino.</td><td>Ábrela y marca la asistencia. Si en realidad no se dio, <strong>cancélala</strong>.</td></tr>
    <tr><td><strong>Falta cerrar la clase</strong></td><td>Ya se marcó quién vino, pero la clase sigue abierta y todavía no cuenta.</td><td>Pulsa el botón del apartado para cerrarlas todas de golpe, o ábrelas una a una y pulsa <em>Cerrar clase</em>.</td></tr>
    <tr><td><strong>Sin entrenador</strong></td><td>Tiene alumnos apuntados, pero ningún entrenador asignado. No se sabe quién la da.</td><td>Ábrela y elige el entrenador (o el staff responsable).</td></tr>
    <tr><td><strong>Clases vacías</strong></td><td>No tienen ningún alumno apuntado. Casi seguro son un error, una prueba o lo que quedó al quitar a un alumno.</td><td>Elimínalas. Si alguna sirve, ábrela y apunta a un alumno.</td></tr>
</table>

<h2 id="rv-tareas">3. Tareas paso a paso</h2>

<h3>Pasar lista de una clase que se quedó sin lista</h3>
<ol class="fm-steps">
    <li>En <em>Falta pasar lista</em>, pulsa la clase (o el botón de su fila) para abrirla.</li>
    <li>Marca la asistencia de cada alumno: vino, faltó, avisó…</li>
    <li>Guarda la lista. La clase pasa al apartado <em>Falta cerrar la clase</em>.</li>
</ol>
<div class="fm-box fm-tip"><strong>¿Y si la clase no se dio?</strong>Ábrela y <strong>cancélala</strong>. Así sale de la lista y no cuenta como clase dada.</div>

<h3>Cerrar las clases</h3>
<ol class="fm-steps">
    <li>En <em>Falta cerrar la clase</em>, pulsa <span class="fm-btn">Cerrar las N clases automáticamente</span> para cerrarlas todas de golpe.</li>
    <li>Si prefieres comprobar la asistencia antes, ábrelas una a una y pulsa <em>Cerrar clase</em>.</li>
    <li>Confirma. Las clases desaparecen de la lista y el contador baja.</li>
</ol>
<div class="fm-box fm-ok"><strong>Cerrar no descuenta bonos</strong>Cerrar una clase solo cambia su estado a «cerrada». No cobra nada ni cambia el saldo de nadie.</div>

<h3>Asignar entrenador</h3>
<ol class="fm-steps">
    <li>En <em>Sin entrenador</em>, abre la clase.</li>
    <li>Elige el entrenador (o el staff que la da) y guarda.</li>
</ol>

<h3>Eliminar clases vacías</h3>
<ol class="fm-steps">
    <li>En <em>Clases vacías</em>, revisa la lista: no deben tener ningún alumno.</li>
    <li>Pulsa el botón de eliminar de una fila, o el de eliminarlas todas, y confirma.</li>
</ol>
<div class="fm-box fm-warn"><strong>Queda registrado</strong>Cada clase eliminada queda anotada en el historial de cambios (quién y cuándo). Solo se pueden eliminar las que no tienen ningún alumno ni archivos adjuntos.</div>

<h2 id="rv-no-ves">4. Lo que no ves, y por qué</h2>
<p>La Revisión completa tiene además apartados de bonos y de dinero (clases dadas sin descontar de un bono, precios, bonos caducados). Esos apartados no se muestran a los administradores sin acceso total, porque llevan importes.</p>
<ul>
    <li>Si cierras una clase en la que algún alumno debe una sesión de bono, esa clase pasará a la revisión de bonos del administrador principal. No tienes que hacer nada más.</li>
    <li>Si necesitas ver o corregir un importe, pídeselo al administrador principal.</li>
</ul>

<h2 id="rv-rutina">5. Rutina recomendada</h2>
<table>
    <tr><th style="width:22%">Cuándo</th><th>Qué hacer</th></tr>
    <tr><td><strong>Cada día</strong></td><td>Pasar lista y cerrar las clases del día. Así casi no se acumula nada en la Revisión.</td></tr>
    <tr><td><strong>Cada semana</strong></td><td>Abrir <em>Revisión de clases</em> y dejar los cuatro apartados a cero.</td></tr>
</table>

<h2 id="rv-faq">6. Preguntas frecuentes</h2>
<p class="fm-q">¿Por qué no veo las finanzas?</p>
<p>Porque solo las ve el administrador principal y las personas que él autorice. Tu parte es la Revisión de clases. Si necesitas ver más, díselo al administrador principal.</p>
<p class="fm-q">El contador no baja después de cerrar una clase.</p>
<p>Recarga la página. Si sigue ahí, comprueba que la clase tiene lista pasada, alumnos y entrenador: puede haber pasado a otro apartado en lugar de desaparecer.</p>
<p class="fm-q">He eliminado una clase por error.</p>
<p>No se puede deshacer desde aquí. Avisa al administrador principal: la eliminación queda registrada con la fecha, la clase y quién la hizo.</p>
<p class="fm-q">¿Una clase cancelada aparece en la Revisión?</p>
<p>No cuenta como pendiente de lista ni de cierre. Solo aparecería en <em>Clases vacías</em> si no tiene ningún alumno.</p>

<div class="fm-box fm-ok" style="margin-top:28px"><strong>¿Dudas?</strong>Usa el botón de reportar un problema de la plataforma o escribe al administrador principal.</div>

</div>
