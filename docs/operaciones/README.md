# Documentación de operaciones — Plataforma JP

Carpeta con el **protocolo de despliegue**, el **modelo de trabajo** y la
**organización de GitHub** del proyecto. Es la fuente de verdad para
cualquiera que vaya a subir cambios a producción o a colaborar en el repo.

| Documento | Para qué sirve | Cuándo leerlo |
|-----------|----------------|---------------|
| [`01-protocolo-despliegue.md`](01-protocolo-despliegue.md) | Pasos exactos para subir a Render y a Hostinger, checklist previa, humo y rollback | **Antes de cada despliegue** |
| [`02-modelo-de-trabajo.md`](02-modelo-de-trabajo.md) | Cómo se organiza el trabajo: entorno local, Services, tickets, tests, versionado | Al incorporarte al proyecto / al empezar un cambio |
| [`03-organizacion-github.md`](03-organizacion-github.md) | Modelo de ramas, PRs, protección de `main`, limpieza del repo, secretos | Al abrir una rama o revisar un PR |

## Contexto rápido

- **App:** backoffice interno de JP Preparation (CodeIgniter 4, PHP 8.2+, MySQL 8).
- **Repo:** <https://github.com/MallenK/Plataforma-JP>
- **Producción real:** Hostinger — `https://app.jppreparation.com` (hosting compartido).
- **Entorno de validación:** Render — `https://plataforma-jp.onrender.com` (Docker, vinculado a `main` en GitHub).
- **Regla de oro del despliegue:** primero Render, se verifica, y **solo si pasa** se sube a Hostinger.
- Más contexto de negocio y arquitectura: [`../../.claude/CLAUDE.md`](../../.claude/CLAUDE.md).
- Esquema de base de datos: [`../BBDD_SCHEMA.md`](../BBDD_SCHEMA.md).

## Estado en el momento de redactar (2026-09-01)

Estos documentos se escriben durante una fase de **integración con varias
ramas de trabajo abiertas en paralelo** (ver snapshot en cada documento).
Las partes marcadas con 🔴 / 🟠 son deuda o bloqueantes vivos a resolver
antes de la próxima subida.
