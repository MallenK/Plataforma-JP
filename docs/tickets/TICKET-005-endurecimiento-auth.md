# TICKET-005 — Capa de seguridad para login, alta de usuarios y cambio de contraseña

| Campo        | Valor                                          |
|--------------|------------------------------------------------|
| Categoría    | `mejora` — Sugerencia / Mejora                 |
| Prioridad    | `alta`                                         |
| Estado       | `resuelto` — en producción                     |
| Módulo       | Auth / Perfil / Configuración                  |
| Rama         | `feat/seguridad-auth-hardening` (sobre `feat/email-recuperar-password-y-bienvenida`) |
| Entregado en | `v1.1.0` (2026-09-01), desplegado y verificado en Hostinger (cabeceras, HSTS, bloqueo de login, `/perfil/password`, `auth_events`) |

## Contexto

El sistema de auth tenía la base bien (bcrypt, CSRF activo, regeneración de
sesión, tokens de reset de 256 bits de un solo uso, sin auto-registro, sin
enumeración en `/forgot-password`) pero faltaban defensas estándar contra
ataques automatizados. Objetivo: cerrarlas **sin fricción para el usuario
normal** (sin 2FA, sin CAPTCHA).

## Qué se ha hecho

### Anti-fuerza-bruta (nuevo `AuthGuardService` + tabla `auth_events`)
- El límite de login ya **no vive en la sesión** (antes se saltaba no mandando
  cookie). Ahora persiste en `auth_events`.
- Bloqueo **por cuenta** (`sec_lockout_threshold`/`sec_lockout_minutes`,
  configurables, def. 5 intentos / 15 min) y **por IP** (20 fallos/15 min →
  60 min, contra password-spraying).
- Login en **tiempo constante** (bcrypt dummy si la cuenta no existe).
- Se rechaza el login de cuentas `status != active` (antes entraban).

### Recuperación de contraseña
- El token se guarda **hasheado (SHA-256)**; en claro solo va en la URL.
- `/forgot-password` **limitado** (1/90 s y 5/hora por email, 15/hora por IP)
  sin revelarlo (sigue devolviendo `success`).
- `/reset-password` limitado por IP.
- En éxito: `password_changed_at`, se borran todos los tokens del email, y
  llega un **email de aviso** ("tu contraseña ha cambiado").
- La nueva contraseña no puede ser común (`app/Data/common-passwords.txt`) ni
  igual a la actual.

### Alta de usuarios / reset por admin
- Contraseña temporal fuerte (`generateTempPassword()`, 14 chars CSPRNG) en
  lugar de `'Jp'+6hex+'!'`.
- `must_change_password = 1` → el usuario **debe elegir su contraseña en el
  primer acceso** (única fricción, y solo una vez).
- El reset por admin registra `admin_pwreset` y avisa al usuario por email.

### Nueva pantalla "cambiar mi contraseña"
- `GET/POST /perfil/password` con re-autenticación (pide la actual).
- Antes no existía: un usuario solo podía cambiarla vía email.
- Al cambiarla, las sesiones en otros dispositivos se cierran (`AuthFilter`
  compara `password_changed_at` con `session('pw_stamp')`, recheck ≤5 min).

### Sesión, cookies y cabeceras
- `Config\Security::$tokenRandomize = true` (BREACH).
- Cookies `Secure` y `forceGlobalSecureRequests` solo en producción.
- `Session::$regenerateDestroy = true`.
- Nuevo `SecurityHeadersFilter`: `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy`, `COOP`, HSTS (prod), quita
  `X-Powered-By`. Filtro `invalidchars` activado.

### Auditoría
- Panel **Configuración → Seguridad → "Actividad de seguridad reciente"**
  (logins fallidos, bloqueos, resets, cambios).

## Migración

`docs/deploy/migraciones_seguridad.sql` (ejecutar en phpMyAdmin):
tabla `auth_events` + columnas `users.password_changed_at` /
`users.must_change_password`. En local: `php spark migrate`.

## Impacto UX

| Situación | Cambio |
|---|---|
| Login correcto | Ninguno |
| Fallar 5 veces / 15 min | Bloqueo temporal con auto-desbloqueo |
| "¿Olvidaste tu contraseña?" | Igual; no se puede spamear |
| Usuario nuevo, primer login | 1 pantalla extra, una vez: define tu contraseña |
| Cambiar/resetear contraseña | Debe ser distinta y no estar entre las comunes |
| Cambio de contraseña | Otras sesiones se cierran (≤5 min) + email de aviso |
| Nuevo: "cambiar mi contraseña" en el perfil | Capacidad nueva |

## Verificación

- `tests/unit/AuthGuardServiceTest.php`, `AuthGuardLockoutTest.php`,
  `AuthHardeningWiringTest.php` (34 tests). Suite completa tras integrar toda
  la release: **153/153**.
- E2E con curl: lockout tras 5 intentos, throttle de forgot, token hasheado en
  BD, flujo `must_change_password`, cambio propio con re-autenticación,
  cabeceras de seguridad presentes. Regresión OK en Mensajes/Tickets/Clases.

## Pendiente / fase 2

- 2FA / TOTP (opcional admin/superadmin)
- CAPTCHA / Turnstile
- Content-Security-Policy (requiere sacar el JS/CSS inline)
- HIBP Pwned Passwords API
- Driver de sesión en BD (revocar sesiones al instante en vez de ≤5 min)
- `PruneAuthEvents`: limpieza de `auth_events` > 90 días (hoy manual, ver
  [`../BBDD_SCHEMA.md`](../BBDD_SCHEMA.md) "Pendiente en BD")
- ~~Verificar el dominio en Resend~~ → **hecho** (`jppreparation.com` verificado)
