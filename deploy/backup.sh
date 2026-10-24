#!/bin/bash
# Backup harian DB Community League, simpan 14 hari di ~/backups/communityleague.
# Cron (server naraya ber-zona Asia/Shanghai = WIB+1, jadi 04.30 server = 03.30 WIB):
#   30 4 * * * $HOME/communityleague/backup.sh >> $HOME/backups/communityleague/backup.log 2>&1
# Restore (dump pakai --clean, menimpa isi DB):
#   gunzip -c FILE | docker compose -f ~/communityleague/docker-compose.yml exec -T db psql -U <DB_USERNAME> <DB_DATABASE>
set -euo pipefail
cd "$(dirname "$0")"
DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2)
DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2)
DIR="$HOME/backups/communityleague"
mkdir -p "$DIR"
F="$DIR/communityleague-$(date +%Y%m%d-%H%M).sql.gz"

docker compose exec -T db pg_dump -U "$DB_USER" --clean --if-exists "$DB_NAME" | gzip > "$F.tmp"
gzip -t "$F.tmp"   # dump rusak/terpotong -> gagal di sini, file lama tetap aman
mv "$F.tmp" "$F"
find "$DIR" -name 'communityleague-*.sql.gz' -mtime +14 -delete
echo "$(date '+%F %T') OK $(du -h "$F" | cut -f1) $F"
