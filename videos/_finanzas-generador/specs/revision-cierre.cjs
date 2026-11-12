// Revisión semanal y cierre del mes.
const { ir } = require('../lib.cjs');

module.exports = {
  intro: { title: 'Revisión semanal y cierre de mes', sub: 'Dejar las cuentas al día y pasar los números al gestor' },
  outro: { title: 'Cuentas al día', sub: 'Una vez por semana: <b>Revisión a cero</b>' },
  scenes: [
    { id: 'rev', url: '/finanzas/revision', dur: 7.5, step: 'Cada semana', t: 'La <b>Revisión</b>',
      s: 'El número de la pestaña dice cuántas cosas quedan por revisar. El objetivo es dejarla <b>a cero</b>.',
      marks: [{ sel: '.fin-tabs >> text=Revisión', label: 'Revisión', click: true }] },
    { id: 'cards', url: '/finanzas/revision', dur: 8, step: 'Cada semana', t: 'Qué <b>mirar</b>',
      s: 'Falta pasar lista · falta cerrar la clase · sin entrenador · clases vacías · clases sin bono descontado · precio por confirmar.',
      marks: [{ union: ['.metric-card >> nth=0', '.metric-card >> nth=5'], label: 'Los seis controles' }] },
    { id: 'sinlista', url: '/finanzas/revision', deep: '.card-jp:has-text("Clases dadas en las que falta pasar lista")', dur: 8, step: 'Cada semana',
      t: '<b>Pasar lista</b> y cerrar',
      s: 'Abre la clase: si se dio, pasa lista y ciérrala; si no se dio, cancélala.',
      marks: [{ sel: 'a:has-text("Pasar lista") >> nth=0', label: 'Pasar lista', click: true }] },
    { id: 'sinbono', url: '/finanzas/revision', deep: '.card-jp:has-text("Clases dadas sin descontar del bono")', dur: 8, step: 'Cada semana',
      t: 'Clases <b>sin bono</b>',
      s: 'Elige: saldar con un bono, «ya está pagada» (se cobró aparte) o «no cobrar».',
      marks: [{ sel: '.card-jp:has-text("Clases dadas sin descontar del bono")', label: 'Clases sin bono' }] },
    { id: 'resumen', url: '/finanzas/resumen?mes=2026-10', dur: 7.5, step: 'A final de mes', t: 'Revisa el <b>Resumen</b>',
      s: 'Comprueba vendido, cobrado, gastos y resultado, y que todos los gastos del mes están apuntados.',
      marks: [{ union: ['.metric-card >> nth=0', '.metric-card >> nth=3'], label: 'Cifras del mes' }] },
    { id: 'csv', url: '/finanzas/movimientos?mes=2026-10', dur: 7, step: 'A final de mes', t: 'Para el <b>gestor</b>',
      s: 'En <b>Movimientos</b> elige el mes y pulsa <b>Exportar CSV</b>: se abre con Excel.',
      marks: [{ sel: 'a:has-text("Exportar CSV"), button:has-text("Exportar CSV")', label: 'Exportar CSV', click: true }] },
    { id: 'exportar', url: '/finanzas/exportar', dur: 8.5, step: 'A final de mes', t: 'O <b>todos</b> los datos',
      s: 'En la pestaña <b>Exportar</b> elige periodo y formato (JSON o ZIP de Excel). Si vas a compartirlo, marca <b>Ocultar nombres</b>.',
      marks: [{ sel: '.card-jp:has-text("Descargar datos")', label: 'Exportar' }, { sel: 'button:has-text("Descargar")', label: 'Descargar', click: true }] },
  ],
};
