# Admin completion — implementation boundary

Status: 14 September 2026. This is an implementation checklist against MASTER-TZ sections 16–30 and 33–44, not a declaration that the entire roadmap is complete.

## Implemented

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

## Verified follow-up: review and planning controls

- Task deadlines now support a separate optional HH:mm clock in the system timezone. Existing date-only deadlines remain valid; partial updates preserve time, clearing the date clears time, and the calendar displays it without UTC day shifts.
- Project timeline records actual previous/current phases and task statuses. Moving a task does not erase its existing project history. Only safe status fields, not arbitrary audit context, are returned.
- AI proposals can be edited and saved before explicit application. Per-account authorization, bounded fields, proposal version conflicts and existing source-staleness checks apply. Unsaved edits block Apply; another tab's changed proposal cannot silently replace the reviewed one.
- Content Library has a selectable table with saved columns, approved sort fields/directions and project/topic/editorial-phase/Review filters. Historical publication date is explicitly editable only by publishers; it does not publish a draft.
- Import Review provides explicit confirmation, ready-state acceptance without public publication, original metadata preservation and opening the actual editor. Import Center includes an eight-stage permission-scoped migration assistant using existing tools and a paginated Review queue. Personal checkmarks are not proof that data has been migrated; legacy structures and subscriber consent are not guessed.
- Poll admin shows participant counts, per-option counts and percentages for single/multiple/legacy ballots. Public/admin use the same bounded-memory tally. External polls support HTTPS links or sandboxed iframes on exact approved third-party hosts. External votes/results and audience enforcement remain with that provider.
- Overview adds processing/problem media, review items, own AI suggestions and external publication states. Widgets support saved wide/normal sizing and drag/keyboard ordering; narrow windows and mobile fall back to one column. Media items open the actual file rather than only the module.

For external iframe polls, the owner may set the non-secret comma-separated `POLL_EMBED_HOSTS` in the private runtime `.env`, then clear config with Plesk PHP 8.4. No hosts are approved by default, no wildcards or same-site embeds are allowed, and the application does not fetch arbitrary poll URLs server-side. A link remains available if the external provider refuses framing. No production hosts/settings were changed by the agent.

## Still unfinished — do not mark complete

- Video-specific duration/size/processing columns and filters; detailed per-provider thumbnail/hashtag/scheduling overrides and final payload/media preview. The new generic content table is not the entire video management specification.
- Generic RSS/URL import adapters and verified legacy subscriber ingestion. The assistant intentionally does not invent legacy mappings or bypass confirmed consent.
- Real inbound YouTube comment synchronization and other supported inbound channel adapters. Existing queued YouTube replies and daily video review import are not incoming comment synchronization.
- Generated AI prioritization/recommendations beyond the contextual actions and own suggestion history in Import Review/Desktop.
- Live: Browser Studio now supports camera/microphone, screen, contained local images/PiP/title, actual browser-output telemetry, WHIP start/stop and local WAV recording with unpublished Podcast drafts. Protected server configuration and confirmed OBS disconnect are implemented; everyday OBS control remains in OBS. See docs/PUBLIC-LIVE.md. Linux/Plesk configuration/normalization and production media still require hosting verification. TURN, guests, full OBS-like scenes/mixer and server replay merge remain unfinished.
- External analytics adapters, TikTok/LinkedIn publishing and generic saved-credential integrations. A saved credential or connected flag does not implement a provider adapter.

## Owner decision required: restricted original media

The plan includes registered/subscriber access rules, while canonical MP4/audio/image originals intentionally remain publicly accessible under public/media/SHA-256. Page-only gating cannot protect those bytes. Complete restricted access therefore requires an approved private-original migration and compatibility plan for existing links, HLS, publishing and recordings. No public originals were moved or existing original URLs broken in this release. Paid complete book PDFs remain private and entitlement-protected.

## Verification and deployment

See PROJECT_STATUS.md for final actually executed checks. Browser tests use a separate synthetic SQLite database and no paid provider calls. Real OpenAI/SMTP/Stripe/social/Live connections and production are not verified by local fixtures.

Back up the production database in Plesk before applying pending forward migrations, including `add_task_deadline_time`. No Composer dependencies or Node production build changed. After receiving the code, migrate with Plesk PHP 8.4 and clear config/routes/views. This follow-up adds no queue jobs or cron/systemd services and moves no archives. If the preceding core release was not deployed, also follow that release's queue-worker restart instruction. Production is not updated by Git push.
