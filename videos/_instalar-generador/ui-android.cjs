/** Piezas de interfaz de Android (Pixel / Chrome) dibujadas en HTML/CSS. Pantalla = 614 × 1370 px. */
const { svg, I } = require('./ui-ios.cjs');

const A = {
  dots: (s = 34) => svg('<circle cx="12" cy="5" r="1.7" fill="currentColor"/><circle cx="12" cy="12" r="1.7" fill="currentColor"/><circle cx="12" cy="19" r="1.7" fill="currentColor"/>', s),
  install: (s = 32) => svg('<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M12 8v6M9.5 11.5L12 14l2.5-2.5"/>', s),
  newtab: (s = 32) => svg('<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M12 8.5v7M8.5 12h7"/>', s),
  incog: (s = 32) => svg('<path d="M4 12h16M7 12l1.5-6h7L17 12"/><circle cx="8" cy="16.5" r="2.5"/><circle cx="16" cy="16.5" r="2.5"/>', s),
  hist: (s = 32) => svg('<path d="M4 12a8 8 0 1 0 2.6-5.9L4 8.5"/><path d="M4 4v4.5h4.5M12 8v4.5l3 1.5"/>', s),
  down: (s = 32) => svg('<path d="M12 4v11M7.5 10.5L12 15l4.5-4.5M5 20h14"/>', s),
  star: (s = 32) => svg('<path d="M12 3l2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8 6.6 19.7l1.1-6.1L3.2 9.4l6.1-.8z"/>', s),
  gear: (s = 32) => svg('<circle cx="12" cy="12" r="3.2"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>', s),
  share: (s = 32) => svg('<circle cx="6" cy="12" r="2.4"/><circle cx="17" cy="6" r="2.4"/><circle cx="17" cy="18" r="2.4"/><path d="M8.2 11l6.6-3.8M8.2 13l6.6 3.8"/>', s),
  bell: (s = 44) => svg('<path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2h-15z"/><path d="M10 21h4"/>', s),
  enter: (s = 34) => svg('<path d="M5 12h14M13 6l6 6-6 6"/>', s),
  back: (s = 30) => svg('<path d="M15 5l-7 7 7 7"/>', s),
  lock: (s = 22) => svg('<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>', s),
};

/** Barra de estado de Android. color '#fff' o '#000'. */
function sb(color = '#fff') {
  return `<div class="sba" style="color:${color}"><span>9:41</span><i class="cam"></i><span class="sg">` +
    `<svg width="26" height="20" viewBox="0 0 26 20" fill="currentColor"><path d="M13 19L1 6a17 17 0 0 1 24 0z"/></svg>` +
    `<svg width="22" height="20" viewBox="0 0 22 20" fill="currentColor"><path d="M21 1v18H1z"/></svg>` +
    `<svg width="34" height="20" viewBox="0 0 34 20" fill="none"><rect x="1" y="3" width="28" height="14" rx="3.5" stroke="currentColor" stroke-width="2" opacity=".5"/><rect x="3.5" y="5.5" width="20" height="9" rx="2" fill="currentColor"/></svg></span></div>`;
}
const navPill = (color = '#fff') => `<i class="navp" style="background:${color}"></i>`;

/** Icono redondeado de la pantalla de inicio. */
function app(label, bg, glyph, extra = '', id = '') {
  return `<div ${id ? `id="${id}" ` : ''}class="aa ${extra}"><div class="ac" style="background:${bg}">${glyph || ''}</div><span>${label}</span></div>`;
}
const G = {
  phone: svg('<path d="M6.5 3.5l3 .8 1 4-2 1.2a11 11 0 0 0 5 5l1.2-2 4 1 .8 3a2 2 0 0 1-2 2.5A15 15 0 0 1 4 5.5a2 2 0 0 1 2.5-2z" fill="#fff" stroke="none"/>', 54),
  msg: svg('<path d="M4 5h16v11H9l-5 4z" fill="#fff" stroke="none"/>', 54),
  cam: svg('<circle cx="12" cy="12" r="6" stroke="#fff" stroke-width="2.4"/><circle cx="12" cy="12" r="2.4" fill="#fff" stroke="none"/>', 54),
  chrome: `<svg viewBox="0 0 64 64" width="84" height="84"><circle cx="32" cy="32" r="30" fill="#fff"/><path d="M32 32L7.8 18A28 28 0 0 1 56.2 18z" fill="#ea4335"/><path d="M32 32L56.2 18A28 28 0 0 1 32 60z" fill="#fbbc05"/><path d="M32 32L32 60A28 28 0 0 1 7.8 18z" fill="#34a853"/><circle cx="32" cy="32" r="12" fill="#fff"/><circle cx="32" cy="32" r="9.5" fill="#4285f4"/></svg>`,
  photos: svg('<circle cx="12" cy="12" r="8" stroke="#fff" stroke-width="2.6"/>', 54),
  maps: svg('<path d="M12 21s7-6.3 7-11a7 7 0 0 0-14 0c0 4.7 7 11 7 11z" fill="#fff" stroke="none"/><circle cx="12" cy="10" r="2.6" fill="#34a853" stroke="none"/>', 54),
  mail: svg('<rect x="3" y="5" width="18" height="14" rx="2" stroke="#fff"/><path d="M4 7l8 6 8-6" stroke="#fff"/>', 54),
  clock: svg('<circle cx="12" cy="12" r="9" stroke="#fff"/><path d="M12 7v5l3.5 2" stroke="#fff"/>', 54),
  gear: svg('<circle cx="12" cy="12" r="3.2" stroke="#fff"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" stroke="#fff"/>', 54),
  cal: svg('<rect x="4" y="5" width="16" height="15" rx="2" stroke="#fff"/><path d="M4 10h16M8 3v4M16 3v4" stroke="#fff"/>', 54),
  jp: '<img src="assets/jp-icon.png" alt="" />',
};

function home(id, withJP) {
  const jp = withJP ? app('JP Prep', '#0f172a', G.jp, 'jpapp', 'p-jp') : '';
  const chromeIcon = withJP ? app('', '#fff', G.chrome, 'nolabel') : app('', '#fff', G.chrome, 'nolabel', 'p-chrome');
  return `<div id="${id}" class="layer and-home">${sb('#fff')}
    <div class="gsearch"><b>G</b><span>Buscar</span></div>
    <div class="agrid">${[
      ['Fotos', '#ea4335', G.photos], ['Mapas', '#34a853', G.maps], ['Gmail', '#d93025', G.mail], ['Reloj', '#5f6368', G.clock],
      ['Calendario', '#1a73e8', G.cal], ['Ajustes', '#5f6368', G.gear],
    ].map((a) => app(...a)).join('')}${jp}</div>
    <div class="adock">${app('', '#34a853', G.phone, 'nolabel')}${app('', '#1a73e8', G.msg, 'nolabel')}${chromeIcon}${app('', '#5f6368', G.cam, 'nolabel')}</div>
    ${navPill('#fff')}</div>`;
}

function keyboard() {
  const row = (s, pad = 0) => `<div class="kr" style="padding:0 ${pad}px">${[...s].map((c) => `<b>${c}</b>`).join('')}</div>`;
  return `<div class="kba">${row('qwertyuiop')}${row('asdfghjklñ', 6)}${row('zxcvbnm', 54)}<div class="kr"><b class="w">?123</b><b class="w">,</b><b class="sp">Español</b><b class="w">.</b><b id="p-enter" class="en">${A.enter(32)}</b></div></div>`;
}

module.exports = { A, sb, navPill, app, G, home, keyboard, I };
