# Modelo de trabajo — Plataforma JP

Informe de cómo se desarrolla, prueba y entrega el software de este
proyecto. Complementa a [`.claude/CLAUDE.md`](../../.claude/CLAUDE.md)
(contexto de negocio y reglas de arquitectura).

---

## 1. Stack y arquitectura (resumen)

- **Backend:** PHP 8.2+ · CodeIgniter 4 (`codeigniter4/framework ^4.7`). MVC
  clásico con una capa de **Services** para la lógica de negocio.
- **BD:** MySQL 8. Migraciones nativas de CodeIgniter en
  `app/Database/Migrations/`.
- **Frontend:** vistas PHP server-rendered + jQuery + JS vanilla por página
  (`public/assets/js/*.js`) + Bootstrap Icons + librería propia de
  componentes accesibles (`public/assets/js/radix-ui.js`). **Sin build step.**
- **Auth:** login email/password (bcrypt), `AuthService` + `AuthFilter`,
  sesión de servidor. Rate-limiting persistente y auditoría en `auth_events`.
- **Autorización:** `RoleFilter` declarado por ruta en `app/Config/Routes.php`.
  Roles: `superadmin`, `admin`, `coach`, `staff`, `player`.
- **Email transaccional:** Resend API vía `MailService`; todos los envíos se
  registran en `email_log`.

### Regla de arquitectura no negociable

> **La lógica de negocio va en `app/Services/*Service.php`, no en los
> controllers.** Los controllers leen input, delegan y devuelven vista/JSON.
> Patrón de referencia: `AuthService`, `ClasesService`, `DocumentService`,
> `PlayerService`, `CoachService`, `ConfiguracionService`.

---

## 2. Entorno local

- **Docker Compose** (`docker-compose.yml`): `jp_app` (Apache+PHP 8.2),
  `jp_db` (MySQL 8), `jp_phpmyadmin`.
- **No hay PHP ni Composer instalados en la máquina host.** Todo comando de
  PHP/Spark/PHPUnit se ejecuta dentro del contenedor.
- `MYSQL_ROOT_PASSWORD` se lee de `.env` (variable `MYSQL_ROOT_PASSWORD`), no
  está hardcodeado.

```bash
docker compose up -d --build     # levantar
docker compose exec app bash     # entrar al contenedor
docker compose exec app php spark migrate --all
docker compose down              # parar
```

- App: <http://localhost:8080> · phpMyAdmin: <http://localhost:8081>

---

## 3. Ciclo de vida de un cambio

```
  Ticket  ─▶  Rama  ─▶  Implementación  ─▶  Test  ─▶  PR a main  ─▶  Tag + version.json  ─▶  Deploy
 docs/tickets/     feat|fix/*        Services       tests/unit/                              Render→Hostinger
```

### 3.1 Ticket

Cada cambio con entidad propia se documenta en `docs/tickets/TICKET-NNN-<slug>.md`
**antes o durante** la implementación. Estructura usada:

- Tabla de cabecera (Categoría, Prioridad, Estado, Módulo, Rama).
- **Contexto** — qué pasa y por qué.
- **Causa raíz**.
- **Solución aplicada** — desglose por fichero/área.
- **Bloqueantes** — acciones manuales necesarias (⚠️).
- **Notas de despliegue** — migraciones, variables, orden.
- **Verificación** — qué tests lo cubren y qué probar a mano.

Ejemplos: `docs/tickets/TICKET-002` … `TICKET-005`.

### 3.2 Rama

Una rama por ticket/tema. Prefijos `feat/`, `fix/`, `hotfix/`, `chore/`,
`docs/`, `release/`. Detalle en
[`03-organizacion-github.md`](03-organizacion-github.md).

Patrón observado en las ramas de fix recientes: **1 commit + 1 ticket + 1
test por rama**, lo que las hace fáciles de revisar y de fusionar o
descartar por separado.

### 3.3 Implementación

- Cambios de negocio → Service. Controller fino.
- Toda ruta que modifica datos declara `'filter' => ['auth', 'role:...']`
  según la matriz de permisos documentada al inicio de `Routes.php`.
- Mobile-first en vistas de alumno/coach (se usan a pie de campo); las
  vistas de gestión (admin/configuración) priorizan escritorio.
- Nunca commitear secretos (`.env`, `deploy/.env`, `users.txt`,
  `database.txt`, `*.zip`).

### 3.4 Tests

- PHPUnit 10.5, config en `phpunit.xml.dist`, suite `App` → `./tests`.
- Tests en `tests/unit/`. Bootstrap del framework CI4.
- Ejecutar **siempre en Docker**:
  ```bash
  docker compose exec app php vendor/bin/phpunit          # toda la suite
  docker compose exec app php vendor/bin/phpunit tests/unit/AuthGuardServiceTest.php
  ```
- `failOnRisky` y `failOnWarning` están activados: un warning rompe el build.
- Regla práctica: **cada fix lleva su test de regresión**; cada feature con
  lógica en Service lleva tests del Service.
- Además hay documentos de QA manual por rol en la raíz
  (`test-app*.md`) — batería funcional que se repasa antes de releases
  grandes.

### 3.5 Revisión

Antes de fusionar a la rama de integración / `main`:

- `/code-review` sobre el diff (reuso, simplificación, eficiencia, bugs).
- `/security-review` cuando el cambio toca auth, subida de archivos,
  permisos, SQL o secretos.
- Para releases grandes: `/code-review ultra`.

### 3.6 Integración y release

- **Trunk-based:** cada `feat/*`/`fix/*` sale de `main` y vuelve a `main` por
  PR. **No hay `develop` ni `release/*`** (ver
  [`03-organizacion-github.md`](03-organizacion-github.md) §2).
- Antes de mergear: suite completa en verde + `/code-review` (+
  `/security-review` si toca auth/subidas/permisos/SQL/secretos).
- Para subir a producción: bump `version.json` + tag `vX.Y.Z` + deploy.
- Ver [`01-protocolo-despliegue.md`](01-protocolo-despliegue.md).

### 3.7 Registro de versión

**Obligatorio y permanente:** cada subida a producción añade una entrada a
`app/version.json` (más reciente primero: `version`, `date` `Y-m-d H:i:s`,
`description`). El panel «Historial de versiones» del sidebar
(admin/superadmin) lo muestra. Es un requisito explícito del cliente.

---

## 4. Gestión de secretos

| Secreto | Dónde vive | En git |
|---------|-----------|--------|
| `.env` local | máquina de cada dev | ❌ (`.gitignore`) |
| `.env` de Hostinger | `public_html/app/.env` en el servidor | ❌ |
| `.env` de Render | variables de entorno del panel de Render | ❌ |
| `deploy/` (plantilla + SQL) | repo local, **sin valores reales** | ❌ (`deploy/` entero en `.gitignore`) |
| `RESEND_API_KEY` | `.env` de cada entorno (`re_au3n6kdT_…`) | ❌ — válida, dominio `jppreparation.com` verificado |
| `MYSQL_ROOT_PASSWORD` | `.env` local (Docker) | ❌ |

Los `.sql` de migración para producción sí van en el repo, en `docs/deploy/`.

---

## 5. Deuda técnica conocida

Fuente principal: sección «Deuda técnica y seguridad conocida» de
[`.claude/CLAUDE.md`](../../.claude/CLAUDE.md). Estado verificado a
2026-09-01 (tras release v1.1.2):

| Punto | Estado real |
|-------|-------------|
| CSRF deshabilitado globalmente | ✅ **Reactivado** en `app/Config/Filters.php` (`csrf` activo en `globals.before`, `tokenRandomize=true`). CLAUDE.md está desactualizado en esto. |
| `MYSQL_ROOT_PASSWORD` hardcodeado en `docker-compose.yml` | ✅ **Resuelto** — ahora `${MYSQL_ROOT_PASSWORD}` desde `.env`. |
| `secureheaders` / `honeypot` desactivados | 🟡 `honeypot` sigue comentado. `secureheaders` sustituido por `SecurityHeadersFilter` propio (activo en `globals.after`) — verificado en producción (cabeceras + HSTS presentes). |
| `deploy/` fuera de `.gitignore` | ✅ **Resuelto** — `.gitignore` ignora `deploy/` entero y `*.zip`. |
| `RESEND_API_KEY` real commiteada | ✅ **Resuelto** — la key anterior quedó revocada; la actual (`re_au3n6kdT_…`) vive solo en el `.env` de cada entorno y el dominio `jppreparation.com` está verificado en Resend. |
| Módulos Torneos y Compras | Desactivados a propósito (rutas comentadas / redirigidas). No reactivar sin confirmación. |

Otros:
- Sin CI en GitHub Actions (`.github/workflows/` no existe). Los tests se
  corren a mano en Docker. → Candidato a añadir workflow que ejecute PHPUnit
  en cada PR.
- Carpeta legacy `Migrations_02` + `Database.zip`: histórico antiguo, **no
  es la fuente de verdad**, no tocar salvo petición explícita.
- Historial de commits inconsistente (mensajes tipo «Tickets», «cambios test
  04/06», «protect me») frente a los recientes con Conventional Commits.

---

## 6. Snapshot del repo — 2026-09-03 (tras la limpieza)

El repo se ordenó a fondo el 2026-09-03: se borraron ~26 ramas ya liberadas o
basura y 4 worktrees huérfanos. Estado limpio y detalle completo en
[`03-organizacion-github.md`](03-organizacion-github.md) §1.

- **Local = remoto:** `main` (`fa2b11d`) sin commits sin pushear. Solo queda
  además la rama `fix/seguridad-uploads` (auditoría de seguridad rehecha limpia
  sobre `main`, pendiente de PR + deploy con ventana — §6 de `03`).
- **Backup** de todas las ramas previas:
  `Desktop/Plataforma-JP/_backups/ramas-backup-2026-09-03.bundle`.
- Añadido sobre v1.1.2: fix del solapamiento de clases en el calendario, fix de
  zona horaria (`appTimezone` pasó de `Europe/London` a `Europe/Madrid` — la
  plataforma iba 1h por detrás de España).

### Histórico — trabajo consolidado en v1.1.0 / v1.1.2 (2026-09-01)

| Área | Qué entró | Contenido |
|------|-----------|-----------|
| Seguridad de auth (TICKET-005) | v1.1.0 | `AuthGuardService`, tabla `auth_events`, bloqueo por cuenta/IP, tokens de reset hasheados (sha256), `must_change_password`, pantalla `/perfil/password`, `SecurityHeadersFilter`, CSRF reactivado. |
| Email transaccional (TICKET-003) | v1.1.0 | `MailService` con `email_log`, `sendWelcomeEmail()`, `sendPasswordChangedEmail()`, recuperación de contraseña operativa. `MAIL_FROM` → `hola@jppreparation.com`. |
| Posiciones múltiples | v1.1.0 | Varias posiciones por alumno en `PlayerProfileModel` (JSON) + checkboxes en alta/edición + `formatPositions()` (`Extremo / Mediapunta`). Overflow de texto en tarjetas de métrica corregido (TICKET-002). |
| Clases | v1.1.0 | Admin/staff pueden ser responsables de una sesión (TICKET-001). Feedback «Después» editable en cuanto se imparte la clase o se pasa lista (TICKET-004). |
| Rediseño de acceso | v1.1.2 | Login con fondo Aura Gradient + tarjeta de cristal, pantallas de recuperar/restablecer a juego, casilla «Recuérdame» (sesión 7 días), se elimina el formulario de auto-registro. |

- **Cómo se consolidó:** las 6 ramas `feat/*`/`fix/*` salían de `dev-auth`
  (`186445f`), no de `origin/main` (`a6e155c`). Se **cherry-pickearon los ~12
  commits propios** sobre `a6e155c` en vez de mergear `dev-auth` entero (que
  arrastraba componentes Radix que el cliente no quería). El rediseño de login
  se trajo aparte en v1.1.2. Detalle en
  [`03-organizacion-github.md`](03-organizacion-github.md) §1.
- **Migraciones en producción:** `spark migrate` **no funciona con TiDB
  Serverless** (Render) — `AUTO_INCREMENT` roto en la tabla `migrations`. Las
  migraciones de esta release se aplicaron a mano en phpMyAdmin de Hostinger
  con los `.sql` de `docs/deploy/` (`migraciones_seguridad.sql`,
  `migraciones_posiciones.sql`). Render quedó sin validar esta vuelta.
- Todas las ramas transitorias de esta consolidación se borraron el
  2026-09-03 (ver [`03-organizacion-github.md`](03-organizacion-github.md) §1).
