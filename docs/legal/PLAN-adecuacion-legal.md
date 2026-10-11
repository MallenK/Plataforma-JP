# Plan de adecuación legal — Plataforma JP Preparation (España)

Estado: **PLAN, nada ejecutado**. Fecha: 2026-10-05.
Marco: RGPD, LOPDGDD (art. 7, consentimiento de menores desde los 14 años) y LSSI-CE (arts. 21-22).
Aviso: documento técnico, no asesoramiento jurídico. Los textos legales los debe revisar un abogado o DPO antes de publicarse.

## Reglas de trabajo
- Todo por rama + PR, nunca directo a `main`.
- Antes de cada bump de `app/version.json`, preguntar patch/minor/major.
- Cada migración lleva su SQL de prod en `docs/deploy/` (añadir con `git add -f`, porque `deploy/` está en `.gitignore`).
- La lógica de negocio va en Services, no en Controllers.

## Alcance de esta ronda
Dentro: recursos en local, menores y consentimiento, páginas legales, marketing preparado (inactivo), documentos.
Fuera (aplazado): cuentas de tutor con varios hijos y emails compartidos entre alumnos. `users.email` sigue siendo único.
No aplica: COPPA, grabación de sesiones (no hay), suscripciones (no hay), agente DMCA (ley de EE. UU.).

## Decisiones tomadas
- El responsable del tratamiento aparece con marcadores `[RAZÓN SOCIAL]`, `[NIF]`, `[DOMICILIO]`, `[EMAIL DE CONTACTO]`, que se rellenan al final.
- Marketing: futuro. Se prepara pero queda desactivado.
- Fichas existentes incompletas: aviso al admin, sin bloquear.
- Muchas cuentas de alumno usan el email del padre o la madre, por lo que se registra de quién es el email.

---

## Fase 1 — Recursos en local (RGPD)
Rama: `fix/rgpd-recursos-locales` · bump propuesto: patch

| Cambio | Archivo(s) |
|---|---|
| Descargar Montserrat 400-800 y Oswald 600/700 en woff2 (subconjunto latino) | nuevo `public/assets/fonts/` |
| `@font-face` locales | nuevo `public/assets/css/fonts.css` |
| Quitar `preconnect` y `<link>` de Google Fonts, enlazar `fonts.css` | `app/Views/layouts/base.php` (líneas 11-13) |
| Cambiar el `<link>` de Google en el tutorial | `public/tutorial.html` (línea 7) |
| Alojar Bootstrap 5.3.0 (CSS y JS), Bootstrap Icons 1.11.3 (CSS y fuentes) y Toastify | nuevo `public/assets/vendor/`; `app/Views/layouts/base.php` (líneas 15-37) |
| Mismo cambio para Bootstrap en las vistas de error | `app/Views/errors/error_403.php`, `app/Views/errors/html/production_reportable.php` |
| html2canvas (hoy desde cdnjs, carga bajo demanda) | `public/assets/js/error-report.js` |
| Revisar el CSP para que todo quede en `'self'` | `app/Filters/SecurityHeadersFilter.php`, `app/Config/ContentSecurityPolicy.php` |

Verificación: la pestaña Red del navegador no debe mostrar peticiones a dominios externos. Revisar también `public/assets/js` por si hay más recursos externos.
Riesgo: bajo. Cuidar la ruta relativa de las fuentes de Bootstrap Icons.

## Fase 2 — Menores y consentimiento (LOPDGDD)
Rama: `feat/menores-consentimiento` · bump propuesto: minor

1. Migración nueva sobre `player_profiles`:
   - `account_email_owner` (`alumno` | `tutor`): de quién es el email de la cuenta.
   - `guardian_name`, `guardian_relationship`, `guardian_phone`.
   - `guardian_email`: solo se rellena si el email de la cuenta es del alumno.
   - `guardian_consent_at`: fecha en que el admin registró el consentimiento.
   - `minor_account_notice_at`: para alumnos de 14 o más con email de tutor, fecha en que se informó al alumno de que la cuenta la gestiona su tutor.
2. `birth_date` obligatorio al crear alumno: `app/Controllers/AlumnosController.php` (~líneas 86, 133, 208) y `app/Services/PlayerService.php`.
3. Regla pura en el Service, con tests:

| Edad | Email de la cuenta | Qué se exige |
|---|---|---|
| Menos de 14 | Tutor | Nombre y relación del tutor, y consentimiento del tutor (fecha) |
| Menos de 14 | Alumno | Datos completos del tutor y consentimiento (se avisa del caso) |
| 14 o más | Tutor | Consentimiento del alumno y marca de "cuenta gestionada por tutor, alumno informado" |
| 14 o más | Alumno | Consentimiento del alumno |

   La edad se calcula con fecha de nacimiento y zona Europe/Madrid.
4. Vistas: `app/Views/alumnos/create.php`, `create_profile.php`, `edit.php`, `show.php`:
   - selector "El email de la cuenta es de: alumno / padre-madre-tutor"
   - bloque "Tutor legal" visible solo si corresponde
   - casilla de consentimiento
   - estado del consentimiento en la ficha
   - todo restringido a admin/superadmin, igual que `image_rights_signed`
5. Fichas existentes (aviso, sin bloqueo):
   - insignia "Datos legales incompletos" en la ficha
   - contador en el dashboard de admin con fichas sin fecha de nacimiento o con menor sin consentimiento
6. Tests: `tests/unit/` con la regla de edad (13 años, 14 años, cumpleaños hoy, sin fecha, cada fila de la tabla).
7. SQL de prod: `docs/deploy/migraciones_menores_consentimiento.sql`.

Nota: el padre o el alumno no firman en la app. El admin registra que obtuvo el consentimiento (papel o firma previa).

## Fase 3 — Páginas legales
Rama: `feat/paginas-legales` · bump propuesto: minor

| Cambio | Archivo(s) |
|---|---|
| Vistas públicas: política de privacidad, aviso legal (LSSI-CE), política de cookies | nuevas `app/Views/legal/*.php`, nuevo `LegalController` |
| Rutas públicas explícitas (sin filtro auth) | `app/Config/Routes.php` |
| Datos del titular centralizados (marcadores) | nuevo `app/Config/Legal.php` |
| Enlaces en el pie y en el login | `app/Views/layouts/app.php`, `app/Views/auth/login.php` |
| Enlace en el email de bienvenida | `app/Services/MailService.php` (`buildWelcomeEmailHtml`) |

Contenido de la política de privacidad:
- Finalidades: gestión de clases, bonos, comunicaciones y soporte.
- Base jurídica: ejecución del contrato y consentimiento.
- Datos de menores y datos de salud (`medical_notes`).
- Encargados: Resend (emails, EE. UU., cláusulas contractuales tipo) y el hosting.
- Plazos de conservación.
- Derechos de acceso, rectificación, supresión, oposición, limitación y portabilidad, y vía de reclamación a la AEPD.
- Se dirige al "usuario o su representante legal" y explica que las cuentas de menores pueden ser gestionadas por el padre, la madre o el tutor.

Cookies: solo técnicas (sesión y CSRF), exentas de consentimiento, por lo que no hace falta banner mientras no haya analítica. La política lo declara.

## Fase 4 — Marketing preparado, inactivo (LSSI-CE art. 21)
Rama: `feat/marketing-preparado` · bump propuesto: minor

| Cambio | Archivo(s) |
|---|---|
| Método `sendMarketing()` separado del transaccional: pie con enlace de baja de un clic (token firmado), identificación del remitente y dirección postal (marcador), cabecera `List-Unsubscribe` | `app/Services/MailService.php` |
| Tabla `marketing_consents` (email, fecha, origen, baja), asociada al email y no al alumno | nueva migración |
| Ruta pública de baja con limitación de intentos | `app/Config/Routes.php`, controller nuevo |
| Interruptor de activación, apagado por defecto | `SettingsModel` / Configuración |
| SQL de prod | `docs/deploy/migraciones_marketing.sql` |

No se envía nada hasta tener consentimientos previos registrados.

## Fase 5 — Documentos (no son código)
- Registro de actividades de tratamiento (RGPD art. 30).
- Contratos de encargado de tratamiento con Resend y con el hosting.
- Evaluación de necesidad de DPO (datos de menores y de salud, escala pequeña).
- Procedimiento de brechas de seguridad: notificar a la AEPD en 72 h.
- Rellenar los marcadores del titular y la dirección postal.

## Orden
1. Fase 1 → 2. Fase 2 → 3. Fase 3 → 4. Fase 4. La Fase 5 se hace en paralelo.

## Pendiente (no se hace ahora)
- Varios alumnos con el mismo email de tutor: `users.email` es único (`CreateUsers.php:53`, `UserModel.php:53`, `AuthController.php:36`), y login y reset buscan por email con `->first()`. Opciones evaluadas: cuenta de tutor con varios hijos (cambio grande) o email de contacto separado. Decisión aplazada.
- Torneos y Compras siguen desactivados. Si se reactivan, habrá que revisar condiciones de pago, renovación y cancelación (Ley de consumidores).
