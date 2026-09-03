# Cómo trabajar en este proyecto

Guía corta. Si solo lees un documento, que sea este. El detalle está en los
demás archivos de `docs/operaciones/`.

---

## Las 5 reglas

1. **`main` no se toca nunca a mano.** Ni `commit`, ni `push`, ni `merge` local
   a `main`. Todo cambio entra por **rama + Pull Request**. `main` está
   protegido en GitHub (ruleset "protect main").
2. **Una rama por cambio**, con prefijo: `feat/`, `fix/`, `hotfix/`, `chore/`,
   `docs/`. Nombre corto y descriptivo en `kebab-case`. Se borra al mergear.
3. **La lógica de negocio va en `app/Services/*Service.php`**, no en los
   controllers (que quedan finos: leen input, delegan, devuelven vista/JSON).
4. **Cada fix lleva su test de regresión.** La suite tiene que quedar en verde
   (`failOnWarning` activo: un warning rompe el build).
5. **Cada cambio que va a producción sube la versión** en `app/version.json`
   (SemVer: `fix` → PATCH, `feat` → MINOR) y se etiqueta con `git tag vX.Y.Z`.

---

## El ciclo, paso a paso

```powershell
# 1. Partir de main actualizado
git checkout main ; git pull

# 2. Rama nueva
git checkout -b fix/lo-que-sea

# 3. Trabajar:
#    - código (negocio → Service)
#    - si tiene entidad: ticket en docs/tickets/TICKET-NNN-slug.md
#    - test de regresión en tests/unit/
#    - si va a producción: nueva entrada en app/version.json (la primera del array)

# 4. Tests en verde
docker compose exec app php vendor/bin/phpunit

# 5. Subir la rama
git push -u origin fix/lo-que-sea
```

6. **Abrir Pull Request a `main`** en GitHub. Descripción: qué, por qué, cómo
   probarlo, ticket enlazado, notas de despliegue, versión nueva si aplica.
7. **Revisión:** `/code-review` sobre el diff. `/security-review` **obligatorio**
   si toca auth, subida de archivos, permisos, SQL o secretos.
8. **"Merge pull request"** en GitHub. GitHub borra la rama sola.
9. En local: `git checkout main ; git pull`.
10. Si el PR subía versión: `git tag vX.Y.Z ; git push origin vX.Y.Z` y **desplegar**.

> PowerShell 5.1 no acepta `&&`. Encadena con `;` o pon un comando por línea.

---

## Versión — siempre clara

- **La versión viva del proyecto = la primera entrada de `app/version.json`.**
  No hay otra fuente. El panel "Historial de versiones" del sidebar la muestra.
- SemVer `MAYOR.MENOR.PARCHE`:
  - **PARCHE** — arreglas algo roto sin cambiar cómo se usa (`fix`).
  - **MENOR** — funcionalidad nueva compatible (`feat`).
  - **MAYOR** — cambio incompatible o rediseño grande (raro, se decide a mano).
- Varios cambios en un mismo deploy → **una sola entrada**, con la subida mayor
  y una descripción que los resume.
- La `description` la lee gente **no técnica**: qué nota el usuario, sin jerga
  ni nombres de fichero.

---

## Despliegue (resumen)

1. Merge del PR → **Render** se despliega solo (entorno de validación).
2. Se verifica en Render.
3. **Hostinger** (producción real, `app.jppreparation.com`) es **manual**: se
   suben los ficheros por File Manager / FTP respetando la estructura de
   carpetas. La plataforma vive en `public_html/app/` (ahí está su `index.php`);
   **no** confundir con `public_html/` a secas, que es la web pública.
4. `git tag vX.Y.Z` + push del tag.

El `.htaccess` de producción está personalizado a mano y **no** es el del repo
— no lo sobrescribas. Detalle y checklist en
[`01-protocolo-despliegue.md`](01-protocolo-despliegue.md).

---

## Documentos relacionados

| Documento | Para qué |
|-----------|----------|
| [`01-protocolo-despliegue.md`](01-protocolo-despliegue.md) | Pasos exactos de despliegue, humo, rollback |
| [`02-modelo-de-trabajo.md`](02-modelo-de-trabajo.md) | Entorno local, Services, tickets, tests, historial |
| [`03-organizacion-github.md`](03-organizacion-github.md) | Ramas, PRs, protección de `main`, versionado en detalle |
| [`../../.claude/CLAUDE.md`](../../.claude/CLAUDE.md) | Contexto de negocio y reglas de arquitectura |
