// Dar un bono a un alumno que tiene clases dadas o programadas sin bono.
// Datos de ejemplo (alumno 1011, sin bonos): se crean antes de capturar y se borran después.
const TAG = 'ejemplo-video';
const abrir = async (p) => { await p.locator('button:has-text("Asignar bono")').first().click(); await p.waitForTimeout(500); };
const { conPrecio } = require('../lib.cjs');
const alumno = async (p) => { await abrir(p); await p.selectOption('#selectBonoType', await conPrecio(p));await p.selectOption('#selectPlayer', '1011'); await p.waitForTimeout(1200); };
const borrar = (execSql) => {
  execSql(`DELETE FROM class_session_players WHERE session_id IN (SELECT id FROM class_sessions WHERE location_custom='${TAG}')`);
  execSql(`DELETE FROM class_sessions WHERE location_custom='${TAG}'`);
};

module.exports = {
  prepare: async ({ execSql }) => {
    borrar(execSql);
    execSql(`INSERT INTO class_sessions (title,session_date,start_time,end_time,status,created_by,location_custom) VALUES ('Técnica individual','2026-10-20','18:00:00','19:00:00','completed',2,'${TAG}'),('Porteros','2026-10-27','18:00:00','19:00:00','completed',2,'${TAG}'),('Técnica individual','2026-11-10','18:00:00','19:00:00','scheduled',2,'${TAG}'),('Porteros','2026-11-12','18:00:00','19:00:00','scheduled',2,'${TAG}')`);
    execSql(`INSERT INTO class_session_players (id,session_id,user_id,attendance) SELECT 8000000+id,id,1011,IF(status='completed','present','confirmed') FROM class_sessions WHERE location_custom='${TAG}'`);
  },
  cleanup: async ({ execSql }) => borrar(execSql),
  intro: { title: 'Saldar clases al dar un bono', sub: 'Cuando el alumno ya vino (o está apuntado) sin tener bono' },
  outro: { title: 'Clases saldadas', sub: 'Las que no marques siguen pendientes · todo se puede <b>devolver</b>' },
  scenes: [
    { id: 'problema', url: '/finanzas/revision', dur: 7.5, step: 'El problema',
      t: 'Clases dadas <b>sin bono</b>',
      s: 'Si un alumno viene sin tener bono con saldo, la clase queda pendiente en <b>Revisión</b> hasta que se le da uno.',
      marks: [{ sel: '.metric-card >> nth=4', label: 'Clases sin bono' }] },
    { id: 'abrir', url: '/bonos', dur: 5.5, t: 'Entra en <b>Bonos › Asignar bono</b>',
      s: 'Es el mismo paso de siempre para dar un bono.',
      marks: [{ sel: 'button:has-text("Asignar bono")', label: 'Asignar bono', click: true }] },
    { id: 'alumno', url: '/bonos', setup: alumno, dur: 8, t: 'Elige tipo y <b>alumno</b>',
      s: 'Si el alumno tiene clases pendientes, aparece un recuadro naranja con <b>casillas</b>.',
      marks: [{ sel: '#selectPlayer', label: 'Alumno' }, { sel: '#alertaDeudas', label: 'Clases pendientes' }] },
    { id: 'grupos', url: '/bonos', setup: alumno, dur: 8.5, t: 'Dos <b>grupos</b> de clases',
      s: '<b>Ya dadas, sin bono</b> (vienen marcadas) y <b>programadas</b> confirmadas sin descontar (sin marcar), pasadas de fecha o no.',
      zoom: 1.3, marks: [{ sel: '#alertaDeudasLista', label: 'Marca las que quieras' }] },
    { id: 'marcar', url: '/bonos', setup: async (p) => { await alumno(p); await p.locator('#alertaDeudasLista .chk-saldar').nth(2).check(); },
      dur: 8, t: 'Marca las que <b>quieras</b>',
      s: 'Abajo ves el resumen: cuántas se saldan y cuántas sesiones le quedarán al bono.',
      marks: [{ sel: '#alertaDeudasLista .chk-saldar >> nth=2', click: true }, { sel: '#alertaDeudasResumen', label: 'Resumen' }] },
    { id: 'crear', url: '/bonos', setup: async (p) => { await alumno(p); await p.locator('#alertaDeudasLista .chk-saldar').nth(2).check(); await p.locator('#btnEmitir').scrollIntoViewIfNeeded(); },
      dur: 7, t: 'Pulsa <b>Crear bono y saldar</b>',
      s: 'Se gasta <b>1 sesión por clase</b>, de la más antigua a la más reciente. Si no marcas ninguna, no se descuenta nada.',
      marks: [{ sel: '#btnEmitir', label: 'Crear bono y saldar', click: true }] },
  ],
};
