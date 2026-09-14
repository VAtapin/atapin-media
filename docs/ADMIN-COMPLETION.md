# Admin completion — implementation boundary

Status: 14 September 2026. This is an implementation checklist against MASTER-TZ sections 16–30 and 33–44, not a declaration that the entire roadmap is complete.

## Implemented in this release

- Desktop overview: real permission-scoped recent projects/tasks/content, storage, publication queue, Live and inbox counters. Widget visibility and order are saved per account/device. Technical queue failures are visible only with settings permission.
- Projects: type, responsible person, team, start/deadline, tags, cover, next action, task progress, linked content/books and paginated work history. Tasks: content linkage, priority, tags, editable checklist and project/assignee/content/status/deadline filters, existing board and My Tasks.
- Existing content editor: independent editorial stage, editable canonical slug and locale, review state, Podcast episode/season, contextual AI and an authenticated preview using real public templates. Preview does not publish, submit comments or record playback/progress. Original document and private cover access remain authorization-protected.
- Document-to-article: queued TXT/HTML/DOCX extraction from existing Media originals, bounded input, HTML sanitization, unpublished draft, original usage link and idempotent re-import preserving manual edits. Failures are reported rather than replacing the original.
- Books: subtitle, publication date, tags and SEO metadata, contextual book AI, book usage links in the Media inspector. Existing private PDF editions, previews, payment and download roles remain unchanged.
- Polls: native edit/create, choices, start/end, active state, registered/confirmed-subscriber audience, single/multiple votes, result policy and placement. Voting and option edits lock the same record; choices cannot change after votes. Closed results can remain visible; future polls do not expose after-close results.
- AI: provider interface with the real existing OpenAI Responses adapter; additional editorial purposes, book/comment/destination context, regeneration, copy and explicit stale-safe application. No tools or automatic publication. Configured model, structured output and store=false are preserved.
- Publishing: destination-specific text preview and contextual AI, using current provider text rules. YouTube visibility remains public by the existing owner decision.
- Community: author/date/related item/original link, contextual reply suggestions and only supported reply targets. Unsupported replies have an explanation rather than an invisible local child record.
- Live: current signal status, timer, active Website presence, approved local chat, recording state and output states. Published Website playback is embedded. OBS remains responsible for starting/stopping the encoder.

## Still unfinished — do not mark complete

- Task deadline time (current task deadline remains date-only); fuller project phase history and richer dashboard recommendations, processing/social-status widgets and arbitrary widget positioning.
- Explicit historical publication-date editing; full video list column/sort/filter UI; detailed per-provider thumbnail/hashtag/scheduling overrides and final payload/media preview.
- Import Review accept/open-editor flow and assisted Migration Wizard; generic RSS/URL import adapters. Existing Takeout/import checkpoint workflows are not a completed migration wizard.
- Real inbound YouTube comment synchronization and other supported inbound channel adapters. Existing queued YouTube replies and daily video review import are not incoming comment synchronization.
- External poll embeds/iframe allowlisting and richer result counts in the admin editor.
- Editable AI response before application and recommendations in Import Review/Desktop. Free prompt plus regeneration/copy is not a full response editor.
- Live remote Start/Stop/emergency controls, bitrate/drop telemetry and browser camera/microphone publishing. MediaMTX API is disabled in the current server configuration; no fake remote OBS controls were introduced. Scene/mixer features are explicitly later extensions in MASTER-TZ.
- External analytics adapters, TikTok/LinkedIn publishing and generic saved-credential integrations. A saved credential or connected flag does not implement a provider adapter.

## Owner decision required: restricted original media

The plan includes registered/subscriber access rules, while canonical MP4/audio/image originals intentionally remain publicly accessible under public/media/SHA-256. Page-only gating cannot protect those bytes. Complete restricted access therefore requires an approved private-original migration and compatibility plan for existing links, HLS, publishing and recordings. No public originals were moved or existing original URLs broken in this release. Paid complete book PDFs remain private and entitlement-protected.

## Verification and deployment

See PROJECT_STATUS.md for final actually executed checks. Browser tests use a separate synthetic SQLite database and no paid provider calls. Real OpenAI/SMTP/Stripe/social/Live connections and production are not verified by local fixtures.

Back up the production database in Plesk before applying the new forward migration. No Composer dependencies or Node production build changed. After receiving the code, apply the pending migration with Plesk PHP 8.4, clear cached routes/views and restart existing queue workers. No new cron/systemd services and no archive moves are required. Production is not updated by Git push.
