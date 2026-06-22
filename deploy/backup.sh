#!/usr/bin/env bash
#
# Back up AMS_APP customer data (MySQL database + uploaded files + .env)
# to an external drive.
#
# Usage:
#   deploy/backup.sh /Volumes/<YourDrive>
#   BACKUP_DEST=/Volumes/<YourDrive> deploy/backup.sh
#
# Creates: <Drive>/AMS_APP_backup/<timestamp>/
#   - db-AMS_APP-<timestamp>.sql.gz   (full database dump, gzipped)
#   - storage-app-<timestamp>.tar.gz  (uploaded files)
#   - .env.backup                     (needed to restore; contains secrets)
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

DEST="${1:-${BACKUP_DEST:-}}"

if [[ -z "$DEST" ]]; then
  echo "Usage: $0 /Volumes/<YourDrive>" >&2
  echo "Currently mounted drives:" >&2
  ls /Volumes >&2
  exit 1
fi

if [[ ! -d "$DEST" ]]; then
  echo "ERROR: destination not found: $DEST" >&2
  echo "Is the external HDD plugged in and mounted? Check: ls /Volumes" >&2
  exit 1
fi

# --- Read DB credentials from .env (handles leading whitespace) ---
get_env() { grep -E "^[[:space:]]*$1=" .env | tail -1 | cut -d= -f2- | tr -d ' "'; }
DB_DATABASE="$(get_env DB_DATABASE)"
DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"
DB_USERNAME="$(get_env DB_USERNAME)"
DB_PASSWORD="$(get_env DB_PASSWORD)"

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$DEST/AMS_APP_backup/$STAMP"
mkdir -p "$OUT"

# Keep the password out of the process list / shell history by using a temp my.cnf
CNF="$(mktemp)"
trap 'rm -f "$CNF"' EXIT
cat >"$CNF" <<EOF
[client]
host=$DB_HOST
port=$DB_PORT
user=$DB_USERNAME
password=$DB_PASSWORD
EOF

echo "==> Dumping database '$DB_DATABASE' ..."
mysqldump --defaults-extra-file="$CNF" \
  --single-transaction --quick --routines --triggers \
  "$DB_DATABASE" | gzip > "$OUT/db-$DB_DATABASE-$STAMP.sql.gz"

echo "==> Archiving uploaded files (storage/app) ..."
tar -czf "$OUT/storage-app-$STAMP.tar.gz" -C "$APP_DIR" storage/app

echo "==> Copying .env (needed for restore) ..."
cp .env "$OUT/.env.backup"

# Flush to disk before you can safely unplug
sync

echo
echo "==> Backup complete: $OUT"
ls -lh "$OUT"
