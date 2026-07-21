#!/usr/bin/env bash

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
PHP_BIN="/usr/local/php84/bin/php"
LOG_FILE="$APP_DIR/storage/logs/cron-schedule.log"
LOCK_FILE="$APP_DIR/storage/app/placowka-schedule.lock"

cd "$APP_DIR" || exit 1

{
  echo "===== $(date '+%Y-%m-%d %H:%M:%S') schedule:run start ====="

  if command -v flock >/dev/null 2>&1; then
    flock -n "$LOCK_FILE" "$PHP_BIN" artisan schedule:run
  else
    "$PHP_BIN" artisan schedule:run
  fi

  echo "===== $(date '+%Y-%m-%d %H:%M:%S') schedule:run end ====="
} >> "$LOG_FILE" 2>&1
