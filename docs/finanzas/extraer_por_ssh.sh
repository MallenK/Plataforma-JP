#!/usr/bin/env bash
# Ejecuta extraccion_prod_anonimizada.sql en producción por SSH y descarga el
# resultado a ~/Documents/finanzas_export_jp/ (FUERA de cualquier repo).
#
#  - Solo lectura (dos SELECT).
#  - Credenciales: se leen del .env del servidor a un fichero temporal 600 que
#    se borra al terminar. No salen por pantalla.
#  - Sal: aleatoria, generada en el servidor y descartada. Nadie la ve.
#  - Al final borra todo lo creado en el servidor.
#
# Uso, desde Git Bash:
#     cd ~/Desktop/Plataforma-JP/WEB/Plataforma-JP
#     bash docs/finanzas/extraer_por_ssh.sh
set -euo pipefail

HOST="hostinger-jppreparation"
HERE="$(cd "$(dirname "$0")" && pwd)"
DEST="$HOME/Documents/finanzas_export_jp"   # fuera de cualquier repo git
mkdir -p "$DEST"

scp -q "$HERE/extraccion_prod_anonimizada.sql" "$HOST:finanzas_extraccion.sql"

REMOTE_TGZ="$(ssh "$HOST" 'bash -s' <<'REMOTE'
set -euo pipefail
cd "$HOME"
rm -rf "$HOME"/finanzas_export_*/ 2>/dev/null || true   # restos de intentos anteriores
APP="$HOME/domains/jppreparation.com/public_html/app"
SQL="$HOME/finanzas_extraccion.sql"
envget() {
  local line v
  line="$(grep -E "^[[:space:]]*database\.default\.$1[[:space:]]*=" "$APP/.env" | tail -1)" || true
  v="${line#*=}"
  v="${v#"${v%%[![:space:]]*}"}"; v="${v%"${v##*[![:space:]]}"}"
  v="${v#\"}"; v="${v%\"}"; v="${v#\'}"; v="${v%\'}"
  v="${v//\\/\\\\}"; v="${v//\"/\\\"}"
  printf '%s' "$v"
}
umask 077
CNF="$(mktemp)"; S2="$(mktemp)"
trap 'rm -f "$CNF" "$S2" "$SQL"' EXIT
{
  echo "[client]"
  echo "host=\"$(envget hostname)\""
  echo "user=\"$(envget username)\""
  echo "password=\"$(envget password)\""
  echo "database=\"$(envget database)\""
  echo "default-character-set=utf8mb4"
} > "$CNF"
SALT="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
OUT="$HOME/finanzas_export_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$OUT"
awk '/^SELECT .version. AS tipo/{f=1} f{print} f&&/;[[:space:]]*$/{exit}' "$SQL" > "$OUT/p1.sql"
awk -v s="$SALT" '/^WITH cfg AS/{f=1; sub(/CAMBIA_ESTO\x27 AS sal/, s "\x27 AS sal")} f{print}' "$SQL" > "$S2"
unset SALT
if grep -q "CAMBIA_ESTO' AS sal" "$S2"; then echo "la sal no se aplicó" >&2; exit 1; fi
mysql --defaults-extra-file="$CNF" -B < "$OUT/p1.sql" > "$OUT/paso1_estructura.tsv"
mysql --defaults-extra-file="$CNF" -B < "$S2"         > "$OUT/paso2_datos.tsv"
rm -f "$OUT/p1.sql"
{
  echo "paso1 filas: $(($(wc -l < "$OUT/paso1_estructura.tsv")-1))"
  echo "paso2 filas: $(($(wc -l < "$OUT/paso2_datos.tsv")-1))"
  echo "paso2 por tabla:"; cut -f1 "$OUT/paso2_datos.tsv" | tail -n +2 | sort | uniq -c
  echo "filas bloqueadas por @: $(grep -c 'bloqueado' "$OUT/paso2_datos.tsv" || true)"
} >&2
tar -czf "$OUT.tar.gz" -C "$HOME" "$(basename "$OUT")" && rm -rf "$OUT"
basename "$OUT.tar.gz"
REMOTE
)"

scp -q "$HOST:$REMOTE_TGZ" "$DEST/"
ssh "$HOST" "rm -f '$REMOTE_TGZ'"
tar -xzf "$DEST/$REMOTE_TGZ" -C "$DEST" && rm -f "$DEST/$REMOTE_TGZ"
echo
echo "Listo. Datos en: $DEST/${REMOTE_TGZ%.tar.gz}/"
echo "Borrado del servidor: OK"
