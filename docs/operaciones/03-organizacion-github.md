# Organización de GitHub — Plataforma JP

Modelo de ramas, flujo de PRs, protección de `main`, convenciones y plan de
limpieza del repositorio <https://github.com/MallenK/Plataforma-JP>.

---

## 1. Estado (2026-09-01, tras release v1.1.2)

- **Release v1.1.2 desplegada en Hostinger y verificada** (cabeceras de
  seguridad, HSTS, bloqueo de login, `/perfil/password`, `auth_events`).
  Contiene el trabajo de 2026-09-01: endurecimiento de auth, posiciones
  múltiples, admin/staff en clases, feedback de sesión, email transaccional
  y el rediseño de las pantallas de acceso.
- **Tags pusheados:** `v1.1.0` (`6b5c687`) y `v1.1.2` (`6645d65`). El commit
  de v1.1.2 ya está en el remoto **a través del tag**.
- ⚠️ **`origin/main` (rama) sigue apuntando a `v1.1.0`** (`6b5c687`). El
  `main` local está en `v1.1.2` (`6645d65`), 2 commits por delante. Falta el
  `git push origin main` — **lo bloquea el clasificador de Claude Code**, lo
  hace el humano a mano (los tags sí se pushean desde el agente).
- **Cómo se consolidó:** las 6 ramas `feat/*`/`fix/*` salían de `dev-auth`
  (`186445f`), no de `origin/main` (`a6e155c`). En vez de mergear `dev-auth`
  entero (arrastraba componentes Radix, etc. que el cliente no quería), se
  **cherry-pickearon los ~12 commits propios** sobre `a6e155c`. El rediseño
  de login se trajo aparte en v1.1.2 (rama `feat/login-redesign`).
- Ramas locales transitorias a borrar tras validar: `develop`,
  `release/2026-09`, `feat/login-redesign`, y las 6 `feat/*`/`fix/*` de la
  release (ver §8.3).

### Pendiente de limpieza (heredado)

- **Ramas de integración duplicadas:** `Dev`, `dev-auth`, `developing-branch`
  → dejar solo `develop`.
- **Ramas basura:** `checkout`, `cheout`. **9 ramas `claude/*`** muertas.
- **`git worktree`** huérfanos en `.claude/worktrees/`.
- **Sin ramas protegidas** ni CI.
- ~~`deploy/` y `*.zip` fuera de `.gitignore`~~ → **hecho** (ya se ignoran).

---

## 2. Modelo de ramas propuesto

Git Flow ligero, adaptado a que **`main` despliega a Render automáticamente**.

```
 feat/*  fix/*  chore/*  docs/*
        \        |       /
         ▼       ▼      ▼
        develop  ← integración continua, siempre desplegable a local
            │
            ▼
        release/X.Y.Z  ← congelada; QA en Render; version.json; hardening final
            │
            ▼
          main  ← = producción. Cada merge = deploy a Render. Tag vX.Y.Z.
            ▲
            │
        hotfix/*  ← urgencias sobre producción; se mergea a main Y a develop
```

| Rama | Rol | Reglas |
|------|-----|--------|
| `main` | **Producción.** Refleja lo desplegado. | Protegida. Solo merge por PR desde `release/*` o `hotfix/*`. Cada merge → tag `vX.Y.Z` + entrada en `version.json`. |
| `develop` | Integración. | Recibe PRs de `feat/*`, `fix/*`, etc. Debe quedar siempre en verde de tests. |
| `feat/<slug>` | Una funcionalidad. | Sale de `develop`. 1 tema por rama. Se borra al mergear. |
| `fix/<slug>` | Un bug no urgente. | Sale de `develop`. Ideal: 1 commit + 1 ticket + 1 test. |
| `hotfix/<slug>` | Urgencia en producción. | Sale de `main`. Se mergea a `main` **y** a `develop`. |
| `release/X.Y.Z` | Preparación de una entrega. | Sale de `develop`. Solo se le hacen ajustes de release (version.json, textos, fixes de QA). |
| `chore/<slug>`, `docs/<slug>` | Mantenimiento, documentación. | Sale de `develop`. |

### Convención de nombres

- Prefijo obligatorio: `feat/ fix/ hotfix/ release/ chore/ docs/`.
- `kebab-case`, en español o inglés pero consistente, corto y descriptivo:
  `feat/email-recuperar-password`, `fix/descarga-documentos-503`.
- Nada de nombres genéricos (`checkout`, `Dev`, `test`, `cambios`).

---

## 3. Flujo de trabajo (PRs)

1. `git checkout develop && git pull`
2. `git checkout -b feat/mi-cambio`
3. Implementar + ticket en `docs/tickets/` + tests.
4. `docker compose exec app php vendor/bin/phpunit` en verde.
5. Push + **Pull Request a `develop`**. Descripción: qué, por qué, cómo
   probarlo, ticket enlazado, notas de migración/entorno.
6. Revisión: `/code-review` (y `/security-review` si toca auth, subidas,
   permisos, SQL o secretos). Resolver findings.
7. Merge a `develop` (squash o merge-commit, ver §5). Borrar la rama.
8. Para entregar: `release/X.Y.Z` desde `develop` → QA en Render →
   PR a `main` → tag → deploy Hostinger
   (ver [`01-protocolo-despliegue.md`](01-protocolo-despliegue.md)).

---

## 4. Protección de `main` (configurar en GitHub → Settings → Branches)

- [ ] Require a pull request before merging (1 aprobación mínimo).
- [ ] Require status checks to pass (cuando haya CI, ver §7).
- [ ] Require branches to be up to date before merging.
- [ ] Require conversation resolution before merging.
- [ ] Do not allow bypassing the above settings.
- [ ] Restrict force-push y deletion.
- (Recomendado lo mismo, más laxo, para `develop`.)

---

## 5. Convención de commits

Adoptar **Conventional Commits** (ya se usa en los recientes):

```
<tipo>(<scope opcional>): <resumen en imperativo, minúscula, sin punto>

<cuerpo opcional: qué y por qué, no cómo>

<footer opcional: BREAKING CHANGE, Refs #ticket, Co-Authored-By>
```

Tipos: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `perf`, `build`.

- `feat` → *minor* en `version.json`; `fix` → *patch*.
- Un commit = un cambio coherente. Nada de «cambios», «varios», «wip» en
  `develop`/`main`.

---

## 6. `.gitignore` — hecho

Desde v1.1.0 el `.gitignore` ya ignora **`deploy/` entero** (contiene
`deploy/.env` con secretos y el front-controller de producción) y **`*.zip`**
además de lo anterior (`.env`, `vendor/`, `writable/*`, `.claude/`, …).

El contenido de `deploy/` se documenta en
[`01-protocolo-despliegue.md`](01-protocolo-despliegue.md) §10. Los `.sql` de
migración para producción se guardan **en el repo** bajo `docs/deploy/`.

Pendiente: sacar `plataforma.zip` (18 MB) del árbol de trabajo (no se
commitea porque está ignorado, pero estorba).

---

## 7. CI (pendiente — no existe `.github/workflows/`)

Añadir un workflow mínimo que en cada PR a `develop`/`main`:

1. Levante MySQL de servicio.
2. `composer install`.
3. `php spark migrate --all` sobre una BD de test.
4. `vendor/bin/phpunit`.

Con eso se puede activar «Require status checks» en la protección de `main`.

---

## 8. Plan de limpieza de ramas

> Ejecutar **coordinando con quien tenga sesiones/worktrees abiertos**.
> Antes de borrar nada remoto, confirmar que su contenido está en
> `origin/main` o ya no hace falta. Se puede archivar como tag:
> `git tag archive/<nombre> <rama> && git push origin archive/<nombre>`.

### 8.1 Borrar ya (local y remoto)

| Rama | Motivo |
|------|--------|
| `checkout`, `cheout` | Nombres basura, locales, obsoletas (mayo). |
| `claude/blissful-ardinghelli-4404c6` | Sesión muerta (mar–may). |
| `claude/busy-hopper-c16659` | Sesión muerta. |
| `claude/competent-perlman-0f4635` | Sesión muerta (solo local). |
| `claude/eager-hodgkin-34c966` | Sesión muerta. |
| `claude/heuristic-meninsky` | Sesión muerta (solo local, tiene worktree). |
| `claude/hopeful-khorana-73601d` | Sesión muerta. |
| `claude/intelligent-lumiere-2f7605` | Sesión muerta. |
| `claude/jovial-hellman` | Sesión muerta. |
| `claude/nice-brahmagupta-dd489d` | Sesión muerta (tiene worktree). |
| `claude/nostalgic-bouman-5f641c` | Sesión muerta (tiene worktree). |
| `backup-alumnos` | Backup de abril, 129 commits atrás. Archivar como tag si se quiere. |
| `feature/auth-system` | Abril, 129 atrás, ya integrada hace tiempo. |
| `hotfix/mensajes-cliente` | Idéntica a `origin/main`. Ya mergeada. |
| `refactor/asistencia-unificada` (remoto) | Mergeada vía PR #38. |

### 8.2 Consolidar y luego borrar

| Rama | Acción |
|------|--------|
| `developing-branch` | Integración histórica. Crear `develop` limpio desde `origin/main` y borrar esta. |
| `Dev` | Duplicado de integración. Verificar que su contenido está en `origin/main`; borrar. |
| `dev-auth` (`186445f`) | Su trabajo **ya está en `main` (`v1.1.2`)** vía cherry-pick (v1.1.0 + el rediseño de login en v1.1.2). Verificar y borrar. |
| `feat/mensajes-polling-adaptativo` | Local, = `dev-auth`. Borrar junto con `dev-auth`. |

### 8.3 Ramas de la release v1.1.2 — YA integradas en `main`

`feat/seguridad-auth-hardening`, `feat/email-recuperar-password-y-bienvenida`,
`feat/multiples-posiciones-alumno`, `fix/clases-asignar-admin-staff`,
`fix/overflow-texto-ficha`, `fix/feedback-textarea-desbloqueo`,
`feat/login-redesign` → todo esto está en `main` (`v1.1.2`, `6645d65`).
`develop` y `release/2026-09` fueron transitorias.

→ **Borrar todas** una vez el `git push origin main` esté hecho y confirmado
el humo en producción. Trabajo futuro: crear `develop` desde `origin/main` y
ramas `feat/*`/`fix/*` desde ahí (§3).

### 8.4 Worktrees huérfanos

```bash
# Desde el worktree principal:
git worktree list
git worktree remove .claude/worktrees/busy-hopper-c16659
git worktree remove .claude/worktrees/heuristic-meninsky
git worktree remove .claude/worktrees/nice-brahmagupta-dd489d
git worktree remove .claude/worktrees/nostalgic-bouman-5f641c
git worktree prune
```

(`.claude/` ya está en `.gitignore`, así que no ensucian el repo, pero
conviene limpiarlos.)

### 8.5 Comandos de limpieza

```bash
# Borrar rama local
git branch -D <rama>

# Borrar rama remota
git push origin --delete <rama>

# Podar referencias remotas que ya no existen
git fetch --prune

# Ver ramas ya mergeadas en origin/main (candidatas a borrar)
git branch --merged origin/main
```

---

## 9. Sincronizar `main`

**Ahora mismo es al revés:** el `main` local (`6645d65`, v1.1.2) va **por
delante** de `origin/main` (`6b5c687`, v1.1.0). Lo que falta es subir el local:

```bash
git checkout main
git push origin main   # lo bloquea el clasificador del agente → hazlo tú
```

Después, cualquier `main` local desalineado se realinea con:

```bash
git fetch origin
git reset --hard origin/main   # seguro solo si tu main local no tiene commits propios
```

---

## 10. Higiene continua

- Borrar la rama al cerrar su PR (activar «Automatically delete head
  branches» en Settings → General).
- `git fetch --prune` de forma habitual.
- No dejar worktrees abiertos tras terminar una sesión.
- Revisar `git status` antes de cada `git add`; nunca `git add .` a ciegas
  (por `deploy/`, `*.zip`, `.env`, `writable/logs/`).
- Una rama de integración (`develop`), no tres.
