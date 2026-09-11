#!/usr/bin/env bash
set -euo pipefail

# Run after git pull in the existing httpdocs checkout. Does not edit Plesk or Git.
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
REPO_ROOT=$(cd -- "$SCRIPT_DIR/../.." && pwd -P)
SUBSCRIPTION=$(dirname -- "$REPO_ROOT")
ORIGIN=${1:-https://mannavomhimmel.de}
PHP_BIN=${PLESK_PHP_BIN:-/opt/plesk/php/8.4/bin/php}
PRIVATE_DIR="$SUBSCRIPTION/private"
CONFIG_FILE="$PRIVATE_DIR/manna-intake-config.php"

if [[ ! -x "$PHP_BIN" ]]; then
  printf 'PHP not found: %s\nSet PLESK_PHP_BIN to your domain PHP binary and run again.\n' "$PHP_BIN" >&2
  exit 1
fi
if [[ $(basename -- "$REPO_ROOT") != httpdocs ]]; then
  printf 'This helper expects the Git checkout in the existing Plesk httpdocs directory.\n' >&2
  exit 1
fi

if [[ $(id -u) == 0 ]]; then
  SITE_UID=$(stat -c '%u' "$REPO_ROOT")
  SITE_USER=$(stat -c '%U' "$REPO_ROOT")
  if [[ "$SITE_UID" == 0 ]]; then
    printf 'httpdocs belongs to root. Set its correct Plesk subscription owner before running this helper.\n' >&2
    exit 1
  fi
  # Preserve an earlier local configuration, including its archive path and limits.
  # Copy only this config; never move or change permissions of existing originals.
  if [[ ! -f "$CONFIG_FILE" && -f "$REPO_ROOT/intake/config.local.php" ]]; then
    if [[ ! -d "$PRIVATE_DIR" ]]; then
      install -d -m 700 -o "$SITE_USER" "$PRIVATE_DIR"
    fi
    install -m 600 -o "$SITE_USER" "$REPO_ROOT/intake/config.local.php" "$CONFIG_FILE"
  fi
  exec runuser -u "$SITE_USER" -- env PLESK_PHP_BIN="$PHP_BIN" bash "$SCRIPT_DIR/deploy-plesk.sh" "$ORIGIN"
fi

umask 077
mkdir -p -- "$PRIVATE_DIR"
if [[ ! -f "$CONFIG_FILE" && -f "$REPO_ROOT/intake/config.local.php" ]]; then
  cp -- "$REPO_ROOT/intake/config.local.php" "$CONFIG_FILE"
fi
export INTAKE_CONFIG="$CONFIG_FILE"
cd -- "$REPO_ROOT"
if [[ -f "$CONFIG_FILE" ]]; then
  "$PHP_BIN" "$SCRIPT_DIR/setup.php" --origin="$ORIGIN" --base-path=/upload/ --update-origin
else
  "$PHP_BIN" "$SCRIPT_DIR/setup.php" --origin="$ORIGIN" --base-path=/upload/ \
    --storage="$PRIVATE_DIR/manna-intake" --max-file-gb=20 --quota-gb=500
fi
"$PHP_BIN" "$SCRIPT_DIR/console.php" status
