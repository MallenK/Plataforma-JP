# TICKET-009 — En un chat con mucho texto no se cargan todos los mensajes

| Campo        | Valor                                                    |
|--------------|----------------------------------------------------------|
| Categoría    | `bug` — Error / Bug                                      |
| Prioridad    | `media`                                                  |
| Estado       | `resuelto` — pendiente de PR y despliegue                |
| Módulo       | Mensajes (chat 1-a-1)                                    |
| Rama         | `fix/mensajes-historial-largo`                           |
| Detectado en | Producción (reporte de usuario con captura de pantalla)  |
| Entregado en | `v1.7.2` (pendiente de despliegue)                       |

## Descripción

Reporte: «si hay mucho texto en un chat en la parte de Mensajes, no se cargan
todos los mensajes». Confirmado después por el usuario: **en el chat no se
puede hacer scroll**.

La captura que acompaña al reporte es una conversación entre el entrenador y
un alumno (cuenta que usa la familia para escribir): varios mensajes largos
seguidos (resúmenes de partido de 3-6 frases) y respuestas cortas del
entrenador. No se incluyen aquí nombres ni datos del alumno real.

## Reproducción en local

`app/Database/Seeds/LongConversationSeeder.php` (nuevo, **solo desarrollo
local**) crea:

- Entrenador `test.coach.chat@test.jppreparation.local` / `Test1234!`
- Alumno `test.alumno.chat@test.jppreparation.local` / `Test1234!`
  («TEST Marc Soler (chat largo)», con ficha de jugador)
- Una conversación entre los dos con **212 mensajes** en ~90 días
  (≈ 43.500 caracteres): 34 mensajes de más de 500 caracteres, 4 hilos
  «muy largos» de varios párrafos (el mayor, ~1.900 caracteres) con
  planificación del entrenador, y logística corta entre medias. Los textos
  son inventados, al estilo de la captura.

Determinista (semilla fija) e idempotente: reutiliza usuarios/conversación y
regenera **solo** los mensajes de esa conversación.

```bash
docker compose exec app php spark db:seed LongConversationSeeder
```

Resultado antes del fix (Chrome real vía Playwright, 1366×768 y 390×844):

| Dato                                   | Valor                        |
|----------------------------------------|------------------------------|
| Mensajes en BD (conversación #37)      | 212                          |
| Mensajes devueltos al abrir            | **50**                       |
| `scrollHeight` / `clientHeight` del chat | **543 / 543** (sin scroll) |
| Rueda del ratón hacia arriba           | `scrollTop` sigue en 0       |

## Causa raíz

Dos problemas encadenados:

1. **No había scroll (CSS).** `.chat-messages-inner` tenía
   `min-height: 100%` + `justify-content: flex-end` y es un hijo flex de
   `.chat-messages` que **se puede encoger** (`flex-shrink: 1` por defecto).
   El `min-height` explícito anula el tamaño mínimo automático del flex item,
   así que el bloque se encogía hasta la altura del contenedor y, por el
   `flex-end`, los mensajes que no cabían se desbordaban **por arriba**. Un
   desbordamiento hacia arriba no genera scroll: el contenedor medía lo mismo
   por dentro que por fuera. Con pocos mensajes no se notaba; con mensajes
   largos se llega enseguida a no caber.
2. **Solo se cargaban 50 mensajes.** `ajaxOpenConversation()` pedía
   `getForConversation($convId, 50)` y la vista no tenía forma de pedir los
   anteriores (el modelo aceptaba `$beforeId`, pero nada lo usaba).

## Solución aplicada

### 1. Scroll — `public/assets/css/app.css`

- `.chat-messages-inner { flex-shrink: 0; }`: crece con su contenido y el
  desbordamiento va hacia abajo (scroll normal). `min-height: 100%` +
  `flex-end` se mantienen para que un chat corto siga pegado abajo.
- `.chat-messages { overflow-anchor: none; overscroll-behavior: contain; }`:
  la posición al anteponer mensajes se corrige a mano (ver 2) y el scroll del
  chat no arrastra la página al llegar al tope.

### 2. Carga progresiva del historial (estudio + implementación)

Objetivo: poder llegar al principio de cualquier conversación **sin que abrir
un chat sea más lento** cuanto más largo es.

**Opciones valoradas**

| Opción | Pros | Contras | Decisión |
|--------|------|---------|----------|
| Cargar todo al abrir | Simple | 212 mensajes ≈ 149 KB de JSON y 212 nodos antes de ver nada; crece sin límite | ❌ |
| Paginación por `OFFSET` | Simple | Se descoloca si llegan mensajes nuevos mientras se pagina; `OFFSET` alto recorre filas | ❌ |
| **Cursor por `id` + scroll hacia arriba** (tipo WhatsApp) | Coste fijo por bloque, usa el índice `conversation_id` (incluye el `id`), inmune a mensajes nuevos | Hay que conservar la posición del scroll al anteponer | ✅ |
| Virtualización del DOM | Memoria constante con miles de mensajes | Complejidad alta sin build step; con cientos de mensajes no compensa | Futuro, si hiciera falta |

**Implementación**

- `MessageModel::getPage($convId, $limit = 30, $beforeId = null)`:
  `ORDER BY id DESC LIMIT limit + 1` → la fila extra indica si quedan más
  **sin `COUNT(*)`**. La lógica pura está en `buildPage()` /
  `clampPageSize()` (máx. 100) para poder testearla sin BD. Sustituye a
  `getForConversation()`.
- `POST /mensajes/open` devuelve el bloque más reciente (30) + `has_more`.
- Nuevo `GET /mensajes/:id/historial?before=ID` →
  `MensajesController::ajaxHistory()` (filtro `auth`; 403 si no eres
  participante, 422 sin `before`). La comprobación de participante se
  extrajo a `findUserConversation()` (también la usa `ajaxPoll`).
- Vista `mensajes/index.php`:
  - Al acercarse a **400 px** del principio se pide el bloque anterior
    (precarga: normalmente llega antes de tocar el techo).
  - Al anteponer se compensa `scrollTop` con la altura añadida → lo que se
    está leyendo no se mueve.
  - Si el bloque no llena la pantalla (mensajes cortos), se pide otro solo.
  - Cabecera del historial: spinner «Cargando mensajes anteriores…», botón
    «Cargar mensajes anteriores» (respaldo para teclado), «Reintentar» si
    falla e «Inicio de la conversación» al llegar al primero.
  - Cada bloque se inserta con un `DocumentFragment` (un solo reflow).
  - `convSeq` descarta respuestas de una conversación anterior si se cambia
    de chat mientras carga (también en la apertura y en el sondeo).
  - Un mensaje entrante **ya no baja de golpe** a quien está leyendo mensajes
    antiguos; solo se baja si estaba abajo del todo.
- **Imágenes adjuntas en diferido**: `loading="lazy"` + `decoding="async"`,
  así solo se descargan las que se acercan a la vista. La miniatura tiene
  tamaño fijo (200×200, `object-fit: cover`) para que al terminar de cargar
  no empuje los mensajes ni descoloque el scroll.
- **Sondeo más ligero**: `GET /mensajes/:id/poll` devolvía cada 3 s los IDs
  de **todos** mis mensajes leídos del historial. Ahora el chat manda
  `read_from` (el más antiguo que aún pinta como «enviado»; 0 = ninguno) y el
  servidor solo devuelve esos. Sin el parámetro (pestañas abiertas antes del
  despliegue) responde como antes.

**Medidas en local (conversación de 212 mensajes)**

| Momento | Antes | Ahora |
|---------|-------|-------|
| Abrir el chat | 50 mensajes, sin scroll | 30 mensajes (~24 KB), con scroll |
| Llegar al primer mensaje | imposible | 7 bloques bajo demanda (~2-23 KB cada uno) |
| `read_ids` en cada sondeo (3 s) | todos mis leídos | 0-1 |

### Ideas para más adelante (no incluidas)

- Miniaturas en servidor para las imágenes del chat (hoy la miniatura
  descarga la imagen original por PHP).
- `ConversationModel::getForUser()` calcula el último mensaje con un
  `GROUP BY` sobre **toda** la tabla `messages` y se llama cada 10 s; con
  volumen convendría guardar `last_message_id` en `conversations`.

## Hallazgo aparte (no corregido aquí)

`NotificationModel::$allowedFields` no incluye `source_type` / `source_id`,
así que las notificaciones de «Nuevo mensaje» (y las de tickets creados desde
Mensajes) se guardan con esos campos a `NULL` y el enlace de la notificación
no puede abrir la conversación. Merece su propio ticket.

## Verificación

- `tests/unit/MensajesHistorialTest.php` (8 tests): bloques y `has_more`,
  orden cronológico, límite de tamaño, ruta con filtro `auth`, que la apertura
  ya no usa los 50 fijos, regla CSS `flex-shrink: 0` / `overflow-anchor`, y
  que la vista pide `/historial`, usa `loading="lazy"` y `read_from`.
- Suite completa: **295 tests OK** (1 omitido) en Docker.
- Navegador real (Playwright + Chromium, escritorio 1366×768 y móvil
  390×844), con la conversación del seeder:
  - abre con 30 mensajes, abajo del todo y con scroll real;
  - al subir carga bloques hasta «Inicio de la conversación» con los **212**
    mensajes, en orden y sin duplicados; el primero es la presentación;
  - la vista no salta al anteponer un bloque (0 px de diferencia);
  - mensaje entrante del alumno mientras el entrenador lee arriba: no le
    mueve; estando abajo, el mensaje queda visible;
  - enviar sigue funcionando; chat nuevo/corto queda pegado abajo;
  - botón «Cargar mensajes anteriores» funciona;
  - `/historial` de una conversación ajena → 403; sin `before` → 422.

## Notas de despliegue

Ficheros que van a producción:

- `app/Config/Routes.php`
- `app/Controllers/MensajesController.php`
- `app/Models/MessageModel.php`
- `app/Views/mensajes/index.php`
- `public/assets/css/app.css`
- `app/version.json`

Sin migraciones ni cambios de `.env`. `LongConversationSeeder.php` **no se
ejecuta en Hostinger ni en Render** (solo entorno local). `app.css` se sirve
con `?v=filemtime` desde `layouts/app.php`, así que el CSS nuevo llega sin
recargar a mano.
