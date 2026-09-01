# TICKET-003 — Recuperación de contraseña por email + correo de bienvenida

| Campo        | Valor                                          |
|--------------|------------------------------------------------|
| Categoría    | `mejora` — Sugerencia / Mejora                 |
| Prioridad    | `alta`                                         |
| Estado       | `abierto`                                      |
| Módulo       | Auth / Onboarding / Email transaccional        |
| Rama         | `feat/email-recuperar-password-y-bienvenida`   |

## Contexto

1. El flujo de "¿Olvidaste tu contraseña?" ya existía (rutas
   `/forgot-password`, `/reset-password`, vistas y `AuthService`), pero
   en producción no llegaban los correos.
2. No existía ningún correo de confirmación / bienvenida al dar de alta
   a un alumno, entrenador o staff.

## Causa raíz del punto 1

`MAIL_FROM` estaba fijado a `onboarding@resend.dev`, el remitente de
pruebas de Resend, que **solo entrega al dueño de la cuenta**. Además,
`MailService` no dejaba ninguna traza consultable de los envíos, así
que los fallos pasaban desapercibidos.

## Solución aplicada

### Email transaccional (`app/Services/MailService.php`)

- Remitente por defecto → `JP Preparation <noreply@jppreparation.com>`
  (dominio verificado). Configurable por `MAIL_FROM` en `.env`.
- Se añade `RESEND_API_KEY` y `MAIL_FROM` documentados en `.env.example`.
- Todos los envíos (y sus fallos) se registran en la tabla `email_log`
  (`status`, `error_msg`, asunto, destinatario). Registro best-effort:
  si `email_log` fallara, no rompe el envío.
- `curl` con `CURLOPT_TIMEOUT` de 15 s.

### Recuperación de contraseña (`app/Services/AuthService.php`)

- `createPasswordReset()` guarda ahora `user_id` y `created_at` en
  `password_resets` y pasa el contexto de destinatario a `MailService`.
- El resto del flujo (token de 1 uso, caducidad 1 h, política de
  contraseña en `resetPassword()`) ya era correcto y se mantiene.

### Correo de bienvenida / confirmación de alta

- Nuevo `MailService::sendWelcomeEmail($to, $name, $role, $tempPassword)`
  con plantilla HTML (`buildWelcomeEmailHtml`).
- Se dispara al crear usuario en:
  - `PlayerService::createAlumno()` (alumnos)
  - `CoachService::createCoach()` (entrenadores)
  - `ConfiguracionService::createStaffUser()` (staff / admin)
- Incluye email + contraseña temporal + enlace de acceso. Envío
  best-effort: si el correo falla, el alta ya se completó.

## Pendiente / notas de despliegue

- Verificar en Resend que el dominio `jppreparation.com` está
  confirmado (SPF + DKIM) para el remitente `noreply@`.
- `deploy/.env` ya apuntaba a `noreply@jppreparation.com`; se alinea el
  resto de entornos a ese valor.
- No requiere migración: `email_log` y las columnas `user_id` /
  `created_at` de `password_resets` ya existían.

## Verificación

- `tests/unit/MailServiceTest.php`
- Manual: alta de un alumno de prueba → llega correo de bienvenida.
- Manual: `/forgot-password` con un email real → llega correo con
  enlace; el enlace restablece la contraseña y caduca en 1 h.
- Revisar la tabla `email_log` tras las pruebas.
