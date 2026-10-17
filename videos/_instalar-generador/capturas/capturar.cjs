/** Regenera las capturas de la app usadas en los vídeos (réplica de /configuracion/notificaciones + login real en localhost:8080).
 *  node capturar.cjs   (necesita Docker levantado y el Chrome de HyperFrames) */
const { chromium } = require('C:/Users/Mallen/AppData/Roaming/npm/node_modules/playwright');
const EXE = 'C:/Users/Mallen/.cache/hyperframes/chrome/chrome-headless-shell/win64-152.0.7977.30/chrome-headless-shell-win64/chrome-headless-shell.exe';
const path = require('path');
const R = 'file:///' + path.join(__dirname, 'replica.html').replace(/\\/g, '/');
const OUT = path.join(__dirname, '..', 'assets');
(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  // móvil
  let ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true, locale: 'es-ES' });
  let p = await ctx.newPage();
  for (const [name, qs] of [['m-dash','view=dash&safe=1'],['m-menu','view=dash&menu=1&safe=1'],['m-notif-off','view=notif&safe=1'],['m-notif-on','view=notif&on=1&safe=1']]) {
    await p.goto(R + '?' + qs, { waitUntil: 'networkidle' }); await p.waitForTimeout(700);
    await p.screenshot({ path: path.join(OUT, name + '.png') });
  }
  await ctx.close();
  // escritorio
  ctx = await b.newContext({ viewport: { width: 1440, height: 800 }, deviceScaleFactor: 2, locale: 'es-ES' });
  p = await ctx.newPage();
  for (const [name, qs] of [['d-dash','view=dash'],['d-notif-off','view=notif'],['d-notif-on','view=notif&on=1']]) {
    await p.goto(R + '?' + qs, { waitUntil: 'networkidle' }); await p.waitForTimeout(700);
    await p.screenshot({ path: path.join(OUT, name + '.png') });
  }
  await p.goto('http://localhost:8080/login', { waitUntil: 'networkidle' }); await p.waitForTimeout(600);
  await p.screenshot({ path: path.join(OUT, 'd-login.png') });
  await p.fill('#login-email', 'marc.demo@example.test'); await p.fill('#login-password', '123456789');
  await p.screenshot({ path: path.join(OUT, 'd-login-lleno.png') });
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
