// Descuento automático de sesiones (Finanzas › Configuración) y cómo devolver una.
// Datos de ejemplo: una clase de hoy con Iker (1023, falta sin justificar ya descontada de un bono)
// y Emma (1011, pendiente de marcar). Se crean antes de capturar y se borran después.
const TAG = 'ejemplo-video';
let claseId = 0;
const { ir } = require('../lib.cjs');
const borrar = (execSql) => {
  execSql(`DELETE FROM class_session_players WHERE session_id IN (SELECT id FROM class_sessions WHERE location_custom='${TAG}')`);
  execSql(`DELETE FROM class_sessions WHERE location_custom='${TAG}'`);
  execSql(`DELETE FROM player_bonos WHERE notes='${TAG}'`);
};

module.exports = {
  prepare: async ({ execSql }) => {
    borrar(execSql);
    execSql(`INSERT INTO player_bonos (id,player_id,bono_type_id,sessions_total,sessions_remaining,start_date,expires_at,created_by,notes) SELECT 8000001,1023,MIN(id),4,3,'2026-10-15','2027-01-15',2,'${TAG}' FROM bono_types`);
    execSql(`INSERT INTO class_sessions (title,session_date,start_time,end_time,status,created_by,location_custom) VALUES ('Técnica individual','2026-11-04','18:00:00','19:00:00','scheduled',2,'${TAG}')`);
    execSql(`INSERT INTO class_session_players (id,session_id,user_id,attendance,bono_deducted_at,bono_deducted_from_id) SELECT 8100000+cs.id,cs.id,1023,'unjustified',NOW(),(SELECT id FROM player_bonos WHERE notes='${TAG}' LIMIT 1) FROM class_sessions cs WHERE cs.location_custom='${TAG}'`);
    execSql(`INSERT INTO class_session_players (id,session_id,user_id,attendance) SELECT 8200000+id,id,1011,'pending' FROM class_sessions WHERE location_custom='${TAG}'`);
    claseId = parseInt(execSql(`SELECT id FROM class_sessions WHERE location_custom='${TAG}' LIMIT 1`).trim(), 10);
  },
  cleanup: async ({ execSql }) => borrar(execSql),
  intro: { title: 'Descuento automático de sesiones', sub: 'Qué hace, cuándo se descuenta y cómo devolver una sesión' },
  outro: { title: 'Una regla clara para todos', sub: 'Se activa en <b>Finanzas › Configuración</b> · siempre se puede deshacer' },
  get scenes() {
    return [
      { id: 'opcion', url: '/finanzas/configuracion', deep: '.rg-grid', dur: 8, step: 'La opción',
        t: 'Que <b>nadie</b> se olvide de cobrar',
        s: 'Si está activada, al guardar la lista una <b>falta sin justificar</b> o un <b>aviso tardío</b> gasta 1 sesión del bono.',
        marks: [{ sel: 'label:has(#cf-auto)', label: 'Descontar automáticamente', click: true }] },
      { id: 'tabla', url: '/finanzas/configuracion', deep: '.rg-table', dur: 9.5, step: 'Cuándo se descuenta',
        t: 'Qué <b>gasta</b> sesión',
        s: 'Presente, falta sin justificar y aviso con menos de 24 h: <b>sí</b>. Aviso con 24 h o más y ausencia justificada: <b>no</b>.',
        marks: [{ sel: '.rg-table', label: 'Sí / No' }] },
      { id: 'ejemplo', url: '/finanzas/configuracion', deep: '.rg-box:has(h4:has-text("Ejemplo"))', dur: 8.5, step: 'Un ejemplo',
        t: 'Avisó <b>tarde</b>',
        s: 'Clase a las 17:00. Avisa a las 10:00 del mismo día: solo 7 horas → se descuenta 1 sesión. Si avisa 25 horas antes, no.',
        marks: [{ sel: '.rg-box:has(h4:has-text("Ejemplo"))', label: 'Ejemplo' }] },
      { id: 'horas', url: '/finanzas/configuracion', deep: '#cf-hours', setup: async (p) => { await p.fill('#cf-hours', '12'); },
        dur: 7, step: 'Ajustable', t: 'Cambia las <b>horas</b>',
        s: 'Por defecto son 24. Los textos de la pantalla se actualizan al escribir. No olvides <b>Guardar reglas</b>.',
        marks: [{ sel: '#cf-hours', label: 'Horas de aviso' }, { sel: 'button:has-text("Guardar reglas")', label: 'Guardar reglas' }] },
      { id: 'onoff', url: '/finanzas/configuracion', deep: '.rg-box:has(h4:has-text("Si está ACTIVADO"))', dur: 8.5, step: 'Activada o no',
        t: 'Qué <b>cambia</b>',
        s: 'Activada: se descuenta solo, del bono que caduca antes. Desactivada: se descuenta a mano con <b>Descontar bono</b>.',
        marks: [{ sel: '.rg-box:has(h4:has-text("Si está ACTIVADO"))', label: 'Activada' }, { sel: '.rg-box:has(h4:has-text("Si está DESACTIVADO"))', label: 'Desactivada' }] },
      { id: 'lista', url: '/clases/' + claseId + '/lista', setup: async (p) => { await p.selectOption('select[name="attendance[1011]"]', 'unjustified'); },
        dur: 8, step: 'En la práctica', t: 'Pasa lista como <b>siempre</b>',
        s: 'Marca la asistencia. Al guardar, la falta sin justificar de Emma descuenta su sesión sola.',
        marks: [{ sel: 'select[name="attendance[1011]"]', label: 'Falta sin justificar' }, { sel: 'button:has-text("Guardar y cerrar")', label: 'Guardar', click: true }] },
      { id: 'devolver', url: '/clases/' + claseId + '/lista', dur: 8, step: 'Si hay un error',
        t: '<b>Devolver</b> la sesión',
        s: 'Se puede deshacer en la misma lista (entrenador, admin y superadmin): pulsa <b>Devolver bono</b> y la sesión vuelve al bono.',
        marks: [{ sel: 'button:has-text("Devolver bono")', label: 'Devolver bono', click: true }] },
      { id: 'reglas', url: '/finanzas/configuracion', deep: '.rg-box:has(h4:has-text("Reglas de uso"))', dur: 9, step: 'Buenas prácticas',
        t: 'Reglas de <b>uso</b>',
        s: 'Avisa antes a las familias, marca bien «Avisó» o «Falta», guarda la lista completa y revisa Revisión cada semana.',
        marks: [{ sel: '.rg-box:has(h4:has-text("Reglas de uso"))', label: 'Reglas de uso' }] },
    ];
  },
};
