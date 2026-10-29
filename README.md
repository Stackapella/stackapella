# Stackapella

Make multi-part vocal harmonies in the browser. Record one part, then record the next while you
hear the earlier ones, stacking them into a full a cappella arrangement. Most people work alone,
and others can be invited to add parts.

It's the audio-only successor to [mudeo](https://github.com/hillelcoren/mudeo).

| Folder | What |
|---|---|
| `server/` | The Laravel server: the API, the admin's sign-in, and (in production) the PWA on the same origin |
| `web/` | The app: a Vue PWA, no npm (projects are saved in the browser until the API exists) |
| `web/spike_audio/` | Throwaway audio feasibility prototype (Flutter; the app itself is Vue) |
| `docs/` | The vision, roadmap, architecture, conventions, decisions and plans |
| `contract/` | The API contract the backend and frontend agents share (`contract/api.md`), and where things stand (`contract/log.md`) |

## Status

Planning is done and the first features are built:
- the studio: record parts over each other, exact to the sample;
- the mic picker and timing check;
- harmony templates: 8 SATB drafts for 2, 3 or 4 singers, synthesized guides, and practice.

The work ahead is in [docs/roadmap.md](docs/roadmap.md): milestones M0 to M10 and launch readiness
(L) ship everything that doesn't need a network. The features that need a crowd (N1 to N7: the
gallery, the libraries' search, the coach directory, leagues, the virtual choir) open when the
admin's network features panel says they're ready. Each milestone is built by two agents at once, one on the backend and one on the frontend.

## Running it yourself

Stackapella's code is being prepared for its open-source release under the Attribution Assurance
License (`LICENSE`; [docs/open-source-plan.md](docs/open-source-plan.md)). You can run your own
install today. For working on the code, see "Running the server" and "Running the app" below.

**The app, not our site.** You may run the Stackapella web app under the AAL. Our marketing site
(its pages, words, images and the Stackapella brand) isn't part of what's licensed for you to
serve; with `IS_NEXUS` off it's switched off for you: the landing, features, pricing, about, press,
the song and singing-guide pages, open source and self-host pages and the sitemap answer 404, and
`/` opens the app. The app, shared harmonies, help, your legal pages and `security.txt` stay. Only
stackapella.com sets `IS_NEXUS=true` ([docs/branding-matrix.md](docs/branding-matrix.md)).

**What you need:**
- PHP 8.3 or 8.4 with `gd`, `intl`, `pdo_mysql`, `bcmath`, `pcntl` and `zip`; Composer. There's
  no npm.
- MySQL 8.4 (MySQL only: SQLite and MariaDB aren't supported).
- ffmpeg: takes, shared harmonies and share videos are encoded on the server.
- **HTTPS.** Browsers give a page the microphone, AudioWorklet, the service worker, passkeys and
  push notifications only in a secure context. `localhost` counts, for trying it out.
- **S3-compatible object storage** with CORS and presigned uploads (DigitalOcean Spaces, AWS S3,
  Cloudflare R2, MinIO): buckets for media, private files and backups. Uploads to the local disk
  are for development only.
- **Two background processes:** the scheduler (`php artisan schedule:work`, which also drains the
  `default` queue every minute), and a worker for the `long` queue
  (`php artisan queue:work database_long --queue=long --timeout=300`).
- Mail: any Laravel mailer (SMTP, SES, Postmark, Resend, Cloudflare).
- Our own beta runs on 1 vCPU and 1 GB for the web, 1 GB for the long worker and 0.5 GB for the
  scheduler (`.do/app.yaml`, with `.do/Dockerfile` and `.do/nginx.conf`, is a working example).

**Install:**

```bash
cd server
composer install --no-dev
cp .env.example .env              # the database, the storage buckets, mail, APP_URL, and SELF_HOSTED=true
php artisan key:generate
# set your own SQIDS_ALPHABET in .env: never the default, and never change it once links are out
php artisan migrate --force
php artisan app:templates:sync    # the songs, into the catalogue
php artisan app:doctor            # every readiness check, with its fix
```

Point your web server's root at `server/public` (the server serves the app from `web/` beside
it), and start the two processes above.

**`SELF_HOSTED=true`** (`server/config/selfhost.php`) makes it yours:
- **Everything is unlocked:** every account has every Plus feature, with no plans and no licence
  key (the limits are yours to set in `server/config/usage.php`).
- **Your own legal pages:** your terms, privacy policy and the rest, as Markdown files in
  `server/resources/legal/local/en/` (`terms.md`, `privacy.md`...; another folder with
  `SELF_HOSTED_LEGAL_PATH`). Until you write one, its page says it isn't written yet; Stackapella's
  own are never shown.
- **Your own addresses:** `SITE_CONTACT_EMAIL`, `SITE_FOUNDER`, `SITE_FOUNDER_URL`, `SITE_X_URL`
  and `SITE_BLUESKY_URL` (`none` hides it), and the mail settings for what you send from.

Each optional service (Google and Apple sign-in,
Turnstile, Sentry, web push with `php artisan app:reminders:keys`, Stripe, Event Schedule, Invoice
Ninja, X and Bluesky) stays out of the way until its keys are in `.env`.

**What a fresh install connects to:** nothing is reported to us, and there's no third-party
analytics and no licence check. The server contacts your storage and mail provider, and each optional service only once
its keys are set; practice reminders go through the browsers' own push services, only for people
who turn them on. The weekly check of Cloudflare's published IP ranges runs only behind Cloudflare
(`BEHIND_CLOUDFLARE`, off by default on a self-hosted install). Your users' browsers load Vue (and lamejs, when exporting an MP3)
from jsDelivr, each pinned to an exact version with an SRI hash, and the Figtree and Noto Music
fonts from Google Fonts.

**Content and the name:** the courses, the song arrangements, the guides and help, What's new
and the screenshots and share images are [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/)
(`CONTENT-LICENSE`): use, change and share them, commercially too, crediting "Stackapella
(https://stackapella.com)" and sharing what you make from them the same way. The courses and
songs live in their own repository, hillelcoren/stackapella-courses. The legal texts are ours:
write your own terms and privacy policy (voices are personal data). The name and the mark are
trademarks. Real singers' guide voices and the instrument samples aren't in the repo; an
install's guides use the synthesized voice, and real instruments stay off until you upload CC0
sets (`app:instruments:upload`, [docs/instrument-build.md](docs/instrument-build.md)). An
install that would rather not carry the content deletes those folders, and runs without them: no
songs until you add your own, no guides and no what's new.

**Contributing:** [CONTRIBUTING.md](CONTRIBUTING.md).

## A guide to the docs

Read them in this order. Each doc ends with **Back** and **Next** links in the same order.

**The big picture**
1. [Vision](docs/vision.md): what Stackapella is, who it's for first, the principles, and the
   launch scope.
2. [Roadmap](docs/roadmap.md): the milestones, each split into backend and frontend tracks, plus
   the things only Hillel can start.
3. [Architecture](docs/architecture.md): the device, the server, the data formats, IDs, where
   audio goes, and who owns what.

**Working on it**

4. [The backend agent](docs/agents/backend.md): what the backend agent reads, owns and counts as
   done.
5. [The frontend agent](docs/agents/frontend.md): the same, for the frontend agent.
6. [Conventions](docs/conventions.md): how the code is written.
7. [Two agents at once](docs/parallel-agents-plan.md): the API contract, git rules, and how the
   agents talk.
8. [Decisions](docs/decisions.md): every decision, newest first, with the reason.

**The features,** in roughly the order they're built

9. [Harmony templates](docs/templates-plan.md): the format, learning, and the public library.
10. [Beta singers](docs/beta-singers-plan.md): real voices for the official guides (M2), and [the beta guide](docs/beta-guide.md) for running it.
11. [Sing](docs/singing-ux-plan.md): part by part, or the whole song (M3).
12. [The backend](docs/backend-plan.md): accounts, storage, sync, the gallery, posting, billing and
    operations (M1 to L, then N1 to N7).
13. [Trust and safety](docs/trust-and-safety-plan.md): spam and abuse handled from day one: trust
    levels, limits, admin controls and the switches for an attack.
14. [Operations](docs/operations-plan.md): support, the Editor role, Hillel's week, keeping songs
    safe, the cost model at $4.99, practice reminders, devices and security housekeeping.
15. [Becoming a better singer](docs/vocal-coaching-plan.md): the course, like Simply Piano (lit
    notes, Wait for me, stars, XP, badges, today's workout) and the singing feedback (M3, M5).
16. [Pricing](docs/pricing-plan.md): Free and Plus, the limits, and the billing rules; [what each
    plan gets](docs/features.md), kept true by the claims test.
17. [Go to market](docs/go-to-market-plan.md): how beginners find it: song pages, the share loop,
    communities, the launch week, and attribution without analytics.
18. [The template creator](docs/template-creator-plan.md): sing it, or write it (M7).
19. [More features](docs/more-features-plan.md): Make it sound good, chords, share videos, intervals,
    slow practice, re-sing a phrase, practice tracks, what's next (at launch); open parts, the
    virtual choir, the harmony gym, song requests (when ready).
20. [The coaching marketplace](docs/coaching-marketplace-plan.md): coaches and take reviews (M8; the
    directory when ready).
21. [Event Schedule](docs/event-schedule-integration-plan.md): lessons and choirs (M9).
22. [Invoice Ninja](docs/invoice-ninja-integration-plan.md): bookkeeping (M10).

**Reference**

23. [The audio spike](docs/audio-spike.md): how the timing was proven, and the device runs still to
    do (M0).
25. [The design prompt](docs/design-prompt.md): the first prompt sent to Claude Design
    (historical).
26. [The learning exploration prompt](docs/design-prompt-explore-learning.md): the second one
    (historical).
27. [The runbook](docs/runbook.md): running it day to day, the playbook, backups and restoring,
    the scheduled jobs, the secrets, who handles personal data, and the launch checklist (L).
29. [Instrument parts](docs/instrument-parts-plan.md): free part keys, voice or instrument, up to 6
    parts in people's own templates, and "Sung by" or "Played by" (a contract proposal).
30. [Buying Plus in the apps](docs/app-purchases-plan.md): Apple's and Google's billing, the rules on
    pointing to the web, fees, and a recommendation (for Hillel to decide).
32. [The security review](docs/security-review.md): the pre-launch review of the whole server,
    what was found and fixed, and what's left.
33. [The launch checklist](docs/launch-checklist.md): go or no-go on 30 November, item by item, with
    each of Hillel's steps.
34. [The privacy audit](docs/privacy-audit.md): every claim in the privacy policy checked against
    the code, what was fixed, and what's left for the lawyer.

## Running the server

You need PHP 8.3 or 8.4 and Composer (Herd's are fine), and MySQL 8.4 (DBngin, or any local MySQL).
There's no npm, ever.

1. Make a database: `CREATE DATABASE stackapella CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
   (the tests make their own, named from `DB_TEST_DATABASE`).
2. Set up the server:
   ```bash
   cd server
   composer install
   cp .env.example .env            # then set DB_PORT, DB_USERNAME and DB_PASSWORD for your MySQL
   # Hillel's Mac: the dev database is its own mysqld on 3307; after a restart run sh tool/dev-mysql.sh
   php artisan key:generate
   php artisan migrate
   php artisan db:seed             # the demo admin, editor, beta singers and accounts
   ```
3. Turn on the commit hook once per clone: `git config core.hooksPath .githooks`.
4. Fetch the courses and songs, which live in their own repo
   ([hillelcoren/stackapella-courses](https://github.com/hillelcoren/stackapella-courses)), at the
   commit `.do/content.lock` pins: `sh server/tool/content.sh link` (from the repo root; again after
   a pull that changes the lock). To try your own changes to them, point it at your checkout:
   `CONTENT_DIR=~/Code/content-<you> sh server/tool/content.sh link` (it copies, so run it again
   after a change).
4. For `/dev/login` to sign you in as the demo admin, `.env` needs the demo lists:
   `ADMIN_GOOGLE_IDS=dev-admin`, `ADMIN_EMAILS=hillel@example.com`, `EDITOR_GOOGLE_IDS=dev-editor`,
   `EDITOR_EMAILS=editor@example.com`.

Everyday commands, from `server/`:

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8000 tool/serve.php   # the API, the admin's sign-in and the PWA
php artisan test --compact        # PHPUnit on MySQL (its own database per session)
vendor/bin/pint                   # code style
vendor/bin/phpstan analyse        # Larastan, level 8
php artisan app:flag              # the switches for an attack and the dark features
```

- **Signing in to the admin locally:** open http://127.0.0.1:8000/dev/login and pick the demo admin.
  It stands in for Google and the second factor, and only exists when `APP_ENV=local`.
- **Beta singers (M2):** `/beta/demo-singer-link-for-the-flow-scripts-0001` is Nora's open invite (sign
  up with it); Sarah has accepted hers: `sarah@example.com`, password `demo-password-for-the-flows`.
- **Demo accounts (M3):** `ada@example.com` with the password `demo-password-for-the-flows`; Una
  (`una@example.com`) hasn't verified, and `/verify?token=demo-verify-link-for-the-flow-scripts-0001`
  verifies her. Emails go to the log locally (`MAIL_MAILER=log`), links and all.
- **Cloud save (M4):** Ada has a demo project (`5b3a9c2e-1f4d-4a6b-8c7e-2d9f0a1b3c4e`) with a recorded
  alto and a take in the bin. Takes become FLAC on the `long` queue (keep `php artisan queue:work
  --queue=long,default` running), and `php artisan app:bin:purge` empties the bin (daily in
  production).
- **Plus (M5):** `plus@example.com` (password `demo-password-for-the-flows`) has Plus without Stripe;
  Silent Night is the demo's song of the month. Billing runs against Stripe's test mode only
  (`STRIPE_*` in `.env`); the webhook is `/webhooks/stripe` (`stripe listen --forward-to
  127.0.0.1:8000/webhooks/stripe` locally). Practice reminders need VAPID keys
  (`php artisan app:reminders:keys`, once).
- **Sharing (M6):** Ada's Amazing Grace is shared by link (`GET /api/harmonies` as Ada gives its id;
  its page is `/h/<id>`), and one of Pia's waits in the admin's reports queue, hidden by 3 reports.
  Mixes, cards and share videos are made on the `long` queue with ffmpeg and GD (the font is in
  `server/resources/fonts`); `php artisan app:sharing:prune` clears unfinished uploads and expired
  videos (hourly in production).
- **Together (M7):** Ada has her own template (unlisted, `a7d0c3b1-2e4f-4a5b-8c6d-7e8f9a0b1c2d`);
  Sarah sings the tenor on Ada's demo project; `/join/demo-project-invite-for-the-flows-0001` is an
  open invite to it; Pia directs "Thursday Singers (demo)" with Ada in it, assigned the alto she has
  recorded (`/choir/demo-choir-link-for-the-flow-script` joins it).
- **Coaching (M8):** Grace (`grace@example.com`, `demo-password-for-the-flows`) is an approved coach
  with a review, a monthly package and a free pack (`/coach/grace_coaches`); she has delivered a
  review to Ada; Ben's application waits in the admin's queue. Buying needs Stripe's test keys
  (`STRIPE_*`) and, for coaches' accounts, Connect's webhook (`STRIPE_CONNECT_WEBHOOK_SECRET`;
  `stripe listen --forward-connect-to 127.0.0.1:8000/webhooks/stripe-connect`). The deadlines run
  every five minutes (`php artisan app:coaching:deadlines`).
- **Event Schedule (M9):** with `EVENT_SCHEDULE_FAKE=true` in `.env` (locally only) the server uses
  its fake Event Schedule: Connect Event Schedule comes straight back to
  `/connect/event-schedule/callback`, a selfhosted key for it starts with `es-`, and `php artisan
  db:seed` connects Grace (a 45-minute lesson on sale, booked by Ada in 3 days) and Pia (her choir
  linked to the Riverside Choir schedule, Amazing Grace picked for the next rehearsal). Play ES's
  side with `php artisan app:event-schedule:fake <engagement id> cancelled|completed|rescheduled
  --by=owner|guest`. The real one needs `EVENT_SCHEDULE_CLIENT_ID` and `_SECRET` from ES's admin.
  The hourly reconcile is `php artisan app:event-schedule:reconcile`; Hillel's health screen is
  `admin.eventSchedule.show`.
- **Books, posts and Sign in with Event Schedule (M10):** with `INVOICE_NINJA_FAKE=true` in `.env`
  (locally only) Invoice Ninja is faked (a token starting `in-` is accepted), and `php artisan
  db:seed` connects Grace's books (her sales recorded) and Stackapella's own (so admin can invoice an
  account for Plus). The commission invoices run on the 1st (`php artisan app:books:commission`).
  Our X and Bluesky posts need `X_*` and `BLUESKY_*`, wait for approval in admin, and go out only
  in production (`app:social:send` every minute, `app:social:month` each morning). Sign in with
  Event Schedule uses the same fake as M9.
- **Launch readiness (L):** `php artisan app:doctor` shows every readiness check with its fix;
  backups are `app:backup:run` and `app:backup:drill` (`MYSQL_CLIENT_PATH` if mysqldump isn't on
  the PATH). The demo has a question from Nora waiting in admin's Support, and Ada's shared harmony
  opted into the gallery (the network panel counts it). `php artisan app:stats:nightly` counts each
  song's finishers and the network panel (nightly in production). The songs, guides and legal
  pages are `/songs`, `/guides/<slug>` and `/legal/<page>`; nothing is indexed until
  `SITE_INDEXING=true` (at launch, on the launch domain only).
- **Sign in with Google or Apple** needs `GOOGLE_CLIENT_*` and `APPLE_*` in `.env`; shield mode needs
  `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET` (`php artisan app:flag shield_mode on --reason="..."`).
- **Signing in for real** (on the beta domain): `/admin/sign-in`, Google, then a passkey or an
  authenticator app. **The first time:** put your email in `ADMIN_EMAILS` and sign in once; the
  refusal shows your Google ID (also in the trust and safety screen's security events). Add it to
  `ADMIN_GOOGLE_IDS`, sign in again (that makes your account), then give yourself the role from the
  App Platform console: `php artisan app:admin:grant <your Google ID>`. Lost every factor?
  `php artisan app:admin:reset-second-factor <your Google ID>`.
- **The contract** (`contract/`): every API route is an operation in `contract/api.md`, and the
  tests check responses against its schemas (`assertMatchesContract`).
- **Deploying** is Hillel's: `.do/app.yaml` (used once) and `.do/Dockerfile`, which builds the
  server and the PWA into one image from the repository's root. To try the image locally:
  `docker build -f .do/Dockerfile -t stackapella .` from the repository's root.

## Running the app

You need PHP (Herd's is fine), and Chrome for the tests. There's nothing to install: no npm.

```bash
cd web
tool/tailwind.sh                     # builds css/app.css (the first run downloads the pinned Tailwind binary)
php -S localhost:8081 tool/serve.php
# open http://localhost:8081 (localhost counts as secure, so the mic works)

# with the real API (Laravel on :8000, see the server's README):
API=http://127.0.0.1:8000 PHP_CLI_SERVER_WORKERS=4 php -S localhost:8081 tool/serve.php
```

- After changing Tailwind classes, run `tool/tailwind.sh` again, or `tool/tailwind.sh --watch`.
  `css/app.css` is committed.
- `tool/test.sh` runs the tests in muted headless Chrome: the timing maths, the audio engine over
  loopback (exact to the sample), the pitch tracker and scoring, the stores, the contract, and the
  checks (words, the offline list, only `api.js` calling `/api`).
- `tool/flows.sh` runs the flow scripts (`tests/flows/*.py`): whole journeys through the app at
  phone and desktop size, with screenshots in `tests/flows/shots/`. With
  `API=http://127.0.0.1:8000` in front, they run against the real server.
- **How to add a test, a flow script or a mock scenario:** [web/tests/README.md](web/tests/README.md).
- **The mock API:** add `?api=mock` to any address on this machine, and `?scenario=` for a
  refusal (`?scenario=rate_limited`, or `?scenario=admin.flags.update:sudo_required` for one
  call). It answers from `contract/`'s examples.
- **The admin** is at http://localhost:8081/admin: with the real API, sign in at `/dev/login` (local
  only); with `?api=mock`, the mock signs you in. **The styleguide** is at /styleguide.
- http://localhost:8081/tests/seed.html makes a demo project with six generated parts. It counts
  toward the 3 free projects.
- To try it on an iPhone, the page must be served over HTTPS: keep the server running and run
  `expose share http://localhost:8081` (Expose ships with Herd), then open the `https://` address
  in Safari. To install it, use Share, then Add to Home Screen.

Projects live in the browser (IndexedDB) until the backend exists, so each browser and device
has its own.

### Words and languages

Every word the app shows comes from `web/locales/en.json` through `t()` (`web/js/i18n.js`).
- **To add or change words,** edit `en.json`, grouped by screen (`studio.record`). `tool/test.sh`
  fails if the code uses a key that isn't there, if a key is unused, or if a template or toast
  holds a literal sentence.
- **To add a language,** drop in `web/locales/<lang>.json` with the same keys (and add it to the
  offline list in `web/sw.js`). The app picks it from the browser, and the check makes sure its
  `{arguments}` match English.
- **To see what isn't translated,** open http://localhost:8081/?lang=pseudo: every word is
  accented and 40% longer, so plain English stands out. `tool/flows.sh pseudo_language` checks
  each screen this way. Song and user content is marked `translate="no"`.

### Harmony templates

**Learn a harmony** on the projects page lists ready-made arrangements (`web/templates/*.json`):
- Each part has a guide, synthesized from its notes in the browser.
- **Practice** a part: Listen, Sing along, On your own, then Record. Your take replaces the guide.
- Loop a phrase, or use "Listen, then repeat".

The official templates are drafts until a musician checks them. Drafts show when the app runs on
your own machine (localhost), or with `?drafts=1`, but never on the live site.
docs/templates-review.md explains the review.

To add a template (in the courses repo, which `web/templates` and `server/database/templates`
come from; see its README):
1. Write `templates/free/<id>.json` (a song the app bundles) or `templates/library/<id>.json` there,
   in the format in [docs/templates-plan.md](docs/templates-plan.md), with times in beats from the
   first pickup beat and pitches as MIDI note numbers.
2. For a bundled song, add its id to `templates/free/index.json`, and add it to the offline list in
   `web/sw.js` here.
3. Run `tool/test.sh`: the loader test refuses a broken file and says why.

### Regenerating the site's app screenshots

The user guide and the public site show the same real screens of the app, each as a pair (light and
dark) in png and webp: `web/icons/help/<id>.png`, `<id>-dark.png` and the same in `.webp`. The list
of shots, with each one's screen, exact state and size, is
[docs/user-guide-shots.md](docs/user-guide-shots.md). To make them all again, on the mock API:

```bash
cd web
PORT=8410 tool/flows.sh help_shots                          # every shot, both themes
ONLY=studio--mixer,your-voice--map PORT=8410 tool/flows.sh help_shots   # just these
```

They're written straight to `web/icons/help/`, so the guide and the site pick them up on the next
deploy. A page shows a shot only once all four files are there. On the site, a page's head names one
with `shot` (and `shot_alt`) and a section with `'pic' => 'shot:<id>'` in `server/lang/en/site.php`;
`components/site/appshot.blade.php` shows the light or dark one to match the visitor's theme.

## Running the audio spike

The spike is a one-screen Flutter app that proves recording, sync and latency compensation in each
browser. It needs the Flutter SDK (3.47.7) and PHP (Herd's is fine).

```bash
cd web/spike_audio
flutter test                    # the DSP maths
tool/selftest.sh                # builds, then runs the loopback self-test in headless Chrome (muted)
```

To try it yourself on this Mac (Chrome, Safari, Firefox):

```bash
cd web/spike_audio
flutter build web --release --no-web-resources-cdn --csp -o build/web
PHP_CLI_SERVER_WORKERS=4 php -d post_max_size=200M -d memory_limit=512M -S 0.0.0.0:8080 tool/serve.php
# open http://localhost:8080 (localhost counts as secure, so the mic works)
# every recording (WAV) and the results (JSON) are saved to build/sessions/<session>/
```

- For the skwasm (Wasm) build, use `-o build/web-wasm` with `--wasm` added, and start the server with
  `BUILD=build/web-wasm`.
- Add `ISOLATE=1` to send the COOP/COEP headers.

On an iPhone the page must be served over HTTPS. Keep the server running and, in a second
terminal, run `expose share http://localhost:8080` (Expose ships with Herd). Then open the
`https://` address it prints in Safari on the phone. Expose may ask you to sign in first; a free
account is enough.

What to do on the page, and what to send back, is in [docs/audio-spike.md](docs/audio-spike.md).
