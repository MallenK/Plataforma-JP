#!/bin/bash
set -e

echo "==> Generating .env from environment variables..."
cat > /var/www/html/.env << EOF
CI_ENVIRONMENT = production

app.baseURL = ${APP_BASE_URL:-''}

database.default.hostname = ${DB_HOST:-localhost}
database.default.database = ${DB_NAME:-}
database.default.username = ${DB_USER:-}
database.default.password = ${DB_PASS:-}
database.default.DBDriver = MySQLi
database.default.port = ${DB_PORT:-3306}
database.default.encrypt = true

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

echo "==> Starting Apache..."
exec apache2-foreground
