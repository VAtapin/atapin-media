#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
APP=$(cd -- "$SCRIPT_DIR/.." && pwd -P)
ROOT=$(dirname -- "$APP")
PRIVATE="$(dirname -- "$ROOT")/private/atapin-platform"
PHP_BIN=${PLESK_PHP_BIN:-/opt/plesk/php/8.4/bin/php}
ACTION=${1:-check}
case "$ACTION" in install|update|owner|check|worker|schedule|services) ;; *) printf 'Usage: bash platform/bin/plesk.sh install|update|owner|check|worker|schedule|services\n' >&2; exit 1 ;; esac
[[ $(basename -- "$ROOT") == httpdocs ]] || { printf 'Run from the existing httpdocs checkout.\n' >&2; exit 1; }
[[ -x "$PHP_BIN" ]] || { printf 'PHP 8.4 not found: %s\n' "$PHP_BIN" >&2; exit 1; }
if [[ $(id -u) == 0 ]]; then
  SITE_USER=$(stat -c '%U' "$ROOT")
  [[ $(stat -c '%u' "$ROOT") != 0 ]] || { printf 'httpdocs must belong to the Plesk subscription user.\n' >&2; exit 1; }
  if [[ "$ACTION" == services ]]; then
    [[ -f "$PRIVATE/.env" && -f "$APP/vendor/autoload.php" ]] || { printf 'Run install first.\n' >&2; exit 1; }
    # Fixed systemd names use numeric UID; reject paths with unit-file specifiers or escapes.
    [[ "$ROOT" =~ ^/[a-zA-Z0-9_./-]+$ && "$PHP_BIN" =~ ^/[a-zA-Z0-9_./-]+$ && "$SITE_USER" =~ ^[a-zA-Z0-9_.-]+$ ]] || exit 1
    UNIT="atapin-media-$(stat -c '%u' "$ROOT")"
    cat > "/etc/systemd/system/$UNIT.service" <<EOF
[Unit]
Description=Atapin Media queue worker
After=network.target
[Service]
Type=simple
User=$SITE_USER
WorkingDirectory=$APP
Environment=PLESK_PHP_BIN=$PHP_BIN
ExecStart=/bin/bash $SCRIPT_DIR/plesk.sh worker
Restart=always
RestartSec=5
TimeoutStopSec=3700
UMask=0077
[Install]
WantedBy=multi-user.target
EOF
    cat > "/etc/systemd/system/$UNIT-schedule.service" <<EOF
[Unit]
Description=Atapin Media scheduler
[Service]
Type=oneshot
User=$SITE_USER
WorkingDirectory=$APP
Environment=PLESK_PHP_BIN=$PHP_BIN
ExecStart=/bin/bash $SCRIPT_DIR/plesk.sh schedule
UMask=0077
EOF
    cat > "/etc/systemd/system/$UNIT-schedule.timer" <<EOF
[Unit]
Description=Atapin Media every-minute schedule
[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true
[Install]
WantedBy=timers.target
EOF
    systemctl daemon-reload
    systemctl enable --now "$UNIT.service" "$UNIT-schedule.timer"
    systemctl is-active "$UNIT.service" "$UNIT-schedule.timer"
    exit 0
  fi
  if [[ "$ACTION" == install || "$ACTION" == update ]]; then
    # Only this application's checkout/runtime; existing intake and YouTube archives are untouched.
    chown -R --no-dereference "$SITE_USER" "$APP"
    install -d -m 700 -o "$SITE_USER" "$PRIVATE"
  fi
  exec runuser -u "$SITE_USER" -- env PLESK_PHP_BIN="$PHP_BIN" bash "$SCRIPT_DIR/plesk.sh" "$ACTION"
fi
[[ "$ACTION" != services ]] || { printf 'Run services as root to install systemd units.\n' >&2; exit 1; }
umask 077
cd -- "$APP"
if [[ "$ACTION" == install || "$ACTION" == update ]]; then
  "$PHP_BIN" -r 'if (PHP_VERSION_ID < 80400) { fwrite(STDERR,"PHP 8.4+ required\n"); exit(1); } foreach (["pdo_mysql","mbstring","openssl","fileinfo","tokenizer","xml","ctype","curl","dom","session"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR,"Missing PHP extension: $e\n"); exit(1); }}'
  [[ -f composer.lock ]] || { printf 'The release composer.lock is missing. Run git pull first.\n' >&2; exit 1; }
  mkdir -p "$PRIVATE/storage/app/private" "$PRIVATE/storage/framework/cache/data" "$PRIVATE/storage/framework/sessions" "$PRIVATE/storage/framework/views" "$PRIVATE/storage/logs" bootstrap/cache
  COMPOSER=/usr/lib/plesk-9.0/composer.phar
  if [[ ! -f "$COMPOSER" ]]; then
    COMPOSER="$PRIVATE/composer.phar"
    if [[ ! -f "$COMPOSER" ]]; then
      "$PHP_BIN" -r 'copy("https://getcomposer.org/installer", $argv[1]); copy("https://composer.github.io/installer.sig", $argv[2]);' "$PRIVATE/composer-setup.php" "$PRIVATE/composer-setup.sig"
      "$PHP_BIN" -r 'if (!hash_equals(trim(file_get_contents($argv[2])), hash_file("sha384", $argv[1]))) { fwrite(STDERR,"Composer installer checksum failed\n"); exit(1); }' "$PRIVATE/composer-setup.php" "$PRIVATE/composer-setup.sig"
      "$PHP_BIN" "$PRIVATE/composer-setup.php" --install-dir="$PRIVATE" --filename=composer.phar
    fi
  fi
  "$PHP_BIN" "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
  if [[ ! -f "$PRIVATE/.env" ]]; then
    [[ "$ACTION" == install && -t 0 ]] || { printf 'Run install in an interactive SSH terminal to configure the database.\n' >&2; exit 1; }
    "$PHP_BIN" bin/configure.php
  fi
  "$PHP_BIN" artisan config:clear
  "$PHP_BIN" artisan migrate --force
  "$PHP_BIN" artisan platform:access
  "$PHP_BIN" artisan config:cache
  "$PHP_BIN" artisan view:cache
  "$PHP_BIN" artisan queue:restart
  # Assets are public; PHP, configuration and runtime directories remain private.
  find public -type d -exec chmod 755 {} +
  find public -type f -exec chmod 644 {} +
  printf '\nReady. Plesk document root: httpdocs/platform/public\nCreate owner: bash platform/bin/plesk.sh owner\nCheck: bash platform/bin/plesk.sh check\n'
  exit 0
fi
[[ -f vendor/autoload.php && -f "$PRIVATE/.env" ]] || { printf 'Run install first.\n' >&2; exit 1; }
case "$ACTION" in
  owner) exec "$PHP_BIN" artisan platform:owner ;;
  check) "$PHP_BIN" artisan migrate:status; "$PHP_BIN" artisan platform:check ;;
  worker) exec "$PHP_BIN" artisan queue:work --sleep=3 --tries=3 --timeout=3600 ;;
  schedule) exec "$PHP_BIN" artisan schedule:run ;;
esac
