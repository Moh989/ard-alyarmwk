#!/usr/bin/env bash
set -euo pipefail
ARD_PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ARD_PROJECT_DIR"
if [ ! -f "${ARD_CONFIG:-config/local.php}" ]; then
  echo 'أكمل إعداد config/local.php وفق README.md أولاً.'
  exit 1
fi
ARD_DB_HOST="$(php bin/preview-db.php host)"
ARD_DB_PORT="$(php bin/preview-db.php port)"
ARD_DB_NAME="$(php bin/preview-db.php database)"
ARD_MYSQL_DIR="$ARD_PROJECT_DIR/storage/mysql"
ARD_MYSQL_SOCKET="$ARD_PROJECT_DIR/storage/mysql.sock"

# Authenticate against the configured database before starting any MySQL process.
if ! php bin/preview-db.php check; then
  if [ "$(php bin/preview-db.php environment)" != local ] || [ "$ARD_DB_HOST" != 127.0.0.1 ]; then
    php bin/preview-db.php report
    exit 1
  fi
  if lsof -nP -iTCP:"$ARD_DB_PORT" -sTCP:LISTEN >/dev/null 2>&1; then
    printf 'المنفذ %s مشغول، لكن اتصال قاعدة الموقع لم ينجح. قد يكون الخادم خاصاً بمشروع آخر.\n' "$ARD_DB_PORT"
    php bin/preview-db.php report
    exit 1
  fi
  if [ ! -d "$ARD_MYSQL_DIR/mysql" ] || [ ! -d "$ARD_MYSQL_DIR/$ARD_DB_NAME" ]; then
    echo 'قاعدة الموقع المحلية غير موجودة في storage/mysql. استعد النسخة المعتمدة وفق README.md؛ لن تُهيأ قاعدة فارغة.'
    exit 1
  fi
  if lsof "$ARD_MYSQL_SOCKET" >/dev/null 2>&1; then
    echo 'خادم قاعدة الموقع يعمل بمقبس محلي، لكن إعداد المنفذ لا يطابق الاتصال المطلوب. راجع إعداد المنفذ قبل إعادة التشغيل.'
    exit 1
  fi
  ARD_MYSQL_COMMAND="$(command -v mysqld)"
  ARD_MYSQL_ARGS=(--no-defaults --datadir="$ARD_MYSQL_DIR" --socket="$ARD_MYSQL_SOCKET" --port="$ARD_DB_PORT" --bind-address=127.0.0.1 --mysqlx=OFF --log-error="$ARD_PROJECT_DIR/storage/logs/mysql.log" --pid-file="$ARD_PROJECT_DIR/storage/mysql.pid")
  if command -v launchctl >/dev/null 2>&1; then
    launchctl remove com.ard-alyarmwk.local-mysql >/dev/null 2>&1 || true
    # Removing a launchd job is asynchronous. Wait before reusing its label.
    for i in {1..50}; do
      if ! launchctl list com.ard-alyarmwk.local-mysql >/dev/null 2>&1; then break; fi
      sleep 0.1
    done
    launchctl submit -l com.ard-alyarmwk.local-mysql -o /dev/null -e "$ARD_PROJECT_DIR/storage/logs/mysql.log" -- "$ARD_MYSQL_COMMAND" "${ARD_MYSQL_ARGS[@]}"
  else
    nohup "$ARD_MYSQL_COMMAND" "${ARD_MYSQL_ARGS[@]}" >/dev/null 2>&1 &
  fi
  for i in {1..60}; do
    if php bin/preview-db.php check; then break; fi
    sleep 0.5
  done
  php bin/preview-db.php report
fi
if curl -fsS -o /dev/null http://127.0.0.1:8088/ar/ 2>/dev/null; then
  echo 'المعاينة تعمل بالفعل: http://127.0.0.1:8088/ar/'
  exit 0
fi
if lsof -nP -iTCP:8088 -sTCP:LISTEN >/dev/null 2>&1; then
  echo 'منفذ المعاينة 8088 مستخدم، لكن الموقع لا يستجيب بصورة صحيحة. راجع الخادم الحالي وسجله.'
  exit 1
fi
printf '%s\n' 'المعاينة: http://127.0.0.1:8088/ar/' 'لوحة الإدارة: http://127.0.0.1:8088/admin'
exec php -d upload_max_filesize=40M -d post_max_size=42M -S 127.0.0.1:8088 -t public public/router.php
