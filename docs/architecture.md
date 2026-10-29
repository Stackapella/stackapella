# Architecture: how the pieces fit

**Status:** describes what's built (2026-10-26) and what the plans add. When a plan and this file
disagree, this file and [decisions.md](decisions.md) win, and the plan is fixed.

The shared mental model for the backend and frontend agents. The vision is in
[vision.md](vision.md), the order of work in [roadmap.md](roadmap.md), and code style in
[conventions.md](conventions.md).

## The system

```
 Singer's device (installed PWA, works offline)            Server (one origin)                    Outside
 ┌─────────────────────────────────────────┐   /api    ┌──────────────────────────────┐
 │ views + components (Vue, no build)      │ ───────▶  │ Laravel 13: Actions,         │ ──▶ Stripe (Cashier, Connect)
 │ stores: projects, session, templates,   │  cookies  │ FormRequests, Resources      │ ──▶ Cloudflare (email)
 │         account, sync                   │  + CSRF   │ Blade: public pages, email   │ ──▶ X, Bluesky
 │ api.js (the only /api caller) + mock    │           │ Jobs: ffmpeg on `long` queue │ ──▶ Event Schedule (OAuth)
 │ db.js (IndexedDB) + outbox              │           │ MySQL 8.4                    │ ──▶ Invoice Ninja
 │ engine (Web Audio, AudioWorklet)        │  presigned│ Spaces: media (CDN), private │ ──▶ Apple, Google sign-in
 │ dsp, synth, pitchtrack, scoring ...     │ ────PUT─▶ │         backup               │
 │ sw.js (app shell, guides cache)         │           └──────────────────────────────┘
 └─────────────────────────────────────────┘
```

**Laravel serves everything on one origin:**
- the PWA's files from `/web`;
- `/api`;
- the Blade public pages (`/h/<id>`, `/@handle`, `/t/<slug>`, `/explore`, `/coaches`, the landing,
  pricing and legal pages);
- `/admin` (the PWA again);
- the webhooks.

There's no separate static site, since the public pages and the app must share an origin and
cookies. This replaces the "static site component" in `docs/conventions.md` (decided 2026-10-26).

**But nginx, not PHP, serves the PWA's files.**
- **What nginx serves directly:**
  - `js/`, `engine/`, `css/`, `icons/`, `templates/`, `sw.js` and `manifest.webmanifest`;
  - from `/web`, through `try_files` in `.do/nginx.conf`.
- **Why it matters:** with no bundler, one page load imports dozens of ES modules, and booting
  Laravel for each would be slow.
- **What reaches PHP:** only `/api`, the public pages, the webhooks, and app routes (which return
  `index.html`).
- **Caching:**
  - `sw.js` and `index.html` are `no-cache`;
  - the other files are short-lived with an ETag;
  - the service worker's versioned cache does the rest.

Locally, `tool/serve.php` (or Herd's nginx) does the same.

## The device (`/web`)

| Layer | Modules (built ✓, planned ·) | Notes |
|---|---|---|
| Audio engine | ✓ `engine/engine.js`, ✓ `engine/recorder-worklet.js` | Everything on the AudioContext clock; frame-exact capture; segments (play, loop, listen then repeat); `positionInfo`, `preview`, `render`, `inputFrame` |
| Signal maths | ✓ `dsp.js` (latency, YIN), ✓ `synth.js` + `synth-worker.js` (· `renderBacking` for chords), ✓ `guides.js`, · `polish.js` (Make it sound good), · `pitchtrack.js`, · `scoring.js`, · `transcribe.js`, · `arrange.js`, · `lyrics.js` | Pure, unit-tested in headless Chrome. **`pitchtrack.js` and `scoring.js` are shared** by beta singers, Sing, coaching, the creator and the marketplace: built once, in milestone 1 |
| Domain | ✓ `templates.js`, ✓ `practice.js`, · `sing.js`, · `course.js` (the course: units, stars, XP, levels, badges, the weekly goal, the workout), · `staff` layout | Pure functions over the data formats below |
| Stores | ✓ `stores/projects.js`, · `stores/session.js`, · `stores/templates.js`, · `stores/account.js`, · `stores/sync.js` | `reactive()` modules, no Pinia |
| Data | ✓ `db.js` (IndexedDB), · outbox | The only module that touches IndexedDB |
| Network | · `api.js`, · `api-mock.js` | The only module that calls `/api`; one function per contract operation |
| Screens | ✓ `views/projects.js`, ✓ `views/studio.js`, · `views/sing.js`, · `views/create.js`, · account, coaching, admin | The router is our own (`router.js`) |
| Offline | ✓ `sw.js` | The app shell, CDN files, templates, and (planned) recorded guides and Plus songs; shows opt-in practice reminders (web push). Never `/api` |

### IndexedDB: one registry of stores and versions

Each plan adds stores, so the versions are assigned here, in roadmap order. `db.js` owns the
upgrade code.

| Version | Stores added or changed | For |
|---|---|---|
| 1 ✓ | `projects`, `parts` (by `projectId`), `audio` | Today |
| 2 | **`audio` converted to 16-bit (`Int16Array`), halving takes on the device** (converted at `db.js`'s edge, to and from `Float32Array`); plus `guides` (recorded guide audio, by `templateId@version:partKey`), `betaTakes` (takes waiting to send), `learning` (the course's progress) | **M1** (before beta singers record on phones); the stores are used from M2 and M3 |
| 3 | `outbox` (changes waiting to sync), `meta` (who's signed in, sync cursors); `parts.offsetSeconds` becomes `offsetFrames` | Accounts and cloud save |
| 4 | `warmups` (the log); scores stay in `project.practice` | Singing feedback |
| 5 | `templates` (the person's own, `source: 'device'`), `templateDrafts` | The template creator |

**If a feature is built out of this order,** it takes the next free version. Versions are never
reused.

## The server (`/server`)

**Action domains** (`app/Actions/<Domain>/`):
- **people:** `Users` (sign-in, profile, export, delete), `Admin`;
- **content:** `Beta`, `Templates`, `Projects` (projects, parts, takes, the bin), `Harmonies`;
- **community:** `Social` (posting), `Moderation`, `Trust` (levels, limits, the blocklist,
  `security_events`; `docs/trust-and-safety-plan.md`);
- **money:** `Coaching`, `Billing`;
- **outside:** `Integrations\EventSchedule`, `Integrations\InvoiceNinja`;
- **help:** `Support`.

**Services** wrap the outside world:
- Spaces, ffmpeg;
- X, Bluesky;
- Event Schedule, Invoice Ninja;
- Stripe (only where Cashier doesn't).

**Rules in one place:**
- `Support/Plans` (limits);
- `Support/Admin/AdminGate`;
- `config/usage.php`;
- `config/coaching.php`.

## Identity and IDs (decided 2026-10-26, open to veto)

- **Records made on the device keep the ID the device gave them:** a UUID from
  `crypto.randomUUID()`, as projects and parts already use. This covers projects, parts, takes,
  device templates and drafts.
  - The API addresses them by that UUID.
  - It's stored in a `uuid` column next to the integer primary key.
  - Offline creation therefore needs no ID remapping when it syncs.
- **Everything else has a server ID,** shown publicly as a Sqid (as the conventions say):
  - users and handles;
  - harmonies, template versions and coaches;
  - engagements, invites and submissions.
- **Public URLs never show a UUID of a private record.** A published harmony gets its own Sqid.

## The shared data formats

Each gets a JSON Schema in `/contract` (milestone 1). The PHP and JS checks then can't disagree.

| Format | Shape (summary) | Lives |
|---|---|---|
| **Template** | `id`, `version`, `reviewed`, `title`, `description`, `difficulty`, `genre`, `credits`, `licence`, `tempo`, `key`, `timeSignature`, `pickupBeats`, `sections` (`name`, `startBeat`, `endBeat`, `phrases`), `parts` (`key`, `name`, `range`, `melody`, `guide: { type: "notes", notes: [{ beat, length, pitch, lyric, br }] }`, later `guide.recording`), `voicings` (`"2"`, `"3"`, `"4"`) | The 4 free songs in the repo (`web/templates`); from M5, Plus songs in `server/database/templates/` and from the API only; the device (cached); the server (`template_versions`). The public catalogue carries only names, ranges and a preview clip |
| **Project** | the UUID, title, bpm, key, time signature, count-in, metronome, `template` (a copy, with its version), `practice` (modes, levels, progress, latest scores), `version` | The device; the server once signed in |
| **Part** | the UUID, project, order, name, colour, volume, pan, muted, solo, **`offsetFrames`**, **`trimStartFrames`, `trimEndFrames`** (non-destructive trims; `docs/more-features-plan.md` feature 17), `templatePartKey`, the current take, `version` | Same |
| **Take** | the UUID, sample rate, frames, peaks, SHA-256, the audio (Float32 on the device, a 16-bit WAV upload, FLAC on the server) | Same |
| **Pitch track** | 100 frames a second: Hz (or null when unvoiced), clarity, loudness | The device only, except when sent to a coach |
| **Score summary** | per take: % on pitch, level word, phrase results, tendencies, strictness | The latest on the device; the history synced for Plus |
| **Review** | pinned comments (time, note, text or voice note), a demo take, the summary, the exercises | The server |

**The take's offset moves from seconds to frames** (`offsetFrames`), as the conventions already
say ("the offset in samples, not ms"). Frames at the take's sample rate are exact; seconds are
not. This happens with db.js version 3. Each part also keeps its take's `sampleRate`, so the
offset is scaled when the device's rate differs (see the invariants).

## Where audio goes (privacy, in one table)

| Audio | Leaves the device when | Where to |
|---|---|---|
| A take | The person is signed in (cloud save) | `private`, by presigned PUT |
| A beta take | The singer sends it | `private` |
| A published harmony's mix | The person publishes it | `media` (public) |
| A take sent for review, with its pitch track | The person sends it to a coach or their teacher | `private`, visible to that coach only, for 90 days |
| Practice runs, live pitch, warm-ups | Never | |
| Scores | Plus history: the numbers only, never audio | The server |
| A part that joins a virtual choir | The person joins (after the quick check) | `private`, heard only inside the choir mix on `media` |
| An open part's other takes | The owner opens parts on a public harmony | Shared with the people who fill it |

**The copy follows this table.** "Your singing is analysed on this device" stays true. "Your
singing stays on this device" is true only while signed out, so the copy changes with cloud save.

## Languages: English at launch, ready for any other

**The rule (Hillel, 2026-10-26): Stackapella launches in English, but adding a language must only
mean dropping in translation files.** No screen, email, page or data format is rewritten to do
it. So these hold from the first line of code (M1), even with only English:

**Every word people read comes from a translation file:**
- **The app:**
  - `web/js/i18n.js` (our own, about 100 lines; no library) gives `t('key', { values })`;
  - plurals use `Intl.PluralRules` (`{count, plural, one {# part} other {# parts}}`);
  - the strings are in `web/locales/<lang>.json`, and `en.json` is the source.

  Templates use `{{ t('studio.record') }}`, never a literal sentence.
- **The server:**
  - Laravel's `lang/<lang>/` files, through `__()`;
  - emails, the Blade public pages and validation messages are all translated;
  - **the API never sends sentences for the app to show:** it sends stable `code`s
    (`rate_limited`, `plus_song`), and the app words them in the person's language. Laravel's
    validation messages are the one exception, already translated on the server.
- **Dates, times, numbers, durations and prices** are formatted with `Intl` (in the app) and
  Carbon's locale (on the server). They're never built by hand ("3 minutes ago", "1.5 GB",
  "$4.99").
- **Content data keeps its words separate from its structure:**
  - course lessons, tips, exercises, badges and the help pages hold text by language
    (`"title": { "en": "Find your voice" }`), falling back to English;
  - part names (Soprano, Alto) are keys, not stored English.
- **Songs keep their own language:**
  - a template's words are the song's (Latin, Ukrainian and English are all already in the
    library), with a `language` field;
  - only the descriptions and credits around them are translated;
  - **syllable splitting** (`lyrics.js`) is per language, English first; others are split by hand
    until each language's patterns are added.

**Layouts survive other languages:**
- **logical CSS only** (`ms-`, `pe-`, `start-`; the conventions already require it), so a
  right-to-left language (Hebrew, Arabic) works by setting `dir`;
- **text grows by up to 40% without breaking:** no fixed-width buttons or truncated labels;
- **fonts fall back by script:** Bricolage Grotesque covers Latin. Other scripts fall back to
  system fonts in the CSS font stack, and the server's share cards and videos get a fallback font
  per script.

**The person's language:**
- chosen from the browser at first, then **in Settings**;
- stored on the account (`users.locale`) for emails and notifications;
- the app sets `<html lang>` and `dir`.

**Later, when a language is added:**
- public pages get `/es/...` paths with `hreflang`;
- the pricing page shows local currencies (Stripe already supports them);
- support and the moderation word lists need that language too.

**Translations themselves:** Claude drafts each language, and a native speaker checks it before it
ships, like the musician's and the voice teacher's checks.

**How it stays true** (tested from M1):
- **a test fails** when a `t()` key used in the code is missing from `en.json`, or a key in
  `en.json` is unused;
- **a pseudo-language** (`?lang=pseudo`, on localhost) shows every string accented and 40% longer,
  so a hard-coded sentence or a cramped layout stands out in the flow scripts' screenshots;
- **a server test** checks every `__()` key exists in `lang/en`, and that no API response carries
  a user-facing sentence apart from validation messages.

## Who builds what (the two agents)

| Piece | Backend agent | Frontend agent |
|---|---|---|
| `/server` code: Actions, models, jobs, controllers, routes, mail | ✓ | |
| Blade public pages: the views and their CSS (`server/resources/views/public/`) | The controller and its data | ✓ (the markup, using the app's Tailwind build) |
| `/web`: the PWA, including the admin screens | | ✓ |
| `web/css/app.css` (Tailwind also scans `server/resources/views/public/`) | | ✓ |
| `/contract` | Both, by agreement (`docs/parallel-agents-plan.md`) | Both |
| Email templates | ✓ (plain, transactional) | |
| `.github/`, `.do/`, `Aptfile` | ✓ | Asks for changes |
| `docs/` feature plans | Neither rewrites them; ask Hillel | |
| `docs/decisions.md` | Append only | Append only |

**The public pages are the exception to "each agent owns its folder":**
- the frontend agent owns `server/resources/views/public/` and its partials;
- the backend agent owns everything that feeds them;
- each page's data (its view model) is written in the contract like an API response, so both sides
  can test it.

## Key flows

1. **A take:**
   1. it's recorded and kept in IndexedDB;
   2. while signed in, the outbox asks `takes.create`;
   3. it's uploaded by presigned PUT;
   4. `takes.complete`, then `ProcessTake` makes the FLAC and the peaks;
   5. other devices download it when the project is opened.
2. **A real voice guide:**
   1. a beta submission is reviewed and accepted;
   2. `PublishGuide` writes the AAC to `media`;
   3. the catalogue lists it by `templateId@version:partKey`;
   4. the app plays it in the original key at 100%, and otherwise the synth.
3. **A published harmony:**
   1. it's rendered on the device (`engine.render`) and uploaded;
   2. `ProcessHarmony` makes the AAC, the peaks and the share card;
   3. the public page goes live once every singer on it has agreed;
   4. **Share as a video:** `RenderShareVideo` turns the mix, the peaks and the template's syllables
      into a vertical MP4 with ffmpeg, kept 7 days in `private`.
4. **A take review:**
   1. the singer pays (Connect, a destination charge) and sends the take with its pitch track and
      scores;
   2. the coach accepts, reviews in the workspace, and delivers;
   3. the singer rates the coach;
   4. a decline, a missed deadline or no answer refunds in full.
5. **Plus:** Cashier Checkout, then the webhook, then `Plans` reads the subscription.
6. **The virtual choir:**
   1. a part that passes the quick check joins a song's choir;
   2. `BuildChoirMix` mixes the voices on the server;
   3. this is **the one exception to "the server never remixes"**, because a choir mix is new work
      that nobody heard before, not a copy of what someone heard.

## Invariants (true everywhere, tested where possible)

- **Every word people read comes from a translation file** (`t()` in the app, `__()` on the
  server), and the API sends codes, not sentences. English is the only language at launch, but
  adding one is dropping in files ("Languages" above).

- **Sample rates are converted, never assumed.**
  - A take, guide or recording keeps the sample rate it was made at, beside its frame counts and
    offsets.
  - `decodeAudioData` resamples to the AudioContext's rate, so every frame count and offset is
    scaled on load: `Math.round(frames * ctx.sampleRate / sourceRate)`. That includes the AAC
    priming check (`docs/beta-singers-plan.md`).
  - Scheduling is always in the context's frames.
- **The mic opens before the count-in, not at it.** On iOS, opening the mic switches the audio
  session (a glitch, a volume drop, sometimes a new hardware sample rate). Sing and the creator
  therefore open it during setup or Get ready and keep it open for the session, closing it on
  leaving. A change of device or sample rate rebuilds the context and offers the timing check
  again.
- **Trims never change a take.** Trimming is two numbers on the part, honoured by everything that
  plays or renders.
- **Takes are stored dry.** Make it sound good is applied only on the way out and in playback, and
  never to the timing check, bleed detection or scores.
- **Audio that must line up is scheduled on the AudioContext clock.** Offsets are in frames.
  Latency is measured per device and mic.
- **Notes are always the truth of a template.** Recorded guides sit beside them.
- **A template version never changes once published.**
- **Nothing public without a choice and consent;** nothing deleted by a downgrade; refusals carry a
  stable `code`.
- **The API changes additively.** An installed app can be days old (`X-App-Version`, 426 when it's
  too old).

---

[Back: Roadmap](roadmap.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: The backend agent](agents/backend.md)

## The courses and songs

They live in their own public repo, `hillelcoren/stackapella-courses` (docs/repos-plan.md). This
repo pins one commit of it in `.do/content.lock`; the Dockerfile and CI fetch exactly that commit
(its tarball, checked against the sha256 there) into `web/course`, `web/templates` and
`server/database/templates`, and a checkout links them there (`sh server/tool/content.sh link`). So
the code reads the same paths as before. A content change is a commit in the courses repo, then a
bump of the lock here (`server/tool/content-bump.sh`), gated by this repo's full suites.
