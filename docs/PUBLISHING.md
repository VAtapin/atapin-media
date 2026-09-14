# Publishing

Publishing is a server-side, queue-backed workflow in `platform/`. A record published on the Website is immediately made public and, when destinations are selected, creates one outbound publication per connected platform. Each destination keeps its own queue status, remote ID/URL, attempt count, timestamps, error and retry action.

## Current flow

- Videos, Shorts, Beiträge and Live events can be selected in the Publishing window.
- Website is an internal destination and becomes public in the same application transaction.
- YouTube, Facebook, Instagram and Telegram use real HTTP connectors when their encrypted connection is configured. Unavailable connector capabilities are marked as skipped or failed per destination; they do not block the Website.
- YouTube video uploads read the canonical local media file through `MediaOriginalLocator`, so SHA-256 filenames and the public `platform/public/media` catalog remain the source of the file. Imported YouTube files are downloaded to a private temporary directory, then registered in canonical storage and shown as review-only content.
- YouTube uploads always request `public`, without `publishAt` or a confirmation step. A private response is reported as a failure, not a successful public publication. Google restricts uploads from unverified API projects to private viewing; the installation needs the required [YouTube API audit](https://developers.google.com/youtube/v3/docs/videos/insert).
- The YouTube video/broadcast ID is saved before thumbnail upload or broadcast binding. A retry resumes those steps instead of creating another video/broadcast. The queue claims work atomically and refuses manual retries of processing or successful publications.
- Instagram uses the video URL when both video and cover exist, preserves its media container, and checks processing status before publishing. `IN_PROGRESS` schedules another check instead of holding the worker asleep. The returned permalink is used, not a fabricated URL.
- Reverse YouTube synchronization is scheduled every five minutes. A synchronized item never becomes public automatically: it is created as `review` with `public_published=false` until an editor publishes it.
- Own outbound YouTube IDs are linked to their original Website records, without another download or review duplicate. External imports retain their existing YouTube publication mapping, so enabling them on the Website does not upload them back to YouTube. Active external Live streams wait for a recording before media download. Existing historical duplicates are not deleted automatically.

## YouTube Live

Live Studio records use `kind=video` with `public_section=live`; Publishing recognizes that existing contract. The first successful Live publication creates one reusable YouTube `liveStream` and stores its stream metadata encrypted with the YouTube connection. Every later event creates only a new public broadcast and binds it to that same reusable stream.

The MediaMTX `runOnAvailable` foreground hook retains `ffmpeg` while it relays the existing authorized local HLS (`http://127.0.0.1:8888/<path>/index.m3u8`) to YouTube. MediaMTX interrupts the hook when the publisher leaves and restarts a crashed hook; `ffmpeg` failures reconnect inside it. One OS file lock prevents competing relays and is released even on process death. There is no detached PID registry, JSON state or separate relay scheduler. FFmpeg output is disabled because diagnostics can include the secret ingestion URL. The hook also exits when the channel is disconnected or the event is no longer public/enabled.

Ending the event queues completion of the existing broadcast. Failed completion is retried through the same publication queue; the original publication time is retained. Already completed broadcasts are accepted, while broadcasts that never started are reported separately without an invalid `complete` transition. Local HLS buffering and platform processing are technical latency, not an editorial hold.

This means OBS uses the existing single shared local RTMPS address/key. A new YouTube stream key is not created for every Live event.

## Configuration

Set these server environment values in the Plesk application environment, then configure the OAuth redirect URI in Google Cloud to exactly match it:

- `YOUTUBE_OAUTH_CLIENT_ID` and `YOUTUBE_OAUTH_CLIENT_SECRET` — OAuth web application credentials.
- `YOUTUBE_OAUTH_REDIRECT_URI` — normally `https://mannavomhimmel.de/desktop/publishing/youtube/callback`.
- `YOUTUBE_SYNC_DOWNLOADER` — installed `yt-dlp` executable used only for review imports.
- `LIVE_RELAY_FFMPEG` — installed `ffmpeg` executable used for Live relay.

The OAuth access and refresh tokens, including the reusable YouTube stream metadata, are stored in the encrypted `settings.secret.social_youtube` value of this single-tenant installation. Connection metadata is kept separately without tokens. Disconnecting clears the encrypted credentials and blocks queued work, synchronization and relay. OAuth requires a nonempty, matching, single-use session state; reconnecting to a different channel discards the previous channel's refresh token/stream. Stored publication errors redact credentials. No token is written to Git, public media or publication payloads.

The YouTube connector does not claim Community post support: the YouTube Data API does not provide a general channel-bulletin publishing endpoint in this workflow. Video/Short uploads and Live broadcasts are supported.

## Operations

The existing Laravel scheduler runs `publishing:youtube-sync` every five minutes and `publishing:retry-due` every minute. The latter dispatches due failures (up to five automatic attempts, with increasing backoff) and platform-processing checks. Manual retry remains available after automatic attempts are exhausted. The existing Plesk queue worker handles publications and synchronization; no additional scheduled task or service is needed.

After updating, run `/opt/plesk/php/8.4/bin/php platform/artisan public:live-config` to regenerate the installed MediaMTX configuration with `runOnAvailable`, `runOnAvailableRestart` and `runOnUnavailable`. MediaMTX supports configuration reload; apply this update off-air. OAuth credentials, Google audit, connected-platform permissions, `yt-dlp`, `ffmpeg`, installed MediaMTX and production streaming need owner-side configuration/verification. HTTP/queue/browser regression tests do not prove a real external publication. The initial Publishing database migration must already be applied (back up before any production migration).
