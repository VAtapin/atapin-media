# Publishing

Publishing is a server-side, queue-backed workflow in `platform/`. A record published on the Website is immediately made public and, when destinations are selected, creates one outbound publication per connected platform. Each destination keeps its own queue status, remote ID/URL, attempt count, timestamps, error and retry action.

## Current flow

- Videos, Shorts, Beiträge and Live events can be selected in the Publishing window.
- Website is an internal destination and becomes public in the same application transaction.
- YouTube, Facebook, Instagram and Telegram use real HTTP connectors when their encrypted connection is configured. Unavailable connector capabilities are marked as skipped or failed per destination; they do not block the Website.
- YouTube video uploads read the canonical local media file through `MediaOriginalLocator`, so SHA-256 filenames and the public `platform/public/media` catalog remain the source of the file. Imported YouTube files are downloaded to a private temporary directory, then registered in canonical storage and shown as review-only content.
- Reverse YouTube synchronization is scheduled every five minutes. A synchronized item never becomes public automatically: it is created as `review` with `public_published=false` until an editor publishes it.

## YouTube Live

The first successful Live publication creates one reusable YouTube `liveStream` and stores its stream metadata encrypted with the YouTube connection. Every later event creates only a new broadcast and binds it to that same reusable stream. The local MediaMTX shared RTMPS path is relayed to the YouTube ingestion URL by `ffmpeg` when the event becomes live; the relay is stopped when the event ends. The scheduled `publishing:live-relay-reconcile` command is a recovery path for hooks that were interrupted.

This means OBS uses the existing single shared local RTMPS address/key. A new YouTube stream key is not created for every Live event.

## Configuration

Set these server environment values in the Plesk application environment, then configure the OAuth redirect URI in Google Cloud to exactly match it:

- `YOUTUBE_OAUTH_CLIENT_ID` and `YOUTUBE_OAUTH_CLIENT_SECRET` — OAuth web application credentials.
- `YOUTUBE_OAUTH_REDIRECT_URI` — normally `https://mannavomhimmel.de/desktop/publishing/youtube/callback`.
- `YOUTUBE_SYNC_DOWNLOADER` — installed `yt-dlp` executable used only for review imports.
- `LIVE_RELAY_FFMPEG` — installed `ffmpeg` executable used for Live relay.

The OAuth access and refresh tokens, including the reusable YouTube stream metadata, are stored in the encrypted `settings.secret.social_youtube` value. Connection metadata is kept separately without tokens. No token is written to Git, public media, publication payloads or relay state files.

The YouTube connector does not claim Community post support: the YouTube Data API does not provide a general channel-bulletin publishing endpoint in this workflow. Video/Short uploads and Live broadcasts are supported.

## Operations

The Laravel scheduler must run `publishing:youtube-sync` every five minutes and `publishing:live-relay-reconcile` every minute. The existing Plesk queue worker must be running because publication and synchronization are queued jobs. The Live MediaMTX hooks call the relay immediately; the scheduler repairs missed lifecycle hooks.
