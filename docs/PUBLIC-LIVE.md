# Public Live

Email reminders use the existing Laravel scheduler and queue (Plesk scheduled PHP tasks); no separate daemon or paid service is installed. Local PHP sendmail is supported with MAIL_MAILER=sendmail and the actual Plesk sendmail path; external SMTP is optional. This is configuration readiness, not proof of server delivery.

- A signed-in visitor opts in on a published future scheduled Live event. Clicking again cancels.
- `public:live-reminders` runs every minute and queues candidates within 15 minutes of starts_at. Time without a zone uses platform.timezone.
- The job rechecks publication, consent and the unchanged event time. Successful SMTP submission is recorded per event time; rescheduling can generate a new reminder. Unique queue locks suppress simultaneous duplicates. SMTP submission is not proof of inbox delivery; mail protocols cannot guarantee exactly-once delivery after a process crash.
- Three job attempts are allowed. A terminal failure is visible and does not restart every minute; cancel/re-enable opts into a retry.
- MAIL_MAILER can be sendmail for local Plesk delivery, or smtp with the deployment's actual MAIL_HOST/MAIL_PORT and authentication/TLS settings. Do not use log/array as real delivery. Store credentials only in the private environment, never Git. If settings change, clear Laravel configuration cache. Local sendmail must be available to the Plesk PHP worker; configuring a path does not verify delivery.
- Existing Plesk scheduler/queue tasks must be enabled. Diagnostic: `/opt/plesk/php/8.4/bin/php platform/artisan public:live-reminders`. The command does not configure SMTP or modify Plesk.

Live chat and page presence refresh every 15 seconds using a CSRF-protected heartbeat. Only published reviewed chat is returned; publication revocations are respected by fresh snapshots. Online counts distinct browser sessions seen within 120 seconds, not proven video viewers. Only a one-way session hash is stored; expired rows are pruned daily. No WebSocket daemon is required.

Web Push uses minishlink/web-push, private encrypted subscriptions and the existing queue. Run `public:push-key` once after migration to generate a stable private VAPID pair (existing pairs are preserved). HTTPS and notification permission are required; signed-in visitors can subscribe/cancel per event and browser. `/public-push-sw.js` only handles notifications; it does not cache private/public pages. Scheduler queues reminders within 15 minutes of starts_at, rechecks publication/time/cancellation, and removes expired subscriptions. Provider acceptance is not proof of notification display. Endpoint hosts are restricted to Google, Mozilla and Apple; redirects are disabled.

Broadcast ingest remains unimplemented; the owner selected OBS → MediaMTX on our server → local website player. No production email or push notification was sent by local tests.
