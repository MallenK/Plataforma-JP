# TICKET-012 — Subida de adjuntos en clases lenta y con error confuso

| Campo        | Valor                                              |
|--------------|----------------------------------------------------|
| Categoría    | `bug` — Error / Performance                        |
| Prioridad    | `media`                                            |
| Estado       | `resuelto`                                         |
| Módulo       | Clases · Observaciones · Adjuntos (fotos/vídeos)  |
| Rama         | `fix/subida-adjuntos-clases`                       |
| Detectado en | Producción (Hostinger)                             |
| Entregado en | `v1.12.0` (pendiente)                              |

## Descripción del problema

Un usuario sube un archivo de ~150 MB (vídeo `.mov` de 1 minuto, grabado en
iPhone) a una sesión de clase. El navegador:

1. Pasa ~2 minutos cargando sin retroalimentación visible.
2. Muestra un error de alerta personalizada: **"El archivo es demasiado grande
   (máximo X MB por envío)"**.
3. El adjunto no se guarda.

El error es confuso porque:
- El archivo pesa 150 MB, pero el tope anunciado era 80 MB (vídeo) o 5 MB
  (otros).
- El usuario no tiene forma de saber si el límite es de la app, del servidor,
  del navegador o de la red.
- Espera 2 minutos para enterarse de algo que se puede comprobar **antes** de
  subir.

---

## Causa raíz

El controlador `ClasesController::uploadAttachment()` pasaba un tope fijo (80 MB
para vídeo) sin consultar lo que el servidor PHP realmente aceptaba
(`upload_max_filesize`, `post_max_size`). Un hosting compartido como Hostinger
puede tener límites más bajos que el `.htaccess` de JP (520 MB), así que:

- El archivo entraba sin problemas en `POST` (hasta 520 MB en local).
- Pero en Hostinger con límites menores, el POST llegaba truncado o se
  rechazaba con un HTTP 413 (*Payload Too Large*).
- El filtro `PostSizeGuardFilter` **sí** detectaba esto y devolvía el error en
  HTML (redireccionaba hacia atrás).

La subida se hacía con **formulario tradicional** (POST y redirect), sin
retroalimentación mientras se enviaba. El usuario esperaba sin saber si el
archivo se estaba cargando.

---

## Solución aplicada

### 1. **Límites dinámicos en el servidor** (`ClasesController`)

- Nuevas constantes públicas:
  - `ATTACHMENT_MAX_DEFAULT_BYTES = 5 * 1024 * 1024` (5 MB, imágenes/PDF)
  - `ATTACHMENT_MAX_VIDEO_BYTES = 200 * 1024 * 1024` (200 MB, vídeos)

  **Nota**: Los 200 MB son compatibles con la mayoría de hostings compartidos
  (que tienen 200-256 MB de límite de POST). En Hostinger se puede subir hasta
  200 MB sin problemas; en otros hostings puede que haya que reducir a 100 MB.

- Nuevo método estático `attachmentLimits()`:
  ```php
  // Lee upload_max_filesize + post_max_size real del servidor
  // y devuelve los límites efectivos (tope de la app recortado por PHP).
  return ['video' => int, 'other' => int];
  ```
  De modo que:
  - **En local**: devuelve 200 MB (vídeo) y 5 MB (otros) — son los límites del
    `.htaccess`.
  - **En Hostinger**: si post_max_size=256M, devuelve `min(200M, 256M) = 200 MB`.
  - **En otro hosting con límite bajo**: si post_max_size=100M, devuelve
    `min(200M, 100M) = 100 MB`.

### 2. **Aviso previo en el navegador** (`attach-upload.js`)

Nuevo archivo: `public/assets/js/attach-upload.js` — módulo que:

- **Antes de subir**: valida el tamaño del archivo elegido en el `<input>` contra
  los límites del servidor (recibidos en atributos `data-max-video`,
  `data-max-other`).
- **Si es demasiado grande**: muestra un toast con consejo:
  - Para imágenes/documentos: "El archivo pesa 500 MB, máximo 5 MB. Redimensiónalo."
  - Para vídeos: "El archivo pesa 300 MB, máximo 200 MB. Recórtalo en menor calidad e inténtalo de nuevo."
- **Si pasa el aviso**: sube por XHR (no formulario tradicional) y muestra:
  - Porcentaje en tiempo real ("Subiendo… 45%").
  - Si el servidor rechaza: HTTP 413 → toast "El archivo es demasiado grande
    para el servidor."

### 3. **Respuesta JSON en subidas AJAX** (`ClasesController::uploadAttachment()`)

El controlador ahora detecta si es AJAX (`$request->isAJAX()`) y responde:

- **Éxito (200 OK)**: `{success: true, csrf: "<nuevo_token>"}`
  → el navegador recarga.
- **Error (422/413/403)**: `{error: "Mensaje", csrf: "<nuevo_token>"}`
  → toast, sin recargar, pero regenera el token CSRF.

Sin AJAX (formulario clásico), sigue usando flash data + redirect.

### 4. **Atributos de datos en las vistas** (`clases/show.php`)

Ambos formularios ahora incluyen:
```html
<form
  data-attach-upload
  data-max-video="<?= $attachLimits['video'] ?>"
  data-max-other="<?= $attachLimits['other'] ?>"
  ...
>
```

El script `attach-upload.js` inicializa todos los formularios con este atributo.

---

## Cambios en detalle

### Controllers

- **`ClasesController.php`**:
  - Constantes públicas de límites.
  - Método `attachmentLimits()` estático.
  - `uploadAttachment()` refactorizado: elige respuesta JSON o redirect según
    `isAJAX()`.
  - Nueva función `$fail()` que encapsula ambas rutas.
  - Paso de `attachLimits` a la vista.

### Views

- **`app/Views/clases/show.php`**:
  - Ambos `<form id="..." data-attach-upload ...>` con atributos de límite.
  - Inclusión de `<script src="/assets/js/attach-upload.js">` en la sección
    `scripts`.

### JavaScript

- **`public/assets/js/attach-upload.js`** (nuevo, 170 líneas):
  - Inicializa formularios con `data-attach-upload`.
  - Valida tamaño al elegir archivo + al hacer submit.
  - Subida por XHR con barra de progreso.
  - Menejo de casos: éxito, error 413/403, pérdida de conexión.
  - Fallback: sin JavaScript, el formulario sigue siendo un POST tradicional.

### Tests

- **`tests/unit/UploadAttachmentLimitsTest.php`** (nuevo):
  - Verifica que los límites sean públicos.
  - Verifica que `attachmentLimits()` devuelva un array con 'video' y 'other'.
  - Verifica que los límites respeten lo que dice `php.ini`.

---

## Experiencia del usuario (antes vs. después)

### ❌ Antes

1. Usuario elige un vídeo de 150 MB.
2. Hace clic en "Subir adjunto".
3. Espera 2 minutos en blanco.
4. Sale un error: "El archivo es demasiado grande (máximo 80 MB)."
5. Confundido: ¿es 80 MB realmente el límite? ¿Dónde está escrito?
6. Reinicia el navegador, intenta de nuevo con un archivo de 79 MB.
7. Vuelve a fallar (porque el hosting tiene post_max_size=100M, no 520M).

### ✅ Después

1. Usuario elige un vídeo de 150 MB.
2. Aparece un toast inmediato: "El archivo pesa 150 MB, máximo 200 MB. Recórtalo..."
3. El usuario toma medidas (comprime en iMovie) y elige un vídeo de 120 MB.
4. Hace clic en "Subir adjunto".
5. Barra de progreso: "Subiendo… 0%", "Subiendo… 45%", etc.
6. Cuando termina: "Adjunto guardado" — página se recarga.

---

## Datos de prueba

Para reproducir en local:

```bash
# 1. Docker con .htaccess en 520 MB (ya está):
docker compose up

# 2. Crear una sesión:
# Ir a /clases, crear una clase

# 3. Intentar subir un vídeo:
# Abrir DevTools → Generar un archivo simulado o grabar un MOV.
# El aviso debe decir "Máximo 200 MB", no 80.
```

Para reproducir en Hostinger:

```bash
# Si post_max_size en hPanel se redujo a 100 M:
# Subir un vídeo de 150 MB
# → Toast debe decir "Máximo 100 MB" (no 200)
```

---

## Notas de despliegue

### Archivos a llevar a producción

1. **`app/Controllers/ClasesController.php`** — constantes, método
   `attachmentLimits()`, lógica de `uploadAttachment()`.
2. **`app/Views/clases/show.php`** — atributos y script.
3. **`public/assets/js/attach-upload.js`** — **nuevo**, 170 líneas.
4. **`tests/unit/UploadAttachmentLimitsTest.php`** — test unitario (solo
   desarrollo).

### Backwards compatibility

- ✅ Sin JavaScript: los formularios siguen funcionando con POST tradicional.
- ✅ Sin cambios en `routes.php`, `migrations`, `.env`, base de datos.
- ✅ El token CSRF se regenera en cada respuesta JSON.
- ✅ El error `PostSizeGuardFilter` ya estaba (v1.1.7); este ticket mejora el
  UX previo.

### Verificación en producción

1. Entrar a `/clases/{id}` donde hay una sesión en curso.
2. Abrir DevTools → Console → verificar que `attach-upload.js` se cargó sin
   errores.
3. Subir un archivo pequeño (~2 MB) → debe funcionar con barra de progreso.
4. Intentar subir un archivo de más del tope que dice el `data-max-*` → toast
   inmediato.

---

## Versión y fecha

- **Rama**: `fix/subida-adjuntos-clases` (desde `origin/main`).
- **Versión de app**: 1.12.0 (será un bump de MINOR).
- **PR**: Pendiente de abrir y mergear a main.
- **Deploy**: Tras merge, seguir el runbook en `docs/operaciones/`.
