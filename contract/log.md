# Contract log

Read the Now block and the last 10 entries at the start of every task, and after any message from
the other agent (`docs/parallel-agents-plan.md`, "Talking to each other"). Whoever changes
something keeps the Now block current. `log.md` is the one file in `contract/` that either agent
may change without `CONTRACT_OK=1`.

## Now

- **Milestone:** M1 to M6 done on the backend (M5 live on the beta); **M6** (sharing: links and
  videos) landed with its agreed contract; the frontend has closed **M3**, **M4**, **M5** and **M6** (2026-10-27) and is building
  **M7**. **M7** (your own templates, collaboration, choir groups) landed with its
  contract; **M8** (coaching, without the directory) landed with its contract; **M9** (Event
  Schedule together, against a fake) landed with its contract; **M10** (Invoice Ninja's books,
  Plus paid by invoice, our own posts, Sign in with Event Schedule) landed with its contract; **L**
  (launch readiness) is built and reviewed on the backend (2026-10-27): what's left is Hillel's
  (items 40 and 41) and the frontend's L.
- **IndexedDB:** **version 2 in use** (since M1: takes stored as 16-bit, plus the `guides`,
  `betaTakes` and `learning` stores). **Version 3 in use since M4 (2026-10-27):** the `outbox` (one entry per record
  to send, keyed `kind:id`, carrying its `changeId`) and `meta` (the sync cursor and conflicts);
  parts and projects gain `version`, and a part its `takeId` and `syncedTakeId`, without a
  migration.
- **Contract operations being built:** M7's `templates.mine/save/update/delete`,
  `templates.recordings.*`, `projects.shared`, `projects.members.*`, `invites.*` and `groups.*`
  (backend: built and tested; frontend: after M4 and M6). M6's `harmonies.*`, `videos.*`, `reports.create`,
  `admin.reports.list` and `admin.harmonies.*` (backend: built and tested; frontend: after M4).
- **Shared formats:** `template.json` (all 8 templates in `web/templates` checked on both sides).
- **Public stub views landed:** `public.harmony` and `public.harmony-gone` (8dfa0ed), for `/h/<id>`.
- **Local servers:** Laravel on `http://127.0.0.1:8000` from `~/Code/stackapella-backend/server`
  (running: `php -S 127.0.0.1:8000 tool/serve.php`, seeded with `php artisan db:seed`); the web app on `:8081` from the frontend's worktree.

### Needs Hillel

Things only Hillel can do. None of them blocks the build: the agents use fakes and test modes
meanwhile.

| # | What | Where | Needed by |
|---|---|---|---|
| 1 | **Done (2026-10-26, Hillel's session): the App Platform app `stackapella`, Managed MySQL 8.4 `stackapella-db` in the `stackapella-nyc1` VPC, the three Spaces buckets with the CDN, and beta.stackapella.com.** Create the DigitalOcean account: App Platform, Managed MySQL 8.4, Spaces (`media`, `private`, `backup` buckets), and the beta domain. Then `doctl apps create --spec .do/app.yaml` once, and set the secrets in the console (the list is in `.do/app.yaml`'s header) | DigitalOcean | M1's exit check |
| 2 | **Done (2026-10-26, Hillel's session): the proxy (beta.stackapella.com proxied, SSL Full (strict)), and email: Cloudflare Email Sending onboarded for stackapella.com (return path cf-bounce.stackapella.com, DMARC p=none, reports to contact@).** Put Cloudflare in front of the beta domain (DNS, the free plan's proxy), and set up Email Sending for a sending subdomain (Workers Paid plan) | Cloudflare | M1 (proxy), M3 (email) |
| 3 | **Done (2026-10-26, Hillel's session): the Google OAuth client, `ADMIN_EMAILS` and `ADMIN_GOOGLE_IDS` are set.** Make a Google OAuth client for the beta domain (`/auth/google/callback`), and put your Google ID and email in `ADMIN_GOOGLE_IDS` and `ADMIN_EMAILS` | Google Cloud console, App Platform env | M1's exit check |
| 4 | **Done (2026-10-26): Hillel signs in to `/admin` with his second factor.** Sign in to `/admin` on the beta domain and enrol a passkey (and the TOTP backup, saving the recovery codes) | The beta domain | M1's exit check |
| 5 | Review the dependencies the agents added, listed under "Decided by the agents (delegated)" in `docs/decisions.md` | `docs/decisions.md` | Any time |
| 6 | **The M0 device runs** (the checklist below): record and check the timing on real devices, with real mics and interruptions. Until then the timing is proven only by the loopback tests in headless Chrome | Your devices, with `web/` served over HTTPS (README, "Running the app") | M0's exit check |
| 7 | **Done (2026-10-26, Hillel OK'd them).** OK the two Docker base images the server now builds on (`composer:2` and `php:8.4-fpm-bookworm`, pinned by digest in `.do/Dockerfile`), which replace the PHP buildpack so the PWA ships in the same image (the reason is in `docs/decisions.md`) | `docs/decisions.md` | Before the first deploy |
| 8 | **Done (2026-10-26, Hillel's session): the first deploy built `.do/Dockerfile` from the root; `/up`, `/` and `/js/main.js` answer; `app:admin:grant` was run.** On the first deploy, check App Platform builds `.do/Dockerfile` from the repository's root (`dockerfile_path` and `source_dir: /` in `.do/app.yaml`), and that `/up`, `/` and `/js/main.js` answer on the beta domain. Then, with your email in `ADMIN_EMAILS`, sign in once at `/admin/sign-in`: the refusal shows your Google ID. Add it to `ADMIN_GOOGLE_IDS`, sign in again (that makes your account), and give yourself the admin role from the web component's console: `php artisan app:admin:grant <your Google ID>` | App Platform | M1's exit check |
| 9 | **The lawyer:** check the beta singer agreement (`server/resources/legal/en/beta-agreement.md`, `reviewed: false`, with the questions in its front matter), fill in the rate, payment terms, governing law and contact, then set `reviewed: true`. Invites shouldn't go out before this | The lawyer | M2's first invite |
| 10 | **The musician:** check all 19 drafted templates (the 8 in `web/templates`, the 11 Plus songs in `server/database/templates`), using `docs/templates-review.md`. A template opens for recording only once it's `reviewed: true` | The musician | M2 (recording), M5 (Plus songs) |
| 12 | **Pushing:** the backend agent doesn't push (your instructions to it say never to), even though another session said you'd allowed it. If you want it to push main, tell it in its own session; until then, push main yourself when you like (everything landed passes the checks) | Your terminal | Any time |
| 13 | **Beta invites:** once items 9 and 10 are done, invite one friend first from `/admin` (beta singers), end to end, before inviting more (`docs/beta-singers-plan.md`) | `/admin` on the beta domain | M2's exit check |
| 14 | **The M1 device pass** (the checklist below the M0 one): the app's words, the guides, recording and the admin on each device | Your devices; `/admin` on the beta domain | Before M2's first invite |
| 15 | **Done (2026-10-26, Hillel's session): `DIGITALOCEAN_TOKEN` (scoped to CDN read and delete) and `DIGITALOCEAN_CDN_ENDPOINT_ID` are set; a test purge answered 204.** **The CDN purge:** a DigitalOcean API token (write) and the media CDN endpoint's id, as `DIGITALOCEAN_TOKEN` and `DIGITALOCEAN_CDN_ENDPOINT_ID` (encrypted) in App Platform. Without them, a removed guide keeps playing from the CDN's edge until its year-long cache expires | DigitalOcean, App Platform | Before a singer asks for removal |
| 16 | **Done (2026-10-26, Hillel's session): `MAIL_MAILER=cloudflare`, the account id and token are set, and a test send through the transport's payload was accepted.** Cloudflare Email Service: set `MAIL_MAILER=cloudflare`, `CLOUDFLARE_ACCOUNT_ID` and `CLOUDFLARE_EMAIL_TOKEN` (an API token that can send) once the sending domain is set up (item 2). Until then the app's mail goes to the log | Cloudflare, App Platform | M3 (verification email) |
| 17 | **Sign in with Apple:** in the Apple developer account (the roadmap's M3 item), make a Services ID for the beta domain with the return URL `https://<beta domain>/auth/apple/callback`, a Sign in with Apple key, and register the sending domain with Apple's private email relay (so mail reaches Hide My Email addresses). Then set `APPLE_CLIENT_ID`, `APPLE_TEAM_ID`, `APPLE_KEY_ID` and `APPLE_PRIVATE_KEY` (encrypted) in App Platform. Until then the Apple button fails with `?authError=failed` | Apple developer account, App Platform | M3's exit check |
| 18 | **Turnstile, for shield mode:** a free Turnstile widget for the beta domain in Cloudflare, as `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET` (encrypted). Shield mode stays off without them, even when switched on | Cloudflare, App Platform | Before an attack |
| 19 | **Done (2026-10-26, set up by Hillel's session): `uploads/` and `tmp/` expire after 1 day, `exports/` after 7, old versions after 30.** A lifecycle rule on the private bucket: in Spaces, expire objects under `uploads/` after 1 day (and under `exports/` after 7). Spaces can't bind a presigned PUT to its size, so this caps what an abandoned or repeated upload can leave behind, on top of the server's own hourly clean-up | DigitalOcean Spaces (`stackapella-private`) | Before cloud save opens (M4) |
| 20 | **Done (2026-10-26, checked live by Hillel's session): the media bucket answers `*` through the CDN (MISS and HIT, `vary: Origin`); the private bucket allows GET, HEAD and PUT from the beta only.** CORS on both buckets: `stackapella-media` (behind the CDN) allows `GET` and `HEAD` from any origin (`*`: public, cookie-free files the app fetches with `mode: 'cors'` to keep guides offline); `stackapella-private` allows `PUT` (uploads) and `GET` (takes, exports, beta masters) from `https://beta.stackapella.com` and, at launch, `https://stackapella.com`, with the `Content-Type` header. Check the CDN passes `Access-Control-Allow-Origin` through. Without the media rule, real voices fail to load in the app | DigitalOcean Spaces | Before real voices reach learners (M2's exit) |
| 21 | **At launch:** add `https://stackapella.com` to `stackapella-private`'s CORS rule (GET, HEAD, PUT, as for the beta), or uploads and downloads fail from the launch domain | DigitalOcean Spaces | Launch (L) |
| 22 | **Stripe for Plus:** a Stripe account with Stripe Tax on (and the accountant's advice on registrations before the first charge); two prices, monthly ($4.99) and yearly ($39.99), each with EUR (4.99 / 39.99) and GBP (4.49 / 34.99) currency options, matching `config/billing.php`; the customer portal set to allow cancelling (with reasons) and changing card and plan, not currency; the webhook endpoint `https://<domain>/webhooks/stripe` with its signing secret, for `checkout.session.completed`, `customer.subscription.*`, `invoice.payment_failed`, `invoice.paid` and `charge.dispute.*` (without the secret, production refuses every webhook and `/up` says degraded); Radar's rules (block a prepaid card whose country differs from the IP's; review a high risk score). Then `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_MONTH` and `STRIPE_PRICE_YEAR` in App Platform (live keys only in production) | Stripe, App Platform | M5's exit check (a test card upgrades from the installed app) |
| 23 | **The lawyer, on Checkout's wording:** the EU and UK consent to start Plus at once (the 14-day withdrawal), the renewal and cancellation lines, and the refund policy page (`docs/pricing-plan.md`, "At checkout") | The lawyer | Before the first real charge |
| 24 | **Done (2026-10-26, Hillel's session): the VAPID keys are in production (never printed), with `VAPID_SUBJECT=mailto:contact@stackapella.com`.** Practice reminders' keys: run `php artisan app:reminders:keys` once (in the App Platform console) and set `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` (encrypted). Never make them again: new keys end every device's reminders | App Platform | M5 (reminders) |
| 25 | **The M2 device pass** (the checklist below): a real voice in the player on each browser, the singer's journey on an iPhone (link, install, code, recording in the installed app) and the review on a Mac | Your devices; `/admin` and a test singer link on the beta domain | Before the first invite (item 13) |
| 26 | **The DMCA designated agent** (shared links host people's audio): register one with the US Copyright Office (online, a small fee), then fill in the agent's name, address and email in `server/resources/legal/en/copyright.md` | copyright.gov, the repo | Before launch (L) |
| 27 | **The lawyer, on the copyright policy:** check `server/resources/legal/en/copyright.md` (`reviewed: false`, the questions in its front matter: the DMCA notice and counter-notice, the repeat-infringer rule, the EU's notice and statement of reasons) | The lawyer | Before launch (L) |
| 28 | **The M6 device pass:** share a harmony from an iPhone; the link plays on a phone, and its card looks right in iMessage and WhatsApp; Share as a video, then post it to TikTok and Reels and check it plays well (words above their captions); report a link signed out from another network three times, and take it down in `/admin` | Your devices; the beta domain | M6's exit check |
| 29 | **The choir plan** (`docs/pricing-plan.md` #11, "before M7"): confirm or change the launch defaults the backend chose (`docs/decisions.md`, "The choir plan, for launch"): groups included in the director's Plus at no extra price, 60 members a group, 5 groups a director, members free and learning the group's Plus songs while in it. **And the age question:** groups are 18 and over for now (asked on joining). Accounts start at 13, and the trust and safety plan gives under-18 accounts private defaults (share links only, no public profile). May a 13 to 17 year old join a choir group, where an adult director (possibly a stranger) sees their name and which parts they've recorded, but can't message them or hear their recordings? If yes, set `usage.group_min_age` to 13; if only with a parent's or school's consent, say so and it gets built. Also: may a 13 to 17 year old sing on someone's project by invite (collaboration has no messages either)? | `docs/decisions.md`, `server/config/usage.php` | Before launch (L) |
| 30 | **Stripe Connect for coaching:** in Stripe, set up Connect (platform profile, Express accounts, branding), and add a Connect webhook endpoint `https://<domain>/webhooks/stripe-connect` for `account.updated`, with its signing secret as `STRIPE_CONNECT_WEBHOOK_SECRET` (encrypted). The platform webhook (item 22) already carries `checkout.session.completed`, `customer.subscription.*` and `invoice.*`, which coaching uses too. Without these, coaches can't be onboarded and production refuses Connect events. In test mode, check a review's transfer in GBP or EUR reaches an Express account when the platform settles in USD (transfers are made in the charge's currency) | Stripe, App Platform | M8's exit check (test mode) |
| 31 | **Coaching's money and terms:** confirm the 15% commission (`config/coaching.php`, `COACHING_COMMISSION_PERCENT`); the lawyer checks the coach agreement (`server/resources/legal/en/coach-agreement.md`, `reviewed: false`) and the marketplace terms singers agree to; the accountant advises on who collects VAT and sales tax on coaching | You, the lawyer, the accountant | Before the first coaching sale |
| 32 | **The sample take for coach applications:** a short take with a few things worth noting, uploaded to the media bucket at `coaching/sample-take.m4a` (or `COACHING_SAMPLE_TAKE_KEY`), which applicants review. Then approve the first coaches (who bring their own students) in `/admin` | The media bucket, `/admin` | Before the first application |
| 33 | **The M8 device pass** (Stripe in test mode): a coach onboards to Express and shares their profile link; a singer buys a review from an iPhone; the coach delivers it with a pinned voice note; a missed deadline refunds in full | Your devices; Stripe's test mode | M8's exit check |
| 34 | **Start the Event Schedule agent** in `~/Code/eventschedule` with `contract/event-schedule.md` (what Stackapella needs: OAuth with PKCE and scopes, the bookings API with `external_id` and `payment: external`, webhooks registered by the app with the v2 signature, members, events, rendering `/h/<id>` links by oEmbed, no double invoicing; keeping an app's webhooks for bookings still to come after a revoke). Decide its OAuth server (Passport or a small one; open decision 7) and the plan rule for integration use (open decision 6). Then register Stackapella as a client (redirect `https://<domain>/connect/event-schedule/callback`) and set `EVENT_SCHEDULE_CLIENT_ID` and `EVENT_SCHEDULE_CLIENT_SECRET` (encrypted). Until then the server runs against its fake, and production refuses to connect (`423 paused`) | You, the ES agent, App Platform | Before lessons go live |
| 35 | **The M9 device pass** (once ES's side is live, in test mode): a coach connects ES and picks a schedule; a singer books a lesson from an iPhone and it appears in the coach's ES; the coach cancels in ES and the singer is refunded; a choir group shows its next rehearsal with what to practise; a harmony link plays on an ES event page | Your devices; Stripe's test mode; ES | M9's exit check on the real ES |
| 36 | **The M3 device pass** (the checklist below): Find your note on a real voice, Sing's live line, signing in with Google and Apple, and Sing's redesign on an iPhone | Your devices; the beta domain | M3's exit check |
| 37 | **M10's outside pieces:** (1) in Stripe, add `charge.refunded` to the platform webhook (Plus refunds go in our books) and, if not yet, `checkout.session.async_payment_succeeded` and `_failed` (item 30); (2) connect Stackapella's own Invoice Ninja company in `/admin` (Books, in sudo; a token from invoicing.co), which also registers its payment webhook for Plus paid by invoice; (3) our X and Bluesky accounts: the keys are already set on the beta (`X_*`, `BLUESKY_HANDLE=stackapella.com`), and the beta runs as production, so posts are proposed there too, each waiting for your approval in `/admin` (Posts); **X is pay-per-use: buy credits (auto top-up off) before approving an X post**, or it fails with "X needs credits" (no retry by itself; Retry after topping up). Nothing ever posts without your approval; (4) in Event Schedule's client for Stackapella, also register the redirect `https://<domain>/auth/event-schedule/callback` (Sign in with Event Schedule) | Stripe, Invoice Ninja, X, Bluesky, ES, App Platform | Before each goes live |
| 38 | **The M10 checks by you:** connect a test company on invoicing.co as a coach, buy a take review as a test singer, refund it, and see the invoice, payment, commission expense and refund there; invoice a test account for Plus and pay it on Invoice Ninja's portal; approve one post on each network; sign in with Event Schedule once its side is live | invoicing.co, X, Bluesky, ES | M10's exit check |
| 39 | **The M4 device pass** (the checklist below): a project recorded on the phone opens on the Mac with the course progress; a conflict asks; Recently deleted restores; the fourth project stays on the device; trims and Make it sound good by ear | Your devices; the beta domain | M4's exit check |
| 40 | **The lawyer, on the launch legal pages:** terms, privacy, refunds, community guidelines, the marketplace terms and the accessibility statement (`server/resources/legal/en/`, each `reviewed: false` with its questions in `for_lawyer`), plus your answers to their bracketed placeholders: your legal name and address, the governing law, the yearly and monthly refund rules, the EU and UK withdrawal refund, and the commission shown to buyers. Each page is live at `/legal/<page>` with a draft banner until `reviewed: true`. | `server/resources/legal/en/` | Launch |
| 41 | **L's outside pieces, before launch** (`docs/runbook.md`, "The launch checklist"): set `SENTRY_LARAVEL_DSN` and `SENTRY_BROWSER_DSN` and see a test error arrive scrubbed; **done (2026-10-27, Hillel's session): a Spaces key scoped to `stackapella-backup` alone, set as `BACKUP_SPACES_KEY`/`_SECRET`, and versioning on `backup`, `private` and `media` (old versions kept 30 days)**; run `php artisan app:backup:run` and `app:backup:drill` once on production and read the result (set `RESTORE_DRILL_DATABASE` to a database with "drill" in its name if the managed server can't make one); deploy the bounce Worker with `MAIL_WEBHOOK_SECRET`; at launch, on the launch domain only, `SITE_INDEXING=true` and `APP_REDIRECT_HOSTS`; run the abuse test against the staging domain; the L device pass; then say go. | App Platform, DigitalOcean Spaces, Sentry, Cloudflare | Launch |
| 42 | **The M5 device pass** (the checklist below): Wait for me and the stars on a real voice, How it went, Your voice, Today's workout, Plus in Stripe's test mode from the installed app, MP3 export, practice reminders on an iPhone, and confirming lamejs | Your devices; Stripe's test mode; the beta domain | M5's exit check |
| 43 | **The M6 device pass** (the checklist below): Share from the studio, the link's card in iMessage and WhatsApp, the `/h` page on a phone, Share as a video on TikTok and Reels, Report and the takedown in admin |

#### The M6 device pass (item 43)

On an iPhone (installed) and a Mac, signed in, with a finished harmony of a song:

| Check | Expect |
|---|---|
| The studio's Share: All of it, unlisted, with Make it sound good | Progress, then the link; Copy, Share (the phone's own sheet), Post to X and Post to Bluesky open pre-filled |
| Send the link in iMessage and WhatsApp | The card shows the title, who sang, and the coloured waveform image |
| Open the link on the phone, signed out | The page plays with the round button; the waveform fills in the parts' colours; a tap moves there; the credits and Learn this harmony are right |
| Share just my part | Only that part is heard on the page |
| Share as a video, dark with the words, then light without | Progress, then the file shares from the sheet; it plays well when posted to TikTok and Reels (draft is enough) |
| Report the page from another phone, signed out | The form sends; after three reports the page says it's being looked at |
| In `/admin`, Reports | The report is there with its player; Hide takes the page down; Restore brings it back; Remove asks for your second factor |
| With Speed below 100% in the studio, Share | It asks you to put the speed back first |

#### The M5 device pass (item 42)

On an iPhone (installed) and a Mac, with headphones unless it says otherwise:

| Check | Expect |
|---|---|
| A course lesson's Try step (Wait for me), singing for real | Each note waits until you find it; "A little higher" or "lower" only after a moment, pointing the right way; Nice or Perfect; Skip this note after a few seconds |
| Without headphones (No headphones), the same Try step | Each note plays for a beat and goes quiet; Hear it again; the notes still light |
| Play it through and finish lessons in unit 1 | The stars feel fair (the voice teacher judges too); a silent try earns none and costs nothing |
| Sing a song's part through to Just recorded | How it went: the stars, the percent, then See details: what went well, one thing to try with its tip, each phrase's stars, your line on the chart, Replay with your line; a second take says "+N% since last time" |
| Record without headphones on the Mac's speakers | How it went says it could hear the other parts, and the take doesn't count against you |
| Your voice (the course map's button) | Your range on the keyboard with the voice it suits; badges earned and to come; with Plus, your history; without, the marked sample |
| Today's workout, then a second one the same day on a free account | The warm-up fits your range; something to work on matches what your takes showed; the second says one a day is free, with the way to Plus |
| Plus from the installed app (Stripe test card 4242 4242 4242 4242) | Checkout opens in the browser with the price, tax, renewal and trial date; back in the app, the plan shows Plus within seconds (the page says it's confirming meanwhile); Manage opens Stripe's portal |
| Export as MP3 and Each part on Plus | The MP3 plays in Music or QuickTime; the ZIP has one WAV a part that line up when dropped into an editor |
| Practice reminders on the iPhone (installed), at a time two minutes ahead | The permission asks once; the notification arrives, worded in your language; a tap opens the course; Turn off stops it on this device only |
| **lamejs** (MP3 export) | Confirm the dependency: lamejs 1.2.1 from jsDelivr, pinned with SRI, LGPL, loaded only for MP3 (docs/decisions.md) |

#### The M4 device pass (item 39)

Signed in with the same account on an iPhone (Safari, then installed) and a Mac (Safari or Chrome):

| Check | Expect |
|---|---|
| On the iPhone: record a part of a song, and finish a lesson; then open the app on the Mac | The project is on the Mac with its take (it plays), and the lesson's stars and XP are there too |
| Rename the song on the iPhone; before the Mac has heard (stay in the studio), rename it there too | The Mac asks "Changed on another device", Keep mine or Use theirs, and does what you chose |
| Delete the project on the iPhone; on the Mac, Settings, Storage, Recently deleted, Restore | It goes from the Mac, then comes back whole, take and all |
| A free account with 3 projects: make a 4th on a device that was signed out, then sign in | The 4th stays on the device, marked "Not saved to your account" |
| In flight mode, record a part; then turn flight mode off | Storage says the change is on its way, then "Everything is saved to your account" |
| Trim a part (drag the ends, Trim the silence, Play from the start), then Same end | No click at the cuts; the singing stays in time; Undo puts it back |
| Export with Make it sound good in each room, and a practice track with a click | It sounds like one room and even, not louder in places; the track's count-in is a bar, the click in time |
| Hear it polished on, then record a new part with headphones | Playback has the room; the new take is dry, and lines up as before |

#### The M3 device pass (item 36)

On an iPhone (Safari, then installed), Android Chrome, and Safari and Chrome on a Mac:

| Check | Expect |
|---|---|
| The home, first visit: Start singing, then Find your note; sing any easy note and hold it | Your note shows as you sing and a bar fills; two seconds held finds it. If it can't, it says why (nothing heard, too quiet, not one note), with the mic picker right there; Skip for now goes on |
| A lesson with a drone or the guide singing along, without headphones (choose No headphones) | The sound underneath rests while you sing; a silent try earns no stars |
| Sing, Amazing Grace's alto, Notes view, On your own, Play, and sing | Your voice draws as a line close to the now line, steady, with no line when you're silent; the notes you hit glow. On the iPhone it keeps up without stutter (note the model) |
| Sing's Stage while recording a part, then Just recorded | The red frame and "Recording 0:07", one Stop; Just recorded shows your take in the stack, Hear it with the others plays it in time |
| Re-sing one phrase, then Keep it | No click at either join; the level matches; Undo puts the old take back |
| Sign up with email, verify from the email, then Settings: Download my data, then Delete | The email arrives (check spam), the link signs you in, the export downloads, the account goes |
| Sign in with Google, then with Apple (once item 17 is done) | Both sign in and land where you were; the Apple relay address works |
| Install the app on the iPhone from the home's card (Share, Add to Home Screen) | The card goes away once installed |

#### The M2 device pass (item 25)

On an iPhone (Safari, then the installed app), Android Chrome, Safari and Firefox on a Mac, and
Chrome on Windows, with a test singer invited to Amazing Grace's alto:

| Check | Expect |
|---|---|
| Open Amazing Grace in the original key, Play, and listen to a part with a real voice against the synth (the part's dialog switches) | The real voice lines up with the other parts to the ear, from the first note, in Safari and Firefox too (the AAC's encoder delay is skipped) |
| Change the key, then the speed | A note says why the practice voice plays, with the way back |
| On the iPhone in Safari: open the singer link | The welcome, the agreement and My parts; the link is gone from the address bar; My parts suggests installing the app |
| Install the app, open it, then "Your beta parts" or "Already singing in the beta? Sign in" | My parts opens in the app, signed in |
| In the app: Learn and record, with headphones | The timing check (no skipping), the sound check (two quiet seconds, then singing), the take, then Check and send |
| Turn on flight mode, send, then turn it off | The take waits ("It goes when you're back online") and goes by itself |
| On a Mac: `/admin/review`, open the take | It plays in the mix and against the synth; nudging and trimming are heard; Accept publishes it |
| Back on the iPhone: Amazing Grace's alto | It's the singer's voice, "Sung by" their credit; My parts thanks them, with Hear your voice |

#### The M1 device pass (item 14)

Every milestone ends with this short pass (`docs/operations-plan.md`, "Devices"). For M1, on an
iPhone (Safari, and installed), Android Chrome, Safari and Firefox on a Mac, and Chrome on Windows:

| Check | Expect |
|---|---|
| Open the app, then a template project, and press Play | The guides play together (no silence at the start); the words are all in English, none missing or showing a key like `studio.record` |
| Record a part over the others, then Undo | As before M1 (the studio's audio moved into a shared store, so any difference is a bug) |
| Reload the project after recording | The take is still there (takes are now stored at half the size; old ones were converted) |
| Open the app in two tabs, one of them from before today's update | The older tab is asked to close or reload, and nothing hangs |
| On a Mac or PC: `/admin` on the beta domain | Signs in with Google and your second factor, shows your name; the trust and safety tabs and the audit log load |
| On the iPhone: `/admin` | The same, with the tabs scrolling sideways |

#### The M0 device-run checklist (item 6)

Serve the app (README, "Running the app"); for the iPhone, over HTTPS with `expose share`. On each
device, open a template (Amazing Grace) and a plain project. For each row: run the timing check,
record a part over the others in headphones, play it back, and judge by ear whether it lines up.
Note anything odd (the sample rate shown in the timing check's details, a volume drop, the earpiece).

| Device and browser | Mics to try | What to check |
|---|---|---|
| iPhone, Safari | Wired headphones with a mic (EarPods), AirPods, the built-in mic with speakers | Audio plays with the silent switch on; playback doesn't move to the earpiece or get quieter when the mic opens; the first click of the count-in isn't swallowed; the AirPods warning shows in the mic picker |
| iPhone, installed (Share, Add to Home Screen) | The same | The same, in the installed app |
| Mac, Safari | Built-in mic with speakers, wired headset, Bluetooth headphones, the iPhone as a Continuity mic | The wireless warning shows for Bluetooth and the iPhone; the timing check passes on wired |
| Mac, Firefox | The same | The mic opens (the app rebuilds its audio at the mic's sample rate if they differ); takes line up |
| Mac, Chrome (control) | The same | As before (the spike passed on wired) |

**Interruptions,** on the iPhone (Safari and installed), each during a take of 20 seconds or more:

| Do this mid-take | Expect |
|---|---|
| Have someone call you | Recording stops; "Recording stopped: your take is saved up to 0:xx"; the take plays back up to there |
| Connect, then disconnect AirPods | The same; "New headphones or mic? Check the mic and timing" appears |
| Lock the screen | The same, when you unlock |
| Switch to another app, then back | The same |
| Start music in another app | The same |

Send back what you saw for each row (or the timing check's details); the agents turn it into the
results table in `docs/audio-spike.md` and fix what breaks.

## Entries (newest first)

- **2026-10-29** (backend, agreed with the frontend, the landing agent and the courses lead; Hillel: "re-use the singer feature in admin to request singers to record these missing parts"): course clips. An assignment is a song's part or a course clip (`web/course/plus/clips.json`, synced on deploy): `beta.parts.list` items gain `kind` and `clip` (template and partKey null for a clip); `beta.submissions.create` takes no templateVersion or key for a clip (no bar; the verdict `ready` still needed); `admin.beta.assignments.create` takes `clipId`; the admin's assignment and submission gain `kind` (and `clipId` or `clip`); new `admin.beta.clips.list` and `courses.clips.list`. Additive for every existing part.
- **2026-10-29** (backend, agreed with the frontend; Hillel's game or serious mode, docs/skins-plan.md): the account's `appearance` gains `skin` (`game` or `serious`, default `game`), saved by account.update like the rest of appearance. Additive.
- **2026-10-29** (backend, agreed with the frontend; the launch plan: coaches sign up at launch, hidden until N4): auth.config gains `coachSignup` (the `coach_signup` switch); coach.application.create answers `409 coach_signup_closed` while it's off. The directory stays `coach_directory`. Additive.
- **2026-10-29** (backend, agreed with the frontend; B, the launch audit): account.json gains `terms` (`accepted`, `current`, `acceptedAt`); new `account.terms.accept` (POST /api/account/terms, `{ version }`; `409 terms_changed` with `current: { version }`). Every new account records the current version when it's made. Additive.
- **2026-10-29** (backend, agreed with the frontend and the courses agent): account.json gains `plusCourses` (the `plus_courses` switch on, or staff); billing.checkout's `moment` accepts `plus_course`. Additive.
- **2026-10-29** (backend, agreed with the frontend; Hillel's "Anonymous totals"): `admin.mudeo.json` gains `days` (newest first, 60 days: `date`, `sent`, `bounced`, `visits`, `joined`, `used`), daily numbers only. Additive.
- **2026-10-29** (backend, for the courses agent): `account.learning.reset` and `undoReset` say the Plus courses' progress (`progress.courses`) is never touched by either; tested. Wording only.
- **2026-10-29** (backend, agreed with the frontend; the arranger's find): `catalogue.song.json` gains `voicings` (the template's own, {} when none), so a library card can say "2 to 4 singers". Additive.
- **2026-10-29** (backend; Hillel's H24, "Always ends Plus"): `admin.people.refund` ends Plus at once, full or partial; `billing.show` `endedReason` gains `refunded`. Additive.
- **2026-10-29** (backend; Hillel's "make sure it isn't served publicly with brackets showing", via the coordinator): `beta.agreement.show` needs a signed-in account (signed out: `401 unauthenticated`). The web and the apps already ask for it only after sign-in, on the way to consent. A narrowing, logged in docs/decisions.md.
- **2026-10-29** (backend, agreed with the frontend; Hillel's "Go on with them"): the bell's `board_beaten` kind (a friend beat your best on a part), with `board` (`templateId`, `title`, `partKey`, `partName`, `score`; null on other kinds) on every bell item, and `pushTypes.board_beaten` (on by default). Additive.
- **2026-10-29** (backend, agreed with the landing agent; Hillel's "Go on with them"): earlier arrangements' boards. `boards.show` takes `?version=` (an earlier version with entries, read only) and gains `current` and `olderVersions`. Additive.
- **2026-10-29** (backend, agreed with the landing agent and the frontend; docs/leaderboards-plan.md, "later"): "report this name". `boards.show` entries gain `id`; `reports.create` takes `boardEntryId` (signed in); 2 people's reports hold the entry off the global board until an admin looks. `admin.boards.flags.list` items gain `reports` and `reason: "reported"`. Additive.
- **2026-10-29** (backend, agreed with the frontend; docs/leaderboards-plan.md, "later"): leaderboard flag review. A perfect, jumping or far-above-the-board score is held off the global board for 48 hours (its singer still sees it) unless cleared; new `admin.boards.flags.list` and `admin.boards.scores.review`; the digest counts them. `boards.show`'s shape is unchanged. Additive.
- **2026-10-29** (backend, agreed with the frontend; the L audit): new `admin.people.payment` (`GET /api/admin/people/{id}/payment`, an admin): the latest Plus payment through Stripe (`amountCents`, `refundedCents`, `currency`, `paidAt`) or null, read when the refund panel opens. `admin.support.show`'s `from.account` gains `plan` (`plus`, `source`, `until`). Additive.
- **2026-10-29** (backend, agreed with the landing agent; its 5d proposal): `beta.credit.update` takes `creditLinksProfile` (boolean), with or without `creditMode` (at least one); `beta.me` (show, consent.create, credit.update, invite.accept) gains `creditLinksProfile`, `hasPublicProfile` and `profilesOpen`. A beta guide's `creditHandle` is set only when the singer is credited by name, chose to link, has a public profile and profiles are open (the gallery). Additive.
- **2026-10-28** (frontend, backend agreed via the coordinator): the `templates.catalogue` example lists Amazing Grace at its current version, 3 (since the arranger's fixes, c9f9ac80), not 2; data only, no shape change. The mock's real voice for it now applies again (`real_voices.py`).
- **2026-10-28** (backend, for Hillel's "country tracking isn't working"): new `admin.diagnostics.country`.
- **2026-10-28** (backend, for the landing agent's Settings switch): `account.json` gains `boardsGlobal` (on the worldwide part leaderboards).
- **2026-10-28** (backend, agreed with the landing agent; Hillel's part leaderboards, the foundation): `boards.scores.submit`, `boards.show`, `boards.global.update`, `boards.challenges.create`, `boards.challenges.accept`, `admin.boards.scores.delete`, `admin.boards.reset`. Scores carry the scorer's versions and inputs for a later rescore; `leaderboard_viewed` and `challenge_accepted` are counted kinds.
- **2026-10-28** (backend, agreed with the landing agent; Hillel's anonymous counts, "all in the app itself, no GA"): new `counts.create` (POST /api/counts, no session, 204) and `admin.counts.show` (GET /api/admin/counts?days=7|30|90); `auth.config` gains `usageCounts`.
- **2026-10-28** (backend, agreed with the landing agent; Hillel's "can we track our users' countries"): `admin.account.json` gains `country` (the latest country signed in from, Cloudflare's CF-IPCountry; null when not known); `admin.people.list` takes `?country=`; new `admin.people.countries` (counts by country).
- **2026-10-28** (backend, agreed with the landing agent; Hillel's "also show stats about each person"): `admin.account.json` gains `stats`, counts and times only: `songs`, `lessonsDone`, `streakWeeks` (the app's streak is in weeks) and `lastActivity` on every row; `takes`, `stars`, `xp`, `level`, `practiceMinutes` and `storageBytes` on the person page.
- **2026-10-28** (backend, agreed with the landing agent for Hillel's People screen): `admin.account.json` gains `adult` (true, false or null; never the birth date), `betaSinger` and `lastActiveAt`, and on the person page `plan` (`plus`, `source`, `until`) and `counts` (`publicHarmonies`, `groups`). `admin.people.list` takes `adult`, `plan`, `role` and `sort`; `admin.audit.list` takes `user`. No trust filter: the level is worked out from activity, not stored.
- **2026-10-28** (backend, from the frontend's ask): `auth.config` gains `sentryDsn`, the browser error reporter's DSN (`SENTRY_BROWSER_DSN`), or null when unset. The web's reporter (0179ff8a) sends nothing while it's null.
- **2026-10-28** (frontend, final report; launch scope as Hillel cut it on 2026-10-28): **the web app is built through launch scope.**
  - **Built and on main today:** the recording stage, delete confirmation, accents, note bricks, scoring that counts unheard notes, template undo, the sounds, the course review's Fix now and #8, #11 and #16, When were you born? (ageConfirmed gone), the studio as 117 and 81 (the words as phrase chips, Ready to record, Recorded 1 hour ago), take result (120), Settings (87) with How you sing and Start the course over, Reminders (111), Storage (88), the upgrade moment (99), Export (83) with piano chords, Share (84), Trim (82), the key control (128), the timing check (101), headphones (102), Check your mic (103), Song settings (105), Sing together save-first (106), the part panel (107), Find your range (108), the Sing coach (66), Session settings (67), Sing the whole song (98), peaks8, offline for every app address, accessibility from 5d's audit, and the Sentry reporter (0179ff8a; it waits for auth.config's `sentryDsn`).
  - **Passed at each landing:** the web unit tests and words check (web/tool/test.sh), the touched flows at phone and desktop, and the server suite when locales changed; 5d's app_audit and offline flows pass.
  - **Needs Hillel (device passes):** every milestone's pass on an iPhone (Safari and installed), Android Chrome, Safari and Firefox on a Mac, and Chrome on Windows; a mic on Input 2 of an interface (may be silent on the web); Vue downloading once with the service worker in control (DevTools); the sounds on real devices; reminders' Send a test; the silent switch and Low Power Mode on an iPhone; Bluetooth and wired timing checks; the key control and real guide voices on a phone; MP3 export on iOS; a console throw once Sentry's DSN is exposed.
  - **Waiting on Hillel's answers:** "so" or "sol"; the course review's four questions (new units Plus, the capstone voicing, one Amazing Grace across units 4, 6 and 7); Jeff Learman's CC0 answer for the jRhodes and Martin samples.
  - **Not done (by scope or held):** M8 to M10 (after launch, behind flags; nothing links to them); the course review's #12, #13 (written, held for the wind-down), #14 and #15; the part cards' M and S in Sing (98); the per-voice preview in the part panel (5d has it); the breath rule (the last half beat before each `br` forgiven, with a breath mark; asked by the backend); the backing overhaul (stashed).
  - **Risks before launch:** device-only audio behaviour (interfaces, Bluetooth drift, iOS sessions) is tested only in Chrome headless; the arrangement fixes reach main only when the backend fast-forwards agent/arrangement-fixes; Sentry sends nothing until `sentryDsn` is in auth.config.

- **2026-10-28** (backend; the frontend reads only `finishers` and `alsoLearned[].id`): **`templates.finishers`' `alsoLearned` lists a song only when at least 5 of the song's finishers share it**; the shape is unchanged.
- **2026-10-28** (backend with the frontend, for My parts, 91): **a beta submission gains `adminNoteAt`** (when Hillel wrote the note) **and `url`** (the singer's own take, signed for 15 minutes, once processed). The proposed "Email me when it opens" isn't built: the beta plan sends singers no automatic email (Hillel hasn't changed it); a song not yet reviewed shows as such in My parts.
- **2026-10-28** (backend; Hillel: "drop old 13+ answer"; the frontend (957ba095) and mobile (71bcc8d) switched first): **`ageConfirmed` is gone.** `auth.register` needs `birthMonth` and `birthYear`, and refuses `ageConfirmed` (`422`), as `account.update` does; the account no longer carries `ageConfirmed` (use `birthGiven`). An account that ticked only the old "13 or over" keeps working, and the clients ask its month and year once.
- **2026-10-28** (backend, from the privacy audit; the frontend told): **`harmonies.update` refuses `mayFeature: true` for an owner under 18** (`403 adult_required`, already in its table), as the gallery does: featuring on our accounts is public. The admin's feature action skips such a harmony.
- **2026-10-28** (backend; the frontend (38f971f9) and mobile (9fda19b) both read `peaks8` first): **a take's `peaks` as numbers is gone**: every take carries `peaks8` (required), `?peaks=8` is accepted and changes nothing, and a sync page holds up to 1,000,000 peaks. Ships with mobile's 9fda19b.
- **2026-10-28** (mobile, told the backend): **the app asks for `peaks8`** (`sync.pull?peaks=8`, mobile 9fda19b), reading `peaks` first, else `peaks8` as byte / 255. `peaks` as numbers stays until the web asks for `peaks8` too.
- **2026-10-28** (backend with the frontend and mobile; the frontend agreed, mobile asked): **`peaks8`**, additive: `sync.pull`, `projects.show` and `takes.show` take `?peaks=8`, and then a take's `peaks` is `null` and `peaks8` carries them as base64 bytes (0 to 255), about a quarter of the size. Without it, nothing changes. When the web and the apps both ask for it, `peaks` as numbers goes (a later entry). Also: a sync page ends early once its takes' peaks get heavy.
- **2026-10-28** (backend, asked by the frontend for the Storage screen): **`bin.purge`** (`DELETE /api/bin/{kind}/{id}`, "Delete now": `204`, or `404` once gone) and **`bin.purgeTakes`** (`DELETE /api/projects/{id}/older-takes`, "Clear older takes": `200 { freedBytes }`) take things out of the bin for good at once; **`usage.show`**'s `storage` gains `projects` (each project's current `bytes` and older `binBytes`). A restore at the project limit is still `403 project_limit`.
- **2026-10-28** (backend with frontend, agreed): **`auth.config` gains `selfHosted`** (boolean, always sent): true on a self-hosted install (`SELF_HOSTED`), where every account has Plus and the app hides the Plus page link, Get Plus and See Plus; false on stackapella.com.
- **2026-10-28** (backend with frontend, agreed): **the open-source launch files are shared root files** in the commit hook, as `README.md` and `CONTENT-LICENSE` are: `CONTRIBUTING.md`, `SECURITY.md`, `THIRD-PARTY-NOTICES.md` and `LICENSE.asc` (docs/open-source-plan.md, 6.2).
- **2026-10-28** (backend with frontend, agreed): **the leak guard tightened** after the security review: the hooks and `server/tool/leak-check.sh` need gitleaks 8.25 or later, read staged names with spaces, and refuse any `*.env` file; a new `.githooks/pre-push` runs gitleaks over the commits being pushed; `.gitleaks.toml` allows only `abuse-test.php`'s one test passphrase, not the whole file. `web/sounds/` and `web/tests/fixtures/` audio pass both hooks as before.
- **2026-10-28** (backend with the landing agent and frontend, agreed): **sign in by email**: `auth.link.send` (always `202`, a one-time 15-minute link; `intended`, `invite` and `early` carried) and `auth.link.open` (`200` with the account, `created`, `intended` and `needsProfile`; `410 link_used` or `link_expired`, `403 suspended`, `423 paused`). A new address's account is made verified, with its name and age asked after.
- **2026-10-27** (backend with frontend, agreed; mobile told): **"When were you born?"**: `auth.register` and `account.update` take `birthMonth` and `birthYear` (month and year only; `account.update` once), a birthday counting as the last day of its month; under 13 is `422 too_young` (the code alone) and nothing is kept; `account` gains `isAdult` and `birthGiven` (never the date). `ageConfirmed: true` still works from older apps, and `adult: true` still counts for accounts without a birth date, never against one that says younger.
- **2026-10-27** (backend, asked by the landing agent; frontend told): **groups.show**'s `link` gains `url` for the director (null for a link made before links were kept: Reset link makes one); **support.create** takes the categories `bug` and `idea` too (the admin inbox sorts them), the six before kept.
- **2026-10-27** (backend, for the open-source plan; frontend and mobile told): the mudeo examples' made-up addresses are on example.com, .net and .org instead of real consumer domains (`ana@example.com`, `sam@example.net`, `old@example.org`); no field changes.
- **2026-10-27** (backend with mobile, agreed): **push to the native apps**: `account.devices.push.update` (`PUT /api/account/devices/current/push`, `{ platform: apns|fcm, token, environment }`) and `.delete`, for the bearer's device only (`403 app_only` for a browser); devices gain `push`; `account` gains `pushTypes` (each bell kind, on unless turned off) and `quietHours`, both settable by `account.update`. Pushes: one per new bell item, `{ title, body, data: { type, url } }` with `url` an app path, held through quiet hours, dropped after 12 hours, no badges; sent only while `APP_PUSH_ENABLED`.
- **2026-10-27** (backend, from the performance pass; frontend and mobile told): **caching**: the public read-mostly reads (the catalogue, the gallery's lists, comments, fills, the ideas shelf and an idea, a choir and its voices, the prices) answer `private, no-cache` with an ETag, and `304` to a matching `If-None-Match` (general rules, "Responses"). Nothing else changes; a client that sends no `If-None-Match` sees only 200s.
- **2026-10-27** (backend with frontend and mobile, agreed): **the real-instrument sample manifest**, format 1 ([instruments.md](instruments.md)): zones of MP3s with their frames, encoder delay and onset, the rule for finding the attack in any decoder (half the delay as the line, scaled by a resampling decoder's rate), pieces for kits, and an optional per-set `gain`.
- **2026-10-27** (backend, from the content review; frontend told): **template.json** `voicings` takes "1" to "6" (it listed only 2 to 4, while the rules allow 1 up to the number of parts); **takes.create** lists its `429 rate_limited` for the daily upload bytes (example added).
- **2026-10-27** (backend, from the content review; frontend told): **account.learning.undoReset** brings back only `lessons` and `testedOut` from before the reset, keeping XP, practice and badges earned since; a second reset inside the undo window keeps the first one's copy.
- **2026-10-27** (backend, from the auth review; mobile told): **the apps' device tokens**: sign-in's `device.replace` is honoured only with that device's access token as the bearer (else a new device, emailed); linking keeps `signedInAt`; linking with an access token past its hour answers `401 token_expired`, not `unauthenticated`; paused sign-ups by Google or Apple answer `423 paused` as documented (the server said 503); the refresh limit and `X-App-Platform` text now match the server.
- **2026-10-27** (backend with frontend, frontend asked): **account.update**'s `heardFrom` also takes `instagram`, `youtube` and `app_store` (the lesson-results screen's chips); the older answers stay valid.
- **2026-10-27** (backend, from the money review; frontend and mobile told): **billing.store.sync** says when it answers 200, 409 and 422 (200 when any purchase was linked; 409 when none was and one is another account's; 422 when none was and one failed the checks), and lists its `429 rate_limited` and `503 unavailable` (examples added); a purchase follows the account the store's current record names. **billing.show** for Plus from a store says `status: active` (it said `none`), `endedAt` and `endedReason` stay null while on Plus, `source` is the store when the web subscription is cancelled but a store one renews, and `plusOfflineUntil` stops at Plus's end only when nothing renews it.
- **2026-10-27** (backend with frontend, agreed): **Start the course over**: `account.learning` gains `epoch`, `resetAt` and `undoUntil`; `account.learning.update` takes `epoch` (`409 learning_reset` for an older one); new `account.learning.reset` and `account.learning.undoReset` (`409 nothing_to_undo`, `410 undo_expired`).
- **2026-10-27** (backend with frontend, agreed): **the song builder's record and store testers**: `template.json` gains the optional `builder` (the progression, sound, mix and sections, so a song reopens in the builder); `admin.people.storeTester.update` and `admin.storeTesters.list`, and `admin.account.json` gains `storeTesterSince`. Additive.
- **2026-10-27** (backend with frontend, agreed): **instrument parts** (docs/instrument-parts-plan.md): a template part's `key` is free (`^[a-z][a-z0-9-]{0,39}$`) with an optional `kind` (`voice` or `instrument`), up to 6 parts in people's own templates (official stay SATB voices); `part.json` gains `kind`; `harmonies.create`'s tracks take `kind`; the `/h` view model gains `creditLine` and each part's `kind`. The minimum app version moves when the frontend's first build with free keys ships.
- **2026-10-27** (backend with the landing agent): **the `/h/<id>` page's view model** (and the embed's) gains `parts`: each part's name, colour, place (`index`) and its own 120-point waveform. Additive.
- **2026-10-27** (backend with frontend, agreed): **their own accent**: the account's `appearance` gains `customAccent` (`#rrggbb` or null, required with `accent: "custom"`), and the accents gain `indigo` and `custom`. Additive.
- **2026-10-27** (backend, wording only): **`billing.store.sync`** says that Apple's Server API answer is what counts (`503 unavailable` when a store doesn't answer) and that sandbox purchases give Plus only to store testers.
- **2026-10-27** (backend with mobile, agreed; frontend told): **Plus bought in the apps**: `billing.store.sync` (Apple's signed transactions or Google's purchase tokens, for a purchase or a restore; `409 store_linked_elsewhere` with `billing`, `422 invalid_purchase`), and `billing.show` gains `source`, `store`, `storeAccountToken` and the `plus_twice` notice. Additive.
- **2026-10-27** (backend with frontend, agreed): **the consent to start Plus at once** (EU, EEA and UK): `billing.prices` gains `startNowConsent` (boolean), and `billing.checkout` takes `startNowConsent` (422 on it when needed and not true). The server keeps the words' version.
- **2026-10-27** (backend with frontend and mobile, agreed): **`auth.config.instrumentsIndexUrl`** (string or null): where the real-instrument samples are listed on the media CDN; null where instruments are off. Additive.
- **2026-10-27** (backend with frontend, agreed): **a guide's credit can link to the singer's profile**: `guide.recording.json` gains `creditHandle` (string or null), set only when the singer is credited by name and has a public profile. Additive.
- **2026-10-27** (backend with mobile, agreed): **a nonce on the apps' Google and Apple sign-ins** (`auth.app.google`, `auth.app.apple`): `nonce` in the request, required for Apple (its SHA-256 given to Apple per sign-in), optional for Google (per launch, as its SDK allows); each ID token signs in once. Apps only; the web is unchanged.
- **2026-10-27** (mobile with backend, agreed): **the apps' device tokens** ("The apps: device tokens"): bearer auth for the native apps, `auth.app.signin/google/apple/verify/refresh/signout`, `account.devices.list/delete` and `appPass.claim`, with their schemas and examples. Additive; the web is unchanged (its `api.js` skips operations whose Who starts with "An app").
- **2026-10-27** (backend, report): the review's highs, mediums and lows are fixed; N1 to N7 are
  built on the server behind their flags (gallery, leagues, template library, coach directory,
  open parts, virtual choir, song requests), with Ideas, the two hosts, the shared invite link, the
  passkeys on the parent domain and the beta take bar. Three independent reviews found and fixed
  more. Still open: real Apple sign-in untested; instrument parts wait for the song builder's
  contract; demo data for open parts, choirs and coaches' next lessons needs real audio or a
  calendar; Hillel to confirm stems' consent (the friends' isolated takes, on their yes).
- **2026-10-27** (both): the beta take bar, proposed by the frontend: `bar` on `beta.me` and
  `beta.invite.show`, `timingPercent`, `verdict` and `tries` in a submission's `scores`, `422
  below_bar` (with `bar`), and `admin.beta.bar.show` and `.update`.
- **2026-10-27** (backend, from the review of N4 to N7; the frontend told): `admin.choirs.voices.held`
  and `.approve` (reports hold a voice for review); `choirs.leave` works whatever the voice is
  now; a fill must match its original's version and key.
- **2026-10-27** (both): N7, song requests, agreed by message: `requests.list` (with `mine`, as
  the frontend asked), `.create`, `.vote`, `.unvote`, `admin.requests.list` and `.update`;
  `request_ready` in the bell (`request` on each item); `requestId` on `reports.create`.
- **2026-10-27** (backend, from Hillel via 9c; the frontend told): the shared invite link,
  `inviteLink.show` and `admin.invite.show`, `.update` and `.replace`; a sign-up carries its code
  in `early`, as the mudeo link's.
- **2026-10-27** (both): N6, the virtual choir, agreed by message: `choirs.show` (with `minScore`
  and `maxVoicesPerPart`, as the frontend asked), `choirs.voices.list`, `choirs.join` (judged by
  the take's stored report when there is one), `choirs.leave`, `admin.choirs.voices.remove`;
  `choirVoiceId` on `reports.create`; the `choir_voice_removed` notice.
- **2026-10-27** (both): `auth.config` gains `prelaunch` (coming soon is on, whoever asks), asked
  for by the frontend for the draft songs on the home.
- **2026-10-27** (both): N5, open parts, agreed by message: `harmonies.open`, `harmonies.stems`
  (with peaks, as the frontend asked), `harmonies.fills.list`; `parentId` and `fillPart` on
  `harmonies.create` (`409 fill_mismatch`); `openParts`, `openPartsGuide`, `parent` and
  `featuredFill` on harmonies, `openParts` on gallery items, `featuredFillId` on
  `harmonies.update`, `explore.list?tab=open`, and the `fill` bell.
- **2026-10-27** (backend, from the review of N3 and Ideas): `ideas.create` has its own limiter
  (ten a day, `429`), and refuses a tune hidden in the library.
- **2026-10-27** (both): N4, the coach directory, agreed by message: `coaches.list` (filters,
  `sort`, `tab=featured`, page cursor, `meta.styles` and `meta.languages`) and
  `admin.coaches.feature`; `/coaches` renders the frontend's `public.coaches`.
- **2026-10-27** (both): Ideas (your-tune step 3), agreed by message: `ideas.list` (with
  `needs`), `.mine`, `.show` (with `voiceList` and the tune inline as `song`), `.create`,
  `.delete`, `ideas.voices.create`, `.complete`, `.delete` (voices with peaks), `admin.ideas.held`
  and `.decide`; `grant: "idea"` on `templates.show`; `ideaEmails` on the account; `idea_voice`
  and `idea_complete` in the bell (`idea` on each item); `ideaId` on `reports.create`; the
  `idea_hidden` and `idea_voice_hidden` notices.
- **2026-10-27** (both): N3, the template library, agreed by message: `library.list` (card
  fields, part ranges and `meta.genres`, as the frontend asked), `templates.publish` and
  `.unpublish`, `templates.rate` and `.unrate`, `admin.library.held`, `.decide` and `.feature`;
  `library` and `parent` on own templates, `declaration` and `parentId` on `templates.save`,
  `grant: "public"` and `library` on `templates.show`, `templateId` on `reports.create`, and the
  `template_hidden` notice.
- **2026-10-27** (backend, from the review of N1; the frontend told): `403 adult_required` on
  `consentRequests.decide` (a yes) and `admin.gallery.guides.create` (the take's singer);
  `no_take` also covers a take that wasn't in the shared mix; opt-ins from before the gallery
  opens wait for review unless level 3 and need `rightsConfirmed` to be listed.
- **2026-10-27** (both): `account.appearance` gains `accent` (coral, amber, green, teal, sky,
  violet, rose, or null for the palette's own), asked for by the frontend for Hillel's accent
  choice; a choice saved before it reads as null.
- **2026-10-27** (both): N2, leagues and friends' leaderboards, agreed by message (`tier` from 0
  with `tiers`, `movingUp`, `lastWeek`); `423 paused` on comments and follows.
- **2026-10-27** (both): the app moves to app.stackapella.com (docs/app-subdomain-plan.md), agreed
  by message: host routing, `public.moving` for old installs' app pages (`$appUrl`), `links.api`
  for Report across origins, the landing always at the public site's root.
- **2026-10-27** (both): N1, the gallery, agreed by message and built on the server: profiles,
  explore, likes, follows, blocks, comments, friends' consent, the bell, the review queue,
  featuring, reports on comments and profiles, `/@<handle>` and `/explore` (the frontend's stubs
  `public.profile` and `public.explore`). The gallery's screens are the frontend's, after L.
- **2026-10-27** (both): the landing at `/` for first-time visitors (the app's `sa_app` marker and
  `/?app` as the installed app's start), `/pricing` and `/help`, agreed by message; the beta pass,
  `admin.preview.*` and `auth.config`'s `comingSoon` before them.
- **2026-10-27** (both): the mudeo email goes through the app, agreed by message:
  `admin.mudeo.send` and `admin.mudeo.test`, `dailyCap` (in place of `workspace`), `newsReady`,
  `postalAddressSet`, the fuller `lastBatch`, and `409 sent_by_app` on undo.
- **2026-10-27** (both): the mudeo email, agreed by message: `earlyAccess.show`, `early` on
  `auth.register` and the Google and Apple forms, `admin.earlyAccess.*` and `admin.mudeo.*`
  (with `remaining` on next, and `sentToday` and `limit` on `409 daily_limit`). The page is
  `/early/<code>`.
- **2026-10-27** (both): the frontend's ask, for the theme picker: `account.show` has
  `appearance` (`mode` and `colour`, null until chosen) and `account.update` accepts it.
- **2026-10-27** (both): from the backend review's lows, agreed by message: `403 age_unconfirmed`
  on uploading, sharing, joining and buying until the 13-or-over answer; the `group` grant only
  while the director has Plus; `paidAt` null for the editor.
- **2026-10-27** (both): from the backend review (Hillel's instruction to fix its findings), agreed
  by message and committed with `CONTRACT_OK=1`: a take keeps at most 16384 peaks (`framesPerPeak`
  512 or a larger power of two; a9d7615); friends who sang on a harmony are credited, and
  `harmonies.update` answers `409 singers_must_agree` to featuring or the gallery until they agree
  (N1 asks them), and the harmony comes down when their voice leaves (9e65cca); wording only: a
  lesson is paid only after the end it was bought for, a coach's short-notice move leaves the
  singer's refund until the start, and `*.complete` processes a copy of the upload, with a second
  complete at once answering as it is.
- **2026-10-27** (frontend): **M6's frontend done; the milestone closes on the frontend.** Built:
  Share from the studio (all of it or just my part, private or unlisted, Make it sound good, the
  mix rendered from the studio's levels and uploaded once, retried with the same change), the link
  with Copy, the device's Share, Post to X and Post to Bluesky; Share as a video (dark or light,
  with or without the words, progress, the file to the share sheet); the `/h/<id>` page and its
  embed (round play button, the waveform in the parts' colours, credits, Learn this harmony,
  Report with Cloudflare's check in shield mode); the yes to be featured; admin Reports (hide,
  restore, dismiss, remove in sudo). Reviewed: an independent review found 11 problems (a share at
  a slow speed, a mix that depended on the screen open, retries that started over, a token reused
  after a failed report, a waveform no keyboard could move, the admin's note errors in the wrong
  field, a credits line saying "arranged by" twice, an embed too tall for its frame, and smaller
  ones); all fixed (97f43c0). Checks: the unit suite and the flows on phone and desktop, and against
  :8000 sharing end to end. The M6 device pass is Needs Hillel item 43. Next: M7's frontend.
- **2026-10-27** (frontend): **M5's frontend done; the milestone closes on the frontend.** Built:
  Wait for me in the course's Try steps (one note at a time on the audio clock), Nice and Perfect,
  a combo, and the Stage's tuning meter; How it went after a take (stars, details, a tip a visit,
  each phrase's best, the chart with your line, replay), with bleed said calmly; Your voice (range on
  a keyboard, the voice it suits, 26 badges, history with Plus and a sample without); Today's
  workout (warm-up, weak spot, review, one a day free); the Plus page from docs/features.md, upgrade
  moments, Checkout and the portal, MP3 (lamejs, pinned with SRI) and each part as a ZIP; Plus songs
  from the account (the five left the app's files; backend 440c560) with their preview clips;
  read-only projects and choosing which stay editable; practice reminders by web push; What's next
  and song links (`/?song=`); the history's numbers going up (take reports with their takes,
  exercises by `scores.create`); Happy Birthday (the fourth free song) and unit 8's lessons, drafted.
  Reviewed: an independent review found 14 problems (What's next after a harmony never showed, the
  change since last time never showed, take reports could miss the history, sign-out could hang,
  Plus could invite a second Checkout, and smaller ones); all fixed, one logged (903efcb). Checks:
  the unit suite and the flows on phone and desktop (new: report, voice, workout, plus, reminders,
  whatsnext, locked, trim, export_polish), and against :8000 the two-device sync and sharing. The M5
  device pass is Needs Hillel item 42. Next: M6's frontend (sharing is under way: 31e5574).
- **2026-10-27** (backend): **The backend's final report: every milestone through L built,
  reviewed and landed.** L in four parts:
  - **Running it** (500e935): Sentry with scrubbing (no person, address, token, IP, request body
    or stack-frame arguments; a `/log/errors` tunnel for the browser), readiness checks with
    alerts and `app:doctor`, the heartbeat, hourly alerts and the 07:00 digest, nightly database
    backups and weekly file copies, the monthly restore drill, the canonical-host redirect,
    `security.txt`, the bounce webhook, and `docs/runbook.md`.
  - **Support and the DSA** (40198f6, ea16306, ae454d1): Contact us with context and saved
    replies, statements of reasons for every restriction with appeals, refunds of Plus in admin.
  - **Counts and pages** (64fba0a, eef3dd8): counts from our own records, the network features
    panel, What's next's finishers, the song, guide and legal pages, the sitemap and robots.
  - **The reviews' fixes** (4e7bfae), the legal drafts (c518f3c) and `docs/features.md` with the
    claims test (195ace8).

  **Reviewed:** two fresh subagents, one on correctness and one on security and abuse, plus my
  own pass. 21 findings, all fixed with tests, except two settled by decision: the backups' key
  (now its own, item 41) and Stripe signatures outside production (now required everywhere but
  local and testing). The most serious:
  - the restore drill could empty the live database if pointed at it (now refused);
  - reversing an appeal on a removed harmony recorded "reversed" but undid nothing (now refused,
    `409 cannot_reverse`, and in sudo);
  - push endpoints could aim the server at internal addresses (now real push services only, no
    redirects);
  - three signed-out networks could hide any page (now one verified account is needed);
  - Sentry would have received stack-frame arguments;
  - a static `robots.txt` hid the server's own.

  **Passed:** `php artisan test --compact` (508 tests), Pint, PHPStan; each landing on main
  fast-forward only. **Done when** (the A3 launch checklist, the abuse test, Hillel's go) waits on
  items 40 and 41.

  **Risks:**
  - The Event Schedule and Invoice Ninja integrations are built against fakes, untested against
    the real services (item 34 starts ES's side).
  - Social posts on the beta need Hillel's approval, and X needs credits.
  - Reconnecting a different Event Schedule account is refused while lessons are upcoming.
  - The drill needs a database the app may fill and empty.
  - Share videos aren't posted natively.
  - `happy-birthday` is a free song in config without its template yet (the frontend is told).
  - The legal pages are drafts until the lawyer's review.
- **2026-10-27** (backend): **L part 3's contract and backend**: counts from our own records (a
  finished song is every part sung by its owner; singers active in a week), `admin.overview`'s
  `harmonies` and `growth`, `harmonies.update`'s `inGallery`, `templates.finishers` (What's next,
  shown from 20 finishers), the network features panel (`admin.network.show`/`.update`, N1 to N7
  with their thresholds, a status change in the digest), and the public songs, guides and legal
  pages (`public.songs`, `public.song`, `public.guide`, `public.legal`, `public.page-gone`) with
  `sitemap.xml` and `robots.txt`, kept out of search until `SITE_INDEXING=true`. Four guides and
  the security policy drafted. The page stubs are the frontend's.
- **2026-10-27** (frontend): **M4's frontend done; the milestone closes on the frontend.** Built:
  IndexedDB version 3 and the sync (the outbox in order, conflicts, the pull, moving the device's
  projects in on the first sign-in, "Not saved to your account" past the plan); "Changed on another
  device" (Keep mine or Use theirs, and Delete it or Keep their version for a delete); the open
  project reloading with another device's change; the Storage page (the meter, amber from 80%,
  whether everything is saved, each project's size, Recently deleted with Restore); Plus without a
  project limit; course progress with the account (`account.learning`, merged to the best of
  both); trimming (the Trim sheet in the studio and on Just recorded, Trim the silence, Line up every
  part, with Undo); Make it sound good (worked out in code: BS.1770 loudness, levelled parts, a
  delay-free compressor, a seeded room, -14 LUFS, -1 dBFS; on Export with a Room choice, and Hear
  it polished live); practice tracks (everything but my part, or my part louder, with a click and a
  count-in). Reviewed: an independent review found 14 problems, most in the sync (a waiting take
  could be overwritten, a stale screen could overwrite another device's change, delete conflicts,
  Undo after a delete, account switching); all fixed with tests (c235f21). Checks: the unit suite,
  the flows on phone and desktop, and a two-device flow against :8000 (a phone records, a Mac signs
  in and opens it with the stars, a conflict asks, the bin restores, the 4th project stays on the
  device). Also found and fixed: the sign-in page went blank when the server offered Event Schedule.
  The M4 device pass is Needs Hillel item 39. Next: M5's frontend.
- **2026-10-27** (backend): **M10's backend built and reviewed; its contract agreed with the
  frontend** (`invoiceNinja.*`, `admin.books.*`, `admin.social.*`; `mayFeature`, `keepsBooks`,
  `methods.eventSchedule`, `plusPaidUntil`, `problemCode`; `POST /auth/event-schedule`). Built:
  coaching's money as a ledger (each sale and refund once, from the engagements as they change and
  from Stripe's package invoices); each coach's books in their own Invoice Ninja (invoice and
  payment per sale, the commission as an expense and given back with refunds, refunds on the
  payment in order, emails off unless asked, the last 12 months on connecting, a "not recorded"
  list with Retry, a refused token paused until reconnecting); Stackapella's own books (Plus
  payments with their tax, refunds, a monthly commission invoice per coach carrying over negative
  months); Plus paid by invoice (completed payments covering it, read from Invoice Ninja, by
  webhook and hourly); our X and Bluesky posts (new songs when first reviewed, the song of the
  month, harmonies whose singers said yes; approved by Hillel, a 2-minute undo, production only,
  claimed and sent once, X without links); Sign in with Event Schedule (matched on its account
  only, no address taken). Demo: Grace's and Stackapella's books in the fake Invoice Ninja, Ada's
  harmony may be featured. **Reviews:** an independent subagent's 2 high and 6 medium findings
  and the smaller ones, fixed with tests (`docs/decisions.md`); the frontend's six points (trust
  for an Event Schedule account, problem codes, both spellings on delete, 404 against no
  connection, `plusPaidUntil`). 486 tests pass; Pint and PHPStan clean. The migration
  (`2026_11_01_100000`) adds tables and nullable columns (and `coaching_payments.backfilled`, in
  a new table). Also: the coming-soon page asks beta singers to sign in, not for a code (9c's
  find), and `sync.pull` takes back the cursor of an account with no changes (the frontend's).
- **2026-10-27** (frontend): **M3's frontend done; the milestone closes on the frontend.** Built:
  Sing (the whole song and part by part, then redesigned after Hillel's 2a and 2b: the part chip
  and Your part, mode chips and Settings, one calm transport row, the turn band, the big Stage, the
  Note lane with your voice live and the notes you hit lit, the coach drawn like the home's course
  band, Just recorded with the waveform stack); the course, part 1 (the map at /learn, the lesson
  player, stars, XP, levels, the weekly goal with a 2, 3 or 5 day choice, Find your note listening
  live with Skip for now, one way in from lesson 1); the new home (direction C, the stack);
  accounts (sign-in with email, Google and Apple, verify, reset, Settings, export, delete; the
  heard-from question); chords underneath; slow practice; note names your way; the kind note about
  the real voice's key; re-sing a phrase (brought forward from M5, with Hillel's join rules); the
  install card and "only on this device"; one styled confirmation instead of the browser's, with
  Undo; leaving Sing goes back where you came from; the studio's practice bar removed (the studio
  mixes, Sing practises); the admin's picking of the best singers (stats, one singer's takes, the
  best-on-pitch version). Reviewed: an independent review of the course, first run and home found
  18 problems; all fixed (e420ee5). Checks: the unit suite and every flow pass on phone and
  desktop; the accounts integration flow (sign up, verify from the logged email, sign out and in,
  export, delete) passed end to end against :8000 on the phone, and a later run's verify page came
  up blank before the sign-up limit (5 an hour) stopped further runs; to check again once it
  resets. The M3 device pass is Needs Hillel item 36. IndexedDB stays at version 2 (no new stores).
  Next: M4's frontend (the sync, the bin, storage, trims, Make it sound good), then M5 to M10.
- **2026-10-27** (backend): **M9's backend built and reviewed, against the fake Event Schedule;
  its contract agreed with the frontend** (`eventSchedule.*`, `coaches.slots`,
  `groups.eventSchedule.*`, `groups.rehearsals.*`, `admin.eventSchedule.show`, and lessons in
  `engagements.*`, `coach.offerings.*` and `reviews.create`). Built: connecting by OAuth with PKCE
  or a selfhosted key (encrypted tokens, never sent to the app; selfhosted calls pinned to a
  checked public address); picking a schedule registers ES's webhook; lesson offerings linked to
  ES appointment types at the coach's own price; slots cached 30 seconds in the singer's zone;
  pay first, then a queued, idempotent booking in ES as already paid, refunded in full when the
  time went or ES never booked it; ES's webhooks (`/webhooks/event-schedule/<connection>`, the v2
  signature, a 5-minute window, once per delivery, queued); cancellations under the coach's
  policy as bought; the share moved once a lesson has ended; the hourly reconcile and Hillel's
  health screen; choirs linked to a schedule with each member's part and progress per rehearsal,
  and the schedule's members for the director (never emailed); oEmbed and `/embed/h/<id>` (the
  only page other sites may frame; plain stubs for the frontend's markup). The demo seeds Grace's
  booked lesson with Ada and Pia's linked choir when the fake is on, and `php artisan
  app:event-schedule:fake` plays ES's side. What ES must build is `contract/event-schedule.md`
  (item 34). **Reviews:** an independent subagent's four money findings and the smaller ones,
  all fixed with tests (`docs/decisions.md`), plus my own checklist pass. 460 tests pass; Pint
  and PHPStan clean. The migration (`2026_10_31_100000`) adds tables and nullable columns only.
- **2026-10-27** (backend): **M8's backend built and reviewed; its contract agreed with the
  frontend.** Built: applications with a sample review and Hillel's decision; coaches' profiles
  (`/coach/<handle>`, no session, noindex) with free Plus while active; Stripe Express onboarding
  and Connect's own webhook; take reviews (paid when asked, 48 hours to accept, the coach's share
  moved on delivery after a 10-minute Unsend, refunds in full for a decline, no answer, a late
  review or an early cancel), monthly packages (a subscription with the fee, reviews a month),
  exercise packs (free or one-off, reviewed); the review workspace (the singer's own copy of a take,
  the pitch track, pinned comments, voice notes and demos as AAC, one follow-up); messages (pay
  elsewhere warned and flagged); ratings and one reply; Send to my teacher (Plus); the admin's
  applications, coaches, packs, disputes and refunds; a demo coach. **Reviews:** an independent
  subagent's 9 high and 11 medium findings (double refunds, payments after cancelling, paid
  checkouts deleted, one failure stopping the deadlines, and more), all fixed with tests
  (`docs/decisions.md`); the frontend's asks (comment ids, exercise references, the pitch track as
  sent).

- **2026-10-27** (backend): **M7's backend built and reviewed; its contract agreed with the
  frontend (its full screen review to come, additive).** Built: people's own templates on the
  server (device UUIDs, immutable versions, private or unlisted, 1 free and any on Plus, read-only
  past the limit after a downgrade, the library opt-in kept for later, opened by the owner, the
  link, a choir group or a project made from it); the creator's recorded guides (Plus, AAC in
  private, counted toward storage); friends on a project (invite links, singers on their own parts,
  the owner's account for storage and sync, leaving with or without the parts, 410 for a removed
  singer); choir groups (Plus directors, 60 members, the join link, songs, assignments, who has
  recorded what; members learn the group's Plus songs). **Reviews:** an independent subagent's 13
  findings, all fixed with tests (a singer unable to open the owner's private template, an ETag
  outliving its links, recordings never cleaned up, group changes not reaching members' devices,
  the export missing their singing, lock order between two people, and more), then the frontend's
  asks (avatars, last activity, removed vs deleted).

- **2026-10-27** (backend): **M6's backend built and reviewed; its contract agreed with the
  frontend.** Built: harmonies shared by link (the mix rendered on the device and uploaded like a
  take; the mix check against the project; `ProcessHarmony` makes the AAC, the waveform and the
  1200 x 630 card in our font; copies on the CDN only while the page is live; just my part);
  random 12-character harmony ids (the link's only secret); Share as a video (`RenderShareVideo`:
  GD draws the title, the parts' moving waveforms, karaoke words timed from the template and the
  end card, ffmpeg moves and encodes them; 2 renders at once for everyone, 7 days kept); share
  limits by trust level; links refused in titles, word-list titles held; Report without an
  account (counted by network, 3 hide the page); the admin queue with hide, restore and remove;
  suspension hides what someone shared; sign-ups from a share recorded; the `/h/<id>` page's data
  (no session, no cookies, noindex); the copyright policy drafted; a demo harmony and a reported
  one. **Reviews:** an independent subagent's 18 findings, all fixed with tests (public copies
  forgotten before deletion, an unpublished ready harmony, three throwaway accounts hiding a page,
  harmony uploads without limits or storage, enumerable Sqids, unfiltered part names, and more:
  `docs/decisions.md`), then the frontend's review (the upload window for the outbox).

- **2026-10-26** (backend): **M5's backend built and reviewed; its contract agreed with the
  frontend.** Built: Plus by Stripe Checkout and the portal through Cashier, with Stripe Tax, the
  trial once per account and per card, our webhook (failed payments, how Plus ended, disputes, the
  trial-ending and payment-failed emails), `billing.prices` and `billing.show` (pending, the
  offline grace), the Checkout return page; Plus songs by `templates.show` (Plus, the song of the
  month, kept songs, the daily cap and ETag); read-only projects after a downgrade
  (`projects.editable.update`); the score history; practice reminders by web push (VAPID, every
  15 minutes, device tokens); disputes in admin; trust level 3; a Plus demo account. Also: beta
  singers' pay and minutes never shown to them (tested), the admin's "who did well" figures, the
  chords check. **Reviews:** the frontend's nine contract changes (read-only reasons, the return
  page signed out, templates.show's grant, the service worker's device tokens, how Plus ended,
  public prices, one way in for scores, the codes, the offline grace), then an independent
  subagent's 11 findings, all fixed with tests (a chargeback that never reached the account,
  forged webhooks without the secret, deleted accounts still billed, a redelivery cancelling a
  trial, and more: `docs/decisions.md`).

- **2026-10-26** (frontend): **M2's frontend done; the milestone closes.** Built: real voices in
  the player (original key and full speed, else the synth with a note saying why; the Real voice /
  Synth switch; credits on the part's badge, in the template details and the export's notes); the
  singer's pages (the link, the agreement, My parts in their words, the code for the installed
  app, Tell Hillel); recording for Hillel (the required timing check, the sound check, the
  quality check before sending with phrases to loop, the previous take kept, Hear it as learners
  will at learners' level, the outbox that sends at launch and when back online); the admin's
  review (the queue, the mix and A/B, the pitch chart, light fixes heard as they're set, accept,
  ask for another take pinned to a phrase, reject, publishing followed, the keys), singers
  (invite with the link shown once, Share, status messages, new link, revoke, paid and forget in
  sudo), real voices per song, the feedback inbox, and the home's numbers; `docs/beta-guide.md`
  for Hillel. **Checks:** every flow on the mock at both sizes; `beta_integration` on the real
  server end to end (a singer records and sends, Hillel accepts, a learner hears the alto with
  its credit). **Reviews:** an independent review per section (C: a re-record sent the first
  take, a noise check that warned on soft singing, a lost answer that could send a take twice,
  and more; D: preview and published guide disagreeing on the start trim, races between takes,
  unsaved fixes lost on J/K, and more), all fixed; then a milestone review against the plan (12
  gaps: Plus songs' voices for singers, sending at launch, picking the better take, the preview
  level, the speed note, the install card, the Wi-Fi tip, the device checklist), fixed or decided
  in `docs/decisions.md`. Also: M3's and M4's contracts reviewed and agreed (three and nine
  changes), and `api.js` has their calls.

- **2026-10-26** (backend): **M4's backend built and reviewed; its contract agreed with the
  frontend.** Built: projects, parts and takes by the device's UUIDs, with versions, `changeId`
  replays and conflicts carrying the server's copy; `sync.pull` by a per-account change number,
  with tombstones and `410 cursor_expired`; takes by presigned PUT, checked and kept as FLAC with
  their peaks (`ProcessTake` on `long`); the bin (a replaced take waits for its replacement to be
  ready; Undo by `takes.restore` or a part save) and its hourly purge; plans and limits (3
  projects and 1 GB free, `usage.show`); course progress (`account.learning`); trust level 2; a
  deleted account's recordings; projects in Download my data; Ada's demo project. **Reviews:** the
  frontend's nine contract changes (the take's own offset, `changeId`, versioned deletes, parents
  first, pending uploads, cursor guarantees, storage net of the current take, wording, rates);
  an independent subagent's 9 findings, all fixed with tests (a pull that could skip a change,
  change numbers outside their transaction, the purge racing a restore, unlocked limit checks,
  restore past the storage, unbounded upload PUTs, orphaned takes; `docs/decisions.md`). Also
  on main: paused and read-only answer 423 (e7cc42e), from the live beta.

- **2026-10-26** (backend): **M3's backend built and reviewed; its contract agreed with the
  frontend.** Built: email accounts (sign-up with the honeypot and the device-timed bot check,
  the verification link, resend within the address's caps), sign-in with a password, Google and
  Apple (Apple's form_post finishing in the browser that started), linking from Settings, signing
  out and out everywhere, the reset link, the profile and handle (look-alikes, a 30-day hold on the
  latest old handle), the email change with an undo link, the password, removing a method, the
  avatar, Download my data, deleting the account, the new-browser email, trust levels 0 and 1,
  shield mode's Turnstile, the admin's People API (find, suspend, trust, recovery), the daily
  clean-up, and demo accounts (README). **Review:** an independent subagent found 3 high, 7 medium
  and 3 low issues, all fixed with tests (an Apple link that could take over an account, an undo
  link a later change could cancel, an address claimable before its owner signed up, and more:
  `docs/decisions.md`). The frontend's review moved the bot check to `elapsedMs`, documented the
  sign-in forms' CSRF (`_token`, and `?authError=expired` for a stale one), and gave the undo link
  the restored address. The tests found an account deletion that put the row back (signing out
  saved a new remember token). Also this week, on main: the deploy's template sync accepts what
  the first deploy published (efc5670), the coming-soon page links singers to their code page, My
  parts marks the singer's own voice, and a first admin is shown their Google ID (0cb906d).

- **2026-10-26** (frontend): **M1 frontend done and reviewed.** Built: db.js version 2 (takes as
  16-bit); `i18n.js` and `locales/en.json` (every string behind `t()`, the key check, the
  pseudo-language and its flow); the session store, extracted from the studio unchanged (pinned by
  the `studio_template` flow first); `pitchtrack.js` and `scoring.js` on synthesized singing;
  `api.js`, the mock and `serve.php`'s API switch; the styleguide, the admin shell and the trust
  and safety screens; the testing guide (`web/tests/README.md`). The integration check passed on
  `:8000`. **Reviews:** an independent review of every section (seven fresh subagents) plus my own
  pass; the real findings fixed, each with a test: a path traversal in `serve.php`'s `/contract/`
  (it could serve `server/.env`), the service worker storing server pages as the app shell, Play
  during the guides' render playing silence for good, saves dropped or lost on leaving, noisy
  singing read as wrong octaves, short rests read as early entries, legato read as scoops, the
  iPhone's own mic called wireless, a mic leaking after leaving, a hung timing check on a call,
  lists that looked empty when they'd failed, and more (the commits say each). **One slip:** I
  landed once after a rebase without re-running the tests, and main's web contract test failed
  for a few minutes; the landing script now always re-runs them after a rebase.

- **2026-10-26** (frontend): **The M1 retrospective, the frontend's half** (the backend adds its own):
  1. *Missed or invented that the docs should have prevented:* the plans said "a take's measured
     delay is sent", but not that the engine already removes it, so the M2 contract nearly made
     the server subtract it twice (caught in review; now in `api.md`). Part names "are keys", but
     existing projects stored them in English, which needed `slotName()`. Nothing said how an
     IndexedDB upgrade meets an old tab (blocked) or a newer one (versionchange).
  2. *Rules hard to find when needed:* how the hook treats root files (`.gitignore` is outside
     both agents' folders: use `web/.gitignore`); that two agents (and review subagents) running
     `tool/test.sh` at once collide on its port (use `PORT=`); that flows under the mock need the
     server's own pages stood in for.
  3. *Where we waited:* almost nowhere. Contracts were agreed within the hour by message; the
     backend ran ahead and drafted M2 and M3 while I finished M1, which worked well. A message
     relayed from a third session ("you may push") conflicted with our instructions; both agents
     declined and put it on Hillel's list.
  4. *For "Never break these":* "A take's frame 0 is the position recording started from (the
     latency is already removed)"; "Every async step in a store re-checks the open project after
     each wait"; "Land only with the tests run after the last rebase"; "Test pitch features on
     singing that glides, breathes and drops octaves, not only on a tidy synth".
- **2026-10-26** (backend): **The M1 retrospective, the backend's half:**
  1. *Missed or invented that the docs should have prevented:* the plans promised things the
     services don't do: a presigned PUT "signed for the exact Content-Length" (Spaces' signer never
     signs it; the server now checks and deletes), Cloudflare email "as raw MIME" (its API takes
     JSON), and the PHP buildpack serving `/web` (it only sees `server/`; now one Docker image).
     Two plan rules collided: orphaned uploads and exports both on `tmp/`. Immutable versions
     would have broken when the musician flips `reviewed` (now only the music is hashed). The
     roadmap's "first counts" in M2 need projects on the server (M4).
  2. *Rules hard to find when needed:* Laravel checks sign-in before the limiters (signed-out
     floods weren't counted until the priority was set); a `git rebase` recreates `public/`, which
     broke `php artisan serve` (now `tool/serve.php`); JSON test requests send no cookies without
     `withCredentials()`; MySQL refuses a stored generated column on a cascading foreign key; a
     plain-text email view must not escape. And one slip of mine: a landing chain whose test step
     was piped through `head` didn't stop on failure (main stayed green, but only by luck); landing
     now goes through a script that sets drafts aside and checks each result.
  3. *Where we waited:* nowhere. I drafted M2 and M3 ahead while the frontend finished M1; each
     contract was agreed by message within the hour. Relayed instructions from a third session
     (push to GitHub; rewrite the conventions) weren't acted on beyond what our own instructions
     allow, and are on Hillel's list.
  4. *For "Never break these":* "Every refusal is a code in the contract's tables, added there
     before the server sends it"; "Land only with the tests, Pint and Larastan run on exactly what's
     committed, after the last rebase"; "Never trust a presigned URL or the client for an upload:
     the server checks size, hash and the audio itself"; "Changing a link or token ends every
     session it started, and only hashes of tokens are stored".
- **2026-10-26** (backend): **M2 backend reviewed:** an independent review (a fresh subagent) plus my
  own pass. 13 real findings fixed, each with a test: a new link didn't end the old link's
  sessions; trims past the end left an accepted take unpublishable for good, and failed jobs left
  takes stuck; making the default the default cleared it; the presigned PUT didn't bind the size
  (the server now checks and deletes, and clears abandoned uploads hourly); a duplicate part in an
  invite was a 500; long beats overflowed; a pruned master gave a broken URL; an abandoned upload
  hid the real status; levelling could clip; a part could be paid twice, and at a later rate; beta
  sessions expired after 4 idle hours; removal left the CDN copy and the master. The contract gained
  `publish` and `forget` (agreed). Also from my own pass: "coming soon" on the beta domain.
- **2026-10-26** (frontend): M1's integration check passed against the real server (`admin.me`,
  the trust and safety screen).
- **2026-10-26** (both): the M2 contract agreed and committed (the catalogue, the beta singers'
  sessions, consent, parts and takes, Tell Hillel, and the admin's beta review), then built and
  tested on the backend: 34 operations, live on `:8000` (`php artisan db:seed` adds the demo
  singers). The frontend builds M2's screens on the mock after closing M1.
- **2026-10-26** (both): agreed: the CSRF rule says which responses set `XSRF-TOKEN` (the app's
  first call is a `GET`), and the errors table gains `400 bad_request` and `503 unavailable`.
- **2026-10-26** (backend): **M1 backend reviewed:** an independent review (a fresh subagent given
  the code), plus my own pass. 14 real findings fixed, each with a test: a second-factor lockout
  that parallel guesses could beat; the local upload stand-in refused by CSRF; an array `email`
  turning any API call into a 500; dot segments reaching `web/tests` and `web/tool`; IPv6 limits
  per address instead of per /64; IPs not in one spelling; card fingerprints matched without case;
  block expiries stored in local time; duplicate writes at once as 500s; a dotted flag name; the
  app's CSP allowing the whole CDN host; nginx dropping `Referrer-Policy`; failed passkeys not
  recorded; production sessions shorter than the admin's idle limit. Also: the app shell is
  sessionless, list filters and cursors are validated, `/up` is limited, anyone signed in can sign
  out. Two contract wording fixes proposed to the frontend.
- **2026-10-26** (both): agreed and committed: the trust and safety screen's operations
  (`admin.flags.list`/`.update`, `admin.limits.list`/`.update` with who changed a limit and why,
  `admin.blocklist.list`/`.create`/`.delete`, `admin.securityEvents.list`, `admin.audit.list`), the
  general rule that a delete answers `204`, and the `401`/`403` codes every `admin.*` operation
  may give (`not_admin`, `second_factor_required`, `sudo_required`). Built and tested on the
  backend; live on `:8000`.
- **2026-10-26** (backend): M1's server landed: the scaffold, tests and CI, the contract checker
  (`assertMatchesContract`, examples and templates against their schemas, routes against
  `api.md`), the admin's Google sign-in with passkeys and the TOTP backup, the Editor role,
  `admin.me`, and the trust and safety basics (real client IP, limiters with live overrides,
  `security_events`, the blocklist, flags and switches, the audit log). Live on `:8000`; the
  deploy image is `.do/Dockerfile` (needs Hillel's OK, item 7).
- **2026-10-26** (frontend): M0's code landed (the wireless-mic note; the mic opened during setup
  with a settle before the count-in; takes kept through interruptions; the context rebuilt at the
  mic's rate on iOS), and M1 section 1, db.js version 2 (takes as 16-bit). **Reviewed:** an
  independent review of each (fresh subagents), plus my own pass; 11 real findings fixed (a mic
  leaking after leaving, a non-atomic rebuild, a hanging timing check on a call, the iPhone's own
  mic called wireless, Firefox's rate refusal, a blocked upgrade hanging the page, a failed open
  cached for the session). Not changed: the wireless note keeps the roadmap's wording.
- **2026-10-26** (frontend): IndexedDB version 2 claimed (16-bit takes, `guides`, `betaTakes`,
  `learning`); the M0 device-run checklist added to "Needs Hillel" (item 6).
- **2026-10-26** (both): the M1 setup agreed by message: the commit hook (`.githooks/pre-commit`),
  the general rules (`api.md`), `admin.me`, `error.json`, and the frontend's `template.json`.
  Committed by the backend with `CONTRACT_OK=1`.
- **2026-10-29** (backend, agreed with the frontend and the landing agent; Hillel's onboarding funnel with local-only firsts and an A/B variant on the device): `counts.create` takes the funnel's `f_*` and `ask_*` kinds and an optional `variant` ("onb1-b"); `auth.config` gains `experiment` (`{ id, arms, enrol }` or null); new `admin.funnel.show` (GET /api/admin/funnel?experiment=|days=).
- **2026-10-29** (backend, agreed with the frontend; Hillel's "Hide profile ... we'd show anonymous"): `account.show`/`account.update` gain `hideProfile`; `person.json` may be "Anonymous" with `handle` and `avatarUrl` null; `profiles.show` 404s for a hidden account. No other shape changes: the values others see change (docs/hide-profile-plan.md).
- **2026-10-29** (backend, security review): an experiment's enrolment ends only on its `end` day, never on counts (anyone can send counts); funnel kinds count once a request whatever `n` says. Descriptions only; no shape change.
- **2026-10-29** (backend, agreed with the frontend and the arranger): `template.json`'s part `key` description names barbershop's tenor, lead (the melody), baritone and bass beside SATB, never mixed. Description only.
- **2026-10-29** (frontend, agreed with the backend): `beta.parts.list.200.json`'s Amazing Grace part gives `template.version` 3, as web/templates/amazing-grace.json is now. Example only; no shape change.
- **2026-10-29** (backend, the arranger's Old Mill Stream): the part-key enums (`catalogue.part.json`, `beta.part.json`, `admin.beta.assignment.json`, `admin.beta.submission.json`) gain barbershop's `lead` and `baritone`, missed with d28a4489.
- **2026-10-29** (backend, agreed with frontend2; Hillel's "keep all takes so users can swap to try"): a replaced take is kept, not binned, and counts toward storage; new `parts.takes.list` (GET /api/parts/{id}/takes), `parts.takes.use` (POST /api/parts/{id}/takes/{takeId}/use, nudge and trims back to 0) and `takes.delete` (DELETE /api/takes/{id}, to the bin; `409 take_current`); `takes.restore` keeps the take it replaces.
- **2026-10-29** (backend, asked by the game agent): `boards.scores.submit` answers `before`, their best on the board before this take (null on a first score or one that didn't count). Additive.
- **2026-10-29** (backend, asked by frontend2; Hillel's "update the admin usage page with all of the new stuff, ie courses, etc"): `counts.create` takes the usage page's new kinds and `<prefix>:<id>` kinds for a Plus course's or a free lesson's starts and finishes, counted only for a real id. No shape change.
