# Protocolo de despliegue — Plataforma JP

> **Regla de oro:** todo cambio se sube **primero a Render**, se pasa la
> batería de humo completa, y **solo si está en verde** se sube a
> Hostinger (producción real). Nunca al revés, nunca directo a Hostinger.

## Estado (2026-09-01, tras release v1.1.2)

- **Producción (Hostinger):** `v1.1.2` desplegada (seguridad de acceso +
  posiciones múltiples + admin/staff en clases + email; y rediseño de las
  pantallas de acceso). Migraciones de BD aplicadas a mano en phpMyAdmin.
- **Render:** la BD (TiDB Serverless) tuvo problemas de credenciales; el
  `spark migrate` automático **no funciona con TiDB**. En el despliegue del
  2026-09-01 se subió **directo a Hostinger** saltándose Render (decisión del
  cliente) tras pasar la suite completa (153/153) y humo en local Docker.
- **Email:** `RESEND_API_KEY` rotada (`re_au3n6kdT_…`), dominio
  `jppreparation.com` **verificado** en Resend, remitente
  `hola@jppreparation.com`. La key **debe estar en el `.env` del servidor**
  (un `HTTP 401` en `email_log` = key incorrecta en `.env`).
- **Git:** local `main` = `v1.1.2` (`6645d65`); tags `v1.1.0` y `v1.1.2`
  pusheados. **Falta `git push origin main`** (la rama remota sigue en
  `v1.1.0`) — lo bloquea el clasificador del agente, lo hace el humano.
- Ramas locales transitorias de la integración: `develop`, `release/2026-09`,
  `feat/login-redesign` y las 6 `feat/*`/`fix/*` (borrar tras validar).

## Incidencia 2026-09-08 — Soporte (tickets) roto en producción

- **Síntoma:** `/tickets/gestion` daba un "404" (pantalla genérica de CI).
- **Causa 1:** el **código** de tickets F1–F4 sí estaba en Hostinger, pero
  las migraciones `2026-09-07-000001..000004` **no**. La consulta de la
  bandeja de gestión pedía `tickets.archived_at` → `Unknown column`.
- **Causa 2 (enmascaraba la 1):** `App\Libraries\ReportableExceptionHandler::handle()`
  no declara `: void`, obligatorio en CI 4.7 → el manejador de errores 5xx
  peta al renderizar y CI cae a su página genérica (que parece un 404).
  **Bug latente también en el repo — pendiente de arreglar (código + PR).**
- **Solución aplicada (BD prod, 2026-09-08):**
  [`docs/deploy/fix_tickets_prod.sql`](../deploy/fix_tickets_prod.sql) en
  phpMyAdmin con "Continuar en caso de error". Añade columnas de `tickets`
  y `ticket_replies` + tablas `ticket_events` y `ticket_counters`. **Aditivo,
  sin pérdida de datos.** Las 3 FOREIGN KEY fallaron con errno 150 (el tipo
  de `tickets.id`/`users.id` en prod no casa con lo que asumían las
  migraciones del repo) → **se dejan sin aplicar; la app no las necesita.**
- **Lección:** producción se construyó a mano / desde exports, su esquema NO
  es idéntico al que generan las migraciones. Antes de tocar la BD de prod,
  pedir `SHOW CREATE TABLE` de las tablas implicadas.

---

## 0. Mapa de entornos

| Entorno | URL | Infra | Despliegue | BD | Migraciones |
|---------|-----|-------|-----------|----|-----|
| **Local** | `http://localhost:8080` | Docker Compose (`jp_app` + `jp_db` + `jp_phpmyadmin`) | `docker compose up -d --build` | `jp_db` (MySQL 8, contenedor) | `php spark migrate` a mano |
| **Render** (PRE-PRODUCCIÓN) | `https://plataforma-jp.onrender.com` | Contenedor Docker (`Dockerfile`), plan Free (se duerme) | **Automático al hacer push a `main`** en GitHub | **2ª BBDD de Hostinger** (MariaDB, Remote MySQL restringido a IPs de Render) — datos de prueba. Host/BBDD/usuario en el panel de env de Render. | `docker/start.sh` corre `php spark migrate --all` (hoy no-op: la BBDD se cargó clonando el esquema local, ver §7-bis). Migración NUEVA → probarla contra pre-prod o aplicarla a mano. |
| **Hostinger** (producción) | `https://app.jppreparation.com` | Hosting compartido Apache + PHP 8.3 (hPanel) | **Manual** — subida de ZIP por hPanel | `u912370917_jpapp` (MariaDB de Hostinger) — **sin acceso remoto** | **Manuales** — SQL en phpMyAdmin |

> **TiDB Serverless queda RETIRADO** (2026-09-06). Pre-producción ya no usa
> TiDB: usa una 2ª BBDD en la misma cuenta de Hostinger. Ventajas: mismo motor
> que producción (MariaDB), sin el caching de AUTO_INCREMENT que rompía
> `migrate` y provocó el bug #1062 de bonos.

### Particularidades de Hostinger

- El layout **no es el estándar de CodeIgniter**. Todo el código cuelga de
  `public_html/app/`:
  ```
  public_html/
   ├── index.php        ← copia de deploy/public/index.php
   ├── .htaccess        ← copia de deploy/public/.htaccess
   └── app/
        ├── index.php   (el de CI, no se usa como front)
        ├── app/
        ├── vendor/
        ├── writable/
        └── ...
  ```
- El front-controller de producción es [`deploy/public/index.php`](../../deploy/public/index.php)
  (bootstrapea `public_html/app/`).
- [`deploy/public/.htaccess`](../../deploy/public/.htaccess): fuerza HTTPS,
  bloquea `app|vendor|writable|.env`, sube límites PHP (subida 64M / post 68M /
  memoria 256M / ejecución 300s).
- El `.env` de producción vive **solo en el servidor**, nunca en git. Base:
  [`deploy/.env`](../../deploy/.env) (con `database.default.password` y
  `RESEND_API_KEY` sin rellenar).

---

## 1. Checklist previa (obligatoria, antes de tocar nada)

- [ ] **Rama de release identificada y congelada.** Debe contener *todo* lo
      que va a producción (ver [`03-organizacion-github.md`](03-organizacion-github.md)).
      Anotar el hash del commit.
- [ ] **Tests en verde en local (Docker):**
      ```bash
      docker compose up -d
      docker compose exec app php vendor/bin/phpunit
      ```
      Debe salir `OK` con 0 fallos y 0 errores. Anotar el número de tests.
- [ ] **Sin secretos en el diff.** Revisar que no entra `.env`, `deploy/.env`,
      `users.txt`, `database.txt`, `*.zip`, ni nada bajo `deploy/`:
      ```bash
      git status --porcelain
      git diff --stat <base>..<release>
      ```
- [ ] **Migraciones nuevas identificadas.** Listar los ficheros nuevos:
      ```bash
      git diff --name-only <base>..<release> -- app/Database/Migrations/
      ```
      Para cada una, tener el SQL equivalente para phpMyAdmin (ver §4.2).
- [ ] **`app/version.json` preparado** con la entrada nueva (ver §5). No se
      commitea todavía; se hace en el commit de release.
- [ ] **Backup de la BD de producción** (phpMyAdmin → Exportar → BD
      `u912370917_jpapp` → SQL completo). Guardar con fecha.
- [ ] **Variables de entorno de Render revisadas** (ver §3.2).
- [ ] **Bloqueantes de negocio revisados** (ver §7).
- [ ] Avisar de ventana de despliegue si puede haber corte.

---

## 2. Consolidar la rama de release

El repo trabaja con una rama de integración; las ramas `feat/*` y `fix/*`
recientes **no siempre parten de `main`** (ver snapshot en
[`03-organizacion-github.md`](03-organizacion-github.md)). Antes de desplegar:

1. Partir de `main` actualizado:
   ```bash
   git checkout main && git pull origin main
   git checkout -b release/YYYY-MM-DD
   ```
2. Fusionar en orden las ramas que entran (feature primero, seguridad al
   final). Resolver conflictos.
3. Añadir la entrada a `app/version.json`.
4. Correr **toda** la suite de tests en Docker. En verde → seguir.
5. (Recomendado) pasar `/code-review` y `/security-review` sobre el diff
   `main..release/YYYY-MM-DD`.
6. Commit de release: `chore(release): vX.Y.Z — <resumen>`.

---

## 3. Despliegue a Render (validación)

### 3.1 Disparar el despliegue

Render está vinculado a GitHub y **reconstruye al detectar push en `main`**.

```bash
git checkout main
git merge --no-ff release/YYYY-MM-DD
git push origin main
```

> Si no se quiere tocar `main` todavía: hacer el push a `main` es el
> mecanismo actual de deploy a Render. Si se configura una rama `staging`
> en el panel de Render, actualizar este documento.

### 3.2 Verificar durante el build (panel de Render → Logs)

`docker/start.sh` hace, en este orden:

1. Genera `/var/www/html/.env` a partir de variables de entorno del panel.
   **Comprobar que están todas y son estables:**
   | Variable | Nota |
   |----------|------|
   | `CI_ENVIRONMENT` | `production` |
   | `APP_BASE_URL` | `https://plataforma-jp.onrender.com/` |
   | `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` / `DB_PORT` | BD de Render |
   | `ENCRYPTION_KEY` | **Debe ser fija y no cambiar entre deploys** — si cambia, se invalidan sesiones y tokens cifrados |
   | `RESEND_API_KEY` | la clave `re_au3n6kdT_…` (válida, `sending` habilitado). **Valor completo solo en el `.env` de cada servidor / gestor de secretos, nunca en el repo.** Sin espacios ni comillas. |
   | `MAIL_FROM` | `JP Preparation <hola@jppreparation.com>` — dominio `jppreparation.com` **verificado** en Resend |

   > `start.sh` genera también variables `email.SMTP*` (Brevo) que el código
   > actual **no usa** (el envío es por Resend API vía `MailService`). No
   > estorban, pero no confiar en ellas.

2. `php spark migrate --all -n` — **con TiDB Serverless esto NO crea bien las
   migraciones nuevas** (AUTO_INCREMENT roto en la tabla `migrations`). El
   `|| echo "[WARN] Migrations failed, continuing..."` lo silencia. Para
   Render/TiDB: aplicar el mismo SQL de `docs/deploy/*.sql` a mano en el
   **SQL Editor de TiDB Cloud** (§4.2), como en Hostinger.

3. Arranca Apache.

### 3.3 Batería de humo en Render

Ver §6. **Toda debe pasar.** Si algo falla → no se sube a Hostinger; se
corrige, se repite el ciclo.

---

## 4. Despliegue a Hostinger (producción)

> Solo si Render pasó la batería de humo completa.

### 4.1 Backup

- phpMyAdmin de Hostinger → Exportar BD `u912370917_jpapp` completa (SQL).
- Descargar copia del `public_html/` actual (ZIP por hPanel) por si hay
  que revertir.

### 4.2 Migraciones (phpMyAdmin, ANTES de subir el código)

1. phpMyAdmin → BD `u912370917_jpapp` → pestaña **SQL**.
2. Marcar **«Continuar en caso de error»** (columnas/tablas que ya existan
   darán error y seguirá).
3. Ejecutar los scripts que apliquen, en orden:
   - **v1.1.0** — [`docs/deploy/migraciones_seguridad.sql`](../deploy/migraciones_seguridad.sql)
     (tabla `auth_events` + `users.password_changed_at` + `users.must_change_password`)
     y [`docs/deploy/migraciones_posiciones.sql`](../deploy/migraciones_posiciones.sql)
     (`player_profiles.position` → `TEXT`).
   - **v1.1.2** — sin cambios de BD.
   - [`deploy/migraciones_pendientes.sql`](../../deploy/migraciones_pendientes.sql)
     → solo si falta algo (Tickets y demás **ya están vivas** en producción).
   - Cualquier `.sql` nuevo de futuras releases.
4. Verificar a mano que las tablas/columnas nuevas existen (`auth_events`,
   `users.must_change_password`, `player_profiles.position` = `text`).

### 4.3 Subir el código

Contenido a subir a `public_html/app/` (sobrescribiendo):

- `app/` (todo)
- `vendor/` (si cambió `composer.lock` — en general sí conviene subirlo)
- `public/assets/` (CSS/JS)
- `public/index.php` **no**: el front real es `deploy/public/index.php`

Método:
1. Generar un ZIP limpio del release (sin `.git`, sin `node_modules`, sin
   `writable/logs/*`, sin `.env`).
2. Subirlo por **hPanel → Administrador de archivos** a `public_html/app/` y
   extraer, **o** por FTP/SFTP.
3. Copiar a `public_html/`:
   - `deploy/public/index.php` → `public_html/index.php`
   - `deploy/public/.htaccess` → `public_html/.htaccess`

### 4.4 Configuración en el servidor

- `public_html/app/.env` (basado en [`deploy/.env`](../../deploy/.env)):
  - `CI_ENVIRONMENT = production`
  - `app.baseURL = 'https://app.jppreparation.com/'`
  - `database.default.*` → credenciales reales de Hostinger.
  - `RESEND_API_KEY=` → la clave `re_au3n6kdT_…` **completa** (sin comillas ni
    espacios). El valor íntegro se copia desde Resend / el gestor de secretos,
    **nunca desde el repo**. Si en `email_log` aparece `HTTP 401` → la línea del
    `.env` está mal escrita.
  - `MAIL_FROM="JP Preparation <hola@jppreparation.com>"` (dominio verificado).
  - `encryption.key` → fija; si no existía, generarla una vez y no volver a
    cambiarla.
- Permisos: `writable/` debe ser escribible (755/775).

### 4.5 Limpiar caché

- Por SSH (si hay acceso): `php spark cache:clear`
- Si no: borrar el contenido de `public_html/app/writable/cache/` desde el
  administrador de archivos.

### 4.6 Registro de versión

- Confirmar que `public_html/app/app/version.json` es el del release (con
  la entrada nueva). El panel «Historial de versiones» del sidebar
  (visible solo a `admin`/`superadmin`) lo lee de ahí.

### 4.7 Batería de humo en producción

Ver §6, contra `https://app.jppreparation.com`. Con una cuenta real de
`superadmin` y una de `player`.

---

## 5. `app/version.json` — obligatorio en cada despliegue

Requisito permanente del cliente: **cada subida a producción** añade una
entrada nueva (la más reciente **primero**) a [`app/version.json`](../../app/version.json):

```json
{
    "version": "1.1.2",
    "date": "2026-09-01 21:30:00",
    "description": "Resumen en una frase de lo que cambia de cara al usuario."
}
```

Última en producción: **v1.1.2** (rediseño de las pantallas de acceso).
Anterior: v1.1.0 (seguridad de acceso, posiciones múltiples, admin/staff en clases).

- `version`: semver-ish — *patch* para fixes pequeños, *minor* para features.
- `date`: formato `Y-m-d H:i:s`.
- Si se despliega por SSH saltándose git: actualizar el fichero **en el repo
  y** subirlo al servidor, para que panel y repo no se desincronicen.

---

## 6. Batería de humo (smoke tests)

Ejecutar en **cada** entorno tras desplegar. Cuenta `superadmin` + cuenta
`player`.

### Acceso / auth
- [ ] `/login` carga con el diseño esperado (revisar que es la versión del release).
- [ ] Login correcto → dashboard con el menú del rol.
- [ ] Login con contraseña mal → mensaje de error, sin filtrar si el email existe.
- [ ] Tras N intentos fallidos → bloqueo temporal (rate-limit persistente).
- [ ] `/forgot-password` con email real → llega correo (⚠️ requiere `RESEND_API_KEY` válida).
- [ ] Enlace de reset → permite cambiar contraseña; caduca a la 1 h; token de 1 uso.
- [ ] Alta de usuario nuevo (alumno) → al primer login obliga a `/perfil/password`.
- [ ] `/perfil/password` (cambiar mi contraseña) con re-autenticación.
- [ ] Logout.

### Autorización
- [ ] `player` en `/alumnos`, `/entrenadores`, `/bonos`, `/pasar-lista` → 403.
- [ ] Rutas de escritura sin sesión → redirige a login.

### Módulos
- [ ] Dashboard: stats solo para admin/superadmin; calendario para todos.
- [ ] Alumnos: ficha, edición de perfil propio, anotaciones.
- [ ] Clases: crear sesión, asignar coach/staff, pasar lista, descuento de bono.
- [ ] Documentación: subir, previsualizar y **descargar** un archivo
      (histórico BUG: descarga daba 503 — verificar).
- [ ] Mensajes: abrir conversación, enviar, adjuntar, botón «nueva conversación» en móvil.
- [ ] Notificaciones: recibir, marcar leída; `player` **no** puede enviar
      (histórico BUG: el botón salía para player — verificar).
- [ ] Tickets: crear (rol no-player), responder (superadmin), adjuntar.
- [ ] Configuración → Seguridad → «Actividad de seguridad reciente» carga.
- [ ] Panel «Historial de versiones» del sidebar muestra la entrada nueva.

### Infra
- [ ] Cabeceras de seguridad presentes en la respuesta
      (`X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`,
      `Permissions-Policy`, `Cross-Origin-Opener-Policy`):
      ```bash
      curl -sI https://<host>/login | grep -iE 'x-frame|x-content|referrer|permissions-policy|cross-origin'
      ```
- [ ] HTTPS forzado (http → 301 https).
- [ ] Sin errores 500 en `writable/logs/` ni en los logs de Render.
- [ ] Tabla `email_log`: revisar estados tras probar envíos.
- [ ] Tabla `auth_events`: se están registrando los logins.

---

## 7. Bloqueantes y riesgos conocidos

| # | Riesgo | Estado | Acción |
|---|--------|--------|--------|
| ✅ 1 | Email transaccional (Resend) | **Resuelto** | Key `re_au3n6kdT_…` válida, dominio `jppreparation.com` **verificado**, remitente `hola@jppreparation.com`. Único cuidado: que esa key esté **exacta** en el `.env` de cada servidor (un `HTTP 401` en `email_log` = key mal escrita). |
| ✅ 2 | `deploy/` fuera de `.gitignore` | **Resuelto** | `.gitignore` ya ignora `deploy/` y `*.zip`. |
| 🟠 3 | `plataforma.zip` (18 MB) sin trackear en la raíz | Vivo | Borrarlo del árbol de trabajo. Ya está en `.gitignore`, nunca `git add`. |
| 🔴 4 | **`spark migrate` NO funciona con TiDB Serverless** (Render): AUTO_INCREMENT roto en la tabla `migrations` → las migraciones nuevas no se registran/crean | Vivo | Aplicar el SQL de `docs/deploy/*.sql` a mano en el **SQL Editor de TiDB Cloud**, e insertar las filas correspondientes en `migrations` para que no se reintente. Mismo procedimiento que Hostinger. |
| 🟡 5 | `must_change_password`: las altas nuevas obligan a definir contraseña en el primer acceso | Por diseño | Avisar al equipo del cambio de UX. Usuarios existentes no se ven afectados (columna default 0). |
| 🟡 6 | Cambio de contraseña cierra sesión en otros dispositivos (`pw_stamp`) | Por diseño | Sesiones antiguas sin `pw_stamp` se adoptan (no se tiran). |
| 🟡 7 | `encryption.key` inestable entre deploys invalida sesiones/tokens | Vigilar | Fijarla como variable persistente en ambos entornos. |
| 🟠 8 | `origin/main` (rama) sigue en `v1.1.0`; el `main` local está en `v1.1.2` | Vivo | Falta `git push origin main` (lo hace el humano). El código de v1.1.2 sí está en el remoto vía el tag `v1.1.2`. |

---

## 7-bis. Pre-producción (Render + 2ª BBDD de Hostinger)

**Pipeline:** `PR → merge a main → Render redespliega solo → validar → promover a Hostinger a mano`.
Pre-prod va **vinculada a `main`** (no hay rama `PPR`): la puerta a producción es
el deploy manual a Hostinger, no una rama.

### Infra

| | |
|---|---|
| App | Servicio Web de Render, rama `main`, auto-deploy. Región **Frankfurt** (cerca del datacenter EU de Hostinger). |
| BBDD | 2ª base de datos en la cuenta de Hostinger, con su propio usuario (solo esa BBDD). Remote MySQL restringido a las IPs de salida de Render. **Datos, host, usuario y BBDD: en el panel de env de Render** — no se versionan. |
| Datos | de prueba: `DatabaseSeeder` + `BulkDemoDataSeeder` + `PreprodTicketsSeeder`. **Nunca** datos reales. |
| Email | **`RESEND_API_KEY` NO se pone** en Render → `MailService` no envía nada (solo lo registra en `email_log`). Si algún día hace falta probar el flujo de email, ponerla + `MAIL_FROM="JP PRE <onboarding@resend.dev>"` (solo llega al dueño de la cuenta Resend). |
| Banner | píldora roja "PRE-PRODUCCIÓN" centrada en el topbar (variable `APP_ENV_LABEL`). |

### Variables de entorno en Render

Se configuran en el panel de Render (Environment). **Nunca en git.**

```
CI_ENVIRONMENT  = production
APP_BASE_URL    = https://plataforma-jp.onrender.com/
APP_ENV_LABEL   = PPR             # cualquier valor que no sea "produccion" -> banner
DB_HOST         = <host MySQL que muestra hPanel>
DB_PORT         = 3306
DB_NAME         = <2ª BBDD de Hostinger>
DB_USER         = <usuario de esa BBDD>
DB_PASS         = <secreto — solo en Render>
DB_ENCRYPT      = false           # Hostinger remoto va en claro (sin TLS)
ENCRYPTION_KEY  = <64 hex, distinto de prod>
SEED_DEMO       = 1               # solo siembra si la tabla users está vacía
# RESEND_API_KEY : NO ponerla (no queremos que pre-prod mande correos)
```

### Montaje inicial de la BBDD (una vez)

Las migraciones **no corren limpias desde cero** (TICKET-2026-00010). Se carga
clonando el esquema local. Exporta primero las credenciales (de hPanel /
Render) a variables de entorno de tu shell:

```bash
export PREPROD_DB_HOST=...  PREPROD_DB_NAME=...  PREPROD_DB_USER=...  PREPROD_DB_PASS=...

# 1. clonar esquema local -> pre-prod (corre en jp_db)
docker cp docs/operaciones/scripts/preprod-import-schema.sh jp_db:/tmp/imp.sh
docker exec -e PREPROD_DB_HOST -e PREPROD_DB_NAME -e PREPROD_DB_USER -e PREPROD_DB_PASS \
  jp_db sh /tmp/imp.sh

# 2. sembrar (corre en jp_app)
for S in DatabaseSeeder BulkDemoDataSeeder PreprodTicketsSeeder; do
  docker exec -e PREPROD_DB_HOST -e PREPROD_DB_NAME -e PREPROD_DB_USER -e PREPROD_DB_PASS \
    jp_app sh /var/www/html/docs/operaciones/scripts/preprod-spark.sh db:seed "$S"
done
```

Acceso: `sergimallenweb@gmail.com` / `123456` (admin), usuarios demo `Demo1234!`.

### Resetear los datos de pre-producción

```bash
# repite el "montaje inicial" (el import hace DROP de todo primero)
```

### Migración nueva en un PR

Al mergear a `main`, `docker/start.sh` intenta `php spark migrate --all` contra
pre-prod. Si la migración es MariaDB-compatible, se aplica sola. Si falla
(SQL de MySQL-8-only), el `[WARN]` deja arrancar la app igual → aplicarla a
mano en phpMyAdmin de Hostinger, **igual que en producción**.

---

## 8. Rollback

### Render
- Panel de Render → Deploys → seleccionar el deploy anterior estable →
  **Redeploy**. O `git revert` del merge y push a `main`.

### Hostinger
1. Restaurar `public_html/` desde el ZIP de backup (§4.1).
2. Restaurar la BD desde el export de backup **solo si** las migraciones
   rompieron datos (una migración aditiva de columnas/tablas normalmente
   no necesita rollback de datos).
3. Limpiar caché (§4.5).
4. Añadir entrada a `version.json` documentando el rollback.

---

## 9. Anexo — comandos útiles

```bash
# Tests (Docker)
docker compose up -d
docker compose exec app php vendor/bin/phpunit
docker compose exec app php vendor/bin/phpunit tests/unit/AuthGuardServiceTest.php

# Migraciones en local
docker compose exec app php spark migrate --all
docker compose exec app php spark migrate:status

# Limpiar caché
docker compose exec app php spark cache:clear

# Diff de release
git diff --stat main..release/YYYY-MM-DD
git log --oneline main..release/YYYY-MM-DD
git diff --name-only main..release/YYYY-MM-DD -- app/Database/Migrations/

# Comprobar cabeceras y estado de un entorno
curl -sI https://plataforma-jp.onrender.com/login
curl -sI https://app.jppreparation.com/login
```

## 10. Ubicaciones de referencia

| Qué | Dónde |
|-----|-------|
| Front-controller de producción | [`deploy/public/index.php`](../../deploy/public/index.php) |
| `.htaccess` de producción | [`deploy/public/.htaccess`](../../deploy/public/.htaccess) |
| Plantilla `.env` de producción | [`deploy/.env`](../../deploy/.env) |
| SQL v1.1.0 — seguridad (auth) | [`docs/deploy/migraciones_seguridad.sql`](../deploy/migraciones_seguridad.sql) |
| SQL v1.1.0 — posiciones alumno | [`docs/deploy/migraciones_posiciones.sql`](../deploy/migraciones_posiciones.sql) |
| SQL pendientes (histórico) | [`deploy/migraciones_pendientes.sql`](../../deploy/migraciones_pendientes.sql) |
| Arranque del contenedor Render | [`docker/start.sh`](../../docker/start.sh) |
| Config Docker Render/Railway | [`Dockerfile`](../../Dockerfile), [`railway.toml`](../../railway.toml) |
| Registro de versiones | [`app/version.json`](../../app/version.json) |
| Esquema BD | [`docs/BBDD_SCHEMA.md`](../BBDD_SCHEMA.md) |
| Contexto de negocio | [`.claude/CLAUDE.md`](../../.claude/CLAUDE.md) |
