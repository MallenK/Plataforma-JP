// La cuenta y el historial de un alumno.
const { ir } = require('../lib.cjs');

module.exports = {
  intro: { title: 'La cuenta de un alumno', sub: 'Qué ha comprado, pagado y usado' },
  outro: { title: 'Todo de un alumno, en un sitio', sub: 'Cada consulta del historial queda registrada' },
  scenes: [
    { id: 'lista', url: '/finanzas/alumnos', dur: 7, t: 'Entra en <b>Alumnos</b>',
      s: 'Cada alumno con lo comprado, lo pagado, lo que debe y sus sesiones. Filtra «solo los que deben».',
      marks: [{ sel: 'button:has-text("Filtrar")', label: 'Filtros' }, { sel: 'a:has-text("Cuenta") >> nth=0', label: 'Abrir la cuenta', click: true }] },
    { id: 'cuenta', url: '/finanzas/alumnos/1013', dur: 7.5, t: 'Su <b>cuenta</b>',
      s: 'Arriba: comprado, pagado, lo que debe (o tiene a favor), sesiones disponibles y clases sin descontar.',
      marks: [{ sel: '.metric-card >> nth=0' }, { sel: '.metric-card >> nth=2' }] },
    { id: 'estado', url: '/finanzas/alumnos/1013', deep: '.card-jp:has-text("Estado de cuenta")', dur: 7.5, t: 'El <b>estado de cuenta</b>',
      s: 'Cada cargo y cada pago por fecha, con lo que debía después de cada uno.',
      marks: [{ sel: '.card-jp:has-text("Estado de cuenta")', label: 'Estado de cuenta' }] },
    { id: 'cobrar', url: '/finanzas/alumnos/1013', deep: 'form[action$="/finanzas/cobros"]', dur: 8, t: '<b>Cobros</b> y cargos sueltos',
      s: 'A la derecha registras un cobro (eliges qué bono paga) o añades un <b>cargo</b> que no es un bono: sesión suelta, material…',
      marks: [{ sel: 'button:has-text("Guardar cobro")', label: 'Registrar cobro' }, { sel: 'button:has-text("Añadir cargo")', label: 'Añadir cargo' }] },
    { id: 'historial-btn', url: '/finanzas/alumnos/1013', dur: 6, t: 'El <b>historial completo</b>',
      s: 'Todo lo que ha pasado con ese alumno, ordenado y con alertas.',
      marks: [{ sel: 'a:has-text("Historial completo")', label: 'Historial completo', click: true }] },
    { id: 'historial', url: '/finanzas/alumnos/1013/historial', dur: 8, t: '<b>Qué revisar</b>',
      s: 'La plataforma avisa sola: <b>rojo</b> si un saldo no cuadra, <b>naranja</b> si hay clases sin descontar o pagos pendientes, <b>verde</b> si todo está bien.',
      marks: [{ sel: '.card-jp >> nth=0', label: 'Alertas' }] },
    { id: 'bonos', url: '/finanzas/alumnos/1013/historial', deep: 'text=Sus bonos, paso a paso', dur: 7.5, t: 'Sus <b>bonos</b> paso a paso',
      s: 'Cada sesión usada: qué clase, quién la descontó, cuándo y cuánto saldo quedó.',
      marks: [{ sel: 'text=Sus bonos, paso a paso', label: 'Bono por bono' }] },
    { id: 'todo', url: '/finanzas/alumnos/1013/historial', deep: 'text=Todo lo que ha pasado', dur: 8, t: '<b>Todo</b> lo que ha pasado',
      s: 'Filtra por categoría o fechas, busca y descarga en Excel o imprime. Cada consulta queda anotada.',
      marks: [{ sel: 'text=Todo lo que ha pasado', label: 'Filtra, busca y descarga' }] },
  ],
};
