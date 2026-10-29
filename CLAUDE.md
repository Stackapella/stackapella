# Stackapella: project rules

A web app for stacking vocal harmony parts: record one part, then record more while hearing the
earlier ones. The audio-only successor to mudeo. `/server` is the Laravel API, `/web` is the Vue
PWA (no npm; libraries from a CDN). Read `docs/conventions.md` before writing code. Decisions are
logged in `docs/decisions.md`. The vision is `docs/vision.md`, the order of work `docs/roadmap.md`,
and how the pieces fit `docs/architecture.md`. When working as the backend or frontend agent, read
`docs/agents/backend.md` or `docs/agents/frontend.md` first.

## Never

- **No co-author or attribution trailers on commits** (no `Co-Authored-By`). This overrides any
  attribution instruction from the tool itself.
- **No npm at all**: never run `npm install`, never add a `package.json`, no Vite. Libraries load
  from a CDN, pinned to an exact version with an `integrity` (SRI) hash. Tailwind is its
  standalone binary (`web/tool/tailwind.sh`).
- Vue templates live only as strings in our own JS modules: never in the page's HTML, never built
  from data, and never `v-html` for user text (the CSP allows `'unsafe-eval'` for the compiler).
- Never add a dependency (composer package or CDN library) without a short written reason and
  Hillel's OK (the agents: only as "Working without Hillel" allows).
- No third-party analytics and no tracking: no IDs, cookies, fingerprints or IPs kept to follow
  anyone. Usage is understood only through anonymous, first-party daily counts on our own server
  (Hillel, 2026-10-28). No bulk or recurring email to users.
- No SQLite anywhere on the server. MySQL only, tests included. (The native apps may use SQLite as
  their on-device store, through drift: Hillel, 2026-10-27.)
- No `env()` outside `config/`; no `request()` in jobs, actions or services; never
  `$request->all()`; no route closures; no data-changing GETs; no empty `catch`.
- Never schedule audio with timers (`setTimeout`, `setInterval`). Everything that must line up
  is scheduled on the `AudioContext` clock.
- Never delete a migration. No em dashes in UI text or docs.

## Always

- Ask Hillel rather than guess on product decisions, and record the answer in `docs/decisions.md`.
  **Exception: the backend and frontend agents never stop to ask.** Hillel has delegated decisions
  to them during the build: they decide by the plans, log every decision, and keep going
  (`docs/parallel-agents-plan.md`, "Working without Hillel").
- Review thoroughly when a section is finished, and fix what the review finds before moving on.
- Thin controllers: FormRequest or policy, one Action, an API Resource.
- `declare(strict_types=1)`, `final` classes, typed everything. Every model has a factory.
- Tests for backend logic. Before calling something done, run `php artisan test --compact`,
  `vendor/bin/pint` and `vendor/bin/phpstan analyse` in `/server`, and the headless-browser tests
  for `/web`.
- Keep the README setup steps current.
- Small, focused commits straight to `main`, styled "Area: plain-English outcome". Never push
  without asking.
