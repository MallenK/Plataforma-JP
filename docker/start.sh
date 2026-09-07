#!/bin/bash
set -e

echo "==> Generating .env from environment variables..."

# baseURL: usa APP_BASE_URL si está; si no, la URL pública que Render
# inyecta automáticamente (RENDER_EXTERNAL_URL). Sin ninguna de las dos,
# CI4 4.7 aborta ("baseURL '/' is not a valid URL").
BASE_URL="${APP_BASE_URL:-${RENDER_EXTERNAL_URL:-}}"
case "$BASE_URL" in
  */) : ;;                       # ya acaba en /
  "") echo "[WARN] Sin APP_BASE_URL ni RENDER_EXTERNAL_URL — la app no arrancará bien." ;;
  *)  BASE_URL="$BASE_URL/" ;;   # añade la barra final
esac

cat > /var/www/html/.env << EOF
CI_ENVIRONMENT = ${CI_ENVIRONMENT:-production}

app.baseURL = ${BASE_URL}

database.default.hostname = ${DB_HOST:-localhost}
database.default.database = ${DB_NAME:-}
database.default.username = ${DB_USER:-}
database.default.password = ${DB_PASS:-}
database.default.DBDriver = MySQLi
database.default.port = ${DB_PORT:-3306}
database.default.encrypt = ${DB_ENCRYPT:-false}

# Etiqueta de entorno (banner "PRE-PRODUCCIÓN"). Vacío en prod.
APP_ENV_LABEL = "${APP_ENV_LABEL:-}"

encryption.key = hex2bin:${ENCRYPTION_KEY:-}

email.fromEmail  = "${MAIL_FROM_EMAIL:-}"
email.fromName   = "${MAIL_FROM_NAME:-JP Academy}"
email.protocol   = smtp
email.SMTPHost   = "${SMTP_HOST:-smtp-relay.brevo.com}"
email.SMTPUser   = "${SMTP_USER:-}"
email.SMTPPass   = "${SMTP_PASS:-}"
email.SMTPPort   = ${SMTP_PORT:-587}
email.SMTPCrypto = tls
email.mailType   = html
EOF

echo "==> Running database migrations..."
cd /var/www/html
php spark migrate --all -n 2>&1 || echo "[WARN] Migrations failed, continuing..."

# Seed inicial SOLO en entornos que lo pidan (SEED_DEMO=1) y SOLO si la BBDD
# está vacía de usuarios — así no se re-siembra en cada redeploy de Render.
if [ "${SEED_DEMO:-0}" = "1" ]; then
  USERS=$(php spark db:table users --limit-rows 1 2>/dev/null | grep -c '@' || true)
  if [ "${USERS:-0}" = "0" ]; then
    ENV_LABEL=$(echo "${APP_ENV_LABEL:-}" | tr '[:upper:]' '[:lower:]')
    if [ "$ENV_LABEL" = "demo" ]; then
      # Entorno DEMO: siembra completa (base + volumen + tickets + invitados + chat).
      echo "==> Seeding DEMO data (BBDD vacía)..."
      php spark db:seed DemoSeeder -n 2>&1 || echo "[WARN] DemoSeeder falló"
    else
      echo "==> Seeding pre-prod data (BBDD vacía)..."
      php spark db:seed DatabaseSeeder -n 2>&1 || echo "[WARN] DatabaseSeeder falló"
      php spark db:seed BulkDemoDataSeeder -n 2>&1 || echo "[WARN] BulkDemoDataSeeder falló"
    fi
  else
    echo "==> Seed omitido (la BBDD ya tiene usuarios)."
  fi
fi

echo "==> Starting Apache..."
# Pipe PHP/Apache error log to stdout so Render captures it
mkdir -p /var/log/apache2
exec apache2-foreground 2>&1
