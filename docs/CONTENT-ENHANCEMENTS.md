# Covers, short descriptions and podcasts

Original video/audio files retain the existing canonical `platform/public/media/<sha256>.<extension>` contract. Playback is direct static delivery, not PHP or a video-optimization pipeline. Takeout and original descriptions are not modified.

## Desktop workflow

- Einstellungen → Medien & Website: project author name/photo, shared AI-cover style and image model, and separate German/English handwritten hero sayings for homepage and overview sections. Photos are canonical public images; an initial/avatar placeholder is shown until a photo is provided.
- Videos/Beiträge/Media Library: open a complete content record to edit its separate Kurzbeschreibung, request an AI cover, or retry free cover extraction. Existing covers are never replaced by automatic frame extraction. AI cover generation is explicit and billable; the request contains project name, filtered title/description and the shared style prompt. It does not upload the video.
- Missing short descriptions are queued automatically when the content list is opened; selected records and all missing descriptions can still be requested from the content toolbar. One background job/provider request contains up to 100 records, additionally limited by a 100 KB evidence budget. Every ID must be returned exactly once with 8–10 German words, or an explicit insufficient-information result. Original descriptions remain intact. Manual short descriptions and changed records are not overwritten. Failed operations are shown in the record and can be requested again.
- Select Podcast as import destination or assign existing complete records individually/in bulk to Podcast. The native Podcast desktop window lists those records. The 4–5 minute duration is not an automatic podcast classifier: choose the destination yourself. The separate configurable 14–16 second Beiträge rule remains unchanged.

Podcast preparation extracts a separate audio file in the normal background queue. AAC and MP3 are copied without re-encoding; unsupported audio is encoded to MP3 only. No video optimization is performed. Public Podcast playback loads audio, not the original video; cover, body and comments retain their relationships.

## Public pages

Cards and video intros show the separate short description, not advertising from the full original body. “Mehr anzeigen” opens the preserved full description. Missing short descriptions stay empty until requested; no paid calls occur on page views.

Public video download links are removed; administrator downloads and non-video material attachments remain. A static public video URL is still technically downloadable by a knowledgeable viewer; this is not DRM. Reactions and comments use the existing AJAX form handler, preserve the document/player, and show feedback beside their controls. Comments enter the existing AI-moderation pipeline; the sender can see their own pending message, others see only approved messages. The comment list refreshes after submission and periodically while the page is visible.

Homepage featured media has a white rounded frame, author row/avatar, round play control and a local translucent text backdrop. Covers remain visible outside the text area.

## Future external-image imports

Explicit thumbnail/cover/avatar/banner fields are queued for download into canonical local image files. Arbitrary links in descriptions are not downloaded. Image requests validate public DNS/IP destinations, pin the resolved address, revalidate redirects, verify TLS, limit download bytes and check actual image type/dimensions. Private destinations and non-images are rejected. The individual import result reports an image error and supports retry without repeating the whole import. Previously completed imports and their old warnings are not rewritten automatically.

## Queue and installation

The current Plesk scheduled queue remains responsible for all background work. Both `ffmpeg` and `ffprobe` must be executable by the domain's PHP/queue user. On the current server their `/usr/bin` installation has been confirmed; no `.env` changes are required. Optional binary overrides are `MEDIA_FFMPEG_BINARY` and `MEDIA_FFPROBE_BINARY`.

For already imported records, enqueue missing free covers/audio once after receiving this code:

```bash
cd /var/www/vhosts/mannavomhimmel.de/httpdocs && \
/opt/plesk/php/8.4/bin/php platform/artisan media:prepare-missing
```

This command does not generate AI covers/text, copy Takeout again, publish content or alter original files. Repeated calls skip already tracked operations; failed derivatives can be retried from their content record. No new database migration, Composer dependency, Node build, symlink or worker service is needed.

## Lightweight checks

Feature coverage: `ContentEnhancementsTest`, `MediaDerivativesTest`, `RemoteImagesTest`, plus existing public/import/assignment tests. Provider HTTP and ffmpeg processes are faked; no billable API calls or full archive import are run as tests.

Browser coverage: `tests/content-enhancements-browser.mjs` with `tests/content-enhancements-fixture.php` on an isolated testing SQLite database containing `content-enhancements-` in its name. It checks desktop/mobile hero layout, native settings/Podcast fields, expandable text, AJAX reactions/comments and unchanged video DOM. Its video bytes are deliberately not a playback fixture; it does not claim real decoder/ffmpeg verification.
