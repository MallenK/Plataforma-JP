/**
 * Test de public/assets/js/install-guide.js: detección del dispositivo para la guía de instalación.
 *   node tests/js/install-guide.test.cjs     (sin dependencias)
 */
'use strict';
const path = require('path');
const { detect } = require(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'install-guide.js'));

let failed = 0;
const ok = (c, l) => { console.log((c ? '  OK   ' : '  FAIL ') + l); if (!c) failed++; };

const UA = {
    iphoneSafari: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
    iphoneChrome: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/124.0.0.0 Mobile/15E148 Safari/604.1',
    iphoneFirefox: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/124.0 Mobile/15E148 Safari/605.1.15',
    iphoneInstagram: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 320.0.0.12.109',
    iphoneWebview: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
    ipadSafari: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
    macSafari: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
    androidChrome: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
    winChrome: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    macChrome: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
};

ok(detect({ ua: UA.iphoneSafari }) === 'ios-safari', 'iPhone + Safari → pasos de Safari');
ok(detect({ ua: UA.iphoneChrome }) === 'ios-other', 'iPhone + Chrome → usar Safari');
ok(detect({ ua: UA.iphoneFirefox }) === 'ios-other', 'iPhone + Firefox → usar Safari');
ok(detect({ ua: UA.iphoneInstagram }) === 'ios-inapp', 'iPhone + navegador de Instagram → abrir en Safari');
ok(detect({ ua: UA.iphoneWebview }) === 'ios-inapp', 'iPhone + WebView sin token Safari → abrir en Safari');
ok(detect({ ua: UA.ipadSafari, touchPoints: 5 }) === 'ios-safari', 'iPad (se identifica como Mac) + táctil → Safari iOS');
ok(detect({ ua: UA.macSafari, touchPoints: 0 }) === 'mac-safari', 'Mac + Safari → Añadir al Dock');
ok(detect({ ua: UA.macChrome, touchPoints: 0 }) === 'desktop', 'Mac + Chrome → escritorio');
ok(detect({ ua: UA.androidChrome }) === 'android', 'Android');
ok(detect({ ua: UA.winChrome }) === 'desktop', 'Windows + Chrome → escritorio');
ok(detect({ ua: UA.iphoneSafari, standalone: true }) === 'installed', 'ya instalada (standalone) gana a todo');
ok(detect({}) === 'desktop', 'sin datos → escritorio, sin romper');

if (failed) { console.error('\n' + failed + ' fallo(s)'); process.exit(1); }
console.log('\nTodo OK');
