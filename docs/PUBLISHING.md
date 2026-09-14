# Publishing

Publishing is the existing queue-backed workflow in `platform/`. Publishing on the Website makes the record public immediately and queues selected connected destinations without another confirmation, scheduled hold or approval. Uploading, encoding and platform processing still take technical time. Each destination retains its status, remote ID/URL, attempts, timestamps, error and retry action.

## Content and destinations

- Publishing offers checkboxes for Videos, Shorts, Beiträge and Live events. Saved `publishing_targets`, including an empty selection, are restored in the GUI and govern later automation. Website/YouTube are initially selected only when no choice has been saved. Without saved targets, Website publication uses connected platforms. Unsupported destinations do not block the Website.
- Real connectors exist for YouTube, Facebook, Instagram, Telegram and X. Permissions, account eligibility, file limits and quotas apply. LinkedIn and unidentified services are not implemented connectors.
- YouTube always requests `public`, without `publishAt`. Nonpublic responses fail visibly. Unverified API projects can be restricted to private uploads; the installation needs the required [YouTube API audit](https://developers.google.com/youtube/v3/docs/videos/insert).
- A Beitrag containing video uploads that video. For YouTube, a Beitrag without video becomes an MP4: an existing image or scrolling text card, accompanied by existing audio when available. Image/text-only cards last 15 seconds; audio cards follow audio duration. No automatic voice generation is provided. Facebook/X/Instagram use native text/image formats where available and adapt audio to video. Telegram sends audio natively.
- The shared ffmpeg renderer creates H.264/AAC MP4s, reuses identical adaptations and registers them through existing canonical `public/media/<sha256>.mp4` storage. It does not replace originals or attach derivatives to Website records. Private temporary files are cleaned up.
- YouTube `post=true` means video adaptation, not native Community publishing. The [Data API activities reference](https://developers.google.com/youtube/v3/docs/activities) does not provide a usable general channel-bulletin publishing method.
- TikTok Direct Post is excluded from this unattended private publishing workflow. Its [Content Sharing Guidelines](https://developers.tiktok.com/doc/content-sharing-guidelines) require creator-facing control/consent and reject an internal utility for accounts managed by the developer/team as an acceptable audited use case. No unofficial/browser bypass is implemented.

## Planned Telegram video links and Mini App — not implemented

The owner confirmed link-based Telegram video announcements and added a Mini App exposing the existing public Website inside Telegram. This is planning only: the current connector still uploads video bytes and no Mini App has been implemented or registered.

- For new video materials (including Shorts and Beiträge with video), send the available cover, title, existing short description and an ordinary link/button to the public Website detail page instead of `sendVideo`. Without a cover, send text and the same link; without a short description, use the title. Respect caption/text limits without paid AI generation. Reuse the selected-destination queue, IDs, status/error/retry and supported edit/delete policy. Link only publicly published materials, not review/private/trash or admin pages. Keep already sent messages and native text/image/audio publication unchanged.
- Add a Main Mini App to the existing Telegram bot, using the Website's HTTPS entrypoint and existing public pages/player/media storage. No second website, separate player, duplicated content database or iframe wrapper. Minimal Telegram SDK integration should handle initialization, viewport/safe-area sizing and Back navigation without breaking ordinary browser use. Viewing public content does not require a new Telegram login system; preserve all existing authorization boundaries.
- Enable/configure the Main Mini App through BotFather and optionally its bot menu button. Distinguish the bot username, Mini App entry URL and launch link from the public channel URL and Chat-ID. Mini App browsing itself does not require a channel; distributing channel posts still requires the channel destination and bot permissions. BotFather registration is an owner-side step and is not completed by merely saving a bot token.
- A configured Main Mini App supports `https://t.me/<botusername>?startapp=<material-token>`. Resolve a bounded, validated material token to the existing public detail page; never accept an arbitrary redirect URL or let a token bypass publication/access rules. For channel posts, use an ordinary URL button with the Mini App deep link, not the `web_app` button type reserved for private user-bot chats. Retain a normal Website link as fallback when the Mini App is unconfigured/unsupported. See the [official launch mechanisms](https://core.telegram.org/bots/webapps#launching-the-main-mini-app) and [button restrictions](https://core.telegram.org/bots/api#inlinekeyboardbutton).
- Video playback remains on our server in the existing Website player; no video upload to Telegram or automatic video compression is needed for these announcements. Ordinary Website links do not guarantee an in-app browser, while registered Mini App links use Telegram's supported launch flow. Do not promise autoplay. Future acceptance should cover Android/iOS/Desktop launch, public navigation, video controls/fullscreen, safe areas and normal-browser fallback.
- Keep bot tokens server-side and encrypted. Do not trust `initDataUnsafe`; any later identity-based action must validate signed `initData` and its age server-side before using it. Do not introduce Telegram account linking, payments, reverse import or webhook registration into this block. Mini App launch does not itself require a new incoming webhook. See [Telegram initialization/data validation](https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app).

Implementation and owner-side registration follow a separate explicit request; this planning task changes no application code, credentials, external settings or production.

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

### Planned connection-form audit — not implemented

The owner requested planning only; do not change forms, credentials, integrations or webhook registration until implementation is explicitly requested.

- Audit every Social Media form: YouTube, Facebook, Instagram, Telegram, X, TikTok and LinkedIn. Compare the current connector/authentication flow with official provider documentation. Classify each field as required, optional, obtained automatically, server-level application configuration, or unused. Distinguish public profile links from publication destination IDs and authentication credentials.
- Reuse the existing connection editor and provider-specific configuration. Check field visibility on initial open, provider switch, existing-connection editing and reload, including CSS overriding `hidden`. Hidden/irrelevant fields must not be submitted or accepted; server-side validation must match the selected provider rather than the current shared field whitelist. Do not carry unsaved credentials between providers. Preserve saved secrets when left blank and unrelated credentials during partial updates.
- Telegram outbound requires Bot Token and Chat-ID; the public URL is optional metadata. OAuth fields and Webhook Secret are not used by this connector. Add concise localized guidance explaining bot versus channel, where to obtain each value and required destination permissions. Where a supported API can resolve destination details safely, avoid duplicate manual entry.
- For YouTube/X, align the editor with existing OAuth connection buttons and server-side app configuration; do not ask an editor to manually supply tokens that the implemented OAuth flow obtains. Check Facebook/Instagram fields against the actual supported Page/account/token workflow. TikTok/LinkedIn must clearly distinguish saved profile/configuration from an unavailable publishing connector.
- Show webhook configuration only for an implemented incoming-event workflow. Distinguish a locally chosen verification token from a provider-issued signing secret or application secret. Generate locally controlled webhook secrets automatically with secure randomness, encrypted storage and explicit rotation; keep them stable on normal saves. Do not fabricate provider-issued secrets or replace them with a generated value. For Telegram, `setWebhook.secret_token` can be chosen locally, but the current outbound connector needs no webhook or secret; do not add an inbound subsystem merely to justify a field. See the [Telegram webhook contract](https://core.telegram.org/bots/api#setwebhook).
- Provide German/English labels and short instructions per provider, clear required/optional indicators and a distinction between saved configuration and confirmed connection/publishing readiness. Any future connection check must be read-only, not a test publication or paid operation; do not register webhooks or change external settings without the corresponding requested workflow.
- Before implementing, produce a provider/field matrix and a minimal change list covering Blade, existing JavaScript, validation and secret handling. Future acceptance checks should cover all providers, switching/reloading, secret preservation and no irrelevant fields on desktop/mobile. No tests, builds, account API requests or production changes are part of this planning task.

Configure secrets on the server, never in Git/chat:

- `YOUTUBE_OAUTH_CLIENT_ID`, `YOUTUBE_OAUTH_CLIENT_SECRET`, `YOUTUBE_OAUTH_REDIRECT_URI`: Google OAuth web app; callback normally `https://mannavomhimmel.de/desktop/publishing/youtube/callback`.
- `X_OAUTH_CLIENT_ID`, optionally `X_OAUTH_CLIENT_SECRET`, `X_OAUTH_REDIRECT_URI`: OAuth 2.0 user authentication with PKCE; callback normally `https://mannavomhimmel.de/desktop/publishing/x/callback`. Scopes: `tweet.read tweet.write users.read media.write offline.access`. Developer app needs write/media access and [X API credits](https://docs.x.com/x-api/getting-started/pricing). [Official OAuth setup](https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code).
- `YOUTUBE_SYNC_DOWNLOADER`: installed yt-dlp for review imports.
- `LIVE_RELAY_FFMPEG`: installed ffmpeg for relay/adaptation, with H.264/AAC and drawtext/usable font for text cards.
- Facebook/Instagram Page/account IDs and authorized tokens; Telegram public channel/group ID and bot token: existing encrypted integration settings, with publishing permissions.

Access/refresh tokens and reusable stream data are encrypted in installation settings; upload-session checkpoints are encrypted too. The platform is single-tenant: each client deployment has its own settings database/encryption key, not shared cross-client credentials. Disconnect clears credentials and blocks subsequent work. OAuth validates nonempty matching one-use state (X also PKCE); errors redact known credentials. Secrets do not enter public media/Publishing JSON.

Existing Plesk scheduler runs daily discovery and `publishing:retry-due` every minute; existing queue workers handle uploads, adaptations, management actions and imports. No new cron/service/dependency/migration is introduced in this follow-up; initial Publishing migration must already be installed. If original foreground hooks were never applied, regenerate MediaMTX config off-air with `/opt/plesk/php/8.4/bin/php platform/artisan public:live-config`; already deployed hooks retain the same entrypoint.

PHP/SQLite, mocked HTTP/Process and browser regressions verify application paths, not real encoding, MediaMTX ingestion, OAuth approval/credits or external playback. Owner-side account configuration and deliberately selected real test publications/streams remain required. These tests perform no production publication/deployment.
