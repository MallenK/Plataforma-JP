<?php
/**
 * Manual de uso de Finanzas (contenido). Se usa en la pestaña Ayuda y en la
 * versión imprimible / PDF. $img = función que devuelve la URL de una captura.
 * Lenguaje llano: lo lee alguien de la academia sin contexto técnico.
 */
$img = $img ?? static fn(string $n) => base_url('finanzas/ayuda/img/' . $n);
$shot = static function (string $file, string $caption) use ($img) {
    return '<figure class="fm-shot"><img src="' . esc($img($file), 'attr') . '" alt="' . esc($caption, 'attr') . '" loading="lazy"><figcaption>' . esc($caption) . '</figcaption></figure>';
};
?>
<?= view('finanzas/_manual_css') ?>

<div class="fin-manual">

<p style="font-size:16px">Este manual explica, paso a paso y sin tecnicismos, cómo usar la sección <strong>Finanzas</strong> de la plataforma de JP Preparation: qué significa cada cifra, cómo registrar cobros y gastos, cómo corregir un error y qué revisar cada semana.</p>

<div class="fm-box fm-tip"><strong>Consejo rápido</strong>En casi todas las pantallas verás un pequeño símbolo <span class="fm-btn">?</span> junto a las cifras y los campos. Pasa el ratón por encima (o tócalo en el móvil) y te explica qué es.</div>

<h3>Contenido</h3>
<ol class="fm-toc">
    <li><a href="#fm-que-es">Qué es Finanzas</a></li>
    <li><a href="#fm-ideas">Las ideas básicas (con un ejemplo)</a></li>
    <li><a href="#fm-entrar">Cómo entrar y moverse</a></li>
    <li><a href="#fm-resumen">Resumen</a></li>
    <li><a href="#fm-movimientos">Movimientos</a></li>
    <li><a href="#fm-cobros">Cobros</a></li>
    <li><a href="#fm-gastos">Gastos</a></li>
    <li><a href="#fm-alumnos">Alumnos y cuenta del alumno</a></li>
    <li><a href="#fm-entrenadores">Entrenadores</a></li>
    <li><a href="#fm-analisis">Análisis</a></li>
    <li><a href="#fm-revision">Revisión</a></li>
    <li><a href="#fm-config">Configuración</a></li>
    <li><a href="#fm-tareas">Tareas paso a paso</a></li>
    <li><a href="#fm-reglas">Reglas importantes</a></li>
    <li><a href="#fm-rutina">Rutina recomendada</a></li>
    <li><a href="#fm-faq">Preguntas frecuentes</a></li>
</ol>

<h2 id="fm-que-es">1. Qué es Finanzas</h2>
<p>Finanzas es el lugar donde se ve y se controla <strong>todo el dinero de la academia</strong>: lo que se vende, lo que se cobra, lo que se gasta y lo que todavía se debe.</p>
<ul>
    <li>Solo lo ve <strong>el administrador principal y las personas que él autorice</strong> (Configuración › «Quién ve las finanzas»). El resto de administradores solo ve la <strong>Revisión de clases</strong>, sin ningún importe. Entrenadores, staff y alumnos no tienen acceso.</li>
    <li>Se alimenta sola con lo que ya hacéis en la plataforma: vender bonos, pasar lista, descontar sesiones. Solo hay que añadir a mano los <strong>cobros</strong> y los <strong>gastos</strong>.</li>
    <li><strong>Nada se borra.</strong> Si algo está mal, se <em>anula</em> indicando el motivo: queda tachado y visible, y las cuentas se recalculan solas.</li>
</ul>

<h2 id="fm-ideas">2. Las ideas básicas (con un ejemplo)</h2>
<p>Para entender las pantallas solo hace falta tener claras estas palabras. Lo explicamos con una alumna inventada, Laura:</p>
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
    <tr><td><strong>A favor</strong></td><td>Cuando un alumno ha pagado de más. Se le aplica solo en su próximo bono.</td></tr>
    <tr><td><strong>Anular</strong></td><td>Corregir un error sin borrarlo: queda tachado, con el motivo, y no cuenta.</td></tr>
</table>

<h2 id="fm-entrar">3. Cómo entrar y moverse</h2>
<ol class="fm-steps">
    <li>Entra en la plataforma con tu usuario de administración.</li>
    <li>En el menú de la izquierda, pulsa <span class="fm-btn">Finanzas</span>.</li>
    <li>Arriba verás las <strong>pestañas</strong>: Resumen, Movimientos, Cobros, Gastos, Alumnos, Entrenadores, Análisis, Revisión, Configuración y Ayuda.</li>
</ol>
<div class="fm-box fm-tip"><strong>¿Solo ves dos pestañas?</strong>Si eres administrador pero no tienes acceso total a las finanzas, verás únicamente <span class="fm-btn">Revisión de clases</span> y <span class="fm-btn">Ayuda</span>. Es lo normal: solo el administrador principal (y quien él autorice) ve los importes.</div>
<p>En las pestañas que muestran datos de un periodo hay un selector de <strong>Mes</strong> (o un rango <em>desde / hasta</em>). Por defecto se ve el mes en curso.</p>
<p>Las listas largas aparecen <strong>por páginas</strong> (25 filas), con un <strong>buscador</strong> encima para encontrar cualquier cosa (un nombre, un importe, un concepto…). Pulsando en el título de una columna se ordena por ella. Con los botones <span class="fm-btn">☰</span> y <span class="fm-btn">▦</span> cambias entre vista de lista y de tarjetas.</p>

<h2 id="fm-resumen">4. Resumen</h2>
<p>Es la pantalla principal: de un vistazo, cómo va el mes.</p>
<?= $shot('resumen.png', 'Finanzas › Resumen') ?>
<ul>
    <li><strong>Primera fila:</strong> vendido, cobrado, servicio prestado y gastos del periodo.</li>
    <li><strong>Segunda fila:</strong> resultado (cobrado − gastos) y la foto de hoy: pendiente de cobro, sesiones pagadas sin dar y caducado sin usar.</li>
    <li><strong>Gráfico:</strong> lo vendido (azul) y el servicio prestado (verde) de los últimos 12 meses, contando desde el mes de inicio de las finanzas (octubre de 2026): al principio solo verás los meses ya transcurridos. Abre <em>Ver como tabla</em> para ver las cifras.</li>
    <li><strong>A la derecha:</strong> cuánto se ha cobrado por cada medio de pago y los gastos por categoría.</li>
    <li><strong>Asistencia:</strong> cuántas veces los alumnos vinieron, faltaron o avisaron, y qué gasta sesión.</li>
</ul>
<div class="fm-box fm-tip"><strong>¿Quién me debe dinero?</strong>Pulsa <em>Ver quién debe</em> en la tarjeta «Pendiente de cobro».</div>

<h2 id="fm-movimientos">5. Movimientos</h2>
<p>Aquí está <strong>todo lo que ha pasado en el mes, una operación por fila</strong>: ventas, cobros, gastos, sesiones consumidas y bonos caducados.</p>
<?= $shot('movimientos.png', 'Finanzas › Movimientos') ?>
<ul>
    <li>Las tarjetas de arriba son filtros: pulsa, por ejemplo, <em>Cobro</em> para ver solo los cobros. Vuelve a pulsar para quitar el filtro.</li>
    <li>Usa el <strong>buscador</strong> para encontrar a un alumno o un importe concreto.</li>
    <li>Lo anulado aparece <span style="text-decoration:line-through">tachado</span> y con el motivo.</li>
    <li><span class="fm-btn">Exportar CSV</span> descarga todo en un fichero que se abre con Excel (útil para el gestor).</li>
</ul>

<h2 id="fm-cobros">6. Cobros</h2>
<p>Para apuntar el dinero que paga un alumno y ver quién debe.</p>
<?= $shot('cobros.png', 'Finanzas › Cobros') ?>
<ul>
    <li><strong>Registrar cobro</strong> (izquierda): alumno, importe, fecha y medio de pago.</li>
    <li><strong>Quién debe ahora</strong> (derecha): alumnos con algo pendiente, de mayor a menor, con un botón para cobrarles.</li>
    <li><strong>Cobros del periodo</strong> (abajo): todos los cobros del mes, con la opción de anular uno con su motivo.</li>
</ul>
<div class="fm-box fm-ok"><strong>Pagos a plazos</strong>Puedes cobrar solo una parte. Lo que falte queda pendiente y se ve en la cuenta del alumno. Cuando pague el resto, registras otro cobro.</div>

<h2 id="fm-gastos">7. Gastos</h2>
<p>Para apuntar cualquier gasto de la academia.</p>
<?= $shot('gastos.png', 'Finanzas › Gastos') ?>
<ul>
    <li>Importe, fecha, <strong>categoría</strong> (material, reparaciones, alquiler de campos, entrenadores…) y cómo se pagó.</li>
    <li>Opcional: a qué <strong>entrenador</strong> o <strong>sede</strong> corresponde, y una <strong>foto del ticket o la factura</strong>.</li>
    <li>La lista de abajo se puede filtrar por categoría. Con <span class="fm-btn">Ver</span> abres el ticket adjunto.</li>
</ul>

<h2 id="fm-alumnos">8. Alumnos y cuenta del alumno</h2>
<p>La lista muestra a cada alumno con lo que ha comprado, lo que ha pagado, lo que debe y las sesiones que le quedan. Puedes filtrar «Solo los que deben».</p>
<?= $shot('alumnos.png', 'Finanzas › Alumnos') ?>
<p>Pulsando en un alumno se abre su <strong>cuenta</strong>: todo lo económico de esa persona en una sola página.</p>
<?= $shot('cuenta.png', 'Cuenta de un alumno') ?>
<ul>
    <li><strong>Arriba:</strong> comprado, pagado, lo que debe (o lo que tiene a favor), sesiones disponibles y clases sin descontar.</li>
    <li><strong>Bonos:</strong> sus bonos con precio, fechas, sesiones usadas y su estado.</li>
    <li><strong>Estado de cuenta:</strong> cada cargo y cada pago por fecha, con lo que debía después de cada uno.</li>
    <li><strong>Clases:</strong> sus clases, a qué bono se descontaron y su valor.</li>
    <li><strong>A la derecha:</strong> <em>Registrar cobro</em> (puedes elegir qué bono paga) y <em>Añadir cargo</em> para cobrar algo que no es un bono (una sesión suelta, material…).</li>
</ul>
<h3>Historial completo de un alumno</h3>
<p>Desde la cuenta del alumno, el botón <span class="fm-btn">Historial completo</span> abre una página con <strong>absolutamente todo lo que ha pasado con ese alumno</strong>, ordenada en cuatro bloques:</p>
<ol>
    <li><strong>Qué revisar:</strong> lo que la plataforma detecta sola. En rojo, los bonos cuyo saldo <em>no cuadra</em> con sus movimientos (y si puede ser un doble descuento). En naranja, clases dadas sin descontar, clases programadas sin saldo o pagos pendientes. En azul, sesiones que caducaron o que le sobrarán. Si todo está bien, sale en verde.</li>
    <li><strong>Sus bonos, paso a paso:</strong> cada bono con su compra, su pago y cada sesión usada (qué clase, quién la descontó y cuándo, y cuántos días después de la clase). La columna <em>Quedan</em> dice el saldo tras cada paso, y la última fila lo compara con el saldo real de hoy.</li>
    <li><strong>Próximas clases:</strong> las ya programadas y de qué bono saldrá cada una.</li>
    <li><strong>Todo lo que ha pasado:</strong> la lista completa, donde cada clase y su descuento van en una sola fila y la columna <em>Saldo después</em> dice cuánto le quedaba en el bono o cuánto debía:</li>
</ol>
<ul>
    <li><strong>Económico:</strong> cada cargo y cada pago, también los anulados y su motivo.</li>
    <li><strong>Bonos:</strong> cuándo se emitieron, cada sesión descontada o devuelta, ampliaciones de fecha, ajustes y anulaciones.</li>
    <li><strong>Clases:</strong> cada clase con su asistencia, y los avisos y respuestas del propio alumno.</li>
    <li><strong>Acceso:</strong> alta, primer acceso e inicios de sesión.</li>
    <li><strong>Comunicación:</strong> mensajes enviados (solo el registro, nunca el texto, que es privado), tickets, avisos y emails.</li>
    <li><strong>Seguimiento y auditoría:</strong> documentos, anotaciones y cualquier cambio registrado (quién, cuándo y por qué).</li>
</ul>
<?= $shot('historial.png', 'Historial completo de un alumno') ?>
<p>Puedes filtrar por <strong>categoría</strong> o por <strong>fechas</strong>, buscar cualquier palabra, y llevártelo con <span class="fm-btn">Descargar Excel (CSV)</span> o <span class="fm-btn">Imprimir / guardar PDF</span> (imprime el historial entero, no solo la página que estás viendo).</p>
<div class="fm-box fm-warn"><strong>Datos personales</strong>Cada vez que alguien consulta o descarga el historial de un alumno queda anotado (quién y cuándo). Úsalo solo cuando haga falta y no compartas el fichero fuera de la academia.</div>

<div class="fm-box fm-tip"><strong>Lo que ve el alumno</strong>Cada alumno ve en su perfil un apartado <em>Mis pagos</em> con lo que ha comprado, lo que ha pagado y lo que tiene pendiente. No ve nada de la academia ni de otros alumnos.</div>

<h2 id="fm-entrenadores">9. Entrenadores</h2>
<p>Para cada entrenador: cuántas clases ha dado en el periodo, cuántas asistencias, el valor de las sesiones consumidas en sus clases y los gastos que se le han asignado (por ejemplo, lo que se le paga).</p>
<?= $shot('entrenadores.png', 'Finanzas › Entrenadores') ?>

<h2 id="fm-analisis">10. Análisis</h2>
<p>Para entender el negocio: qué bonos se venden más y cuánto dejan, cuánto se cobra de media por sesión, cuántas clases son individuales o DUO, en qué sedes se dan y por categoría de edad.</p>
<?= $shot('analisis.png', 'Finanzas › Análisis') ?>

<h2 id="fm-revision">11. Revisión</h2>
<p>La lista de <strong>cosas pendientes de revisar a mano</strong> para que las cuentas cuadren. El número de la pestaña te dice cuántas quedan.</p>
<div class="fm-box fm-tip"><strong>Quién ve qué</strong>Los administradores sin acceso total ven solo el bloque <em>Clases</em> (los cuatro primeros apartados de la tabla), sin ningún importe, y tienen su propia pestaña <span class="fm-btn">Ayuda</span> con esa parte explicada. Los apartados de bonos y dinero son solo para el acceso total.</div>
<?= $shot('revision.png', 'Finanzas › Revisión') ?>
<table>
    <tr><th style="width:34%">Apartado</th><th>Qué hacer</th></tr>
    <tr><td><strong>Falta pasar lista</strong></td><td>La clase ya pasó y nadie marcó quién vino. Ábrela y marca la asistencia; si en realidad no se dio, cancélala.</td></tr>
    <tr><td><strong>Falta cerrar la clase</strong></td><td>Ya se marcó quién vino, pero la clase sigue abierta y no cuenta en Finanzas. Ciérralas todas de golpe con el botón del apartado, o ábrelas una a una.</td></tr>
    <tr><td><strong>Sin entrenador</strong></td><td>Tiene alumnos pero nadie la da. Ábrela y elige el entrenador. Ya no se pueden crear clases así.</td></tr>
    <tr><td><strong>Clases vacías</strong></td><td>Sin ningún alumno: casi seguro un error o una prueba. Elimínalas (queda registrado). Si alguna sirve, apunta a un alumno.</td></tr>
    <tr><td><strong>Clases sin bono descontado</strong> <em>(acceso total)</em></td><td>Pulsa <em>Revisarlas</em> y elige: saldar con un bono, «ya está pagada» (se cobró aparte) o «no cobrar».</td></tr>
    <tr><td><strong>Bonos con precio estimado</strong> <em>(acceso total)</em></td><td>Escribe lo que pagó de verdad el alumno y pulsa <em>Confirmar</em>.</td></tr>
    <tr><td><strong>Caducados sin usar</strong> <em>(acceso total)</em></td><td>Solo es un aviso. Si quieres darle más tiempo al alumno, amplía la caducidad desde la ficha del bono.</td></tr>
</table>
<p>Con acceso total también ves el aviso del <strong>cierre de la revisión inicial</strong>, que da por buenas en bloque las clases y precios anteriores a empezar con Finanzas. Úsalo una sola vez, después de revisar que las cifras tienen sentido.</p>

<h2 id="fm-config">12. Configuración</h2>
<?= $shot('config.png', 'Finanzas › Configuración') ?>
<ul>
    <li><strong>Reglas de asistencia:</strong> activar o desactivar el descuento automático (<strong>viene desactivado</strong>) y fijar con cuántas horas debe avisar un alumno (por defecto, 24).</li>
    <li><strong>Las finanzas cuentan desde:</strong> fecha a partir de la cual cuenta todo en Finanzas (por defecto, el 1 de octubre de 2026). Lo anterior —ventas, cobros, gastos, clases y los bonos vendidos antes— no sale en ninguna cifra, lista ni en Revisión. No se borra nada.</li>
    <li><strong>Quién ve las finanzas:</strong> las personas con acceso total, además del administrador principal. Solo el administrador principal puede cambiar esta lista y cada cambio queda en el registro de auditoría. Quien no esté marcado solo ve la Revisión de clases.</li>
    <li><strong>Usuarios que no cuentan en las finanzas:</strong> para operar con cuentas de prueba sin ensuciar las cifras. Un usuario marcado aquí desaparece de ventas, cobros, bonos, caducados, deudas, Alumnos, Entrenadores y Revisión, y <strong>también las clases en las que participe</strong>, como alumno o como entrenador. No depende de que esté de baja. En el resto de la plataforma sigue funcionando igual, y al quitarlo de la lista vuelve a contar. Solo lo cambia el administrador principal, y queda registrado.</li>
    <li><strong>Medios de pago</strong> y <strong>categorías</strong> de gasto e ingreso: añadir, cambiar el nombre o archivar. Archivar no borra lo ya registrado.</li>
    <li><strong>IVA y facturas:</strong> por ahora está desactivado; se activará cuando el gestor lo confirme.</li>
</ul>

<h2 id="fm-tareas">13. Tareas paso a paso</h2>

<h3>Vender un bono (con descuento y cobro)</h3>
<ol class="fm-steps">
    <li>Ve a <span class="fm-btn">Bonos</span> y pulsa <span class="fm-btn">Asignar bono</span>. Se abre la ventana «Nuevo bono».</li>
    <li>Elige el <strong>tipo de bono</strong> (verás su precio) y el <strong>alumno</strong>.</li>
    <li>Si tiene descuento, escribe el importe (<kbd>10</kbd>) o el porcentaje (<kbd>10%</kbd>) y el motivo (hermanos, promoción…).</li>
    <li>Si paga en ese momento, marca <strong>Cobrado ahora</strong>, elige cómo paga y revisa el importe. Si no paga, déjalo sin marcar: quedará pendiente de cobro.</li>
    <li>Pulsa <span class="fm-btn">Crear bono</span>. La venta (y el cobro, si lo marcaste) ya aparecen en Finanzas.</li>
</ol>
<div class="fm-box fm-warn"><strong>Solo con acceso total</strong>Los campos de descuento y «Cobrado ahora» solo aparecen para quien tiene acceso total a las finanzas. Un administrador sin ese acceso puede asignar el bono, pero sin descuento ni cobro.</div>

<h3>Registrar un pago de un alumno</h3>
<ol class="fm-steps">
    <li>Ve a <span class="fm-btn">Finanzas › Cobros</span> (o abre la cuenta del alumno).</li>
    <li>Elige el alumno, escribe el importe, la fecha y el medio de pago.</li>
    <li>Pulsa <span class="fm-btn">Guardar cobro</span>. Se aplica a lo más antiguo que deba.</li>
</ol>
<div class="fm-box fm-tip"><strong>¿Y si paga de más?</strong>Lo que sobra queda <em>a su favor</em> y se usa automáticamente cuando compre su próximo bono.</div>

<h3>Registrar un gasto</h3>
<ol class="fm-steps">
    <li>Ve a <span class="fm-btn">Finanzas › Gastos</span>.</li>
    <li>Escribe el importe, la fecha y elige la categoría. Añade una descripción (por ejemplo, «20 balones»).</li>
    <li>Si es de un entrenador o de una sede, elígelos. Si tienes el ticket, adjunta una foto.</li>
    <li>Pulsa <span class="fm-btn">Guardar gasto</span>.</li>
</ol>

<h3>Corregir un error (anular)</h3>
<ol class="fm-steps">
    <li>Busca la fila equivocada (en Cobros, Gastos o en la cuenta del alumno).</li>
    <li>Escribe el <strong>motivo</strong> en la casilla de la derecha (por ejemplo, «importe equivocado»).</li>
    <li>Pulsa el botón de anular y confirma.</li>
    <li>Si hacía falta, vuelve a registrarlo bien.</li>
</ol>
<div class="fm-box fm-warn"><strong>Importante</strong>No hay botón de borrar a propósito: así siempre queda constancia de qué pasó, cuándo y quién lo hizo.</div>

<h3>Cuando un alumno falta o avisa tarde</h3>
<div class="fm-box fm-warn"><strong>El descuento automático viene desactivado</strong>Se activa en <em>Configuración › Reglas de asistencia</em>. Mientras esté desactivado, la plataforma no descuenta nada sola: registra la falta o el aviso tardío y quien pasa lista decide si descuenta la sesión, a mano, desde Pasar lista.</div>
<p>Con el descuento automático <strong>activado</strong>, al <strong>pasar lista</strong>, si marcas a un alumno como <em>falta sin justificar</em>, o como <em>avisó ausencia</em> y avisó con menos de 24 horas, la plataforma <strong>descuenta la sesión sola</strong> de su bono (del que caduca antes) y te lo dice en un mensaje.</p>
<p>Si en ese caso concreto no quieres cobrarle la sesión, en la misma lista pulsa <span class="fm-btn">Devolver bono</span> o cambia su estado a <em>ausencia justificada</em>.</p>

<h3>Mandar los números al gestor</h3>
<ol class="fm-steps">
    <li>Ve a <span class="fm-btn">Finanzas › Movimientos</span> y elige el mes.</li>
    <li>Pulsa <span class="fm-btn">Exportar CSV</span> y envía el fichero (se abre con Excel).</li>
</ol>

<h2 id="fm-reglas">14. Reglas importantes</h2>
<h3>Qué gasta una sesión del bono</h3>
<div class="fm-box fm-warn"><strong>Ojo con el descuento automático</strong>En la tabla, «automáticamente» significa <em>si el descuento automático está activado</em>. Por defecto está desactivado: entonces esas situaciones quedan registradas y la sesión se descuenta a mano.</div>
<table>
    <tr><th>Situación al pasar lista</th><th>¿Gasta sesión?</th></tr>
    <tr><td>Presente</td><td>Sí</td></tr>
    <tr><td>Falta sin justificar</td><td>Sí (automáticamente si está activado; si no, a mano)</td></tr>
    <tr><td>Avisó ausencia con menos de 24 horas (o sin hora de aviso)</td><td>Sí (automáticamente si está activado; si no, a mano)</td></tr>
    <tr><td>Avisó ausencia con 24 horas o más</td><td>No</td></tr>
    <tr><td>Ausencia justificada</td><td>No</td></tr>
</table>
<p>Las 24 horas se pueden cambiar en <em>Configuración</em>. Cualquier descuento se puede devolver desde Pasar lista (entrenador, administración).</p>
<h3>Otras reglas</h3>
<ul>
    <li><strong>Nada se borra:</strong> bonos, cobros, cargos y gastos se anulan con motivo.</li>
    <li><strong>Precio congelado:</strong> si cambias la tarifa de un tipo de bono, los bonos ya vendidos mantienen su precio.</li>
    <li><strong>Anular un bono</strong> anula también su venta. Si el alumno ya lo había pagado, ese dinero queda a su favor.</li>
    <li><strong>Bonos caducados:</strong> las sesiones no usadas se dan por ganadas y se señalan aparte.</li>
</ul>

<h2 id="fm-rutina">15. Rutina recomendada</h2>
<table>
    <tr><th style="width:22%">Cuándo</th><th>Qué hacer</th></tr>
    <tr><td><strong>Cada día</strong></td><td>Pasar lista y cerrar las clases. Registrar los cobros y gastos del día.</td></tr>
    <tr><td><strong>Cada semana</strong></td><td>Mirar <em>Revisión</em> y dejarla a cero. Mirar <em>Cobros › Quién debe ahora</em> y reclamar lo pendiente.</td></tr>
    <tr><td><strong>Cada mes</strong></td><td>Revisar el <em>Resumen</em> del mes, comprobar que los gastos están todos apuntados y exportar <em>Movimientos</em> para el gestor.</td></tr>
</table>

<h2 id="fm-faq">16. Preguntas frecuentes</h2>
<p class="fm-q">Me he equivocado al registrar un cobro. ¿Lo borro?</p>
<p>No se puede borrar: anúlalo escribiendo el motivo y vuelve a registrarlo bien. Las cuentas se recalculan solas.</p>
<p class="fm-q">Un alumno dice que ya pagó, pero le sale que debe.</p>
<p>Abre su cuenta (Finanzas › Alumnos) y mira el estado de cuenta. Si el pago no aparece, regístralo con la fecha en que pagó.</p>
<p class="fm-q">¿Por qué «vendido» y «cobrado» no coinciden?</p>
<p>Porque no todo lo vendido se paga el mismo día: puede haber pagos a plazos o pendientes. La diferencia acumulada es el «pendiente de cobro».</p>
<p class="fm-q">¿Qué es «Sin especificar» en los medios de pago?</p>
<p>Son los pagos anteriores a Finanzas, de los que no se sabe cómo se pagaron. Los nuevos siempre llevan su medio de pago.</p>
<p class="fm-q">Se ha descontado una sesión a un alumno que avisó a tiempo.</p>
<p>Abre Pasar lista de esa clase y pulsa <em>Devolver bono</em> en ese alumno, o cambia su estado a «ausencia justificada».</p>
<p class="fm-q">¿Quién ve estas cifras?</p>
<p>Solo el administrador principal y las personas que él autorice. Los demás administradores ven únicamente la Revisión de clases, sin importes. Los entrenadores y el staff no tienen acceso, y los alumnos solo ven sus propios pagos.</p>
<p class="fm-q">Soy administrador y no veo las finanzas, ¿es un fallo?</p>
<p>No: es lo previsto. Tienes la pestaña <em>Revisión de clases</em> (para comprobar que las clases se dieron, se pasó lista y se cerraron) y su <em>Ayuda</em>. Si necesitas ver los importes, pídeselo al administrador principal.</p>
<p class="fm-q">¿Puedo probar cosas con una cuenta de prueba sin ensuciar las cifras?</p>
<p>Sí. El administrador principal marca esa cuenta en <em>Configuración › Usuarios que no cuentan en las finanzas</em>. Desde ese momento ni ella ni sus clases cuentan en ninguna cifra, aunque siga funcionando en la plataforma. Se puede quitar de la lista cuando se quiera.</p>
<p class="fm-q">¿Puedo ver meses anteriores?</p>
<p>Sí: usa el selector de mes, o un rango «desde / hasta», en la parte de arriba de cada pestaña.</p>

<div class="fm-box fm-ok" style="margin-top:28px"><strong>¿Dudas?</strong>Usa el botón de reportar un problema de la plataforma o contacta con el administrador. Cada cifra tiene su símbolo <span class="fm-btn">?</span> con una explicación corta.</div>

</div>
