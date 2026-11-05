/**
 * Monta los vídeos tutoriales de Finanzas (HyperFrames) a partir de specs/<nombre>.cjs
 * y de las capturas de shots/<nombre>/ (ver capturar.cjs).
 *
 *   node build.cjs [nombre|all]      → ../finanzas-<nombre>/index.html (+ assets y ficheros de proyecto)
 *   cd ../finanzas-<nombre> && npx hyperframes check && npx hyperframes render
 *
 * Estilo del vídeo «cancelar-sesion» / «instalar-*»: fondo azul marino, texto grande por pasos a la
 * izquierda, la pantalla REAL de la plataforma a la derecha con aros rojos y un ratón que señala.
 * Sin audio. 1920×1080.
 */
const fs = require('fs');
const path = require('path');

const W = 1920, H = 1080;
const SW = 1152, SH = 720;          // pantalla del «portátil» (la captura 1440×900 se reduce a 0,8)
const K = SW / 1440;
const num = (n) => (Math.round(n * 1000) / 1000).toString();
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const CSS = `
      * { margin: 0; padding: 0; box-sizing: border-box; }
      html, body { margin: 0; width: ${W}px; height: ${H}px; overflow: hidden; background: #0b1220; }
      #root { position: relative; width: 100%; height: 100%; overflow: hidden; font-family: Arial, "Segoe UI", sans-serif; color: #fff;
        background: radial-gradient(1100px 800px at 18% 20%, #17305f 0%, #0b1220 70%); }
      .step { font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #60a5fa; }
      .txt { font-weight: 800; line-height: 1.08; letter-spacing: -.02em; }
      .sub { color: #cbd5e1; font-weight: 500; }
      .center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 0 200px; }
      .center img { width: 300px; margin-bottom: 36px; }
      .big { font-size: 104px; font-weight: 800; line-height: 1.05; letter-spacing: -.03em; }
      .mid { margin-top: 34px; font-size: 48px; color: #cbd5e1; font-weight: 500; line-height: 1.25; }
      .layer { position: absolute; inset: 0; opacity: 0; overflow: hidden; z-index: 0; background: #f1f5f9; }
      .layer img { width: ${SW}px; height: auto; display: block; }
      .mark { position: absolute; border: 5px solid #ef4444; border-radius: 12px; opacity: 0; box-shadow: 0 0 0 3px rgba(255,255,255,.6), 0 0 30px rgba(239,68,68,.55); z-index: 55; pointer-events: none; }
      .cursor { position: absolute; left: 0; top: 0; width: 40px; height: 40px; opacity: 0; z-index: 70; pointer-events: none; filter: drop-shadow(0 4px 6px rgba(0,0,0,.45)); }
      .ripple { position: absolute; width: 90px; height: 90px; margin: -45px 0 0 -45px; border-radius: 50%; border: 6px solid #ef4444; background: rgba(239,68,68,.22); opacity: 0; z-index: 65; pointer-events: none; }
      .pill { position: absolute; z-index: 56; background: #ef4444; color: #fff; font-weight: 800; font-size: 22px; padding: 6px 16px; border-radius: 999px; opacity: 0; box-shadow: 0 6px 20px rgba(0,0,0,.4); white-space: nowrap; pointer-events: none; }
      .cap { position: absolute; left: 70px; top: 0; bottom: 0; width: 570px; display: flex; flex-direction: column; justify-content: center; text-align: left; }
      .cap .step { font-size: 28px; margin-bottom: 18px; }
      .cap .txt { font-size: 56px; }
      .cap .txt b { color: #fbbf24; }
      .cap .sub { margin-top: 20px; font-size: 31px; line-height: 1.28; }
      .cap .sub b { color: #fff; }
      .device { position: absolute; left: 700px; top: 160px; width: 1180px; height: 760px; border-radius: 26px; background: #05080f; padding: 14px;
        box-shadow: 0 0 0 3px #27324a, 0 40px 100px rgba(0,0,0,.6), 0 0 140px rgba(59,130,246,.16); }
      .screen { position: relative; width: ${SW}px; height: ${SH}px; border-radius: 14px; overflow: hidden; background: #0f172a; }
      #cam { position: absolute; left: 0; top: 0; width: ${SW}px; height: ${SH}px; transform-origin: 0 0; }
      .device-stand { position: absolute; left: 1140px; top: 920px; width: 260px; height: 22px; background: linear-gradient(#1c2538, #0b1220); border-radius: 0 0 24px 24px; box-shadow: 0 0 0 2px #27324a; }
      .progress { position: absolute; left: 0; bottom: 0; height: 8px; width: 100%; background: #1e293b; z-index: 80; }
      .progress i { display: block; height: 100%; width: 0; background: #3b82f6; }
`;

function build(name) {
  const S = require('./specs/' + name + '.cjs');
  const shotsDir = path.join(__dirname, 'shots', name);
  const marksAll = JSON.parse(fs.readFileSync(path.join(shotsDir, 'marks.json'), 'utf8'));
  const out = path.join(__dirname, '..', 'finanzas-' + name);
  fs.mkdirSync(path.join(out, 'assets'), { recursive: true });

  // ---- tiempos ----
  const introA = 0, introB = 3.2;
  let t = introB;
  const scenes = S.scenes.map((sc) => { const o = { ...sc, a: t, b: t + sc.dur }; t = o.b; return o; });
  const outroA = t, outroB = t + 3.6;
  const D = outroB;
  const numbered = scenes.filter((s) => s.step !== false);
  let k = 0;

  // ---- capas, textos ----
  const layers = [], caps = [];
  let fx = '';
  const marksJs = [], cursorJs = [], zoomJs = [];
  let KS = K;
  const mid = (m) => ({ x: (m.x - 4) * KS, y: (m.y - 4) * KS, w: (m.w + 8) * KS, h: (m.h + 8) * KS });
  let mi = 0;

  scenes.forEach((sc, si) => {
    fs.copyFileSync(path.join(shotsDir, sc.id + '.png'), path.join(out, 'assets', sc.id + '.png'));
    layers.push(`          <div id="L-${sc.id}" data-layout-allow-overlap data-layout-allow-occlusion class="layer"><img src="assets/${sc.id}.png" alt="" /></div>`);
    let label = '';
    if (sc.step !== false) { k += 1; label = sc.step ? esc(sc.step) : `Paso ${k} de ${numbered.length}`; }
    caps.push(`        <div id="cap-${sc.id}" class="cap clip" data-start="${num(sc.a)}" data-duration="${num(sc.dur)}" data-track-index="3"><div class="step">${label}</div><div class="txt">${sc.t}</div>${sc.s ? `<div class="sub">${sc.s}</div>` : ''}</div>`);

    const ms = (marksAll[sc.id] && marksAll[sc.id].marks) || [];
    KS = SW / ((marksAll[sc.id] && marksAll[sc.id].w0) || 1440);
    const gap = ms.length ? Math.max(0.9, (sc.dur - 1.8) / ms.length) : 0;
    ms.forEach((m, j) => {
      const r = mid(m);
      const at = sc.a + 1.0 + j * gap;
      const end = sc.b - 0.1;
      const id = 'mk' + mi;
      fx += `          <div id="${id}" class="mark" style="left:${num(r.x)}px;top:${num(r.y)}px;width:${num(r.w)}px;height:${num(r.h)}px"></div>\n`;
      marksJs.push(`tl.fromTo("#${id}", { opacity: 0, scale: 1.2 }, { opacity: 1, scale: 1, duration: 0.4, ease: "power2.out" }, ${num(at)});`);
      marksJs.push(`tl.to("#${id}", { opacity: 0, duration: 0.25 }, ${num(end)});`);
      if (m.label) {
        const py = r.y > 46 ? r.y - 44 : r.y + r.h + 8;
        fx += `          <div id="pl${mi}" class="pill" style="left:${num(Math.max(8, r.x))}px;top:${num(py)}px">${esc(m.label)}</div>\n`;
        marksJs.push(`tl.fromTo("#pl${mi}", { opacity: 0, y: 10 }, { opacity: 1, y: 0, duration: 0.35 }, ${num(at + 0.15)});`);
        marksJs.push(`tl.to("#pl${mi}", { opacity: 0, duration: 0.25 }, ${num(end)});`);
      }
      // ratón: grande → cerca de la esquina; pequeño → centro
      const big = r.w * r.h > 90000;
      const cx = big ? r.x + Math.min(r.w * 0.4, 160) : r.x + r.w / 2;
      const cy = big ? r.y + Math.min(r.h * 0.3, 50) : r.y + r.h / 2;
      cursorJs.push({ at, cx, cy, click: !!m.click, mi });
      if (m.click) fx += `          <div id="rip${mi}" class="ripple" style="left:${num(cx)}px;top:${num(cy)}px"></div>\n`;
      mi += 1;
    });

    if (sc.zoom && ms.length) {
      const us = ms.map(mid);
      const x0 = Math.min(...us.map((u) => u.x)), y0 = Math.min(...us.map((u) => u.y));
      const x1 = Math.max(...us.map((u) => u.x + u.w)), y1 = Math.max(...us.map((u) => u.y + u.h));
      const s = sc.zoom, fx_ = (x0 + x1) / 2, fy_ = (y0 + y1) / 2;
      const tx = Math.min(0, Math.max(SW * (1 - s), SW / 2 - s * fx_));
      const ty = Math.min(0, Math.max(SH * (1 - s), SH / 2 - s * fy_));
      zoomJs.push(`tl.to("#cam", { scale: ${s}, x: ${num(tx)}, y: ${num(ty)}, duration: 0.9, ease: "power2.inOut" }, ${num(sc.a + 0.6)});`);
      zoomJs.push(`tl.to("#cam", { scale: 1, x: 0, y: 0, duration: 0.5, ease: "power2.inOut" }, ${num(sc.b - 0.55)});`);
    }
  });

  // ---- timeline ----
  let js = 'const tl = gsap.timeline({ paused: true });\n';
  js += `tl.fromTo("#intro img, #intro .big, #intro .mid", { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.7, stagger: 0.18 }, 0.1);\n`;
  js += `tl.to("#intro", { opacity: 0, duration: 0.3 }, ${num(introB - 0.35)});\n`;
  js += `tl.fromTo("#outro img, #outro .big, #outro .mid", { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.7, stagger: 0.18 }, ${num(outroA + 0.2)});\n`;
  js += `tl.fromTo(".device, .device-stand", { y: 120, opacity: 0 }, { y: 0, opacity: 1, duration: 0.8, ease: "power3.out" }, ${num(introB)});\n`;
  js += `tl.to(".device, .device-stand", { opacity: 0, duration: 0.4 }, ${num(outroA - 0.4)});\n`;
  js += `tl.fromTo(".progress i", { width: "0%" }, { width: "100%", duration: ${num(outroA - introB)}, ease: "none" }, ${num(introB)});\n`;
  js += `document.querySelectorAll(".cap").forEach((c) => {
        const s = parseFloat(c.dataset.start), d = parseFloat(c.dataset.duration);
        tl.fromTo(c.children, { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 0.5, stagger: 0.1, ease: "power2.out" }, s + 0.05);
        tl.to(c.children, { opacity: 0, y: -20, duration: 0.3 }, s + d - 0.35);
      });\n`;
  scenes.forEach((sc, i) => {
    const f = 0.3;
    js += `tl.fromTo("#L-${sc.id}", { opacity: 0 }, { opacity: 1, duration: ${i === 0 ? 0.01 : f} }, ${num(sc.a)});\n`;
    js += `tl.to("#L-${sc.id}", { opacity: 0, duration: ${f} }, ${num(sc.b)});\n`;
  });
  js += marksJs.join('\n') + '\n' + zoomJs.join('\n') + '\n';
  if (cursorJs.length) {
    js += `gsap.set("#cursor", { x: 900, y: 640 });\n`;
    cursorJs.forEach((c, i) => {
      const mv = 0.8;
      if (i === 0) js += `tl.fromTo("#cursor", { opacity: 0 }, { opacity: 1, duration: 0.3 }, ${num(Math.max(0, c.at - mv - 0.1))});\n`;
      js += `tl.to("#cursor", { x: ${num(c.cx - 6)}, y: ${num(c.cy - 4)}, duration: ${num(mv - 0.1)}, ease: "power2.inOut" }, ${num(c.at - mv)});\n`;
      if (c.click) {
        js += `tl.to("#cursor", { scale: 0.85, duration: 0.1 }, ${num(c.at + 0.1)});\n`;
        js += `tl.to("#cursor", { scale: 1, duration: 0.15 }, ${num(c.at + 0.22)});\n`;
        js += `tl.fromTo("#rip${c.mi}", { opacity: 1, scale: 0.3 }, { opacity: 0, scale: 1.6, duration: 0.6, ease: "power2.out", immediateRender: false }, ${num(c.at + 0.1)});\n`;
      }
    });
    js += `tl.to("#cursor", { opacity: 0, duration: 0.3 }, ${num(outroA - 0.5)});\n`;
  }
  js += `window.__timelines["main"] = tl;\ntl.seek(0);\n`;

  const html = `<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=${W}, height=${H}" />
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.14.2/dist/gsap.min.js"></script>
    <style>${CSS}    </style>
  </head>
  <body>
    <div id="root" data-composition-id="main" data-start="0" data-duration="${num(D)}" data-width="${W}" data-height="${H}">

      <div id="intro" class="center clip" data-start="0" data-duration="${num(introB)}" data-track-index="1">
        <img src="assets/logo.webp" alt="JP Preparation" />
        <div class="big">${S.intro.title}</div>
        <div class="mid">${S.intro.sub}</div>
      </div>

      <div id="stage" class="clip" data-start="${num(introB)}" data-duration="${num(outroA - introB)}" data-track-index="2" style="position:absolute;inset:0">
${caps.join('\n')}

          <div class="device"><div class="screen"><div id="cam">
${layers.join('\n')}
${fx}          <svg id="cursor" class="cursor" viewBox="0 0 24 24"><path d="M4 2l15 9-6.5 1.5L16 20l-3 1.2-3.5-7.6L4 18z" fill="#fff" stroke="#111" stroke-width="1.4" stroke-linejoin="round"/></svg>
          </div></div></div>
          <div class="device-stand"></div>
          <div class="progress"><i></i></div>
      </div>

      <div id="outro" class="center clip" data-start="${num(outroA)}" data-duration="${num(outroB - outroA)}" data-track-index="1">
        <img src="assets/logo.webp" alt="JP Preparation" />
        <div class="big">${S.outro.title}</div>
        <div class="mid">${S.outro.sub}</div>
      </div>
    </div>
    <script>
      window.__timelines = window.__timelines || {};
      ${js.split('\n').join('\n      ')}
    </script>
  </body>
</html>
`;
  fs.writeFileSync(path.join(out, 'index.html'), html);
  fs.copyFileSync(path.join(__dirname, 'assets', 'logo.webp'), path.join(out, 'assets', 'logo.webp'));

  const id = 'finanzas-' + name;
  fs.writeFileSync(path.join(out, 'package.json'), JSON.stringify({
    name: id, private: true, type: 'module',
    scripts: { check: 'npx hyperframes check', render: 'npx hyperframes render', dev: 'npx hyperframes preview' },
  }, null, 2) + '\n');
  fs.writeFileSync(path.join(out, 'meta.json'), JSON.stringify({ id, name: id, createdAt: '2026-11-05T00:00:00.000Z' }, null, 2));
  const ref = path.join(__dirname, '..', '_instalar-generador');
  console.log('OK →', path.relative(process.cwd(), out), D.toFixed(1) + ' s');
}

const arg = process.argv[2];
const names = !arg || arg === 'all'
  ? fs.readdirSync(path.join(__dirname, 'specs')).filter((f) => f.endsWith('.cjs')).map((f) => f.replace(/\.cjs$/, ''))
  : [arg];
names.forEach(build);
