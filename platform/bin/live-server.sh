#!/usr/bin/env bash
set -euo pipefail
umask 077
APP=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)
ROOT=$(dirname -- "$APP")
[[ $(basename -- "$ROOT") == httpdocs ]] || { printf 'Run in the Plesk httpdocs checkout.\n' >&2; exit 1; }
PRIVATE="$(dirname -- "$ROOT")/private/atapin-live"
mkdir -p -- "$PRIVATE"
case ${1:-status} in
install)
  [[ $(uname -m) == x86_64 ]] || { printf 'This pinned release requires x86_64.\n' >&2; exit 1; }
  curl --fail --location --proto '=https' --tlsv1.2 'https://github.com/bluenviron/mediamtx/releases/download/v1.21.0/mediamtx_v1.21.0_linux_amd64.tar.gz' -o "$PRIVATE/release.tar.gz"
  printf '%s  %s\n' 'e02e34c3337a35f20ac9e5aa31524566108964e6e37dbc46cf8292169f6c792b' "$PRIVATE/release.tar.gz" | sha256sum --check --status
  tar -xzf "$PRIVATE/release.tar.gz" -C "$PRIVATE" mediamtx
  chmod 700 "$PRIVATE/mediamtx"
  "$PRIVATE/mediamtx" --validate-conf "$PRIVATE/mediamtx.yml"
  ;;
start)
  [[ -x "$PRIVATE/mediamtx" && -f "$PRIVATE/mediamtx.yml" ]] || { printf 'Generate configuration and install first.\n' >&2; exit 1; }
  "$PRIVATE/mediamtx" --validate-conf "$PRIVATE/mediamtx.yml"
  if flock -n "$PRIVATE/server.lock" true; then
    nohup flock -n -F "$PRIVATE/server.lock" "$PRIVATE/mediamtx" "$PRIVATE/mediamtx.yml" >> "$PRIVATE/server.log" 2>&1 < /dev/null &
    printf 'Start requested; see private/atapin-live/server.log.\n'
  else printf 'MediaMTX already holds the server lock.\n'; fi
  ;;
status)
  if flock -n "$PRIVATE/server.lock" true; then printf 'Stopped.\n'; else printf 'Background process holds server lock.\n'; fi
  ;;
*) printf 'Usage: bash platform/bin/live-server.sh install|start|status\n' >&2; exit 1 ;;
esac
