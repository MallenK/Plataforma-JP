# 🚀 Plataforma JP Preparation

Plataforma interna de gestión (backoffice) de la academia **JP Preparation**
(Sant Vicenç dels Horts, Barcelona): jugadores, entrenadores, staff, calendario
de clases, bonos, documentación, mensajería, notificaciones, tickets y
configuración. **No es la web pública de marketing.**

Desarrollada con **CodeIgniter 4 + Docker**. Server-rendered (vistas PHP +
jQuery + JS vanilla por página), sin build step de frontend.

> **Documentación operativa** (léela antes de tocar producción o el repo):
> - [`docs/operaciones/00-COMO-TRABAJAR.md`](docs/operaciones/00-COMO-TRABAJAR.md) — **empieza aquí:** forma de trabajar, ramas, versionado
> - [`docs/operaciones/`](docs/operaciones/) — protocolo de despliegue, modelo de trabajo, organización de GitHub
> - [`docs/BBDD_SCHEMA.md`](docs/BBDD_SCHEMA.md) — esquema de base de datos
> - [`docs/tickets/`](docs/tickets/) — tickets internos
> - [`.claude/CLAUDE.md`](.claude/CLAUDE.md) — contexto de negocio y reglas de arquitectura
>
> **Producción real:** Hostinger — `https://app.jppreparation.com`.
> **Validación:** Render — `https://plataforma-jp.onrender.com`.
> Versión actual: primera entrada de [`app/version.json`](app/version.json).

---

# 🧱 Tecnologías

* PHP 8.2+ (Docker)
* CodeIgniter 4 (`codeigniter4/framework ^4.7`), MVC + capa de **Services**
* MySQL 8 (local / Hostinger MariaDB en prod / TiDB Cloud Serverless en Render)
* Docker + Docker Compose (entorno local)
* Frontend: vistas PHP + jQuery + JS vanilla + Bootstrap Icons (sin bundler)
* Email transaccional: Resend API vía `MailService`
* PHPUnit 10.5 (`tests/unit/`, se ejecuta en Docker)

---

# 🐳 Entorno de desarrollo (Docker)

## ▶️ Levantar el entorno

```bash
docker-compose up -d --build
```

---

## 🛑 Parar el entorno

```bash
docker-compose down
```


## 🛑 Limpiar caché

```bash
docker exec -it jp_app php spark cache:clear
```



---

## 🔍 Ver contenedores activos

```bash
docker ps
```

---

## 💻 Entrar al contenedor PHP

```bash
docker exec -it jp_app bash
```

---

## 🚪 Salir del contenedor

```bash
exit
```

---

## ⚙️ Ejecutar comandos de CodeIgniter

```bash
docker exec -it jp_app php spark                       # lista de comandos
docker exec -it jp_app php spark migrate               # migraciones
docker exec -it jp_app php spark make:filter AuthFilter # generar un filtro
docker exec -it jp_app php vendor/bin/phpunit          # tests
```

---

# 🌐 Acceso a la aplicación

* App: <http://localhost:8080>
* phpMyAdmin: <http://localhost:8081>

---

# 🧪 Base de datos (local)

* Contenedor: `jp_db` (MySQL 8) · BD `jp_preparation`
* Desde el contenedor `jp_app`: host `db`, puerto `3306`
* Desde el host: `localhost:3306` (o phpMyAdmin en `:8081`)

---

# ⚙️ Configuración

## 🔧 Variables de entorno

Editar:

```bash
.env
```

Claves importantes (local):

```env
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
database.default.hostname = db
database.default.database = jp_preparation
# Email (opcional en local): RESEND_API_KEY=... y MAIL_FROM="JP Preparation <hola@jppreparation.com>"
```

El `.env` de producción vive **solo en el servidor**, nunca en git
(base: `deploy/.env`, sin valores reales). `MYSQL_ROOT_PASSWORD` se lee de
`.env` (no está hardcodeado en `docker-compose.yml`).

---

# 📁 Estructura del proyecto

```text
app/
 ├── Controllers/        ← finos: leen input, delegan, devuelven vista/JSON
 ├── Services/           ← LÓGICA DE NEGOCIO (AuthService, ClasesService, ...)
 ├── Models/
 ├── Filters/            ← AuthFilter, RoleFilter, SecurityHeadersFilter
 ├── Views/
 ├── Database/Migrations/ ← migraciones nativas de CI4 (fuente de verdad)
 └── version.json        ← historial de versiones (obligatorio actualizar en cada deploy)

public/assets/{js,css}/  ← JS vanilla por página, sin bundler
docs/                    ← operaciones, esquema BD, tickets
deploy/                  ← front-controller + .env de producción (GITIGNORADO)
```

> Regla no negociable: **la lógica de negocio va en `app/Services/*Service.php`,
> no en los controllers.**

---

# 🔐 Autenticación y autorización

* Login por email/password (bcrypt), `AuthService` + `AuthFilter`, sesión de servidor.
* **Sin auto-registro público** (el formulario `/register` se eliminó en v1.1.2).
  Las altas las hacen admin/superadmin desde la propia plataforma.
* Recuperación de contraseña por email (`/forgot-password` → `/reset-password`).
* Pantalla de cambio de contraseña propia con re-autenticación (`/perfil/password`).
* Endurecimiento (v1.1.0): bloqueo persistente por cuenta/IP, tokens de reset
  hasheados, `must_change_password` en el primer acceso, auditoría en
  `auth_events`, cabeceras de seguridad + HSTS, CSRF activo.
* Roles vía `RoleFilter` declarado por ruta en `app/Config/Routes.php`:
  `superadmin`, `admin`, `coach`, `staff`, `player`. Matriz de permisos
  documentada al inicio de `Routes.php`.

---

# 🛠️ Git & GitHub

## 🔄 Estado del repo

```bash
git status
```

---

## ➕ Añadir cambios

**Nunca `git add .` a ciegas** — el árbol tiene ficheros que no deben entrar
(`deploy/`, `*.zip`, `.env`, `writable/logs/`). Revisar siempre antes:

```bash
git status
git add <rutas concretas>
```

---

## 💾 Commit

```bash
git commit -m "tipo(scope): resumen en imperativo"
```

Conventional Commits. `feat` → *minor* en `version.json`; `fix` → *patch*.

---

## 🌱 Ramas y despliegue

**`main` no se toca a mano** (está protegido). Trunk-based: rama por cambio
(`feat/`, `fix/`, `hotfix/`, `chore/`, `docs/`) → **Pull Request a `main`** →
merge → tag `vX.Y.Z` → deploy. Sin `develop` ni `release/*`.
Guía en una página: [`docs/operaciones/00-COMO-TRABAJAR.md`](docs/operaciones/00-COMO-TRABAJAR.md).
Detalle: [`docs/operaciones/03-organizacion-github.md`](docs/operaciones/03-organizacion-github.md)
y [`docs/operaciones/01-protocolo-despliegue.md`](docs/operaciones/01-protocolo-despliegue.md).

**Cada subida a producción añade una entrada a [`app/version.json`](app/version.json)**
(requisito del cliente; se ve en el panel «Historial de versiones» del sidebar).

---

## 🔄 Traer cambios

```bash
git pull origin main
```

---

# 🚨 Problemas comunes

## ❌ No funciona `php spark`

👉 Ejecuta dentro de Docker:

```bash
docker exec -it jp_app php spark
```

---

## ❌ Rutas con /public

👉 Solucionado configurando Docker para apuntar a `/public`

---

## ❌ AJAX no funciona

* Revisar consola del navegador y Network (POST vs GET)
* CSRF: los endpoints AJAX mandan el header `X-CSRF-TOKEN` y devuelven un
  hash fresco en el JSON de respuesta (ver `mensajes`, `clases`)
* Sesión caducada → los endpoints AJAX devuelven 401/JSON de error, no HTML

---

# 🧩 Módulos

Dashboard · Alumnos · Entrenadores · Clases/Calendario · Bonos · Documentación
· Mensajes · Notificaciones · Tickets · Configuración.
**Torneos** y **Compras** están implementados pero **desactivados** (rutas
comentadas / redirigidas) — no reactivar sin confirmación.

---
