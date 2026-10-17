# Auditoría «sin recargar la pantalla» — 17/10/2026

Pregunta: ¿la plataforma funciona sin recargar la pantalla constantemente, es dinámica y tiene indicadores de carga (loaders)?

**Respuesta corta:** a medias. Lo que cambia solo (avisos, calendarios, chat, contadores) ya era dinámico desde la v2.0.0. Lo que el usuario hace (pulsar un enlace, guardar un formulario) seguía recargando la página entera en casi todo, y no había ningún indicador de que algo estuviera cargando. Con esta rama, **Finanzas completo** (navegación + los 18 formularios) funciona sin recargar y **toda la plataforma** tiene barra de progreso y botones «ocupados». El resto de módulos sigue recargando al guardar; abajo está el porqué y el plan para convertirlos uno a uno.

## 1. Inventario (medido sobre el código, 17/10/2026)

| Dato | Valor |
|---|---|
| Vistas (sin las de error) | 78 |
| Formularios POST | 118 |
| …de ellos ya sin recarga antes de esta rama | 2 (`data-live-form`: «No puedo ir» y mensaje del alumno) |
| …sin recarga ahora (Finanzas) | +18 |
| Zonas que se refrescan solas (`data-live`) | 4 (acciones rápidas y próximas clases del dashboard, respuestas de un ticket, «mi clase») |
| Vistas con `fetch`/AJAX propio | 11 vistas + 6 ficheros JS |
| Vistas con `<script>` en línea | 33 (21 con `let`/`const`/`class` de nivel superior) |
| Vistas con tablas paginadas (DataTables) | 20 |

Formularios POST por módulo: Clases 21 · Finanzas 18 · Alumnos 17 · Configuración 12 · Torneos 11 (desactivado) · Bonos 10 · Perfil 10 · Entrenadores 8 · Documentación 6 · Compras 3 (desactivado) · Auth 1 · Componentes 1.

## 2. Lo que ya era dinámico (no se toca)

- **Sondeo único** `GET /live/ping` cada 30 s con la pestaña visible (y al instante con un push): contadores de la barra superior (avisos, mensajes, tickets) y «sellos de cambio» por zona.
- **Zonas `data-live`**: si cambia una clase, un bono o un ticket, se vuelven a pedir y se sustituyen solas (nunca si el usuario está escribiendo).
- **Calendarios** (`CAL` en /clases y `DBCAL` en el dashboard): se repintan con `JPLive.on(['class'], …)`.
- **Chat**: sondeo propio y carga progresiva del historial.
- **Pasar lista**: descontar/devolver/cambiar bono por `fetch`.
- **Borrado con animación** (`ajax-delete.js`), **reporte rápido de ticket** (modal AJAX), **subida de adjuntos con progreso** (XHR).

## 3. Lo que se añade en esta rama

### Loaders (todas las pantallas)
- **Barra de progreso superior** (`JPLoader`): se enciende al pulsar un enlace que recarga la página, al enviar un formulario y durante cualquier carga sin recarga. Se apaga sola a los 9 s si algo no termina (p. ej. una descarga).
- **Botones «ocupados»**: al enviar un formulario el botón se desactiva y muestra un spinner (acaba con el doble clic). Excluir con `data-no-loader`.
- **Esqueleto (skeleton)** mientras carga una región: aparece a los 140 ms (las cargas rápidas no parpadean) y se retira al pintar.
- **Destello suave** cuando una zona `data-live` se actualiza sola, para que se note que algo cambió sin tapar nada.
- Todo respeta `prefers-reduced-motion` y anuncia «Cargando…» a lectores de pantalla.

### Navegación y formularios sin recargar (`JPSoft`) — Finanzas
- El controller pasa `softRegion`; `layouts/app.php` envuelve el contenido en `<div data-soft-region="finanzas" data-soft-prefix="finanzas">`.
- Los **enlaces** bajo `/finanzas` se piden por `fetch` y solo cambia la región; se actualizan el título de la pestaña, la barra superior y la opción activa del menú; el botón **atrás/adelante** funciona (`pushState`/`popstate`).
- Los **formularios** (GET: periodo, filtros, buscador; POST: cobros, gastos, cargos, anulaciones, ajustes, acceso, cierres de la Revisión) se envían por `fetch`; el servidor sigue respondiendo con redirección + mensaje, que sale como **aviso emergente** (`[data-flash]`). Los formularios con confirmación (`data-ru-confirm`) se envían así tras aceptar.
- **Red de seguridad**: ante sesión caducada, error 4xx/5xx, respuesta sin la región, no-HTML o fallo de red, se hace la navegación normal de siempre. Un POST fallido **nunca** se repite como GET: se avisa y se recarga la pantalla actual si procede.
- Sin JavaScript, o si algo no carga, todo funciona como antes.

## 4. Lo que sigue recargando y por qué

Todos los demás módulos (Clases, Alumnos, Configuración, Bonos, Perfil, Entrenadores, Documentación) siguen el patrón clásico **POST → redirección → página completa**. No se ha convertido todo en una sola pasada a propósito:

1. **Scripts en línea.** 21 vistas declaran `let`/`const`/`class` en el nivel superior de un `<script>`. Si una navegación sin recarga volviera a ejecutarlos, el navegador lanza `SyntaxError: Identifier … has already been declared` y la pantalla queda rota. Una SPA global exigiría reescribir esas 21 vistas a la vez.
2. **Manejadores globales** (`onclick="abrirModal()"`), modales de Bootstrap y DataTables que se enlazan al cargar: no se pueden volver a montar «a ciegas».
3. **Riesgo/beneficio**: Clases y Pasar lista son lo más delicado de la plataforma (bonos, asistencia, avisos); una regresión ahí cuesta más que un par de recargas.

### Plan recomendado (de menos a más riesgo)
Para cada módulo: (a) pasar `softRegion` desde el controller; (b) mover sus scripts en línea a un fichero propio envuelto en IIFE que se reinicie con `jp:soft-loaded`; (c) marcar los mensajes con `data-flash`; (d) cambiar `form.submit()` por `form.requestSubmit()`; (e) test y prueba en navegador.

| Orden | Módulo | Motivo |
|---|---|---|
| 1 | Bonos | 10 formularios, una vista de lista; los scripts ya están acotados |
| 2 | Notificaciones y Tickets | ya usan `fetch` en parte |
| 3 | Alumnos (lista y ficha) | 17 formularios, mucho uso diario |
| 4 | Configuración y Entrenadores | uso ocasional, solo administración |
| 5 | Perfil y Documentación | subidas de archivos: conservar el progreso propio |
| 6 | Clases / Pasar lista | lo último y con tests de integración previos |

## 5. Cómo se usa (para desarrolladores)

```php
// Controller (BaseController::view data): activa la región
return view('bonos/index', $data + ['softRegion' => 'bonos']);   // prefijo = 'bonos' (o 'softPrefix' si difiere)
```
```html
<!-- Mensajes que deben salir como toast y no pintarse dos veces -->
<div class="alert-jp success" data-flash>…</div>
<!-- Excluir un enlace o formulario -->
<a href="…" data-no-soft>…</a>   <form data-no-soft>…</form>
<!-- Sin spinner en el botón -->
<button data-no-loader>…</button>
```
```js
// Reiniciar código de la página tras pintar contenido nuevo
window.addEventListener('jp:soft-loaded', function (e) { /* e.detail.region, e.detail.url */ });
// Barra de progreso en tus propias cargas
JPLoader.start(); fetch(…).finally(JPLoader.done);
```
Reglas: dentro de una región, los scripts en línea no pueden declarar `let/const/class` de nivel superior (envolver en IIFE); `onchange="this.form.requestSubmit()"` en lugar de `this.form.submit()`.

## 6. Verificación
- `node tests/js/soft-nav.test.cjs` (jsdom, 78 comprobaciones): elegibilidad de enlaces y formularios, fallbacks, POST fallido, doble envío, navegaciones solapadas (la barra y el esqueleto no se quedan colgados), atrás/adelante, scripts, barra, botones y esqueleto.
- Resto de tests JS existentes (`live`, `radix-confirm`, `ajax-delete`, `money-privacy`) sin cambios.
- **Prueba real en Chrome** contra la app local (sesiones de prueba, sin tocar contraseñas): pestañas de Finanzas sin recargar (el marcador de la página sigue vivo), URL y título correctos, atrás, selector de mes, barra de progreso y esqueleto visibles con red lenta, tablas DataTables montadas tras cada cambio, guardar/revocar el acceso sin recargar y con aviso, y cero excepciones JS.
- Hallazgo de la prueba real (corregido): una navegación que quedaba obsoleta por otra más reciente no apagaba la barra de progreso. Ahora cada `start()` lleva siempre su `done()`.
- Nota de entorno: en la copia local `jp_prodlike` cada petición tarda ~8 s la primera vez (Docker sobre Windows); no es del código nuevo.
