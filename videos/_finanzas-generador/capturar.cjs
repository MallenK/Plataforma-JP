/**
 * Captura con Playwright las pantallas REALES de la plataforma (Docker local, BD jp_prodlike)
 * para los vídeos tutoriales de Finanzas, y mide dónde está cada elemento a señalar.
 *
 *   node capturar.cjs [general|vender-cobrar|...]     (sin argumento: todos)
 *
 * Escribe shots/<video>/<escena>.png (1440×900) y shots/<video>/marks.json.
 * No envía formularios: rellena y fotografía, así que no cambia datos (salvo los de ejemplo
 * que una spec cree en su `prepare` y borre en su `cleanup`).
 */
const fs = require('fs');
const path = require('path');
const { execSync, spawnSync } = require('child_process');

const GLOBAL = execSync('npm root -g').toString().trim();
const { chromium } = require(path.join(GLOBAL, 'playwright'));

const BASE = process.env.JP_BASE || 'http://localhost:8080';
const USER = process.env.JP_USER || 'superadmin.1@prodlike.local';
const PASS = process.env.JP_PASS || 'Test1234!';
const W = 1440, H = 900;

const only = process.argv[2];
const specs = fs.readdirSync(path.join(__dirname, 'specs'))
  .filter((f) => f.endsWith('.cjs')).map((f) => f.replace(/\.cjs$/, ''))
  .filter((n) => !only || n === only);

// Oculta avisos que ensucian la captura (tutorial inicial, banner push, toasts).
const QUIET_JS = `try{['superadmin','admin','coach','staff','player','alumno'].forEach(function(r){localStorage.setItem('jp_tutorial_seen_'+r,'1');});}catch(e){}`;
const QUIET_CSS = '.toastify,#push-banner,#push-card,.jp-toast,#debug-icon,#debug-bar,#toolbarContainer{display:none!important} *{caret-color:transparent!important}';

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const ctx = await browser.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 1, locale: 'es-ES' });
  await ctx.addInitScript(QUIET_JS);
  const page = await ctx.newPage();

  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('#login-email', USER);
  await page.fill('#login-password', PASS);
  await Promise.all([page.waitForURL((u) => !/\/login/.test(u.pathname), { timeout: 20000 }), page.click('.login-btn-submit')]);
  console.log('login ok');

  for (const name of specs) {
    const S = require('./specs/' + name + '.cjs');
    const dir = path.join(__dirname, 'shots', name);
    fs.mkdirSync(dir, { recursive: true });
    const out = {};
    if (S.prepare) await S.prepare({ execSql });
    try {
      for (const sc of S.scenes) {
        // `deep`: sección situada más abajo → ventana muy alta y se recorta alrededor (sin desplazar la página).
        await page.setViewportSize({ width: W, height: sc.deep ? 3400 : H });
        await page.goto(BASE + sc.url, { waitUntil: 'networkidle' });
        await page.addStyleTag({ content: QUIET_CSS });
        await page.waitForTimeout(sc.wait || 900);
        if (sc.setup) await sc.setup(page);
        await page.waitForTimeout(350);
        const marks = [];
        for (const m of sc.marks || []) {
          let box = null;
          const sels = m.union || [m.sel];
          const boxes = [];
          for (const q of sels) {
            try { const b = await page.locator(q).first().boundingBox({ timeout: 4000 }); if (b) boxes.push(b); } catch (e) { /* no existe */ }
          }
          if (boxes.length) {
            const x0 = Math.min(...boxes.map((b) => b.x)), y0 = Math.min(...boxes.map((b) => b.y));
            const x1 = Math.max(...boxes.map((b) => b.x + b.width)), y1 = Math.max(...boxes.map((b) => b.y + b.height));
            box = { x: x0, y: y0, width: x1 - x0, height: y1 - y0 };
          }
          if (!box) { console.warn('  ! sin elemento:', name, sc.id, sels.join(' + ')); continue; }
          marks.push({ ...m, x: box.x, y: box.y, w: box.width, h: box.height });
        }
        let w0 = W;
        if (sc.deep) {
          const b = await page.locator(sc.deep).first().boundingBox();
          const cw = 1200, ch = 750;
          // el recorte no puede pasarse del final del contenido (si no, la imagen sale más baja y se deforma)
          const bottom = await page.evaluate(() => Math.max(...[...document.querySelectorAll('.card-jp, .fin-manual')].map((e) => e.getBoundingClientRect().bottom + window.scrollY)));
          const clip = { x: 240, y: Math.max(0, Math.round(Math.min(b.y - 90, bottom + 24 - ch))), width: cw, height: ch };
          await page.screenshot({ path: path.join(dir, sc.id + '.png'), clip });
          marks.forEach((m) => { m.x -= clip.x; m.y -= clip.y; });
          w0 = cw;
        } else {
          await page.screenshot({ path: path.join(dir, sc.id + '.png') });
        }
        out[sc.id] = { marks, w0 };
        console.log(name, sc.id, marks.length + '/' + (sc.marks || []).length + ' marcas');
      }
    } finally {
      if (S.cleanup) await S.cleanup({ execSql });
    }
    fs.writeFileSync(path.join(dir, 'marks.json'), JSON.stringify(out, null, 2));
  }
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });

/** Ejecuta SQL en la BD local de Docker (solo para datos de ejemplo de una spec). El SQL viaja como argumento ($0), sin pasar por el shell. */
function execSql(sql) {
  const r = spawnSync('docker', ['exec', 'jp_db', 'sh', '-c', 'mysql --default-character-set=utf8mb4 -uroot -p"$MYSQL_ROOT_PASSWORD" jp_prodlike -N -e "$0"', sql], { encoding: 'utf8' });
  if (r.status !== 0) throw new Error('SQL falló: ' + sql.slice(0, 80) + ' → ' + (r.stderr || '').slice(0, 200));
  return r.stdout;
}
