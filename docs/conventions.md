# Conventions

How Stackapella is built. It copies the conventions of Hillel's other projects, so the code reads
like theirs. Each rule names where it comes from:

- **iaw**: `~/Code/iaw`, the 2026 Laravel 13 rewrite of It's All Widgets. This is the main reference.
- **ES**: `~/Code/eventschedule`, Event Schedule (Laravel 11).
- **mudeo**: `~/Code/mudeo_old` (Flutter) and `~/Code/mudeo_api` (Laravel 5.8), the video app
  Stackapella replaces.

`~/Code/itsallwidgets` (Laravel 5.6, 2018) was reviewed too. Its conventions are the 2018 Laravel
skeleton that iaw replaced, so it is not used as a reference.

The items once marked "(confirm)", where the two projects differ, were all decided on 2026-10-25
(see [decisions.md](decisions.md)). The server side is planned in [backend-plan.md](backend-plan.md).

## Never

- No npm at all: no `package.json`, no `npm install`, no Vite (as iaw). **Decided by Hillel**, who
  doesn't trust npm (too many supply-chain problems) and never wants to run `npm install`.
  - Libraries the app runs load from a CDN, pinned to an exact version with an `integrity` (SRI)
    hash. This differs from iaw and ES, which vendor files (ES because selfhosters shouldn't call
    outside servers; Stackapella is hosted only).
  - Tailwind runs as its standalone binary, downloaded at a pinned version and checked against its
    sha256, as iaw's `bin/tailwindcss` is. That is the only build step, and it needs no npm.
- No analytics of any kind (iaw CLAUDE.md).
- No `env()` outside `config/` (iaw `tests/Architecture/ArchitectureTest.php`
  `test_env_is_only_read_in_config`).
- No `request()` in jobs, actions or services. Never `$request->all()` or
  `fill($request->all())`. No route closures. No empty `catch`. No `DB` facade in controllers.
  No `file_get_contents()` on a URL; use the `Http` facade (iaw ArchitectureTest).
- No data-changing GET routes (iaw `test_routes_that_change_data_are_never_gets`; ES CLAUDE.md
  "An address that changes something is never a GET").
- No SQLite, not even in tests. MySQL only (iaw `test_no_connection_falls_back_to_sqlite`; ES
  CLAUDE.md "MySQL only").
- Never delete a migration. New migrations use today's date (ES CLAUDE.md).
- Never show a raw exception message to a user (ES CLAUDE.md).
- No em dashes in UI text or docs (iaw and ES CLAUDE.md).
- No co-author or attribution trailers on commits (ES CLAUDE.md; confirmed by Hillel).
- No bulk or recurring email to users. A user gets email only about something they did
  (verification, password reset) (iaw CLAUDE.md).

## Always

- Thin controllers: authorise (FormRequest or policy), call one Action, return a resource or
  response (iaw CLAUDE.md, `app/Actions/Apps/HideApp.php`). **Decided.**
- `declare(strict_types=1);` at the top of every PHP file, and `final class` unless a class is
  meant to be extended (iaw: 522 of 626 app classes are final).
- Explicit parameter and return types. PHPDoc on classes and public methods that explains why,
  not what. Array shapes in PHPDoc (iaw Boost guidelines in `CLAUDE.md`).
- Every model has a factory (iaw `test_every_model_has_a_factory`).
- Every enum column is cast to a backed PHP enum with a `label()` (iaw `app/Enums`).
- Encode every server ID a user can see (ES `app/Utils/UrlUtils.php`, `encodeId()` /
  `decodeIdOrFail()` with Sqids). Records made on the device (projects, parts, takes, device
  templates) keep their device UUID instead (`docs/architecture.md`, Identity and IDs).
- Before calling anything done, run `php artisan test --compact`, `vendor/bin/pint` and
  `vendor/bin/phpstan analyse` for the server, and the headless-browser tests for the web app
  (iaw CLAUDE.md).

## Folder structure

```
/server   Laravel API (app/, config/, database/, routes/, tests/ as iaw)
/web      Vue PWA: no npm, no install step; libraries from a CDN
/docs     the vision, roadmap, architecture, plans, decisions, runbooks; agents/ holds the role files
/contract the API contract shared by the two agents (from roadmap M1)
/.do      app.yaml (used once), nginx.conf
/.github  workflows
```

`/server/app` follows iaw:

| Folder | Holds | iaw example |
|---|---|---|
| `Actions/<Domain>/VerbNoun.php` | one `handle()` per business operation, dependencies in the constructor | `app/Actions/Users/SignInWithGoogle.php` |
| `Services/<Vendor or Area>/` | wrappers around outside APIs and infrastructure | `app/Services/Provision/SpacesBuckets.php` |
| `Support/<Area>/` | domain logic that is not an action | `app/Support/Admin/AdminGate.php` |
| `Data/` | plain `final` DTOs with typed public props | `app/Data/AppDraft.php` |
| `Enums/` | string-backed enums | `app/Enums` |
| `Http/Controllers/<Area>/` | thin controllers, grouped by feature | `app/Http/Controllers/Apps` |
| `Http/Requests/<Area>/` | FormRequests | `app/Http/Requests/Apps/SubmitAppRequest.php` |
| `Http/Resources/` | API Resources | `app/Http/Resources/Legacy` |
| `Jobs/` | `final`, `ShouldQueue`, scalar ids in the constructor, safe to run again | `app/Jobs` |
| `Policies/` | registered with `Gate::policy()` | `app/Policies/AppPolicy.php` |
| `Rules/` | custom validation rules | `app/Rules` |

`/web` is served as is:

```
index.html             app shell: import map (CDN URLs, pinned, with SRI), manifest link
manifest.webmanifest   PWA manifest
sw.js                  service worker
css/app.css            Tailwind's output, committed (CI checks it is current)
css/input.css          Tailwind input: design tokens and @theme
js/                    main.js, views/, components/, stores/, api.js, dsp.js
engine/                the audio engine and AudioWorklet processor (from the spike)
icons/                 app icons, including maskable ones
tool/tailwind.sh       downloads the pinned Tailwind binary, checks its sha256, builds the CSS
tool/serve.php         local server (until Laravel serves the app)
tool/test.sh           runs tests/ in muted headless Chrome
tool/cdp.py            drives Chrome over the DevTools protocol (Python standard library only)
tests/                 index.html test page, *.test.js, fixtures.js, seed.html (a demo project)
templates/             harmony templates (JSON), listed in index.json
js/synth.js            the guide voice; synth-worker.js runs it off the main thread, guides.js caches it
js/practice.js         what plays in a template project (voiceLevels), pure and unit-tested
js/templates.js        loads and validates templates
```

## Laravel

**Controllers.** Grouped by feature, not split into API and web (iaw). Single-action controllers
are invokable (iaw `Apps/UploadController`). Actions are injected through the method:

```php
public function store(StoreProjectRequest $request, CreateProject $create): ProjectResource
```

**API responses.** Eloquent API Resources in `app/Http/Resources` (iaw, and the Laravel default).
ES builds JSON by hand with `toApiData()` and a `{data, meta}` envelope
(`app/Http/Controllers/Api/ApiEventController.php`). Resources give the same envelope with less
code. Errors are `{"message": ..., "errors": {...}}` (Laravel's default 422 shape), plus a stable
`code` for limit errors (ES `app/Exceptions/EventCreationLimitException.php`).

**Models.**
- `$fillable` only, one column per line. Server-owned fields (plan, `google_id`, flags) are left
  out and set with `forceFill()`, with a docblock that says so (iaw `app/Models/User.php`).
- Casts use the `casts(): array` method.
- Factories are attached with `#[UseFactory(...)]` plus `/** @use HasFactory<XFactory> */`.
- Eager-load lists are model constants (iaw `FlutterApp::CARD_RELATIONS`).
- `Model::shouldBeStrict(! isProduction())` and `Relation::enforceMorphMap()` in
  `AppServiceProvider` (iaw `app/Providers/AppServiceProvider.php`).
- Hard deletes, with a job that removes the Spaces objects. ES uses `is_deleted` booleans instead
  of SoftDeletes. The exception: takes and projects go to a 30-day bin first
  ([backend-plan.md](backend-plan.md), C3).

**Validation.**
- A FormRequest for every write. Rules are arrays, and cross-field checks go in `after(): array`
  closures (iaw `app/Http/Requests/Apps/SubmitAppRequest.php`).
- Naming is iaw's `Http/Requests/<Area>/<Verb><Noun>Request.php` (`Projects/StoreProjectRequest`).
  ES uses `<Model><Action>Request` (`RoleCreateRequest`).
- Public forms get a honeypot field: `'nickname' => ['prohibited']` (iaw; ES CLAUDE.md).

**Auth.**
- Session-cookie auth on the same origin as the web app (see Deployment). Neither project
  uses Sanctum or Passport.
- Google through Socialite, with `prompt=select_account`. Sign-in is refused unless
  `email_verified`. Accounts are matched by `google_id` only, never by email. A new user is built
  with `forceFill()` inside a transaction with `lockForUpdate()` (iaw
  `app/Actions/Users/SignInWithGoogle.php`, `app/Http/Controllers/Auth/LoginController.php`).
- After login: `session()->invalidate(); Auth::login($user, remember: true);
  session()->regenerate();` (iaw).
- Email and password: Laravel's hashing (`'password' => 'hashed'` cast), email verification,
  password reset, and named rate limiters on login, register and reset. Mail goes through
  Cloudflare Email Service, through a small custom transport (decided 2026-10-26;
  `docs/backend-plan.md` D9). iaw uses Postmark. ES has the same flows as a copied-in Breeze scaffold (`app/Http/Controllers/Auth`).
- A `/dev/login` route only when `APP_ENV=local`, because Google rejects `.test` redirect URIs
  (iaw `DevLoginController`).
- Authorisation goes through policies registered with `Gate::policy()` (iaw
  `app/Policies/AppPolicy.php`, ES `EventPolicy`).

**Routes.**
- Every route is `[Controller::class, 'method']` and named.
- Throttles use named limiters defined in `AppServiceProvider` (iaw `uploads`: 40 per minute and
  300 per day per user).
- Middleware is configured in `bootstrap/app.php`, with `/up` as the health route (iaw, ES).

**Plan limits** (ES `app/Models/Role.php`, `config/usage.php`, `docs/FEATURES.md`):
- A limit is a method that returns `?int`, where `null` means unlimited. A `canCreateX(): bool`
  next to it compares the count to the limit.
- The numbers live in `config/usage.php`, can be overridden by env, and are named per tier:
  `'project_limit_free' => (int) (env('PROJECT_LIMIT_FREE') ?: 3)`.
- Hitting a limit returns 403 with a stable `code`, so the app can show the right message.
- `docs/features.md` will be the single source of truth for what each tier gets (ES
  `docs/FEATURES.md`).

**Jobs and scheduling.** Jobs take scalar ids, re-fetch the model and set `$tries` and
`$backoff`. Scheduled commands are named, with a bounded `withoutOverlapping(N)` (ES CLAUDE.md).
**Two queues** (revised 2026-10-26; a review found that ffmpeg jobs would block mail on one
queue):
- **`default`** (mail, webhooks, light jobs) is drained by a per-minute
  `queue:work --stop-when-empty --max-time=55` in `routes/console.php`, as iaw does;
- **`long`** (ffmpeg: `ProcessTake`, `ProcessHarmony`, `RenderShareVideo`, later `BuildChoirMix`)
  has its own resident worker component in `.do/app.yaml`:
  - `queue:work database_long --queue=long --timeout=300 --max-jobs=50 --max-time=3600`;
  - 2 processes, which is also the cap on video renders at once;
  - `retry_after` (600) above the timeout.

A slow render never delays a password reset.

**Logging and errors.** Production logs go to `stderr`. Sentry is used by both projects
(`sentry/sentry-laravel`). Not for the MVP: it's revisited before launch (decisions.md).

## Frontend: a Vue PWA

**Decided by Hillel:** Vue 3, built as an installable PWA, with no npm at all. Libraries come from a
CDN. Flutter web was dropped because:
- the audio engine is JS anyway;
- Flutter's runtime adds 2.2 to 3.6 MB gzipped, plus memory on iOS;
- real form fields give password-manager autofill and accessibility.

- **Loading:**
  - Plain ES modules, no bundler.
  - The import map in `index.html` resolves bare names (`import { createApp } from 'vue'`), as
    iaw's does (`app/Support/Assets/ImportMap.php`).
  - Each CDN URL names an exact version, carries an `integrity` hash and
    `crossorigin="anonymous"`. Vue comes from jsDelivr as the ESM browser build with the template
    compiler (`vue.esm-browser.prod.js`).
- **Templates and CSP** (**decided**: no npm means no build-time compiling):
  - Vue compiles templates in the browser, so the CSP allows `'unsafe-eval'`. iaw refuses it,
    because text reaching an in-DOM template could run (`app/Http/Middleware/SecurityHeaders.php`).
  - The rule that makes it safe here:
    - Templates live only as strings in our own JS modules (`` template: `...` ``), never in the
      page's HTML and never built from data.
    - Mount points are empty elements.
    - User text is only shown through `{{ }}` (escaped), never `v-html`.
  - `script-src` allows `'self'`, the one CDN host and `'unsafe-eval'`. There are still no inline
    scripts.
- **State:** `reactive()` stores in plain modules (`js/stores/`). No Pinia. The router is our own
  (`js/router.js`).
- **Styling:**
  - Tailwind through its standalone binary (`tool/tailwind.sh`, as iaw's `composer run css`). It
    scans `web/js` and the Blade public pages (`server/resources/views/public/`). The
    built `css/app.css` is committed, so serving and deploying need no build. CI fails when it's
    out of date (iaw `.github/workflows/test.yml`).
  - The design tokens (part colours, type scale, spacing, light and dark) are CSS custom
    properties, which the Tailwind theme reads.
  - Logical classes only (`ms-`, `pe-`, `start-`), as iaw requires, so right-to-left languages work.
- **Words and languages** (`docs/architecture.md`, "Languages"):
  - every user-facing string goes through `t()` (`web/js/i18n.js`, `web/locales/en.json`) in the
    app, and `__()` (`lang/en/`) on the server; never a literal sentence in a template, a Blade
    view or an email;
  - the API returns codes, and the app words them;
  - dates, numbers, durations and prices use `Intl` or Carbon's locale;
  - keys are grouped by screen (`studio.record`, `sing.yourTurn`), and the English is written to
    the existing copy rules (sentence case, warm and plain, no em dashes).
  - The font is Bricolage Grotesque from Google Fonts.
- **Admin screens and forms look like iaw, not Event Schedule** (Hillel, 2026-10-26: iaw is newer
  and more modern). This covers the admin, and every web form: sign-in and account settings,
  contact, reports, the coach application, checkout steps, and the public pages' forms. Follow
  iaw's structure and patterns, in Stackapella's own colours, font and part colours:
  - **the reference is iaw's styleguide** (`~/Code/iaw/resources/views/styleguide.blade.php`,
    served at `/styleguide`);
  - **form fields:** the label, hint, field and error, one per row, with the spacing and states of
    iaw's `components/form/field.blade.php`;
  - **admin layouts:** iaw's `resources/views/admin/*` for queues, a person's page, settings and the
    audit log (lists with filters, one-click actions, a status chip from
    `components/admin/status.blade.php`, plain empty states);
  - **page structure and type:** iaw's `resources/css/app.css` (the page container, the eyebrow
    label, the heading scale);
  - **Event Schedule's admin and forms are not the reference.** Its patterns are older.
- **Stackapella gets its own styleguide page** (`/styleguide`, on localhost and for admins, as
  iaw's): buttons, form fields with every state, tables, status chips, sheets, toasts and empty
  states, in our tokens. Every form and admin screen is built from what's on it, so the two
  agents' screens look like one product.
- **The PWA:**
  - `manifest.webmanifest`: `display: standalone`, normal and maskable icons, theme colours from
    the palette.
  - A hand-written service worker (`sw.js`, no Workbox). It caches the app shell and the pinned CDN
    files under a versioned cache name, and never caches `/api`.
  - Takes not yet uploaded wait in IndexedDB, and the app calls `navigator.storage.persist()`.
    Safari clears a site's storage after 7 days without a visit unless the app is installed, one
    more reason to upload at once.
  - The Screen Wake Lock API keeps the screen on while recording.
  - An installed iOS web app runs in its own mode, so the iOS spike checks both Safari and the
    installed app.
- **Audio:** `engine/engine.js` and `engine/recorder-worklet.js` move over from the spike. The
  calibration maths in the spike's `lib/dsp.dart` is ported to `js/dsp.js`. Nothing that has to
  line up uses timers.

## Testing

- PHPUnit 12, not Pest (iaw, ES).
- Suites: `Unit`, `Feature` and `Architecture` (iaw `phpunit.xml`).
- `final class XTest extends TestCase`. Methods are snake_case sentences:
  `test_a_free_user_cannot_save_a_fourth_project` (iaw, ES).
- `use RefreshDatabase;` in feature tests.
- Test data comes from factories, not helper traits (iaw). ES builds most test data
  with traits like `tests/Feature/Concerns/CreatesScheduleData.php`.
- `tests/TestCase.php::setUp()`: `Http::preventStrayRequests()`, `Storage::fake(...)` for each disk,
  and `Bus::fake([...])` for heavy jobs (iaw `tests/TestCase.php`).
- The test database is MySQL. Each run or session gets its own schema, named from `TEST_DB_TOKEN`
  or `CLAUDE_CODE_SESSION_ID`. A schema without "test" in its name is refused (iaw
  `tests/TestDatabase.php`).
- `phpunit.xml` forces `CACHE_STORE=database` (as in production), `QUEUE_CONNECTION=sync`,
  `SESSION_DRIVER=array` and `BCRYPT_ROUNDS=4`, and sets every third-party key to empty (ES
  `phpunit.xml`).
- The N+1 guard: `tests/Support/QueryGuard.php` wraps every request, and deliberate exceptions go
  in `tests/query-baseline.php` with the reason (iaw, ES).
- `tests/Architecture/ArchitectureTest.php` turns the Never list above into tests (iaw).
- The web app is tested in headless Chrome, because no npm means no JS test runner. A test page
  runs the unit tests (DSP, stores) and the loopback self-test, then reports to a small server,
  as the spike's `tool/selftest.sh` does.
- Whole flows (sign in, record, save) are driven by the flow scripts on `web/tool/cdp.py`, against
  the mock and then the real server. No Dusk, so there's no second browser driver.

## Style and linting

- Laravel Pint with the default preset and no `pint.json` (iaw, ES). CI runs `pint --test`.
- Larastan at level 8 (iaw `phpstan.neon`). ES has none.
- `.editorconfig` is the Laravel default (iaw `.editorconfig`): 4 spaces, LF, final newline,
  `*.md` keeps trailing whitespace, YAML uses 2 spaces.

## Env and config

- `.env.example` documents every key, with comments for the non-obvious ones (ES
  `.env.example`).
- Read env with `env('X') ?: default` rather than `env('X', default)`, because the default only
  fires for a missing key, never for one present but empty. Cast explicitly: `(int)`, `(bool)`
  (ES `config/`).
- Third-party keys go in `config/services.php`. Product settings go in their own files:
  `config/usage.php` for limits (ES).
- `phpunit.xml` forces every third-party key empty, so a test never reaches an outside service
  with a developer's real credentials (ES CLAUDE.md).

## Storage

iaw `config/filesystems.php`, ES `config/filesystems.php`:

- A `$spaces` closure builds each Spaces disk: `throw` and `report` set to true, region and
  endpoint from env.
- Code only uses the aliases `Storage::disk('media')` and `Storage::disk('private')`. The env vars
  `MEDIA_DISK` and `PRIVATE_DISK` point them at local disks in development and at Spaces in
  production.
- Object keys are random and immutable (`Str::random(32)` or a uuid), so a public file can be
  cached for a year.
- Private files (every recorded take, since projects are private) are served through
  `temporaryUrl()`, never a public URL (iaw podcast MP3s).

## Deployment (DigitalOcean App Platform)

iaw `.do/app.yaml`, `.do/nginx.conf`; ES `docs/DIGITALOCEAN_WORKER.md`:

- `.do/app.yaml` is committed and used once: `doctl apps create --spec .do/app.yaml` (iaw).
  After that, change the app in the console, because re-applying a stored spec
  deletes domains and secrets set there. ES never commits a spec, because it edits its domains at
  runtime. Stackapella doesn't need that.
- Secrets never go in the spec. Set them in the console.
- Components:
  - `web`: the PHP buildpack, `php artisan optimize && heroku-php-nginx -C .do/nginx.conf public/`,
    health check on `/up`.
  - `scheduler`: a worker running `schedule:work`, which also drains the queue.
  - `migrate`: a `PRE_DEPLOY` job running `php artisan migrate --force`.
  - **Laravel serves the PWA too** (decided 2026-10-26): `/web`'s files as they are, `/api`, and
    the Blade public pages, all on one origin. There's no separate static site component, because
    the public pages and the app must share an origin and cookies (`docs/architecture.md`). No
    build is needed, because the CSS is committed.
- App-level envs: `LOG_CHANNEL=stderr`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database` and
  `SESSION_DRIVER=database`, plus the disk aliases.
- DO Managed MySQL, created outside the spec. The CA cert comes from an env var (iaw
  `app/Support/DatabaseCa.php`).
- `Aptfile` lists `mysql-client` and `ffmpeg` (FLAC, AAC and probing uploads).

## CI

iaw `.github/workflows/test.yml`, ES `.github/workflows/test.yml`. The workflow runs on push to
`main` and on pull requests. It sets `permissions: contents: read` and cancels in-progress runs
with concurrency.

- **`server` job:**
  - A `mysql:8.4` service with `sql_require_primary_key` and a strict `sql_mode`. Setup uses PHP 8.4.
  - `composer install`, then a "no npm, ever" check.
  - `pint --test`, `phpstan analyse`, `composer audit`.
  - `php artisan test --compact` with `QUERY_GUARD_STRICT=1`. The query-guard report is uploaded.
- **`web` job:**
  - "No npm, ever" (iaw): fails if a `package.json` or `node_modules` appears.
  - Rebuilds the CSS with the pinned Tailwind binary and fails on a diff (iaw).
  - Checks that every CDN URL names an exact version and has an `integrity` hash.
  - Runs the headless-Chrome test page: unit tests and the engine self-test.
- **`outdated.yml`:** a weekly check that the pinned CDN libraries and Tailwind have no newer
  release, as iaw's vendored-assets check does.

## Docs and README

- `README.md` (iaw `README.md`):
  - what it is
  - first-time setup on a Mac as numbered steps (Herd, DBngin, `composer install`)
  - everyday commands in one code block
  - how it's built, as bold-led bullets
  - deploying
- `docs/` holds plain runbooks in the second person, with short sentences, tables for "what you
  see / what to do", and no emojis (iaw `docs/SETUP.md`, ES `docs/`).
- `docs/decisions.md` is a dated log of decisions. `docs/features.md` lists what each tier gets.
- `CLAUDE.md` holds the short project rules and points here.

## Commits

- Style is "Area: plain-English outcome", e.g. `Recording: a take starts on the beat after the
  count-in` (ES and iaw history).
- No trailers.
- A body, when one is needed, is prose explaining the cause and the fix.
- Small, focused commits, straight to `main` (ES CLAUDE.md "Work directly on `main`"). Never push
  without asking.

## Domain notes carried over from mudeo

- A song has ordered tracks. Each track has `delay` (ms, can be negative), `volume`, `order_id` and
  `is_included` (`mudeo_api` `song_video` table,
  `database/migrations/2019_07_28_104320_add_delay_fix_for_latency.php`). Stackapella stores the
  offset in samples rather than ms.
- Collaborators join through `song_user` and a `sharing_key`. `parent_id` marks forks.
- mudeo started tracks with `Future.delayed` timers (`mudeo_old/lib/ui/song/song_edit.dart`). Its
  latency calibration (`lib/ui/song/calibrate.dart`) was never finished. Stackapella schedules
  everything on the audio clock and measures latency by loopback. See
  [audio-spike.md](audio-spike.md).

---

[Back: The frontend agent](agents/frontend.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Two agents at once](parallel-agents-plan.md)
