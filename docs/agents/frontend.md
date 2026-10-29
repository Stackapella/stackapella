# You're the frontend agent

You build `/web`, the Vue PWA, while the backend agent builds `/server` at the same time. Read
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
12. **A take's frame 0 is the position recording started from:** the engine has already removed
    the measured latency. Never subtract it again (the M2 contract says so too).
13. **Land only with `tool/test.sh` run after the last rebase:** the other agent's contract
    change can make `api.js` incomplete the moment you rebase.

(12 and 13 were added after the M1 retrospective, `contract/log.md`.)

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
5. **Before touching audio** (the engine, recording, playback, Wait for me, pitch), re-read the
   architecture's invariants and `docs/audio-spike.md`. If the obvious fix is a timer, it's the wrong
   fix: schedule on the audio clock.
6. **Before bumping the IndexedDB version,** claim it in the "Now" block of `contract/log.md` (and
   land that line first), so a later session can't reuse it.
7. **In a milestone that adds public pages, the stub views are your first commit.**

## Read first, in this order
1. [../vision.md](../vision.md): what Stackapella is, who it's for first (beginners learning
   harmony), and the principles. When a plan is silent, they decide.
2. [../architecture.md](../architecture.md): the modules, the IndexedDB versions, the data formats,
   where audio goes, and who owns what.
3. [../conventions.md](../conventions.md), "Frontend": no npm, CDN libraries with SRI, templates
   only in our JS modules, no `v-html` for user text, and never timers for audio.
4. [../parallel-agents-plan.md](../parallel-agents-plan.md): the contract, git rules, and how to
   talk to the backend agent.
5. **Your milestone** in the roadmap, then the feature plans it names (Sing, singing feedback, the
   creator, the beta, the marketplace).
6. `contract/log.md`: where things stand.

## What you own
- **All of `web/`,** including `web/css/app.css` (the Tailwind build) and the admin screens.
- **`server/resources/views/public/`:** the public Blade pages' markup. They use the app's Tailwind
  build (Tailwind scans that folder too). The backend agent gives them their data, following
  the contract's view models.
- **Never edit anything else in `server/`.** Ask the backend agent.

## How you work
- **Set `STACKAPELLA_AGENT=frontend`** before committing, and **work in your own worktree**
  (`~/Code/stackapella-frontend`, branch `agent/frontend`). Land each small commit on `main` with a
  rebase and a fast-forward (`docs/parallel-agents-plan.md`, "Git").
- **At the start of a milestone, land a stub view first** for each new public page, so the backend's
  tests have something stable to render.
- **`api.js` is the only place that calls `/api`:** one function per contract operation.
- **Build against the mock** (`?api=mock`, and `?scenario=` for the error cases) until the backend
  agent says the endpoints are live. Then run the milestone's flow scripts against the real
  server (`API=http://127.0.0.1:8000` in `tool/serve.php`).
- **Admin screens and every form look like iaw's, not Event Schedule's.** Build them from
  Stackapella's `/styleguide` page, which follows iaw's (`docs/conventions.md`).
- **The shared modules come first:** `pitchtrack.js`, `scoring.js` and the session store. Don't
  write a second version inside a feature.
- **IndexedDB versions** are assigned in `docs/architecture.md`. Take the next free one if you
  build out of order, claim it in the log first, and never reuse one.
- **Need a contract change?** Message the backend agent (`ListAgents`, then `SendMessage`), as in
  the parallel-agents plan.
- **Product questions are yours to decide** under the delegation. Log them in `docs/decisions.md`.
- **Never push.**
- **Practical habits from M1:**
  - run `PORT=<another> tool/test.sh` when a review subagent or the other agent may be testing at
    the same time (the default port is shared);
  - files at the repo's root (like `.gitignore`) are outside your folders: use `web/.gitignore`;
  - in a store, every async step re-checks that the same project is still open after each wait;
  - test pitch features on singing that glides, breathes and drops octaves (`sing()` in
    `tests/fixtures.js`), not only on a tidy synth;
  - integration flows are named `*_integration.py` and run only with `API=` set.

## Done means
- `web/tool/test.sh` passes;
- the milestone's flow scripts pass on the mock and against the real server, at 390 x 844 and
  1440 x 900, muted;
- the CSS is rebuilt and committed;
- new files are in `sw.js`'s offline list;
- the README's web steps are current.

---

[Back: The backend agent](backend.md) · [Docs guide](../../README.md#a-guide-to-the-docs) · [Next: Conventions](../conventions.md)
