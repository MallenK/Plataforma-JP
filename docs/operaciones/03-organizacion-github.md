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

1. **Una rama = un cambio con nombre claro.** Prefijo obligatorio
   (`feat/ fix/ hotfix/ chore/ docs/`), `kebab-case`, descriptivo. Nada de
   `Dev`, `checkout`, `test`, `cambios`, `wip`.
2. **La rama se borra en cuanto se mergea** (local y remoto). Activar
   "Automatically delete head branches" en Settings → General.
3. **`main` se pushea siempre tras commitear.** No acumular commits locales.
4. **No recrear ramas de integración.** Si algo necesita cocinarse entre varias
   ramas, se hace en una `feat/*` que las va integrando y se borra igual.
5. Ramas de agente (`claude/*`, worktrees): se borran al cerrar su PR o al
   terminar la sesión. No dejar worktrees abiertos.

---

## 3. Flujo de trabajo (PR)

1. `git checkout main && git pull`
2. `git checkout -b fix/mi-cambio`
3. Implementar + ticket en `docs/tickets/TICKET-NNN-*.md` + test de regresión.
4. `docker compose exec app php vendor/bin/phpunit` en verde
   (`failOnRisky`/`failOnWarning` activos: un warning rompe el build).
5. Push + **Pull Request a `main`**. Descripción: qué, por qué, cómo probarlo,
   ticket enlazado, notas de migración/entorno.
6. Revisión: `/code-review` sobre el diff. `/security-review` **obligatorio**
   si toca auth, subida de archivos, permisos, SQL o secretos. Resolver
   findings.
7. Merge a `main` (merge-commit, ver §5). **Borrar la rama.**
8. Si la subida va a producción: bump de `app/version.json`, tag `vX.Y.Z`,
   `git push origin main --tags`, deploy a Hostinger
   (ver [`01-protocolo-despliegue.md`](01-protocolo-despliegue.md)).

---

## 4. Protección de `main` (GitHub → Settings → Branches)

- [ ] Require a pull request before merging.
- [ ] Require status checks to pass (cuando exista CI, §7).
- [ ] Require branches to be up to date before merging.
- [ ] Require conversation resolution before merging.
- [ ] Restrict force-push y deletion.
- [ ] Settings → General → "Automatically delete head branches".

---

## 5. Convención de commits

**Conventional Commits:**

```
<tipo>(<scope>): <resumen imperativo, minúscula, sin punto>

<cuerpo: qué y por qué, no cómo>

<footer: BREAKING CHANGE, Refs #ticket, Co-Authored-By>
```

Tipos: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `perf`, `build`.
`feat` → *minor* en `version.json`; `fix` → *patch*. Un commit = un cambio
coherente.

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
