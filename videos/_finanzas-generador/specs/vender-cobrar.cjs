// Vender un bono (con descuento y cobro) y registrar un pago a plazos.
const abrir = async (p) => { await p.locator('button:has-text("Asignar bono")').first().click(); await p.waitForTimeout(500); };
const { conPrecio } = require('../lib.cjs');
const tipo = async (p) => { await abrir(p); await p.selectOption('#selectBonoType', await conPrecio(p)); };
const jugador = async (p) => { await tipo(p); await p.selectOption('#selectPlayer', '1023'); await p.waitForTimeout(800); };
const descuento = async (p) => { await jugador(p); await p.fill('#bnDiscount', '10%'); await p.fill('#bnDiscountReason', 'Hermanos'); };
const cobro = async (p) => { await descuento(p); await p.check('#bnPayNow'); await p.locator('#bnPayBox').scrollIntoViewIfNeeded(); };

module.exports = {
  intro: { title: 'Vender un bono y cobrarlo', sub: 'Asignar el bono, aplicar descuento y apuntar el pago' },
  outro: { title: 'Venta y cobro, hechos', sub: 'Lo que falte por pagar queda en <b>Cobros › Quién debe</b>' },
  scenes: [
    { id: 'bonos', url: '/bonos', dur: 6, t: 'Entra en <b>Bonos</b>',
      s: 'En el menú de la izquierda, pulsa <b>Bonos</b> y después <b>Asignar bono</b>.',
      marks: [{ sel: 'button:has-text("Asignar bono")', label: 'Asignar bono', click: true }] },
    { id: 'tipo', url: '/bonos', dur: 6.5, setup: tipo, t: 'Elige el <b>tipo</b> de bono',
      s: 'Al elegirlo ves su <b>precio</b> debajo.',
      marks: [{ sel: '#selectBonoType', label: 'Tipo de bono' }, { sel: '#bnPrice', label: 'Precio' }] },
    { id: 'jugador', url: '/bonos', dur: 6, setup: jugador, t: 'Elige al <b>jugador</b>',
      s: 'Si ya tiene bonos o clases pendientes, la plataforma te avisa aquí mismo.',
      marks: [{ sel: '#selectPlayer', label: 'Jugador' }] },
    { id: 'descuento', url: '/bonos', dur: 7.5, setup: descuento, t: '¿Hay <b>descuento</b>?',
      s: 'Escribe un importe (<b>10</b>) o un porcentaje (<b>10%</b>) y el motivo. El precio final se recalcula al instante.',
      marks: [{ sel: '#bnDiscount', label: 'Descuento' }, { sel: '#bnFinalWrap', label: 'Precio final' }] },
    { id: 'cobro', url: '/bonos', dur: 7.5, setup: cobro, t: '¿Paga <b>ahora</b>?',
      s: 'Marca <b>Cobrado ahora</b>, elige cómo paga y revisa el importe. Puede ser solo una parte. Si no paga, déjalo sin marcar.',
      marks: [{ sel: '#bnPayNow', label: 'Cobrado ahora', click: true }, { sel: '#bnPayBox' }] },
    { id: 'crear', url: '/bonos', dur: 6, setup: async (p) => { await cobro(p); await p.locator('#btnEmitir').scrollIntoViewIfNeeded(); },
      t: 'Pulsa <b>Crear bono</b>', s: 'El alumno recibe un aviso de «Tienes un bono nuevo».',
      marks: [{ sel: '#btnEmitir', label: 'Crear bono', click: true }] },
    { id: 'movs', url: '/finanzas/movimientos?mes=2026-10', dur: 6.5, t: 'Mira la venta en <b>Finanzas</b>',
      s: 'En <b>Movimientos</b> aparece la venta y, si cobraste, el cobro. Si no, queda como <b>pendiente de cobro</b>.',
      marks: [{ sel: '.fin-tabs >> text=Movimientos', label: 'Movimientos' }] },
    { id: 'cobrar', url: '/finanzas/cobros?mes=2026-10', dur: 8, t: 'Cuando <b>pague</b> el resto',
      setup: async (p) => {
        const f = p.locator('.card-jp:has-text("Registrar cobro")').first();
        await f.locator('select[name="player_id"]').selectOption({ index: 2 });
        await f.locator('input[name="amount"]').fill('60');
      },
      s: 'En <b>Cobros</b> elige alumno, importe, fecha y medio de pago, y pulsa <b>Guardar cobro</b>. Se aplica a lo más antiguo que deba.',
      marks: [{ sel: '.card-jp:has-text("Registrar cobro")', label: 'Registrar cobro' }, { sel: 'button:has-text("Guardar cobro")', label: 'Guardar', click: true }] },
    { id: 'deben', url: '/finanzas/cobros?mes=2026-10', dur: 6.5, t: '¿Quién <b>debe</b> aún?',
      s: 'A la derecha, <b>Quién debe ahora</b>: de más a menos, con un botón para cobrar.',
      marks: [{ sel: '.card-jp:has-text("Quién debe ahora")', label: 'Quién debe ahora' }] },
  ],
};
