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

Broadcast uses OBS → encrypted RTMPS → MediaMTX → the local HLS player. Settings → System → Live management opens /desktop/live (content.publish permission). Create an event, enable ingest, copy its complete private RTMPS server address into OBS Server and leave Stream key empty. Rotate invalidates the old key. HLS reading requires explicit public publication, publishing requires the encrypted event key. The local RTMP fallback remains on loopback for administrators; only the TLS RTMPS listener is public.

Run public:live-config, then bash platform/bin/live-server.sh install and start as the Plesk subscription user. Installation pins official MediaMTX 1.21.0 and verifies its SHA-256. No Docker, root ownership changes or systemd installation. Start is detached with a lock; status and private/atapin-live/server.log expose process state. Configure a separate Plesk command task to run `/bin/bash /var/www/vhosts/mannavomhimmel.de/httpdocs/platform/bin/live-server.sh start` every minute; the lock makes this safe and it restarts MediaMTX automatically after a reboot. The script does not configure Plesk.

For public RTMPS, the administrator must place the active Plesk certificate chain and its private key at `private/atapin-live/rtmps.crt` and `private/atapin-live/rtmps.key` (or set `LIVE_RTMP_CERT` and `LIVE_RTMP_KEY` in the private platform `.env`). The files must be readable by the subscription user; keep the key mode `0600` and never commit either file. Allow inbound TCP `1936` in the server firewall and use the certificate's DNS name in `LIVE_RTMP_HOST` (default: the `APP_URL` host). Run `public:live-config` again after installing or renewing the certificate. OBS requires a certificate from a public certificate authority; self-signed certificates are rejected by OBS. The streamer does not need SSH or server access after this one-time setup.

Plesk additional nginx directives (owner applies):
```nginx
location /_live/ {
    proxy_pass http://127.0.0.1:8888/;
    proxy_http_version 1.1;
    proxy_buffering off;
}
```
The SSH tunnel is retained only as an administrative fallback: `ssh -N -L 1935:127.0.0.1:1935 YOUR_PLESK_SSH_USER@mannavomhimmel.de`. Use H.264/AAC and a two-second keyframe interval. The website embeds only /_live/live-ID/, not YouTube. MediaMTX hooks update live/ended status and register completed MP4 segments in Media Library without copying. Originals have no automatic expiry; monitor disk usage. Long broadcasts produce multiple one-hour segments, all linked to the event; the existing replay selects the first playable original, not a merged recording. HLS has buffering latency; no server transcoding or paid AI is involved.

AI assistant: Settings → AI contains a separate opt-in switch and whole-site daily request cap (default 20; zero disables new requests). The existing provider/key/model are reused. Signed-in visitors explicitly consent and ask via the AI form (`@Assistent` prefix is accepted there). Answers are labelled AI and private to the requesting visitor, not automatically published to the public chat. Only bounded published article/video text is passed as context; no media analysis or tools/private archive access. Each queued request has one API attempt, at most 400 output tokens and a 1,000-character question; failures still consume the reservation to prevent retry loops. The cap limits calls, not an exact currency amount; configure provider-side spending limits separately. Disabling the assistant before processing prevents the call. Local tests use Http fakes, never paid requests.
