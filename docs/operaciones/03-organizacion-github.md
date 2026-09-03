# Organización de GitHub — Plataforma JP

Modelo de ramas, flujo de PRs, protección de `main` y convenciones del
repositorio <https://github.com/MallenK/Plataforma-JP>.

---

## 1. Estado (2026-09-03, tras la limpieza)

El repo se ordenó a fondo el 2026-09-03. Punto de partida limpio:

| | Ramas |
|---|---|
| **Local** | `main`, `fix/seguridad-uploads` |
| **Remoto (`origin`)** | `main` únicamente |
| **Tags** | `v1.1.0` (`6b5c687`), `v1.1.2` (`6645d65`) |
| **Worktrees** | ninguno |

- `main` local **=** `origin/main` (`fa2b11d`). Ya no hay commits locales sin
  pushear. Contiene: v1.1.2 + fix del calendario (clases a la misma hora) +
  fix de zona horaria (`Europe/Madrid`) + limpieza del gitlink accidental.
- **`fix/seguridad-uploads`** — la auditoría de seguridad de 2026-09-02
  **rehecha limpia sobre `main`** (cherry-pick de los 2 commits reales, sin la
  basura de skills ni los reverts de dashboard/`version.json` que arrastraba la
  vieja `auditoria/seguridad-2026-09`). 167/167 tests. Pendiente de PR + deploy
  deliberado (ver §6).
- **Backup completo** de todas las ramas antes de borrar:
  `../../../_backups/ramas-backup-2026-09-03.bundle` (fuera del repo, en
  `Desktop/Plataforma-JP/_backups/`). Restaurar una rama:
  `git fetch ../_backups/ramas-backup-2026-09-03.bundle <rama>:<rama>`.

### Qué se borró y por qué

- **~26 ramas ya liberadas o basura.** Todo `feat/*` y `fix/*` de las releases
  v1.1.0 / v1.1.2 (su código ya está en `main` vía los commits de release, que
  fueron squash — por eso git las veía como "no mergeadas"), más las de
  integración duplicadas (`Dev`, `dev-auth`, `develop`, `developing-branch`,
  `release/2026-09`), las basura (`checkout`, `cheout`, `backup-alumnos`,
  `feature/auth-system`) y **13 ramas `claude/*`** de sesiones de agente
  muertas (todas 100+ commits por detrás de `main`, 0 commits propios).
- **4 worktrees huérfanos** en `.claude/worktrees/`.
- El **gitlink accidental** `.claude/worktrees/heuristic-meninsky` que se había
  commiteado a `main` (`.claude/` ya está en `.gitignore`; se coló con `-f`).

---

## 2. Modelo de ramas — trunk-based simple

Con un equipo pequeño y `main` desplegando a Render automáticamente, **no hay
`develop` ni `release/*`**. `main` es el tronco.

```
  feat/*  fix/*  chore/*  docs/*   ← ramas cortas (< 1 semana), salen de main
        \        |        /
         ▼       ▼       ▼
        main  ← siempre desplegable. Cada merge de release → tag vX.Y.Z + version.json
         ▲
         │
     hotfix/*  ← urgencias; igual que fix/ pero con prioridad
```

| Rama | Rol | Reglas |
|------|-----|--------|
| `main` | **Tronco y producción.** Refleja lo desplegado en Hostinger. | Protegida (§4). Solo entra por PR. Verde de tests obligatorio. |
| `feat/<slug>` | Una funcionalidad. | Sale de `main`, vida corta. Se **borra al mergear**. |
| `fix/<slug>` | Un bug. | Sale de `main`. Ideal: 1 commit + 1 ticket + 1 test. Se borra al mergear. |
| `hotfix/<slug>` | Bug urgente en producción. | Igual que `fix/`, solo cambia la prioridad de revisión/deploy. |
| `chore/<slug>`, `docs/<slug>` | Mantenimiento, documentación. | Salen de `main`. Se borran al mergear. |

### Reglas de oro

1. **`main` NO se toca directamente. Nunca.** Ni `commit`, ni `push`, ni
   `merge` local a `main`. Todo cambio — hasta una coma en un `.md` o un bump
   de `version.json` — entra por **rama + Pull Request**. `main` solo cambia
   cuando se hace "Merge" a un PR en GitHub. (Está protegido, §4.)
2. **Una rama = un cambio con nombre claro.** Prefijo obligatorio
   (`feat/ fix/ hotfix/ chore/ docs/`), `kebab-case`, descriptivo. Nada de
   `Dev`, `checkout`, `test`, `cambios`, `wip`.
3. **La rama se borra en cuanto se mergea** (local y remoto). Activar
   "Automatically delete head branches" en Settings → General.
4. **No recrear ramas de integración.** Si algo necesita cocinarse entre varias
   ramas, se hace en una `feat/*` que las va integrando y se borra igual.
5. Ramas de agente (`claude/*`, worktrees): se borran al cerrar su PR o al
   terminar la sesión. No dejar worktrees abiertos.
6. **Cada PR que va a producción toca `version.json`** (§5). Si un PR no sube
   a prod (docs internas, refactor sin efecto visible), no lo toca.

---

## 3. Flujo de trabajo (paso a paso)

```bash
# 1. Partir SIEMPRE de main actualizado
git checkout main && git pull

# 2. Rama nueva con prefijo
git checkout -b fix/hora-espana

# 3. Trabajar: código + (si aplica) ticket en docs/tickets/ + test de regresión
#    Si el cambio va a producción -> añadir entrada a app/version.json (§5)

# 4. Tests en verde (failOnRisky/failOnWarning activos: un warning rompe)
docker compose exec app php vendor/bin/phpunit

# 5. Subir la rama y abrir PR
git push -u origin fix/hora-espana
#    En GitHub: "Compare & pull request" -> base: main
```

6. **PR a `main`.** Descripción: qué, por qué, cómo probarlo, ticket enlazado,
   notas de migración/entorno, **versión nueva** si toca.
7. Revisión: `/code-review` sobre el diff. `/security-review` **obligatorio** si
   toca auth, subida de archivos, permisos, SQL o secretos. Resolver findings.
8. **"Merge pull request"** en GitHub (merge-commit). GitHub borra la rama.
   `git checkout main && git pull` en local; `git branch -d <rama>`.
9. Si el PR subía versión: **tag y deploy** (§5.3).

> Nunca `git merge` a `main` en local ni `git push origin main`. Si te
> equivocas y GitHub te deja (no debería, §4), el merge-commit queda mal
> atribuido y te saltas la revisión.

---

## 4. Protección de `main` — **configurar YA** (GitHub → Settings → Branches → Add rule, branch name `main`)

- [x] **Require a pull request before merging** ← esto es lo que impide tocar `main` a mano.
  - [x] Require approvals: 1 (si trabajas solo, ver nota abajo).
  - [x] Require conversation resolution before merging.
- [x] Require status checks to pass → activar cuando exista CI (§7).
- [x] Require branches to be up to date before merging.
- [x] **Do not allow bypassing the above settings** (aplica también al owner).
- [x] Restrict force-pushes. Restrict deletions.
- Settings → General → **Automatically delete head branches**.

> **Trabajando solo:** GitHub no te deja aprobar tu propio PR. Opciones: (a)
> dejar "Require approvals" en 0 pero mantener "Require a pull request" — ya
> obliga a PR y a que CI pase, que es el 90% del valor; (b) marcar "Allow
> specified actors to bypass" solo para ti como escape de emergencia. Recom.: (a).

---

## 5. Versión — SIEMPRE clara

La versión viva del proyecto **es la primera entrada de `app/version.json`**.
No hay otra fuente. El panel "Historial de versiones" del sidebar
(admin/superadmin) la muestra. Es un requisito explícito del cliente.

### 5.1 Numeración — SemVer `MAJOR.MINOR.PATCH`

| Parte | Cuándo sube | Ejemplo |
|-------|-------------|---------|
| **PATCH** (`1.1.3 → 1.1.4`) | Un `fix`: corriges algo que estaba mal, sin cambiar cómo se usa. | Fix de la hora, del calendario. |
| **MINOR** (`1.1.4 → 1.2.0`) | Un `feat`: funcionalidad nueva o mejora visible, compatible con lo anterior. | Nuevo módulo, campo nuevo, pantalla nueva. |
| **MAJOR** (`1.x.x → 2.0.0`) | Cambio grande o incompatible: rediseño gordo, romper un flujo, migración de datos que obliga a algo. | Se decide a mano, es raro. |

- Varios cambios en un mismo deploy → **una sola entrada** con la subida mayor
  (si hay 1 feat + 2 fixes → MINOR) y la descripción los resume todos.
- El número **nunca baja ni se reutiliza**.

### 5.2 Entrada en `version.json` (dentro del PR, la primera del array)

```json
{
    "version": "1.1.4",
    "date": "2026-09-03 17:00:00",
    "description": "Texto para el cliente, en español llano, sin jerga técnica: qué nota el usuario. Nada de nombres de fichero ni de 'appTimezone'."
}
```

- `date` = fecha/hora **real del deploy a Hostinger** (`Y-m-d H:i:s`, hora de
  España). Si al abrir el PR no la sabes, pon la estimada y ajústala antes de
  mergear.
- `description` la lee gente no técnica: resultado visible, no implementación.

### 5.3 Tag + deploy (tras mergear el PR)

```bash
git checkout main && git pull
git tag v1.1.4
git push origin v1.1.4
```

Luego deploy a Hostinger (§ "Qué subir" en
[`01-protocolo-despliegue.md`](01-protocolo-despliegue.md)). Render ya se ha
desplegado solo al mergear.

### 5.4 Convención de commits

**Conventional Commits:** `<tipo>(<scope>): <resumen imperativo, minúscula, sin punto>`.
Tipos: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `perf`, `build`.
El commit de subida de versión: `chore(release): vX.Y.Z — <resumen corto>`.

---

## 6. `fix/seguridad-uploads` — cómo cerrarla

La rama trae el endurecimiento de subida de archivos (uploads fuera de
`public/`, `upload_helper.php`, `MigrateUploadsOutOfWebroot`, logout POST,
rename de CSRF a `jp_csrf_token`, hardening de `Notificaciones::send`).
Informe: `docs/auditoria/2026-09-02-auditoria-seguridad.md`.

**Tiene pasos de deploy que exigen ventana:**

1. PR a `main` + `/security-review` + `/code-review`.
2. El rename de `tokenName`/`cookieName` de CSRF **invalida los formularios
   abiertos** → desplegar en horario de bajo tráfico.
3. Tras desplegar en Hostinger, por SSH:
   `php spark uploads:migrate` (mueve adjuntos ya subidos a `writable/uploads/`;
   hay fallback, no es bloqueante).
4. Bump `version.json` + tag.

---

## 7. CI (pendiente — no existe `.github/workflows/`)

Workflow mínimo por PR a `main`: levantar MySQL de servicio, `composer
install`, `php spark migrate --all` sobre BD de test, `vendor/bin/phpunit`.
Con eso se activa "Require status checks" en la protección de `main`.

---

## 8. Comandos útiles

```bash
# Borrar rama tras mergear
git branch -d <rama> && git push origin --delete <rama>

# Podar referencias remotas que ya no existen
git fetch --prune

# Ver qué ramas ya están en main
git branch --merged main

# Restaurar una rama del backup del 2026-09-03
git fetch ../_backups/ramas-backup-2026-09-03.bundle <rama>:<rama>
```

---

## 9. Higiene continua

- Borrar la rama al cerrar su PR.
- `git fetch --prune` de forma habitual.
- No dejar worktrees abiertos tras terminar una sesión (`git worktree list`).
- Revisar `git status` antes de cada `git add`; **nunca `git add .` a ciegas**
  (`deploy/`, `*.zip`, `.env`, `writable/logs/`, `.claude/`).
- `main` local siempre alineado con `origin/main`. Para realinear sin commits
  propios: `git fetch origin && git reset --hard origin/main`.
