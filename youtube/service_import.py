#!/usr/bin/env python3
"""Import a selected public channel using the existing resumable collector."""
import argparse
import sys
from pathlib import Path
from urllib.parse import urlparse
from collect import YouTube, Lock, inventory, collect_video, private_output, save
from posts import collect_posts


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--url', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--node', required=True)
    args = parser.parse_args()
    url = urlparse(args.url)
    if url.scheme != 'https' or url.hostname not in ('youtube.com', 'www.youtube.com', 'm.youtube.com') or url.username or url.password or url.port:
        parser.error('Expected a public YouTube channel HTTPS URL.')
    channel = args.url.rstrip('/')
    root = private_output(args.output)
    lock = Lock(root / 'collection.lock')
    try:
        backend = YouTube(root, args.node, 5 * 1024 ** 3)
        _, raw, _ = backend.extract(channel, extract_flat=True)
        ident = raw.get('channel_id')
        if not ident:
            raise RuntimeError('Channel identity is unavailable.')
        listing = inventory(backend, root, channel, ident)
        partial = any(tab['state'] not in ('complete', 'absent') for tab in listing['tabs'].values())
        try:
            report = collect_posts(backend, root, channel, ident, save)
            save(root / 'posts-report.json', report)
            partial = partial or report['state'] != 'complete'
        except Exception as error:
            save(root / 'posts-report.json', {'state': 'failed', 'error': str(error)})
            partial = True
        for entry in listing['entries']:
            state = collect_video(backend, root, entry, ident)
            partial = partial or state not in ('complete', 'not_public')
        return 2 if partial else 0
    finally:
        lock.close()


if __name__ == '__main__':
    sys.exit(main())
