# Notificaciones push (PWA) — operación y diagnóstico

Guía para quien despliega y para quien recibe el aviso de "no me llegan las notificaciones".

## Cómo funciona (en 30 segundos)

1. Cualquier notificación nueva (`NotificationModel::createWithRecipients`) respeta las preferencias de cada
   destinatario (Configuración → Notificaciones) y se guarda en el centro de notificaciones (campanita).
2. `PushService::queueForNotification` la encola. El envío real se hace **después** de responder al navegador
   (`post_system`), nunca bloquea al usuario y nunca lanza.
3. `PushService::deliver` cifra el mensaje (RFC 8291) y lo envía a cada dispositivo suscrito en paralelo
   (`WebPush::sendMany`, VAPID RFC 8292). **`DeliveryPolicy`** decide qué hacer con cada respuesta.
4. Al pulsar el aviso se abre `GET /notificaciones/:id/ir`: lo marca leído y salta al ticket/chat/clase/bono.

Piezas: `app/Services/PushService.php` (orquesta) · `app/Libraries/Push/` (`DeliveryPolicy`, `SubscriptionStore`,
`PushLogger`, `PushTransport`) · `app/Libraries/WebPush.php` (cifrado y envío) · `public/sw.js` y
`public/assets/js/pwa.js` (navegador).

## Qué hace el sistema con cada respuesta del servicio push

| Respuesta                       | Acción                                                                                      |
|---------------------------------|---------------------------------------------------------------------------------------------|
| 2xx                             | Entregado. Contador de fallos a 0.                                                          |
| 404 / 410                       | El dispositivo ya no existe: se borra la suscripción.                                       |
| 0 (red), 408, 429, 5xx          | Fallo **temporal**: no cuenta ni borra. Se reintenta con el siguiente aviso.                |
| Resto de 4xx (400, 413…)        | Cuenta como fallo; a la **5.ª** se borra la suscripción.                                    |
| **Todo el lote** 401/403        | Clave VAPID mal configurada: **no se borra nada** y se registra `evt=vapid_rejected` (error). |

> Una caída de Google/Apple o unas claves VAPID mal puestas **no pueden** vaciar la tabla `push_subscriptions`.

## Dónde mirar los logs

* **`writable/logs/push-AAAA-MM-DD.log`** — registro propio del push. Se escribe siempre (nivel info o superior),
  da igual el `logger.threshold` del `.env` (en producción el general solo guarda errores). Se conservan 30 días.
* `writable/logs/log-AAAA-MM-DD.log` — el general de CodeIgniter. Los errores del push (`ERROR`) también salen aquí.

Formato (una línea por evento, buscable con `grep`):

```
2026-10-15T10:42:07+02:00 INFO [push] evt=batch batch=a1b2c3 notif=42 subs=3 ok=2 gone=1 transient=0 rejected=0 deleted=1 ms=412
```

| `evt=`            | Nivel   | Significa                                                                              |
|-------------------|---------|----------------------------------------------------------------------------------------|
| `subscribe`       | info    | Un dispositivo se ha suscrito (o reasignado a otro usuario).                           |
| `unsubscribe`     | info    | Baja manual o por cierre de sesión.                                                    |
| `batch`           | info    | Resumen de una notificación: suscripciones, entregadas, caídas, borradas, milisegundos. |
| `delivery_failed` | warning | Una suscripción concreta no recibió el aviso (`http=`, `action=`).                     |
| `subscribe_rejected` | warning | Alta rechazada (endpoint no permitido, claves inválidas, push sin configurar).      |
| `job_dropped`     | warning | Se encoló un aviso de una notificación que ya no existe (transacción revertida).       |
| `vapid_rejected`  | **error** | Todos los servicios push rechazan la firma: revisar claves VAPID. No borra nada.     |
| `flush_failed` / `deliver_failed` / `queue_failed` | **error** | Excepción inesperada (lleva `exception=` y `at=`). |

Para seguir una notificación concreta: `grep "notif=42" writable/logs/push-*.log`.

## "No me llegan las notificaciones" — árbol de decisión

1. **¿Hay claves VAPID?** `.env` debe tener `vapid.publicKey`, `vapid.privateKey`, `vapid.subject`
   (`php spark push:vapid --write` las genera). Sin ellas el push está desactivado y la campanita sigue sondeando.
   Síntoma en log: `evt=subscribe_rejected reason="push no configurado"`.
2. **¿Tiene permiso el navegador?** En Configuración → Notificaciones debe aparecer el dispositivo como activo.
   Si el usuario bloqueó las notificaciones en el navegador, solo él puede revertirlo en los ajustes del sitio.
3. **¿Hay suscripción?** `SELECT user_id, last_used_at, failures FROM push_subscriptions WHERE user_id = ?;`
   Sin filas → el dispositivo no está suscrito (o se borró por 404/410 al desinstalar la app).
4. **¿Se envió?** `grep "notif=<id>" writable/logs/push-*.log`:
   * `batch … subs=0` → el destinatario no tiene dispositivos suscritos.
   * `batch … ok=N` → el servicio push aceptó el mensaje; si no aparece en el móvil, es del sistema operativo
     (ahorro de batería, modo No molestar, permisos del navegador).
   * `delivery_failed … http=403` repetido → ver `vapid_rejected`.
5. **¿Preferencias?** Si el usuario apagó la categoría (Configuración → Notificaciones) no se crea ni la fila
   para él. Es el comportamiento esperado, no un fallo.
6. **iPhone/iPad**: el push solo existe con la app **instalada en la pantalla de inicio** (iOS ≥ 16.4).
   Guía pública para el usuario: `/instalar`.

## Lista de comprobación de despliegue

- [ ] Migraciones `2026-10-05-000001` (suscripciones) y `2026-10-05-000002` (preferencias) aplicadas
      (`docs/migraciones/2026-10-05_push_pwa_notificaciones.sql` para phpMyAdmin).
- [ ] `vapid.*` en el `.env` **del entorno** (Render: variables de entorno; Hostinger: `.env`). Mismas claves que
      cuando los usuarios se suscribieron: **cambiarlas invalida todas las suscripciones** (el navegador las
      recrea solo al abrir la app).
- [ ] `public/sw.js` y `public/assets/js/pwa.js` desplegados **juntos**; si cambias `sw.js`, sube `VERSION`.
- [ ] `writable/logs/` con permiso de escritura (el log del push lo necesita).
- [ ] Probar con el botón "Enviar prueba" (tarjeta de notificaciones en Configuración → Notificaciones y en /notificaciones) y revisar el
      `evt=batch` correspondiente.
- [ ] Hostinger: tras desplegar, mirar un `batch` y comprobar que el `ms=` es razonable. Si las peticiones del
      usuario se notaran lentas al crear avisos, el servidor no libera la conexión antes del envío
      (`litespeed_finish_request`/`fastcgi_finish_request`); el log `debug` `evt=release_client via=none` lo delata
      (requiere `logger.threshold = 8` temporalmente).

## Pruebas automáticas

```
vendor/bin/phpunit --filter "Push|Notification"     # PHP: política, servicio, controlador, endpoints
node tests/js/sw.test.cjs && node tests/js/pwa.test.cjs   # service worker y cliente, sin dependencias
```

GitHub Actions (`.github/workflows/tests.yml`) ejecuta toda la suite en cada PR y en cada push a `main`/`prod`.
