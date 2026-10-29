# Two agents at once: one on the backend, one on the frontend

**Status:** planned, not started (2026-10-26). Its setup is part of roadmap M1, and the role files
are written: [agents/backend.md](agents/backend.md) and [agents/frontend.md](agents/frontend.md).
The milestones the agents build are in [roadmap.md](roadmap.md).

## Context

Stackapella has two halves: `/server` (the Laravel API) and `/web` (the Vue PWA). Hillel wants two
Claude Code agents working at the same time, one on each. They **agree on the API first**, then
both build toward it: the backend makes it real, and the frontend builds against a mock of it until
the real one is ready.

What makes this work:
- **The contract is the only thing they share.** It's written, reviewed and approved before either
  side builds a slice, and it's tested from both sides, so a drift fails a test, not a demo.
- **Each agent owns its folder.** Nothing else is shared except a few files with clear rules.
- **They talk to each other directly,** through Claude Code's messages between local sessions,
  and to Hillel for anything that's a product decision.

## How Hillel runs it

1. **Agree a slice's contract first,** in one session (see "A slice, start to finish"). Hillel
   approves it, and it's committed.
2. **Open two terminals,** one in each agent's worktree (made once with
   `git worktree add ../stackapella-backend -b agent/backend` and the same for the frontend; see
   "Git"), and start `claude` in each:
   - in the first: "You're the backend agent. Read `docs/agents/backend.md`, then build slice 2.";
   - in the second: "You're the frontend agent. Read `docs/agents/frontend.md`, then build slice
     2."
3. **Each works on its own, and never stops to ask Hillel** ("Working without Hillel" below). It
   decides by the plans, logs its decisions, and messages the other agent when it needs
   something from it.
4. **At the end of the slice,** the frontend agent runs the flow scripts against the real backend
   (the integration check), and both report back.
5. Hillel reviews the commits, and says when to push (agents never push).

**Why two sessions Hillel can see, not one lead session spawning two background agents:**
- he can watch and steer each one;
- each asks him its product questions directly;
- and neither one's work is hidden behind the other's summary.

A lead session can still run the integration check for him if he prefers.

## The contract (`/contract`, at the repo's root)

```
contract/
  api.md                         the rules every endpoint follows, and the list of endpoints
  schemas/<operation>.json       the response (and request) shape of each, as JSON Schema
  examples/<operation>.<case>.json   a real-looking response for each case (200, 422, 410...)
```

- **An operation** is named like `beta.parts.list` or `templates.catalogue`. `api.md` lists each one:
  - its method and path, and who may call it (singer, admin, anyone);
  - the request, and each response case with its status, its schema and its example;
  - the errors.
- **The general rules** (in `api.md`, once), from `docs/conventions.md`:
  - JSON responses in API Resources' `{ "data": ... }` envelope, and `{ "data": [...], "meta": ... }`
    for lists, with cursor pagination;
  - IDs are strings, never numbers: Sqids for server records, and the device's UUID for records
    made on the device (`docs/architecture.md`). Times are ISO 8601 in UTC;
  - errors are Laravel's `{ "message", "errors" }` 422 shape, plus a stable `code` for refusals
    (`invite_expired`, `consent_required`, `rate_limited`), which the app shows as its own words.
    Never a raw exception message;
  - the HTTP statuses used:
    - 401 (signed out), 403 with a `code`, 404, 409 (out of date), 410 (expired or revoked),
      422, 429 (with `Retry-After`);
  - auth is a session cookie on the same origin. Changing requests send Laravel's CSRF token
    (`XSRF-TOKEN` cookie, `X-XSRF-TOKEN` header);
  - uploads go straight to Spaces by a presigned URL, so the contract includes the PUT's headers
    and what happens when an upload fails (it's retried from the device's outbox; see the
    backend plan, C4), not only our own endpoints;
  - **changes are additive by default:** a new field or a new endpoint. Renaming or removing one
    is a breaking change (see "Changing the contract").
- **JSON Schema, a small subset:** `type` (with `"null"` for optional values), `properties`,
  `required`, `additionalProperties: false`, `items`, `enum`, `format` (`date-time`, `uri`), and
  `$ref` between files. It's enough for our responses, and small enough to check without a library.
- **Every example must pass its schema.** The examples are what the frontend's mock serves, so the
  mock can't drift either.

**Why JSON Schema and examples, not OpenAPI:**
- OpenAPI's tooling (generators, validators and mock servers) mostly comes from npm or needs a
  composer package;
- the subset above is checked by about 150 lines of our own PHP in the server's tests;
- if Hillel would rather use a library for it, `opis/json-schema` (composer, MIT) is the candidate.
  It needs his OK as a dependency.

## Testing the contract from both sides

**The backend** (PHPUnit, in `server/tests/Contract/`):
- **`assertMatchesContract($response, 'beta.parts.list', 200)`** in each feature test. It checks
  the JSON against the schema, and the status and `code` against `api.md`.
- **Every example passes its schema** (one test that runs them all).
- **Every API route is in the contract, and every operation has a route:** an architecture test
  that compares `routes/api.php` with `api.md`, like the existing Never tests.
- **Every operation has at least one feature test** using `assertMatchesContract`.

**The frontend** (headless Chrome, `web/tool/test.sh`):
- **`web/js/api.js` is the only place that calls `/api`.** Each operation is one function named
  after it (`api.beta.parts.list()`). A test checks that every contract operation has its
  function, and nothing else in `web/js` calls `fetch('/api...`.
- **The mock** (`web/js/api-mock.js`):
  - turned on with `?api=mock`, on localhost only;
  - answers each function with the contract's examples, so the app is built and tested against the
    same JSON the backend is checked against;
  - **a scenario** picks other cases, for example `?api=mock&scenario=invite_expired`;
  - it keeps a little state only where a flow script needs it (a submission moves from Submitted
    to In review).
- **The flow scripts** (`web/tool/cdp.py`) run against the mock while the backend is being built,
  and against the real server at the end of the slice.

**The integration check** (the end of each slice):
- `tool/serve.php` gains a switch: `API=mock` (the default, no server needed) or
  `API=http://127.0.0.1:8000`, which forwards `/api` to Laravel on the same origin, so cookies
  and CSRF behave as in production;
- the frontend agent runs the slice's flow scripts against the real server, with seeded data
  (`php artisan db:seed --class=BetaDemoSeeder`), and reports what passed;
- a failure is fixed by whichever side is wrong, as judged by the contract. If the contract itself
  was wrong, it's changed as below.

## Who owns what

| Path | Owner | Rule |
|---|---|---|
| `server/` | backend agent | The frontend agent never edits it (except the public views below). |
| `web/` | frontend agent | The backend agent never edits it, including `web/css/app.css`. |
| `server/resources/views/public/` | frontend agent | The public Blade pages' markup. The backend agent writes their controllers and data, following the view models in the contract. |
| `contract/` | both, by agreement | Changed only as in "Changing the contract". |
| `docs/decisions.md` | both | Append only; commit it on its own, straight away. |
| `README.md` | each its own section | Server setup, web setup. |
| `docs/<topic>-plan.md`, `vision.md`, `roadmap.md`, `architecture.md` | neither | Hillel's plans: read them, don't rewrite them; ask instead. Adding "Built in M_n (date)" to a status line is fine. |
| `.github/`, `.do/` | backend agent | The frontend agent asks for changes (the CSS check in CI, the static files). |

## Working without Hillel (his standing delegation, 2026-10-26)

Hillel's words: **"I want to press start and come back to a finished app."** So while the two
agents build Stackapella, **they never stop to ask him a question.** He has given them the power to
do what's best per the plan. This replaces every "ask Hillel" in the plans while they build.

**How a decision is made, in this order:**
1. the feature plans;
2. `docs/decisions.md` (the newest entry wins over a plan);
3. the vision's principles (`docs/vision.md`);
4. the conventions.
- **Anything marked "proposed" or "open to veto"** is taken as written (the plan's
  recommendation).
- **Where nothing decides it,** the agent picks the option that's simplest, kindest to a beginner
  and easiest to undo.

**Every decision is logged** in `docs/decisions.md` under "Decided by the agents (delegated)",
with the reason, so Hillel can read it later and veto it.

**What this changes:**
- **Contracts:** the two agents agree each milestone's contract between them, and log it. Hillel
  reviews the log when he's back; nobody waits for him. A breaking change needs both agents to
  agree, with the reason logged.
- **Dependencies:** those the plans name (the table in `docs/backend-plan.md`, Liang hyphenation,
  the MP3 encoder, `minishlink/web-push`) are approved. Any other needs a clear reason, the
  smallest option, no npm, and a log entry.
- **Things only Hillel can do in the real world never block:**
  - accounts and live keys, device runs, the lawyer, the musician, the voice teacher, the
    accountant, deploying, pushing;
  - the agents build and test everything with fakes and test modes (Stripe test mode,
    `Http::fake`, `Storage::fake`, the log mailer, a fake Event Schedule);
  - they keep a **"Needs Hillel"** list in the Now block of `contract/log.md`: what to do, and
    where;
  - they carry on.
- **Content is drafted by the agents,** marked `reviewed: false` until Hillel's experts check it:
  - the frontend agent drafts Happy Birthday, the course units, exercises, tips, badges, help
    pages and copy;
  - the backend agent drafts the Plus library's songs (`server/database/templates/`) and the legal
    pages' first drafts, which are marked for the lawyer.
- **The retrospective after M1** is written by the agents themselves into the log, and the role
  files are updated from it.

**Thorough reviews, always** (Hillel, 2026-10-26). Each agent reviews its work **at the end of
every section** (each numbered item in a milestone's track) **and every milestone**, before moving
on:
1. **an independent review:** the `code-review` skill at high effort on the section's commits, or a
   fresh review subagent given the code, not the agent's conclusions;
2. **its own pass** against this checklist:
   - **correctness and misses:** against the plan, the contract and the architecture's invariants;
   - **UX and copy:** the vision's principles, phone and desktop, and the error states;
   - **accessibility and languages:** contrast, touch targets, screen readers, `t()` and `__()`,
     and the pseudo-language;
   - **security and abuse:** `docs/trust-and-safety-plan.md`;
   - **code fit:** `docs/conventions.md`;
   - **tests:** unit tests, flow scripts and contract checks, error paths included;
3. **every real finding fixed** before the next section;
4. the review noted in the log.

**"Finished" means:** every milestone through L built, reviewed and passing its checks on fakes and
test modes, with the "Needs Hillel" list as his launch checklist. Then the agents stop.

**What still never happens, delegation or not:**
- pushing, deploying, or touching production;
- creating accounts, spending money, or using live keys;
- real email, posts or payments to real people;
- committing a secret;
- npm or analytics;
- rewriting the plans. Decisions are appended; status lines may say "Built in M_n".

## Git: a worktree for each agent, landing on `main` (revised 2026-10-26)

**Why not one shared checkout:** a review found two real problems.
- The backend's tests for the public pages would fail whenever the frontend had a half-saved Blade
  view in the same working tree.
- Two agents committing in one checkout race on `.git/index.lock`, and risk staging each other's
  files.

**Each agent therefore works in its own git worktree, from day one:**
- `~/Code/stackapella-backend` on the branch `agent/backend`;
- `~/Code/stackapella-frontend` on the branch `agent/frontend`.

Hillel's own checkout (`~/Code/stackapella`) stays on `main`, clean.
- **Landing on `main` keeps "straight to `main`":** after each small commit (or a few), the agent
  runs:
  1. `git rebase main`;
  2. the tests;
  3. **checks Hillel's checkout is clean:** `git -C ~/Code/stackapella status --porcelain` prints
     nothing. If not, it doesn't land; it tells Hillel and carries on working;
  4. `git -C ~/Code/stackapella merge --ff-only agent/<role>`.

  History stays linear, with no merge commits. Hillel's checkout always shows the latest `main`.
- **When landing fails, nothing is lost:**
  - "not a fast-forward" means the other agent landed first: rebase and try again;
  - "index.lock exists" means both are landing at the same moment: wait a few seconds and try
    again;
  - never delete the lock, and never force.
- **Hillel's checkout is for reviewing and pushing while the agents run.** If he wants to edit
  himself, he does it in a worktree of his own (`git worktree add ../stackapella-hillel -b hillel`),
  so the agents' landings never meet his uncommitted work.
- **Picking up the other's work:** `git rebase main` before starting each task, and whenever the
  other agent says it landed a contract change or a stub.
- **The commit hook** (`.githooks/pre-commit`, enabled with `git config core.hooksPath .githooks`)
  stays:
  - when `STACKAPELLA_AGENT` is `backend` or `frontend`, it refuses staged files outside that
    agent's folders (the frontend's include `server/resources/views/public/`);
  - it refuses changes to `contract/` without `CONTRACT_OK=1` (set only after both agreed).
- **Public pages:**
  - at the start of a milestone, **the frontend agent first lands a stub view** for each new public
    page (it prints the contract's view model plainly) so the backend's tests have something
    stable to render;
  - it then builds the real page in its own worktree, and lands it when it's done;
  - the backend's tests only ever see landed views.
- **Never `git stash`, `git reset --hard` or `git clean` in Hillel's checkout,** and never force a
  move of `main`.
- **Tests and servers stay apart:**
  - each session's test database is named from `CLAUDE_CODE_SESSION_ID`;
  - the backend agent runs Laravel on port 8000 from its worktree, and the frontend agent the web
    server on 8081 from its own, forwarding `/api` to 8000 for the integration check.

## Talking to each other

- **Messages between the two sessions** (Claude Code's `SendMessage` to a local session; each finds
  the other with `ListAgents`). Use them for:
  - "slice 2 endpoints are live on :8000" (backend);
  - "the parts list needs the part's duration" (frontend, as a contract change proposal);
  - "the seeder for the demo singer is committed" (backend).
- **`contract/log.md` has two parts:**
  - **a "Now" block at the top,** kept current by whoever changes it:
    - the milestone in progress;
    - the IndexedDB version in use, and any version claimed;
    - the contract operations being built;
    - the public stub views landed;
    - the questions waiting on Hillel;
  - then dated lines, newest first: each agreed change, each landed stub, each finished milestone.

  Both agents read the Now block and the last 10 entries at the start of every task and after any
  message, so a restarted session, or one that missed a message, never works from stale
  assumptions.
- **Product questions are decided under Hillel's delegation** (above), never left open. If both hit the same question, the
  backend agent asks, and the answer goes in `docs/decisions.md`.
- **Neither waits idle:** while a change is being agreed, the frontend keeps going on the mock, and
  the backend on the parts that aren't affected.

## Changing the contract

1. The agent that needs the change messages the other with the operation, the change, and why.
2. **Additive** (a new field or endpoint): the other agrees, or says why not, in its reply. The
   proposer commits the contract change ("Contract: the parts list includes each part's length"),
   with its schema and example, and adds a line to `contract/log.md`.
3. **Breaking** (renaming, removing, changing a type or meaning): both agree, and the reason is
   logged in `docs/decisions.md` for Hillel to review, since a breaking change usually means a
   slice was planned wrong.
4. The backend makes it real and tested; the frontend updates `api.js` and the mock follows the new
   example by itself.

## A slice, start to finish

**A slice** is one feature that runs from end to end: its endpoints, its screens and its tests. The
contract for a slice is written before either side builds it:
1. **Draft:** one session reads the feature's plan and drafts the slice's operations, schemas,
   examples and error cases.
2. **Review** (the review passes Hillel asks for): misses, the app's needs (every screen's data, and
   each error state having a case), and the backend's needs (what's cheap to query, without N+1).
3. **Both agents agree** (Hillel's delegation; he reviews the log later). It's committed
   ("Contract: slice 2, invites and consent"), and the two agents start.

**The slices are the milestones in [roadmap.md](roadmap.md).** Each milestone lists its backend
track, frontend track, contract operations, Hillel's gates and its exit check. M1 has almost no
contract, so it's the first real test of two agents at once.

## The role files (`docs/agents/`)

- **`docs/agents/backend.md`** and **`docs/agents/frontend.md`**, read at the start of each
  session:
  - the role, its folders, and what it must never touch;
  - set `STACKAPELLA_AGENT` before committing;
  - the contract workflow and the message etiquette above;
  - its definition of done:
    - backend: `php artisan test --compact`, Pint, PHPStan, every operation in the slice covered by
      `assertMatchesContract`;
    - frontend: `web/tool/test.sh`, the slice's flow scripts on the mock, then the integration run;
  - decide by the plans under Hillel's delegation, never stopping to ask, and log the decisions;
  - thorough reviews at the end of every section and milestone.
- `CLAUDE.md` gains a line pointing at them. Everything else in it applies to both agents as
  before.

## Review (misses, the experience for Hillel, code fit)

**Missed, now covered:**
- **Uploads that bypass our API** (presigned PUTs to Spaces) are part of the contract.
- **Cookies and CSRF** behave differently across origins, so the integration run forwards `/api`
  through the same origin rather than calling `:8000` directly.
- **Two agents in one checkout** would break each other's tests and race on the git index, so each
  has its own worktree, landing on `main` by fast-forward, and a hook keeps commits to their own
  folders (revised after a review).
- **The service worker** must never cache `/api` (`docs/conventions.md`). The mock bypasses it.
- **Seeded data** for the integration run is a backend deliverable of each slice.
- **Shared files** (`decisions.md`, `README.md`, CI) have owners or rules.
- **The admin's sign-in** needs Google, which can't run in a test. It uses the existing
  `/dev/login` route (local only) in flow scripts.
- **The contract can be wrong.** The integration check decides which side changes, and a breaking
  fix goes to Hillel.

**For Hillel:**
- **Two terminals, two short prompts:** the role files hold the rest.
- **He sees where things stand** in `contract/log.md` and the commit history, not only in chat.
- **Each agent's questions come to him directly,** one at a time, never decided between agents.
- **He approves each slice's contract before work starts,** the one point where a mistake costs both
  sides.

**Code fit:**
- **No new dependencies:** the schema check is our own PHP, the mock is our own JS, and
  `serve.php`'s forwarding uses PHP's curl.
- **It follows the conventions:**
  - API Resources' envelope and Laravel's error shape;
  - the stable `code` for refusals;
  - architecture tests for the route list;
  - the per-session test database.
- **Code that's useful anyway:** `api.js` as the single client and the mock stay after the beta,
  for every later feature.

## Order of work
1. **The setup, committed before any slice:**
   - `contract/api.md` (general rules) and `contract/log.md`;
   - `docs/agents/backend.md` and `docs/agents/frontend.md`;
   - the `.githooks/pre-commit` hook;
   - a line in `CLAUDE.md`.
2. **M1 with two agents,** as the trial run.
   - The backend builds the schema checker and `assertMatchesContract`;
   - the frontend builds `api.js`, the mock and the `serve.php` switch.
3. **A short retrospective with Hillel,** with the same questions each time:
   - what did an agent miss or invent that the docs should have prevented?
   - which rule was hard to find when it was needed?
   - where did the two agents wait on each other?
   - what should move into the "Never break these" list?

   The role files and this plan are updated before M2, and again after each milestone if needed.
4. **M2 onwards.**

## Verification
- **The hook:** with `STACKAPELLA_AGENT=frontend`, a commit staging `server/` is refused, and one
  changing `contract/` without `CONTRACT_OK=1` is refused.
- **The contract tests fail when they should:**
  - a field removed from a Resource fails `assertMatchesContract`;
  - an example that doesn't match its schema fails;
  - a route missing from `api.md` fails the architecture test;
  - a contract operation missing from `api.js` fails the web test.
- **M1's integration run:** the app on `serve.php` with `API=http://127.0.0.1:8000` signs in
  as admin through `/dev/login` and shows the admin's name from `admin.me`.
- **By Hillel:** one slice run with two agents end to end, then the retrospective.

---

[Back: Conventions](conventions.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Decisions](decisions.md)
