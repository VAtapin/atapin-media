# Public Live

Email reminders use the existing Laravel scheduler and queue (Plesk scheduled PHP tasks); no separate daemon or paid service is installed.

- A signed-in visitor opts in on a published future scheduled Live event. Clicking again cancels.
- `public:live-reminders` runs every minute and queues candidates within 15 minutes of starts_at. Time without a zone uses platform.timezone.
- The job rechecks publication, consent and the unchanged event time. Successful SMTP submission is recorded per event time; rescheduling can generate a new reminder. Unique queue locks suppress simultaneous duplicates. SMTP submission is not proof of inbox delivery; mail protocols cannot guarantee exactly-once delivery after a process crash.
- Three job attempts are allowed. A terminal failure is visible and does not restart every minute; cancel/re-enable opts into a retry.
- MAIL_MAILER must be smtp, with the deployment's actual MAIL_HOST/MAIL_PORT, sender and required authentication/TLS settings. Do not use log/array as real delivery. Store credentials only in the existing private environment, never Git. If settings change, clear Laravel configuration cache.
- Existing Plesk scheduler/queue tasks must be enabled. Diagnostic: `/opt/plesk/php/8.4/bin/php platform/artisan public:live-reminders`. The command does not configure SMTP or modify Plesk.

Live chat and page presence refresh every 15 seconds using a CSRF-protected heartbeat. Only published reviewed chat is returned; publication revocations are respected by fresh snapshots. Online counts distinct browser sessions seen within 120 seconds, not proven video viewers. Only a one-way session hash is stored; expired rows are pruned daily. No WebSocket daemon is required.

Web Push and the broadcast ingest transport remain unimplemented. Broadcast source/ingest must be selected before implementing the actual stream (OBS RTMP/SRT to this server versus an existing local stream endpoint). No production email was sent by local tests.
