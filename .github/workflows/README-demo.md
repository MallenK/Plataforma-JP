# Mantenimiento del entorno DEMO

La demo es un **2º servicio web en Render** (aparte del de pre-producción),
desplegado desde la rama **`demo`**, con su **propia BBDD** (3ª BBDD en la
cuenta de Hostinger) cargada con datos ficticios.

## Puesta en marcha (una vez)

1. **BBDD**: crear una 3ª BBDD + usuario en hPanel de Hostinger. Abrir
   *Remote MySQL* a las IPs de salida de Render.
2. **Servicio Render**: *New Web Service* → mismo repo → **Branch: `demo`**
   → mismo `Dockerfile`, región Frankfurt. Nombre neutro (no debe contener
   "jppreparation"), p. ej. `gestion-academias-demo`.
3. **Variables de entorno en Render** (Environment):

   ```
   CI_ENVIRONMENT   = production
   APP_BASE_URL     = https://<tu-servicio>.onrender.com/
   APP_ENV_LABEL    = demo          # activa el modo demo (login invitado, /demo/reset, badge)
   DB_HOST/PORT/NAME/USER/PASS      # la 3ª BBDD
   DB_ENCRYPT       = false
   ENCRYPTION_KEY   = <64 hex nuevo, distinto de prod y pre-prod>
   SEED_DEMO        = 1             # siembra DemoSeeder si la BBDD está vacía
   DEMO_RESET_TOKEN = <cadena larga y aleatoria>
   # RESEND_API_KEY : NO ponerla (la demo no envía correos)
   ```

4. **Montaje inicial de la BBDD**: las migraciones no corren limpias desde
   cero (TKT-2026-00010) → clonar el esquema como en pre-producción
   (`docs/operaciones/scripts/preprod-import-schema.sh`) y luego
   `php spark db:seed DemoSeeder`. En el primer arranque, si la BBDD ya
   tiene el esquema pero 0 usuarios, `docker/start.sh` siembra solo.

## Reset nocturno + keep-alive

`GET /demo/reset?token=<DEMO_RESET_TOKEN>` vacía todas las tablas y vuelve a
lanzar `DemoSeeder`. El plan free de Render además duerme el servicio a los
15 min de inactividad.

`demo-maintenance.yml` cubre las dos cosas, **pero GitHub solo ejecuta
`schedule` desde la rama por defecto del repo** (`main`). Como la demo va en
`demo`, hay dos opciones:

- **Opción A (recomendada):** cron externo gratuito (cron-job.org):
  - cada 10 min → `https://<demo>/login`
  - 1×/día     → `https://<demo>/demo/reset?token=<DEMO_RESET_TOKEN>`
- **Opción B:** llevar `demo-maintenance.yml` también a `main` y configurar
  en *Settings → Secrets and variables → Actions*:
  - Variable `DEMO_URL` = `https://<tu-servicio>.onrender.com`
  - Secret `DEMO_RESET_TOKEN` = el mismo valor que en Render

`workflow_dispatch` permite lanzar keepalive/reset a mano desde la pestaña
Actions en cualquier caso.
