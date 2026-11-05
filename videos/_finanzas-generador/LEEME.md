# Vídeos tutoriales de Finanzas (HyperFrames + Playwright)

Siete vídeos horizontales (1920×1080, sin audio) con el estilo de `cancelar-sesion` / `instalar-*`:
texto grande por pasos a la izquierda y, a la derecha, **la pantalla real de la plataforma** con aros rojos
y un ratón que señala. Se ven en **Finanzas › Ayuda** (solo admin).

| Spec | Vídeo (carpeta de salida) | Qué enseña |
| --- | --- | --- |
| `general` | `finanzas-general` | Recorrido por todas las pestañas |
| `vender-cobrar` | `finanzas-vender-cobrar` | Asignar bono, descuento, cobro, quién debe |
| `saldar-clases` | `finanzas-saldar-clases` | Saldar clases dadas/programadas al dar un bono |
| `gastos` | `finanzas-gastos` | Registrar y anular un gasto |
| `alumnos` | `finanzas-alumnos` | Cuenta e historial de un alumno |
| `descuento-auto` | `finanzas-descuento-auto` | Descuento automático y «Devolver bono» |
| `revision-cierre` | `finanzas-revision-cierre` | Revisión semanal, cierre y exportación |

## Cómo se regeneran

Requisitos: Docker con la app en `http://localhost:8080` (BD `jp_prodlike`, datos ficticios), Google Chrome,
Node, `playwright` instalado globalmente y `npx hyperframes`.

```bash
node capturar.cjs [spec]      # Playwright: fotografía las pantallas reales (shots/<spec>/) y mide dónde señalar
node build.cjs    [spec|all]  # escribe ../finanzas-<spec>/index.html
cd ../finanzas-<spec>
npx hyperframes check
npx hyperframes render --crf 24 -o ../../app/Data/manual_finanzas/videos/finanzas-<spec>.mp4
```

- `specs/<nombre>.cjs`: escenas (URL, qué hacer en la página, texto, duración y elementos a señalar con un
  selector). Para cambiar un texto o un tiempo se edita aquí y se regenera.
- **No se envía ningún formulario**: se rellena y se fotografía. Las specs que necesitan datos de ejemplo
  (`saldar-clases`, `descuento-auto`) los crean en `prepare` (marcados con `location_custom='ejemplo-video'`)
  y los borran en `cleanup`, aunque falle la captura.
- Los importes salen **difuminados** (privacidad por defecto de la plataforma): no se enseñan cifras reales.
- Si cambia una pantalla de Finanzas o el modal de Bonos, hay que volver a capturar y renderizar.
- Los nombres de archivo que sirve la plataforma están en `FinanzasController::MANUAL_VIDEOS`.
