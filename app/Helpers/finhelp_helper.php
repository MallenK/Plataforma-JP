<?php

/**
 * Textos de ayuda de Finanzas (tooltips «?» y manual). Un solo sitio para que
 * la ayuda en pantalla y el manual digan lo mismo. Lenguaje llano: lo lee
 * alguien sin contexto técnico.
 */
if (!function_exists('fin_help_texts')) {
    function fin_help_texts(): array
    {
        return [
            // Resumen
            'vendido'      => 'Lo que se ha vendido en el periodo: bonos (con su descuento) y cargos sueltos. Es lo que los alumnos se han comprometido a pagar, lo hayan pagado ya o no.',
            'cobrado'      => 'El dinero que ha entrado de verdad en el periodo: efectivo, Bizum, transferencia, tarjeta… Es la caja.',
            'servicio'     => 'Las clases que ya se han dado y descontado de un bono, valoradas a lo que costó cada sesión de ese bono. Es lo que la academia ya ha «entregado».',
            'gastos'       => 'Lo que la academia ha gastado en el periodo: material, reparaciones, alquiler de campos, entrenadores…',
            'resultado'    => 'Cobrado menos gastos del periodo: el dinero que queda.',
            'pendiente'    => 'Lo vendido que todavía no se ha pagado, a día de hoy. Pulsa para ver quién debe.',
            'pagado_sin_dar' => 'Sesiones que los alumnos ya han comprado y aún no han usado. Es lo que la academia todavía debe dar.',
            'caducado'     => 'Sesiones de bonos que caducaron sin usarse en el periodo. Ese dinero se da por ganado, pero se señala aparte.',
            'precio_medio' => 'Lo que se cobra de media por cada sesión, sumando todos los bonos vendidos.',
            'asistencia'   => 'Cómo fue la asistencia en las clases cerradas del periodo y qué estados gastan una sesión del bono.',

            // Movimientos
            'mov_venta'    => 'Un bono vendido o un cargo suelto (por ejemplo, una sesión suelta).',
            'mov_cobro'    => 'Un pago de un alumno.',
            'mov_gasto'    => 'Un gasto de la academia.',
            'mov_servicio' => 'Una sesión de bono consumida en una clase (asistió, faltó sin justificar o avisó tarde).',
            'mov_caducado' => 'Un bono que caducó con sesiones sin usar.',
            'export'       => 'Descarga todas las operaciones del periodo en un fichero que se abre con Excel.',

            // Cobros
            'cobro_alumno' => 'Quién paga.',
            'cobro_importe'=> 'Lo que paga ahora. Puede ser una parte (pago a plazos): el resto queda pendiente.',
            'cobro_medio'  => 'Cómo ha pagado. Sirve para saber cuánto entra por Bizum, en efectivo, etc.',
            'cobro_reparto'=> 'El pago se aplica primero a lo más antiguo que deba. Si sobra dinero, queda a favor del alumno y se usa solo en su próximo bono.',
            'cobro_ref'    => 'Opcional: el número del Bizum o de la transferencia, para encontrarlo luego.',
            'quien_debe'   => 'Alumnos con algo pendiente de pagar, de mayor a menor.',
            'anular'       => 'Si te equivocaste, anúlalo indicando el motivo. No se borra: queda tachado y visible, y las cuentas se recalculan.',

            // Gastos
            'gasto_cat'    => 'De qué tipo es el gasto. Las categorías se pueden cambiar en Configuración.',
            'gasto_staff'  => 'Opcional: si el gasto es de un entrenador (por ejemplo, lo que se le paga), elígelo aquí y aparecerá en la pestaña Entrenadores.',
            'gasto_sede'   => 'Opcional: si el gasto es de un campo o sede concreta.',
            'gasto_file'   => 'Opcional: una foto del ticket o la factura (PDF o imagen). Se guarda de forma privada.',

            // Alumnos / cuenta
            'comprado'     => 'Todo lo que el alumno ha comprado (bonos y cargos), sin contar lo anulado.',
            'pagado'       => 'Todo lo que el alumno ha pagado, sin contar lo anulado.',
            'debe'         => 'Comprado menos pagado. Si sale «a su favor», el alumno ha pagado de más y se le aplicará en el próximo bono.',
            'sesiones'     => 'Sesiones que le quedan en bonos que todavía no han caducado.',
            'sin_descontar'=> 'Clases a las que asistió y que no se descontaron de ningún bono. Hay que revisarlas.',
            'historial'    => 'Reúne pagos y cargos, bonos y sus movimientos, clases y asistencia, avisos del alumno, accesos, mensajes (solo el registro, no el texto), tickets, avisos, emails, documentos, anotaciones y cambios registrados. Cada consulta queda anotada en la auditoría.',
            'estado_cuenta'=> 'Todos sus cargos y pagos por fecha, con lo que debía después de cada uno.',
            'cargo_manual' => 'Para cobrar algo que no es un bono: una sesión suelta, material, una inscripción… Los bonos se cargan solos al venderlos.',
            'descuento'    => 'Escribe un importe (10) o un porcentaje (10%). Indica el motivo: hermanos, promoción, cortesía…',

            // Entrenadores / análisis
            'ent_clases'   => 'Clases ya cerradas en las que figura como responsable.',
            'ent_servicio' => 'Valor de las sesiones de bono consumidas en sus clases.',
            'ent_gastos'   => 'Gastos que se le han asignado en la pestaña Gastos (por ejemplo, lo que se le paga).',
            'an_tipos'     => 'Qué bonos se venden más y cuánto dejan, con el precio medio por sesión.',

            // Revisión / configuración
            'rev_sesiones' => 'Clases cuya fecha ya pasó y que nadie cerró. Ábrelas: pasa lista y ciérralas si se dieron, o cancélalas si no se dieron.',
            'rev_sin_bono' => 'Clases dadas que no se descontaron de ningún bono. Se pueden saldar con un bono, marcar como pagadas aparte o no cobrar.',
            'rev_precios'  => 'Bonos de antes de esta versión cuyo precio pagado no se guardaba.',
            'rev_caducados'=> 'Solo informativo: bonos que caducaron con sesiones sin usar.',
            'cfg_auto'     => 'Si está activado, una falta sin justificar o un aviso tardío descuenta la sesión solo al guardar la lista. Siempre se puede devolver.',
            'cfg_horas'    => 'Con cuántas horas de antelación tiene que avisar un alumno para no perder la sesión. Por defecto, 24.',

            // Venta de bono
            'venta_pago'   => 'Márcalo si el alumno paga al momento. Si no, el bono queda pendiente de cobro en Finanzas.',
        ];
    }
}

if (!function_exists('fin_help')) {
    /**
     * Icono «?» con su explicación (tooltip). Accesible: botón con aria-label,
     * se abre al pasar el ratón, con el teclado o tocando en el móvil.
     */
    function fin_help(string $key, ?string $text = null): string
    {
        $t = $text ?? (fin_help_texts()[$key] ?? '');
        if ($t === '') {
            return '';
        }
        $e = esc($t, 'attr');
        return '<button type="button" class="fin-help" data-bs-toggle="tooltip" data-bs-title="' . $e . '" aria-label="Ayuda: ' . $e . '"><i class="bi bi-question-circle" aria-hidden="true"></i></button>';
    }
}
