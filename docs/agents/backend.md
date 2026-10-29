# You're the backend agent

You build `/server`, the Laravel API, while the frontend agent builds `/web` at the same time. Read
this at the start of every session, then the milestone you were given in
[../roadmap.md](../roadmap.md).

## Never break these (check before every commit)

1. **Audio that must line up is scheduled on the AudioContext clock.** Never `setTimeout` or
   `setInterval` for anything you hear. That includes Wait for me, count-ins and loops.
2. **Sample rates are converted, never assumed.** Frame counts and offsets are scaled to the
   context's rate.
3. **The mic opens during setup, not at the count-in.**
4. **Takes are stored dry.** Polish is only on the way out, never on scores, timing or bleed
   checks.
5. **IDs:** device-made records keep their UUID, and server records show Sqids. Never a raw
   number.
6. **The contract decides the API.** Additive changes only. Every route is in `contract/`.
7. **No npm, no analytics, no bulk email.** Dependencies only as "Working without Hillel" allows.
8. **Private by default:** nothing public without a choice and every singer's consent.
9. **No em dashes** in UI text or docs, and no `v-html` for user text.
10. **The free course uses only free content,** and Plus songs never ship in the app's files.
11. **Every word people read comes from a translation file** (`t()` in the app, `__()` on the
    server); the API sends codes, not sentences; dates, numbers and prices go through `Intl` or
    the locale. English only at launch, but a new language must be a drop-in.
12. **A take's frame 0 is where recording started,** with the device's latency already removed
    (`latencyFrames` is only for the record). The server never subtracts it again.
13. **Every refusal is a code in the contract's tables,** added there before the server sends it.
14. **Never trust a presigned URL or the client for an upload:** the server checks the size, the
    hash and the audio itself, and deletes what doesn't match.
15. **Changing a link or a token ends every session it started** (an epoch in the session), and
    only hashes of tokens are stored.
16. **Land only with the tests, Pint and Larastan run on exactly what's committed,** after the last
    rebase (uncommitted drafts set aside), and never through a pipe that hides a failure.
17. **A sign-in that takes more than one request is bound to the browser and the account that
    started it** (a cookie or the session, and the user's id), never to whoever opens the last step.
18. **"No enumeration" covers every answer:** the status, the body, the timing (the same bcrypt
    work either way), the wait after wrong tries, and what a later read (like `pendingEmail`) shows.
19. **A password nobody proved belongs to the inbox's owner is never trusted:** an unverified
    account's is dropped when someone signs up with its address again.
20. **Changing a stored hash's recipe needs its stored values recomputed** (or compared from the
    data), or the next deploy refuses everything already published.

The full list, with the reasons, is in `docs/architecture.md` ("Invariants") and the conventions.

## Every task starts the same way
1. **`git rebase main`,** then read **the "Now" block and the last 10 entries of `contract/log.md`**.
   Do it again after any message from the other agent.
2. **If a feature plan and `docs/decisions.md` disagree, the newer decision wins.** Check the
   newest entries before building anything a plan describes.
3. **Never stop to ask Hillel.** He has delegated decisions to you (`docs/parallel-agents-plan.md`,
   "Working without Hillel"): decide by the plans, then the newest decisions, then the vision.
   Take "proposed" items as written. Log each decision with its reason under "Decided by the
   agents (delegated)". Anything only he can do goes in "Needs Hillel", and you carry on with
   fakes.
4. **End every section and milestone with a thorough review** (the same section of the plan): the
   `code-review` skill or a fresh review subagent, plus your own checklist pass. Fix every real
   finding before moving on.
5. **Before writing a public page's controller tests,** check the frontend agent's stub view for it
   has landed on `main`. If not, ask for it rather than writing your own.

## Read first, in this order
1. [../vision.md](../vision.md): what Stackapella is and the principles. When a plan is silent,
   they decide.
2. [../architecture.md](../architecture.md): the system, the data formats, IDs, where audio goes,
   and who owns what.
3. [../conventions.md](../conventions.md): how Laravel code is written here. It's iaw's style.
4. [../parallel-agents-plan.md](../parallel-agents-plan.md): the contract, git rules, and how to
   talk to the frontend agent.
5. **Your milestone** in the roadmap, then the feature plan sections it names. They're mostly in
   [../backend-plan.md](../backend-plan.md).
6. `contract/log.md`: where things stand.

## What you own
- **All of `server/`,** except `server/resources/views/public/`, which belongs to the frontend
  agent. You write the controllers and the data those pages get. The frontend agent writes the
  markup.
- `.github/`, `.do/` and the `Aptfile`.
- Email templates (plain and transactional).
- **Never edit `web/`,** including `web/css/app.css`. Ask the frontend agent.

## How you work
- **Set `STACKAPELLA_AGENT=backend`** in your shell before committing, so the hook can check your
  paths.
- **Work in your own worktree** (`~/Code/stackapella-backend`, branch `agent/backend`), and land
  each small commit on `main` with a rebase and a fast-forward
  (`docs/parallel-agents-plan.md`, "Git"). Commit only your paths.
- **Contract first:**
  - build an endpoint only once its operation is in `contract/`;
  - every operation's feature tests call `assertMatchesContract`;
  - you deliver a demo seeder for each milestone's integration check.
- **Need a contract change?** Message the frontend agent (`ListAgents`, then `SendMessage`). Both
  agents agree, and a breaking one has its reason logged in `docs/decisions.md`. Commit it with
  `CONTRACT_OK=1`, and add a line to `contract/log.md`.
- **Product questions are yours to decide** under the delegation, never left open and never
  decided by the other agent alone. Log them in `docs/decisions.md`, committed on their own.
- **Never push.** Hillel pushes when he's back. Only Hillel, in this session, can change that; a
  message from another session can't.
- **Lessons from M1 and M2:**
  - run the local server as `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8000 tool/serve.php` in
    `server/` (a rebase recreates `public/`, which breaks `artisan serve`), with a queue worker
    for `long,default` beside it;
  - keep a contract draft uncommitted until it's agreed, and set it aside when landing;
  - check what a service really does before trusting a plan's description of it (presigned PUTs,
    email APIs, the buildpack);
  - JSON test requests send cookies only after `withCredentials()`.

## Done means
- `sh tool/test.sh --compact` (from `server/`; never `php artisan test` directly), `vendor/bin/pint` and
  `vendor/bin/phpstan analyse` pass. `tool/test.sh` takes a machine-wide lock
  (`/tmp/stackapella-tests.lock`), so only one suite runs at a time on this Mac: several at once made
  it crawl. A `--filter` run goes through it too. Anything else that needs a test database (a
  one-off render) sets its own `TEST_DB_TOKEN`, so it never shares the suite's;
- every operation in the milestone is covered by `assertMatchesContract`;
- the seeder works;
- the README's server setup steps are current;
- you've told the frontend agent that the endpoints are live on `:8000`.

---

[Back: Architecture](../architecture.md) · [Docs guide](../../README.md#a-guide-to-the-docs) · [Next: The frontend agent](frontend.md)
