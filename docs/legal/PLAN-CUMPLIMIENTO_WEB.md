# Plan de cumplimiento normativo (RGPD / LOPDGDD / LSSI-CE)

Estado: **PLAN, nada aplicado todavía.** Se ejecutará en una rama nueva (`cumplimiento-rgpd`) cuando se dé el visto bueno. No es asesoramiento jurídico: lo marcado **[ABOGADO/DPO]** debe revisarlo un profesional.

## Decisiones tomadas

| Tema | Decisión |
|---|---|
| Dominio canónico | **Sin www** (`https://jppreparation.com`) |
| Testimonios y casos de éxito (Resultados) | **Se dejan como están por ahora.** Queda como riesgo asumido, ver "Riesgos aceptados" |
| Rama de trabajo | Sí, rama propia, pero **no se ejecuta todavía** |
| Analítica | **Sin medición de movimiento por ahora** (decisión del cliente) |
| Banner de cookies | **Evitarlo.** Si la web no usa cookies ni recursos de terceros que requieran consentimiento, no hace falta banner |

## Principio rector: web sin banner

La ausencia de banner solo es legal si **no hay nada que lo exija**. Por eso el plan elimina las fuentes de consentimiento en lugar de ocultar el banner:

- **Sin GTM / GA4** → no hay analítica → no hay cookies que consentir.
- **Sin Google Fonts, Unsplash ni texturas externas** → no se envía la IP a terceros al cargar la página.
- **Sin iframes automáticos de Instagram y Google Maps** → se sustituyen por un bloque estático con botón "Ver mapa" / "Ver en Instagram" (un clic del usuario abre el servicio). No es un modal ni una alerta: es un enlace con imagen.
- Lo único que se guarda en el navegador sería técnico (ninguno tras quitar el consentimiento).

Si en el futuro el cliente quiere analítica, se reactivará el banner (el código actual queda en el historial de git) o se valorará una analítica sin cookies **[ABOGADO/DPO]**.

## Cambios por bloque

### 1. Quitar analítica y banner

| Archivo | Cambio |
|---|---|
| `index.html` | Eliminar Consent Mode, script de GTM y `<noscript>` de GTM |
| `src/App.tsx` | Eliminar `SHOW_COOKIE_BANNER`, `<CookieConsent />` y `trackVirtualPageview` del `RouteChangeHandler` |
| `src/lib/analytics.ts` | Eliminar (o dejar `trackEvent` como no-op si se prefiere no tocar los componentes) |
| `src/components/CookieConsent.tsx` | Eliminar |
| `Navigation.tsx`, `Contacto.tsx` | Quitar las llamadas `trackEvent(...)` (o dejarlas inertes) |
| `.claude/ANALYTICS.md` | Marcar como "desinstalado, pendiente decisión del cliente" |

### 2. Recursos de terceros alojados en local

| Archivo | Cambio |
|---|---|
| `index.html`, `src/index.css` | Descargar Inter (300-700), Oswald (400-700) y Anton a `public/fonts/` (woff2) y declarar `@font-face` en local con `font-display: swap`. Quitar preconnect y hojas de Google |
| `Hero.tsx:96`, `Home.tsx:307`, `Tecnificacion.tsx:244`, `Instalaciones.tsx:32` | Sustituir imágenes de Unsplash por fotos propias optimizadas (`npm run optimize-images`) **[necesito que me indiques qué fotos usar]** |
| `Resultados.tsx:17-31` y `:222-228` | Las imágenes de Unsplash de los casos y avatares: eliminar el campo `image`/`avatar` si no se muestra, o sustituir. Los textos no cambian |
| `Equipo.tsx:303`, `Resultados.tsx:268`, `Programas.tsx:443`, `Tecnificacion.tsx:381` | Sustituir `transparenttextures.com` por una textura propia en CSS (gradiente/patrón SVG inline) |
| `Contacto.tsx:476-482` | Quitar el iframe de Instagram. Sustituir por tarjeta con enlace al perfil (sin cargar nada de Meta) |
| `Contacto.tsx:492-498` | Quitar el iframe de Google Maps. Sustituir por imagen estática propia del mapa + botón "Abrir en Google Maps" (ya existe en la misma sección) |

### 3. Páginas legales

Separar la ruta única `/legal` en tres páginas, enlazadas en el pie de todas las páginas (el pie ya existe en `Navigation.tsx:337-358`):

- `/aviso-legal`: art. 10 LSSI-CE con marcadores `[RAZÓN SOCIAL]`, `[NIF]`, `[DOMICILIO]`, `[EMAIL DE CONTACTO]`, datos registrales si aplica, propiedad intelectual y ley aplicable.
- `/privacidad`: responsable, finalidad, base jurídica (consentimiento para el formulario), destinatarios (proveedor de correo/hosting como encargados), plazo de conservación, derechos y vía de ejercicio (email), derecho a reclamar ante la AEPD, tratamiento de menores de 14 años, ausencia de datos de salud. **[ABOGADO/DPO]**
- `/cookies`: texto breve "esta web no utiliza cookies ni tecnologías de seguimiento" + aviso de que los enlaces externos (Instagram, TikTok, Google Maps, WhatsApp) se rigen por sus propias políticas.
- Mantener `/legal` con redirección a `/aviso-legal` para no romper enlaces.
- Actualizar `public/sitemap.xml` y los enlaces del pie.

### 4. Formulario de contacto (`Contacto.tsx` + `public/contact.php`)

Cambios en el formulario:

- Casilla **obligatoria y sin premarcar** de aceptación de la política de privacidad, con enlace.
- **Primera capa informativa** bajo el botón: responsable, finalidad, base jurídica, destinatarios, derechos y enlace a la política completa.
- Casilla **aparte y sin premarcar** para comunicaciones comerciales (opcional). Solo se mostrará si el cliente va a enviar comunicaciones comerciales; si no, se omite y se deja constancia.
- Campo **"¿Quién rellena el formulario?"**: jugador adulto (+14) o padre/madre/tutor. Si es tutor: campo "Nombre del tutor" obligatorio y declaración expresa "Declaro que actúo en representación del menor". Para menores de 14 el formulario solo lo puede enviar el tutor.
- Campo "Edad" pasa de texto libre a número.
- Aviso junto al campo Mensaje: "No incluyas datos de salud".

Cambios en `contact.php`:

- Validar en servidor la aceptación de privacidad y, si hay menor, el nombre del tutor y la declaración.
- Registrar en el email la fecha/hora del consentimiento y la versión de la política aceptada (como prueba).
- **Quitar la IP** del cuerpo del email (minimización).
- Dejar el destinatario como constante (hoy es un Gmail: ver riesgos).

### 5. Seguridad y dominio

En `public/.htaccess`:

- Redirección 301 de `www.jppreparation.com` → `https://jppreparation.com` y de `http` → `https`.
- Cabeceras: `Strict-Transport-Security` (empezar con `max-age` corto y subirlo tras comprobar), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` restrictivo, `frame-ancestors 'self'` (o `X-Frame-Options: SAMEORIGIN`) y una **CSP** (`default-src 'self'`; con las fuentes e imágenes locales queda muy ajustada).

Cambio de canónica a sin-www en:

- `index.html` (canonical, og:url, og:image, twitter:image y JSON-LD).
- `public/sitemap.xml`, `public/robots.txt` y `public/llms.txt`.
- `.claude/DEPLOYMENT.md` (comandos `curl` de verificación).
- **Fuera del repo:** Search Console (añadir la propiedad sin www y hacer cambio de dirección), perfiles sociales y Google Business si apuntan a www.

### 6. Enlace con la plataforma

- `Navigation.tsx:349-356`: el enlace "Área Privada" a `app.jppreparation.com` se mantiene.
- **Fuera de este repo:** comprobar que la pantalla de acceso de la plataforma enlace a `https://jppreparation.com/aviso-legal`, `/privacidad` y `/cookies`. Hay que hacerlo en el proyecto de la plataforma.

### 7. Correcciones menores

- `Contacto.tsx:91`: unificar la edad mínima (la web dice 4 en la mayoría de sitios, el FAQ de contacto dice 8).
- `Home.tsx:433`: cambiar `wa.me/34600000000` por el número real `34601506857`.
- `Contacto.tsx:476`: se resuelve al sustituir el iframe.
- Pie: ajustar el año/derechos si procede y añadir `[RAZÓN SOCIAL]` cuando se conozca.

### 8. Imágenes de menores (sin cambios de código por ahora)

- Entregar al cliente el **listado de fotos** donde pueden aparecer menores (carrusel Stage 2026, galería de Eventos, Instalaciones, Home, Programas, Tecnificación, Equipo).
- El cliente cotejará cada una con las autorizaciones de derechos de imagen firmadas. Lo que no tenga autorización se retira.
- Proponer un **modelo de autorización de imagen** para tutores **[ABOGADO/DPO]**.

## Riesgos aceptados (decididos por el cliente, sin cambio)

1. **Testimonio con nombre y apellidos reales y otro de posible menor** (`Resultados.tsx:218-229`): sigue publicado sin constancia de consentimiento.
2. **Casos de éxito con menores identificados por nombre y edad** (`Resultados.tsx:10-33`): si son reales hace falta consentimiento del tutor; si no lo son, es riesgo de publicidad engañosa.
3. **Datos del formulario en un Gmail** (`contact.php:12`): Google actúa como encargado sin contrato específico documentado y puede implicar transferencia internacional. Recomendable pasar a un correo de dominio propio.
4. **Sin analítica:** no se podrá medir tráfico ni conversiones.

Recomendación: aunque se mantengan por ahora, dejar el punto 1 y 2 anotados para que el abogado los valore.

## Orden de ejecución propuesto (cuando se dé luz verde)

1. Crear la rama `cumplimiento-rgpd` desde `main`.
2. Bloque 1 (quitar analítica y banner) y bloque 2 (recursos locales): reduce el riesgo de forma inmediata.
3. Bloque 3 (páginas legales con marcadores) y bloque 4 (formulario y `contact.php`).
4. Bloque 5 (seguridad y dominio sin www).
5. Bloques 6-7 (plataforma y correcciones menores).
6. Verificación: `npx tsc --noEmit`, `npm run build`, revisión de la pestaña Red del navegador (cero peticiones a dominios externos al cargar), `curl -I` de cabeceras y redirecciones, prueba del formulario con y sin menor.
7. Un commit por bloque. Despliegue con `npm run deploy` solo tras tu confirmación.

## Datos que necesito del cliente

- Razón social, NIF, domicilio fiscal, email de contacto y datos registrales si los hay.
- Correo destinatario del formulario (¿se mantiene el Gmail?).
- Fotos propias para sustituir las de Unsplash.
- ¿Habrá comunicaciones comerciales? (decide si existe la casilla de marketing).
- Confirmación de si las autorizaciones de imagen de menores están firmadas.
