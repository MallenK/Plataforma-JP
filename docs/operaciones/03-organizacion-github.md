# Organización de GitHub — Plataforma JP

Modelo de ramas, flujo de PRs, protección de `main`, convenciones y plan de
limpieza del repositorio <https://github.com/MallenK/Plataforma-JP>.

---

## 1. Estado actual (2026-09-01) — el desorden a corregir

- **28 ramas locales · 15 remotas.** Muchas obsoletas, duplicadas o con
  nombres basura.
- **`main` local va 47 commits por detrás de `origin/main`.** Fuente de
  errores: hay que trabajar siempre contra `origin/main`.
- **Las ramas de trabajo divergen de `origin/main`** (~24 commits que
  `origin/main` tiene y ellas no) porque el histórico se ha integrado con
  **PRs squasheados desde `developing-branch`** (46+ «Merge pull request
  #NN from MallenK/developing-branch»). El contenido es semánticamente
  parecido pero los árboles no coinciden.
- **Dos ramas de integración con nombres distintos:** `Dev` y `dev-auth`
  (+ `developing-branch`, la histórica).
- **Ramas basura:** `checkout`, `cheout`.
- **9 ramas `claude/*`** de sesiones de marzo–mayo, muertas.
- **4 `git worktree`** en `.claude/worktrees/` de sesiones huérfanas.
- **Sin ramas protegidas** ni CI.
- **`deploy/` y `*.zip` no están en `.gitignore`** → riesgo de subir
  secretos / binarios grandes.
- **Commits inconsistentes:** «Tickets» ×5, «cambios test 04/06», «protect
  me» conviven con Conventional Commits en los recientes.

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

## 6. `.gitignore` — añadir

El `.gitignore` actual ignora `.env`, `deploy/.env`, `vendor/`,
`writable/*`, `.claude/`. **Falta:**

```gitignore
# Bundle de despliegue (contiene .env de producción y front-controller)
deploy/

# Artefactos comprimidos
*.zip
/plataforma.zip

# QA manual (opcional — si se prefiere mantenerlos fuera del repo)
# test-app*.md
```

> Si se decide **mantener en el repo** una plantilla de `deploy/` (útil para
> el runbook), entonces ignorar solo lo sensible:
> `deploy/.env` (ya está) y cualquier `deploy/**/*.env`, y **verificar a mano
> antes de cada `git add`** que no entra nada real. La opción segura por
> defecto es ignorar `deploy/` entero y documentar su contenido aquí.

Acción inmediata: sacar `plataforma.zip` (18 MB) del árbol de trabajo.

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
| `developing-branch` | Es la integración histórica. **Renombrar a `develop`** (`git branch -m developing-branch develop`, push, actualizar default de PRs) o crear `develop` limpio desde `origin/main` y borrar esta. |
| `Dev` | Duplicado de integración. Confirmar que su contenido está en `origin/main`; borrar. |
| `dev-auth` | Igual que `Dev`. Su contenido (`186445f`, panel de versiones) parece ya en `origin/main`. Verificar y borrar. |
| `feat/mensajes-polling-adaptativo` | `186445f`, superseded por `origin/main` (`a6e155c`). Verificar y borrar. |

### 8.3 Mantener (activas — 2026-09-01)

`feat/seguridad-auth-hardening`, `feat/email-recuperar-password-y-bienvenida`,
`feat/multiples-posiciones-alumno`, `fix/clases-asignar-admin-staff`,
`fix/overflow-texto-ficha`, `fix/feedback-textarea-desbloqueo`,
`test/integracion-fixes` (transitoria).

→ Todas deben rebasar/mergear sobre el nuevo `develop` (que parte de
`origin/main`) para eliminar la divergencia, y luego seguir el flujo de §3.

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

## 9. Sincronizar `main` local

```bash
git checkout main
git fetch origin
git reset --hard origin/main   # el main local está 47 commits atrás; alinearlo
```

(Seguro: el `main` local no tiene commits propios que no estén en
`origin/main`.)

---

## 10. Higiene continua

- Borrar la rama al cerrar su PR (activar «Automatically delete head
  branches» en Settings → General).
- `git fetch --prune` de forma habitual.
- No dejar worktrees abiertos tras terminar una sesión.
- Revisar `git status` antes de cada `git add`; nunca `git add .` a ciegas
  (por `deploy/`, `*.zip`, `.env`, `writable/logs/`).
- Una rama de integración (`develop`), no tres.
