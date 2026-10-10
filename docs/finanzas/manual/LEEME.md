# Manual de Finanzas — cómo regenerarlo

El contenido está en `app/Views/finanzas/_manual.php` (se ve en Finanzas › Ayuda) y los
textos de las ayudas «?» en `app/Helpers/finhelp_helper.php`. Capturas y PDF viven en
`app/Data/manual_finanzas/` y solo se sirven a admin/superadmin (`/finanzas/ayuda/...`).

Si cambia alguna pantalla, con Docker levantado y la BD `jp_prodlike` (datos ficticios):

```bash
python -I -X utf8 docs/finanzas/manual/capturas.py app/Data/manual_finanzas <carpeta_temporal>
python -I -X utf8 docs/finanzas/manual/generar_pdf.py app/Data/manual_finanzas <carpeta_temporal>
```

Requisitos: Google Chrome (modo sin ventana), Python con Pillow y PyMuPDF.
Ojo: las cifras de las capturas salen de la copia de prod (nombres ficticios, importes reales
de la academia): el PDF es para JP Preparation, no para enseñarlo a otras academias.
