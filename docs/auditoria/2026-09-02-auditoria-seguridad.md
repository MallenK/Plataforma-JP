# Auditoría de seguridad — Plataforma JP Preparation

| | |
|---|---|
| **Fecha** | 2026-09-02 |
| **Rama de trabajo** | `auditoria/seguridad-2026-09` |
| **Punto de control (base)** | commit `6e910d0` — estado íntegro de la app antes de tocar nada |
| **`main` de referencia** | `e486316` (sin modificar) |
| **Alcance** | Revisión de todo el código de la aplicación (`app/`, `public/assets/`, config, despliegue). Búsqueda de vulnerabilidades + aplicación de correcciones de bajo riesgo. |
| **Metodología** | Revisión manual de código: rutas y control de acceso, autenticación, subida y servido de archivos, inyección SQL, XSS (servidor y cliente), CSRF, exposición de secretos, configuración de framework y despliegue. |

> ⚠️ Documento sensible: contiene detalle de vulnerabilidades explotables. No publicar fuera del equipo. Está excluido de la imagen Docker (`.dockerignore` ignora `*.md`).

> **Nota de despliegue (2026-09-03).** El trabajo de la auditoría se parte en dos para producción:
> - **Tanda A — `fix/seguridad-rce-uploads` (este PR).** RCE de subida (H1/H2), adjuntos privados fuera del webroot (M1), open-redirect (M3), XSS latentes (L3/L5/L6), `.dockerignore` (M2), `Notificaciones::send` (L8), `robots.txt` (L9), `www→https` (L10), uploads trackeados en git (L12). **No cambia nada visible para el usuario** salvo que reactiva los adjuntos del chat. Sin ventana especial.
> - **Tanda B — pendiente (una noche de bajo tráfico).** `logout` solo POST (L1) y rename de los nombres de CSRF (L2): invalidan los formularios abiertos en el momento del deploy.
> En este documento, L1 y L2 figuran como "hechos en rama" porque el código existe, pero **NO entran en la Tanda A**.

---

## 1. Resumen ejecutivo

La base es **sólida**: CSRF activo globalmente, auto-routing desactivado, contraseñas con bcrypt, login en tiempo constante, tokens de reset hasheados, `esc()` aplicado de forma consistente en las vistas server-rendered, consultas parametrizadas en los modelos y comprobaciones de acceso a nivel de objeto en la mayoría de módulos.

El problema serio está en **tres manejadores de subida de archivos** (`Notificaciones`, `Tickets`, `Mensajes`) que **no validan la extensión final** del archivo y lo guardan **dentro de `public/`**. Con un archivo polyglot (cabecera de imagen válida + código PHP) y nombre `algo.php` se conseguía **ejecución remota de código autenticada**. El caso de `Notificaciones` lo podía disparar **cualquier usuario, incluido un alumno**.

> **Actualización 2026-09-02 (2ª tanda):** tras aprobar el informe se han aplicado también las recomendaciones 1–4 de la sección 4. Ver **§6**. Estados actualizados abajo.

| # | Severidad | Hallazgo | Estado |
|---|-----------|----------|--------|
| **H1** | 🔴 Alta / Crítica | Subida arbitraria de archivos → RCE en `NotificacionesController` y `TicketsController` (sin lista blanca de extensión, guardado en webroot) | ✅ **Corregido** (lista blanca + **uploads movidos fuera de `public/`**, §6) |
| **H2** | 🟠 Media | `MensajesController::handleFileUpload()` llama a un método inexistente (`secureUploadDir`) → adjuntos de chat rotos + endurecimiento de directorio que nunca ocurría | ✅ **Corregido** |
| **M1** | 🟠 Media | Archivos subidos servidos sin cabeceras de seguridad ni control de acceso (servido estático por Apache / `serveFile()` con `exit`) | ✅ **Corregido** — adjuntos privados fuera de webroot y solo por controlador (§6); avatares siguen estáticos pero con lista blanca + `.htaccess` |
| **M2** | 🟠 Media | `deploy/.env` (secretos de PRODUCCIÓN) se copia dentro de la imagen Docker | ✅ **Corregido** (`.dockerignore`) |
| **M3** | 🟡 Media-baja | Open redirect en `DocumentacionController::upload()` (`//evil.com`) | ✅ **Corregido** |
| **L1** | 🟢 Baja | `logout` por GET → cierre de sesión forzado vía CSRF | 🅱️ **Tanda B** — código listo en rama, no en el PR de la Tanda A |
| **L2** | 🟢 Baja | Config CSRF con nombres por defecto (`csrf_test_name`); CLAUDE.md dice `jp_csrf_token` (inexacto) | 🅱️ **Tanda B** — código listo en rama; invalida forms abiertos al desplegar |
| **L3** | 🟢 Baja | `<title>` sin `esc()` en `layouts/base.php` (XSS latente) | ✅ **Corregido** |
| **L4** | 🟢 Baja | SQL construido por interpolación de string en `DocumentService::getAccessibleFolders()` | 📋 Recomendación (hoy no explotable) |
| **L5** | 🟢 Baja | `json_encode` dentro de `<script>` sin `JSON_HEX_TAG` en `clases/create.php` (nombre lo controla el alumno) | ✅ **Corregido** |
| **L6** | 🟢 Baja | `buildAvatarHtml()` (JS de mensajes) no escapa las iniciales en la rama sin imagen | ✅ **Corregido** |
| **L7** | ⚪ Info | DoS por bloqueo de cuenta (quien conoce un email puede bloquearla 5 fallos/15 min) | Aceptado (compromiso documentado) |
| **L8** | 🟢 Baja | `Notificaciones` individual: cualquiera (incl. alumno) puede notificar a cualquiera, sin rate-limit; incoherente con el bloqueo alumno↔alumno de Mensajes | ✅ **Corregido** — regla alumno→no-alumno + rate-limit + `type` estricto (§6) |
| **L9** | ⚪ Info | `robots.txt` permite indexar toda la app (backoffice tras login) | 📋 Recomendación |
| **L10** | ⚪ Info | Redirección `www→no-www` en `public/.htaccess` baja a `http://` | 📋 Recomendación |
| **L11** | ⚪ Info | `.env` de desarrollo en el árbol de trabajo (`CI_ENVIRONMENT = development` + clave Resend). No commiteado. | Vigilar |
| **L12** | 🟡 Media-baja | Ficheros subidos por usuarios **trackeados en git** pese al `.gitignore` (commit "Subida 29/04"): 2 avatares, 1 adjunto de mensaje privado, 2 PDF de carpetas personales/públicas. `.gitignore` no destrackea lo ya trackeado. | 📋 Recomendación (`git rm --cached`) — ver §7 |

---

## 2. Hallazgos detallados

### 🔴 H1 — Subida arbitraria de archivos → RCE autenticada

**Ubicación**
- `app/Controllers/NotificacionesController.php` → `handleFileUpload()` (antes líneas ~249-280)
- `app/Controllers/TicketsController.php` → `handleFileUpload()` (antes líneas ~364-398)

**Descripción**

Ambos manejadores:

1. Validaban **solo el MIME** devuelto por `finfo` contra una lista blanca que incluye `text/plain` e `image/*`.
2. Construían el nombre en disco con la **extensión declarada por el cliente**:
   ```php
   $newName = uniqid('', true) . '_' . time() . '.' . $file->getClientExtension();
   ```
3. Guardaban en `FCPATH . 'uploads/notificaciones/'` / `'uploads/tickets/'`, es decir **dentro de `public/`**, un directorio **sin `.htaccess`** y por tanto servido y **ejecutado** por Apache (`mod_php`).

**Prueba de concepto**

```
# Fichero polyglot: bytes de GIF válido + payload PHP
printf 'GIF89a;\n<?php system($_GET["c"]); ?>' > shell.gif.php
```

`finfo` clasifica el fichero como `image/gif` (en lista blanca). La extensión `.php` viene del cliente y no se filtra → se guarda como `uploads/notificaciones/<uniqid>_<timestamp>.php`. Ese fichero es directamente solicitable y Apache lo ejecuta como PHP → **RCE**.

- `NotificacionesController::send` está protegida solo con `filter => 'auth'`; en el método solo se bloquea a `coach`. **Un alumno puede subir el adjunto.**
- `TicketsController` requiere `coach`/`staff`/`admin`/`superadmin` (escalada a RCE desde una cuenta de rol bajo).

El nombre lleva `time()`, y `uniqid()` es time-based: es fuerza-brutable dentro de la misma ventana de segundos; además, en tickets el atacante ve su propio ticket.

**Impacto**: ejecución de código arbitrario en el servidor de producción. Compromiso total.

**Corrección aplicada** (`app/Helpers/upload_helper.php` + los 3 manejadores + avatares)

1. **Lista blanca de extensión obligatoria** (`upload_allowed_extension()`), además del MIME. Rechaza cualquier cosa que no sea `jpg/jpeg/png/webp/gif/pdf/doc/docx/xls/xlsx/txt/mp4` (imágenes en avatares). También rechaza extensiones dobles / raras (`foo.php.jpg`).
2. **Nombre en disco 100 % aleatorio con CSPRNG**: `bin2hex(random_bytes(16)) . '.' . $ext` — no inferible.
3. **`upload_harden_dir()`**: crea el directorio y deja un `.htaccess` (con guardas `<IfModule>` para no provocar 500) que desactiva `Options -Indexes`, `php_flag engine off` y `Require all denied` para `*.php|phtml|phar|cgi|pl|py|sh|asp|jsp`, más `X-Content-Type-Options: nosniff`. **Verificado contra el Apache del contenedor: `.php` → 403, `.txt` → 200, `apachectl -t` → Syntax OK.**

**Pendiente (no aplicado — cambio mayor):** mover estos directorios **fuera de `public/`** (a `WRITEPATH/uploads/`, como ya hace `DocumentService`) y servirlos siempre por el controlador. Es la única protección definitiva; el `.htaccess` depende del servidor web.

---

### 🟠 H2 — `MensajesController::handleFileUpload()` llama a método inexistente

**Ubicación**: `app/Controllers/MensajesController.php:500` — `$this->secureUploadDir($uploadDir);`

`secureUploadDir()` **no está definido en ninguna parte** (ni en el controller, ni en `BaseController`, ni en un trait). El historial de git confirma que la llamada se añadió sin implementar nunca el método.

**Impacto**:
- Todo adjunto en el chat lanza `Error: Call to undefined method`, capturado por el `try/catch` externo → 500 genérico. **Los adjuntos de Mensajes están rotos al 100 %.**
- El endurecimiento del directorio que ese método supuestamente hacía nunca ocurría.

**Corrección aplicada**: la llamada se sustituye por `helper('upload'); upload_harden_dir($uploadDir);`. Este manejador **ya tenía** lista blanca de extensión (bien); ahora además usa `random_bytes` para el nombre.

> ⚠️ **Efecto secundario**: esto **reactiva** los adjuntos en el chat (llevaban rotos desde que se introdujo el bug). Conviene probar el flujo de adjuntar archivo en Mensajes antes de subir a producción.

---

### 🟠 M1 — Archivos subidos servidos sin cabeceras de seguridad ni control de acceso

**Descripción**

1. **Servido estático**: los ficheros en `public/uploads/**` (avatares y, cuando funcionen, adjuntos de mensajes/notificaciones/tickets) los sirve Apache directamente. `App\Filters\SecurityHeadersFilter` es un filtro `after` de CodeIgniter y **no se ejecuta** para ficheros estáticos → sin `nosniff`, sin `X-Frame-Options`.
2. **`DocumentacionController::serveFile()`** manda las cabeceras a mano y hace `exit` → el filtro `after` **tampoco** se ejecuta para descargas/previsualizaciones. Servía contenido `inline` sin `nosniff`.
3. **Sin control de acceso en el path directo**: los directorios `uploads/mensajes|notificaciones|tickets` no tenían `.htaccess`; quien conociera/adivinara la ruta descargaba adjuntos privados de otros usuarios, saltándose la comprobación de permisos del método `download()`.

**Corrección aplicada (parcial)**:
- `serveFile()`: añadidas `X-Content-Type-Options: nosniff` y `X-Frame-Options: SAMEORIGIN`.
- `upload_harden_dir()` deja `.htaccess` con `nosniff` en cada directorio de subida.
- Nombres de fichero ahora con CSPRNG → no adivinables.

**Pendiente**: mover uploads fuera de webroot (ver H1) — resuelve del todo el punto 3.

---

### 🟠 M2 — Secretos de producción dentro de la imagen Docker

`.dockerignore` ignoraba `.env`, `.git`, `users.txt`, pero **no `deploy/`**, que contiene `deploy/.env` con la **API key de Resend de producción** (`re_ivw6…`) y la contraseña de BD de producción, ni `database.txt`, ni `plataforma.zip` (snapshot de 18 MB). El `Dockerfile` hace `COPY . /var/www/html`.

Al estar fuera del docroot (`public/`) no son accesibles por web, pero quedan en las **capas de la imagen** y en el filesystem del contenedor (accesibles con una shell o si la imagen/registro se filtra).

**Corrección aplicada**: `.dockerignore` ahora ignora `deploy/`, `database.txt` y `*.zip`.

> Independientemente de esto: verificar que la key `re_ivw6…` sigue siendo válida y **rotarla** si esta imagen se ha publicado en algún registro.

---

### 🟡 M3 — Open redirect en `DocumentacionController::upload()`

**Antes**: `preg_match('#^/[a-zA-Z0-9/_-]#', $redirectTo)` — el segundo carácter podía ser `/`, así que `//evil.com` pasaba y `redirect()->to('//evil.com')` es una redirección externa protocol-relative.

**Corrección aplicada**: `preg_match('#^/[a-zA-Z0-9][a-zA-Z0-9/_?=&.\#-]*$#', $redirectTo)` — exige `/` + alfanumérico y solo caracteres de ruta seguros; rechaza `//`, `/\`, esquemas y URLs absolutas.

---

### 🟢 Hallazgos menores

| # | Detalle | Acción |
|---|---------|--------|
| **L1** | `Routes.php:45` `$routes->get('logout', …)` — un `<img src=".../logout">` cierra la sesión del usuario. | Recomendado: `logout` por POST (con formulario/JS en el enlace del navbar) o exigir CSRF. No aplicado: toca vistas. |
| **L2** | `app/Config/Security.php`: `tokenName='csrf_test_name'`, `cookieName='csrf_cookie_name'` (valores de esqueleto). CLAUDE.md afirma `jp_csrf_token` — **incorrecto**. CSRF funciona igual, pero los nombres por defecto son "fingerprinteables" y la doc engaña. `csrfProtection='cookie'` (token en cookie legible por JS); `session` es más robusto. | Doc corregida en CLAUDE.md. Cambiar los nombres requiere coordinar el deploy (las vistas usan `csrf_token()`, se adaptan solas; puede haber un parpadeo de fallos CSRF justo al desplegar). |
| **L3** | `layouts/base.php:6` `<?= $title ?>` sin `esc()`. Hoy ningún controller pasa dato de usuario crudo, pero es un XSS latente en `<title>`. | ✅ `esc($title …)` en el layout + quitado el `esc()` redundante de los 5 controllers que lo pre-escapaban (evita doble escape). |
| **L4** | `DocumentService::getAccessibleFolders()` arma fragmentos `WHERE "… = {$userId} …"` con el escape desactivado. Hoy **no explotable** (el parámetro está tipado `int` y viene de sesión server-side), pero es un patrón frágil. | Recomendado: pasar a binding (`?`). No aplicado: es reescribir una consulta que funciona, riesgo de regresión > beneficio. |
| **L5** | `clases/create.php`: `json_encode($nombres)` embebido en `<script>`. PHP escapa `/` por defecto (neutraliza `</script>`), pero un alumno controla su `name`. | ✅ Añadidas `JSON_HEX_TAG\|JSON_HEX_APOS\|JSON_HEX_QUOT\|JSON_HEX_AMP`. |
| **L6** | `mensajes/index.php` `buildAvatarHtml()` — `initials` sin escapar en la rama sin foto (máx. 2 chars en mayúscula: no explotable, pero inconsistente). | ✅ `escHtml(initials)`. |
| **L8** | `NotificacionesController::send` tipo `individual` no restringe destinatario: cualquiera (incl. alumno) manda notificaciones con título/cuerpo arbitrarios a cualquiera, sin rate-limit. Mensajes sí bloquea alumno↔alumno. | Recomendado: aplicar la misma regla de contacto que Mensajes + rate-limit. |
| **L9** | `public/robots.txt` → `Disallow:` (vacío) = indexable. | Recomendado `Disallow: /` para un backoffice tras login. |
| **L10** | `public/.htaccess`: `RewriteRule ^ http://%1%{REQUEST_URI}` baja a HTTP en el redirect www→no-www (ForceHTTPS/HSTS lo re-suben). | Cambiar a `https://`. |
| **L11** | `.env` de desarrollo en el árbol (`CI_ENVIRONMENT = development`). Verificado: **nunca commiteado** (`git log` vacío para `.env`). Riesgo solo si se copia a un servidor a mano. | Vigilar. Los despliegues reales usan `deploy/.env` (Hostinger) o variables del panel (Render), ambos con `production`. |

---

## 3. Cambios aplicados en esta rama

| Archivo | Cambio |
|---|---|
| `app/Helpers/upload_helper.php` | **Nuevo.** `upload_allowed_extension()` (lista blanca de extensión) + `upload_harden_dir()` (crea dir + `.htaccess` anti-ejecución con guardas `<IfModule>`). |
| `app/Controllers/NotificacionesController.php` | Lista blanca de extensión, nombre CSPRNG, `upload_harden_dir()`. |
| `app/Controllers/TicketsController.php` | Íd. |
| `app/Controllers/MensajesController.php` | Sustituida la llamada rota `secureUploadDir()` por `upload_harden_dir()`; nombre CSPRNG; validación de extensión vía helper. |
| `app/Controllers/AvatarController.php` | Lista blanca de extensión de imagen; `upload_harden_dir()` en `uploads/avatars/`. |
| `app/Controllers/DocumentacionController.php` | `serveFile()`: `X-Content-Type-Options: nosniff` + `X-Frame-Options`. `upload()`: regex anti-open-redirect endurecida. |
| `app/Views/layouts/base.php` | `<title>` ahora con `esc()`. |
| `app/Controllers/{Alumnos,Clases,Entrenadores,Torneos}Controller.php` | Quitado el `esc()` redundante en `'title'` (el layout ya escapa). |
| `app/Views/clases/create.php` | `json_encode(...)` con flags `JSON_HEX_*`. |
| `app/Views/mensajes/index.php` | `escHtml(initials)` en `buildAvatarHtml()`. |
| `.dockerignore` | Ignora `deploy/`, `database.txt`, `*.zip`. |
| `.claude/CLAUDE.md` | Sección de deuda técnica actualizada con estos hallazgos. |

**2ª tanda (§6):** `app/Helpers/upload_helper.php` (+3 funciones), `app/Commands/MigrateUploadsOutOfWebroot.php` (nuevo), `Mensajes/Notificaciones/TicketsController.php` (upload→WRITEPATH, download→`upload_resolve_stored`), `app/Models/NotificationModel.php` (`countRecentBySender`), `NotificacionesController.php` (`send` endurecido), `app/Config/Routes.php` (logout POST), `app/Config/Security.php` (nombres CSRF), `app/Views/components/sidebar.php` + `public/assets/css/app.css` (form de logout), `tests/unit/UploadHardeningTest.php` (nuevo).

**Verificación:**
- `php -l` OK en todos los archivos tocados.
- **Suite de tests: 167/167 OK** (663 assertions) — `NotificacionesTest` + nuevo `UploadHardeningTest`.
- `.htaccess` probado contra el Apache del contenedor: `.php` → 403, estáticos → 200, sintaxis OK.
- App levantada: `/login` → 200, `/dashboard` → 302, `GET /logout` → 302, `POST /logout` sin CSRF → 403.
- `php spark uploads:migrate` probado end-to-end.

---

## 4. Recomendaciones pendientes (requieren decisión / coordinación)

> Los puntos 1–4 se aplicaron en la 2ª tanda — ver **§6**. Quedan pendientes:

5. **(Baja) Bindings en `getAccessibleFolders()`** (L4).
6. **(Baja) `robots.txt` → `Disallow: /`; `.htaccess` www→no-www a `https://`** (L9/L10).
7. **Rotar la API key de Resend** `re_ivw6…` si la imagen Docker con `deploy/` se ha publicado alguna vez. *(No es un cambio de código.)*
8. Fase 2 ya prevista en CLAUDE.md: 2FA/TOTP, CAPTCHA, CSP, HIBP, driver de sesión en BD.

---

## 6. Segunda tanda — recomendaciones 1–4 aplicadas (2026-09-02)

Tras aprobar el informe se pidió aplicar **todo lo pendiente menos rotar la key de Resend** (punto 7, acción externa).

### 6.1 Adjuntos privados fuera del webroot (resuelve H1 y M1 del todo)

- **Nuevas funciones en `app/Helpers/upload_helper.php`**:
  - `upload_private_dir($sub)` → `WRITEPATH/uploads/<sub>/` (fuera de `public/`, junto a lo que ya usa `DocumentService`).
  - `upload_stored_path()` → valor que se guarda en BD, formato `uploads/<sub>/<nombre>` (igual que antes → **sin migración de BD**).
  - `upload_resolve_stored()` → resuelve ese valor a ruta absoluta comprobando **primero `WRITEPATH`, luego `FCPATH`** (los adjuntos antiguos siguen sirviéndose). Incluye defensa anti path-traversal (regex + rechazo de `..`).
- **`Mensajes` / `Notificaciones` / `Tickets`**: `handleFileUpload()` guarda en `upload_private_dir()`; `download()` resuelve con `upload_resolve_stored()` en vez de `FCPATH . $fila['file_path']`.
- **Comando de migración**: `php spark uploads:migrate` mueve los adjuntos ya subidos de `public/uploads/{mensajes,notificaciones,tickets}` a `writable/uploads/…` (copia → verifica tamaño → borra; idempotente; `--dry-run` disponible). **En prod (Hostinger) ejecutarlo por SSH después de desplegar.** Si no se ejecuta, los antiguos siguen funcionando por el fallback.
- Avatares: se mantienen en `public/uploads/avatars/` (son públicos, se incrustan como `<img>` en toda la app; meterlos tras un controlador = 1 consulta + auth por avatar y por fila de tabla). Quedan con lista blanca de extensión + `.htaccess` anti-ejecución.

### 6.2 `logout` solo por POST (L1)

- `Routes.php`: `POST /logout` (con CSRF) hace el logout real; `GET /logout` solo redirige (a `/dashboard` o `/login`) — así un `<img src=".../logout">` ya no cierra la sesión y los enlaces/marcadores viejos no dan 404.
- `components/sidebar.php`: el enlace pasa a `<form method="post">` + `<button class="sidebar-logout">` + `csrf_field()`. CSS ajustado (`app.css`).
- `perfil/change_password.php` ya usaba un form POST con CSRF — sin cambios.

### 6.3 Nombres de CSRF propios (L2)

- `Config/Security.php`: `tokenName` `csrf_test_name` → **`jp_csrf_token`**; `cookieName` `csrf_cookie_name` → **`jp_csrf_cookie`**.
- Todas las vistas usan `csrf_field()`/`csrf_token()`/`csrf_hash()` (dinámico) → se propaga solo. Verificado: la página de login ya emite `jp_csrf_token`.
- ⚠️ **Al desplegar**: las pestañas ya abiertas con el token viejo fallarán un POST hasta recargar. Desplegar en horario de bajo tráfico.

### 6.4 `Notificaciones::send` endurecido (L8)

- `type` se normaliza a exactamente `individual` | `group` (antes un `type` inventado por un alumno se colaba en la rama grupal).
- Individual: se comprueba que el destinatario **existe y está activo**, y se aplica la **misma regla que Mensajes** — un alumno solo puede notificar a no-alumnos.
- **Rate-limit** por remitente (ventana de 10 min): 8 envíos para alumno, 40 para el resto → HTTP 429. Nuevo `NotificationModel::countRecentBySender()`.

### 6.5 Verificación (2ª tanda)

- `php -l` OK en todo lo tocado.
- **Tests: 167/167 OK** (+11 nuevos en `tests/unit/UploadHardeningTest.php`: lista blanca de extensión, anti-traversal, almacenamiento en `WRITEPATH`, y *code-scan* que fija el wiring de los 3 handlers + logout POST + nombres CSRF).
- `.htaccess` generado probado contra Apache del contenedor: `.php` → 403, estáticos → 200, `apachectl -t` OK.
- `php spark uploads:migrate` probado end-to-end (movió un fichero real, creó `.htaccess`).
- `GET /logout` → 302; `POST /logout` sin token → 403; login emite `jp_csrf_token`.

### 6.6 Nota operativa

En **Hostinger** (prod) la CLI y el servidor web corren con el mismo usuario → `spark uploads:migrate` no da problemas de permisos. En Docker/Render, si el comando se ejecuta como `root` y el web como `www-data`, hacer `chown -R www-data:www-data writable/uploads` después (el comando lo avisa).

---

## 7. Hallazgo adicional (L12) — uploads trackeados en git

Durante la 2ª tanda se detectó que hay contenido subido por usuarios **dentro del repositorio git**, a pesar de que `.gitignore` ignora `public/uploads/` y `writable/uploads/` (no destrackea lo que ya estaba trackeado):

```
public/uploads/avatars/avatar_2_1776705808.jpeg
public/uploads/avatars/avatar_2_1776706042.jpeg
public/uploads/mensajes/69f1d60c0263e6..._1777456652.png   ← adjunto de un mensaje privado
writable/uploads/personal/8/632a7a42...pdf                 ← PDF de carpeta personal de un usuario
writable/uploads/public/3/a8b0299e...pdf
```

**No se ha tocado en esta rama** (borrarlos de git puede eliminarlos en el próximo `git pull` de producción si el deploy es por git). Recomendación, a coordinar:

```bash
git rm --cached public/uploads/avatars/*.jpeg \
                 public/uploads/mensajes/*.png \
                 writable/uploads/personal/8/*.pdf \
                 writable/uploads/public/3/*.pdf
# confirmar que en producción esos ficheros existen en disco ANTES de desplegar el commit
```

Y verificar que el resto de `public/uploads/` y `writable/uploads/` no tiene nada más trackeado: `git ls-files public/uploads writable/uploads`.

---

## 5. Lo que está bien (para no re-auditarlo)

- **CSRF** activo globalmente (`Filters.php` `globals.before`), `tokenRandomize=true`, endpoints AJAX devuelven `csrf_hash()` fresco.
- **Auto-routing desactivado** (`Routing.php` `$autoRoute = false`).
- **Autenticación**: bcrypt vía callback del modelo; login en tiempo constante con hash dummy; rechazo de cuentas no `active`; `session()->regenerate()` en login; anti-fuerza-bruta por cuenta y por IP en `auth_events`.
- **Reset de contraseña**: token aleatorio de 256 bits guardado hasheado (SHA-256), expira 1 h, se borran todos los tokens al usarlo, aviso por email, throttling por email e IP sin filtrar existencia de cuenta.
- **XSS servidor**: `esc()` aplicado de forma consistente (tickets, notificaciones, alumnos, clases…). `avatar_html()` escapa. `nl2br(esc(...))` correcto.
- **XSS cliente**: el chat y las notificaciones usan `escHtml()` sobre los campos del servidor.
- **SQL**: modelos con `?` binding; `query builder` con valores tipados; sin concatenación de input de usuario en SQL.
- **Control de acceso a nivel de objeto** presente en `Documentacion` (`canAccessFolder`), `Clases` (`isAssignedOrAdmin`, filtro por rol en `show`), `Mensajes` (pertenencia a conversación), `Tickets` (dueño/superadmin), `Perfil` (self/admin + usuario maestro protegido), `Avatar` (self/admin).
- **Gestión de staff**: roles en lista blanca (`admin/staff/coach`), nunca `superadmin`; usuario maestro (`id=2` / email) protegido en `BaseController`, `ConfiguracionService` y `PerfilController`.
- **Sin secretos hardcodeados** en `app/` ni `public/assets/`. `.env` nunca commiteado.
- **Cabeceras de seguridad** (`SecurityHeadersFilter`) correctas en respuestas normales: `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP; HSTS solo en prod.
- **Cookies**: `httponly=true`, `samesite=Lax`, `secure` en producción.
- **Subida en Documentación** (`DocumentService::uploadFile`): lista blanca de extensión + MIME real + límite por tipo + guardado en `WRITEPATH` (fuera de webroot) + nombre CSPRNG + `.htaccess`. **Este es el patrón correcto** — los demás manejadores deberían converger a él.
