#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
REPO_ROOT=$(cd -- "$SCRIPT_DIR/../.." && pwd -P)
ACTION=${1:-status}
if [[ $# -gt 0 ]]; then shift; fi
if [[ $(basename -- "$REPO_ROOT") != httpdocs ]]; then
  printf 'Run this helper from the Git checkout in your Plesk httpdocs directory.\n' >&2; exit 1
fi
case "$ACTION" in install|start|run|status|logs) ;; *) printf 'Usage: bash youtube/bin/plesk.sh install|start|run|status|logs [collector options]\n' >&2; exit 1 ;; esac
SUBSCRIPTION=$(dirname -- "$REPO_ROOT")
PRIVATE_DIR="$SUBSCRIPTION/private"
ARCHIVE="$PRIVATE_DIR/manna-youtube"
VENV="$PRIVATE_DIR/manna-youtube-runtime"
NODE_BIN=${PLESK_NODE_BIN:-/opt/plesk/node/22/bin/node}
if [[ ! -x "$NODE_BIN" ]]; then NODE_BIN=$(command -v node || true); fi
if [[ "$ACTION" != status && "$ACTION" != logs ]]; then
  if [[ -z "$NODE_BIN" ]] || ! "$NODE_BIN" -e 'process.exit(Number(process.versions.node.split(".")[0]) >= 22 ? 0 : 1)'; then
    printf 'Node 22+ is required. Set PLESK_NODE_BIN to your existing Plesk Node binary.\n' >&2; exit 1
  fi
fi
if [[ $(id -u) == 0 ]]; then
  SITE_USER=$(stat -c '%U' "$REPO_ROOT")
  if [[ $(stat -c '%u' "$REPO_ROOT") == 0 ]]; then printf 'httpdocs must belong to the Plesk subscription user.\n' >&2; exit 1; fi
  if [[ "$ACTION" == install ]]; then
    if ! command -v ffmpeg >/dev/null || ! command -v ffprobe >/dev/null || ! python3 -c 'import venv, ensurepip' 2>/dev/null; then
      apt-get update
      apt-get install -y python3-venv ffmpeg
    fi
  fi
  exec runuser -u "$SITE_USER" -- env PLESK_NODE_BIN="$NODE_BIN" bash "$SCRIPT_DIR/plesk.sh" "$ACTION" "$@"
fi
umask 077
mkdir -p -- "$PRIVATE_DIR" "$ARCHIVE"
cd -- "$REPO_ROOT"
if [[ "$ACTION" == install ]]; then
  command -v ffmpeg >/dev/null && command -v ffprobe >/dev/null || { printf 'Install ffmpeg first, or run install as root.\n' >&2; exit 1; }
  python3 -c 'import sys; assert sys.version_info >= (3, 10), "Python 3.10+ required"'
  if [[ ! -x "$VENV/bin/python" ]]; then python3 -m venv "$VENV"; fi
  "$VENV/bin/python" -m pip install -r "$REPO_ROOT/youtube/requirements.txt"
  printf 'Collector ready. Archive: %s\nStart: bash youtube/bin/plesk.sh start\n' "$ARCHIVE"
  exit 0
fi
if [[ "$ACTION" == logs ]]; then touch "$ARCHIVE/collection.log"; exec tail -n 60 -f "$ARCHIVE/collection.log"; fi
if [[ ! -x "$VENV/bin/python" ]]; then printf 'Run: bash youtube/bin/plesk.sh install\n' >&2; exit 1; fi
if [[ "$ACTION" == status ]]; then exec "$VENV/bin/python" youtube/collect.py --output "$ARCHIVE" --status; fi
if [[ "$ACTION" == run ]]; then exec "$VENV/bin/python" -u youtube/collect.py --output "$ARCHIVE" --node "$NODE_BIN" "$@"; fi
# nohup keeps the one-shot import running when the SSH connection closes.
# The collector holds an OS lock; duplicate starts cannot write concurrently.
nohup "$VENV/bin/python" -u "$REPO_ROOT/youtube/collect.py" --output "$ARCHIVE" --node "$NODE_BIN" "$@" >> "$ARCHIVE/collection.log" 2>&1 < /dev/null &
printf 'Started process %s.\nStatus: bash youtube/bin/plesk.sh status\nLog: bash youtube/bin/plesk.sh logs\n' "$!"
