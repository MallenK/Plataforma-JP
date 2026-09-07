# Documentación de operaciones — Plataforma JP

Carpeta con el **protocolo de despliegue**, el **modelo de trabajo** y la
**organización de GitHub** del proyecto. Es la fuente de verdad para
cualquiera que vaya a subir cambios a producción o a colaborar en el repo.

| Documento | Para qué sirve | Cuándo leerlo |
|-----------|----------------|---------------|
| [`00-COMO-TRABAJAR.md`](00-COMO-TRABAJAR.md) | **Empieza aquí.** Las 5 reglas + el ciclo completo en una página | Siempre, lo primero |
| [`01-protocolo-despliegue.md`](01-protocolo-despliegue.md) | Pasos exactos para subir a Render y a Hostinger, checklist previa, humo y rollback | **Antes de cada despliegue** |
| [`02-modelo-de-trabajo.md`](02-modelo-de-trabajo.md) | Cómo se organiza el trabajo: entorno local, Services, tickets, tests, versionado | Al incorporarte al proyecto / al empezar un cambio |
| [`03-organizacion-github.md`](03-organizacion-github.md) | Modelo de ramas, PRs, protección de `main`, limpieza del repo, secretos | Al abrir una rama o revisar un PR |

## Contexto rápido

- **App:** backoffice interno de JP Preparation (CodeIgniter 4, PHP 8.2+, MySQL 8).
- **Repo:** <https://github.com/MallenK/Plataforma-JP>
- **Producción real:** Hostinger — `https://app.jppreparation.com` (hosting compartido, MariaDB `u912370917_jpapp`).
- **Pre-producción (PPR):** Render — `https://plataforma-jp.onrender.com` (Docker, auto-deploy desde `main`, 2ª BD MariaDB de la cuenta Hostinger). `docker/start.sh` corre `php spark migrate --all` en cada arranque, así que las migraciones se aplican solas aquí.
- **Regla de oro del despliegue:** primero PPR (Render), se verifica, y **solo si pasa** se sube a Hostinger. Las migraciones de producción se aplican a mano en el phpMyAdmin de Hostinger.
- Más contexto de negocio y arquitectura: [`../../.claude/CLAUDE.md`](../../.claude/CLAUDE.md).
- Esquema de base de datos: [`../BBDD_SCHEMA.md`](../BBDD_SCHEMA.md).

## Estado

Modelo **trunk-based**: solo la rama `main` (protegida, todo por PR), sin
`develop` ni `release/*`. Versión = primera entrada de `app/version.json`.
La rama se borra al mergear el PR.

- **PPR (Render):** sigue a `main` automáticamente.
- **Producción (Hostinger):** va por detrás a propósito — se despliega a mano
  cuando una tanda de cambios está validada en PPR. Consultar `app/version.json`
  y el panel del sidebar para saber qué versión hay viva.
- **Pendiente de producción:** auditoría de seguridad Tanda A/B y la
  reorganización de tickets (F0–F4) están en `main` y PPR pero **no** en
  Hostinger.

Historial de releases: [`02-modelo-de-trabajo.md`](02-modelo-de-trabajo.md) §6.
