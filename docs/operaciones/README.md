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
- **Entorno de validación:** Render — `https://plataforma-jp.onrender.com` (Docker, vinculado a `main` en GitHub, BD TiDB Cloud Serverless).
- **Regla de oro del despliegue:** primero Render, se verifica, y **solo si pasa** se sube a Hostinger. ⚠️ `spark migrate` no funciona con TiDB Serverless (Render); las migraciones de producción se aplican a mano en phpMyAdmin de Hostinger con los `.sql` de `docs/deploy/`.
- Más contexto de negocio y arquitectura: [`../../.claude/CLAUDE.md`](../../.claude/CLAUDE.md).
- Esquema de base de datos: [`../BBDD_SCHEMA.md`](../BBDD_SCHEMA.md).

## Estado (2026-09-03)

`main` en **v1.1.5**. Repo limpio: solo la rama `main` (local y remoto), modelo
**trunk-based** con `main` protegido (todo por PR). Tags `v1.1.0` … `v1.1.5`.

- **En Hostinger (producción):** v1.1.5 — incluye el fix de zona horaria
  (`Europe/Madrid`), el fix del solapamiento de clases en el calendario y la
  **Tanda A** de la auditoría de seguridad (RCE de subida de archivos, adjuntos
  fuera del webroot, y puntos menores). Ver `docs/tickets/TICKET-006`.
- **Pendiente:** Tanda B de la auditoría (`logout` solo POST + rename de los
  nombres de CSRF) — necesita ventana de bajo tráfico. Sin rama todavía.

Historial de releases anteriores: [`02-modelo-de-trabajo.md`](02-modelo-de-trabajo.md) §6.
