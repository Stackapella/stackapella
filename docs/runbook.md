# The runbook: running Stackapella

**Status:** written for launch readiness (L, 2026-10-27), by the backend agent. Hillel checks it
before launch. It gathers what the plans say about running the app into one place to work from:
[backend-plan.md](backend-plan.md) A3 and D9, [trust-and-safety-plan.md](trust-and-safety-plan.md)
("The playbook") and [operations-plan.md](operations-plan.md).

## Every day

- **The digest** arrives at 07:00 UTC (`app:digest`): yesterday in numbers, what's waiting (reports,
  coach applications, packs, coaching problems, beta takes, feedback, posts to approve), and any
  readiness check that isn't passing. Nothing should wait more than a day.
- **Posts** wait in `/admin` (Posts) for approval. Each goes 2 minutes after you approve it (undo
  until then). X is pay-per-use: approve an X post only with credits on the account.
- **Alerts** (`app:alerts`, hourly) email you at once when something is over its line
  (`config/ops.php`): sign-ups, reports, share videos waiting, failed payments, storage growth,
  limiter refusals, suppressed addresses. Each says where it's handled.
- **Readiness** (`app:readiness --alert`, every 10 minutes) emails you when a check changes. At a
  terminal, `php artisan app:doctor` shows the build, the versions and every check with its fix.

## The playbook

| What you see | What to do |
|---|---|
| A spike of sign-ups from a few IPs | Shield mode on (`php artisan app:flag shield_mode on --reason="..."`); ban the IPs (Blocklist); check the new accounts' linked page; clear them in bulk |
| Many reports on one shared page | It's already hidden at 3. Look, then Restore or Ban |
| Someone's emails bounce, or a complaint | Cloudflare and our records have suppressed it; check the account; block the domain if it's a pattern |
| The video queue is full | Pause share videos (`pause_videos`); check who's rendering; limit them |
| A burst of failed payments | Card testing: pause Checkout for level 1, turn on shield mode, check Radar |
| A DMCA notice | The page is already hidden; follow the copyright policy (counter-notice, repeat infringers) |
| A DMCA notice about a template in the library | Admin > Library: Hide it with the notice as the reason (its creator gets a notice they can appeal; projects made from it keep their copy). A counter-notice that stands: Show it again after 10 to 14 working days |
| The library's review queue | Admin > Library: each held template with its declaration and reports. Approve, or Hide with the reasons |
| One account downloading the Plus library | Check the flag; limit them, or end Plus and refund |
| Someone to find, or stop | Admin > People: search by name, handle or email; their page has the account (never their songs or recordings), their recent sign-in events, and Suspend with the reason you'd give them (in sudo; Unsuspend undoes it) |
| Someone lost their Google or Apple account | Check it's them with what only the owner knows (a recent receipt, their songs' titles), then Admin > People, their page, Recover sign-in with the address they can read now (in sudo) |
| Someone asks for a Plus refund | Bought on the web: Admin > People, their page, Refund Plus (in sudo, in full or in part). Through the App Store or Google Play: the store refunds it, so point them there |
| A coaching problem reported | `/admin` (Coaching, Disputes): refund in full or in part, in sudo |
| A lesson that doesn't match Event Schedule | `admin.eventSchedule.show` lists it with what was found; the coach or singer may need a refund |
| "Not recorded" in someone's books | It's retried hourly; a refused token waits for them to reconnect |
| Readiness: Scheduler failing | The scheduler worker stopped: restart it in App Platform |
| Readiness: Queue failing | The queue worker is stuck or down; restart it, then `php artisan queue:retry all` once the cause is fixed |
| Readiness: Database backup failing | `php artisan app:backup:run` by hand and read its error (mysqldump, the bucket) |

## Backups and restoring

- **Nightly at 03:10 UTC:** a gzipped `mysqldump` to the `backup` bucket (`db/daily/`, 30 kept; the
  1st of each month also to `db/monthly/`, 12 kept). **Weekly (Sunday 04:10):** every new file in
  `media` and `private` is copied to `backup` (keys never change); a copy whose file was deleted
  goes 30 days later (`backup/state/missing.json` says since when), and exports are never copied. Turn on **versioning** on the
  `private` and `media` buckets (old versions kept 30 days), and on `backup` too. Give the backup
  bucket its own key (`BACKUP_SPACES_KEY`), scoped to it alone.
- **The restore drill, monthly (the 2nd, 05:10):** the newest dump is restored into a scratch
  database, checked, then dropped (`php artisan app:backup:drill`). It fails, naming the table, when
  a table is missing or came back empty, when a live row the backup should hold isn't in it (by id,
  leaving out the last 10 minutes before the dump), or when a foreign key in the copy points at
  nothing; a pass says how many rows and foreign keys it checked. On a managed
  server that can't make databases, set `RESTORE_DRILL_DATABASE` to a database the app's user can
  fill and empty. Readiness warns when no drill has passed in 35 days. **Before launch, run it once
  by hand and read the result.**
- **To restore for real** (the database is lost or damaged):
  1. turn on read-only mode, so nothing new is written while you work;
  2. download the newest good dump from `backup/db/daily/`;
  3. load it into a fresh database (`gunzip -c dump.sql.gz | mysql -h ... -u ... -p stackapella`);
  4. point `DB_DATABASE` at it, redeploy, check `/up` and `app:doctor`;
  5. turn read-only mode off. Anything made since the dump is lost: say so in the app and by email
     to those affected (a transactional notice).
- **Files** come back from the buckets' versions (30 days) or from `backup/media/` and
  `backup/private/`.

## Scheduled jobs

The scheduler worker runs `schedule:work` (`routes/console.php`). Every job has a name and a bounded
`withoutOverlapping`, so a stuck one frees itself; slow ones run in the background, so the
minute's quick ones never wait. Times are UTC. **Sentry** means a cron monitor reports a missed or
failed run (once `SENTRY_LARAVEL_DSN` is set). Sentry's plan has one monitor, so only the jobs named
in `SENTRY_CRON_MONITORS` (comma-separated, `backup-database` by default) get one; the rest are
covered by the heartbeat (readiness' Scheduler check), the doctor and their own effects. To remove
monitors made before this: in Sentry, **Crons**, tick every monitor except `backup-database`, then
**Delete**. The scheduled `readiness --alert` emails what changed and succeeds even when a check
fails (only the plain command and `app:doctor` exit non-zero), so it never shows in Sentry as a
failed job. Every job is safe to run again by hand with
`php artisan <command>`: each picks up only what's still to do (the commission counts what earlier
runs invoiced, a league closes once, a reminder is sent once).

| Job | When | What it does | Sentry |
|---|---|---|---|
| `queue:default` | Every minute | Drains the `default` queue (mail, light jobs); ffmpeg work has its own resident worker on `long` | |
| `heartbeat` (`app:heartbeat`) | Every minute | Records that the scheduler runs (readiness' Scheduler check) | |
| `social-send` | Every minute | Sends approved posts once their 2-minute undo has passed | |
| `coaching-deadlines` | Every 5 minutes | Refunds late reviews and lessons, pays coaches for what was done, drops abandoned checkouts | |
| `readiness` (`--alert`) | Every 10 minutes | Checks everything is in place; emails the admins when a check changes | |
| `practice-reminders` | Every 15 minutes | Sends the practice reminders that are due | |
| `store-tokens` | Every minute | Gives a store token to any account made without one (in a deploy's gap) | |
| `store-acknowledge` | Hourly at :15 | Acknowledges Google Play purchases still waiting (Google refunds them after three days) | |
| `alerts` | Hourly at :05 | Emails the admins when something is over its line (`config/ops.php`) | |
| `event-schedule-reconcile` | Hourly at :20 | Checks upcoming lessons against Event Schedule, fixing what a lost webhook missed | |
| `beta-prune-masters` | Hourly at :25 | Deletes rejected beta masters after 90 days and uploads never finished | |
| `ideas-notify` | Hourly at :25 | Emails writers about new voices on their ideas, at most once a day each | |
| `bin-purge` | Hourly at :35 | Removes what's been in the bin 30 days, orphaned takes, stale uploads, old tombstones | |
| `coaches-refresh` | Hourly at :35 | Works out each coach's lowest price, fastest review and next lesson for the directory | |
| `books-sweep` | Hourly at :35 | Retries Invoice Ninja recordings, and checks sent Plus invoices for payment | |
| `choirs-mix` | Hourly at :45 | Mixes again each virtual choir whose voices changed | |
| `sharing-prune` | Hourly at :45 | Deletes unfinished harmony uploads, expired share videos, renders that never finished | |
| `backup-database` | Daily 03:10 | The nightly database backup (see Backups) | Yes |
| `prune-security-events` | Daily 03:10 | Forgets raw IPs after 30 days and events after a year | |
| `accounts-prune` | Daily 03:30 | Deletes used email links, expired handle holds, old exports, long-unseen browsers, old support messages | |
| `clear-password-resets` | Daily 03:40 | Flushes expired password reset tokens | |
| `stats-nightly` | Daily 03:40 | Counts each song's finishers and recounts the network panel | |
| `prune-failed-jobs` | Daily 03:45 | Deletes failed jobs older than 30 days (their payloads name people) | |
| `cache-prune` | Daily 03:50 | Deletes expired rows from the database cache and its locks | |
| `mudeo-prune` | Daily 03:55 | Deletes the mudeo email list 90 days after its last batch (opt-outs stay, hashed) | |
| `digest` | Daily 07:00 | Emails the admins the daily digest | |
| `social-month` | Daily 08:30 | Proposes posts about a song of the month that starts that day | |
| `leagues-weekly` | Mondays 00:10 | Closes last week's leagues, moves the top 5 up, forms this week's | |
| `check-cloudflare-ranges` | Mondays 04:20 | Checks `config/proxies.php` still matches Cloudflare's published ranges | |
| `backup-files` | Sundays 04:10 | Copies new files in `media` and `private` to the backup bucket | |
| `books-commission` | The 1st, 06:10 | Records last month's coaching commission in Stackapella's Invoice Ninja | |
| `backup-drill` | The 2nd, 05:10 | Restores the newest backup into a scratch database, checks it, drops it | |

## Uploading an instrument's samples

A built instrument (a folder with its `manifest.json`, in the format the frontend's spec sets, and
its samples) goes on the media CDN with the media bucket's keys:

```
php artisan app:instruments:upload piano 1 ./build/piano --check   # check the folder only
php artisan app:instruments:upload piano 1 ./build/piano           # upload, and make it current
php artisan app:instruments:upload piano 1 --current               # go back to version 1
```

- It goes to `instruments/<name>/<version>/`, cached for a year, and **never changes**: the same
  version again is refused, so a fix is a new version (1.1, then 2).
- `instruments/index.json` lists each instrument's current version and manifest, cached for five
  minutes, so the apps see a new version within minutes. `--not-current` uploads without changing
  it (to try a version first).
- The folder may hold only the manifest, samples (m4a, mp3, ogg, opus, flac, wav, webm) and text
  files, with plain names, up to 300 MB; hidden files and links are refused.
- The browsers fetch the files with CORS, which the media bucket already answers for any origin.
- `auth.config`'s `instrumentsIndexUrl` stays null (the apps play the synth) until
  `INSTRUMENTS_ENABLED=true` **and** an instrument has been uploaded; it appears within five
  minutes of the first upload, at once on that server.

### The 11 sets, step by step (Hillel)

The sets are built at `~/Library/Caches/stackapella-samples/build/<name>/<version>`. On this Mac,
in `server/`, with the production media bucket in the shell for these commands (never in a file):
`export MEDIA_DISK=spaces_media SPACES_KEY=… SPACES_SECRET=… SPACES_BUCKET=stackapella-media
CDN_URL=…`, the values as App Platform has them (`SPACES_REGION` and `SPACES_ENDPOINT` too if
they aren't nyc3). Without `MEDIA_DISK=spaces_media` the upload goes to this Mac's own folder.

1. **The MVP's four first** (a song's backing needs a chord instrument, a bass and a beat):
   ```
   B=~/Library/Caches/stackapella-samples/build
   for n in piano upright-bass drums nylon-guitar; do
     v=$(ls "$B/$n" | sort -V | tail -1)
     php artisan app:instruments:upload "$n" "$v" "$B/$n/$v" --check && php artisan app:instruments:upload "$n" "$v" "$B/$n/$v"
   done
   ```
2. **Then the other seven,** the same loop with
   `for n in electric-piano electric-bass brushes body-percussion strings organ ukulele; do`.
3. Set `INSTRUMENTS_ENABLED=true` in App Platform and deploy. Check: `curl -s
   https://app.stackapella.com/api/auth/config | grep instrumentsIndexUrl` shows the index's URL,
   and opening it lists the sets (the server sees the first upload within five minutes).

## Uploading the landing's film

The showreel's videos, sound, posters and captions are rendered locally
(`server/resources/promo/showreel/README.md`) and never committed; only its `manifest.json` (each
file's name, size and sha256) and the small posters are in git. They go on the media CDN with the
media bucket's keys, after the render's manifest is committed:

```
php artisan app:showreel:upload --from ~/Code/stackapella-showreel/server/public/videos/showreel --check
php artisan app:showreel:upload --from ~/Code/stackapella-showreel/server/public/videos/showreel
```

- `--check` only checks: the folder's manifest is the one in git (so the pages ask for this
  render), and each of its 17 files is there at its size and sha256, with every file the landing
  uses among them. Without `--from` it reads `server/public/videos/showreel`.
- Each render goes to `showreel/<version>/` (the start of its manifest's hash), each file with its
  type (WebM and MP4 video, AAC and Opus sound, WebP and JPEG posters, WebVTT captions) and cached
  for a year. A new render is a new folder, so nothing cached goes stale; running it again skips
  what's already there.
- **No setting needed:** with `MEDIA_DISK=spaces_media` and `CDN_URL` set (as in production), the
  landing asks for `<CDN_URL>/showreel/<version>/<file>`, the folder the command writes, and its
  Content-Security-Policy allows that host for video and pictures. `SHOWREEL_URL` is only an
  override. Without a CDN it plays the files from `server/public/videos/showreel` (fine locally).
- Deploy the commit with the new manifest only after its render is uploaded: the pages ask for the
  new folder as soon as the manifest changes.

## Rescoring the boards (not built: how it would run)

The part leaderboards rank each person's best score per song version, part and **scoring version**
(`config('boards.scoring_version')`, `BOARDS_SCORING_VERSION`). Boards compare only within one
scoring version, so a change to the scoring or the pitch detector can't mix old and new numbers.

To carry scores across a better pitch detector (detector version N):
- Every score row keeps what a rescore needs: the take (`take_id`, pinned: deleting the take deletes
  the score, so no audio is kept that someone deleted), the template version, the part, key offset,
  speed, the alignment applied (`alignment_ms`), the output, `pitch_detector_version`,
  `scoring_version` and the per-note results (`notes`).
- The scorer is plain ES modules with no DOM (`web/js/scoring.js`, `web/js/pitchtrack.js`,
  `web/js/dsp.js`; checked in node 22), so a future `app:scores:rescore --detector=N` can run them
  headless over the pinned takes, write the new scores under the next scoring version, and then
  switch `BOARDS_SCORING_VERSION`. Takes with no take reference stay on the old version's board.

## Store testers (Plus from sandbox purchases)

App Review, TestFlight and Google's test purchases are sandbox: they give Plus only to accounts
marked as testers. Before submitting the app, mark App Review's demo account (and any tester), in
admin (a person's page, Store tester; the Store testers list) or at a terminal:

```
php artisan app:store:tester review@stackapella.com        # sandbox purchases give Plus
php artisan app:store:tester review@stackapella.com --off  # and stop
```

## Cost alerts (set by hand, once)

Our own alerts (`app:alerts`) watch what the app can count: storage growth, the video queue,
limiter refusals, suppressed addresses. The bills are watched where they're made, so these are set
in each service's dashboard ([operations-plan.md](operations-plan.md), "Alerts"):

| Where | Alert | Set it to |
|---|---|---|
| DigitalOcean, Billing | Billing alert (the month's spend) | The month's budget, with a first alert below it |
| DigitalOcean, the app (App Platform), Alerts | Deployment failed; domain failed; CPU and memory | Deployments and domains on; CPU and memory over 80% for 10 minutes, for the web, worker and scheduler components |
| DigitalOcean, the MySQL cluster, Alerts | Disk, CPU, memory | Disk over 75% (then resize before it fills); CPU and memory over 85% for 10 minutes |
| DigitalOcean, Spaces | None of its own | Covered by our storage growth alert and the month's billing alert |
| Cloudflare, Email Sending | The daily quota | A notification at 70% |
| X developer portal | Pay-per-use credits | A spend cap a little above a month's posts; approve X posts only with credits on the account |
| Sentry | The plan's event quota | Spike protection on; an email at 80% of the quota |

Each alert goes to Hillel's email.

## The secrets (names only, never values)

Each lives in App Platform's environment (encrypted), unless it says otherwise. Rotate one by
making a new value where it comes from, setting it, redeploying, then revoking the old one.

| Secret | From | Rotating it |
|---|---|---|
| `APP_KEY` | `php artisan key:generate` | Avoid: rotate only if it leaks. Put the old key in `APP_PREVIOUS_KEYS` first, so the encrypted columns still decrypt (connections, second factors, push and store tokens, group links, early-access codes). What no previous key saves, because it's keyed on `APP_KEY` itself: everyone is signed out (sessions, the device check), the admin's recovery codes stop working (make new ones before rotating), the beta pass and the hashed addresses reset (rate limits, report de-duplication, the mudeo list's "already listed", so check the mudeo list isn't mid-send), and passkeys made afterwards get new user handles unless `PASSKEYS_USER_HANDLE_SECRET` is set |
| `APP_PREVIOUS_KEYS` | The old `APP_KEY`, comma-separated | Set only during a rotation; drop it once nothing old is left to decrypt |
| `SQIDS_ALPHABET` | A shuffle of the 62 letters and digits, made once | **Never rotate**: every link and id the app has given out changes. It must not be the default in `config/app.php`, which is public with the source |
| `PASSKEYS_USER_HANDLE_SECRET` | A random 64-character string | Set it before launch so passkeys don't depend on `APP_KEY`; changing it only gives passkeys made afterwards a new handle |
| `DB_PASSWORD`, `DB_CA_CERT` | DigitalOcean Managed MySQL | Reset the user's password there |
| `SPACES_KEY`, `SPACES_SECRET`; `BACKUP_SPACES_KEY`, `BACKUP_SPACES_SECRET` | DigitalOcean Spaces | A new key pair; the backup bucket has its own, scoped to it alone (so the app's key can't delete a backup) |
| `STRIPE_SECRET`, `STRIPE_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CONNECT_WEBHOOK_SECRET` | Stripe | Roll the key in Stripe; each webhook's secret is on its endpoint |
| `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_EMAIL_TOKEN`, `MAIL_WEBHOOK_SECRET` | Cloudflare (Email Service; our Worker) | A new API token; the Worker's secret set on both sides |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET` | Cloudflare Turnstile | Rotate the widget's secret |
| `MAIL_MUDEO_FROM`, `MAIL_MUDEO_READY`, `MAIL_POSTAL_ADDRESS` (not secrets) | The mudeo email's sender (unset: hillel@stackapella.com, through Cloudflare Email Sending, already covered by SPF and DKIM), and the footer's postal address: `Stackapella, 14799 Soaring Eagle Ct, Fort Myers, FL 33912, USA` | None needed; `MAIL_MUDEO_READY=false` holds the sends back. Send the admin's test email first and check it arrives |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google Cloud console | A new client secret |
| `APPLE_*` (team, key id, private key) | Apple Developer | A new Sign in with Apple key |
| `APP_STORE_ISSUER_ID`, `APP_STORE_KEY_ID`, `APP_STORE_PRIVATE_KEY` | App Store Connect (Users and Access, Integrations: an In-App Purchase key) | Revoke the key and make a new one; the App Store Server Notifications URL is `/webhooks/app-store` |
| `GOOGLE_PLAY_SERVICE_ACCOUNT`, `GOOGLE_PLAY_PACKAGE`, `GOOGLE_PLAY_PUSH_AUDIENCE`, `GOOGLE_PLAY_PUSH_EMAIL` | Google Cloud (a service account with Play Console's financial access) and Pub/Sub (a push subscription to `/webhooks/google-play`, with authentication on) | A new key for the service account, then delete the old one |
| `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_PRIVATE_KEY`, `APNS_TOPIC`; `FCM_PROJECT_ID`, `FCM_SERVICE_ACCOUNT`; `APP_PUSH_ENABLED` | Apple Developer (a token-based APNs key, .p8); Firebase (the project, and a service account allowed to send). Push to the apps stays off until `APP_PUSH_ENABLED=true` | A new APNs key, then revoke the old in Apple Developer; a new service-account key, then delete the old |
| `GOOGLE_APP_CLIENT_IDS`, `APPLE_APP_BUNDLE_ID` (not secrets) | The apps' OAuth clients in Google Cloud (one per platform); the apps' bundle id in Apple Developer | Nothing to rotate; a client removed in Google signs nobody out (device tokens are ours) |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | `php artisan app:reminders:keys` | New keys end every reminder subscription (people opt in again) |
| `EVENT_SCHEDULE_CLIENT_ID`, `EVENT_SCHEDULE_CLIENT_SECRET` | Event Schedule's admin | A new client secret there |
| `X_API_KEY`, `X_API_SECRET`, `X_ACCESS_TOKEN`, `X_ACCESS_SECRET` | X developer portal | Regenerate the keys and tokens |
| `BLUESKY_APP_PASSWORD` | Bluesky settings (App passwords) | Revoke and make a new one |
| `SENTRY_LARAVEL_DSN`, `SENTRY_BROWSER_DSN` | Sentry | Rotate the project's keys |
| `DIGITALOCEAN_TOKEN` (CDN purge) | DigitalOcean API | A new token, scoped to the CDN |

Keep the recovery codes for DigitalOcean, Stripe, Cloudflare, Apple, Google and the domain's
registrar somewhere safe that isn't one of them.

## Who handles personal data (for the privacy policy)

| Service | What it handles | Why |
|---|---|---|
| DigitalOcean (App Platform, Managed MySQL, Spaces) | Everything we store: accounts, recordings, shared harmonies | Hosting |
| Cloudflare | Requests at the edge; Email Service (addresses, the emails) and the bounce Worker; Turnstile in shield mode (with the visitor's IP) | DDoS protection, email, bot checks |
| Stripe | Payments, cards, the buyer's name, email and billing address (Stripe Tax); coaches' Express accounts (their email and country to start) | Payments, tax and payouts |
| Google, Apple | Sign-in (an account id, a name, an address); App Store and Google Play purchases and their notices (Pub/Sub for Google), linked by a random code | Signing in; Plus bought in the apps |
| Event Schedule (when a person connects it, or signs in with it) | Sign-in; a lesson's time, the singer's name, address and notes | Signing in, booking lessons |
| Invoice Ninja (when a coach or Stackapella connects it) | A singer's name and address on the coach's invoice; Plus buyers' in Stackapella's books; coaches' on commission invoices | Bookkeeping |
| Sentry (when set up) | Errors, with the account's id and browser type, scrubbed of names, addresses, IPs and recordings | Fixing bugs |
| X, Bluesky | Only what we post (a song, or a harmony its singer said yes to, with the singer's name and their handle there) | Our own accounts |
| Browser push services (Google FCM, Mozilla, Apple, Microsoft) | An encrypted reminder, to the address the browser gave (only once someone turns reminders on) | Practice reminders |
| Apple Push Notification service and Firebase Cloud Messaging | The bell's news as a notification's words (a singer's name, a harmony's or idea's title) and an app path, to the push token the app gave (only while push is on, and for the kinds the person keeps on) | Push to the native apps |
| jsDelivr | Visitors' IP addresses, as the app's libraries load | Delivering the app's code (pinned, with SRI) |
| Google Fonts | Visitors' IP addresses, as the app's and the public pages' fonts load | The look (self-hosting the fonts would remove it) |
| DigitalOcean CDN | Requests for public files (shared harmonies, guides, avatars, choir mixes) | Serving public media |

No third-party analytics, no advertising, no tracking, no third-party scripts on our pages. The app sends anonymous first-party counts (`counts.create`: a number per day, kind, guest or account, and country, nothing that identifies anyone), shown in admin as Usage.

## The launch checklist

The full version, item by item with what's missing and each of Hillel's steps, is
[launch-checklist.md](launch-checklist.md); the security review is [security-review.md](security-review.md).

Everything in [backend-plan.md](backend-plan.md) A3, plus the outside pieces in `contract/log.md`
("Needs Hillel"). Before saying go:

- [ ] `php artisan app:doctor` passes on production, with no failures (warnings explained).
- [ ] A backup made and the restore drill passed on production.
- [ ] Sentry set up, and a test error seen in it (with nothing personal in it).
- [ ] The launch domain live, `APP_URL` set to it, its other names in `APP_REDIRECT_HOSTS`; the
      beta domain kept as staging (its own deployment).
- [ ] The private bucket's CORS rule lists the launch domain (item 21).
- [ ] Stripe live: webhooks with every event (items 22, 30, 37), Radar's rules (radar-rules.md), the tax settings.
- [ ] Email from the launch domain's sending subdomain (SPF, DKIM, DMARC), and the bounce Worker
      sending to `/webhooks/mail` with `MAIL_WEBHOOK_SECRET`.
- [ ] `security.txt` answers, and `SECURITY_CONTACT` reaches a person.
- [ ] The legal pages reviewed by the lawyer (terms, privacy, refunds, community guidelines,
      copyright, the coach agreement and the marketplace terms, the security policy).
- [ ] The abuse test (abuse-test.md) run against the beta domain, every attack stopped where it says.
- [ ] The last device pass, on every device in the operations plan.
- [ ] Hillel says go.

---

[Back: The learning exploration prompt](design-prompt-explore-learning.md) · [Docs guide](../README.md#a-guide-to-the-docs)
