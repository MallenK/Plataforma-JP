/** Piezas de interfaz de iPhone (iOS) dibujadas en HTML/CSS. Pantalla = 614 × 1370 px. */

const svg = (inner, size = 24, extra = '') =>
  `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ${extra}>${inner}</svg>`;

const I = {
  share: (s = 40) => svg('<path d="M12 15V3M8 7l4-4 4 4"/><path d="M7 10H6a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1h-1"/>', s),
  back: (s = 40) => svg('<path d="M15 5l-7 7 7 7"/>', s),
  fwd: (s = 40) => svg('<path d="M9 5l7 7-7 7"/>', s),
  book: (s = 40) => svg('<path d="M3 5.5h6.5A2.5 2.5 0 0 1 12 8v12a2.5 2.5 0 0 0-2.5-2.5H3z"/><path d="M21 5.5h-6.5A2.5 2.5 0 0 0 12 8v12a2.5 2.5 0 0 1 2.5-2.5H21z"/>', s),
  tabs: (s = 40) => svg('<rect x="7" y="3" width="14" height="14" rx="3"/><path d="M17 20H6a3 3 0 0 1-3-3V8"/>', s),
  dots: (s = 40) => svg('<circle cx="6" cy="12" r="1.4" fill="currentColor"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/><circle cx="18" cy="12" r="1.4" fill="currentColor"/>', s),
  plusSq: (s = 34) => svg('<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/>', s),
  copy: (s = 34) => svg('<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>', s),
  star: (s = 34) => svg('<path d="M12 3l2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8 6.6 19.7l1.1-6.1L3.2 9.4l6.1-.8z"/>', s),
  glass: (s = 34) => svg('<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>', s),
  glasses: (s = 34) => svg('<circle cx="7" cy="14" r="3.5"/><circle cx="17" cy="14" r="3.5"/><path d="M10.5 14h3M3.5 14L5 8h2M20.5 14L19 8h-2"/>', s),
  pen: (s = 34) => svg('<path d="M4 20l4-1 11-11-3-3L5 16z"/>', s),
  print: (s = 34) => svg('<path d="M7 9V3h10v6"/><rect x="4" y="9" width="16" height="8" rx="2"/><rect x="7" y="14" width="10" height="7"/>', s),
  tab: (s = 34) => svg('<rect x="4" y="5" width="16" height="14" rx="3"/><path d="M4 10h16"/>', s),
  lock: (s = 22) => svg('<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>', s),
  close: (s = 30) => svg('<path d="M6 6l12 12M18 6L6 18"/>', s),
};

/** Barra de estado de iPhone (isla dinámica). color: '#000' o '#fff'. */
function sb(color = '#000') {
  return `<div class="sbi" style="color:${color}"><span class="tm">9:41</span><i class="isl"></i>` +
    `<span class="sg"><svg width="34" height="22" viewBox="0 0 34 22" fill="currentColor"><rect x="0" y="14" width="5" height="8" rx="1.5"/><rect x="9" y="9" width="5" height="13" rx="1.5"/><rect x="18" y="4" width="5" height="18" rx="1.5"/><rect x="27" y="0" width="5" height="22" rx="1.5"/></svg>` +
    `<svg width="36" height="22" viewBox="0 0 36 22" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="2" width="30" height="18" rx="5" opacity=".45"/><rect x="4" y="5" width="24" height="12" rx="3" fill="currentColor" stroke="none"/><rect x="33" y="8" width="2.5" height="6" rx="1" fill="currentColor" stroke="none" opacity=".45"/></svg></span></div>`;
}
const indicator = (color = '#000') => `<i class="hbar" style="background:${color}"></i>`;

/** Icono genérico de aplicación del iPhone. */
function app(label, bg, glyph, extra = '', id = '') {
  return `<div ${id ? `id="${id}" ` : ''}class="ai ${extra}"><div class="ic" style="background:${bg}">${glyph || ''}</div><span>${label}</span></div>`;
}
const G = {
  phone: svg('<path d="M6.5 3.5l3 .8 1 4-2 1.2a11 11 0 0 0 5 5l1.2-2 4 1 .8 3a2 2 0 0 1-2 2.5A15 15 0 0 1 4 5.5a2 2 0 0 1 2.5-2z" fill="#fff" stroke="none"/>', 58),
  msg: svg('<path d="M12 4c5 0 9 3.2 9 7.3S17 18.6 12 18.6c-1 0-2-.1-2.8-.4L5 20l1-3.6C4.2 15.3 3 13.4 3 11.3 3 7.2 7 4 12 4z" fill="#fff" stroke="none"/>', 58),
  music: svg('<path d="M9 18V6l10-2v12" /><circle cx="7" cy="18" r="2.4" fill="#fff"/><circle cx="17" cy="16" r="2.4" fill="#fff"/>', 54, 'stroke="#fff"'),
  compass: `<svg viewBox="0 0 64 64" width="104" height="104"><defs><linearGradient id="sg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4fc3ff"/><stop offset="1" stop-color="#1a6dff"/></linearGradient></defs><rect width="64" height="64" rx="15" fill="url(#sg)"/><circle cx="32" cy="32" r="22" fill="none" stroke="#fff" stroke-width="2.5"/><path d="M42 22L35 35 22 42l7-13z" fill="#ff453a"/><path d="M42 22L35 35 29 29z" fill="#fff"/></svg>`,
  cam: svg('<path d="M4 8h3l1.5-2h7L17 8h3v11H4z" fill="#fff" stroke="none"/><circle cx="12" cy="13.5" r="3.4" fill="#8e8e93" stroke="none"/>', 56),
  map: svg('<path d="M12 21s7-6.3 7-11a7 7 0 0 0-14 0c0 4.7 7 11 7 11z" fill="#fff" stroke="none"/><circle cx="12" cy="10" r="2.6" fill="#34c759" stroke="none"/>', 56),
  clock: svg('<circle cx="12" cy="12" r="9" stroke="#fff"/><path d="M12 7v5l3.5 2" stroke="#fff"/>', 56),
  cloud: svg('<path d="M7 18a4 4 0 0 1-.4-8A5.5 5.5 0 0 1 17 8.5 4.8 4.8 0 0 1 17 18z" fill="#fff" stroke="none"/>', 56),
  note: svg('<path d="M6 4h12v16H6z" fill="#fff" stroke="none"/><path d="M9 9h6M9 13h6" stroke="#f5a623"/>', 52),
  gear: svg('<circle cx="12" cy="12" r="3.2" stroke="#fff"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" stroke="#fff"/>', 56),
  heart: svg('<path d="M12 20s-7-4.6-7-10a4 4 0 0 1 7-2.4A4 4 0 0 1 19 10c0 5.4-7 10-7 10z" fill="#ff375f" stroke="none"/>', 56),
  photo: `<svg viewBox="0 0 64 64" width="60" height="60"><g opacity=".85"><circle cx="32" cy="16" r="10" fill="#ff9f0a"/><circle cx="46" cy="32" r="10" fill="#ff453a"/><circle cx="32" cy="48" r="10" fill="#bf5af2"/><circle cx="18" cy="32" r="10" fill="#30d158"/></g></svg>`,
  jp: '<img src="assets/jp-icon.png" alt="" />',
};

/** Pantalla de inicio. withJP = incluye el icono de JP Prep. */
function home(id, withJP) {
  const rows = [
    [['Fotos', '#fff', G.photo], ['Cámara', '#5b5b61', G.cam], ['Mapas', '#6fd68c', G.map], ['Reloj', '#111', G.clock]],
    [['Tiempo', 'linear-gradient(#4aa3ff,#2468e6)', G.cloud], ['Notas', '#ffd60a', G.note], ['Ajustes', '#8e8e93', G.gear], ['Salud', '#fff', G.heart]],
  ];
  const jp = withJP ? app('JP Prep', '#0f172a', G.jp, 'jpapp', 'p-jp') : '';
  const safari = withJP ? app('', 'transparent', G.compass, 'nolabel safari') : app('', 'transparent', G.compass, 'nolabel safari', 'p-safari');
  return `<div id="${id}" class="layer ios-home">${sb('#fff')}
    <div class="grid">${rows.map((r) => r.map((a) => app(...a)).join('')).join('')}${jp}</div>
    <div class="dock">${app('', '#34c759', G.phone, 'nolabel')}${safari}${app('', '#34c759', G.msg, 'nolabel')}${app('', '#ff453a', G.music, 'nolabel')}</div>
    ${indicator('#fff')}</div>`;
}

function keyboard() {
  const row = (s, pad = 0) => `<div class="kr" style="padding:0 ${pad}px">${[...s].map((c) => `<b>${c}</b>`).join('')}</div>`;
  return `<div class="kb">${row('qwertyuiop')}${row('asdfghjkl', 22)}${row('zxcvbnm', 62)}<div class="kr"><b class="w">123</b><b class="sp">espacio</b><b id="p-go" class="w go">Ir</b></div></div>`;
}

module.exports = { I, sb, indicator, app, G, home, keyboard, svg };
