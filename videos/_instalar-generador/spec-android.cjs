const { A: AI, sb, navPill, home, keyboard, G, I } = require('./ui-android.cjs');
const P = require('./probe-lib.cjs').make('android');

// Posiciones sobre las capturas reales de la app (captura 1170×2532 → pantalla 614×1370).
const A = {
  entrar: { x: 308, y: 853 },
  menubtn: { x: 39, y: 103, w: 58, h: 58 },
  cfg: { x: 10, y: 666, w: 351, h: 68, cx: 185, cy: 700 },
  enable: { x: 28, y: 597, w: 305, h: 50, cx: 180, cy: 622 },
};

const appPage = (id, img, bar, nav) =>
  `<div id="${id}" class="layer"><img class="full" src="assets/${img}" alt="" />${sb(bar)}${navPill(nav)}</div>`;

const menuRows = [
  [AI.newtab(), 'Nueva pestaña'], [AI.incog(), 'Nueva pestaña de incógnito'], [AI.hist(), 'Historial'], [AI.down(), 'Descargas'],
  [AI.star(), 'Marcadores'], [AI.share(), 'Compartir…'], [AI.install(), 'Instalar app', 'p-install'], [AI.gear(), 'Configuración'],
];

const toolbar = (inner) => `<div class="ctop">${sb('#000')}<div class="ctb">${inner}<i class="tabsq">1</i><i id="p-menu" class="mdots">${AI.dots(36)}</i></div></div>`;

const layers = [
  { id: 'L-ahome', html: home('L-ahome', false), show: [[4.6, 10.6]] },

  { id: 'L-atype', show: [[10.4, 18.2]], html: `<div id="L-atype" class="layer cr">
      ${toolbar(`<div id="p-pill" class="cpill foc"><span class="lk">${AI.lock(22)}</span><span id="atyped" class="typed">app.jppreparation.com</span></div>`)}
      <div class="sugg"><div class="sg1"><span>${I.glass(26)}</span><div><b>app.jppreparation.com</b><small>Abrir esta dirección</small></div></div></div>
      ${keyboard()}${navPill('#000')}</div>` },

  { id: 'L-achrome', show: [[18.0, 37.4]], html: `<div id="L-achrome" class="layer cr">
      ${toolbar(`<div class="cpill"><span class="lk">${AI.lock(22)}</span><span>app.jppreparation.com</span></div>`)}
      <img class="cpg" src="assets/m-login.png" alt="" />${navPill('#000')}</div>` },
  { id: 'L-amenu', show: [[24.2, 31.2]], html: `<div id="L-amenu" class="layer"><div class="cmenu">${menuRows.map(([ic, t, id]) => `<div ${id ? `id="${id}" ` : ''}class="cr1"><span class="ico">${ic}</span><span>${t}</span></div>`).join('')}</div></div>` },
  { id: 'L-ainst', show: [[31.0, 37.2]], html: `<div id="L-ainst" class="layer"><div class="dim"></div>
      <div class="idlg"><div class="idt">Instalar app</div>
        <div class="idr"><img src="assets/jp-icon.png" alt="" /><div><b>JP Preparation</b><small>app.jppreparation.com</small></div></div>
        <div class="idb"><span class="cn">Cancelar</span><span id="p-instalar" class="ok">Instalar</span></div></div></div>` },

  { id: 'L-ahome2', html: home('L-ahome2', true), show: [[37.0, 43.3]] },

  { id: 'L-asplash', show: [[43.0, 44.7]], html: `<div id="L-asplash" class="layer" style="background:#0f172a">${sb('#fff')}<img class="splash" src="assets/jp-icon.png" alt="" />${navPill('#fff')}</div>` },
  { id: 'L-alogin', show: [[44.4, 47.3]], html: appPage('L-alogin', 'm-login.png', '#fff', '#fff') },
  { id: 'L-alogin2', show: [[47.0, 54.6]], html: appPage('L-alogin2', 'm-login-lleno.png', '#fff', '#fff') },
  { id: 'L-adash', show: [[54.3, 60.2]], html: appPage('L-adash', 'm-dash.png', '#000', '#000') },
  { id: 'L-amenu2', show: [[59.9, 65.6]], html: appPage('L-amenu2', 'm-menu.png', '#fff', '#fff') },
  { id: 'L-anotif', show: [[65.3, 77.9]], html: appPage('L-anotif', 'm-notif-off.png', '#000', '#000') },
  { id: 'L-aperm', show: [[71.8, 78.0]], html: `<div id="L-aperm" class="layer"><div class="dim"></div>
      <div class="pdlg"><i class="bell">${AI.bell(46)}</i><div class="pt">¿Permitir que <b>JP Preparation</b> te envíe notificaciones?</div>
        <div id="p-permitir" class="pb pa">Permitir</div><div class="pb">No permitir</div></div></div>` },
  { id: 'L-anotif2', show: [[77.8, 84.2]], html: appPage('L-anotif2', 'm-notif-on.png', '#000', '#000') },
];

module.exports = {
  kind: 'phone', device: 'android', W: 1080, H: 1920, D: 91.2,
  introT: [0, 4.6], outroT: [84.0, 91.2],
  intro: { title: 'Instala la app<br />en tu móvil Android', sub: 'Te llevará unos 2 minutos.<br />Sigue los pasos uno a uno, sin prisa' },
  outro: { title: '¡Ya está!', sub: 'Abre siempre la app desde el <b>icono de JP Prep</b><br />y te llegarán los avisos de clases y mensajes<br /><span style="display:block;margin-top:26px;font-size:34px;color:#94a3b8">¿No te llegan avisos? Ajustes del móvil → Aplicaciones → JP Preparation → Notificaciones</span>' },
  assets: { 'jp-icon.png': 'jp-icon.png', 'm-login.png': 'm-login.png', 'm-login-lleno.png': 'm-login-lleno.png', 'm-dash.png': 'm-dash.png', 'm-menu.png': 'm-menu.png', 'm-notif-off.png': 'm-notif-off.png', 'm-notif-on.png': 'm-notif-on.png' },
  steps: [
    { a: 4.6, b: 10.4, t: 'Abre <b>Chrome</b>', s: 'El icono redondo de colores:<br />rojo, amarillo, verde y azul' },
    { a: 10.4, b: 18.0, t: 'Escribe esta dirección', s: '<b style="color:#fff">app.jppreparation.com</b><br />y pulsa la flecha del teclado' },
    { a: 18.0, b: 24.2, t: 'Pulsa los tres<br />puntitos <b>⋮</b>', s: 'Arriba a la derecha' },
    { a: 24.2, b: 31.0, t: 'Pulsa <b>«Instalar app»</b>', s: 'Si no la ves, busca «Añadir a la<br />pantalla de inicio» y pulsa esa' },
    { a: 31.0, b: 37.0, t: 'Pulsa <b>«Instalar»</b>', s: 'Es el botón azul de abajo' },
    { a: 37.0, b: 43.0, t: '¡Ya tienes el icono!', s: 'Búscalo en tu pantalla de inicio.<br />Desde ahora abre la app desde aquí' },
    { a: 43.0, b: 50.4, t: 'Escribe tu <b>email</b><br />y tu <b>contraseña</b>', s: 'Los de siempre' },
    { a: 50.4, b: 54.3, t: 'Pulsa <b>«Entrar»</b>' },
    { a: 54.3, b: 59.9, t: 'Pulsa las tres rayitas <b>☰</b>', s: 'Arriba a la izquierda' },
    { a: 59.9, b: 65.3, t: 'Pulsa <b>«Configuración»</b>' },
    { a: 65.3, b: 71.8, t: 'Pulsa <b>«Activar<br />notificaciones»</b>', s: 'Así te avisaremos de clases y mensajes' },
    { a: 71.8, b: 77.8, t: 'Pulsa <b>«Permitir»</b>', s: 'Si pulsas «No permitir», no te llegarán los avisos' },
    { a: 77.8, b: 84.0, num: false, label: 'Hecho', t: '¡Notificaciones activadas!', s: 'Ya recibirás los avisos de clases, mensajes y bonos' },
  ],
  layers,
  marks: [
    { ...P.b('p-chrome', 10), r: 40, a: 5.6, b: 8.6 },
    { ...P.b('p-pill', 4), r: 34, a: 11.0, b: 12.2 },
    { ...P.b('p-enter', 4), r: 40, a: 15.0, b: 16.8 },
    { ...P.b('p-menu', 14), r: 34, a: 19.0, b: 22.6 },
    { ...P.b('p-install', 4), r: 16, a: 25.2, b: 29.2 },
    { ...P.b('p-instalar', 10), r: 36, a: 32.0, b: 35.4 },
    { x: P.c('p-jp').x - 62, y: P.b('p-jp').y + 6, w: 124, h: 124, r: 34, a: 38.0, b: 41.6 },
    { x: 36, y: 520, w: 542, h: 214, r: 34, a: 44.5, b: 49.0 },
    { x: A.entrar.x - 270, y: A.entrar.y - 44, w: 540, h: 88, r: 44, a: 51.0, b: 53.4 },
    { x: A.menubtn.x - A.menubtn.w / 2 - 6, y: A.menubtn.y - A.menubtn.h / 2 - 6, w: A.menubtn.w + 12, h: A.menubtn.h + 12, r: 16, a: 55.0, b: 58.6 },
    { x: A.cfg.x, y: A.cfg.y, w: A.cfg.w, h: A.cfg.h, r: 18, a: 60.6, b: 63.8 },
    { x: A.enable.x - 6, y: A.enable.y - 6, w: A.enable.w + 12, h: A.enable.h + 12, r: 14, a: 66.0, b: 70.4 },
    { ...P.b('p-permitir', 6), r: 40, a: 72.8, b: 76.4 },
  ],
  taps: [
    { ...P.c('p-chrome'), t: 8.8 },
    { ...P.c('p-enter'), t: 16.6 },
    { ...P.c('p-menu'), t: 22.8 },
    { ...P.c('p-install'), t: 29.4 },
    { ...P.c('p-instalar'), t: 35.6 },
    { x: P.c('p-jp').x, y: P.b('p-jp').y + 58, t: 41.8 },
    { x: A.entrar.x, y: A.entrar.y, t: 53.5 },
    { x: A.menubtn.x, y: A.menubtn.y, t: 58.9 },
    { x: A.cfg.cx, y: A.cfg.cy, t: 64.2 },
    { x: A.enable.cx, y: A.enable.cy, t: 70.8 },
    { ...P.c('p-permitir'), t: 76.6 },
  ],
  tl: `
      gsap.set("#atyped", { clipPath: "inset(0 100% 0 0)" });
      tl.to("#atyped", { clipPath: "inset(0 0% 0 0)", duration: 3.0, ease: "steps(21)" }, 12.2);
      tl.fromTo("#L-ahome2 .jpapp", { scale: 0.2, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.6, ease: "back.out(2.2)" }, 37.6);
  `,
  css: `
      .sba { position: absolute; left: 0; right: 0; top: 0; height: 46px; z-index: 30; display: flex; align-items: center; justify-content: space-between; padding: 4px 36px 0 40px; font-weight: 500; font-size: 22px; }
      .sba .cam { position: absolute; left: 50%; top: 14px; margin-left: -11px; width: 22px; height: 22px; border-radius: 50%; background: #000; }
      .sba .sg { display: flex; gap: 8px; align-items: center; }
      .navp { position: absolute; left: 50%; bottom: 10px; margin-left: -90px; width: 180px; height: 7px; border-radius: 5px; z-index: 30; }
      .full { position: absolute; left: 0; top: 0; width: 100%; height: 100%; object-fit: cover; object-position: top; }
      .splash { position: absolute; left: 50%; top: 50%; width: 190px; height: 190px; margin: -95px 0 0 -95px; border-radius: 44px; }
      .dim { position: absolute; inset: 0; background: rgba(0,0,0,.45); }

      .and-home { background: linear-gradient(170deg, #0a3550 0%, #16727f 55%, #d9a441 120%); }
      .gsearch { position: absolute; left: 28px; right: 28px; top: 84px; height: 76px; border-radius: 40px; background: rgba(255,255,255,.95); display: flex; align-items: center; gap: 18px; padding: 0 28px; color: #5f6368; font-size: 26px; }
      .gsearch b { font-size: 34px; color: #4285f4; }
      .agrid { position: absolute; left: 16px; right: 16px; top: 220px; display: grid; grid-template-columns: repeat(4, 1fr); row-gap: 44px; }
      .aa { display: flex; flex-direction: column; align-items: center; gap: 10px; color: #fff; font-size: 19px; font-weight: 500; }
      .ac { width: 104px; height: 104px; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; }
      .ac img { width: 100%; height: 100%; display: block; }
      .aa.nolabel span { display: none; }
      .adock { position: absolute; left: 22px; right: 22px; bottom: 40px; height: 140px; display: grid; grid-template-columns: repeat(4, 1fr); align-items: center; justify-items: center; }

      .cr { background: #fff; }
      .ctop { position: absolute; left: 0; right: 0; top: 0; height: 152px; background: #fff; z-index: 5; border-bottom: 1px solid #e3e3e6; }
      .ctb { position: absolute; left: 14px; right: 10px; top: 52px; height: 84px; display: flex; align-items: center; gap: 12px; }
      .cpill { flex: 1; height: 72px; border-radius: 36px; background: #eef0f3; display: flex; align-items: center; gap: 14px; padding: 0 26px; font-size: 26px; color: #1f1f1f; }
      .cpill.foc { background: #fff; box-shadow: 0 0 0 3px #1a73e8; }
      .cpill .lk { color: #5f6368; display: flex; }
      .tabsq { width: 44px; height: 44px; border: 3px solid #444; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-style: normal; font-weight: 700; font-size: 22px; color: #333; flex: 0 0 44px; }
      .mdots { width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; color: #444; flex: 0 0 56px; }
      .cpg { position: absolute; left: 0; top: 152px; width: 100%; height: 1218px; object-fit: cover; object-position: top; }
      .sugg { position: absolute; left: 0; right: 0; top: 152px; }
      .sg1 { display: flex; align-items: center; gap: 22px; padding: 26px 28px; font-size: 26px; color: #1f1f1f; } .sg1 small { display: block; color: #5f6368; font-size: 20px; margin-top: 4px; } .sg1 span { color: #5f6368; display: flex; }
      .kba { position: absolute; left: 0; right: 0; bottom: 0; height: 420px; background: #eceff1; padding: 16px 6px 0; }
      .kr { display: flex; gap: 9px; justify-content: center; margin-bottom: 16px; }
      .kr b { flex: 1; max-width: 52px; height: 74px; background: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: 400; color: #202124; }
      .kr b.w { max-width: none; flex: 0 0 74px; background: #dfe3e8; font-size: 22px; }
      .kr b.sp { max-width: none; flex: 1; font-size: 22px; color: #5f6368; }
      .kr b.en { max-width: none; flex: 0 0 96px; background: #1a73e8; color: #fff; border-radius: 37px; }

      .cmenu { position: absolute; right: 14px; top: 100px; width: 440px; background: #fff; border-radius: 20px; box-shadow: 0 10px 44px rgba(0,0,0,.45); padding: 8px 0; }
      .cr1 { height: 76px; display: flex; align-items: center; gap: 24px; padding: 0 28px; font-size: 25px; color: #202124; }
      .cr1 .ico { display: flex; color: #5f6368; }

      .idlg { position: absolute; left: 24px; right: 24px; bottom: 120px; background: #fff; border-radius: 30px; padding: 34px 32px 26px; color: #202124; }
      .idt { font-size: 30px; font-weight: 600; margin-bottom: 26px; }
      .idr { display: flex; align-items: center; gap: 22px; margin-bottom: 36px; }
      .idr img { width: 84px; height: 84px; border-radius: 20px; }
      .idr b { display: block; font-size: 27px; font-weight: 600; } .idr small { color: #5f6368; font-size: 21px; }
      .idb { display: flex; justify-content: flex-end; align-items: center; gap: 26px; font-size: 26px; font-weight: 500; }
      .idb .cn { color: #1a73e8; padding: 16px 20px; }
      .idb .ok { background: #1a73e8; color: #fff; padding: 18px 40px; border-radius: 40px; }

      .pdlg { position: absolute; left: 62px; right: 62px; top: 470px; background: #f3f4f6; border-radius: 36px; padding: 34px 30px 30px; text-align: center; color: #202124; }
      .pdlg .bell { display: flex; justify-content: center; color: #1a73e8; margin-bottom: 14px; }
      .pdlg .pt { font-size: 27px; line-height: 1.28; margin-bottom: 28px; }
      .pdlg .pb { height: 74px; border-radius: 38px; border: 2px solid #c8ccd2; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 500; color: #1a73e8; margin-top: 14px; background: #fff; }
      .pdlg .pb.pa { background: #1a73e8; border-color: #1a73e8; color: #fff; }
  `,
};
