const { svg } = require('./ui-ios.cjs');
const P = require('./probe-lib.cjs').make('windows');

const ic = {
  back: svg('<path d="M19 12H5M11 6l-6 6 6 6"/>', 20), fwd: svg('<path d="M5 12h14M13 6l6 6-6 6"/>', 20),
  reload: svg('<path d="M20 12a8 8 0 1 1-2.4-5.7M20 4v5h-5"/>', 20),
  lock: svg('<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>', 16),
  glass: svg('<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>', 17),
  star: svg('<path d="M12 3l2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8 6.6 19.7l1.1-6.1L3.2 9.4l6.1-.8z"/>', 18),
  install: svg('<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4M12 6.5v5.5M9.5 9.5L12 12l2.5-2.5"/>', 22),
  dots: svg('<circle cx="12" cy="5" r="1.7" fill="currentColor"/><circle cx="12" cy="12" r="1.7" fill="currentColor"/><circle cx="12" cy="19" r="1.7" fill="currentColor"/>', 20),
  min: svg('<path d="M5 12h14"/>', 16), max: svg('<rect x="6" y="6" width="12" height="12"/>', 14), close: svg('<path d="M6 6l12 12M18 6L6 18"/>', 16),
  bell: svg('<path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2h-15z"/><path d="M10 21h4"/>', 20),
  folder: svg('<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="#fcc419" stroke="none"/>', 28),
  sub: svg('<path d="M9 5l7 7-7 7"/>', 14),
};

const chromeIcon = (s = 30) => `<svg viewBox="0 0 64 64" width="${s}" height="${s}"><circle cx="32" cy="32" r="30" fill="#fff"/><path d="M32 32L7.8 18A28 28 0 0 1 56.2 18z" fill="#ea4335"/><path d="M32 32L56.2 18A28 28 0 0 1 32 60z" fill="#fbbc05"/><path d="M32 32L32 60A28 28 0 0 1 7.8 18z" fill="#34a853"/><circle cx="32" cy="32" r="12" fill="#fff"/><circle cx="32" cy="32" r="9.5" fill="#4285f4"/></svg>`;
const edgeIcon = (s = 30) => `<svg viewBox="0 0 64 64" width="${s}" height="${s}"><defs><linearGradient id="eg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#35c1f1"/><stop offset="1" stop-color="#0f7dc9"/></linearGradient></defs><circle cx="32" cy="32" r="29" fill="url(#eg)"/><path d="M14 34c0-9 7-16 16-16 8 0 14 5 14 12 0 5-4 7-8 7H24c0 5 4 8 10 8 4 0 8-1 11-4-2 8-9 13-17 13-10 0-18-8-18-20z" fill="#0b4f8a" opacity=".55"/><path d="M20 30c1-7 7-12 14-12 7 0 12 4 12 10H20z" fill="#3ddc97"/></svg>`;
const startIcon = `<svg width="26" height="26" viewBox="0 0 24 24"><path fill="#4aa3ff" d="M2 4l9-1.2v8.4H2zM12 2.6L22 1v10.2H12zM2 12.8h9v8.4L2 20zM12 12.8h10V23l-10-1.4z"/></svg>`;

const omni = (inner, extra = '') => `<div class="womni ${extra}">${inner}</div>`;

/** Ventana de Chrome (sin la barra de tareas, que va en su propia capa). */
function chrome(id, { tab, omnibox, content, menuId = 'p-wmenu' }) {
  return `<div id="${id}" class="layer wwin">
    <div class="wtabs"><div class="wtab"><img src="assets/jp-icon.png" class="fav" alt="" /><span>${tab}</span><i>${ic.close}</i></div><b class="wplus">+</b>
      <div class="wctl"><i>${ic.min}</i><i>${ic.max}</i><i>${ic.close}</i></div></div>
    <div class="wbar"><i>${ic.back}</i><i class="dis">${ic.fwd}</i><i>${ic.reload}</i>${omnibox}<i class="wprof">M</i><i id="${menuId}" class="wdots">${ic.dots}</i></div>
    <div class="wcontent">${content}</div></div>`;
}

const urlOmni = (withInstall) => omni(`<span class="lk">${ic.lock}</span><span class="u">app.jppreparation.com</span>${withInstall ? `<i id="p-winstall" class="oi inst">${ic.install}</i>` : ''}<i class="oi">${ic.star}</i>`);

/** Ventana de la app instalada (barra de título oscura del color de la app). */
const appWin = (id, img) => `<div id="${id}" class="layer wwin"><div class="wapptitle"><img src="assets/jp-icon.png" alt="" /><span>JP Preparation</span><div class="wctl l"><i>${ic.min}</i><i>${ic.max}</i><i>${ic.close}</i></div></div>
  <div class="wapp"><img src="assets/${img}" alt="" /></div></div>`;

const menuItems = [
  ['Nueva pestaña', 'Ctrl+T'], ['Nueva ventana', 'Ctrl+N'], ['Nueva ventana de incógnito', 'Ctrl+Mayús+N'], '-',
  ['Historial', '▸'], ['Descargas', 'Ctrl+J'], ['Marcadores y listas', '▸'], '-',
  ['Zoom', '− 100 % +'], '-', ['Imprimir…', 'Ctrl+P'], ['Guardar y compartir', 'sub', 'p-wsave'], ['Más herramientas', '▸'], '-', ['Configuración', ''],
];

// Desde las capturas reales de la app (1440×800 → pantalla 1152×720; ventana de navegador: contenido a y=84; de app: a y=36)
const IMG = { cfg: { x: 9.6, y: 301.2, w: 172.8, h: 33.6, cx: 96, cy: 318 }, enable: { x: 733.6, y: 240.8, w: 150.4, h: 24.8, cx: 808.8, cy: 253.2 }, card: { x: 450.4, y: 308.8, w: 251.2, h: 182 }, entrar: { cx: 576, cy: 470, w: 251.2, h: 41.6 } };
const AW = 36;

const layers = [
  { id: 'L-wdesk', show: [[4.6, 85.8]], fade: 0.01, html: `<div id="L-wdesk" class="layer wdesk"><div class="bin">${svg('<path d="M5 7h14l-1.2 13H6.2zM9 7V4h6v3M3 7h18"/>', 30)}<span>Papelera de reciclaje</span></div></div>` },

  { id: 'L-wnew', show: [[10.8, 19.6]], html: chrome('L-wnew', {
      tab: 'Nueva pestaña', menuId: 'x-wmenu',
      omnibox: omni(`<span class="lk">${ic.glass}</span><span id="wtyped" class="u typed">app.jppreparation.com</span>`, 'foc'),
      content: `<div class="ntp"><div class="ntp-s">${ic.glass} Buscar o escribir una dirección web</div></div><div class="wdrop"><div class="dr">${ic.glass}<span><b>app.jppreparation.com</b></span><em>Abrir esta dirección</em></div></div>` }) },

  { id: 'L-wlogin', show: [[19.2, 32.8], [75.8, 84.4]], html: chrome('L-wlogin', { tab: 'JP Preparation', omnibox: urlOmni(true), content: '<img src="assets/d-login.png" alt="" />' }) },
  { id: 'L-wdlg', show: [[26.0, 32.8]], html: `<div id="L-wdlg" class="layer"><div class="wdlg"><div class="dt">Instalar app</div>
      <div class="dr1"><img src="assets/jp-icon.png" alt="" /><div><b>JP Preparation</b><small>app.jppreparation.com</small></div></div>
      <div class="db"><span id="p-winst" class="ok">Instalar</span><span class="cn">Cancelar</span></div></div></div>` },

  { id: 'L-wmenu', show: [[77.2, 84.4]], html: `<div id="L-wmenu" class="layer"><div class="wmenu">${menuItems.map((m) => m === '-' ? '<hr />' : `<div ${m[2] ? `id="${m[2]}" ` : ''}class="mi"><span>${m[0]}</span><em>${m[1] === 'sub' ? ic.sub : m[1]}</em></div>`).join('')}</div>
      <div id="wfly" class="wmenu fly" style="top:${Math.max(60, P.b('p-wsave').y - 8)}px"><div class="mi"><span>Guardar página como…</span><em>Ctrl+S</em></div><div class="mi"><span>Copiar enlace</span><em></em></div><div class="mi"><span>Crear acceso directo…</span><em></em></div><div id="p-wsubinst" class="mi"><span>Instalar JP Preparation…</span><em></em></div></div></div>` },

  { id: 'L-wapp', show: [[32.4, 46.9]], html: appWin('L-wapp', 'd-login.png') },
  { id: 'L-wapp2', show: [[43.0, 51.8]], html: appWin('L-wapp2', 'd-login-lleno.png') },
  { id: 'L-wdash', show: [[50.6, 57.2]], html: appWin('L-wdash', 'd-dash.png') },
  { id: 'L-wnotif', show: [[56.8, 70.2]], html: appWin('L-wnotif', 'd-notif-off.png') },
  { id: 'L-wperm', show: [[63.8, 70.2]], html: `<div id="L-wperm" class="layer"><div class="wperm"><div class="ph">app.jppreparation.com quiere<i>${ic.close}</i></div>
      <div class="pr">${ic.bell}<span>Mostrar notificaciones</span></div><div class="pbt"><span id="p-wallow" class="a">Permitir</span><span class="b">Bloquear</span></div></div></div>` },
  { id: 'L-wnotif2', show: [[69.9, 76.2]], html: appWin('L-wnotif2', 'd-notif-on.png') },

  // la barra de tareas va la última (por encima de las ventanas)
  { id: 'L-wtask', show: [[4.6, 85.8]], fade: 0.01, html: `<div id="L-wtask" class="layer"><div class="wtask"><div class="tc">
      <span class="tbi">${startIcon}</span><span class="tbi"><span class="sp">${ic.glass} Buscar</span></span>
      <span id="p-wedge" class="tbi">${edgeIcon(28)}</span><span id="p-wchrome" class="tbi act">${chromeIcon(28)}</span><span class="tbi">${ic.folder}</span>
      <span id="p-wjp" class="tbi jpi"><img src="assets/jp-icon.png" alt="" /></span></div>
      <div class="tray">ESP&nbsp; ${svg('<path d="M3 9a14 14 0 0 1 18 0M6 12.5a9 9 0 0 1 12 0M9 16a4.5 4.5 0 0 1 6 0"/>', 18)}&nbsp; 9:41<br />17/10/2026</div></div></div>` },
];

module.exports = {
  kind: 'desktop', W: 1920, H: 1080, D: 91.2,
  introT: [0, 4.6], outroT: [84.2, 91.2],
  intro: { title: 'Instala la app<br />en tu ordenador <b>Windows</b>', sub: 'Te llevará unos 2 minutos.<br />Sigue los pasos uno a uno, sin prisa' },
  outro: { title: '¡Ya está!', sub: 'Abre siempre la app desde el <b>icono de JP Prep</b><br />de la barra de tareas o del menú Inicio' },
  assets: { 'jp-icon.png': 'jp-icon.png', 'd-login.png': 'd-login.png', 'd-login-lleno.png': 'd-login-lleno.png', 'd-dash.png': 'd-dash.png', 'd-notif-off.png': 'd-notif-off.png', 'd-notif-on.png': 'd-notif-on.png' },
  steps: [
    { a: 4.6, b: 11.2, t: 'Abre <b>Chrome</b>', s: 'El icono redondo de colores de la barra de abajo. También sirve Microsoft Edge' },
    { a: 11.2, b: 19.4, t: 'Escribe esta dirección<br />y pulsa <b>Enter</b>', s: '<b style="color:#fff">app.jppreparation.com</b><br />en la barra de arriba' },
    { a: 19.4, b: 26.4, t: 'Pulsa el icono<br /><b>de instalar</b>', s: 'Un monitor con una flecha, a la derecha de la barra de arriba' },
    { a: 26.4, b: 32.6, t: 'Pulsa <b>«Instalar»</b>', s: 'El botón azul de la ventanita' },
    { a: 32.6, b: 39.4, t: 'La app se abre<br />en su propia ventana', s: 'Y su icono queda en la barra de abajo' },
    { a: 39.4, b: 46.5, t: 'Escribe tu <b>email</b><br />y tu <b>contraseña</b>', s: 'Los de siempre' },
    { a: 46.5, b: 51.0, t: 'Pulsa <b>«Entrar»</b>' },
    { a: 51.0, b: 57.0, t: 'Pulsa <b>«Configuración»</b>', s: 'En el menú de la izquierda' },
    { a: 57.0, b: 63.6, t: 'Pulsa <b>«Activar<br />notificaciones»</b>', s: 'Así te avisaremos de clases y mensajes' },
    { a: 63.6, b: 70.0, t: 'Pulsa <b>«Permitir»</b>', s: 'Si pulsas «Bloquear», no te llegarán los avisos' },
    { a: 70.0, b: 76.0, num: false, label: 'Hecho', t: '¡Notificaciones<br />activadas!', s: 'Ya recibirás los avisos de clases, mensajes y bonos' },
    { a: 76.0, b: 84.0, num: false, label: 'Truco', t: '¿No ves el icono<br />de instalar?', s: 'Pulsa <b style="color:#fff">⋮</b> → «Guardar y compartir» →<br />«Instalar JP Preparation»' },
  ],
  layers,
  marks: [
    { ...P.b('p-wchrome', 6), r: 14, a: 5.8, b: 9.2 },
    { ...P.b('p-winstall', 6), r: 14, a: 20.4, b: 25.4 },
    { ...P.b('p-winst', 8), r: 12, a: 27.4, b: 31.2 },
    { ...P.b('p-wjp', 4), r: 12, a: 34.2, b: 38.2 },
    { x: IMG.card.x - 14, y: IMG.card.y + AW - 14, w: IMG.card.w + 28, h: 112 + 14, r: 22, a: 40.4, b: 45.6 },
    { x: IMG.entrar.cx - IMG.entrar.w / 2 - 8, y: IMG.entrar.cy + AW - IMG.entrar.h / 2 - 8, w: IMG.entrar.w + 16, h: IMG.entrar.h + 16, r: 16, a: 47.0, b: 50.0 },
    { x: IMG.cfg.x - 6, y: IMG.cfg.y + AW - 6, w: IMG.cfg.w + 12, h: IMG.cfg.h + 12, r: 14, a: 51.8, b: 55.8 },
    { x: IMG.enable.x - 8, y: IMG.enable.y + AW - 8, w: IMG.enable.w + 16, h: IMG.enable.h + 16, r: 12, a: 58.0, b: 62.0 },
    { ...P.b('p-wallow', 6), r: 12, a: 64.8, b: 68.4 },
  ],
  pills: [{ x: 360, y: 124, t: 'Enter ↵', a: 16.4, b: 19.0 }],
  cursorStart: { x: 880, y: 560 },
  taps: [
    { ...P.c('p-wchrome'), t: 9.4 },
    { ...P.c('p-winstall'), t: 25.6 },
    { ...P.c('p-winst'), t: 31.4 },
    { x: IMG.entrar.cx, y: IMG.entrar.cy + AW, t: 50.3 },
    { x: IMG.cfg.cx, y: IMG.cfg.cy + AW, t: 56.0 },
    { x: IMG.enable.cx, y: IMG.enable.cy + AW, t: 62.4 },
    { ...P.c('p-wallow'), t: 69.0 },
    { ...P.c('p-wmenu'), t: 77.6, move: 1.6 },
    { ...P.c('p-wsave'), t: 80.0 },
    { ...P.c('p-wsubinst'), t: 82.8 },
  ],
  zooms: [
    { fx: 560, fy: 700, s: 1.8, a: 5.0, b: 10.6 },
    { fx: 420, fy: 70, s: 1.9, a: 11.6, b: 19.0 },
    { fx: 960, fy: 64, s: 2.2, a: 19.8, b: 26.1 },
    { fx: 860, fy: 130, s: 1.9, a: 26.1, b: 32.2 },
    { fx: 560, fy: 700, s: 1.8, a: 33.8, b: 38.6 },
    { fx: 576, fy: 400, s: 1.5, a: 39.8, b: 50.8 },
    { fx: 200, fy: 360, s: 1.7, a: 51.4, b: 56.6 },
    { fx: 800, fy: 300, s: 1.7, a: 57.4, b: 63.4 },
    { fx: 230, fy: 130, s: 1.8, a: 64.0, b: 69.8 },
    { fx: 800, fy: 140, s: 1.6, a: 76.6, b: 79.0 },
    { fx: 760, fy: 330, s: 1.6, a: 79.0, b: 83.6 },
  ],
  tl: `
      gsap.set("#wtyped", { clipPath: "inset(0 100% 0 0)" });
      tl.to("#wtyped", { clipPath: "inset(0 0% 0 0)", duration: 3.0, ease: "steps(21)" }, 12.4);
      gsap.set("#wfly", { opacity: 0 });
      tl.to("#wfly", { opacity: 1, duration: 0.25 }, 79.6);
      gsap.set("#p-wjp", { opacity: 0, scale: 0.3 });
      tl.to("#p-wjp", { opacity: 1, scale: 1, duration: 0.6, ease: "back.out(2.2)" }, 33.0);
  `,
  css: `
      .wdesk { background: radial-gradient(700px 420px at 28% 38%, #5db3ff 0%, #2b64d8 38%, #10256b 75%, #0a1236 100%); }
      .bin { position: absolute; left: 18px; top: 14px; width: 80px; text-align: center; color: #fff; font-size: 12px; display: flex; flex-direction: column; align-items: center; gap: 4px; }
      .wwin { background: #fff; height: 672px; inset: 0 0 auto 0; }
      .wtabs { position: absolute; left: 0; right: 0; top: 0; height: 40px; background: #dee1e6; }
      .wtab { position: absolute; left: 10px; top: 7px; width: 230px; height: 33px; background: #fff; border-radius: 10px 10px 0 0; display: flex; align-items: center; gap: 10px; padding: 0 12px; font-size: 13px; color: #202124; }
      .wtab .fav { width: 18px; height: 18px; border-radius: 4px; } .wtab i { margin-left: auto; color: #5f6368; display: flex; }
      .wplus { position: absolute; left: 252px; top: 8px; font-size: 22px; color: #5f6368; font-weight: 400; }
      .wctl { position: absolute; right: 0; top: 0; height: 36px; display: flex; color: #202124; }
      .wctl i { width: 46px; height: 36px; display: flex; align-items: center; justify-content: center; }
      .wctl.l { color: #fff; }
      .wbar { position: absolute; left: 0; right: 0; top: 40px; height: 44px; background: #fff; display: flex; align-items: center; gap: 6px; padding: 0 12px; color: #5f6368; border-bottom: 1px solid #dadce0; }
      .wbar > i { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; } .wbar i.dis { opacity: .35; }
      .womni { flex: 1; height: 34px; border-radius: 17px; background: #eef0f3; display: flex; align-items: center; gap: 10px; padding: 0 14px; font-size: 14px; color: #202124; margin: 0 8px; }
      .womni.foc { background: #fff; box-shadow: 0 0 0 2px #1a73e8; }
      .womni .lk { display: flex; color: #5f6368; } .womni .u { flex: 1; }
      .womni .oi { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; color: #5f6368; }
      .womni .oi.inst { color: #1a73e8; }
      .wprof { width: 28px !important; height: 28px !important; border-radius: 50%; background: #7b61ff; color: #fff !important; font-weight: 700; font-size: 13px; font-style: normal; }
      .wcontent { position: absolute; left: 0; right: 0; top: 84px; bottom: 0; overflow: hidden; background: #fff; }
      .wcontent > img { width: 100%; display: block; }
      .ntp { position: absolute; inset: 0; background: #fff; display: flex; align-items: center; justify-content: center; }
      .ntp-s { width: 560px; height: 46px; border-radius: 23px; border: 1px solid #dfe1e5; display: flex; align-items: center; gap: 12px; padding: 0 18px; color: #5f6368; font-size: 15px; }
      .wdrop { position: absolute; left: 150px; top: 0; width: 868px; background: #fff; border-radius: 0 0 18px 18px; box-shadow: 0 8px 22px rgba(0,0,0,.25); padding: 8px 0; }
      .wdrop .dr { display: flex; align-items: center; gap: 14px; padding: 8px 18px; font-size: 14px; color: #202124; } .wdrop .dr svg { color: #5f6368; } .wdrop .dr em { margin-left: auto; color: #5f6368; font-style: normal; font-size: 12px; }

      .wapptitle { position: absolute; left: 0; right: 0; top: 0; height: 36px; background: #0f172a; display: flex; align-items: center; gap: 10px; padding: 0 12px; color: #fff; font-size: 13px; }
      .wapptitle img { width: 18px; height: 18px; border-radius: 4px; }
      .wapp { position: absolute; left: 0; right: 0; top: 36px; bottom: 0; overflow: hidden; background: #f1f5f9; } .wapp img { width: 100%; display: block; }

      .wtask { position: absolute; left: 0; right: 0; bottom: 0; height: 48px; background: rgba(28,34,52,.96); border-top: 1px solid rgba(255,255,255,.12); }
      .wtask .tc { position: absolute; left: 0; right: 0; top: 0; height: 48px; display: flex; justify-content: center; align-items: center; gap: 6px; }
      .tbi { min-width: 44px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; position: relative; }
      .tbi.act::after, .tbi.jpi::after { content: ""; position: absolute; bottom: 2px; left: 50%; margin-left: -8px; width: 16px; height: 3px; border-radius: 2px; background: #60cdff; }
      .tbi.jpi img { width: 26px; height: 26px; border-radius: 6px; }
      .tbi .sp { background: rgba(255,255,255,.12); border-radius: 18px; height: 30px; padding: 0 14px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #ddd; width: 150px; }
      .tray { position: absolute; right: 14px; top: 4px; color: #fff; font-size: 11px; text-align: right; line-height: 1.35; }

      .wdlg { position: absolute; left: 680px; top: 92px; width: 360px; background: #fff; border-radius: 12px; box-shadow: 0 10px 34px rgba(0,0,0,.4); padding: 20px 22px 18px; color: #202124; }
      .wdlg .dt { font-size: 17px; font-weight: 600; margin-bottom: 14px; }
      .wdlg .dr1 { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; } .wdlg .dr1 img { width: 48px; height: 48px; border-radius: 10px; }
      .wdlg .dr1 b { display: block; font-size: 15px; } .wdlg .dr1 small { color: #5f6368; font-size: 13px; }
      .wdlg .db { display: flex; justify-content: flex-end; gap: 10px; font-size: 14px; font-weight: 500; }
      .wdlg .ok { background: #1a73e8; color: #fff; padding: 9px 22px; border-radius: 20px; } .wdlg .cn { color: #1a73e8; padding: 9px 14px; }

      .wmenu { position: absolute; left: 818px; top: 74px; width: 322px; background: #fff; border-radius: 10px; box-shadow: 0 6px 26px rgba(0,0,0,.35); padding: 6px 0; font-size: 13px; color: #202124; }
      .wmenu.fly { left: 478px; width: 330px; }
      .wmenu .mi { height: 32px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; } .wmenu .mi em { color: #5f6368; font-style: normal; font-size: 12px; display: flex; }
      .wmenu hr { margin: 5px 0; border: 0; border-top: 1px solid #e3e3e6; }

      .wperm { position: absolute; left: 12px; top: 44px; width: 380px; background: #fff; border-radius: 12px; box-shadow: 0 8px 28px rgba(0,0,0,.4); padding: 16px 18px; color: #202124; }
      .wperm .ph { display: flex; justify-content: space-between; font-size: 14px; color: #3c4043; margin-bottom: 14px; } .wperm .ph i { color: #5f6368; display: flex; }
      .wperm .pr { display: flex; align-items: center; gap: 12px; font-size: 14px; margin-bottom: 16px; color: #202124; }
      .wperm .pbt { display: flex; gap: 10px; font-size: 14px; font-weight: 500; }
      .wperm .pbt span { padding: 8px 22px; border-radius: 20px; border: 1px solid #dadce0; color: #1a73e8; } .wperm .pbt .a { background: #1a73e8; border-color: #1a73e8; color: #fff; }
  `,
};
