"""Capturas del manual de Finanzas desde la app local (jp_prodlike, datos ficticios).
Uso: python shots.py <carpeta_salida_png> <carpeta_trabajo>"""
import http.cookiejar, os, re, subprocess, sys, time, urllib.parse, urllib.request
from PIL import Image

OUT, WORK = sys.argv[1], sys.argv[2]
os.makedirs(OUT, exist_ok=True); os.makedirs(WORK, exist_ok=True)
B = "http://localhost:8080"
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
def get(path):
    return op.open(B + path).read().decode("utf-8")
html = get("/login")
tok = re.search(r"hash\s*:\s*['\"]([^'\"]+)['\"]", html).group(1)
data = urllib.parse.urlencode({"jp_csrf_token": tok, "email": "superadmin.1@prodlike.local", "password": "Test1234!"}).encode()
req = urllib.request.Request(B + "/login", data=data, headers={"X-Requested-With": "XMLHttpRequest"})
print("login", op.open(req).status)

TOOLTIP = """<script>window.addEventListener('load',function(){setTimeout(function(){
var el=document.querySelectorAll('.metric-label .fin-help')[1]; if(el&&window.bootstrap){bootstrap.Tooltip.getOrCreateInstance(el).show();}},900);});</script>"""
QUIET = """<script>try{['superadmin','admin','coach','staff','player','alumno'].forEach(function(r){localStorage.setItem('jp_tutorial_seen_'+r,'1');});}catch(e){}</script>
<style>.toastify,#push-banner,#push-card{display:none!important}</style>"""

pages = [
    ("resumen",      "/finanzas/resumen?mes=2026-10",      1500, True),
    ("movimientos",  "/finanzas/movimientos?mes=2026-10",  1250, False),
    ("cobros",       "/finanzas/cobros?mes=2026-10",       1250, False),
    ("gastos",       "/finanzas/gastos?mes=2026-10",       1150, False),
    ("alumnos",      "/finanzas/alumnos",                  1100, False),
    ("cuenta",       "/finanzas/alumnos/1013",             1450, False),
    ("entrenadores", "/finanzas/entrenadores?mes=2026-09", 950,  False),
    ("analisis",     "/finanzas/analisis?mes=2026-09",     1150, False),
    ("revision",     "/finanzas/revision",                 1150, False),
]
for name, path, h, tip in pages:
    page = get(path)
    page = page.replace("<head>", "<head>" + QUIET, 1) if "<head>" in page else QUIET + page
    if tip:
        page = page.replace("</body>", TOOLTIP + "</body>", 1)
    f = os.path.join(WORK, name + ".html")
    open(f, "w", encoding="utf-8").write(page)
    png = os.path.join(WORK, name + "_raw.png")
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run",
                    "--window-size=1440,%d" % h, "--virtual-time-budget=6000",
                    "--screenshot=" + png, "file:///" + f.replace("\\", "/")],
                   check=True, capture_output=True, timeout=120)
    im = Image.open(png).convert("RGB")
    # Recorta el menú lateral (240 px) para que la captura se centre en Finanzas.
    im = im.crop((240, 0, im.width, im.height))
    im.save(os.path.join(OUT, name + ".png"), optimize=True)
    print(name, im.size)
