"""PDF del manual de Finanzas a partir de /finanzas/ayuda/imprimir.
Uso: python gen_pdf.py <dir_capturas_y_salida> <dir_trabajo>"""
import http.cookiejar, os, re, subprocess, sys, urllib.parse, urllib.request
import pymupdf as fitz

IMG_DIR, WORK = sys.argv[1], sys.argv[2]
os.makedirs(WORK, exist_ok=True)
B = "http://localhost:8080"
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
OUT = os.path.join(IMG_DIR, "Manual-Finanzas-JP-Preparation.pdf")

cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
html = op.open(B + "/login").read().decode("utf-8")
tok = re.search(r"hash\s*:\s*['\"]([^'\"]+)['\"]", html).group(1)
data = urllib.parse.urlencode({"jp_csrf_token": tok, "email": "superadmin.1@prodlike.local", "password": "Test1234!"}).encode()
op.open(urllib.request.Request(B + "/login", data=data, headers={"X-Requested-With": "XMLHttpRequest"}))

page = op.open(B + "/finanzas/ayuda/imprimir").read().decode("utf-8")
base_img = "file:///" + IMG_DIR.replace("\\", "/").rstrip("/") + "/"
import html as _h
page = re.sub(r'src="([^"]*)"', lambda m: ('src="' + base_img + re.search(r'([a-z0-9_-]+\.png)$', _h.unescape(m.group(1))).group(1) + '"')
              if re.search(r'/finanzas/ayuda/img/[a-z0-9_-]+\.png$', _h.unescape(m.group(1))) else m.group(0), page)
page = page.replace(' loading="lazy"', '')
n_imgs = page.count(base_img)
src = os.path.join(WORK, "manual.html")
open(src, "w", encoding="utf-8").write(page)

raw = os.path.join(WORK, "manual_raw.pdf")
subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--no-first-run", "--no-pdf-header-footer",
                "--virtual-time-budget=8000", "--print-to-pdf=" + raw, "file:///" + src.replace("\\", "/")],
               check=True, capture_output=True, timeout=180)

doc = fitz.open(raw)
total = doc.page_count
for i, pg in enumerate(doc):
    if i == 0:
        continue  # portada sin pie
    r = pg.rect
    pg.insert_text((r.x0 + 45, r.y1 - 24), "Manual de Finanzas · JP Preparation", fontsize=8, color=(0.45, 0.5, 0.58))
    label = f"Página {i + 1} de {total}"
    w = fitz.get_text_length(label, fontsize=8)
    pg.insert_text((r.x1 - 45 - w, r.y1 - 24), label, fontsize=8, color=(0.45, 0.5, 0.58))
doc.set_metadata({"title": "Manual de uso de Finanzas — JP Preparation", "author": "JP Preparation",
                  "subject": "Plataforma JP Preparation · Finanzas 2.0"})
doc.save(OUT, garbage=4, deflate=True)
print("imágenes enlazadas:", n_imgs, "· páginas:", total, "·", round(os.path.getsize(OUT) / 1024), "KB ->", OUT)
