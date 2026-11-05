#!/bin/sh
# Renderiza los vídeos indicados (o todos) a app/Data/manual_finanzas/videos/ (uno tras otro).
cd "$(dirname "$0")/.." || exit 1
for n in ${@:-general vender-cobrar saldar-clases gastos alumnos descuento-auto revision-cierre}; do
  echo "== $n"
  (cd finanzas-$n && npx hyperframes render --crf 24 -o ../../app/Data/manual_finanzas/videos/finanzas-$n.mp4) || echo "FALLO $n"
done
echo FIN-RENDER
