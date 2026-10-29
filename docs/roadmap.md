# Roadmap: the order of work, for two agents

**Status:** agreed in outline (2026-10-26). Nothing past what's built is started. This is the
single order of work: the feature plans hold the detail, and their own "Order of work" lists give
the steps inside a milestone.

**How to read it:**
- each milestone has a **backend track** and a **frontend track**, which run at the same time
  (`docs/parallel-agents-plan.md`);
- the **contract** for a milestone is agreed by the two agents (under Hillel's delegation; he reviews
  the log) before either track builds
  anything that depends on it;
- **work that needs no contract starts at once;**
- **Hillel's gates** are things only he can do. **During the build they never block:** the agents
  use fakes and test modes, and list each gate in "Needs Hillel" (`docs/parallel-agents-plan.md`,
  "Working without Hillel");
- **every section and milestone ends with a thorough review** before the next starts;
- a milestone is **done** when its exit check passes.

The why is in [vision.md](vision.md), and how the pieces fit in [architecture.md](architecture.md).

## The shape: everything that doesn't need a network, at launch

**The rule (Hillel, 2026-10-26; it replaces "a small launch, then grow"):** a feature ships at
launch if it's useful to **one person**, or to **people who already know each other** (by a link,
an invite, a teacher's own students, a choir's own members). A feature waits only if its value
depends on **strangers finding each other**: searching, browsing, rankings, crowds.

| Area | At launch (no network needed) | Waits for a network |
|---|---|---|
| Learning | Real voices, Sing, the whole course, the harmony gym, slow practice, re-sing a phrase, practice tracks | Leagues and friends' leaderboards |
| Sharing harmonies | Share links and videos; **inviting friends to add parts** (collaboration) | The public gallery and Explore, likes, comments, follows, the open parts shelf |
| Making templates | **The template creator:** private and unlisted templates, shared by link; your own recorded guides | **The public template library and its search** |
| Choirs | **Choir groups:** a director shares songs, assigns parts and sees who's learned what; choirs across Event Schedule and Stackapella | The virtual choir (it needs many voices) |
| Coaching | **Coaches apply, get approved and sell** (reviews, lessons through Event Schedule, ongoing coaching, packs) to people who come through **their own profile link**; ratings on their profile; Send to my teacher | **The coach directory,** search and suggestions |
| Business | Plus; Invoice Ninja; our X and Bluesky posts of new songs | Posting featured harmonies (they come from the gallery) |
| Requests | | Song requests and votes |

**Opt-ins are collected from launch,** so each network feature opens already full:
- "List this template in the library when it opens";
- "Show this harmony in the gallery when it opens";
- "Add my voice to this song's choir when it opens";
- coaches are listed in the directory by default when it opens (their agreement says so).

**When a network feature opens is decided by numbers, not guesses.** The admin's **network features
panel** (below) tracks each one against its threshold, and says when to start building it and when
to switch it on.

**The one number that matters in the beta and after:**
- how many people **finish a whole harmony**;
- how many **come back for a second song**.

These are counted on the server from our own records (M2 onwards), never with analytics. If they're
low, the next milestone is fixing the core, not adding features.

**The cost of this rule, said plainly:** the launch is much bigger than "a small launch":
- the template creator;
- the marketplace (without its directory);
- Event Schedule;
- choir groups;
- Invoice Ninja.

So the milestones keep the core first (M0 to M6), and **launch readiness (L) can happen after any
milestone from M6 on,** if Hillel decides to launch with less. Nothing later depends on being in
the launch.

## Start now: things with a long lead time (Hillel)

These take weeks outside our control, and later milestones wait on them:

| Gate | Needed by | Who |
|---|---|---|
| A musician checks the 8 templates, their chords and the course's harmony units (`docs/templates-review.md`) | M2 (recording real voices), M3 | A musician |
| **The launch library** (`docs/templates-plan.md`): about 20 songs, the 8 drafted plus Happy Birthday and a first batch of 11. **The 4 free songs get real voices for every part;** Plus songs get them as beta singers are ready, with the synth otherwise | L | Claude drafts, the musician checks, the beta singers record |
| **The library's pace after launch:** proposed 2 new songs a month, the promise that keeps Plus worth paying for | After launch | The same |
| A voice teacher checks the course's lessons, exercises and tips | M3, M5 | A voice teacher |
| The business that sells: the name on Stripe, the terms, the invoices and the tax registrations | M5 | Hillel |
| **The lawyer:** the beta agreement; then terms, privacy, refunds and the copyright policy; **the coach agreement and the marketplace terms**; community guidelines before the gallery | M2, M6, M8 and L; the gallery after launch | A lawyer |
| **Music licensing:** a first conversation with the lawyer about licensed songs, since it's the biggest limit on the library | Early, for after launch | Hillel and the lawyer |
| **Accounts:** DigitalOcean (App Platform, Managed MySQL, Spaces), the beta domain, and **Cloudflare** (the domain's DNS, the Workers Paid plan, and Email Sending set up for a sending subdomain) | M1 | Hillel |
| An Apple developer account (Sign in with Apple; register the sending domain with Apple's private relay) | M3 | Hillel |
| **Stripe:** the account and Stripe Tax, then **the Connect platform profile** for coaching (Stripe reviews marketplaces) | M5, and Connect by M8 | Hillel |
| An accountant: VAT and sales tax for Plus, then for marketplace fees | M5, M8 | An accountant |
| The DMCA designated agent (US Copyright Office), since shared links host user audio | M6 | Hillel |
| The X developer account (pay per use), and a Bluesky app password | M10 (posting new songs) | Hillel |
| **Cloudflare in front of the app** (DDoS and bot protection, edge rate limits; proposed, as iaw uses it) | M1 | Hillel |
| OK on each new dependency (the table in `docs/backend-plan.md`; Liang hyphenation for the creator; an MP3 encoder) | When first needed (the creator is M7) | Hillel |
| **The choir plan** (price, seats, the rules for members under 18; `docs/pricing-plan.md` #11) | M7 | Hillel |
| **Event Schedule's changes** (OAuth, appointment webhooks, the bookings API), built in that repo | M9 | Hillel and an agent in that repo |

## The milestones to launch

### M0. Prove it on real devices
**Why first:** the timing is proven exact in headless Chrome, but real iPhone, Safari and Firefox
runs are still pending (`docs/audio-spike.md`). Beta singers will record on their phones.

- **Frontend:** run the app's record and timing check on:
  - an iPhone, in Safari and as an installed app;
  - Safari and Firefox on a Mac;
  - with wired, Bluetooth and Continuity mics;
  - **with interruptions:** a phone call, AirPods connecting or disconnecting, the screen
    locking, the app going to the background. The take is kept up to the interruption
    (`docs/operations-plan.md`).

  - **the wireless findings:** Bluetooth and Continuity mics change their delay mid-session (the
    spike measured a 44 ms jump), so the mic picker warns kindly when the chosen mic looks
    wireless (AirPods, Bluetooth, Hands-Free, iPhone): "Wireless mics change their delay while
    you sing. Wired headphones with a built-in mic give the tightest timing";
  - **the mic opened before the count-in** and kept open for the session, so iOS's audio switch
    doesn't land on the first beat (`docs/architecture.md`).

  Fix what breaks.
- **Backend:** nothing yet. M1 can start at the same time.
- **Hillel:** the device runs (with the agent's checklist).
- **Done when:** a take lines up by ear and by the loopback check on each, or the limit is
  documented with a message for the singer.

### M1. Foundations (almost no contract)
- **Backend** (`docs/backend-plan.md` A1, A2):
  - the `/server` scaffold, tests and CI;
  - `.do/app.yaml`; Laravel serving the PWA on one origin;
  - the admin sign-in with the second factor;
  - security headers; the audit log; the local signed upload route;
  - **the contract checker** (`assertMatchesContract`, the route check);
  - **Laravel's `lang/en/` from the first line:** emails, pages and messages through `__()`,
    codes not sentences in the API, and the `__()` key test.
- **Frontend:**
  - **the session store** (Sing step 1, `docs/singing-ux-plan.md`), extracted from `studio.js`
    unchanged;
  - **`pitchtrack.js` and `scoring.js`** (`docs/vocal-coaching-plan.md`), the shared pair;
  - **`api.js` and the mock**, and `serve.php`'s API switch.
  - **`i18n.js` and `locales/en.json`:** every string in the existing screens moves behind `t()`,
    with the missing-key test and the pseudo-language (`docs/architecture.md`, "Languages").
  - **the styleguide page** (`/styleguide`): buttons, form fields in every state, tables, status
    chips, sheets, toasts and empty states, following iaw's styleguide in Stackapella's colours. The
    admin and every form are built from it (`docs/conventions.md`);
  - **a short testing guide** (`web/tests/README.md`): how to add a unit test, a flow script on
    `cdp.py`, and a mock scenario, each with a worked example, so error paths get tested too.
  - **db.js version 2: takes stored as 16-bit** (about 17 MB per 3-minute part instead of 35),
    before beta singers record on their phones (`docs/architecture.md`).
- **Both:** the setup in `docs/parallel-agents-plan.md`:
  - `contract/api.md` (the general rules) and `contract/log.md`;
  - the template JSON Schema;
  - the commit hook, and the role files.
- **Trust and safety** (`docs/trust-and-safety-plan.md`, the M1 row): limiters keyed by user and IP, live overrides, `security_events`, the blocklist, the attack switches, the trust and safety screen.
- **Operations** (`docs/operations-plan.md`): the Editor role, with the admin.
- **Contract:** the general rules, the template schema, `admin.me`.
- **Hillel:** the DO account and beta domain; OK on the first dependencies.
- **Done when:** the app runs from Laravel on the beta domain, Hillel signs in to `/admin` with
  his second factor, and the template schema passes for all 8 templates on both sides (all 8 are
  still in `web/templates` until M5 moves the Plus songs to the server; the test then covers both
  folders).

### M2. Real voices: the beta (`docs/beta-singers-plan.md`)
- **Backend:**
  - the catalogue (`templates`, seeded);
  - invites, the code exchange, consent;
  - submissions and presigned uploads;
  - `ProcessSubmission`, `PublishGuide`;
  - the admin review endpoints; the feedback inbox;
  - **the first counts:** finished harmonies and second songs, from our own records.
- **Frontend:**
  - recorded guides in the player (original key, 100%, otherwise the synth with a note), with the
    Real voice / Synth switch;
  - the singer screens (welcome, consent, setup with the sound check, My parts);
  - the quality check (using `scoring.js`); "Hear it as learners will"; the upload;
  - the admin review queue (the mix, A/B, the pitch chart, light fixes);
  - Tell Hillel.
- **Contract:** `templates.catalogue`, `beta.session.*`, `beta.parts.list`, `beta.submissions.*`,
  `beta.feedback.create`, `admin.beta.*`.
- **Hillel:** the reviewed templates; the lawyer-checked agreement; then the first invites (one
  friend first).
- **Done when:** a beta singer's alto plays as the default guide for every learner of Amazing
  Grace.

The beta then runs alongside everything below. Real voices arrive as singers record, and the beta
singers are the first testers of each milestone.

### M3. Sing, the course, and accounts
- **Frontend:**
  - **Sing** (`docs/singing-ux-plan.md` steps 2 to 6): the chooser, part by part (Coach and Phrase
    path), the whole song (Stage, Staff, Note lane), recording, Just recorded;
  - **the course, part 1** (`docs/vocal-coaching-plan.md`):
    - the map, the lesson player, stars, XP, levels and the weekly goal;
    - **units 5 to 7, the harmony path** ("Your first harmony", `docs/more-features-plan.md`
      feature 2), with lit notes in the Note lane;
  - **Chords underneath** (feature 3);
  - **the first run, for a beginner** (revised after a review: the timing check and the range
    check no longer stand between a beginner and their first note):
    - a welcome, then **straight into unit 1, lesson 1** ("Find your voice"), with only the mic
      permission;
    - **unit 1 is the range check** for beginners, and finds their range as they sing;
    - the explicit range check stays for "I've sung harmony before" and for the library;
    - **the timing check waits until the first timed take** (recording a part, or a
      timing-scored exercise in unit 4 onwards);
    - headphones are asked for when other parts will play;
    - **the goal is singing within a minute;**
  - **when to ask for an account** (proposed, open to veto): never to learn or record. It's asked
    for only when the person wants to save to the cloud, share, buy, or reaches the free limit;
  - **the sign-in and account screens:** Apple, Google, email; Settings; Download my data; Delete.
- **Backend** (`docs/backend-plan.md` B): Google, Apple and password sign-in, linking, the
  profile and handle, export, deletion, "sign out everywhere".
- **Trust and safety** (`docs/trust-and-safety-plan.md`, the M3 row): sign-up, sign-in and reset protections, verification caps, handle rules, trust levels 0 and 1, Turnstile built behind shield mode.
- **Keeping songs safe, and growth** (`docs/operations-plan.md`, `docs/go-to-market-plan.md`):
  the "only on this device" card after the first part, `persist()`, the install card, `ref` at
  sign-up, "How did you hear about us?", and account recovery support.
- **From the product review** (`docs/more-features-plan.md`, features 9, 10, 14, 16): Find the
  third and Hold your note in units 5 and 6; slow practice at 85% and 70%; note labels and the
  keyboard; the kind note when the real voice can't follow the key.
- **Contract:** `auth.*`, `account.*`, and the template schema's optional `chords`.
- **Hillel:** the Apple developer account; the musician checks the chords and the harmony units;
  the voice teacher checks those lessons.
- **Done when:**
  - a beginner goes from the first run through unit 5 to recording the round's 2nd voice, on an
    iPhone, earning stars on the way;
  - an account can be made, exported and deleted with each sign-in method.

### M4. Your work in the cloud, sounding good
- **Backend** (C1 to C6):
  - projects, parts, takes, presigned uploads, `ProcessTake` (FLAC);
  - sync with versions and 409s; the bin and its purge;
  - plans and limits;
  - `account.learning` (course progress, stars, XP, badges, the weekly goal);
  - the `take_scores` table.
- **Frontend:**
  - db.js version 3 (the outbox, `offsetFrames`);
  - the sync layer; "Changed on another device";
  - moving browser projects into the account; "Recently deleted";
  - the storage meter and the plan gates;
  - **Make it sound good** on export, and Hear it polished (`docs/more-features-plan.md`, feature
    1).
- **Trust and safety** (`docs/trust-and-safety-plan.md`, the M4 row): upload limits, the exact `Content-Length`, byte caps, trust level 2.
- **Trimming** (`docs/more-features-plan.md` feature 17): trim a part's start and end, trim the
  silence, line up the starts or ends, with fades, Undo, and the trims honoured by playback,
  export and sharing. `trimStartFrames` and `trimEndFrames` sync as part settings.
- **From the product review** (features 12, 15): practice tracks (everything but my part, my part
  louder, with a click); the Storage page.
- **Contract:** `projects.*`, `parts.*` (with the trims), `takes.*`, `bin.*`, `usage.get`, `account.learning`.
- **Done when:**
  - a project recorded on the phone opens on the Mac, with the course progress;
  - a conflict asks, and a deleted take comes back from the bin;
  - the fourth project is refused with `project_limit`;
  - an export sounds polished at -14 LUFS.

### M5. Getting better, and Plus
- **Frontend** (`docs/vocal-coaching-plan.md`, the course part 2):
  - **Wait for me** and the full play-along screen (lit notes, combo, the edge bar on the Stage);
  - units 1 to 4 and 8;
  - the report and chart (replay with your line); bleed;
  - **Today's workout;** badges; tips;
  - Your voice;
  - the plan gate, the upgrade moments and Checkout;
  - Plus songs downloaded through the API and cached for offline use.
- **Backend:**
  - **Plus songs through the API** (`docs/pricing-plan.md`): the catalogue's `access` and the
    song of the month; only the 4 free songs stay bundled with the app;
  - **Plus billing** (D8: Cashier, Stripe Tax, the portal, the webhook, the grace period, the
    emails);
  - scores history sync (`take_scores`).
- **Trust and safety** (`docs/trust-and-safety-plan.md`, the M5 row): Radar rules, one trial per card, the Checkout limit, chargebacks, the Plus download cap, trust level 3.
- **Practice reminders** (opt-in web push, `docs/operations-plan.md`), and the 14-day offline
  grace for Plus songs; **the checkout rules** (`docs/pricing-plan.md`, "At checkout").
- **From the product review** (features 11, 13): Re-sing this phrase; the What's next card.
- **Contract:** `billing.*`, `scores.*`.
- **Hillel:** Stripe and Stripe Tax; the accountant; the voice teacher's check; prices and the
  trial.
- **Done when:**
  - a beginner plays a lesson in Wait for me and earns 3 stars;
  - a test card upgrades to Plus from the installed app;
  - history appears on a second device;
  - the lessons and tips are marked reviewed.

### M6. Share it: links and videos
The launch's sharing is personal: a link or a video that the person sends where they like. The
public gallery comes after launch.
- **Backend** (`docs/backend-plan.md` D6, the unlisted parts):
  - harmonies as **private or unlisted** (no public gallery yet), and `ProcessHarmony`;
  - the `/h/<id>` page's data (unlisted, so not indexed);
  - the share card;
  - **`RenderShareVideo`** (`docs/more-features-plan.md`, feature 4);
  - **Report on a shared page,** with hide and takedown in admin (the copyright policy).
- **Frontend:**
  - **first commit: the stub view for `/h/<id>`** (the backend's tests render it);
  - Share: publish as unlisted, with Make it sound good;
  - the share sheet, and "Post to X" and "Post to Bluesky" with a pre-filled post;
  - **Share as a video:** the choices, progress, and the share sheet with the file;
  - the `/h/<id>` Blade page (the player, waveform, credits, "Learn this harmony");
  - the admin report and takedown screen.
- **Trust and safety** (`docs/trust-and-safety-plan.md`, the M6 row): share limits by trust level, the mix check, Report without an account, hiding at 3 reports, the video caps, no links in titles.
- **Growth** (`docs/go-to-market-plan.md`): sign-ups from a share recorded with the harmony; the
  end card and "Learn this harmony".
- **From the product review** (feature 12): Share just my part.
- **Contract:** `harmonies.*` (private and unlisted), `videos.*`, `reports.create`,
  `admin.reports.*`, and the `/h` page's view model.
- **Hillel:** the DMCA agent; the copyright policy.
- **Done when:**
  - a finished harmony's link plays on a phone, with a card that looks right in iMessage and
    WhatsApp;
  - its video plays well on TikTok and Reels;
  - a reported link can be taken down in admin.

### M7. Make your own, together
- **Frontend:**
  - **the template creator** (`docs/template-creator-plan.md`): device templates and drafts, Sing
    it and Write it, transcription, lyrics, harmony, import and export (MusicXML if it fits);
  - **collaboration:** invite friends to a project by link, roles, leaving;
  - **choir groups** (Plus): a director makes a group, invites members by link, shares songs
    (official or their own), assigns parts, and sees who has learned which part;
  - the opt-in "List this template in the library when it opens".
- **Backend** (`docs/backend-plan.md` C7, D1, D2):
  - templates on the server: versions, private and unlisted, the private limit;
  - a creator's own recorded guides (Plus);
  - project invites and roles;
  - groups, memberships, assignments, and the learned-parts view;
  - the template opt-in, counted for the network features panel.
- **Contract:** `templates.*` (create, version, visibility, list mine), `invites.*`, `groups.*`.
- **Hillel:** OK on Liang hyphenation (or the built-in rules); the choir plan.
- **Done when:**
  - a template sung into the creator on an iPhone is shared by link with a friend, who learns it;
  - a director assigns parts to three members, and sees who has recorded theirs.

### M8. Coaching, without the directory (`docs/coaching-marketplace-plan.md`)
- **Backend:**
  - coach applications and approval;
  - Connect Express onboarding;
  - offerings (take reviews, ongoing coaching, exercise packs);
  - engagements, destination charges and the commission;
  - deadlines and automatic refunds;
  - ratings, messages and disputes;
  - Send to my teacher;
  - the marketplace's trust and safety protections.
- **Frontend:**
  - **the coach's profile page, reached by its own link** (`/coach/<handle>`; not listed or
    searchable yet, and `noindex` until the directory opens);
  - checkout;
  - the review workspace (both sides);
  - messages;
  - the coach dashboard;
  - ratings on the profile;
  - Send to my teacher;
  - the admin screens for applications and disputes.
- **Contract:** `coaches.*`, `engagements.*`, `reviews.*`, `ratings.*`, `messages.*`, `packs.*`,
  `connect.*`, `admin.coaching.*`.
- **Hillel:** the Connect platform profile; the coach agreement and marketplace terms; the
  commission; the first coaches approved (who bring their own students).
- **Done when:** in Stripe test mode, a coach shares their profile link, a singer buys a review,
  the coach delivers it with a pinned voice note, and a missed deadline refunds in full.

### M9. Event Schedule together (`docs/event-schedule-integration-plan.md`)
- **In Event Schedule's repo** (G4 items 1 to 8): OAuth with scopes, appointment webhooks, the
  bookings API, mail for integration bookings, the members endpoint, oEmbed, embeddable booking
  pages, and no double invoicing.
- **Backend:** connecting accounts, live lessons (booked and paid in Stackapella), the webhook, the
  reconcile, and choirs across both apps.
- **Frontend:**
  - Connect Event Schedule;
  - booking a lesson on the coach's profile;
  - lessons in the Coaching tab;
  - rehearsals and what to practise in a choir group.
- **Done when:** a lesson booked in Stackapella appears in the coach's Event Schedule, a
  cancellation there refunds here, and a choir's next rehearsal shows what to practise.

### M10. The rest that needs no network
- **The harmony gym** (all its games; `docs/more-features-plan.md` feature 7), and
  relative-tuning feedback in the report;
- **Invoice Ninja** (`docs/invoice-ninja-integration-plan.md`): Stackapella's own books and
  coaches' books;
- **our X and Bluesky posts** of new songs and the song of the month (`docs/backend-plan.md` D7,
  without featured harmonies);
- **smaller:**
  - free-form punch-in;
  - Sign in with Event Schedule;
  - paying by invoice;
  - our accounts posting share videos (with consent).
- **Done when:** each item's checks in its plan pass.

### L. Launch readiness
- **Backend, also:** **the network features panel** in admin (below), and the opt-ins counted.
- **Backend** (A3, D9):
  - Sentry; readiness checks; backups and a **restore drill**;
  - the scheduled jobs reviewed;
  - the rate-limit and security review;
  - the moved launch domain with redirects, and the beta domain kept as staging;
  - the server-side counts (finished harmonies, second songs, sign-ups, upgrades).
- **Frontend:**
  - **first commit: the stub views** for the song, guide, landing, pricing and legal pages;
  - the landing, pricing and legal pages (Blade);
  - an accessibility pass;
  - help pages for the common problems (the mic, headphones, timing, the installed app on
    iPhone);
  - the offline list complete;
  - "A new version is ready" on update.
- **Both:**
  - finish counts and "also learned" for the What's next card (`docs/more-features-plan.md`,
    feature 13), computed nightly from our own records;
  - `docs/features.md` and the claims test;
  - the list of services that handle personal data (DigitalOcean, Cloudflare, Stripe, Apple,
    Google, Sentry), for the privacy policy;
  - **the beta singers are invited to make accounts,** and their credits link to their profiles if
    they choose.
- **Hillel:** the legal pages; Sentry; the launch library; a last look on every device.
- **Trust and safety** (`docs/trust-and-safety-plan.md`, the L row): the alerts, the runbook, the
  privacy policy's abuse section, and **the abuse test** against the beta domain.
- **Go to market** (`docs/go-to-market-plan.md`): **the song pages (`/songs/<slug>`) and guides,
  sitemaps,** the landing page with beta singers' stories, and the press kit.
- **Operations** (`docs/operations-plan.md`): the support inbox with context and saved replies,
  "Send diagnostics", refunds in admin, `security.txt`, **the security review**, the secrets list,
  and cost alerts.
- **Legal duties** (`docs/trust-and-safety-plan.md`): statements of reasons and appeals, the
  under-18 defaults, the accessibility statement, and the terms on sharing and monetising.
- **Done when:** the launch checklist in `docs/backend-plan.md` A3 passes, the abuse test stops
  every attack where the plan says, and Hillel says go.

**Every milestone from M1 (and L) ends with the device pass:** an iPhone (Safari and installed),
Android Chrome, Safari and Firefox on a Mac, and Chrome on Windows (`docs/operations-plan.md`).

**Launch.**

The launch week itself is in `docs/go-to-market-plan.md`.

## After launch: the features that need a network

Each opens when **the network features panel** says it's ready. Building starts when its numbers
reach about half the threshold, so it's ready when they get there.

| # | Feature | Ready when (proposed thresholds, counted from our own records) | Plans |
|---|---|---|---|
| N1 | **The gallery:** public harmonies, Explore, public profiles, likes, comments, follows, blocks, featured harmonies and their posts | 300 harmonies opted in to the gallery, from 100 different singers | `backend-plan.md` D6, D7 |
| N2 | **Leagues and friends' leaderboards** | 1,000 singers active in a week (for leagues of about 30 at a similar level), and N1 open (friends come from follows) | `vocal-coaching-plan.md` |
| N3 | **The public template library and search** | 50 templates opted in, from 20 creators | `templates-plan.md` Phase 3; `template-creator-plan.md` |
| N4 | **The coach directory,** search, suggestions and featured coaches | 15 approved coaches, 10 of them with 3 or more ratings, and 500 singers active in a week | `coaching-marketplace-plan.md` F7 |
| N5 | **The open parts shelf** | N1 open, and 50 harmonies offered with an open part | `more-features-plan.md` 5 |
| N6 | **The virtual choir,** song by song | For a song: 30 voices opted in, across at least 3 parts | `more-features-plan.md` 6 |
| N7 | **Song requests and votes** | 500 singers active in a week | `more-features-plan.md` 8 |
| | Licensed songs, if the lawyer finds a way | | `templates-plan.md` |

### The network features panel (in admin)
- **One row per network feature:**
  - its numbers against its threshold, with a bar;
  - the opt-ins collected so far;
  - its status: **Collecting** (under half), **Start building** (half or more), **Ready to open**
    (at the threshold), or **Open**.
- **"Singers active in a week"** counts signed-in people who practised, recorded or took a lesson
  that week. It's from our own records, never analytics.
- **The thresholds are settings** Hillel can change in admin.
- **A job (`NetworkReadiness`) recounts nightly.** The daily digest says when a status changes
  ("The gallery is ready to open: 312 harmonies from 118 singers").
- **Opening a feature** is a feature flag that Hillel switches on (behind sudo, audit-logged), once
  the feature is built.

## The native apps (Flutter, `~/Code/stackapella-mobile`)

iOS and Android first, then desktop (macOS, Windows, Linux), starting with the audio spike
(`docs/decisions.md`, 2026-10-27). The mobile agent keeps its own roadmap in that repo.

**When the apps are in the stores, the web promotes them** (Hillel, 2026-10-27; don't forget):
- `web/manifest.webmanifest` gains `related_applications` (the App Store and Play entries with
  their ids), with `prefer_related_applications` left false so the PWA still installs.
- The app's pages get Apple's Smart App Banner (`<meta name="apple-itunes-app">`) on iOS.
- The landing's "Coming soon" for the apps turns into store badges (its config list), and the
  help pages and the install prompt mention the apps.
- Android's `assetlinks.json` and Apple's `apple-app-site-association` on `app.stackapella.com`,
  so song links open in the app when it's installed.

## How the tracks stay balanced

**Each milestone gives both agents work from day one:**

| Milestone | Backend starts with (no contract needed) | Frontend starts with (no contract needed) |
|---|---|---|
| M1 | The scaffold, CI, deploy | The session store, `pitchtrack.js` and `scoring.js`, the mock |
| M2 | The catalogue seeding, the tables | Recorded guides in the player, from a local file |
| M3 | Google sign-in | The Sing screens and `course.js` |
| M4 | Storage and `ProcessTake` | db.js version 3, the outbox, and `polish.js` |
| M5 | Cashier | Wait for me and the play-along screen |
| M6 | Harmonies, `ProcessHarmony` and `RenderShareVideo` | The share flow, on the mock |
| M7 | Template versions, groups | Transcription, the creator's wizard |
| M8 | Connect onboarding | The review workspace, on the mock |
| M9 | The Event Schedule client (against a local Event Schedule) | Connect Event Schedule, lesson booking, on the mock |
| M10 | Invoice Ninja's client | The harmony gym |
| L | Backups, readiness, the network features panel | The landing and pricing pages |

**When one agent runs ahead,** it doesn't start the next milestone's contract work alone. It:
- takes the next no-contract item;
- or writes the tests for what's coming;
- or works through the review list of the milestone.

## What each milestone delivers besides code
- A short entry in `contract/log.md`, and the flow scripts for its exit check.
- Updated README setup steps.
- Decisions logged in `docs/decisions.md`.
- A note in the feature plan: "Built in M_n (date)".

## What reading every plan together found (2026-10-26)

| Found | Where it's handled now |
|---|---|
| No single statement of what Stackapella is, for whom, or what's in the launch | [vision.md](vision.md) |
| Three different orders of work (the backend plan's phases, the two-agent slices, each plan's own) | This roadmap; the others point here |
| Real-device timing still untested, though beta singers will record on phones | M0 |
| The pitch tracker and scoring needed by five plans, with no owner | Built once, in M1 |
| Several plans adding IndexedDB stores, each calling itself "version 2" | One registry in [architecture.md](architecture.md) |
| Device-made records (UUIDs today) and Sqids clashing once things sync | Device UUIDs kept; Sqids for server records |
| Offsets stored in seconds on the device, frames in the backend plan | Frames everywhere, from db.js version 3 |
| The conventions' static-site deploy clashing with the public Blade pages | Laravel serves everything on one origin |
| The public pages belonging to neither agent's folder | The frontend agent owns their markup |
| "Your singing stays on this device" untrue once takes sync | "Analysed on this device"; a privacy table |
| The pricing table calling every recorded guide Plus | Official real voices are free; your own are Plus |
| "Send to my teacher" and the marketplace overlapping | Hillel's call: it stays, in the same workspace |
| No landing, pricing or help pages in any plan | L |
| No first-run path for a beginner, and no rule for when to ask for an account | M3 (proposed) |
| Long-lead outside work (lawyer, musician, voice teacher, Stripe's Connect review, Apple, DMCA) not scheduled | "Start now" at the top |
| No launch library target, and no plan for beta singers after launch | The gates and L |
| A launch too big for one founder (the gallery and a two-sided marketplace at once) | Network features wait for a network (the panel says when); everything else ships, with L possible after any milestone from M6 |
| Stale docs: the templates plan's format and decisions, the design prompts' out-of-scope list, the README's status | Updated or marked historical |

---

[Back: Vision](vision.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Architecture](architecture.md)
