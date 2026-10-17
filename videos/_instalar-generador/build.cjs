/**
 * Generador de los vídeos «Cómo instalar la app» (HyperFrames).
 *
 *   node build.cjs iphone|android|windows|mac|all
 *
 * Lee spec-<nombre>.cjs y escribe ../instalar-<nombre>/index.html (+ assets, package.json…).
 * Después, dentro de esa carpeta:  npm run check  ·  npm run render
 *
 * Una especificación describe: pasos con su texto, capas (pantallas del móvil/ordenador),
 * toques/clics, marcas rojas, zooms y animaciones propias. Todo con tiempos absolutos (s).
 */
const fs = require('fs');
const path = require('path');

const HF_VERSION = '0.8.161';
const names = process.argv[2] === 'all' || !process.argv[2] ? ['iphone', 'android', 'windows', 'mac'] : [process.argv[2]];

const num = (n) => (Math.round(n * 1000) / 1000).toString();

function css(S) {
  const phone = S.kind === 'phone';
  const base = `
      * { margin: 0; padding: 0; box-sizing: border-box; }
      html, body { margin: 0; width: ${S.W}px; height: ${S.H}px; overflow: hidden; background: #0b1220; }
      #root { position: relative; width: 100%; height: 100%; overflow: hidden; font-family: Arial, "Segoe UI", sans-serif; color: #fff;
        background: radial-gradient(${phone ? '900px 700px at 50% 0%' : '1100px 800px at 18% 20%'}, #17305f 0%, #0b1220 70%); }
      .step { font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #60a5fa; }
      .txt { font-weight: 800; line-height: 1.08; letter-spacing: -.02em; }
      .sub { color: #cbd5e1; font-weight: 500; }
      .center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 0 ${phone ? 80 : 200}px; }
      .center img { width: ${phone ? 340 : 300}px; margin-bottom: ${phone ? 50 : 36}px; }
      .big { font-size: ${phone ? 96 : 104}px; font-weight: 800; line-height: 1.05; letter-spacing: -.03em; }
      .mid { margin-top: 34px; font-size: ${phone ? 46 : 48}px; color: #cbd5e1; font-weight: 500; line-height: 1.25; }
      .mid b { color: #fff; }
      .layer { position: absolute; inset: 0; opacity: 0; overflow: hidden; z-index: 0; }
      .tap { position: absolute; width: 120px; height: 120px; margin: -60px 0 0 -60px; border-radius: 50%; border: 8px solid #fff; background: rgba(255,255,255,.28); opacity: 0; box-shadow: 0 0 0 6px rgba(239,68,68,.65); z-index: 60; pointer-events: none; }
      .mark { position: absolute; border: 6px solid #ef4444; border-radius: 22px; opacity: 0; box-shadow: 0 0 0 4px rgba(255,255,255,.55), 0 0 40px rgba(239,68,68,.55); z-index: 55; pointer-events: none; }
      .cursor { position: absolute; left: 0; top: 0; width: 44px; height: 44px; opacity: 0; z-index: 70; pointer-events: none; filter: drop-shadow(0 4px 6px rgba(0,0,0,.45)); }
      .ripple { position: absolute; width: 90px; height: 90px; margin: -45px 0 0 -45px; border-radius: 50%; border: 6px solid #ef4444; background: rgba(239,68,68,.22); opacity: 0; z-index: 65; pointer-events: none; }
      .swipe { position: absolute; width: 110px; height: 110px; margin: -55px 0 0 -55px; border-radius: 50%; border: 8px solid #fff; background: rgba(255,255,255,.3); opacity: 0; box-shadow: 0 0 0 6px rgba(239,68,68,.65); z-index: 60; pointer-events: none; }
      .label-pill { position: absolute; z-index: 56; background: #ef4444; color: #fff; font-weight: 800; font-size: 26px; padding: 8px 18px; border-radius: 999px; opacity: 0; box-shadow: 0 6px 20px rgba(0,0,0,.4); white-space: nowrap; pointer-events: none; }
`;
  const phoneCss = `
      .cap { position: absolute; left: 60px; right: 60px; top: 100px; height: 350px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
      .cap .step { font-size: 34px; margin-bottom: 16px; }
      .cap .txt { font-size: 64px; }
      .cap .txt b { color: #fbbf24; }
      .cap .sub { margin-top: 16px; font-size: 35px; line-height: 1.2; }
      .device { position: absolute; left: 50%; top: 470px; margin-left: -325px; width: 650px; height: 1406px; border-radius: 86px; background: #05080f; padding: 18px;
        box-shadow: 0 0 0 4px #27324a, 0 50px 120px rgba(0,0,0,.6), 0 0 160px rgba(59,130,246,.18); }
      .device.android { border-radius: 70px; }
      .screen { position: relative; width: 100%; height: 100%; border-radius: 70px; overflow: hidden; background: #0f172a; font-family: -apple-system, "SF Pro Text", "Segoe UI", Roboto, Arial, sans-serif; }
      .android .screen { border-radius: 54px; font-family: Roboto, "Segoe UI", Arial, sans-serif; }
`;
  const deskCss = `
      .cap { position: absolute; left: 70px; top: 0; bottom: 0; width: 570px; display: flex; flex-direction: column; justify-content: center; text-align: left; }
      .cap .step { font-size: 30px; margin-bottom: 18px; }
      .cap .txt { font-size: 60px; }
      .cap .txt b { color: #fbbf24; }
      .cap .sub { margin-top: 20px; font-size: 32px; line-height: 1.25; }
      .device { position: absolute; left: 700px; top: 160px; width: 1180px; height: 760px; border-radius: 26px; background: #05080f; padding: 14px;
        box-shadow: 0 0 0 3px #27324a, 0 40px 100px rgba(0,0,0,.6), 0 0 140px rgba(59,130,246,.16); }
      .screen { position: relative; width: 1152px; height: 720px; border-radius: 14px; overflow: hidden; background: #0f172a; font-family: "Segoe UI", Arial, sans-serif; }
      #cam { position: absolute; left: 0; top: 0; width: 1152px; height: 720px; transform-origin: 0 0; }
      .device-stand { position: absolute; left: 1140px; top: 920px; width: 260px; height: 22px; margin-left: 0; background: linear-gradient(#1c2538, #0b1220); border-radius: 0 0 24px 24px; box-shadow: 0 0 0 2px #27324a; }
`;
  return base + (phone ? phoneCss : deskCss) + (S.css || '');
}

function build(name) {
  const S = require('./spec-' + name + '.cjs');
  const out = path.join(__dirname, '..', 'instalar-' + name);
  fs.mkdirSync(path.join(out, 'assets'), { recursive: true });

  // ---- numeración de pasos ----
  const numbered = S.steps.filter((s) => s.num !== false);
  let k = 0;
  const capsHtml = S.steps.map((s) => {
    let label = s.label || '';
    if (s.num !== false) { k += 1; label = `Paso ${k} de ${numbered.length}`; }
    return `        <div class="cap clip" data-start="${num(s.a)}" data-duration="${num(s.b - s.a)}" data-track-index="3"><div class="step">${label}</div><div class="txt">${s.t}</div>${s.s ? `<div class="sub">${s.s}</div>` : ''}</div>`;
  }).join('\n');

  // Las capas se funden entre sí (fundido cruzado): se marcan para que «check» no lo tome por un solape.
  const layersHtml = S.layers
    .map((l) => `          ${l.html.replace(/<div id="(L-[^"]+)" class="layer/, '<div id="$1" data-layout-allow-overlap data-layout-allow-occlusion class="layer')}`)
    .join('\n');

  // ---- elementos de efectos ----
  const taps = S.taps || [], marks = S.marks || [], swipes = S.swipes || [], pills = S.pills || [];
  const phone = S.kind === 'phone';
  let fx = '';
  taps.forEach((t, i) => { fx += phone
    ? `          <div id="tap${i}" class="tap" style="left:${t.x}px;top:${t.y}px"></div>\n`
    : `          <div id="rip${i}" class="ripple" style="left:${t.x}px;top:${t.y}px"></div>\n`; });
  marks.forEach((m, i) => { fx += `          <div id="mk${i}" class="mark" style="left:${m.x}px;top:${m.y}px;width:${m.w}px;height:${m.h}px;${m.r != null ? `border-radius:${m.r}px;` : ''}"></div>\n`; });
  swipes.forEach((s, i) => { fx += `          <div id="sw${i}" class="swipe" style="left:${s.x1}px;top:${s.y1}px"></div>\n`; });
  pills.forEach((p, i) => { fx += `          <div id="pl${i}" class="label-pill" style="left:${p.x}px;top:${p.y}px">${p.t}</div>\n`; });
  if (!phone) fx += `          <svg id="cursor" class="cursor" viewBox="0 0 24 24"><path d="M4 2l15 9-6.5 1.5L16 20l-3 1.2-3.5-7.6L4 18z" fill="#fff" stroke="#111" stroke-width="1.4" stroke-linejoin="round"/></svg>\n`;

  const body = phone
    ? `          <div class="device ${S.device || ''}"><div class="screen">\n${layersHtml}\n${fx}          </div></div>`
    : `          <div class="device"><div class="screen"><div id="cam">\n${layersHtml}\n${fx}          </div></div></div>\n          <div class="device-stand"></div>`;

  // ---- timeline ----
  const [iA, iB] = S.introT, [oA, oB] = S.outroT;
  let js = '';
  js += `const tl = gsap.timeline({ paused: true });\n`;
  js += `const FADE = 0.3;\n`;
  js += `tl.fromTo("#intro img, #intro .big, #intro .mid", { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.7, stagger: 0.18 }, ${num(iA + 0.1)});\n`;
  js += `tl.to("#intro", { opacity: 0, duration: 0.3 }, ${num(iB - 0.35)});\n`;
  js += `tl.fromTo("#outro img, #outro .big, #outro .mid", { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.7, stagger: 0.18 }, ${num(oA + 0.2)});\n`;
  js += `tl.fromTo(".device${phone ? '' : ', .device-stand'}", { y: 120, opacity: 0 }, { y: 0, opacity: 1, duration: 0.8, ease: "power3.out" }, ${num(iB)});\n`;
  js += `tl.to(".device${phone ? '' : ', .device-stand'}", { opacity: 0, duration: 0.4 }, ${num(oA - 0.4)});\n`;
  js += `document.querySelectorAll(".cap").forEach((c) => {
        const s = parseFloat(c.dataset.start), d = parseFloat(c.dataset.duration);
        tl.fromTo(c.children, { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 0.5, stagger: 0.1, ease: "power2.out" }, s + 0.05);
        tl.to(c.children, { opacity: 0, y: -20, duration: 0.3 }, s + d - 0.35);
      });\n`;
  // capas
  S.layers.forEach((l) => {
    (l.show || []).forEach(([a, b]) => {
      js += `tl.fromTo("#${l.id}", { opacity: 0 }, { opacity: 1, duration: ${l.fade != null ? l.fade : 0.3} }, ${num(a)});\n`;
      js += `tl.to("#${l.id}", { opacity: 0, duration: ${l.fade != null ? l.fade : 0.3} }, ${num(b)});\n`;
    });
  });
  // marcas
  marks.forEach((m, i) => {
    const n = Math.max(1, Math.floor((m.b - m.a - 0.6) / 0.5));
    js += `tl.fromTo("#mk${i}", { opacity: 0, scale: 1.25 }, { opacity: 1, scale: 1, duration: 0.4, ease: "power2.out" }, ${num(m.a)});\n`;
    js += `tl.to("#mk${i}", { scale: 1.06, duration: 0.5, yoyo: true, repeat: ${n % 2 ? n : n + 1}, ease: "sine.inOut" }, ${num(m.a + 0.5)});\n`;
    js += `tl.to("#mk${i}", { opacity: 0, duration: 0.3 }, ${num(m.b)});\n`;
  });
  pills.forEach((p, i) => {
    js += `tl.fromTo("#pl${i}", { opacity: 0, y: 12 }, { opacity: 1, y: 0, duration: 0.35 }, ${num(p.a)});\n`;
    js += `tl.to("#pl${i}", { opacity: 0, duration: 0.3 }, ${num(p.b)});\n`;
  });
  // toques / clics
  if (phone) {
    taps.forEach((t, i) => {
      js += `tl.fromTo("#tap${i}", { opacity: 0, scale: 1.7 }, { opacity: 1, scale: 1, duration: 0.3, ease: "power2.out" }, ${num(t.t)});\n`;
      js += `tl.to("#tap${i}", { opacity: 0, scale: 0.8, duration: 0.35 }, ${num(t.t + 0.5)});\n`;
    });
  } else {
    const cs = S.cursorStart || { x: 900, y: 600 };
    js += `gsap.set("#cursor", { x: ${cs.x}, y: ${cs.y} });\n`;
    let first = true;
    taps.forEach((t, i) => {
      const mv = Math.max(0.5, t.move || 0.9);
      if (first) { js += `tl.fromTo("#cursor", { opacity: 0 }, { opacity: 1, duration: 0.3 }, ${num(Math.max(0, t.t - mv - 0.1))});\n`; first = false; }
      js += `tl.to("#cursor", { x: ${t.x - 6}, y: ${t.y - 4}, duration: ${num(mv - 0.15)}, ease: "power2.inOut" }, ${num(t.t - mv)});\n`;
      js += `tl.to("#cursor", { scale: 0.85, duration: 0.1 }, ${num(t.t - 0.05)});\n`;
      js += `tl.to("#cursor", { scale: 1, duration: 0.15 }, ${num(t.t + 0.08)});\n`;
      js += `tl.fromTo("#rip${i}", { opacity: 1, scale: 0.3 }, { opacity: 0, scale: 1.6, duration: 0.6, ease: "power2.out", immediateRender: false }, ${num(t.t)});\n`;
    });
    if (S.cursorHide) js += `tl.to("#cursor", { opacity: 0, duration: 0.3 }, ${num(S.cursorHide)});\n`;
  }
  // deslizamientos del dedo
  swipes.forEach((s, i) => {
    js += `tl.fromTo("#sw${i}", { opacity: 0, scale: 1.5, x: 0, y: 0 }, { opacity: 1, scale: 1, duration: 0.25 }, ${num(s.a)});\n`;
    js += `tl.to("#sw${i}", { x: ${s.x2 - s.x1}, y: ${s.y2 - s.y1}, duration: ${num(s.b - s.a - 0.3)}, ease: "power2.inOut" }, ${num(s.a + 0.2)});\n`;
    js += `tl.to("#sw${i}", { opacity: 0, scale: 0.8, duration: 0.25 }, ${num(s.b - 0.1)});\n`;
  });
  // zooms (solo escritorio)
  const zs = [...(S.zooms || [])].sort((p, q) => p.a - q.a);
  zs.forEach((z, zi) => {
    const cx = 576, cy = 360, SW = 1152, SH = 720;
    const tx = Math.min(0, Math.max(SW * (1 - z.s), cx - z.s * z.fx)); // la cámara no se sale de la pantalla
    const ty = Math.min(0, Math.max(SH * (1 - z.s), cy - z.s * z.fy));
    js += `tl.to("#cam", { scale: ${z.s}, x: ${num(tx)}, y: ${num(ty)}, duration: 0.9, ease: "power2.inOut" }, ${num(z.a)});\n`;
    const nxt = zs[zi + 1];
    if (!nxt || nxt.a - z.b > 1.2) js += `tl.to("#cam", { scale: 1, x: 0, y: 0, duration: 0.8, ease: "power2.inOut" }, ${num(z.b)});\n`;
  });
  js += (S.tl || '') + '\n';
  js += `window.__timelines["main"] = tl;\ntl.seek(0);\n`;

  const html = `<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=${S.W}, height=${S.H}" />
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.14.2/dist/gsap.min.js"></script>
    <style>${css(S)}    </style>
  </head>
  <body>
    <div id="root" data-composition-id="main" data-start="0" data-duration="${num(S.D)}" data-width="${S.W}" data-height="${S.H}">

      <div id="intro" class="center clip" data-start="${num(iA)}" data-duration="${num(iB - iA)}" data-track-index="1">
        <img src="assets/logo.webp" alt="JP Preparation" />
        <div class="big">${S.intro.title}</div>
        <div class="mid">${S.intro.sub}</div>
      </div>

      <div id="stage" class="clip" data-start="${num(iB)}" data-duration="${num(oA - iB)}" data-track-index="2" style="position:absolute;inset:0">
${capsHtml}

${body}
      </div>

      <div id="outro" class="center clip" data-start="${num(oA)}" data-duration="${num(oB - oA)}" data-track-index="1">
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

  // assets
  Object.entries(S.assets || {}).forEach(([dest, src]) => {
    fs.copyFileSync(path.join(__dirname, 'assets', src), path.join(out, 'assets', dest));
  });
  fs.copyFileSync(path.join(__dirname, 'assets', 'logo.webp'), path.join(out, 'assets', 'logo.webp'));

  // ficheros de proyecto (mismos que el vídeo «cancelar-sesion»)
  const id = 'instalar-' + name;
  fs.writeFileSync(path.join(out, 'package.json'), JSON.stringify({
    name: id, private: true, type: 'module',
    scripts: {
      dev: `npx --yes hyperframes@${HF_VERSION} preview`,
      check: `npx --yes hyperframes@${HF_VERSION} check`,
      render: `npx --yes hyperframes@${HF_VERSION} render`,
      publish: `npx --yes hyperframes@${HF_VERSION} publish`,
    },
  }, null, 2) + '\n');
  fs.writeFileSync(path.join(out, 'meta.json'), JSON.stringify({ id, name: id, createdAt: '2026-10-17T00:00:00.000Z' }, null, 2));
  const refDir = path.join(__dirname, '..', 'cancelar-sesion');
  ['hyperframes.json', 'AGENTS.md', 'CLAUDE.md'].forEach((f) => {
    if (fs.existsSync(path.join(refDir, f))) fs.copyFileSync(path.join(refDir, f), path.join(out, f));
  });
  console.log('OK →', path.relative(process.cwd(), out));
}

names.forEach(build);
