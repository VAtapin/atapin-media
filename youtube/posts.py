"""Capture public community posts while yt-dlp handles tab pagination.

The upstream tab extractor otherwise returns only video links found in posts.
This small adapter is tested against the pinned yt-dlp version.
"""
from urllib.parse import urlparse
import hashlib
import json
from pathlib import Path
import urllib.request


def text(value):
    if not isinstance(value, dict):
        return ''
    return value.get('simpleText') or ''.join(r.get('text', '') for r in value.get('runs', []))


def walk(value):
    if isinstance(value, dict):
        yield value
        for child in value.values():
            yield from walk(child)
    elif isinstance(value, list):
        for child in value:
            yield from walk(child)


def normalize(post):
    links = sorted({d['url'] for d in walk(post.get('contentText', {})) if isinstance(d.get('url'), str)})
    images = []
    for node in walk(post.get('backstageAttachment', {})):
        if 'thumbnails' in node and isinstance(node['thumbnails'], list):
            candidates = [t for t in node['thumbnails'] if isinstance(t, dict) and t.get('url')]
            if candidates:
                best = max(candidates, key=lambda t: t.get('width', 0) * t.get('height', 0))
                if best['url'] not in images:
                    images.append(best['url'])
    return {'id': post['postId'], 'url': 'https://www.youtube.com/post/' + post['postId'],
            'text': text(post.get('contentText')), 'published_label': text(post.get('publishedTimeText')),
            'likes_label': text(post.get('voteCount')), 'links': links, 'images': images, 'raw': post}


def coverage(items, warnings):
    warnings = list(warnings)
    possible_limit = len(items) >= 200
    if possible_limit:
        warnings.append('YouTube returned at least 200 posts. Its public feed may omit older posts; this is not a complete historical archive.')
    return {'state': 'partial' if warnings else 'complete', 'count': len(items), 'warnings': warnings,
            'possible_history_limit': possible_limit,
            'last_returned_date_label': items[-1]['published_label'] if items else None,
            'comments': 'Only video comments are collected; post discussion threads are not included.'}


def extractor_class():
    from yt_dlp.extractor.youtube import YoutubeTabIE

    class ArchivePostsIE(YoutubeTabIE):
        def __init__(self, downloader):
            super().__init__(downloader)
            self.posts = {}
            self.pagination_warnings = []

        def write_debug(self, message, *args, **kwargs):
            if 'feed looping' in message.lower():
                self.pagination_warnings.append(message)
            return super().write_debug(message, *args, **kwargs)

        def capture(self, value):
            for node in walk(value):
                post = node.get('backstagePostRenderer') or node.get('postRenderer')
                if isinstance(post, dict) and post.get('postId'):
                    self.posts[post['postId']] = normalize(post)

        def _extract_entries(self, renderer, continuation_list):
            self.capture(renderer)
            yield from super()._extract_entries(renderer, continuation_list)

        def _post_thread_entries(self, renderer):
            self.capture(renderer)
            # Do not follow external/other-channel video links in posts.
            return iter(())

    return ArchivePostsIE


def download_image(url, folder):
    parsed = urlparse(url)
    host = parsed.hostname or ''
    if parsed.scheme != 'https' or not any(host == h or host.endswith('.' + h) for h in ('ytimg.com', 'ggpht.com', 'googleusercontent.com')):
        raise ValueError('Unexpected image host: ' + host)
    name = hashlib.sha256(url.encode()).hexdigest()[:24] + '.image'
    path = Path(folder) / name
    if path.is_file() and path.stat().st_size:
        return name
    request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(request, timeout=30) as response:
        data = response.read(25 * 1024 ** 2 + 1)
        if len(data) > 25 * 1024 ** 2 or not response.headers.get('Content-Type', '').startswith('image/'):
            raise ValueError('Invalid or oversized post image.')
    temporary = path.with_suffix('.part')
    temporary.write_bytes(data)
    temporary.replace(path)
    return name


def collect_posts(backend, root, channel, channel_id, save):
    backend.log.warnings.clear()
    with backend.YDL({**backend.options, 'extract_flat': True}) as ydl:
        extractor = extractor_class()(ydl)
        ydl.add_info_extractor(extractor)
        info = ydl.extract_info(channel + '/posts', download=False, ie_key=extractor.ie_key())
        raw = ydl.sanitize_info(info)
        if raw.get('channel_id') != channel_id:
            raise RuntimeError('Posts channel identity did not match.')
        save(root / 'channel' / 'posts.json', raw)
        warnings = list(backend.log.warnings) + extractor.pagination_warnings
        # Persist every text record before downloading any image: a slow/failed image
        # must not prevent the oldest posts from being archived.
        save(root / 'posts' / 'index.json', {'count': len(extractor.posts), 'entries': [
            {'id': post['id'], 'published_label': post['published_label']} for post in extractor.posts.values()]})
        for post in extractor.posts.values():
            if not all(c.isalnum() or c in '_-' for c in post['id']):
                raise ValueError('Invalid post ID.')
            folder = root / 'posts' / post['id']
            folder.mkdir(parents=True, exist_ok=True)
            save(folder / 'post.json', post)
            (folder / 'text.txt').write_text(post['text'], encoding='utf-8')
        for index, post in enumerate(extractor.posts.values(), 1):
            folder = root / 'posts' / post['id']
            print(f"Post images {index}/{len(extractor.posts)}: {post['id']}", flush=True)
            post['image_files'] = []
            for url in post['images']:
                backend.space()
                try:
                    post['image_files'].append({'url': url, 'file': download_image(url, folder)})
                except Exception as error:
                    warnings.append(post['id'] + ': ' + str(error))
            save(folder / 'post.json', post)
        return coverage(list(extractor.posts.values()), warnings)
