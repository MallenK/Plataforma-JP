# TICKET-006 — Endurecimiento de la subida de archivos (RCE) + puntos menores de auditoría

| | |
|---|---|
| **Categoría** | Seguridad |
| **Prioridad** | 🔴 Alta (RCE autenticada en producción) |
| **Estado** | En rama, pendiente de PR + deploy |
| **Módulo** | Notificaciones, Incidencias (Tickets), Mensajes, Documentación, infra |
| **Rama** | `fix/seguridad-rce-uploads` |
| **Versión** | 1.1.5 |
| **Informe** | `docs/auditoria/2026-09-02-auditoria-seguridad.md` (hallazgos H1, H2, M1, M2, M3, L3, L5, L6, L8, L9, L10, L12) |

Este ticket es la **Tanda A** del trabajo de la auditoría. La **Tanda B** (logout POST + rename de nombres CSRF, hallazgos L1/L2) queda para una ventana de bajo tráfico y se hará en otra rama.

---

## Contexto

Los manejadores de adjuntos de **Notificaciones**, **Incidencias** y **Mensajes**:

- Validaban solo el MIME (`finfo`), no la **extensión final** del fichero.
- Guardaban el fichero **dentro de `public/`**, en carpetas sin `.htaccess`.

Con un fichero polyglot (cabecera de imagen válida + código PHP) nombrado `algo.php`, un usuario **con sesión iniciada** conseguía **ejecución de código en el servidor**. El caso de Notificaciones lo podía disparar **cualquier rol, incluido un alumno**.

Además, las carpetas de adjuntos privados eran servidas directamente por Apache: quien adivinaba la ruta (nombre `uniqid()` + `time()`, fuerza-brutable) se descargaba adjuntos privados de otros usuarios **saltándose la comprobación de permisos**.

Efecto colateral detectado: `MensajesController::handleFileUpload()` llamaba a un método inexistente (`secureUploadDir()`) → **los adjuntos del chat llevaban meses rotos** (500 silencioso).

## Causa raíz

- Confianza en el MIME + uso de la extensión declarada por el cliente para el nombre en disco.
- Directorio de subida dentro del webroot con `mod_php` activo.
- Servido estático sin control de acceso ni cabeceras.
- Llamada a un helper que nunca se implementó.

## Solución aplicada

| Área | Cambio |
|---|---|
| `app/Helpers/upload_helper.php` (nuevo) | `upload_allowed_extension()` (lista blanca de extensión, rechaza dobles extensiones), `upload_private_dir()` → `WRITEPATH/uploads/` (fuera del webroot), `upload_stored_path()` (formato en BD sin cambios → **sin migración**), `upload_resolve_stored()` (resuelve con fallback a `FCPATH` para adjuntos antiguos + anti path-traversal), `upload_harden_dir()` (`.htaccess` anti-ejecución con guardas `<IfModule>`). |
| `NotificacionesController`, `TicketsController`, `MensajesController` | `handleFileUpload()`: lista blanca de extensión + nombre `random_bytes(16)` + guardado en `upload_private_dir()`. `download()`: sirve por PHP resolviendo con `upload_resolve_stored()`. |
| `MensajesController` | Sustituida la llamada rota `secureUploadDir()` → **reactiva los adjuntos del chat**. |
| `AvatarController` | Lista blanca de extensión de imagen + `.htaccess`. Los avatares **siguen en `public/`** (son públicos, se incrustan en toda la app). |
| `NotificacionesController::send` (L8) | `type` estricto (`individual`/`group`), destinatario debe existir/estar activo, un alumno solo notifica a no-alumnos, rate-limit por remitente (8/10 min alumno, 40/10 min resto → 429). Nuevo `NotificationModel::countRecentBySender()`. |
| `app/Commands/MigrateUploadsOutOfWebroot.php` (nuevo) | `php spark uploads:migrate` — mueve adjuntos ya subidos de `public/uploads/{mensajes,notificaciones,tickets}` a `writable/uploads/`. Idempotente, `--dry-run`. **No obligatorio** (hay fallback), pero recomendado. |
| `DocumentacionController` | `serveFile()`: `X-Content-Type-Options: nosniff` + `X-Frame-Options`. `upload()`: regex anti open-redirect endurecida (M3). |
| `app/Views/layouts/base.php` (L3) | `<title>` con `esc()`; quitado el `esc()` redundante de 4 controllers. |
| `app/Views/clases/create.php` (L5) | `json_encode` con `JSON_HEX_TAG\|JSON_HEX_APOS\|JSON_HEX_QUOT\|JSON_HEX_AMP`. |
| `app/Views/mensajes/index.php` (L6) | `escHtml(initials)` en `buildAvatarHtml()`. |
| `.dockerignore` (M2) | Ignora `deploy/`, `database.txt`, `*.zip` (secretos de prod fuera de la imagen). |
| `public/robots.txt` (L9) | `Disallow: /` (backoffice tras login, no debe indexarse). |
| `public/.htaccess` (L10) | Redirect `www → no-www` ahora siempre a `https://` y en cualquier esquema (antes bajaba a `http://` un instante). |
| git (L12) | `git rm --cached` de 5 ficheros subidos por usuarios que se colaron en el repo (2 avatares, 1 adjunto de mensaje privado, 2 PDF de carpetas). Siguen en disco; solo se dejan de versionar. |
| `tests/unit/UploadHardeningTest.php` (nuevo) | 9 tests: lista blanca de extensión, anti-traversal, guardado en `WRITEPATH`, y *code-scan* que fija el wiring de los 3 handlers para que una refactorización futura no reintroduzca el RCE. |

## Bloqueantes

- ⚠️ **Verificar antes del deploy** que los 5 ficheros del punto L12 existen en el **disco de Hostinger** (el `git rm --cached` no los borra de disco; el deploy a Hostinger es manual, no `git pull`, así que no deberían perderse — pero confirmar).

## Notas de despliegue

- **Sin migración de BD.** El formato de `file_path` en las tablas no cambia.
- Render: automático al mergear.
- **Hostinger (subir estos ficheros):**
  - `app/Helpers/upload_helper.php` (nuevo)
  - `app/Commands/MigrateUploadsOutOfWebroot.php` (nuevo)
  - `app/Controllers/{Notificaciones,Tickets,Mensajes,Avatar,Documentacion}Controller.php`
  - `app/Models/NotificationModel.php`
  - `app/Views/layouts/base.php`, `app/Views/clases/create.php`, `app/Views/mensajes/index.php`
  - `app/Controllers/{Alumnos,Clases,Entrenadores,Torneos}Controller.php` (solo quitan un `esc()` redundante)
  - `app/version.json`
  - `.dockerignore`, `public/robots.txt`, `public/.htaccess`
- **Tras subir**, por SSH en Hostinger (opcional, recomendado):
  ```bash
  php spark uploads:migrate
  ```
- **NO** entra en este deploy: `logout` POST ni el rename de CSRF (Tanda B).

## Verificación

- **Suite: 165/165** (`docker compose exec app php vendor/bin/phpunit`).
- Manual tras desplegar:
  - Subir un `.jpg` como adjunto en **Mensajes** → funciona (llevaba roto).
  - Intentar subir un `.php` en Notificaciones → rechazado.
  - Descargar un adjunto propio de una incidencia → funciona.
  - `https://www.<dominio>` → redirige a `https://<dominio>` sin pasar por http.
  - `robots.txt` sirve `Disallow: /`.
