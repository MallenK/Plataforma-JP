const { I, sb, indicator, home, keyboard, G } = require('./ui-ios.cjs');
const P = require('./probe-lib.cjs').make('iphone');

// Posiciones de los elementos dibujados a mano → medidas con probe.cjs (ids "p-…").
// Las que van sobre capturas reales de la app están calculadas (captura 1170×2532 → pantalla 614×1370).
const A = {
  entrar: { x: 308, y: 853 },
  menubtn: { x: 39, y: 103, w: 58, h: 58 },
  cfg: { x: 10, y: 666, w: 351, h: 68, cx: 185, cy: 700 },
  enable: { x: 28, y: 597, w: 305, h: 50, cx: 180, cy: 622 },
};
const shareUp = 0; // (probe mide con la línea de tiempo al final: la hoja ya está arriba)

const appPage = (id, img, bar, ind) =>
  `<div id="${id}" class="layer"><img class="full" src="assets/${img}" alt="" />${sb(bar)}${indicator(ind)}</div>`;

const tile = (c, t) => `<div class="tile"><i style="background:${c}">${t}</i></div>`;

const rows = [
  [I.copy(), 'Copiar'], [I.glasses(), 'Añadir a lista de lectura'], [I.book(), 'Añadir marcador'], [I.star(), 'Añadir a favoritos'],
  [I.glass(), 'Buscar en la página'], [I.plusSq(), 'Añadir a pantalla de inicio', 'p-a2hs'], [I.pen(), 'Marcar'], [I.print(), 'Imprimir'],
];

const layers = [
  // 1 · pantalla de inicio (sin el icono de JP)
  { id: 'L-home', html: home('L-home', false), show: [[4.6, 10.8]] },

  // 2 · escribir la dirección
  { id: 'L-type', show: [[10.6, 18.6]], html: `<div id="L-type" class="layer saf-start">
      <div class="top54"></div>${sb('#000')}
      <div class="fav"><h3>Favoritos</h3><div class="tiles">${tile('#0a84ff', 'A')}${tile('#ff9f0a', 'B')}${tile('#30d158', 'C')}${tile('#bf5af2', 'D')}</div></div>
      <div id="p-pill" class="pillk"><span class="lk">${I.lock(22)}</span><span class="typed" id="typed">app.jppreparation.com</span></div>
      ${keyboard()}${indicator('#000')}</div>` },

  // 3 · Safari con la web cargada (iOS 18 y anteriores: botón Compartir abajo)
  { id: 'L-saf', show: [[18.4, 25.6], [32.2, 46.0]], html: `<div id="L-saf" class="layer saf">
      <div class="top54"></div><img class="pg" src="assets/m-login.png" alt="" />${sb('#000')}
      <div class="sbar"><div class="pill2"><span class="aa">aA</span><span class="u">${I.lock(20)} app.jppreparation.com</span><span class="rl">↻</span></div>
        <div class="tb"><i class="off">${I.back()}</i><i class="off">${I.fwd()}</i><i id="p-share">${I.share()}</i><i>${I.book()}</i><i>${I.tabs()}</i></div></div>
      ${indicator('#000')}</div>` },

  // 3b · iPhone con iOS 26: el botón Compartir está dentro de «⋯»
  { id: 'L-saf26', show: [[25.2, 32.8]], html: `<div id="L-saf26" class="layer saf">
      <div class="top54"></div><img class="pg full26" src="assets/m-login.png" alt="" />${sb('#000')}
      <div class="f26"><i class="c26">${I.back(34)}</i><div class="pill26">${I.lock(22)} app.jppreparation.com</div><i id="p-dots26" class="c26">${I.dots(40)}</i></div>${indicator('#000')}</div>` },
  { id: 'L-menu26', show: [[28.6, 32.8]], html: `<div id="L-menu26" class="layer"><div class="mcard">
      <div class="mr"><span>Nueva pestaña</span>${I.tab()}</div>
      <div id="p-share26" class="mr"><span>Compartir</span>${I.share(34)}</div>
      <div class="mr"><span>Añadir marcador</span>${I.book(34)}</div>
      <div class="mr last"><span>Buscar en la página</span>${I.glass()}</div></div></div>` },

  // 4-5 · hoja de compartir
  { id: 'L-share', show: [[32.5, 45.9]], html: `<div id="L-share" class="layer"><div class="dim"></div>
      <div id="sheet" class="sheet"><div class="grab"></div>
        <div class="sh-head"><img src="assets/jp-icon.png" alt="" /><div><b>JP Preparation</b><small>app.jppreparation.com ›</small></div><i class="x">${I.close(26)}</i></div>
        <div class="sh-apps"><div><i style="background:#0a84ff">AirDrop</i><span>AirDrop</span></div><div><i style="background:#34c759"></i><span>Mensajes</span></div><div><i style="background:#0a84ff"></i><span>Mail</span></div><div><i style="background:#25d366"></i><span>WhatsApp</span></div></div>
        <div class="sh-list">${rows.map(([ic, t, id]) => `<div ${id ? `id="${id}" ` : ''}class="sr"><span>${t}</span>${ic}</div>`).join('')}</div>
      </div></div>` },

  // 6 · «Añadir a pantalla de inicio»
  { id: 'L-a2hs', show: [[45.6, 52.9]], html: `<div id="L-a2hs" class="layer"><div class="dim"></div>
      <div class="a2sheet"><div class="nav"><span class="cn">Cancelar</span><b>Añadir a pantalla de inicio</b><span id="p-add" class="ad">Añadir</span></div>
        <div class="card"><div class="r1"><img src="assets/jp-icon.png" alt="" /><div class="nm">JP Prep</div></div><div class="r2">https://app.jppreparation.com/</div></div>
        <p class="note">Se añadirá un icono a tu pantalla de inicio para que puedas abrir este sitio web rápidamente.</p>
        <div class="card tg"><div><b>Abrir como app web</b><small>Se abrirá como una app, a pantalla completa.</small></div><i id="p-toggle" class="tog"><u></u></i></div>
      </div></div>` },

  // 7 · icono ya en la pantalla de inicio
  { id: 'L-home2', html: home('L-home2', true), show: [[52.6, 59.2]] },

  // 8-14 · la app instalada
  { id: 'L-splash', show: [[59.0, 60.7]], html: `<div id="L-splash" class="layer" style="background:#0f172a">${sb('#fff')}<img class="splash" src="assets/jp-icon.png" alt="" />${indicator('#fff')}</div>` },
  { id: 'L-login', show: [[60.4, 63.3]], html: appPage('L-login', 'm-login.png', '#fff', '#fff') },
  { id: 'L-login2', show: [[63.0, 70.9]], html: appPage('L-login2', 'm-login-lleno.png', '#fff', '#fff') },
  { id: 'L-dash', show: [[70.6, 76.5]], html: appPage('L-dash', 'm-dash.png', '#000', '#000') },
  { id: 'L-menu', show: [[76.2, 81.9]], html: appPage('L-menu', 'm-menu.png', '#fff', '#fff') },
  { id: 'L-notif', show: [[81.6, 94.3]], html: appPage('L-notif', 'm-notif-off.png', '#000', '#000') },
  { id: 'L-alert', show: [[88.0, 94.3]], html: `<div id="L-alert" class="layer"><div class="dim"></div>
      <div class="alert"><div class="at"><b>«JP Preparation» quiere enviarte notificaciones</b><p>Las notificaciones pueden incluir alertas, sonidos e iconos. Se pueden configurar en Ajustes.</p></div>
      <div class="ab"><span>No permitir</span><span id="p-permitir" class="pm">Permitir</span></div></div></div>` },
  { id: 'L-notif2', show: [[94.0, 100.8]], html: appPage('L-notif2', 'm-notif-on.png', '#000', '#000') },
];

const circle = (b) => ({ ...b, r: 999 });

module.exports = {
  kind: 'phone', device: 'iphone', W: 1080, H: 1920, D: 107.6,
  introT: [0, 4.6], outroT: [100.4, 107.6],
  intro: { title: 'Instala la app<br />en tu iPhone', sub: 'Te llevará unos 2 minutos.<br />Sigue los pasos uno a uno, sin prisa' },
  outro: { title: '¡Ya está!', sub: 'Abre siempre la app desde el <b>icono de JP Prep</b><br />y te llegarán los avisos de clases y mensajes<br /><span style="display:block;margin-top:26px;font-size:34px;color:#94a3b8">¿No te llegan avisos? Ajustes del iPhone → Notificaciones → JP Prep → Permitir</span>' },
  assets: { 'jp-icon.png': 'jp-icon.png', 'm-login.png': 'm-login.png', 'm-login-lleno.png': 'm-login-lleno.png', 'm-dash.png': 'm-dash.png', 'm-menu.png': 'm-menu.png', 'm-notif-off.png': 'm-notif-off.png', 'm-notif-on.png': 'm-notif-on.png' },
  steps: [
    { a: 4.6, b: 10.6, t: 'Abre <b>Safari</b>', s: 'El icono azul con una brújula.<br />Sirve solo Safari, no otros navegadores' },
    { a: 10.6, b: 18.4, t: 'Escribe esta dirección', s: '<b style="color:#fff">app.jppreparation.com</b><br />y pulsa «Ir»' },
    { a: 18.4, b: 25.2, t: 'Pulsa el botón<br /><b>Compartir</b>', s: 'El cuadrado con una flecha hacia arriba' },
    { a: 25.2, b: 32.4, num: false, label: '¿No ves ese botón?', t: 'Pulsa los tres puntitos <b>«⋯»</b>', s: 'Y luego «Compartir». Pasa en los iPhone más nuevos' },
    { a: 32.4, b: 39.2, t: 'Desliza el menú<br />hacia arriba', s: 'Con el dedo, como si subieras una persiana' },
    { a: 39.2, b: 45.6, t: 'Pulsa <b>«Añadir a<br />pantalla de inicio»</b>', s: 'Está en la lista, más abajo' },
    { a: 45.6, b: 52.6, t: 'Pulsa <b>«Añadir»</b>', s: 'Arriba a la derecha. Deja activado<br />«Abrir como app web»' },
    { a: 52.6, b: 59.0, t: '¡Ya tienes el icono!', s: 'A partir de ahora abre la app desde aquí,<br />no desde Safari' },
    { a: 59.0, b: 66.4, t: 'Escribe tu <b>email</b><br />y tu <b>contraseña</b>', s: 'Los de siempre. Es normal que te los pida otra vez' },
    { a: 66.4, b: 70.6, t: 'Pulsa <b>«Entrar»</b>' },
    { a: 70.6, b: 76.2, t: 'Pulsa las tres rayitas <b>☰</b>', s: 'Arriba a la izquierda' },
    { a: 76.2, b: 81.6, t: 'Pulsa <b>«Configuración»</b>' },
    { a: 81.6, b: 88.0, t: 'Pulsa <b>«Activar<br />notificaciones»</b>', s: 'Así te avisaremos de clases y mensajes' },
    { a: 88.0, b: 94.0, t: 'Pulsa <b>«Permitir»</b>', s: 'Si pulsas «No permitir», no te llegarán los avisos' },
    { a: 94.0, b: 100.4, num: false, label: 'Hecho', t: '¡Notificaciones activadas!', s: 'Ya recibirás los avisos de clases, mensajes y bonos' },
  ],
  layers,
  marks: [
    { ...P.b('p-safari', 10), r: 34, a: 5.6, b: 8.6 },
    { ...P.b('p-pill', 4), r: 36, a: 11.2, b: 12.4 },
    { ...P.b('p-go', 4), r: 14, a: 15.6, b: 17.1 },
    { ...circle(P.b('p-share', 18)), a: 19.2, b: 24.0 },
    { ...circle(P.b('p-dots26', 6)), a: 26.0, b: 28.2 },
    { ...P.b('p-share26', 0), r: 20, a: 29.2, b: 31.2 },
    { ...P.b('p-a2hs', 4), y: P.b('p-a2hs', 4).y + shareUp, r: 18, a: 39.8, b: 44.0 },
    { ...P.b('p-toggle', 8), r: 40, a: 46.4, b: 48.8 },
    { ...P.b('p-add', 14), r: 30, a: 49.2, b: 51.2 },
    { x: P.c('p-jp').x - 62, y: P.b('p-jp').y + 52 - 62, w: 124, h: 124, r: 34, a: 53.4, b: 57.6 },
    { x: 36, y: 520, w: 542, h: 214, r: 34, a: 60.9, b: 65.5 },
    { x: A.entrar.x - 270, y: A.entrar.y - 44, w: 540, h: 88, r: 44, a: 67.0, b: 69.4 },
    { x: A.menubtn.x - A.menubtn.w / 2 - 6, y: A.menubtn.y - A.menubtn.h / 2 - 6, w: A.menubtn.w + 12, h: A.menubtn.h + 12, r: 16, a: 71.2, b: 75.0 },
    { x: A.cfg.x, y: A.cfg.y, w: A.cfg.w, h: A.cfg.h, r: 18, a: 77.0, b: 80.2 },
    { x: A.enable.x - 6, y: A.enable.y - 6, w: A.enable.w + 12, h: A.enable.h + 12, r: 14, a: 82.4, b: 86.8 },
    { ...P.b('p-permitir', 6), r: 14, a: 89.0, b: 92.6 },
  ],
  taps: [
    { ...P.c('p-safari'), t: 8.8 },
    { ...P.c('p-go'), t: 17.3 },
    { ...P.c('p-share'), t: 24.2 },
    { ...P.c('p-dots26'), t: 28.2 },
    { ...P.c('p-share26'), t: 31.6 },
    { ...P.c('p-a2hs', 0, shareUp), t: 44.2 },
    { ...P.c('p-add'), t: 51.4 },
    { x: P.c('p-jp').x, y: P.b('p-jp').y + 52, t: 57.8 },
    { x: A.entrar.x, y: A.entrar.y, t: 69.5 },
    { x: A.menubtn.x, y: A.menubtn.y, t: 75.3 },
    { x: A.cfg.cx, y: A.cfg.cy, t: 80.4 },
    { x: A.enable.cx, y: A.enable.cy, t: 87.0 },
    { ...P.c('p-permitir'), t: 92.8 },
  ],
  swipes: [{ x1: 307, y1: 1150, x2: 307, y2: 640, a: 34.4, b: 36.6 }],
  tl: `
      gsap.set("#typed", { clipPath: "inset(0 100% 0 0)" });
      tl.to("#typed", { clipPath: "inset(0 0% 0 0)", duration: 3.0, ease: "steps(21)" }, 12.4);
      gsap.set("#sheet", { y: 1240 });
      tl.to("#sheet", { y: 570, duration: 0.7, ease: "power3.out" }, 32.5);
      tl.to("#sheet", { y: 0, duration: 1.3, ease: "power2.inOut" }, 34.7);
      tl.fromTo("#L-home2 .jpapp", { scale: 0.2, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.6, ease: "back.out(2.2)" }, 53.0);
  `,
  css: `
      .sbi { position: absolute; left: 0; right: 0; top: 0; height: 54px; z-index: 30; display: flex; align-items: center; justify-content: space-between; padding: 6px 38px 0 46px; font-weight: 700; font-size: 22px; }
      .isl { position: absolute; left: 50%; top: 12px; margin-left: -62px; width: 124px; height: 36px; border-radius: 20px; background: #000; }
      .sg { display: flex; gap: 8px; align-items: center; }
      .hbar { position: absolute; left: 50%; bottom: 9px; margin-left: -100px; width: 200px; height: 7px; border-radius: 5px; z-index: 30; }
      .full { position: absolute; left: 0; top: 0; width: 100%; height: 100%; object-fit: cover; object-position: top; }
      .splash { position: absolute; left: 50%; top: 50%; width: 190px; height: 190px; margin: -95px 0 0 -95px; border-radius: 44px; }
      .dim { position: absolute; inset: 0; background: rgba(0,0,0,.38); }
      .top54 { position: absolute; left: 0; right: 0; top: 0; height: 54px; background: #f2f2f7; z-index: 2; }

      .ios-home { background: linear-gradient(165deg, #1d3a8a 0%, #5b3fc4 52%, #e0709a 100%); }
      .ios-home .grid { position: absolute; left: 16px; right: 16px; top: 110px; display: grid; grid-template-columns: repeat(4, 1fr); row-gap: 40px; }
      .ai { display: flex; flex-direction: column; align-items: center; gap: 9px; color: #fff; font-size: 18px; font-weight: 600; }
      .ic { width: 104px; height: 104px; border-radius: 25px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
      .ic img { width: 100%; height: 100%; display: block; }
      .ai.nolabel span { display: none; }
      .dock { position: absolute; left: 22px; right: 22px; bottom: 28px; height: 140px; border-radius: 44px; background: rgba(255,255,255,.26); display: grid; grid-template-columns: repeat(4, 1fr); align-items: center; justify-items: center; }

      .saf-start, .saf { background: #fff; }
      .fav { position: absolute; left: 30px; right: 30px; top: 110px; color: #000; }
      .fav h3 { font-size: 30px; margin-bottom: 18px; }
      .tiles { display: flex; gap: 26px; }
      .tile { display: flex; flex-direction: column; align-items: center; }
      .tile i { width: 112px; height: 112px; border-radius: 24px; display: block; font-style: normal; color: #fff; font-size: 44px; font-weight: 700; text-align: center; line-height: 112px; }
      .pillk { position: absolute; left: 24px; right: 24px; bottom: 440px; height: 70px; border-radius: 18px; background: #e9e9ee; display: flex; align-items: center; padding: 0 20px; gap: 12px; font-size: 27px; color: #000; z-index: 3; }
      .pillk .lk { color: #8e8e93; display: flex; }
      .kb { position: absolute; left: 0; right: 0; bottom: 0; height: 428px; background: #d1d3d9; padding: 14px 6px 0; }
      .kr { display: flex; gap: 9px; justify-content: center; margin-bottom: 14px; }
      .kr b { flex: 1; max-width: 52px; height: 70px; background: #fff; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 29px; font-weight: 400; color: #000; box-shadow: 0 2px 0 #898a8d; }
      .kr b.w { max-width: none; flex: 0 0 104px; background: #abb0ba; font-size: 24px; }
      .kr b.sp { max-width: none; flex: 1; font-size: 22px; color: #555; }
      .kr b.go { background: #007aff; color: #fff; font-weight: 600; }

      .saf .pg { position: absolute; left: 0; top: 54px; width: 100%; height: 1104px; object-fit: cover; object-position: top; }
      .saf .pg.full26 { height: 1316px; }
      .sbar { position: absolute; left: 0; right: 0; bottom: 0; height: 212px; background: #f2f2f7; border-top: 1px solid #d0d0d5; }
      .pill2 { position: absolute; left: 24px; right: 24px; top: 14px; height: 64px; border-radius: 18px; background: #e3e3e8; display: flex; align-items: center; justify-content: space-between; padding: 0 22px; font-size: 25px; color: #000; }
      .pill2 .u { display: flex; align-items: center; gap: 8px; }
      .pill2 .aa, .pill2 .rl { color: #444; font-size: 26px; }
      .tb { position: absolute; left: 0; right: 0; top: 96px; height: 72px; display: grid; grid-template-columns: repeat(5, 1fr); justify-items: center; align-items: center; color: #0a74ff; }
      .tb i { display: flex; } .tb i.off { color: #b8c2d3; }
      .f26 { position: absolute; left: 18px; right: 18px; bottom: 28px; height: 78px; display: flex; gap: 12px; align-items: center; }
      .c26 { width: 78px; height: 78px; border-radius: 50%; background: rgba(255,255,255,.96); box-shadow: 0 4px 18px rgba(0,0,0,.28); display: flex; align-items: center; justify-content: center; color: #111; flex: 0 0 78px; }
      .pill26 { flex: 1; height: 78px; border-radius: 40px; background: rgba(255,255,255,.96); box-shadow: 0 4px 18px rgba(0,0,0,.28); display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 25px; color: #111; }
      .mcard { position: absolute; right: 18px; bottom: 126px; width: 340px; background: rgba(247,247,249,.98); border-radius: 30px; box-shadow: 0 14px 50px rgba(0,0,0,.4); padding: 6px 0; }
      .mr { height: 74px; display: flex; align-items: center; justify-content: space-between; padding: 0 28px; font-size: 25px; color: #111; border-bottom: 1px solid #dcdce0; }
      .mr.last { border-bottom: 0; }

      .sheet { position: absolute; left: 0; right: 0; top: 130px; height: 1240px; background: #f2f2f7; border-radius: 38px 38px 0 0; padding: 12px 22px; color: #000; }
      .grab { width: 70px; height: 8px; border-radius: 5px; background: #c4c4c8; margin: 0 auto 16px; }
      .sh-head { display: flex; align-items: center; gap: 18px; height: 96px; }
      .sh-head img { width: 76px; height: 76px; border-radius: 18px; }
      .sh-head b { display: block; font-size: 27px; } .sh-head small { color: #6e6e73; font-size: 21px; }
      .sh-head .x { margin-left: auto; width: 54px; height: 54px; border-radius: 50%; background: #e0e0e5; display: flex; align-items: center; justify-content: center; color: #555; }
      .sh-apps { display: flex; justify-content: space-between; padding: 16px 8px 12px; height: 160px; }
      .sh-apps div { display: flex; flex-direction: column; align-items: center; gap: 10px; font-size: 18px; color: #333; }
      .sh-apps i { width: 84px; height: 84px; border-radius: 22px; display: block; font-style: normal; color: #fff; font-size: 15px; text-align: center; line-height: 84px; }
      .sh-list { background: #fff; border-radius: 22px; margin-top: 16px; }
      .sr { height: 66px; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; font-size: 24px; border-bottom: 1px solid #e6e6ea; }
      .sr:last-child { border-bottom: 0; }

      .a2sheet { position: absolute; left: 0; right: 0; top: 100px; bottom: 0; background: #f2f2f7; border-radius: 38px 38px 0 0; padding: 28px 24px; color: #000; }
      .a2sheet .nav { display: flex; align-items: center; justify-content: space-between; height: 56px; font-size: 22px; margin-bottom: 26px; }
      .a2sheet .nav .cn { color: #0a74ff; } .a2sheet .nav b { font-size: 21px; } .a2sheet .nav .ad { color: #0a74ff; font-weight: 700; font-size: 26px; }
      .a2sheet .card { background: #fff; border-radius: 22px; padding: 0 24px; }
      .a2sheet .r1 { display: flex; align-items: center; gap: 20px; height: 112px; border-bottom: 1px solid #e6e6ea; }
      .a2sheet .r1 img { width: 76px; height: 76px; border-radius: 18px; }
      .a2sheet .nm { font-size: 28px; font-weight: 600; }
      .a2sheet .r2 { height: 70px; display: flex; align-items: center; color: #6e6e73; font-size: 23px; }
      .a2sheet .note { color: #6e6e73; font-size: 20px; line-height: 1.35; margin: 14px 8px 26px; }
      .a2sheet .tg { display: flex; align-items: center; justify-content: space-between; height: 104px; }
      .a2sheet .tg b { display: block; font-size: 25px; font-weight: 600; } .a2sheet .tg small { color: #6e6e73; font-size: 19px; }
      .tog { width: 84px; height: 50px; border-radius: 26px; background: #34c759; display: block; position: relative; flex: 0 0 84px; }
      .tog u { position: absolute; right: 4px; top: 4px; width: 42px; height: 42px; border-radius: 50%; background: #fff; display: block; box-shadow: 0 2px 6px rgba(0,0,0,.3); }

      .alert { position: absolute; left: 92px; top: 520px; width: 430px; background: rgba(246,246,248,.98); border-radius: 30px; text-align: center; color: #000; overflow: hidden; }
      .alert .at { padding: 30px 26px 24px; } .alert .at b { display: block; font-size: 26px; line-height: 1.25; margin-bottom: 10px; } .alert .at p { font-size: 20px; line-height: 1.3; color: #333; }
      .alert .ab { display: grid; grid-template-columns: 1fr 1fr; border-top: 1px solid #c7c7cc; height: 78px; }
      .alert .ab span { display: flex; align-items: center; justify-content: center; font-size: 26px; color: #0a74ff; }
      .alert .ab span.pm { font-weight: 700; border-left: 1px solid #c7c7cc; }
  `,
};
