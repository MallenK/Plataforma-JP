/**
 * Posiciones de los elementos de las pantallas (relativas a .screen), medidas con probe.cjs.
 * Uso en una spec:  const P = require('./probe-lib.cjs').make('iphone');
 *   P.c('p-share')           → { x, y }   centro
 *   P.b('p-share', 8)        → { x, y, w, h }   caja con margen
 */
const fs = require('fs');
const path = require('path');

function make(name) {
  const file = path.join(__dirname, `probe-${name}.json`);
  const data = fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : {};
  const get = (id) => data[id] || { x: 0, y: 0, w: 40, h: 40 };
  return {
    c: (id, dx = 0, dy = 0) => { const r = get(id); return { x: Math.round(r.x + r.w / 2 + dx), y: Math.round(r.y + r.h / 2 + dy) }; },
    b: (id, pad = 0) => { const r = get(id); return { x: Math.round(r.x - pad), y: Math.round(r.y - pad), w: Math.round(r.w + pad * 2), h: Math.round(r.h + pad * 2) }; },
  };
}
module.exports = { make };
