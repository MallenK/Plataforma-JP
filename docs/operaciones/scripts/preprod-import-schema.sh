#!/bin/sh
# Clona el ESQUEMA de la BBDD local (jp_preparation, Docker) a la BBDD de
# PRE-PRODUCCIÓN de Hostinger + la tabla `migrations` con sus datos, para que
# CI4 la vea como "ya migrada".
#
# Por qué esto y no `php spark migrate`: las migraciones de este proyecto no
# corren limpias desde cero (ver TICKET-2026-00010): CreateUsers deja
# users.id SIGNED y ~30 migraciones lo referencian UNSIGNED (FK errno 150),
# y hay 18 columnas con utf8mb4_0900_ai_ci (MySQL 8) que MariaDB no soporta.
#
# NO toca producción: el usuario de pre-prod solo tiene permisos sobre su BBDD.
#
# Uso (desde el host, ejecuta DENTRO de jp_db):
#   docker cp docs/operaciones/scripts/preprod-import-schema.sh jp_db:/tmp/imp.sh
#   docker exec -e PREPROD_DB_HOST -e PREPROD_DB_NAME -e PREPROD_DB_USER -e PREPROD_DB_PASS \
#     -e LOCAL_DB_USER=jp_user -e LOCAL_DB_PASS=jp_pass -e LOCAL_DB_NAME=jp_preparation \
#     jp_db sh /tmp/imp.sh
set -e

: "${PREPROD_DB_HOST:?}"; : "${PREPROD_DB_NAME:?}"; : "${PREPROD_DB_USER:?}"; : "${PREPROD_DB_PASS:?}"
LOCAL_DB_USER="${LOCAL_DB_USER:-jp_user}"
LOCAL_DB_PASS="${LOCAL_DB_PASS:-jp_pass}"
LOCAL_DB_NAME="${LOCAL_DB_NAME:-jp_preparation}"

case "$PREPROD_DB_NAME" in
  *jpapp*|*prod*) echo "ABORTADO: '$PREPROD_DB_NAME' parece producción"; exit 1 ;;
esac

REMOTE="mysql -h $PREPROD_DB_HOST -u $PREPROD_DB_USER -p$PREPROD_DB_PASS $PREPROD_DB_NAME"

echo ">>> DESTINO: $PREPROD_DB_HOST / $PREPROD_DB_NAME"
echo ">>> Producción NO se toca (usuario y BBDD distintos, sin acceso remoto)."
echo

echo "=== 1) Volcado del esquema local ==="
mysqldump --no-data --no-tablespaces --skip-add-drop-table --skip-comments \
  -u"$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" "$LOCAL_DB_NAME" > /tmp/preprod.sql
mysqldump --no-create-info --no-tablespaces --skip-comments \
  -u"$LOCAL_DB_USER" -p"$LOCAL_DB_PASS" "$LOCAL_DB_NAME" migrations >> /tmp/preprod.sql
sed -i 's/utf8mb4_0900_ai_ci/utf8mb4_general_ci/g' /tmp/preprod.sql
echo "   lineas: $(wc -l < /tmp/preprod.sql)"

echo "=== 2) DROP de todas las tablas actuales en $PREPROD_DB_NAME ==="
{
  echo "SET FOREIGN_KEY_CHECKS=0;"
  $REMOTE -N -e "SELECT CONCAT('DROP TABLE IF EXISTS \`', table_name, '\`;')
                 FROM information_schema.tables WHERE table_schema='$PREPROD_DB_NAME';"
  echo "SET FOREIGN_KEY_CHECKS=1;"
} | $REMOTE

echo "=== 3) Importar el esquema ==="
{ echo "SET FOREIGN_KEY_CHECKS=0;"; cat /tmp/preprod.sql; echo "SET FOREIGN_KEY_CHECKS=1;"; } | $REMOTE

echo "=== 4) Comprobación ==="
$REMOTE -e "
  SELECT COUNT(*) AS tablas FROM information_schema.tables WHERE table_schema='$PREPROD_DB_NAME';
  SELECT COUNT(*) AS filas_migrations FROM migrations;
  SELECT COUNT(*) AS usuarios FROM users;"

echo
echo "=== ESQUEMA IMPORTADO. Ahora: preprod-spark.sh db:seed DatabaseSeeder / BulkDemoDataSeeder / PreprodTicketsSeeder ==="
