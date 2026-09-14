# Publishing

Publishing is the existing queue-backed workflow in `platform/`. Publishing on the Website makes the record public immediately and queues selected connected destinations without another confirmation, scheduled hold or approval. Uploading, encoding and platform processing still take technical time. Each destination retains its status, remote ID/URL, attempts, timestamps, error and retry action.

## Content and destinations

- Publishing offers checkboxes for Videos, Shorts, Beiträge and Live events. Saved `publishing_targets`, including an empty selection, are restored in the GUI and govern later automation. Website/YouTube are initially selected only when no choice has been saved. Without saved targets, Website publication uses connected platforms. Unsupported destinations do not block the Website.
- Real connectors exist for YouTube, Facebook, Instagram, Telegram and X. Permissions, account eligibility, file limits and quotas apply. LinkedIn and unidentified services are not implemented connectors.
- YouTube always requests `public`, without `publishAt`. Nonpublic responses fail visibly. Unverified API projects can be restricted to private uploads; the installation needs the required [YouTube API audit](https://developers.google.com/youtube/v3/docs/videos/insert).
- A Beitrag containing video uploads that video to video-upload destinations; Telegram sends the Website announcement described below. For YouTube, a Beitrag without video becomes an MP4: an existing image or scrolling text card, accompanied by existing audio when available. Image/text-only cards last 15 seconds; audio cards follow audio duration. No automatic voice generation is provided. Facebook/X/Instagram use native text/image formats where available and adapt audio to video. Telegram sends audio natively.
- The shared ffmpeg renderer creates H.264/AAC MP4s, reuses identical adaptations and registers them through existing canonical `public/media/<sha256>.mp4` storage. It does not replace originals or attach derivatives to Website records. Private temporary files are cleaned up.
- YouTube `post=true` means video adaptation, not native Community publishing. The [Data API activities reference](https://developers.google.com/youtube/v3/docs/activities) does not provide a usable general channel-bulletin publishing method.
- TikTok Direct Post is excluded from this unattended private publishing workflow. Its [Content Sharing Guidelines](https://developers.tiktok.com/doc/content-sharing-guidelines) require creator-facing control/consent and reject an internal utility for accounts managed by the developer/team as an acceptable audited use case. No unofficial/browser bypass is implemented.

## Telegram video links and Main Mini App

New Telegram video/Short announcements (including Beiträge with video) send the available cover, title, existing short description and Website detail-page button, not video bytes. Without a cover they send text; without a short description they use the title. Text is capped at 1024 characters without paid AI generation. Already sent messages and native text/image/audio posts retain their existing behavior. The selected-destination queue, saved message IDs, statuses, errors, retries and supported edit/delete policy are reused. Announcements require a publicly published Website material.

- The Main Mini App uses the existing Website homepage, pages, player and media storage. The Telegram SDK is loaded only in Telegram launch context; initialization, viewport/safe areas and Back navigation are supported. There is no second website, player, content database, iframe wrapper or Telegram login. Public viewing preserves existing authorization boundaries.
- In BotFather, configure the existing bot's **Main Mini App URL** as the installation's HTTPS homepage (Manna: `https://mannavomhimmel.de/`). In Settings → Social Media → Telegram, save the **bot username without @** and enable **Main Mini App is configured in BotFather** only after registration. The checkbox does not register anything externally. The form displays the installation entry URL. BotFather setup is an owner-side step, not completed by code deployment or saving a bot token.
- Mini App browsing needs no channel or bot token. Channel distribution separately needs a public channel/group destination (`@channel` or Chat-ID), bot token and bot posting permissions. The public channel URL is optional metadata and is neither the bot username nor the Mini App URL.
- Configured announcements add `https://t.me/<botusername>?startapp=record_<id>` alongside the ordinary Website button. The throttled public `/telegram/material/{token}` resolver accepts only bounded record tokens and resolves only ready/public/non-archived video, Short or Beitrag records through the existing public content service. No arbitrary redirect URL, private record or admin page is accepted. Channel posts use ordinary URL buttons, not the `web_app` button type reserved for private user-bot chats. See the [official launch mechanisms](https://core.telegram.org/bots/webapps#launching-the-main-mini-app) and [button restrictions](https://core.telegram.org/bots/api#inlinekeyboardbutton).
- Video playback stays on our server. Neither uploading/compressing a video for Telegram nor autoplay is promised. Ordinary Website links may open a browser; registered Mini App links use Telegram's launch flow. Real Android/iOS/Desktop launch, fullscreen and playback acceptance still require the configured bot and owner-side device checks.
- Bot tokens remain server-side and encrypted. Public launch tokens select a page, never authenticate users. `initDataUnsafe` is not used. Future identity-based actions must validate signed `initData` and its age server-side. No account linking, payments, reverse import or webhook registration is added; launching this public Mini App requires no incoming webhook. See [Telegram initialization/data validation](https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app).

Local mocked checks verify announcements, safe public resolution and message edits. They do not register the bot, publish real messages or prove Telegram-device playback.

## Upload recovery and publication checks

YouTube uses resumable 8 MiB chunks. Session URL/path/size are encrypted in the publication checkpoint. After interruption, the worker queries accepted bytes and resumes from the server-confirmed offset. Querying the same session after a lost final reply recovers the completed video ID without another upload, following the [resumable upload protocol](https://developers.google.com/youtube/v3/guides/using_resumable_upload_protocol). Expired sessions/changed sources fail visibly instead of silently creating duplicates.

Video/broadcast IDs are saved before thumbnails/binding. Facebook saves its ID before processing checks; Instagram retains container/media IDs; X retains chunk/media checkpoints and post IDs; Telegram retains message IDs. Retries reuse these. Atomic claims prevent concurrent execution of one publication. This is not universal exactly-once delivery: an operation without API idempotency/recovery can lose its creation reply before saving an ID, especially broadcast creation or sending a new post/message.

YouTube checks public visibility and processing rejection; uploaded/processing videos schedule another check. Facebook video checks encoding readiness and `published`; Instagram checks container readiness and retrieves the actual permalink; X checks media processing and retrieves its created post. New publications refuse X protected accounts and Telegram private destinations. API confirmation does not prove guest playback in every region: copyright, geographic and later moderation restrictions remain possible.

Processing checks use the queue, not sleeping workers. Five consecutive failures exhaust automatic retries; pending checks do not consume that failure budget. Manual retry applies to failed work, not queued/processing/successful work.

## Reverse YouTube import

`publishing:youtube-sync` runs once daily at midnight in the application's timezone. Discovery considers uploads published within the previous 24 hours, without the old 500-video cap. Previously discovered unfinished/failed items are also rechecked by ID, allowing downloads/Live recordings to finish outside that window. Failed daily runs do not backfill undiscovered older days automatically; historical imports remain an Import Center operation.

Private downloads enter the same canonical SHA-256 registry. Records remain `review`, `public_published=false`, until an editor activates them. Own outbound IDs link to original Website records without a download/review duplicate. External imports retain their YouTube mapping, so activating the Website does not upload them back. Active external Live streams wait for recordings, rather than automatically embedding the currently running stream. Historical duplicates are not removed automatically.

## Live output

Live Studio's existing `kind=video`, `public_section=live` contract is recognized. One reusable YouTube `liveStream`/key is stored encrypted per connected channel. Later events create a public broadcast bound to that same stream, not a new key. OBS retains its existing single local RTMPS address/key.

Publishing accepts named `rtmp_*` destinations with a complete RTMP/RTMPS URL including the platform key. They are Live-only, encrypted, and URLs/keys are never returned in Publishing JSON. This requires an ingestion address/key actually provided by the platform/account. There is no generic arbitrary REST/API connector: each API has distinct authentication and resource/payload contracts.

The existing MediaMTX `runOnAvailable` foreground hook owns one ffmpeg child per selected output, relaying authorized local HLS to YouTube/configured RTMP destinations. A failed output reconnects independently without stopping others. One OS lock prevents competing hooks. No detached PID registry, JSON process manager, new scheduler or additional daemon is introduced. Disconnect/remove/deselect stops that output; a Live title update does not interrupt it. Update ingestion keys off-air.

`relaying` means local ffmpeg is running, not confirmed remote public playback. YouTube additionally polls public broadcast lifecycle and reports `live` only after API confirmation. Ending a Website event queues completion of its existing broadcast, with shared retries; completed and never-started broadcasts are distinguished. Generic RTMP destinations do not have platform-specific public-playback/status API checks.

## Subsequent changes and deletion

Website edits queue supported operations on the existing external ID, including edits during original-upload processing:

| Destination | Text/metadata update | Reversible unpublish | Explicit deletion |
| --- | --- | --- | --- |
| YouTube | Title, description, tags, cover | Private visibility | Yes |
| Facebook | Video title/description; Page post/photo-story message | Video/Page post, not a photo asset | Yes |
| Telegram | Text or media caption | No | Yes, within Bot API limits |
| X | Not implemented | No | Yes |
| Instagram | Not implemented | Not implemented | Not implemented |

YouTube media bytes cannot be replaced by metadata updates. Editing image/audio/text does not automatically re-encode/replace an already published adaptation. Individual platform-specific text/visibility/schedule editors are not implemented; the source record drives supported updates. X editing is not treated as an unrestricted metadata endpoint; Instagram editing/deletion are not promised by this connector.

Default Website unpublication/soft deletion uses reversible hiding where supported and **does not destroy** X/Telegram/photo copies. A per-record GUI checkbox explicitly enables deletion on supported connectors when the Website is unpublished/soft-deleted; off by default, it warns of irreversible deletion. A separate confirmed “delete on platform” action retains local records/files. Restoring hidden records reapplies public visibility; recreating deleted copies requires explicit publication again. Hard deletion is not a reliable remote-cleanup mechanism.

The Website publication status follows its actual ready/public/trash flags and reports `unpublished` after removal, without erasing the publication timestamp. Telegram stores the sent text/media message type together with its ID: later local attachments do not change the edit method. Legacy messages without that checkpoint retain the previous attachment-based fallback; their historical type cannot be recovered reliably from local attachments.

Imported YouTube mappings are protected from ordinary Website metadata/visibility edits. Explicit deletion, or explicitly enabling deletion-on-unpublish, can remove them. Daily reverse import does not overwrite owner edits or implement bidirectional metadata/delete propagation.

## Configuration and operations

### Social Media connection forms

The existing editor and server validation share one provider field definition. Irrelevant fields are hidden, disabled and rejected by validation; unsaved credentials are cleared when switching providers. Blank secret fields preserve saved credentials, and partial saves preserve unrelated OAuth/connection data. Profile-only settings and Mini App-only settings are not reported as publishing connections. Saving configuration does not confirm remote API permission.

| Network | Editor inputs | Obtained elsewhere / not entered here |
| --- | --- | --- |
| YouTube | Optional public URL; existing OAuth connection action | Channel ID/access/refresh tokens from OAuth; Google application credentials configured by an administrator in Social Media settings |
| X | Optional public URL; existing OAuth connection action | User ID/tokens from OAuth; app configuration, API access/credits separately |
| Facebook | Page ID and authorized Page Access Token; optional public URL | Publishing permissions must already be granted; no generic API key/app ID/webhook field |
| Instagram | Business/Creator account ID and authorized Meta token; optional public URL | Token must match the configured connector API and publishing permissions; no generic API key/app ID/webhook field |
| Telegram | Bot Token + destination Chat-ID for posts; optional channel URL; bot username + configured Main Mini App checkbox for Mini App links | Bot token from BotFather, destination and bot posting permissions; Mini App registration in BotFather. Mini App-only browsing requires no channel/token |
| TikTok | Optional public profile URL only | No supported unattended publishing connector in this installation |
| LinkedIn | Optional public profile/Page URL only | Publishing connector not implemented |

Localized German/English instructions explain required destination values, optional public URLs, OAuth, secret preservation and Mini App registration. The interface does not expose saved secrets. Existing integration forms outside Social Media are unchanged.

No Social Media connector in this block implements incoming webhooks, so none asks for **Webhook Secret**. There is no unused secret generator or fake registration. If an incoming workflow is added later, locally controlled verification tokens must be generated securely, encrypted and rotated explicitly; provider-issued signing/app secrets must not be fabricated. Telegram `setWebhook.secret_token` is locally selectable but unnecessary for outbound publishing or this Mini App. See the [Telegram webhook contract](https://core.telegram.org/bots/api#setwebhook).

Configure secrets on the server, never in Git/chat:

- YouTube OAuth Client ID/Secret and X OAuth Client ID/Secret are entered by an administrator in **Einstellungen → Social Media** and stored encrypted in the settings database. OAuth callbacks are fixed application routes: `https://mannavomhimmel.de/desktop/publishing/youtube/callback` and `https://mannavomhimmel.de/desktop/publishing/x/callback`. X uses OAuth 2.0 with PKCE and scopes `tweet.read tweet.write users.read media.write offline.access`; the developer app needs write/media access and [X API credits](https://docs.x.com/x-api/getting-started/pricing). [Official OAuth setup](https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code).
- `YOUTUBE_SYNC_DOWNLOADER`: installed yt-dlp for review imports.
- `LIVE_RELAY_FFMPEG`: installed ffmpeg for relay/adaptation, with H.264/AAC and drawtext/usable font for text cards.
- Facebook/Instagram Page/account IDs and authorized tokens; Telegram public channel/group ID and bot token: existing encrypted integration settings, with publishing permissions.

Access/refresh tokens and reusable stream data are encrypted in installation settings; upload-session checkpoints are encrypted too. The platform is single-tenant: each client deployment has its own settings database/encryption key, not shared cross-client credentials. Disconnect clears credentials and blocks subsequent work. OAuth validates nonempty matching one-use state (X also PKCE); errors redact known credentials. Secrets do not enter public media/Publishing JSON.

Existing Plesk scheduler runs daily discovery and `publishing:retry-due` every minute; existing queue workers handle uploads, adaptations, management actions and imports. No new cron/service/dependency/migration is introduced in this follow-up; initial Publishing migration must already be installed. If original foreground hooks were never applied, regenerate MediaMTX config off-air with `/opt/plesk/php/8.4/bin/php platform/artisan public:live-config`; already deployed hooks retain the same entrypoint.

PHP/SQLite, mocked HTTP/Process and browser regressions verify application paths, not real encoding, MediaMTX ingestion, OAuth approval/credits or external playback. Owner-side account configuration and deliberately selected real test publications/streams remain required. These tests perform no production publication/deployment.
