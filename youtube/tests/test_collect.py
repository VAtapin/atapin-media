import copy
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import collect
import posts


class FakeYouTube:
    def __init__(self):
        self.info = {'id': 'abcdefghijk', 'channel_id': collect.CHANNEL_ID, 'title': 'Titel <script>',
                     'description': 'Original\nText', 'availability': 'public', 'comment_count': 2}
        self.calls = []
        self.fail_comments = False

    def space(self):
        pass

    def extract(self, url, **kwargs):
        self.calls.append('metadata')
        return copy.deepcopy(self.info), copy.deepcopy(self.info), []

    def comments(self, info):
        self.calls.append('comments')
        if self.fail_comments:
            raise RuntimeError('Temporary comment error')
        return [{'id': 'a', 'parent': 'root', 'text': 'Comment'}, {'id': 'b', 'parent': 'a', 'text': 'Reply'}], []

    def download(self, info, folder, assets=False):
        self.calls.append(assets if assets else 'video')
        if assets == 'thumbnail':
            (folder / 'thumbnail.webp').write_bytes(b'cover')
        elif assets == 'subtitles':
            (folder / 'subtitles').mkdir(exist_ok=True)
            (folder / 'subtitles/de.vtt').write_text('WEBVTT\n\n00:00.000 --> 00:01.000\nText', encoding='utf-8')
        else:
            (folder / 'video.webm').write_bytes(b'complete-media-fixture')
        return []


class ArchiveTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.backend = FakeYouTube()
        self.entry = {'id': 'abcdefghijk', 'title': 'Titel', 'sources': ['videos', 'shorts']}
        self.probe = patch('collect.verify_media', return_value='fixture-sha256')
        self.probe.start()

    def tearDown(self):
        self.probe.stop()
        self.temp.cleanup()

    def test_roundtrip_resume_and_missing_sidecar(self):
        self.assertEqual(collect.collect_video(self.backend, self.root, self.entry), 'complete')
        folder = self.root / 'items/abcdefghijk'
        self.assertEqual((folder / 'description.txt').read_text(), 'Original\nText')
        comments = collect.read(folder / 'comments.json')
        self.assertEqual(comments['comments'][1]['parent'], 'a')
        calls = list(self.backend.calls)
        self.assertEqual(collect.collect_video(self.backend, self.root, self.entry), 'complete')
        self.assertEqual(calls, self.backend.calls, 'complete item makes no network calls')
        (folder / 'comments.json').unlink()
        self.assertEqual(collect.collect_video(self.backend, self.root, self.entry), 'complete')
        self.assertEqual(self.backend.calls.count('video'), 1, 'lost comments do not redownload video')
        self.assertEqual(self.backend.calls.count('comments'), 2)

    def test_comment_failure_still_saves_video_then_retries(self):
        self.backend.fail_comments = True
        self.assertEqual(collect.collect_video(self.backend, self.root, self.entry), 'partial')
        self.assertTrue((self.root / 'items/abcdefghijk/video.webm').is_file())
        self.backend.fail_comments = False
        self.assertEqual(collect.collect_video(self.backend, self.root, self.entry), 'complete')
        self.assertEqual(self.backend.calls.count('video'), 1)

    def test_foreign_private_and_live_media_not_downloaded(self):
        for key, value, expected in [('channel_id', 'other', 'failed'), ('availability', 'private', 'not_public'), ('live_status', 'is_live', 'deferred')]:
            self.backend.info[key] = value
            self.assertEqual(collect.collect_video(self.backend, self.root, self.entry), expected)
            self.assertNotIn('video', self.backend.calls)
            self.backend = FakeYouTube()

    def test_disk_reserve_aborts_instead_of_false_success(self):
        self.backend.space = lambda: (_ for _ in ()).throw(collect.DiskFull('reserve'))
        with self.assertRaises(collect.DiskFull):
            collect.collect_video(self.backend, self.root, self.entry)

    def test_dedup_keeps_sources_and_order(self):
        output = {}
        collect.flatten({'entries': [{'id': 'abcdefghijk', 'title': 'A'}, {'id': '../escape'}]}, 'videos', output)
        collect.flatten({'entries': [{'id': 'abcdefghijk', 'title': 'A'}]}, 'shorts', output)
        self.assertEqual(list(output), ['abcdefghijk'])
        self.assertEqual(output['abcdefghijk']['sources'], ['videos', 'shorts'])

    def test_auto_translations_are_not_requested(self):
        native = [{'url': 'https://youtube.com/api/timedtext?lang=de'}]
        translated = [{'url': 'https://youtube.com/api/timedtext?lang=de&tlang=fr'}]
        self.assertEqual(collect.original_captions({'de': native, 'de-orig': native, 'fr': translated}), {'de-orig': native})

    def test_output_and_file_manifest_cannot_escape(self):
        with self.assertRaises(ValueError):
            collect.private_output(collect.REPO / 'youtube/archive')
        self.assertFalse(collect.phase_valid(self.root, {'state': 'complete', 'files': [{'path': '../escape', 'bytes': 1}]}))
        with self.assertRaises(ValueError):
            collect.collect_video(self.backend, self.root, {'id': '../escape'})

    def test_only_one_writer(self):
        first = collect.Lock(self.root / 'lock')
        try:
            with self.assertRaises(RuntimeError):
                collect.Lock(self.root / 'lock')
        finally:
            first.close()

    def test_post_text_links_images_and_poll_raw_preserved(self):
        raw = {'postId': 'UgkxExample', 'contentText': {'runs': [{'text': 'Hallo\n'}, {'text': 'Welt', 'navigationEndpoint': {'urlEndpoint': {'url': 'https://example.com'}}}]},
               'backstageAttachment': {'imageRenderer': {'image': {'thumbnails': [{'url': 'https://yt3.ggpht.com/small', 'width': 10, 'height': 10}, {'url': 'https://yt3.ggpht.com/large', 'width': 100, 'height': 100}]}}, 'pollRenderer': {'choices': ['A', 'B']}}}
        post = posts.normalize(raw)
        self.assertEqual(post['text'], 'Hallo\nWelt')
        self.assertEqual(post['images'], ['https://yt3.ggpht.com/large'])
        self.assertEqual(post['raw']['backstageAttachment']['pollRenderer']['choices'], ['A', 'B'])
        with self.assertRaises(ValueError):
            posts.download_image('http://127.0.0.1/internal', self.root)


class MediaTest(unittest.TestCase):
    @unittest.skipUnless(shutil.which('ffmpeg') and shutil.which('ffprobe'), 'ffmpeg not installed')
    def test_real_media_validation(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / 'video.mkv'
            subprocess.run(['ffmpeg', '-v', 'error', '-f', 'lavfi', '-i', 'color=c=blue:s=64x64:d=0.2', '-c:v', 'ffv1', str(path)], check=True)
            digest = collect.verify_media(path)
            self.assertEqual(len(digest), 64)
            path.write_bytes(b'not a video')
            with self.assertRaises(subprocess.CalledProcessError):
                collect.verify_media(path)


if __name__ == '__main__':
    unittest.main()
