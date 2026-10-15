"""Guía para alumnos y familias: cómo avisar de que no puedes ir a una clase.
Capturas en móvil (500 px, el mínimo de Chrome) de la app local (jp_prodlike, datos ficticios) y PDF final.

Uso: python -I -X utf8 docs/alumno/guia/generar_guia.py <carpeta_pdf> <carpeta_trabajo>
Requisitos: Docker levantado, Google Chrome, Python con Pillow y PyMuPDF.
"""
import base64, http.cookiejar, os, re, subprocess, sys, urllib.parse, urllib.request
from PIL import Image
import pymupdf as fitz

OUT_DIR, WORK = sys.argv[1], sys.argv[2]
os.makedirs(OUT_DIR, exist_ok=True); os.makedirs(WORK, exist_ok=True)
B = "http://localhost:8080"
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
EMAIL, PWD, PLAYER_ID = "alumno.1096@prodlike.local", "Test1234!", 1096
MYSQL = ["docker", "exec", "jp_db", "mysql", "-uroot", "-pQwaszx12345_", "jp_prodlike", "-N", "-e"]

def sql(q):
    return subprocess.run(MYSQL + [q], capture_output=True, text=True, encoding="utf-8").stdout.strip()

cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
def get(path): return op.open(B + path).read().decode("utf-8")
tok = re.search(r"hash\s*:\s*['\"]([^'\"]+)['\"]", get("/login")).group(1)
op.open(urllib.request.Request(B + "/login", data=urllib.parse.urlencode(
    {"jp_csrf_token": tok, "email": EMAIL, "password": PWD}).encode(), headers={"X-Requested-With": "XMLHttpRequest"}))

# Dos clases futuras de la alumna: una con tiempo y otra para avisar tarde (la de hoy).
rows = sql("select cs.id, cs.session_date from class_sessions cs join class_session_players p on p.session_id=cs.id "
           "where p.user_id=%d and cs.status='scheduled' and cs.session_date>=CURDATE() order by cs.session_date, cs.start_time" % PLAYER_ID).splitlines()
ids = [r.split("\t")[0] for r in rows]
S_OK, S_NOTICED = ids[1], ids[2]   # con tiempo
S_LATE = ids[0]                    # la más próxima (aviso tardío si es hoy/mañana)

QUIET = """<script>try{['player','alumno'].forEach(function(r){localStorage.setItem('jp_tutorial_seen_'+r,'1');});}catch(e){}</script>
<style>.toastify,#push-banner,#push-card{display:none!important} html{scroll-behavior:auto}</style>"""

def shot(name, path, height, js="", card_only=True):
    page = get(path)
    page = page.replace("<head>", "<head>" + QUIET + ("<style>.row.g-3{display:none!important}</style>" if card_only else ""), 1)
    if js:
        page = page.replace("</body>", '<script>window.addEventListener("load",function(){setTimeout(function(){%s},400)});</script></body>' % js, 1)
    f = os.path.join(WORK, name + ".html"); open(f, "w", encoding="utf-8").write(page)
    raw = os.path.join(WORK, name + "_raw.png")
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run",
                    "--force-device-scale-factor=2", "--window-size=500,%d" % height, "--virtual-time-budget=6000",
                    "--screenshot=" + raw, "file:///" + f.replace("\\", "/")], check=True, capture_output=True, timeout=120)
    im = Image.open(raw).convert("RGB")
    # recorta el blanco sobrante de abajo (color de fondo = esquina inferior izquierda)
    bg = im.getpixel((2, im.height - 2)); y = im.height - 1
    while y > 300 and all(im.getpixel((x, y)) == bg for x in range(0, im.width, 23)): y -= 1
    im = im.crop((0, 0, im.width, min(im.height, y + 40)))
    out = os.path.join(WORK, name + ".png"); im.save(out, optimize=True)
    return out

# El segundo ancho (móvil) oculta el menú lateral: el panel «Tu clase» queda arriba.
CHECK = "var r=document.querySelector('.mc-chip input[value=enfermo]'); if(r){r.checked=true;}"
OPEN_CANCEL = "var d=document.querySelector('.mc-cancel'); if(d){d.open=true;}" + CHECK + \
              "var t=document.querySelector('.mc-cancel textarea'); if(t){t.value='Tiene fiebre desde ayer';}"
OPEN_MSG = "var d=document.querySelector('.mc-msg'); if(d){d.open=true;} var t=document.querySelector('.mc-msg textarea'); if(t){t.value='Llegaré 10 minutos tarde.';}"

imgs = {}
imgs["clase"]    = shot("clase",    "/clases/" + S_OK, 900)
imgs["motivo"]   = shot("motivo",   "/clases/" + S_OK, 1500, OPEN_CANCEL)
imgs["tardio"]   = shot("tardio",   "/clases/" + S_LATE, 1500, OPEN_CANCEL)
# aviso enviado: se marca temporalmente en la BD local y se deshace
sql("update class_session_players set student_note='Enfermo o lesionado: tiene fiebre desde ayer', student_noted_at=NOW() where user_id=%d and session_id=%s" % (PLAYER_ID, S_NOTICED))
try:
    imgs["enviado"] = shot("enviado", "/clases/" + S_NOTICED, 900)
finally:
    sql("update class_session_players set student_note=NULL, student_noted_at=NULL where user_id=%d and session_id=%s" % (PLAYER_ID, S_NOTICED))
imgs["mensaje"]  = shot("mensaje",  "/clases/" + S_OK, 1500, OPEN_MSG)
imgs["ayuda"]    = shot("ayuda",    "/ayuda", 1250, card_only=False)

def b64(p): return "data:image/png;base64," + base64.b64encode(open(p, "rb").read()).decode()

def step(n, title, body, key, note=""):
    return f"""<section class="step"><div class="txt"><div class="num">{n}</div><h2>{title}</h2>{body}{('<div class="note">'+note+'</div>') if note else ''}</div>
<div class="shot"><img src="{b64(imgs[key])}"></div></section>"""

html = f"""<!doctype html><html lang="es"><meta charset="utf-8"><title>Guía para familias — JP Preparation</title>
<style>
@page {{ size:A4; margin:16mm 14mm }}
*{{box-sizing:border-box}} body{{font-family:Segoe UI,Arial,sans-serif;color:#1e293b;font-size:14.5px;line-height:1.5;margin:0}}
.cover{{height:255mm;display:flex;flex-direction:column;justify-content:center;page-break-after:always}}
.cover small{{letter-spacing:.14em;text-transform:uppercase;color:#64748b;font-weight:700}}
.cover h1{{font-size:38px;line-height:1.1;margin:10px 0 14px;color:#0f172a}} .cover p{{font-size:17px;color:#475569;max-width:480px}}
.cover .box{{margin-top:26px;background:#f1f5f9;border-radius:14px;padding:16px 20px;max-width:480px}}
.step{{display:flex;gap:22px;align-items:flex-start;page-break-inside:avoid;margin:0 0 26px;padding-bottom:22px;border-bottom:1px solid #e2e8f0}}
.txt{{flex:1}} .shot{{width:230px;flex:none}} .shot img{{width:100%;border:1px solid #cbd5e1;border-radius:14px}}
.num{{width:34px;height:34px;border-radius:50%;background:#3b82f6;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:17px}}
h2{{font-size:21px;margin:8px 0 6px;color:#0f172a}} p{{margin:6px 0}}
.note{{background:#fffbeb;color:#92400e;border-radius:10px;padding:9px 12px;margin-top:10px;font-size:13.5px}}
.ok{{background:#ecfdf5;color:#065f46;border-radius:10px;padding:9px 12px;margin-top:10px;font-size:13.5px}}
b{{color:#0f172a}} .foot{{margin-top:8px;color:#64748b;font-size:13px}}
</style>
<div class="cover"><small>JP Preparation</small><h1>Cómo avisar de que no puedes ir a una clase</h1>
<p>Guía paso a paso, con capturas de la app, para alumnos y familias.</p>
<div class="box"><b>En resumen:</b> entra en la clase, pulsa el botón rojo <b>«No puedo ir»</b>, elige el motivo y confirma. Avisa con al menos <b>24 horas</b> de antelación y no se descuenta nada de tu bono.</div></div>

{step(1, "Abre tu clase", "<p>Entra en la app, pulsa <b>Clases</b> en el menú y toca la clase a la que no puedes ir.</p><p>Arriba verás <b>tu clase</b>: el día, la hora, el campo y tu entrenador.</p>", "clase")}
{step(2, "Pulsa «No puedo ir»", "<p>Pulsa el botón rojo. Elige el <b>motivo</b> (enfermo, colegio, viaje u otro) y, si quieres, escribe una frase.</p><p>Debajo verás <b>qué pasará</b> si avisas ahora, antes de confirmar.</p>", "motivo", "<b>Avisar con tiempo</b> (24 h antes o más): no se descuenta ninguna sesión.")}
{step(3, "Si avisas tarde", "<p>Si faltan menos de 24 horas, la app te lo dice en naranja antes de confirmar.</p><p>Se avisa también a la administración y <b>se descuenta una sesión de tu bono</b> hasta que tu entrenador cierre la clase y decida.</p>", "tardio", "Tú decides: puedes volver atrás con «Volver» si no quieres avisar.")}
{step(4, "Comprueba que se ha enviado", "<p>Cuando todo ha ido bien, la clase muestra <b>«Aviso enviado»</b> en verde (o naranja si fue tardío).</p><p>Tu entrenador y la administración ya lo saben.</p>", "enviado", "Un aviso enviado <b>no se puede deshacer</b>. Si te equivocas o puedes ir al final, escribe un mensaje (paso 5).")}
{step(5, "Decirle algo a tu entrenador", "<p>Pulsa <b>«Decirle algo a mi entrenador»</b> para escribir un mensaje: llegarás tarde, vas con molestias, tienes una duda o ha cambiado tu plan.</p><p>Lo ven tu entrenador y la administración. No cambia tu asistencia ni tu bono.</p>", "mensaje", "¿Una lesión que deban saber siempre? Añádela en <b>Mi ficha › Notas médicas</b>.")}
{step(6, "¿Dudas? Mira la Ayuda", "<p>En el menú tienes <b>Ayuda</b>, con las preguntas más habituales explicadas paso a paso.</p>", "ayuda")}
<p class="foot">La asistencia final de cada clase la decide siempre el entrenador o la administración.</p>
</html>"""

src = os.path.join(WORK, "guia.html"); open(src, "w", encoding="utf-8").write(html)
raw = os.path.join(WORK, "guia_raw.pdf")
subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--no-first-run", "--no-pdf-header-footer",
                "--virtual-time-budget=8000", "--print-to-pdf=" + raw, "file:///" + src.replace("\\", "/")],
               check=True, capture_output=True, timeout=180)
final = os.path.join(OUT_DIR, "Guia-avisar-ausencia-JP-Preparation.pdf")
doc = fitz.open(raw)
for i, pg in enumerate(doc):
    if i:
        r = pg.rect
        pg.insert_text((r.x0 + 40, r.y1 - 22), "Guía para familias · JP Preparation", fontsize=8, color=(0.45, 0.5, 0.58))
        pg.insert_text((r.x1 - 70, r.y1 - 22), "Página %d de %d" % (i + 1, doc.page_count), fontsize=8, color=(0.45, 0.5, 0.58))
doc.save(final, deflate=True)
print("PDF:", final, doc.page_count, "páginas", os.path.getsize(final) // 1024, "KB")
