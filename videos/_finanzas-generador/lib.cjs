// Utilidades comunes de las specs de captura.

/**
 * Desplaza hasta dejar el elemento centrado en el contenedor que hace scroll de verdad
 * (scrollIntoView movería también contenedores con overflow:hidden y bajaría todo el layout).
 */
const ir = (p, sel) => p.locator(sel).first().evaluate((e) => {
  let a = e.parentElement;
  while (a && !(/(auto|scroll)/.test(getComputedStyle(a).overflowY) && a.scrollHeight > a.clientHeight)) a = a.parentElement;
  const sc = a || document.scrollingElement;
  const r = e.getBoundingClientRect();
  const top = a ? a.getBoundingClientRect().top : 0;
  const h = a ? a.clientHeight : window.innerHeight;
  sc.scrollTop += r.top - top - Math.max(90, (h - r.height) / 2);
});

/** Elige el primer tipo de bono con precio (el modal de «Asignar bono» debe estar abierto). */
const conPrecio = (p) => p.evaluate(() => {
  const o = [...document.querySelectorAll('#selectBonoType option')].find((x) => parseInt(x.dataset.price || '0', 10) > 0);
  return o ? o.value : '';
});

module.exports = { ir, conPrecio };
