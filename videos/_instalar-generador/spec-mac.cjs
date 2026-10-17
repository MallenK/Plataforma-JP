const { svg } = require('./ui-ios.cjs');
const P = require('./probe-lib.cjs').make('mac');

const ic = {
  lock: svg('<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>', 13),
  glass: svg('<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>', 14),
  back: svg('<path d="M15 5l-7 7 7 7"/>', 18), fwd: svg('<path d="M9 5l7 7-7 7"/>', 18),
  side: svg('<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M9 5v14"/>', 20),
  share: svg('<path d="M12 15V3M8 7l4-4 4 4"/><path d="M7 10H6a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1h-1"/>', 18),
  plus: svg('<path d="M12 5v14M5 12h14"/>', 18), tabs: svg('<rect x="4" y="4" width="12" height="12" rx="2"/><path d="M8 20h10a2 2 0 0 0 2-2V8"/>', 18),
  wifi: svg('<path d="M3 9a14 14 0 0 1 18 0M6 12.5a9 9 0 0 1 12 0M9 16a4.5 4.5 0 0 1 6 0"/>', 15),
  bat: svg('<rect x="2" y="7" width="18" height="10" rx="3"/><rect x="4" y="9" width="11" height="6" rx="1.5" fill="currentColor"/><path d="M22 10v4"/>', 18),
  apple: `<svg width="14" height="16" viewBox="0 0 24 28"><path fill="#fff" d="M19.4 14.9c0-3.400 2.800-5 2.900-5.100-1.600-2.300-4-2.600-4.900-2.700-2.100-.2-4.100 1.200-5.100 1.200-1.100 0-2.700-1.200-4.400-1.200-2.300 0-4.400 1.300-5.600 3.400-2.400 4.100-.6 10.200 1.700 13.500 1.100 1.600 2.500 3.500 4.300 3.400 1.700-.1 2.400-1.100 4.500-1.100 2.100 0 2.700 1.100 4.500 1 1.900 0 3-1.700 4.100-3.300 1.300-1.900 1.800-3.700 1.900-3.800-.1 0-3.800-1.400-3.900-5.300zM16.100 4.800C17 3.700 17.600 2.200 17.400.7c-1.300.1-2.900.9-3.800 2-.8 1-1.600 2.500-1.400 4 1.400.1 2.900-.7 3.900-1.900z"/></svg>`,
};

const compass = '<svg viewBox="0 0 64 64"><defs><linearGradient id="mg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4fc3ff"/><stop offset="1" stop-color="#1a6dff"/></linearGradient></defs><rect width="64" height="64" rx="15" fill="url(#mg)"/><circle cx="32" cy="32" r="22" fill="none" stroke="#fff" stroke-width="2.5"/><path d="M42 22L35 35 22 42l7-13z" fill="#ff453a"/><path d="M42 22L35 35 29 29z" fill="#fff"/></svg>';
const dk = (bg, inner = '', extra = '', id = '') => `<div ${id ? `id="${id}" ` : ''}class="di ${extra}" style="background:${bg}">${inner}</div>`;

const menus = ['Safari', 'Archivo', 'Edición', 'Visualización', 'Historial', 'Marcadores', 'Ventana', 'Ayuda'];
const menubar = `<div class="mbar"><span class="ap">${ic.apple}</span>${menus.map((m, i) => `<span ${i === 1 ? 'id="p-marchivo" ' : ''}class="mm${i === 0 ? ' b' : ''}">${m}</span>`).join('')}
  <div class="mr">${ic.wifi}${ic.bat}<span>sáb 17 oct&nbsp; 9:41</span></div></div>`;

const dock = (withJP) => `<div class="dock"><div class="dk">
  ${dk('linear-gradient(135deg,#6ec6ff,#1a73e8)', '<b>☻</b>')}${dk('#e5e5ea', svg('<rect x="4" y="4" width="6" height="6" rx="1.5" fill="#666"/><rect x="14" y="4" width="6" height="6" rx="1.5" fill="#666"/><rect x="4" y="14" width="6" height="6" rx="1.5" fill="#666"/><rect x="14" y="14" width="6" height="6" rx="1.5" fill="#666"/>', 34))}
  ${dk('transparent', compass, 'cmp', 'p-msafari')}${dk('linear-gradient(#4aa3ff,#1a6ee8)', svg('<rect x="3" y="6" width="18" height="12" rx="2" stroke="#fff"/><path d="M4 8l8 6 8-6" stroke="#fff"/>', 34))}
  ${dk('#fff', '<b class="cal">17</b>')}${dk('#ffd60a', svg('<path d="M6 4h12v16H6z" fill="#fff" stroke="none"/><path d="M9 9h6M9 13h6" stroke="#f5a623"/>', 32))}${dk('#8e8e93', svg('<circle cx="12" cy="12" r="3.2" stroke="#fff"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" stroke="#fff"/>', 34))}
  ${withJP ? dk('#0f172a', '<img src="assets/jp-icon.png" alt="" />', 'jpd', 'p-mjp') : ''}</div></div>`;

const traffic = '<i class="tl r"></i><i class="tl y"></i><i class="tl g"></i>';

/** Ventana de Safari: left 40, top 40, 1072 × 590 (barra de herramientas 52). */
function safari(id, { pill, content }) {
  return `<div id="${id}" class="layer"><div class="sw">
    <div class="stb">${traffic}<i class="sb1">${ic.side}</i><i class="sb1">${ic.back}</i><i class="sb1 dis">${ic.fwd}</i>${pill}<i class="sb1 r1">${ic.share}</i><i class="sb1">${ic.plus}</i><i class="sb1">${ic.tabs}</i></div>
    <div class="scn">${content}</div></div></div>`;
}
const urlPill = (extra = '') => `<div class="spill ${extra}"><span class="lk">${ic.lock}</span><span class="u">app.jppreparation.com</span></div>`;
const appWin = (id, img) => `<div id="${id}" class="layer"><div class="aw"><div class="atb">${traffic}<span>JP Preparation</span></div><div class="acn"><img src="assets/${img}" alt="" /></div></div></div>`;

const fileRows = [
  ['Nueva ventana', '⌘N'], ['Nueva ventana privada', '⇧⌘N'], ['Nueva pestaña', '⌘T'], '-',
  ['Abrir archivo…', '⌘O'], ['Abrir ubicación…', '⌘L'], '-', ['Cerrar ventana', '⇧⌘W'], ['Cerrar pestañas', '⌘W'], '-',
  ['Guardar como…', '⌘S'], ['Compartir', '▸'], ['Añadir al Dock…', '', 'p-maddock'], ['Imprimir…', '⌘P'],
];

// Posiciones sobre las capturas de la app (css 1440×800): ventana de la app en left 60, top 48+30, ancho 1032
const K = 1032 / 1440, AX = 60, AY = 78;
const T = (x, y) => ({ x: +(AX + x * K).toFixed(1), y: +(AY + y * K).toFixed(1) });
const S = (w) => +(w * K).toFixed(1);
const CFG = { ...T(12, 376.5), w: S(216), h: S(42), c: T(120, 397.5) };
const ENA = { ...T(917, 301), w: S(188), h: S(31), c: T(1011, 316.5) };
const CARD = { ...T(563, 386), w: S(314), h: S(118) };
const ENT = { c: T(720, 587.5), w: S(314), h: S(52) };

const layers = [
  { id: 'L-mdesk', show: [[4.6, 88.4]], fade: 0.01, html: `<div id="L-mdesk" class="layer mdesk"></div>` },

  { id: 'L-mnew', show: [[10.8, 19.2]], html: safari('L-mnew', {
      pill: urlPill('foc').replace('<span class="u">app.jppreparation.com</span>', '<span id="mtyped" class="u typed">app.jppreparation.com</span>'),
      content: `<div class="fav"><h4>Favoritos</h4><div class="tl4"><i style="background:#0a84ff">A</i><i style="background:#ff9f0a">B</i><i style="background:#30d158">C</i><i style="background:#bf5af2">D</i></div></div>` }) },
  { id: 'L-msaf', show: [[19.0, 38.8]], html: safari('L-msaf', { pill: urlPill(), content: '<img src="assets/d-login.png" alt="" />' }) },
  { id: 'L-mfile', show: [[24.4, 32.2]], html: `<div id="L-mfile" class="layer"><div class="fmenu" style="left:${Math.max(4, P.b('p-marchivo').x - 6)}px">${fileRows.map((r) => r === '-' ? '<hr />' : `<div ${r[2] ? `id="${r[2]}" ` : ''}class="fr"><span>${r[0]}</span><em>${r[1]}</em></div>`).join('')}</div></div>` },
  { id: 'L-mdlg', show: [[31.8, 38.8]], html: `<div id="L-mdlg" class="layer"><div class="mdlg"><div class="mt">Añadir al Dock</div>
      <div class="mr1"><img src="assets/jp-icon.png" alt="" /><div class="fl"><label>Nombre</label><div class="in">JP Preparation</div><label>Dirección</label><div class="in g">https://app.jppreparation.com/</div></div></div>
      <div class="mb"><span class="cn">Cancelar</span><span id="p-madd" class="ok">Añadir</span></div></div></div>` },

  { id: 'L-mapp', show: [[44.2, 48.0]], html: appWin('L-mapp', 'd-login.png') },
  { id: 'L-mapp2', show: [[47.7, 56.0]], html: appWin('L-mapp2', 'd-login-lleno.png') },
  { id: 'L-mdash', show: [[55.6, 62.2]], html: appWin('L-mdash', 'd-dash.png') },
  { id: 'L-mnotif', show: [[61.8, 75.4]], html: appWin('L-mnotif', 'd-notif-off.png') },
  { id: 'L-mperm', show: [[68.8, 75.4]], html: `<div id="L-mperm" class="layer"><div class="dim"></div><div class="pdl"><img src="assets/jp-icon.png" alt="" /><div class="pt">¿Quieres permitir que «JP Preparation» te envíe notificaciones?</div><div class="ps">Podrás cambiarlo cuando quieras en Ajustes del Sistema.</div><div class="pb2"><span class="n">No permitir</span><span id="p-mallow" class="a">Permitir</span></div></div></div>` },
  { id: 'L-mnotif2', show: [[74.8, 88.4]], html: appWin('L-mnotif2', 'd-notif-on.png') },

  // Menú y Dock siempre por encima de las ventanas (el menú «Archivo» del Dock/Barra va en su capa)
  { id: 'L-mbars', show: [[4.6, 38.6]], fade: 0.25, html: `<div id="L-mbars" class="layer">${menubar}${dock(false)}</div>` },
  { id: 'L-mbars2', show: [[38.4, 88.4]], fade: 0.25, html: `<div id="L-mbars2" class="layer">${(menubar + dock(true)).replace(/ id="p-(msafari|marchivo)"/g, '')}</div>` },
];

module.exports = {
  kind: 'desktop', W: 1920, H: 1080, D: 95.2,
  introT: [0, 4.6], outroT: [88.2, 95.2],
  intro: { title: 'Instala la app<br />en tu <b>Mac</b>', sub: 'Te llevará unos 2 minutos.<br />Sigue los pasos uno a uno, sin prisa' },
  outro: { title: '¡Ya está!', sub: 'Abre siempre la app desde el <b>icono de JP Prep</b><br />de la barra de abajo (el Dock)' },
  assets: { 'jp-icon.png': 'jp-icon.png', 'd-login.png': 'd-login.png', 'd-login-lleno.png': 'd-login-lleno.png', 'd-dash.png': 'd-dash.png', 'd-notif-off.png': 'd-notif-off.png', 'd-notif-on.png': 'd-notif-on.png' },
  steps: [
    { a: 4.6, b: 11.0, t: 'Abre <b>Safari</b>', s: 'El icono azul con una brújula, en la barra de abajo (el Dock)' },
    { a: 11.0, b: 19.0, t: 'Escribe esta dirección<br />y pulsa <b>Intro</b>', s: '<b style="color:#fff">app.jppreparation.com</b><br />en la barra de arriba' },
    { a: 19.0, b: 25.2, t: 'Pulsa <b>«Archivo»</b>', s: 'En el menú de arriba del todo de la pantalla' },
    { a: 25.2, b: 32.0, t: 'Pulsa <b>«Añadir<br />al Dock…»</b>', s: 'Está casi al final de la lista' },
    { a: 32.0, b: 38.4, t: 'Pulsa <b>«Añadir»</b>', s: 'El botón azul de la ventanita' },
    { a: 38.4, b: 44.4, t: '¡Ya tienes el icono<br />en el Dock!', s: 'Púlsalo para abrir la app' },
    { a: 44.4, b: 51.5, t: 'Escribe tu <b>email</b><br />y tu <b>contraseña</b>', s: 'Los de siempre. Si te los pide otra vez, es normal' },
    { a: 51.5, b: 56.0, t: 'Pulsa <b>«Entrar»</b>' },
    { a: 56.0, b: 62.0, t: 'Pulsa <b>«Configuración»</b>', s: 'En el menú de la izquierda' },
    { a: 62.0, b: 68.5, t: 'Pulsa <b>«Activar<br />notificaciones»</b>', s: 'Así te avisaremos de clases y mensajes' },
    { a: 68.5, b: 75.0, t: 'Pulsa <b>«Permitir»</b>', s: 'Si pulsas «No permitir», no te llegarán los avisos' },
    { a: 75.0, b: 81.0, num: false, label: 'Hecho', t: '¡Notificaciones<br />activadas!', s: 'Ya recibirás los avisos de clases, mensajes y bonos' },
    { a: 81.0, b: 88.0, num: false, label: 'Truco', t: '¿Usas Chrome<br />en tu Mac?', s: 'Mira el vídeo de Windows: es igual, con el icono de instalar de la barra de arriba' },
  ],
  layers,
  marks: [
    { ...P.b('p-msafari', 6), r: 16, a: 5.8, b: 9.0 },
    { ...P.b('p-marchivo', 6), r: 8, a: 20.0, b: 24.0 },
    { ...P.b('p-maddock', 2), r: 6, a: 26.0, b: 30.2 },
    { ...P.b('p-madd', 8), r: 12, a: 33.0, b: 37.0 },
    { ...P.b('p-mjp', 6), r: 14, a: 39.6, b: 43.4 },
    { x: CARD.x - 12, y: CARD.y - 8, w: CARD.w + 24, h: CARD.h + 16, r: 20, a: 45.0, b: 50.5 },
    { x: ENT.c.x - ENT.w / 2 - 8, y: ENT.c.y - ENT.h / 2 - 8, w: ENT.w + 16, h: ENT.h + 16, r: 14, a: 52.0, b: 55.0 },
    { x: CFG.x - 6, y: CFG.y - 6, w: CFG.w + 12, h: CFG.h + 12, r: 12, a: 56.8, b: 60.8 },
    { x: ENA.x - 8, y: ENA.y - 8, w: ENA.w + 16, h: ENA.h + 16, r: 12, a: 63.0, b: 67.0 },
    { ...P.b('p-mallow', 6), r: 12, a: 69.8, b: 73.4 },
  ],
  pills: [{ x: 330, y: 110, t: 'Intro ↵', a: 16.2, b: 18.8 }],
  cursorStart: { x: 880, y: 560 },
  taps: [
    { ...P.c('p-msafari'), t: 9.2 },
    { ...P.c('p-marchivo'), t: 24.2 },
    { ...P.c('p-maddock'), t: 30.4 },
    { ...P.c('p-madd'), t: 37.2 },
    { ...P.c('p-mjp'), t: 43.7 },
    { x: ENT.c.x, y: ENT.c.y, t: 55.3 },
    { x: CFG.c.x, y: CFG.c.y, t: 61.0 },
    { x: ENA.c.x, y: ENA.c.y, t: 67.4 },
    { ...P.c('p-mallow'), t: 74.0 },
  ],
  zooms: [
    { fx: 576, fy: 690, s: 1.8, a: 5.0, b: 10.6 },
    { fx: 440, fy: 66, s: 1.9, a: 11.4, b: 18.8 },
    { fx: 300, fy: 20, s: 2.0, a: 19.4, b: 24.6 },
    { fx: 330, fy: 250, s: 1.7, a: 24.8, b: 31.8 },
    { fx: 576, fy: 300, s: 1.6, a: 32.2, b: 37.8 },
    { fx: 576, fy: 690, s: 1.8, a: 38.8, b: 44.2 },
    { fx: 576, fy: 340, s: 1.5, a: 44.8, b: 55.8 },
    { fx: 200, fy: 380, s: 1.7, a: 56.4, b: 61.8 },
    { fx: 780, fy: 300, s: 1.7, a: 62.4, b: 68.2 },
    { fx: 576, fy: 330, s: 1.7, a: 68.9, b: 74.6 },
  ],
  tl: `
      gsap.set("#mtyped", { clipPath: "inset(0 100% 0 0)" });
      tl.to("#mtyped", { clipPath: "inset(0 0% 0 0)", duration: 3.0, ease: "steps(21)" }, 12.2);
      tl.fromTo("#L-mbars2 .jpd", { y: -60, opacity: 0 }, { y: 0, opacity: 1, duration: 0.7, ease: "bounce.out" }, 38.6);
  `,
  css: `
      .mdesk { background: linear-gradient(160deg, #3a1c71 0%, #d76d77 55%, #ffaf7b 100%); }
      .mbar { position: absolute; left: 0; right: 0; top: 0; height: 28px; background: rgba(20,20,30,.55); display: flex; align-items: center; gap: 4px; padding: 0 14px; color: #fff; font-size: 13px; z-index: 40; }
      .mbar .ap { display: flex; margin-right: 12px; } .mbar .mm { padding: 3px 9px; border-radius: 5px; } .mbar .mm.b { font-weight: 700; }
      .mbar .mr { margin-left: auto; display: flex; gap: 12px; align-items: center; color: #fff; }
      .mbar-cover { position: absolute; left: 0; right: 0; top: 0; height: 0; }
      .dock { position: absolute; left: 0; right: 0; bottom: 8px; display: flex; justify-content: center; z-index: 40; }
      .dk { display: flex; align-items: center; gap: 10px; padding: 8px 14px; border-radius: 22px; background: rgba(255,255,255,.28); border: 1px solid rgba(255,255,255,.35); }
      .di { width: 54px; height: 54px; border-radius: 13px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 26px; overflow: hidden; }
      .di svg { width: 100%; height: 100%; } .di.cmp { background: none !important; } .di img { width: 100%; height: 100%; display: block; }
      .di .cal { color: #e5372b; font-size: 26px; font-weight: 600; }
      .di > svg { width: 34px; height: 34px; } .di.cmp > svg { width: 54px; height: 54px; }
      .sw { position: absolute; left: 40px; top: 40px; width: 1072px; height: 590px; background: #fff; border-radius: 12px; box-shadow: 0 24px 70px rgba(0,0,0,.5), 0 0 0 1px rgba(0,0,0,.25); overflow: hidden; }
      .stb { position: relative; height: 52px; background: #ececee; border-bottom: 1px solid #d4d4d8; display: flex; align-items: center; gap: 14px; padding: 0 16px; color: #555; }
      .tl { width: 13px; height: 13px; border-radius: 50%; display: block; margin-right: -4px; } .tl.r { background: #ff5f57; } .tl.y { background: #febc2e; } .tl.g { background: #28c840; }
      .sb1 { display: flex; } .sb1.dis { opacity: .35; } .sb1.r1 { margin-left: auto; }
      .spill { position: absolute; left: 50%; top: 9px; margin-left: -230px; width: 460px; height: 34px; border-radius: 9px; background: #dedee1; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; color: #222; }
      .spill.foc { background: #fff; box-shadow: 0 0 0 3px #4d9bff; justify-content: flex-start; padding: 0 14px; } .spill .lk { display: flex; color: #666; }
      .scn { position: absolute; left: 0; right: 0; top: 52px; bottom: 0; overflow: hidden; background: #fff; } .scn > img { width: 100%; display: block; }
      .fav { padding: 40px 56px; color: #222; } .fav h4 { font-size: 20px; margin-bottom: 16px; } .tl4 { display: flex; gap: 24px; } .tl4 i { width: 72px; height: 72px; border-radius: 16px; display: block; font-style: normal; color: #fff; font-size: 30px; font-weight: 700; text-align: center; line-height: 72px; }
      .fmenu { position: absolute; left: 124px; top: 28px; width: 300px; background: rgba(245,245,247,.98); border-radius: 10px; box-shadow: 0 10px 34px rgba(0,0,0,.45), 0 0 0 1px rgba(0,0,0,.18); padding: 5px 5px; font-size: 13px; color: #1c1c1e; }
      .fr { height: 25px; display: flex; align-items: center; justify-content: space-between; padding: 0 12px; border-radius: 5px; } .fr em { color: #777; font-style: normal; font-size: 12px; }
      .fmenu hr { border: 0; border-top: 1px solid #d6d6da; margin: 4px 6px; }
      .mdlg { position: absolute; left: 50%; top: 92px; margin-left: -230px; width: 460px; background: #f2f2f4; border-radius: 0 0 14px 14px; box-shadow: 0 14px 44px rgba(0,0,0,.45); padding: 22px 26px 20px; color: #1c1c1e; }
      .mdlg .mt { font-size: 16px; font-weight: 700; text-align: center; margin-bottom: 16px; }
      .mr1 { display: flex; gap: 18px; align-items: flex-start; } .mr1 img { width: 76px; height: 76px; border-radius: 17px; }
      .fl { flex: 1; } .fl label { display: block; font-size: 12px; color: #666; margin: 0 0 3px; } .fl .in { background: #fff; border: 1px solid #cfcfd4; border-radius: 6px; padding: 6px 10px; font-size: 13px; margin-bottom: 10px; } .fl .in.g { color: #777; }
      .mb { display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; font-size: 13px; } .mb span { padding: 6px 18px; border-radius: 7px; background: #fff; border: 1px solid #cfcfd4; } .mb .ok { background: #0a7aff; border-color: #0a7aff; color: #fff; font-weight: 600; }
      .aw { position: absolute; left: 60px; top: 48px; width: 1032px; height: 560px; background: #fff; border-radius: 12px; box-shadow: 0 24px 70px rgba(0,0,0,.5), 0 0 0 1px rgba(0,0,0,.25); overflow: hidden; }
      .atb { position: relative; height: 30px; background: #f1f1f3; border-bottom: 1px solid #d8d8dc; display: flex; align-items: center; padding: 0 14px; gap: 8px; }
      .atb span { position: absolute; left: 0; right: 0; text-align: center; font-size: 13px; color: #444; font-weight: 600; }
      .acn { position: absolute; left: 0; right: 0; top: 30px; bottom: 0; overflow: hidden; } .acn img { width: 100%; display: block; }
      .dim { position: absolute; inset: 0; background: rgba(0,0,0,.28); }
      .pdl { position: absolute; left: 50%; top: 170px; margin-left: -170px; width: 340px; background: rgba(240,240,243,.98); border-radius: 16px; box-shadow: 0 18px 54px rgba(0,0,0,.5); padding: 22px 24px 20px; text-align: center; color: #1c1c1e; }
      .pdl img { width: 64px; height: 64px; border-radius: 14px; } .pdl .pt { font-size: 14px; font-weight: 700; margin: 10px 0 6px; } .pdl .ps { font-size: 12px; color: #555; margin-bottom: 16px; }
      .pb2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px; } .pb2 span { padding: 7px 0; border-radius: 7px; background: #fff; border: 1px solid #cfcfd4; } .pb2 .a { background: #0a7aff; border-color: #0a7aff; color: #fff; font-weight: 600; }
  `,
};
