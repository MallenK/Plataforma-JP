# TICKET-009 — En un chat con mucho texto no se cargan todos los mensajes

| Campo        | Valor                                                    |
|--------------|----------------------------------------------------------|
| Categoría    | `bug` — Error / Bug                                      |
| Prioridad    | `media`                                                  |
| Estado       | `en investigación` — reproducido en local, sin fix aún   |
| Módulo       | Mensajes (chat 1-a-1)                                    |
| Rama         | `fix/mensajes-historial-largo`                           |
| Detectado en | Producción (reporte de usuario con captura de pantalla)  |
| Entregado en | — (pendiente)                                            |

## Descripción

Reporte: «si hay mucho texto en un chat en la parte de Mensajes, no se cargan
todos los mensajes».

La captura que acompaña al reporte es una conversación entre el entrenador y
un alumno (cuenta que usa la familia para escribir): varios mensajes largos
seguidos (resúmenes de partido de 3-6 frases) y respuestas cortas del
entrenador. No se incluyen aquí nombres ni datos del alumno real.

## Reproducción en local

Primer paso pedido: montar una conversación **mucho más grande** que la de la
captura para poder reproducirlo.

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

Cómo verlo:

1. <http://localhost:8080> → login como el entrenador (o el alumno).
2. **Mensajes** → abrir la conversación con el otro usuario.
3. Subir con scroll hasta arriba.

- **Esperado:** el primer mensaje es el de presentación de la familia
  («Hola! Soy la madre de Marc…», hace ~90 días).
- **Obtenido:** el historial empieza a mitad de la conversación y no hay
  forma de ver los mensajes anteriores.

Comprobado por HTTP contra la app en Docker (sesión real del entrenador,
`POST /mensajes/open`):

| Dato                              | Valor                  |
|-----------------------------------|------------------------|
| Mensajes en BD (conversación #37) | 212                    |
| Mensajes devueltos al abrir       | **50**                 |
| Primero devuelto                  | 2026-08-29 17:23       |
| Primero real                      | 2026-06-18 09:00       |

## Causa (hipótesis a confirmar)

No depende de lo largo que sea el texto, sino del **número de mensajes**
(con mensajes largos se llega antes a notar que «faltan»):

- `MensajesController::ajaxOpenConversation()` carga el historial con
  `MessageModel::getForConversation($convId, 50)` → solo los **50 últimos**.
- `getForConversation()` ya admite paginar hacia atrás (`$beforeId`), pero
  **ninguna ruta lo usa** y la vista `mensajes/index.php` no tiene «cargar
  mensajes anteriores» ni carga al hacer scroll hacia arriba.
- El polling (`GET /mensajes/:id/poll?since=`) solo trae mensajes nuevos.

Pendiente de confirmar con el usuario que lo reportó que el síntoma es
«falta el principio de la conversación» y no otro (p. ej. un mensaje largo
cortado visualmente).

## Solución aplicada

Pendiente. De momento la rama solo contiene el seeder de reproducción y este
ticket.

## Verificación

- Seeder ejecutado dos veces en Docker: 212 mensajes y una sola conversación
  (sin duplicados).
- Reproducido por HTTP con la sesión del entrenador: 50 de 212 mensajes.

## Notas de despliegue

- `LongConversationSeeder.php` **no se ejecuta en Hostinger ni en Render**
  (solo entorno local).
- Sin migraciones ni cambios de `.env` por ahora.
