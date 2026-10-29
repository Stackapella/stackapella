# The backend plan: every server piece, in order

**Status:** planned, not started (2026-10-26). Sections F (coaching) and G (Event Schedule) live in
[coaching-marketplace-plan.md](coaching-marketplace-plan.md) and
[event-schedule-integration-plan.md](event-schedule-integration-plan.md), and the Invoice Ninja
integration in [invoice-ninja-integration-plan.md](invoice-ninja-integration-plan.md). The open decisions, review,
order of work and verification below cover all three.

## Context

`/server` doesn't exist yet: the app is frontend-first, with projects in IndexedDB. Every plan so
far has named backend pieces in passing:
- pricing: accounts, cloud save, limits, Cashier;
- templates: versions, publishing, moderation;
- beta singers;
- coaching: Send to my teacher;
- the two-agent plan: the API contract.

This plan gathers them, then adds what Hillel asked for:
- song storage, worked out properly;
- the server side of **every** planned feature;
- social posting and a public gallery;
- **a coaching marketplace**, where coaches sell their services in the app and review takes in
  it;
- **an integration with Event Schedule**, so both apps work together. Live lessons are booked
  through Event Schedule's appointments, not rebuilt here;
- **an integration with Invoice Ninja,** which keeps the books for coaches' sales and for
  Stackapella's own.

**How it's built:**
- the work is ordered in milestones in [roadmap.md](roadmap.md), each split into backend and
  frontend tracks with the contract agreed first (`docs/parallel-agents-plan.md`);
- it follows `docs/conventions.md`: Laravel 13, PHP 8.4, MySQL 8.4, Actions, FormRequests, API
  Resources, Sqids, PHPUnit with QueryGuard and architecture tests, Pint, Larastan level 8, App
  Platform and Spaces;
- **iaw** is the main reference: social posting, admin, the second factor, Spaces, backups,
  readiness and share cards. **Event Schedule (ES)** is the reference for Cashier, Stripe Connect
  onboarding, data export and deletion, and it's the other half of the integration.

**Hillel's answers (2026-10-26):**
- **X and Bluesky:** our own accounts post new templates and featured harmonies (iaw's pattern).
  Users share their own harmonies with links and pre-filled posts, without connecting accounts.
- **A public gallery:** harmonies, profiles, likes, comments, follows and reports. Unlisted links
  too.
- **Sign-in:** Google, email with a password, and **Sign in with Apple**.
- **Billing:** Stripe with Cashier and Stripe Tax (closes pricing #1).
- **Coaching:**
  - what coaches offer: **take reviews**, **live lessons**, **ongoing coaching** and **exercise
    packs**;
  - **money:** Stripe Connect, with a cut for Stackapella;
  - **coaches** are **approved by Hillel, then rated** by singers after a paid session.
- **Live lessons and appointments:** a seamless integration with Event Schedule, not built in the
  app. More broadly, **an integration so people can use both apps together.**
- **Song storage:** current takes, plus a **30-day bin**.
- **Audience and launch** (later the same day): beginners first, and **everything that doesn't need
  a network at launch**: the core, the course, sharing, the template creator (without the public
  library), collaboration, choir groups, coaching (without the directory), Event Schedule and
  Invoice Ninja. Features that need strangers to find each other wait, and the admin's network
  features panel says when ([vision.md](vision.md), [roadmap.md](roadmap.md)).

**IDs, the public pages' ownership, and the data formats** are settled in
[architecture.md](architecture.md), which wins where this plan differs.

## Dependencies (each needs Hillel's OK before it's added)

**Stackapella (`/server`):**

| Package | For | Why not hand-written |
|---|---|---|
| `laravel/socialite` | Google sign-in | First-party; iaw and ES use it |
| `socialiteproviders/apple` | Sign in with Apple | Apple's client secret is an ES256 JWT, and its ID token needs JWKS checks: security code best not hand-rolled |
| `league/flysystem-aws-s3-v3` | Spaces disks, presigned uploads | Laravel's S3 driver; iaw uses it |
| `sqids/sqids` | Public IDs | The decided ID scheme (ES) |
| `laravel/passkeys`, `pragmarx/google2fa`, `bacon/bacon-qr-code` | The admin second factor | As iaw's `SecondFactor` |
| `laravel/cashier` | Plus subscriptions | As ES. It brings `stripe/stripe-php`, which Connect (coaching) uses too |
| `sentry/sentry-laravel` | Errors and cron monitors, before launch | Already "revisit before launch" |
| `minishlink/web-push` | Opt-in practice reminders (`docs/operations-plan.md`) | Web Push's payload encryption is security code best not hand-rolled |

**System:** `ffmpeg` through the `Aptfile` (FLAC, AAC, loudness, probing).

**Not needed:**
- X, Bluesky, Event Schedule and Invoice Ninja clients are raw `Http` calls (iaw's style);
- share cards use GD directly;
- Turnstile is built in M3 but stays off until "shield mode" is switched on during an attack
  (`docs/trust-and-safety-plan.md`).

**Event Schedule:** changes listed in section G. The one possible new dependency there is an
OAuth server (`laravel/passport`, or a small one of our own), decided in that repo.

---

## A. Foundation, admin and operations

### A1. Foundation
- **The scaffold, to the conventions:**
  - `bootstrap/app.php` middleware and named limiters;
  - `/up` (iaw `HealthController`), stderr logs;
  - `Model::shouldBeStrict()` and `enforceMorphMap`.
- **Tests:**
  - the per-session MySQL schema (iaw `tests/TestDatabase.php`);
  - QueryGuard and its baseline;
  - `ArchitectureTest` (the Never list);
  - the contract checker (`tests/Contract`).
- **CI** (iaw `.github/workflows/test.yml`): MySQL 8.4 strict, Pint, Larastan, `composer audit`,
  the tests, the "no npm" check, and the web job.
- **Hosting:**
  - `.do/app.yaml` with web, scheduler and migrate, as iaw: Managed MySQL, and the database
    queue, cache and sessions;
  - a **`long` queue** for ffmpeg work (iaw `database_long`) **on its own resident worker
    component** (2 processes, a 300-second timeout), so heavy jobs never block mail and light jobs
    (`docs/conventions.md`, "Two queues");
  - `.do/nginx.conf`, and the `Aptfile`.
- **One origin:**
  - Laravel serves the PWA's files and `/api`;
  - **Blade renders the public pages** (section D), so crawlers and social apps see real HTML;
  - app routes (`/`, `/p/*`, `/sing`, `/coach/*`, `/admin`) return the PWA's `index.html`.
- **The client's real IP:** trusted proxies for Cloudflare and App Platform, so limits and
  `security_events` see the real client (`docs/trust-and-safety-plan.md`, "The limits").
- **Security headers:** iaw `SecurityHeaders`:
  - HSTS, `X-Frame-Options`, and `Permissions-Policy` (the microphone for our origin only);
  - `'unsafe-eval'` (for the Vue compiler) is on app pages only, never on public pages;
  - `frame-ancestors` opens only for the embeddable player (section G).
- **Sessions:** the database driver, encrypted, secure, `SameSite=lax`, CSRF through `XSRF-TOKEN`.
  Visitors to public pages get no cookie (iaw `DropAnonymousCookies`).
- **An append-only `audit_logs`** for every admin, moderation and money action.
- **Working on a laptop:**
  - the local disks stand in for Spaces;
  - a signed local `PUT /dev/upload/{key}` route stands in for presigned uploads, so the upload
    flow is the same everywhere;
  - `/dev/login`;
  - demo seeders for each slice.
- **Stale app versions:**
  - an installed PWA can be days old, so the API only changes additively (the contract rule);
  - the app sends `X-App-Version`, and the server answers `426` with "Update the app" for a
    version it no longer supports;
  - the app shows "A new version is ready" when its service worker updates.
- **Feature flags** (iaw `DeployFlags`, `app:flag`): the gallery, coaching and the ES
  integration can ship dark and be turned on per person.

### A2. Admin
- **The gate:** iaw `config/admin.php` and `AdminGate`:
  - the DB flag, the Google ID allowlist, and a verified email from a fresh sign-in;
  - a remember-me restore never grants admin;
  - idle and maximum lifetimes, and the session tied to the browser.
- **The second factor and sudo:**
  - passkeys, then TOTP with recovery codes (iaw `SecondFactor`);
  - `EnsureSudo` for destructive and money actions;
  - `app:reset-second-factor`.
- **Screens** (in the PWA at `/admin`, every route checked on the server). **They look like iaw's
  admin, not Event Schedule's** (`docs/conventions.md`, "Admin screens and forms look like iaw"):
  - overview;
  - beta review;
  - moderation (reports and held items);
  - people (suspend, behind sudo);
  - **trust and safety:** the queue, a person's page with linked accounts, the blocklist, and the
    switches for an attack (`docs/trust-and-safety-plan.md`);
  - **coach applications;**
  - **coaching disputes and refunds;**
  - featured;
  - social posts;
  - feedback and support;
  - **integration health** (ES connections and webhooks);
  - **network features:** each feature that needs a crowd, its numbers against its threshold, the
    opt-ins collected, and its status (Collecting, Start building, Ready to open, Open), recounted
    nightly by `NetworkReadiness` (`docs/roadmap.md`);
  - audit.
- **A daily digest to Hillel** (iaw `AdminDigest`): reports, coach applications, overdue reviews,
  disputes, failed posts, failed jobs and webhooks.

### A3. Operations and launch readiness
- **Sentry** with `ScrubEvent` (no audio, no personal data), cron monitors, and the browser error
  tunnel (iaw `ErrorTunnelController`).
- **Readiness checks** (`app:readiness --alert`, `app:doctor`, as iaw): env, mail, storage,
  social, Stripe, Stripe Connect, and Event Schedule.
- **Backups** (iaw `BackupRun`):
  - nightly database dumps (30 daily, 12 monthly);
  - **versioning on the `private` bucket** (the users' takes) as well as `media`, with old versions
    kept 30 days;
  - a weekly copy to `backup`;
  - a **restore drill before launch** (iaw `Scrubber`).
- **Scheduled jobs**, each named and `withoutOverlapping`:
  - queue draining;
  - **the bin purge** (section C);
  - orphaned uploads;
  - expired exports;
  - **coaching deadlines** (section F);
  - **the ES reconcile** (section G);
  - rejected beta masters after 90 days;
  - pruning, the heartbeat and the digest.
- **Support:** a "Contact us" form (a honeypot and a limit) into the admin inbox, where it sits with
  beta feedback.
- **Legal pages before launch:** terms, privacy, refunds, community guidelines, copyright (DMCA),
  **the coach agreement and the marketplace terms**. A lawyer reviews them with the beta
  agreement.

---

## B. Accounts and sign-in

- **Google:** iaw `LoginController` and `SignInWithGoogle`:
  - `prompt=select_account`, `email_verified` required;
  - matched on `google_id` only;
  - the session invalidated and regenerated.
- **Sign in with Apple:**
  - matched on Apple's `sub`, and the name saved from the first sign-in (Apple sends it only
    once);
  - the sending domain is **registered with Apple's private relay**, or mail to Hide My
    Email addresses bounces;
  - it needs a paid Apple developer account.
- **Email and password:** hashing, verification, reset (Cloudflare Email Service), the sign-up honeypot, and the
  named limiters.
- **Linking methods:**
  - any of Google, Apple and a password, added from Settings;
  - **never merged by email automatically**: a merge by email is an account takeover;
  - the last method can't be removed.
- **The profile:**
  - name, a **handle** (reserved after deletion, as iaw), an avatar (GD), a bio;
  - optional X and Bluesky handles (to credit people in our posts);
  - a time zone (for lessons).
- **Language:** `users.locale`, from the browser at first and then Settings, used for emails and
  notifications (English only at launch; `docs/architecture.md`, "Languages").
- **Age:** confirmed at sign-up, at least 13 (the lawyer confirms the EU and UK ages). Coaching has
  its own rule (section F).
- **Settings:**
  - email (re-verified on change), sign-in methods, password;
  - plan and usage;
  - **notifications**, per kind;
  - **connected apps** (Event Schedule);
  - **Download my data:** queued, a signed link for 7 days (ES `ExportPersonalData`). It includes
    links to every take, harmony and coaching review;
  - **Delete my account** (iaw `DeleteAccount`, ES `AccountDeletionService`):
    - recordings go through the bin purge, and harmonies are taken down;
    - comments are shown as "deleted", and coaching ratings stay but anonymised;
    - the Cashier subscription is cancelled;
    - a coach's pending payouts are settled first, and open engagements must be finished or
      refunded before a coach can delete;
  - "Sign out everywhere".

---

## C. Song storage, cloud save and sync

### C1. What's stored, and where

| What | Format | Size (3-minute part) | Bucket | Lifetime |
|---|---|---|---|---|
| A take (the user's recording) | **FLAC**, 16-bit mono, from the uploaded WAV | about 9 MB (the WAV is 17 MB) | `private` | While current, then 30 days in the bin |
| A part's outline (peaks) | JSON | about 10 KB | the database | With the take |
| A published harmony's mix | AAC 128 kbps, MP4 with the edit list | about 2.8 MB | `media` (CDN) | While published |
| Official guides | AAC (beta plan) | about 2.8 MB | `media` | Per template version |
| A creator's own recorded guides | FLAC | about 9 MB | `private` | With the template |
| Coach voice notes, exercise audio | AAC | about 0.5 MB a minute | `private` | With the review or pack |
| Share cards, avatars | JPEG | under 300 KB | `media` | Replaced on change |
| Exports, data downloads | ZIP | varies | `private` `tmp/` | 7 days |

**Keys and URLs:**
- keys are random and immutable (`takes/<random>.flac`), and a changed file gets a new key;
- `media` files are cached as immutable;
- `private` files are only reached by `temporaryUrl()`, valid for 15 minutes.

**Limits:**
- a take is at most 10 minutes;
- an upload is checked against its declared size and **SHA-256**;
- **ffmpeg probes it**, so anything that isn't the audio it claims to be is refused.

**Costs:** Spaces is about $5 a month for 250 GB, then $0.02 per GB. At about 9 MB a part, 100 GB
holds about 11,000 three-minute parts. The storage allowances (pricing #4) are set from real
numbers once cloud save runs.

### C2. Tables
- `projects`:
  - owner, title, bpm, key, time signature;
  - `template_version_id`, `parent_id` (remixes);
  - `settings` (JSON: practice progress from the singing UX, the views);
  - `version`, `binned_at`.
- `parts`:
  - project, order, name, colour;
  - `offset_frames`, volume, muted, included, `template_part_key`;
  - `current_take_id`, `version`.
- `takes`:
  - the owner, the key, sample rate, frames, `bytes`, peaks, SHA-256;
  - **`binned_at`**;
  - takes are shared by remixes, so each take has a reference count.

### C3. The bin (Hillel's answer)
- **A replaced or deleted take** goes to the bin for 30 days: Undo works across devices, and
  "Recently deleted" can restore it.
- **A deleted project** goes to "Recently deleted" for 30 days, with all its takes.
- **The bin doesn't count toward the storage allowance,** so deleting to make room works at once.
- **The purge job** removes binned rows after 30 days. A file is deleted only when **no project
  references it** (remixes share takes).
- **This replaces "hard deletes" for audio and projects** (logged as a decision). Other rows stay
  hard-deleted.

### C4. Uploads
- `POST /api/parts/{id}/takes` returns a presigned PUT to `private`, signed for the exact size.
  **"Resumable" means retried, not byte-resumed:** a presigned PUT is all or nothing, so a failed
  upload stays in the device's outbox and is sent again from the start when the connection is
  back. A 3-minute take is about 17 MB as a 16-bit WAV. S3 multipart uploads come only if real use
  shows failures that retries don't fix.
- `.../complete` checks the upload. `ProcessTake` (on the `long` queue) then probes it, makes the
  FLAC, works out the peaks, sets `bytes`, and makes it the part's current take.
- **Until it's confirmed,** the take stays in IndexedDB, marked "safe on this device".
- **Orphaned uploads** (presigned but never completed) are deleted after a day (`tmp/` expiry).

### C5. Sync, the device first
- **IndexedDB stays the working copy.** `web/js/db.js` gains a sync layer and an outbox, so it works
  offline.
- **Each change carries its `version`.** A stale one gets a **409**, and the app asks per part in
  plain words ("Changed on another device: Keep mine or Use theirs").
- **Takes are immutable,** so audio never conflicts, only settings.
- **The device cache:** projects opened in the last 30 days keep their audio on the device.
  Others download when opened.
- **On first sign-in, browser projects move into the account.** Over the limit, the extras stay on
  the device, marked "not saved to your account" (pricing plan).

### C6. Plans and limits (pricing plan, Phase 2)
- **The `Plans` class** follows the ownership rule. It reads a `plan` column now, and Cashier
  later.
- **`config/usage.php`:** project limit, storage (free and Plus), invites, private templates.
- **The checks live in the Actions:**
  - each refusal is a 403 with its `code`;
  - projects over the limit become read-only, and nothing is deleted;
  - there's a `docs/features.md` claims test.

### C7. Collaboration
- **`project_user`** gives roles (owner, singer). Invite links have a hashed token and can be
  revoked, with the invite limit.
- **What each can do:** a singer adds and replaces their own parts; the owner can change anything.
- **Leaving** asks whether to keep the person's parts.
- **Remixes** copy a public harmony with `parent_id`, a credit back, and shared takes.
- **No real-time editing:** a project refreshes when it's opened, and on focus.

---

## D. Every feature's server side

### D1. Harmony templates (`docs/templates-plan.md`)
- **Tables:** `templates`, `template_versions` (immutable), `template_guides`, `template_ratings`,
  `template_reports`, plus a use count.
- **The catalogue** (`GET /api/templates`):
  - it arrives in the beta phase, seeded from `web/templates`;
  - it's cacheable;
  - it lists every song with `access: free | plus` and `freeUntil` (the song of the month);
  - the bundled files are the offline fallback.
- **Plus songs** (M5, `docs/pricing-plan.md`):
  - only the 4 free songs stay bundled in `web/templates`;
  - a Plus song's JSON comes from `GET /api/templates/{id}`, for Plus accounts (and everyone during
    its month), with `plus_song` refused otherwise;
  - its recorded guides sit at unguessable immutable keys, given out by the same call;
  - **the preview clip** (20 to 30 seconds, a finished mix, AAC on `media`) is public, for the
    catalogue, the in-app preview and the song pages:
    - Hillel makes it in admin with **Make the preview** (the app renders the chosen section with
      the current guides, real voices or synth, using the engine's `render`, and uploads it), so
      there's still one mixer;
    - it's made again when a song's guides change;
  - **where the template files live:**
    - until M5, all 8 stay in `web/templates`;
    - in M5 the Plus songs move to `server/database/templates/` (seeded into `template_versions`),
      and only the 4 free songs stay in `web`;
    - **the schema test runs over both folders,** so all the official templates are checked on both
      sides from M1 on.
- **One template format, checked the same on both sides.** The template JSON gets a JSON Schema in
  `/contract`, so the PHP check and the app's check (`web/js/templates.js`) can't disagree.
- **Visibility:** private, unlisted (1 free, unlimited on Plus) and public. Editing makes a new
  version.
- **Public publishing:** the licence declaration, a review hold on a creator's first templates,
  Report, takedown, and limits (Phase 3).

### D2. The template creator (`docs/template-creator-plan.md`)
- **Device templates and drafts** sync to the account as private templates, and count toward the
  private limit.
- **The creator's own recorded guide** uploads like a take (Plus, `private`).
- **Import:** a `.json` file is checked against the template schema on the server too.

### D3. The singing UX (`docs/singing-ux-plan.md`)
- **Practice progress** (`project.practice.progress`) travels in `projects.settings`.
- **The learning path's progress** syncs as `account.learning`, a small JSON on the user
  (`docs/more-features-plan.md`, feature 2).
- Nothing else is needed on the server.

### D4. Vocal coaching, automated (`docs/vocal-coaching-plan.md`)
- **Plus history syncs:** a small `take_scores` table (per take: the summary scores and the phrase
  results) and the warm-ups log. Without it, a Plus user changing phones would lose the history
  they pay for.
- **Pitch tracks stay on the device,** except when a take is sent to a coach (section F).
- **Tips and exercises** stay as static data in the repo, with their review gate.

### D5. Beta singers (`docs/beta-singers-plan.md`)
- **As planned.** It's the first slice after the foundation and admin.
- **One change to carry in:** the singers' licence also covers their guides heard in harmonies
  people make and share (D6). Otherwise a shared harmony with a beta voice in it isn't covered.

### D6. The public gallery and sharing
- **Publishing a harmony:**
  - the mix is **rendered on the device** (the engine's `render`, the same as Export), and
    uploaded;
  - `ProcessHarmony` makes the AAC, the peaks and the share card;
  - **the server never remixes parts:** two mixers would drift. The one exception is the virtual
    choir's mix (`docs/more-features-plan.md`, feature 6);
  - **Share as a video** (`RenderShareVideo`, feature 4) makes a vertical MP4 from the mix, with
    ffmpeg.
- **Visibility:** private (the default), **unlisted** (a link) or **public** (the gallery). "May
  Stackapella share this" is off by default.
- **Everyone who sang on it agrees** before it's public: each collaborator gets an in-app
  request.
- **Public pages** (Blade, cacheable, no visitor cookies):
  - `/h/<sqid>` (a player, the waveform, credits, and "Learn this harmony" or "Remix it");
  - `/@handle`;
  - `/t/<slug>`;
  - `/explore` (new, featured, by template and genre; MySQL FULLTEXT, iaw `Searchable`);
  - `/coaches` and `/coach/<handle>` (section F);
  - sitemaps, `robots.txt` and IndexNow (iaw).
- **Share cards:**
  - 1200 x 630 JPEG, under 300 KB, no WebP (ES's WhatsApp lesson);
  - the title, singers, waveform and part colours, drawn with GD (iaw `SocialCard`);
  - with OG tags.
- **The social parts:**
  - likes;
  - follows (a "Following" tab);
  - comments (plain text, 500 characters, limited; the owner can delete them or turn them off; a
    word list sends some to review, as ES `SpamWatch`);
  - blocks;
  - counts kept by observers.
- **Notifications are in the app only:**
  - a bell for likes (grouped), comments, follows, collaboration requests, coaching events and
    lesson changes;
  - **email only for something the person did or paid for** (the rule). That includes coaching
    receipts and booking confirmations, which are transactional;
  - web push is a later question.
- **Reports and moderation:**
  - Report on harmonies, comments, profiles, templates and coaches (iaw `ReportController`);
  - the admin queue: hide, remove, suspend, audit-logged;
  - a new account's first public harmonies wait for review.
- **Copyright:**
  - a DMCA designated agent, a takedown and counter-notice process, a repeat-infringer policy;
  - "Do you have the right to share this song?" when publishing publicly.

### D7. Social posting (our accounts)
- **A port of iaw `app/Services/Social/`:**
  - `XClient`: v2, OAuth 1.0a signed by hand, and **no links**, since X charges more for a post
    with one;
  - `BlueskyClient`: an app password, `uploadBlob`, facets, a link card;
  - `SocialPoster`, and `PostToSocial` (`$tries = 1`, an atomic claim, visibility checked again);
  - the `SocialPost` rows.
- **What posts:**
  - new templates;
  - featured harmonies (only with "may share", credited by handle);
  - guides by credited beta singers;
  - **new approved coaches** (if they agree).
- **How:**
  - posts are queued in production only, after Hillel approves them;
  - a 120-second undo;
  - held if the item changes, cancelled if it's hidden;
  - retried by hand, with failures in the digest.
- **The images:** the share card, with alt text. Later, a 30-second video (the card with the
  audio, made by ffmpeg).
- **Users share their own:**
  - the share sheet with the `/h/<sqid>` link;
  - "Post to X" and "Post to Bluesky" open pre-filled posts (`x.com/intent/post`,
    `bsky.app/intent/compose`).

### D8. Billing: Plus (pricing plan, Phase 4)
- **Cashier on `User`:**
  - Stripe Tax, monthly and yearly;
  - Checkout, the billing portal, and the webhook (ES `SubscriptionController` and
    `SubscriptionWebhookController`);
  - Plus kept while `pastDue()`;
  - the trial;
  - read-only after a downgrade;
  - cancellation reasons.
- **Email:** payment failed and trial ending (Cloudflare Email Service). Stripe sends receipts.
- **Tax:** for an accountant before the first charge.
- **Testing:** Checkout from the installed iPhone app.
- **One Stripe account, two jobs:** Cashier bills Plus, and Connect (F) pays coaches. Their
  webhooks are separate endpoints, each with its own secret.

### D9. Email: Cloudflare Email Service, transactional only (Hillel, 2026-10-26)
- **What goes out:**
  - verification, reset, email change, export ready;
  - payment failed, trial ending;
  - **coaching receipts, a review delivered, a lesson booked or changed;**
  - Hillel's digest.
- **The service:**
  - Cloudflare Email Service (Email Sending), from a sending subdomain (for example
    `mail.stackapella.com`) on Cloudflare DNS;
  - Cloudflare sets up SPF, DKIM and DMARC for it;
  - it needs the Workers Paid plan to send to anyone;
  - **it's in public beta (since April 2026),** with a daily quota that Cloudflare raises over
    time, so the quota is watched (below).
- **Laravel's side (no new dependency):**
  - a small custom mail transport, `Services/Mail/CloudflareTransport`, sends each message as raw
    MIME through Cloudflare's REST API with the `Http` facade;
  - Laravel's mailables, queues and `__()` translations stay exactly as they are;
  - `MAIL_MAILER=cloudflare` in production and `log` locally;
  - **a `failover` mailer** in `config/mail.php` makes a second provider a config change, not a
    rewrite, if the beta lets us down.
- **Bounces and complaints:**
  - Cloudflare suppresses bounced and complaining addresses by itself (hard bounces for good,
    soft ones for a day);
  - **our records follow,** so the person is told in the app to fix their address:
    - each send's reply lists the `suppressed_recipients` it dropped, recorded at once;
    - a scheduled job syncs Cloudflare's suppression list every 15 minutes through its API;
    - later, if needed, a tiny Worker forwards Cloudflare's bounce and complaint events (they
      arrive on a Cloudflare Queue) to a signed `/webhooks/mail`. This is iaw's
      `MailWebhookController` role.
- **Watched:**
  - the delivery, hard-bounce and complaint rates (Cloudflare's own targets are above 95%, under
    2% and under 0.1%);
  - the daily quota (an alert at 70%);
  - in the readiness checks and the trust and safety alerts.

## Open decisions (for Hillel, before the milestone that needs them)

| # | Decision | Recommendation |
|---|---|---|
| 1 | Minimum age for accounts (EU, UK) | 13+, and 16+ where consent law needs it; the lawyer confirms |
| 2 | Coaching age | 18+ for singers and coaches at first |
| 3 | Commission | 15% on reviews, lessons and packages; 30% on exercise packs (no service time) |
| 4 | Coaches get Plus free while active | Yes |
| 5 | Package reviews roll over | No |
| 6 | ES plan for integration use | Allowed on any ES plan for Stackapella coaches, because the booking is paid through Stackapella. Otherwise every coach also needs ES Pro |
| 7 | ES's OAuth server | Passport, if ES accepts the dependency; otherwise a small one of our own |
| 8 | Who moderates, and how fast | Hillel at first; the digest keeps nothing waiting more than a day |
| 9 | Web push | **Decided 2026-10-26:** opt-in practice reminders at launch (`docs/operations-plan.md`) |
| 10 | Comment rules | The owner deletes or turns them off; a word list plus review |
| 11 | Storage sizes, prices, trial, inactive accounts (pricing #2 to #4, #8) | From real FLAC sizes once cloud save runs |
| 12 | Paying by Invoice Ninja invoice (Plus, a choir plan, coaches' organisation clients) | Later, once someone asks for it |

## What else was missing (the review Hillel asked for)

**From the request:**
- Song storage, now fully specified (C): formats, buckets, the bin, reference counts, the device
  cache, versioning and costs.
- Every feature's server side (D): templates, the creator, the singing UX, coaching history, the
  beta and the gallery.

**Beyond it:**
- **Stale installed apps** calling a changed API: only additive changes, `X-App-Version`, and
  426 "Update the app".
- **Remixes share takes,** so a file is deleted only when nothing references it.
- **Plus history** would be lost on a new phone without `take_scores`.
- **One template schema** for the PHP and JS checks.
- **Account takeover by email match:** methods link only from Settings, and that includes ES
  sign-in.
- **Apple's private relay** needs the sending domain registered.
- **Consent:**
  - collaborators agree before a harmony goes public;
  - **the beta licence** must cover shared harmonies;
  - a **coach sees only what was sent,** and for a limited time.
- **Money safety:**
  - automatic refunds for declines and missed deadlines;
  - payouts settled before a coach deletes their account;
  - disputes and chargebacks in admin;
  - audit logs for every money action.
- **Safeguarding:** coaching is 18+, with no background checks (said plainly), and reports and
  blocks everywhere.
- **Lost webhooks:** the hourly reconcile with ES.
- **ES's webhook signature** doesn't cover the timestamp, so v2 signs it.
- **Time zones** for lessons: the profile's zone, shown in the viewer's.
- **Local development:** a signed local upload route, so the presigned flow runs on a laptop.
- **Crawlers** need Blade public pages; `'unsafe-eval'` stays off them.
- **Copyright** for user songs: the DMCA agent and takedown.
- **Support:** a contact form into the admin inbox.
- **A restore drill,** not only backups.
- **Feature flags** to ship the gallery, coaching and ES dark.

## UX notes
- **Sign-in:** one screen with Apple, Google and email, and the last method used first.
- **Publishing is never a surprise:** private by default, one clear visibility choice, "may share"
  off by default.
- **Sync conflicts** ask per part, in plain words.
- **Coaching:**
  - **it grows out of the automated report** ("Get feedback from a coach");
  - **booking a lesson never leaves Stackapella:** slots, payment and confirmation all happen in
    one screen;
  - **a review opens on the singer's own take,** with each comment at its moment, Play from here,
    and the coach's voice;
  - **prices, turnaround and the refund rules** show before paying ("Full refund if not delivered
    by Friday").
- **For coaches:** a dashboard with requests due soon, upcoming lessons, earnings, and "Reconnect
  Event Schedule" when needed.
- **Both apps:** a choir member sees "Rehearsal Thursday: practise the alto" in Stackapella, and
  "Practise the songs" on the ES event.

## Order of work

The order is in [roadmap.md](roadmap.md), where each section here belongs to a milestone:

| Sections | Milestone |
|---|---|
| A1, A2 (foundation, admin) | M1 |
| D5, the D1 catalogue (beta singers) | M2 |
| B (accounts) | M3 |
| C1 to C6, the D4 scores, `account.learning` (storage, sync, the bin, limits, course progress) | M4 |
| D8 (Plus billing) | M5 |
| D6's private and unlisted harmonies, the `/h` page, the share card, Share as a video, reports and takedown | M6 |
| C7 (collaboration), D1, D2 (templates on the server, the creator), choir groups | M7 |
| The coaching marketplace, without its directory | M8 |
| Event Schedule (lessons, choirs) | M9 |
| D7 posts of new songs, Invoice Ninja, the harmony gym | M10 |
| A3, D9, the landing, pricing and legal pages, **the network features panel** | L, then launch |
| D6's public gallery, featured posts, leagues, the template library, the coach directory, open parts, the virtual choir, song requests | N1 to N7, each when the panel says it's ready |

The server pieces of `docs/more-features-plan.md` sit in those milestones: `account.learning` in
M4, `RenderShareVideo` in M6, the gym's data in M10, then open parts, `BuildChoirMix` and song
requests when the network features panel says they're ready.

## Verification (per milestone; the full lists live with each one)
- **PHPUnit on MySQL,** with `assertMatchesContract` on every API operation. In particular:
  - **accounts:** sign-in never merges by email;
  - **storage:**
    - a stale part gets a 409;
    - a binned take is restored, and purged after 30 days;
    - a file shared by a remix survives the original's deletion;
    - an upload with the wrong hash or a non-audio file is refused;
  - **limits:** each refuses with its `code`;
  - **the gallery:**
    - collaborators must agree before a harmony goes public;
    - a blocked person can't comment;
    - social posts send once, never for hidden items, and never with a link on X (`Http::fake`,
      as iaw `SocialPostingTest`);
  - **coaching (Stripe's test mode and fixtures):**
    - a declined or overdue review refunds in full;
    - the fee is right;
    - a rating needs a finished paid engagement;
    - a coach can't sell before `charges_enabled`;
    - access to a take ends after 90 days;
  - **ES:**
    - a lesson is booked once with the same idempotency key;
    - a cancel webhook refunds under the policy;
    - a replayed or unsigned webhook is refused;
    - the reconcile finds a missed cancellation;
    - a revoked connection pauses lessons;
  - **data:** an export contains the person's data, and deletion goes through the bin purge.
- **The architecture tests:** the Never list, routes against the contract, and every model having
  a factory.
- **Contract tests against ES:** recorded ES responses (from its `openapi.json` and a test
  install) are checked in Stackapella's tests, and ES's own tests cover each new endpoint and
  webhook.
- **Web flow scripts** (`web/tool/cdp.py`, against the mock, then the real server):
  - sign up, sync between two browser profiles, and resolve a conflict;
  - delete and restore a take;
  - publish, then see the page and card;
  - **a singer buys a take review, the coach delivers with a pinned voice note, and the singer
    rates it;**
  - **book a lesson against a local ES, cancel it in ES, and see the refund.**
- **By Hillel:**
  - Apple sign-in on an iPhone (with Hide My Email);
  - real test posts to X and Bluesky;
  - Checkout and Connect onboarding from the installed app;
  - a real lesson booked between two test accounts across both apps;
  - the restore drill.

---

[Back: Sing](singing-ux-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Trust and safety](trust-and-safety-plan.md)
