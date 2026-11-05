// Registrar un gasto de la academia.
const form = (p) => p.locator('.card-jp:has-text("Registrar gasto")').first();
const rellenar = async (p) => {
  const f = form(p);
  await f.locator('input[name="amount"]').fill('24,90');
  await f.locator('input[name="spent_at"]').fill('2026-10-28');
  await f.locator('select[name="category_id"]').selectOption({ label: 'Material' });
  await f.locator('select[name="method_id"]').selectOption({ index: 1 });
  await f.locator('input[name="description"]').fill('Balones de entrenamiento');
};

module.exports = {
  intro: { title: 'Registrar un gasto', sub: 'Material, alquiler de campos, entrenadores…' },
  outro: { title: 'Gasto registrado', sub: 'Baja el <b>Resultado</b> del mes y suma en su categoría' },
  scenes: [
    { id: 'tab', url: '/finanzas/resumen?mes=2026-10', dur: 5.5, t: 'Entra en <b>Finanzas › Gastos</b>',
      s: 'Aquí se apunta todo lo que paga la academia.',
      marks: [{ sel: '.fin-tabs >> text=Gastos', label: 'Gastos', click: true }] },
    { id: 'importe', url: '/finanzas/gastos?mes=2026-10', setup: rellenar, dur: 8, t: '<b>Importe</b> y fecha',
      s: 'Escribe cuánto fue y el día en que se pagó.',
      marks: [{ sel: '.card-jp:has-text("Registrar gasto") input[name="amount"]', label: 'Importe' }, { sel: '.card-jp:has-text("Registrar gasto") input[name="spent_at"]', label: 'Fecha' }] },
    { id: 'categoria', url: '/finanzas/gastos?mes=2026-10', setup: rellenar, dur: 7, t: '<b>Categoría</b> y forma de pago',
      s: 'La categoría sirve para ver en qué se va el dinero. Añade una descripción corta.',
      marks: [{ sel: '.card-jp:has-text("Registrar gasto") select[name="category_id"]', label: 'Categoría' }, { sel: '.card-jp:has-text("Registrar gasto") input[name="description"]', label: 'Descripción' }] },
    { id: 'extras', url: '/finanzas/gastos?mes=2026-10', setup: async (p) => { await rellenar(p); const f = form(p); await f.locator('select[name="staff_id"]').selectOption({ index: 1 }); await f.locator('select[name="location_id"]').selectOption({ index: 1 }); },
      dur: 8, t: '¿De un <b>entrenador</b> o una sede?',
      s: 'Opcional: asígnalo para verlo en la pestaña Entrenadores. Y adjunta una <b>foto del ticket</b> o la factura.',
      marks: [{ sel: '.card-jp:has-text("Registrar gasto") select[name="staff_id"]', label: 'Entrenador' }, { sel: '.card-jp:has-text("Registrar gasto") select[name="location_id"]', label: 'Sede' }] },
    { id: 'guardar', url: '/finanzas/gastos?mes=2026-10', setup: async (p) => { await rellenar(p); await p.locator('button:has-text("Guardar gasto")').scrollIntoViewIfNeeded(); },
      dur: 6, t: 'Pulsa <b>Guardar gasto</b>', s: 'Ya cuenta en las cifras del mes.',
      marks: [{ sel: 'button:has-text("Guardar gasto")', label: 'Guardar gasto', click: true }] },
    { id: 'lista', url: '/finanzas/gastos?mes=2026-10', dur: 7, t: 'La <b>lista</b> de gastos',
      s: 'Filtra por categoría y abre el ticket con <b>Ver</b>.',
      marks: [{ sel: '.card-jp:has-text("Gastos del periodo")', label: 'Gastos del periodo' }] },
    { id: 'anular', url: '/finanzas/gastos?mes=2026-10', dur: 7.5, t: '¿Te has <b>equivocado</b>?',
      s: 'No se borra: escribe el <b>motivo</b> y pulsa anular. Queda tachado y las cuentas se recalculan.',
      marks: [{ sel: 'form[action*="/anular"] input[name="reason"]', label: 'Motivo' }, { sel: 'form[action*="/anular"] button', label: 'Anular', click: true }] },
  ],
};
