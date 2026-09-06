#!/bin/sh
# Ejecuta `php spark ...` contra la BBDD de PRE-PRODUCCIÓN de Hostinger.
# Hace un swap TEMPORAL del .env del contenedor y lo restaura (trap).
#
# Uso (desde el host):
#   PREPROD_DB_HOST=... \
#   PREPROD_DB_NAME=... \
#   PREPROD_DB_USER=... \
#   PREPROD_DB_PASS=... \
#   docker exec -e PREPROD_DB_HOST -e PREPROD_DB_NAME -e PREPROD_DB_USER -e PREPROD_DB_PASS \
#     jp_app sh /var/www/html/docs/operaciones/scripts/preprod-spark.sh db:seed PreprodTicketsSeeder
set -e
cd /var/www/html

: "${PREPROD_DB_HOST:?define PREPROD_DB_HOST}"
: "${PREPROD_DB_NAME:?define PREPROD_DB_NAME}"
: "${PREPROD_DB_USER:?define PREPROD_DB_USER}"
: "${PREPROD_DB_PASS:?define PREPROD_DB_PASS}"
[ "$#" -ge 1 ] || { echo "uso: preprod-spark.sh <comando spark...>"; exit 1; }

case "$PREPROD_DB_NAME" in *jpapp*|*prod*) echo "ABORTADO: '$PREPROD_DB_NAME' parece producción"; exit 1 ;; esac

cp .env .env.local.bak
trap 'mv -f .env.local.bak .env 2>/dev/null || true; echo; echo "[.env local restaurado]"' EXIT INT TERM

sed -i \
  -e "s#^database.default.hostname .*#database.default.hostname = ${PREPROD_DB_HOST}#" \
  -e "s#^database.default.database .*#database.default.database = ${PREPROD_DB_NAME}#" \
  -e "s#^database.default.username .*#database.default.username = ${PREPROD_DB_USER}#" \
  -e "s#^database.default.password .*#database.default.password = ${PREPROD_DB_PASS}#" \
  .env

echo ">>> spark $* (contra ${PREPROD_DB_NAME})"
echo
php spark "$@"
