#!/usr/bin/env python3
"""One-shot, resumable archive of Manna Vom Himmel's public YouTube uploads."""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import signal
import subprocess
import sys
import time
from urllib.parse import urlparse, parse_qs
from datetime import datetime, timezone

CHANNEL = 'https://www.youtube.com/@MannaVomHimmel'
CHANNEL_ID = 'UC32nXxuzjY7ExrDgzSAQNXQ'
VIDEO_ID = re.compile(r'^[A-Za-z0-9_-]{11}$')
MEDIA_NAME = re.compile(r'^video\.(mkv|mp4|webm|mov|m4v|flv|3gp)$')
REPO = Path(__file__).resolve().parent.parent
PHASES = ('metadata', 'comments', 'thumbnail', 'subtitles', 'video')


def original_captions(captions):
    result = {}
    for language, tracks in (captions or {}).items():
        if not language.endswith('-orig') and language + '-orig' in captions:
            continue
        native = [track for track in tracks if 'tlang' not in parse_qs(urlparse(track.get('url', '')).query)]
        if native:
            result[language] = native
    return result


def now():
    return datetime.now(timezone.utc).isoformat()


def save(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_name(path.name + '.tmp')
    with temporary.open('w', encoding='utf-8', newline='\n') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
        stream.write('\n')
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(temporary, path)


def read(path, default=None):
    return json.loads(Path(path).read_text(encoding='utf-8')) if Path(path).is_file() else default


def private_output(value):
    path = Path(value).expanduser().resolve()
    if path == REPO or REPO in path.parents or any(p.name.lower() in ('httpdocs', 'htdocs', 'public_html') for p in [path, *path.parents]):
        raise ValueError('Archive must be outside the Git checkout and web document root.')
    path.mkdir(parents=True, exist_ok=True, mode=0o700)
    return path


class Lock:
    def __init__(self, path):
        self.file = open(path, 'a+b')
        if self.file.seek(0, 2) == 0:
            self.file.write(b'0')
            self.file.flush()
        self.file.seek(0)
        try:
            if os.name == 'nt':
                import msvcrt
                msvcrt.locking(self.file.fileno(), msvcrt.LK_NBLCK, 1)
            else:
                import fcntl
                fcntl.flock(self.file, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except OSError:
            self.file.close()
            raise RuntimeError('Another collection is already running for this archive.') from None

    def close(self):
        self.file.close()


class Log:
    def __init__(self):
        self.warnings = []

    def debug(self, message):
        if not message.startswith(('[debug]', '[download] Downloading item')):
            print(message, flush=True)

    def warning(self, message):
        self.warnings.append(message)
        print('WARNING: ' + message, flush=True)

    def error(self, message):
        print('ERROR: ' + message, flush=True)


class DiskFull(RuntimeError):
    pass


class YouTube:
    def __init__(self, root, node, reserve):
        from yt_dlp import YoutubeDL
        self.YDL = YoutubeDL
        self.root, self.reserve = root, reserve
        self.log = Log()
        self.options = {
            'logger': self.log, 'quiet': True, 'noprogress': True,
            'socket_timeout': 30, 'retries': 3, 'fragment_retries': 3,
            'extractor_retries': 3, 'sleep_interval_requests': 1,
            'sleep_interval': 5, 'max_sleep_interval': 10,
            'concurrent_fragment_downloads': 1, 'skip_unavailable_fragments': False,
            'js_runtimes': {'node': {'path': node}},
            'cachedir': str(root / '.cache'), 'noplaylist': True,
            'overwrites': False, 'continuedl': True,
            'extractor_args': {'youtube': {'comment_sort': ['new'], 'skip': ['translated_subs']}},
        }

    def space(self, _=None):
        if shutil.disk_usage(self.root).free < self.reserve:
            raise DiskFull('Free disk space is below the configured reserve; collection stopped.')

    def extract(self, url, **options):
        self.space()
        self.log.warnings.clear()
        with self.YDL({**self.options, **options}) as ydl:
            info = ydl.extract_info(url, download=False)
            if not info:
                raise RuntimeError('YouTube returned no data.')
            return info, ydl.sanitize_info(info), list(self.log.warnings)

    def comments(self, info):
        # Separate extraction keeps a comment failure from losing video metadata.
        comments_info, _, notes = self.extract('https://www.youtube.com/watch?v=' + info['id'],
                                               getcomments=True, ignore_no_formats_error=True)
        return list(comments_info.get('comments') or []), notes

    def download(self, info, folder, assets=False):
        self.space()
        self.log.warnings.clear()
        options = {
            **self.options, 'progress_hooks': [self.space],
            'format': 'bestvideo+bestaudio/best', 'merge_output_format': 'mkv',
            'outtmpl': {'default': str(folder / 'video.%(ext)s'),
                        'thumbnail': str(folder / 'thumbnail.%(ext)s'),
                        'subtitle': str(folder / 'subtitles' / '%(id)s.%(ext)s')},
            'skip_download': bool(assets), 'writethumbnail': assets == 'thumbnail',
            'writesubtitles': assets == 'subtitles', 'writeautomaticsub': assets == 'subtitles',
            'subtitleslangs': ['all'], 'subtitlesformat': 'vtt/best',
            'ignore_no_formats_error': assets,
        }
        clean = copy.deepcopy({k: v for k, v in info.items() if not k.startswith('__')})
        clean['automatic_captions'] = original_captions(clean.get('automatic_captions'))
        with self.YDL(options) as ydl:
            ydl.process_ie_result(clean, download=True)
        return list(self.log.warnings)


def flatten(info, source, output):
    """Only inventory entries become download candidates; playlist links are not followed."""
    for entry in info.get('entries') or []:
        if not entry:
            continue
        ident = entry.get('id', '')
        if not VIDEO_ID.fullmatch(ident):
            continue
        row = output.setdefault(ident, {'id': ident, 'title': entry.get('title'), 'sources': []})
        if source not in row['sources']:
            row['sources'].append(source)


def inventory(backend, root):
    collected, reports = {}, {}
    for tab in ('videos', 'shorts', 'streams'):
        try:
            _, raw, warnings = backend.extract(CHANNEL + '/' + tab, extract_flat=True)
            if raw.get('channel_id') != CHANNEL_ID:
                raise RuntimeError('Channel identity did not match; refusing unrelated content.')
            save(root / 'channel' / (tab + '.json'), raw)
            flatten(raw, tab, collected)
            reports[tab] = {'state': 'partial' if warnings else 'complete', 'warnings': warnings}
        except DiskFull:
            raise
        except Exception as error:
            message = str(error)
            absent = 'does not have a' in message.lower() and 'tab' in message.lower()
            reports[tab] = {'state': 'absent' if absent else 'failed', 'error': message}
            print(tab + ': ' + message, flush=True)
    # Public playlist structure is metadata only: playlists can contain other creators' videos.
    try:
        _, raw, warnings = backend.extract(CHANNEL + '/playlists', extract_flat=True)
        if raw.get('channel_id') != CHANNEL_ID:
            raise RuntimeError('Playlist channel identity did not match.')
        save(root / 'channel' / 'playlists.json', raw)
        for item in raw.get('entries') or []:
            ident = (item or {}).get('id', '')
            if not re.fullmatch(r'[A-Za-z0-9_-]{10,100}', ident):
                continue
            try:
                _, playlist, notes = backend.extract('https://www.youtube.com/playlist?list=' + ident, extract_flat=True)
                playlist['ordered_items'] = [{'position': index, 'id': (video or {}).get('id'),
                                             'title': (video or {}).get('title'), 'availability': (video or {}).get('availability')}
                                            for index, video in enumerate(playlist.get('entries') or [], 1)]
                save(root / 'playlists' / (ident + '.json'), playlist)
                from posts import download_image
                covers = playlist.get('thumbnails') or []
                if covers:
                    covers = sorted(covers, key=lambda t: (t.get('width') or 0) * (t.get('height') or 0), reverse=True)
                    try:
                        cover_file = download_image(covers[0]['url'], root / 'playlists')
                        playlist['cover_file'] = cover_file
                        save(root / 'playlists' / (ident + '.json'), playlist)
                    except Exception as error:
                        notes.append(str(error))
                warnings.extend(notes)
            except DiskFull:
                raise
            except Exception as error:
                warnings.append(str(error))
        reports['playlists'] = {'state': 'partial' if warnings else 'complete', 'warnings': warnings}
    except DiskFull:
        raise
    except Exception as error:
        message = str(error)
        absent = 'does not have a' in message.lower() and 'tab' in message.lower()
        reports['playlists'] = {'state': 'absent' if absent else 'failed', 'error': message}
    result = {'channel': CHANNEL, 'channel_id': CHANNEL_ID, 'collected_at': now(),
              'tabs': reports, 'entries': list(collected.values())}
    # A failed tab must not discard candidates discovered by an earlier run.
    previous = read(root / 'inventory.json', {})
    for item in previous.get('entries', []):
        if item['id'] not in collected:
            result['entries'].append(item)
    save(root / 'inventory.json', result)
    return result


def file_list(folder, paths):
    return [{'path': str(path.relative_to(folder)).replace('\\', '/'), 'bytes': path.stat().st_size}
            for path in paths if path.is_file()]


def phase_valid(folder, phase):
    if phase.get('state') != 'complete':
        return False
    for item in phase.get('files', []):
        path = (folder / item['path']).resolve()
        if folder.resolve() not in path.parents or not path.is_file() or path.stat().st_size != item['bytes']:
            return False
    return True


def video_files(folder):
    return [path for path in folder.iterdir() if MEDIA_NAME.fullmatch(path.name) and path.is_file()]


def verify_media(path):
    result = subprocess.run(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type', '-of', 'json', str(path)],
                            capture_output=True, text=True, timeout=120, check=True)
    if not any(s.get('codec_type') == 'video' for s in json.loads(result.stdout).get('streams', [])):
        raise RuntimeError('Downloaded file has no readable video stream.')
    digest = hashlib.sha256()
    with path.open('rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            digest.update(block)
    return digest.hexdigest()


def collect_video(backend, root, entry):
    ident = entry['id']
    if not VIDEO_ID.fullmatch(ident):
        raise ValueError('Invalid video ID.')
    folder = root / 'items' / ident
    folder.mkdir(parents=True, exist_ok=True)
    state_path = folder / 'state.json'
    state = read(state_path, {'id': ident, 'phases': {}})
    phases = state['phases']
    if all(phase_valid(folder, phases.get(p, {})) for p in PHASES):
        return 'complete'
    print(f"Collecting {ident}: {entry.get('title', '')}", flush=True)
    try:
        info, raw, warnings = backend.extract('https://www.youtube.com/watch?v=' + ident,
                                              getcomments=False, ignore_no_formats_error=True)
        if info.get('channel_id') != CHANNEL_ID:
            raise RuntimeError('Video belongs to a different or unknown channel; not downloading.')
        if info.get('availability') not in (None, 'public'):
            state.update(state='not_public', reason=info.get('availability'), checked_at=now())
            save(state_path, state)
            return 'not_public'
        if info.get('is_live') or info.get('live_status') in ('is_live', 'is_upcoming', 'post_live'):
            state.update(state='deferred', reason='Live stream is not yet a downloadable replay.', checked_at=now())
            save(state_path, state)
            return 'deferred'
        save(folder / 'metadata.json', {'collected_at': now(), 'sources': entry['sources'], 'youtube': raw})
        (folder / 'title.txt').write_text(info.get('title', '') + '\n', encoding='utf-8')
        (folder / 'description.txt').write_text(info.get('description') or '', encoding='utf-8')
        (folder / 'source.url').write_text('https://www.youtube.com/watch?v=' + ident + '\n', encoding='utf-8')
        phases['metadata'] = {'state': 'complete', 'warnings': warnings, 'files': file_list(folder, [folder / n for n in ('metadata.json', 'title.txt', 'description.txt', 'source.url')])}
        save(state_path, state)
        for phase in PHASES[1:]:
            if phase_valid(folder, phases.get(phase, {})):
                continue
            backend.space()
            try:
                if phase == 'comments':
                    comments, notes = backend.comments(info)
                    save(folder / 'comments.json', {'collected_at': now(), 'reported_count': info.get('comment_count'),
                                                   'downloaded_count': len(comments), 'warnings': notes, 'comments': comments})
                    paths = [folder / 'comments.json']
                elif phase in ('thumbnail', 'subtitles'):
                    notes = backend.download(info, folder, assets=phase)
                    paths = list(folder.glob('thumbnail.*')) if phase == 'thumbnail' else list((folder / 'subtitles').glob('*'))
                    paths = [p for p in paths if not p.name.endswith(('.part', '.ytdl'))]
                    if phase == 'thumbnail' and info.get('thumbnails') and not paths:
                        raise RuntimeError('Thumbnail was advertised but not saved.')
                else:
                    # Recover a completed file after a crash before state.json was committed.
                    existing = video_files(folder)
                    recorded = phases.get('video', {}).get('files', [])
                    for path in existing:
                        expected = next((f['bytes'] for f in recorded if f['path'] == path.name), None)
                        try:
                            if expected is not None and path.stat().st_size != expected:
                                raise RuntimeError('Size changed.')
                            verify_media(path)
                        except Exception:
                            path.rename(path.with_name(path.name + '.' + str(time.time_ns()) + '.damaged'))
                    notes = [] if video_files(folder) else backend.download(info, folder)
                    paths = video_files(folder)
                    if not paths:
                        raise RuntimeError('No completed video file was produced.')
                details = {'state': 'partial' if notes else 'complete', 'warnings': notes, 'files': file_list(folder, paths), 'collected_at': now()}
                if phase == 'video':
                    for file in details['files']:
                        file['sha256'] = verify_media(folder / file['path'])
                phases[phase] = details
            except DiskFull:
                raise
            except Exception as error:
                phases[phase] = {**phases.get(phase, {}), 'state': 'failed', 'error': str(error), 'checked_at': now()}
                print(f'{ident} {phase}: {error}', flush=True)
            save(state_path, state)
        state['state'] = 'complete' if all(phase_valid(folder, phases.get(p, {})) for p in PHASES) else 'partial'
        if state['state'] == 'complete':
            state.pop('error', None)
    except DiskFull:
        raise
    except Exception as error:
        state.update(state='failed', error=str(error))
        print(f'{ident}: {error}', flush=True)
    state['checked_at'] = now()
    save(state_path, state)
    return state['state']


def main():
    # Windows console encodings must not abort an otherwise valid Unicode title.
    for stream in (sys.stdout, sys.stderr):
        if hasattr(stream, 'reconfigure'):
            stream.reconfigure(encoding='utf-8', errors='replace')
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, help='Private directory outside the checkout/web root')
    parser.add_argument('--node', default=shutil.which('node'))
    parser.add_argument('--reserve-gib', type=float, default=5)
    parser.add_argument('--limit', type=int, help='Process only this many inventory entries (smoke test)')
    parser.add_argument('--inventory-only', action='store_true')
    parser.add_argument('--use-inventory', action='store_true', help='Resume from an existing inventory without rescanning tabs/posts')
    parser.add_argument('--status', action='store_true')
    args = parser.parse_args()
    if args.reserve_gib <= 0 or (args.limit is not None and args.limit < 1):
        parser.error('Reserve and limit must be positive.')
    os.umask(0o077)
    root = private_output(args.output)
    if args.status:
        status = read(root / 'report.json', {'state': 'not_started'})
        try:
            probe = Lock(root / 'collection.lock')
            probe.close()
            status['active'] = False
        except RuntimeError:
            status['active'] = True
        if not status['active'] and status['state'] == 'running':
            status['state'] = 'interrupted'
        print(json.dumps(status, ensure_ascii=False, indent=2))
        return 0
    if not args.node:
        parser.error('Node 22+ is required; pass --node=/opt/plesk/node/22/bin/node')
    node_version = subprocess.check_output([args.node, '--version'], text=True).strip()
    if int(node_version.lstrip('v').split('.')[0]) < 22:
        parser.error('Node 22+ is required.')
    if not args.inventory_only and (not shutil.which('ffmpeg') or not shutil.which('ffprobe')):
        parser.error('ffmpeg and ffprobe are required.')
    lock = Lock(root / 'collection.lock')
    signal.signal(signal.SIGTERM, lambda *_: (_ for _ in ()).throw(KeyboardInterrupt()))
    report = {'state': 'running', 'pid': os.getpid(), 'started_at': now(), 'channel': CHANNEL, 'counts': {}}
    save(root / 'report.json', report)
    try:
        backend = YouTube(root, args.node, args.reserve_gib * 1024 ** 3)
        if args.use_inventory:
            listing = read(root / 'inventory.json')
            if not listing or listing.get('channel_id') != CHANNEL_ID:
                raise RuntimeError('No valid saved inventory; run without --use-inventory first.')
        else:
            listing = inventory(backend, root)
            from posts import collect_posts
            try:
                listing['tabs']['posts'] = collect_posts(backend, root, CHANNEL, CHANNEL_ID, save)
            except DiskFull:
                raise
            except Exception as error:
                message = str(error)
                absent = 'does not have a' in message.lower() and 'tab' in message.lower()
                listing['tabs']['posts'] = {'state': 'absent' if absent else 'failed', 'error': message}
        save(root / 'inventory.json', listing)
        report['inventory'] = {'entries': len(listing['entries']), 'tabs': listing['tabs']}
        save(root / 'report.json', report)
        if not args.inventory_only:
            for entry in listing['entries'][:args.limit]:
                report['current_id'] = entry['id']
                save(root / 'report.json', report)
                result = collect_video(backend, root, entry)
                report['counts'][result] = report['counts'].get(result, 0) + 1
                save(root / 'report.json', report)
        inventory_ok = bool(listing['entries']) and all(r['state'] in ('complete', 'absent') for r in listing['tabs'].values())
        incomplete = any(v for k, v in report['counts'].items() if k not in ('complete', 'not_public'))
        report['state'] = 'complete' if inventory_ok and not incomplete else 'partial'
        if args.inventory_only:
            report['mode'] = 'inventory_only'
        if args.limit:
            report['limit'] = args.limit
        return 0 if report['state'] == 'complete' else 2
    except KeyboardInterrupt:
        report['state'] = 'interrupted'
        return 130
    except Exception as error:
        report.update(state='failed', error=str(error))
        print(str(error), file=sys.stderr, flush=True)
        return 1
    finally:
        report['finished_at'] = now()
        save(root / 'report.json', report)
        lock.close()


if __name__ == '__main__':
    sys.exit(main())
