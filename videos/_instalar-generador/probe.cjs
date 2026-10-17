/**
 * Mide la posición de todos los elementos con id="p-…" dentro de .screen y la guarda en probe-<nombre>.json.
 *   node probe.cjs iphone      (después: node build.cjs iphone para que use las posiciones)
 * Usa el Chrome que ya baja HyperFrames y el Playwright global.
 */
const fs = require('fs');
const path = require('path');
const name = process.argv[2];
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'C:/Users/Mallen/AppData/Roaming/npm/node_modules/playwright');
const EXE = process.env.HF_CHROME || 'C:/Users/Mallen/.cache/hyperframes/chrome/chrome-headless-shell/win64-152.0.7977.30/chrome-headless-shell-win64/chrome-headless-shell.exe';
const spec = require('./spec-' + name + '.cjs');

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const p = await b.newPage({ viewport: { width: spec.W, height: spec.H } });
  const url = 'file:///' + path.join(__dirname, '..', 'instalar-' + name, 'index.html').replace(/\\/g, '/');
  await p.goto(url, { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  await p.evaluate(() => { const t = window.__timelines.main; t.seek(t.duration()); });
  await p.waitForTimeout(200);
  const res = await p.evaluate(() => {
    const scr = document.querySelector('.screen');
    const cam = document.getElementById('cam');
    // medimos sin transformaciones: devolvemos el cam a su estado base
    const out = {};
    const sr = scr.getBoundingClientRect();
    const scale = sr.width / scr.offsetWidth;
    document.querySelectorAll('.screen [id^="p-"]').forEach((el) => {
      const r = el.getBoundingClientRect();
      out[el.id] = { x: (r.left - sr.left) / scale, y: (r.top - sr.top) / scale, w: r.width / scale, h: r.height / scale };
    });
    return out;
  });
  fs.writeFileSync(path.join(__dirname, `probe-${name}.json`), JSON.stringify(res, null, 1));
  console.log(Object.keys(res).length, 'elementos →', `probe-${name}.json`);
  await b.close();
})().catch((e) => { console.error(e); process.exit(1); });
