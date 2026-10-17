# Generador de los vídeos «Cómo instalar la app»

Cuatro vídeos HyperFrames (mismo estilo que `../cancelar-sesion`): **iPhone**, **Android** (vertical 1080×1920) y **Windows**, **Mac** (horizontal 1920×1080).
Sin audio: texto grande por pasos, aros rojos y dedo/ratón que señalan dónde pulsar.

```
node build.cjs iphone|android|windows|mac|all   # escribe ../instalar-<nombre>/index.html (+ assets, package.json…)
node probe.cjs <nombre>                          # mide dónde caen los botones dibujados (probe-<nombre>.json); luego build otra vez
cd ../instalar-<nombre> && npm run check && npm run render
```

- `spec-<nombre>.cjs`: pasos (texto + tiempos), capas (pantallas), aros, toques/clics y zooms de cada vídeo. Para cambiar un texto o un tiempo, se edita aquí y se regenera.
- `ui-ios.cjs`, `ui-android.cjs`: piezas de interfaz del sistema dibujadas en HTML/CSS (Safari, hoja de compartir, Chrome, teclados…).
  Windows y Mac llevan sus piezas dentro de su propia spec.
- `assets/`: capturas de la app. Las pantallas de Configuración → Notificaciones son una **réplica** (`capturas/replica.html`, con el CSS y los textos reales de la app) renderizada con `capturas/capturar.cjs`; el login es el real.
- Las pantallas del sistema (Safari, Chrome, Dock, diálogos de permiso…) son **ilustraciones recreadas**, no capturas: si Apple/Google cambian su interfaz, hay que retocarlas aquí.
- Dirección que se enseña: `app.jppreparation.com`. El «Instalar la app en el móvil» del login lleva a `/instalar`.
