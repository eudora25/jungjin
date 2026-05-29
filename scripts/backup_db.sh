#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./scripts/backup_db.sh               # dumps DB_DATABASE
#   ./scripts/backup_db.sh jungjin_legacy # dumps named database
#
# Notes:
# - Designed for Sail + MariaDB container.
# - Creates storage/backups/db/*.sql.gz and rotates old backups.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DB_NAME="${1:-${DB_DATABASE:-jungjin}}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
BACKUP_DIR="${BACKUP_DIR:-storage/backups/db}"

mkdir -p "$BACKUP_DIR"

timestamp="$(date +%Y%m%d_%H%M%S)"
outfile="${BACKUP_DIR}/${DB_NAME}_${timestamp}.sql.gz"

echo "[backup] db=${DB_NAME}"
echo "[backup] out=${outfile}"

./vendor/bin/sail exec -T mariadb sh -lc \
  "mariadb-dump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" --single-transaction --routines --triggers --databases \"${DB_NAME}\"" \
  | gzip > "$outfile"

echo "[backup] rotate: keep_days=${KEEP_DAYS}"
find "$BACKUP_DIR" -type f -name "${DB_NAME}_*.sql.gz" -mtime +"$KEEP_DAYS" -print -delete || true

echo "[backup] done"

