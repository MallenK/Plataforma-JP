#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
#  Sube la BD local `jp_prodlike` (copia de prod YA anonimizada, ver
#  gen_prodlike_local.py) a la BD de PPR en Hostinger.
#
#  - No toca producción: no lee ni escribe la BD de prod.
#  - Pide una contraseña común nueva para todos los usuarios en PPR (PPR es
#    accesible desde internet, así que no se usa Test1234!).
#  - Quita el usuario 99 (tu superadmin local copiado) antes de subir.
#  - Antes de reemplazar PPR hace copia de seguridad en ~/backups/ del servidor.
#  - Las contraseñas de la BD de PPR se teclean en el servidor; no se guardan.
#
#  Uso, desde Git Bash:
#     cd ~/Desktop/Plataforma-JP/WEB/Plataforma-JP
#     bash docs/ppr/subir_prodlike_a_ppr.sh
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

HOST="hostinger-jppreparation"
LOCAL_DB="jp_prodlike"
ROOT_PW="Qwaszx12345_"           # MySQL de Docker local (docker-compose.yml, solo desarrollo)
TS="$(date +%Y%m%d_%H%M%S)"
WORK="$(mktemp -d)"
DUMP="$WORK/prodlike_$TS.sql"
trap 'rm -rf "$WORK"' EXIT

echo "═══ Subir $LOCAL_DB (local, anonimizada) → PPR ═══"
read -rsp "Contraseña común para TODOS los usuarios en PPR (mín. 10): " PW; echo
read -rsp "Repítela: " PW2; echo
[ "$PW" = "$PW2" ] || { echo "No coinciden."; exit 1; }
[ ${#PW} -ge 10 ] || { echo "Mínimo 10 caracteres."; exit 1; }
HASH="$(printf '%s\n' "$PW" | docker exec -i jp_app php -r 'echo password_hash(rtrim(fgets(STDIN), "\n"), PASSWORD_DEFAULT);')"
unset PW PW2
[[ "$HASH" == \$2y\$* ]] || { echo "No se pudo generar el hash."; exit 1; }

echo "1/4  Volcando $LOCAL_DB desde Docker…"
docker exec -e MYSQL_PWD="$ROOT_PW" jp_db mysqldump -uroot --default-character-set=utf8mb4 \
  --single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF --hex-blob \
  "$LOCAL_DB" > "$DUMP"
{
  # PPR corre `main`, que ya incluye la PWA (prod todavía no): sus tablas.
  cat "$(dirname "$0")/../migraciones/2026-10-05_push_pwa_notificaciones.sql"
  echo
  echo "DELETE FROM users WHERE id = 99;"
  echo "DELETE FROM auth_events;"
  echo "UPDATE users SET password = '$HASH', must_change_password = 0, password_changed_at = NOW();"
} >> "$DUMP"
gzip "$DUMP"
echo "     $(du -h "$DUMP.gz" | cut -f1)"

echo "2/4  Subiendo al servidor…"
ssh "$HOST" 'mkdir -p ~/ppr_import && chmod 700 ~/ppr_import'
scp -q "$DUMP.gz" "$HOST:ppr_import/"
ssh "$HOST" 'cat > ~/ppr_import/importar.sh' <<'REMOTE'
#!/usr/bin/env bash
set -euo pipefail
umask 077
DUMP="$1"
PROD_DB="u912370917_jpapp"
CNF="$(mktemp)"
trap 'rm -f "$CNF" "$DUMP"' EXIT
q() { local v="$1"; v="${v//\\/\\\\}"; v="${v//\"/\\\"}"; printf '%s' "$v"; }

read -rp  "BD PPR · nombre [u912370917_u937091_jppre]: " DB; DB="${DB:-u912370917_u937091_jppre}"
[ "$DB" != "$PROD_DB" ] || { echo "ERROR: esa es la BD de PRODUCCIÓN."; exit 1; }
read -rp  "BD PPR · usuario: " U
read -rsp "BD PPR · contraseña: " P; echo
printf '[client]\nhost="localhost"\nuser="%s"\npassword="%s"\ndefault-character-set=utf8mb4\n' "$(q "$U")" "$(q "$P")" > "$CNF"
unset P
mysql --defaults-extra-file="$CNF" -N -B "$DB" -e "SELECT 1" >/dev/null

N="$(mysql --defaults-extra-file="$CNF" -N -B "$DB" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")"
echo "PPR ($DB) tiene ahora $N tablas. Se hará copia de seguridad y se REEMPLAZARÁ por la copia anonimizada."
read -rp "Escribe REEMPLAZAR para continuar: " R
[ "$R" = "REEMPLAZAR" ] || { echo "Cancelado. PPR no se ha tocado."; exit 1; }

TS="$(date +%Y%m%d_%H%M%S)"
mkdir -p ~/backups
echo "3/4  Copia de seguridad de PPR → ~/backups/ppr_pre_prodlike_$TS.sql.gz"
mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --skip-lock-tables \
          --no-tablespaces --hex-blob "$DB" | gzip > ~/backups/ppr_pre_prodlike_$TS.sql.gz

echo "4/4  Reemplazando PPR…"
DROPS="$(mysql --defaults-extra-file="$CNF" -N -B "$DB" -e \
  "SELECT CONCAT('DROP TABLE IF EXISTS \`', table_name, '\`;') FROM information_schema.tables WHERE table_schema = DATABASE()")"
[ -n "$DROPS" ] && mysql --defaults-extra-file="$CNF" "$DB" -e "SET FOREIGN_KEY_CHECKS=0; $DROPS"
gunzip -c "$DUMP" | mysql --defaults-extra-file="$CNF" "$DB"

echo
mysql --defaults-extra-file="$CNF" -t "$DB" -e "
SELECT 'usuarios' t, COUNT(*) n FROM users UNION ALL SELECT 'bonos', COUNT(*) FROM player_bonos
UNION ALL SELECT 'sesiones', COUNT(*) FROM class_sessions UNION ALL SELECT 'asistencias', COUNT(*) FROM class_session_players
UNION ALL SELECT 'movimientos bono', COUNT(*) FROM bono_movements"
echo "✓ PPR reemplazado. Backup anterior: ~/backups/ppr_pre_prodlike_$TS.sql.gz"
REMOTE

echo "     Conectando para importar (te pedirá los datos de la BD de PPR)…"
ssh -t "$HOST" "bash ~/ppr_import/importar.sh ~/ppr_import/$(basename "$DUMP.gz"); rm -rf ~/ppr_import"

echo
echo "Listo. Entra en PPR con superadmin.1@prodlike.local y la contraseña que has elegido."
echo "Si algo falla en la web, revisa que el código de PPR (Render) sea v1.32.0 o posterior."
