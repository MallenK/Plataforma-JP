# TICKET-008 — "Descontar bono" desde Pasar Lista falla con 403 Forbidden

| Campo        | Valor                                              |
|--------------|----------------------------------------------------|
| Categoría    | `bug` — Error / Bug                                |
| Prioridad    | `alta`                                             |
| Estado       | `resuelto`                                         |
| Módulo       | Clases · Pasar Lista · Bonos                       |
| Rama         | `fix/descontar-bono-csrf-403`                      |
| Detectado en | Local (`/clases/{id}/lista`)                       |
| Entregado en | `v1.1.7` (pendiente)                               |

## Descripción

En la pantalla de **Pasar Lista** de una clase
(`http://localhost:8080/clases/125/lista`), al pulsar **"Descontar bono"** de
un jugador marcado como presente, la petición falla:

```
jugadores/49/descontar-bono:1  Failed to load resource:
the server responded with a status of 403 (Forbidden)
```

El bono nunca se descuenta y el usuario solo ve "Error de red".

## Causa raíz

El `fetch` de `app/Views/clases/pasar_lista.php` enviaba **siempre** la
cabecera `X-CSRF-TOKEN`, pero con valor vacío, porque la leía de un
`<meta name="csrf-token">` **que no existe** en el layout
(`layouts/app.php` no lo renderiza):

```js
'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
```

En CodeIgniter 4, `Security::getPostedToken()` sigue este orden de prioridad:

1. `$_POST[tokenName]` → vacío (el cuerpo se manda como JSON, no
   `application/x-www-form-urlencoded`).
2. **cabecera `X-CSRF-TOKEN`** → está presente pero vacía →
   `getPostedToken()` devuelve `null` y **corta ahí** (`return`).
3. ~~token en el cuerpo JSON~~ → nunca llega a leerse.

El token válido sí viajaba en el `body`
(`JSON.stringify({ csrf_test_name: '<hash>' })`), pero la cabecera vacía lo
tapaba. Token nulo → `SecurityException::forDisallowedAction()` → **403**.

Ninguna otra pantalla usa ese patrón del `<meta>` inexistente; el resto del
front manda el token bien (constantes `CSRF_NAME`/`CSRF_HASH` + cuerpo
`form-urlencoded`, o la cabecera con el hash real).

## Solución aplicada

`app/Views/clases/pasar_lista.php`: la cabecera `X-CSRF-TOKEN` ahora lleva el
hash real (`<?= csrf_hash() ?>`) en vez del `<meta>` inexistente. Se mantiene
además el token en el cuerpo JSON como respaldo.

```js
'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
```

Como en `app/Config/Security.php` está `tokenRandomize = true` y
`regenerate = false`, el token sigue siendo válido para descuentos repetidos
en la misma pantalla sin recargar.

Cambio de una sola línea en una vista PHP. Sin BD, sin migraciones, sin
variables de entorno.

## Verificación

- `tests/unit/DeductBonoCsrfTest.php` — ejercita `Security::verify()` con una
  `IncomingRequest` real:
  1. token válido en el cuerpo + cabecera `X-CSRF-TOKEN` vacía →
     `SecurityException` (reproduce el 403).
  2. token válido en el cuerpo, **sin** cabecera → pasa.
  3. token válido en la cabecera `X-CSRF-TOKEN` → pasa (el caso del fix).
- Suite completa: **173/173** en verde (Docker). Nota: `AuthGuardServiceTest::testTempPasswordEsFuerte`
  es un test flaky preexistente (colisión CSPRNG ~1/1000 con el prefijo "Jp");
  no lo toca este cambio.
- Manual: `/clases/{id}/lista`, jugador presente con bono activo →
  "Descontar bono" → el contador de sesiones baja, sin 403.

## Datos de prueba

`app/Database/Seeds/BulkDemoDataSeeder.php` (nuevo, **solo desarrollo local**):
inyección masiva de datos realistas — sedes, entrenadores, staff, 60 alumnos
con ficha, 5 tipos de bono, 45 bonos asignados, 12 plantillas recurrentes y
28 sesiones puntuales (pasadas con lista/feedback + futuras + canceladas).
Idempotente (usuarios con dominio `@demo.jppreparation.local`). Sirve para
tener volumen parecido a producción y reproducir esta y otras incidencias.

```bash
docker compose exec app php spark db:seed BulkDemoDataSeeder
```

## Notas de despliegue

- El fichero que va a producción es **`app/Views/clases/pasar_lista.php`**.
- `BulkDemoDataSeeder.php` **no se ejecuta en Hostinger** (es solo para el
  entorno local).
- Sin migraciones ni cambios de `.env`.
