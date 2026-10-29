# Performance and robustness of the API

**Status (2026-10-27): reviewed and fixed by the backend agent; the load test is for the
coordinator to run on the beta.** What was checked, what was found, what changed, and what's left.

## What was checked

| Area | How | Found | Done |
|---|---|---|---|
| N+1 queries | Lazy loading already throws outside production (`Model::shouldBeStrict`), so tests catch it. A query count test (`tests/Feature/Ops/QueryBudgetTest.php`) measures each heavy read with a few items and with many: the catalogue, a sync pull, the gallery, the ideas shelf and an idea, a choir and its voices, billing.show and the course. | None: every one is flat (the same queries for 2 items as for 12). | The test stays, with a ceiling for each (below). |
| Indexes | Each table added today against its queries: store subscriptions, app devices, the course, ideas and their voices, choirs and their voices, song requests, social posts, share videos. | Every lookup has its index (the unique tokens and hashes, the user and status pairs, the listing orders). | Nothing to add. |
| Slow queries | Nothing logged them. | | One query at 300 ms or more, and a request's or job's queries adding up to 2 s, are logged with the SQL and where it ran, never the values (`config/ops.php`, `slow`; `SLOW_QUERY_MS`, `SLOW_QUERIES_TOTAL_MS`). |
| Payloads | Sizes on the dev server. | The catalogue is 18 KB (2.7 KB gzipped); a song 7 KB; the rest under 1 KB. Nothing to trim. | |
| Cache headers | Every API answer was `no-store`. | The public reads were fetched whole every time. | See "Cache headers". |
| Queue jobs | Each job's queue, timeout, tries and `failed()`. | The export ran on the default worker (60 s) with no timeout; a very active account's could be killed mid-way. Our social posts were already moved to the long worker today. Every other job fits its worker. | `BuildExport` runs on the long worker with a 280 s timeout. |

## The query budgets

What each read costs now (`QUERY_BUDGET_REPORT=1 php artisan test tests/Feature/Ops/QueryBudgetTest.php`),
and the ceiling the test holds it to:

| Read | Queries | Ceiling |
|---|---|---|
| templates.catalogue (signed out, signed in) | 10, 13 | 20, 25 |
| sync.pull | 19 | 30 |
| explore.list (signed out, signed in) | 18, 21 | 25, 30 |
| ideas.list, ideas.show | 19, 22 | 25, 28 |
| choirs.show, choirs.voices.list | 18, 19 | 24, 24 |
| billing.show | 11 | 20 |
| account.learning.show | 8 | 10 |
| usage.show, bin.list, groups.list | 13, 11, 10 | 17, 15, 14 |
| auth.config, billing.prices, account.show | 8, 7, 10 | 12, 11, 14 |

About 6 of each request's queries are the rate limiters' bookkeeping: the cache is MySQL's
(`CACHE_STORE=database`), so every limiter check reads its counters and takes a row lock to add
one. That's the largest fixed cost per request, and under load one network's requests wait for
each other on that lock. **For Hillel (it costs money):** a managed Valkey or Redis (DigitalOcean's
Managed Caching) for the cache, the limiters and the sessions would remove most of it. It isn't
needed for the beta's numbers; it's the first thing to do if the load test's p95 is over budget.

## Cache headers

The public, read-mostly reads (`templates.catalogue`, `explore.list`, `profiles.show`,
`profiles.harmonies`, `comments.list`, `harmonies.fills.list`, `ideas.list`, `ideas.show`,
`choirs.show`, `choirs.voices.list`, `billing.prices`) now carry an ETag of their answer and
`Cache-Control: private, no-cache`: only the viewer's browser keeps them, and it always asks again,
getting a 304 with no body when nothing changed. Browsers do this by themselves; an app that sends
`If-None-Match` must handle the 304. `private`, because an answer can name what this viewer liked
or unlocked. Nothing is cached by a shared cache (Cloudflare), and everything else stays `no-store`.
The song (`templates.show`) already had its own ETag.

## Load test (for the coordinator, on the beta)

`server/tool/load/beta.js`, for [k6](https://k6.io) (its own binary; never an app dependency). It
refuses to run against production.

```sh
k6 run -e BASE=https://beta.stackapella.com server/tool/load/beta.js
k6 run -e BASE=https://beta.stackapella.com -e TOKENS=sat_a,sat_b,sat_c server/tool/load/beta.js
```

- **Visitors:** 50 a minute for 5 minutes: `auth.config`, the catalogue (and its 304), the prices,
  the gallery, the ideas shelf and a choir (a closed feature's 404 counts as served).
- **Singers** (with `TOKENS`, app access tokens of the beta's test accounts): each opens the app 20
  times: the account, billing, the course, a sync pull and the catalogue.
- **Passes when:** fewer than 1% fail (a limit's 429 and a closed feature's 404 aren't failures);
  public reads p95 under 400 ms and p99 under 1 s; signed-in reads p95 under 600 ms and p99 under
  1.5 s (`public_ms`, `signed_in_ms`: the served answers, not the 429s).
- **Only the beta or this machine:** `BASE` must be `https://beta.stackapella.com` or a localhost
  server, and redirects aren't followed.
- **Limits are part of it:** from one machine, the per-network limits (60 signed-out requests a
  minute, 120 for the public pages) apply, and the `limited` rate counts the 429s. For more than one
  network's share, run it from several machines at once; there's no bypass for load tests.
- **Watch while it runs:** the slow-query log, the database's CPU, the queue's depth (`/up`), and
  the error rate. Write the numbers into this page under "Results".

## Results

**On the beta, one visitor (2026-10-29, the backend agent, from Hillel's network):** not the load
test (k6 isn't installed here, and the singers' part needs app tokens), but each public read 25
times over one kept-alive connection, so a request costs one round trip plus the server's work.

| Read | p50 | p95 | The server's share at p95 (less the ~300 ms round trip `/up` shows) |
|---|---|---|---|
| `/up` | 302 ms | 397 ms | ~95 ms (the health check reads the queue and the database) |
| `auth.config` | 301 ms | 368 ms | ~65 ms |
| `templates.catalogue` | 325 ms | 367 ms | ~65 ms |
| `billing.prices` | 284 ms | 461 ms | ~160 ms (and the per-network limit answered 429 by then: about 170 requests in a minute) |
| The landing, Songs, Pricing | 105 to 252 ms | 118 to 279 ms | Served from Cloudflare's edge (`public, max-age=300`) |

**Reading:** the server's own time is well inside the public reads' 400 ms p95, and the edge serves the
public pages. Nothing here calls for Valkey or Redis. That decision still waits for the load test
under concurrency (H22, Hillel: `brew install k6`, then the commands above with test accounts' app
tokens), which is what can show MySQL's cache and limiter rows under load.

Locally: below.

## Local performance pass (2026-10-28)

**Set-up.** A scratch database, `stackapella_perf`, seeded by `server/tool/load/seed.php`: 1,505
people, the 22 official songs and 300 library songs, 3,750 songs in progress with 15,000 parts and
30,000 takes (each part a current take and an older one in the bin), 150 groups of 16, 1,500
gallery harmonies with likes and comments, 300 ideas with voices, and a virtual choir of 400. Five
heavy singers have 150 songs each; one of them has realistic takes (two minutes, about 11,000
peaks each, as `Peaks` makes them). The server ran production-like on its own port: `APP_ENV=
production`, no debug, OPcache without timestamp checks, config, routes and events cached (to a
separate path, so the dev server wasn't touched), 8 workers, a queue worker running, the
slow-query log at 50 ms. `server/tool/load/profile.php` runs each hot read through the app in
process, 9 times, and gives its median time, queries, database time, the cache table's share,
its size and its slowest query.

**What was found, and fixed** (median of 9, on an M-series Mac; the database is local, so a
managed one adds its network round trips):

| Read | Before | After | What it was | The fix |
|---|---|---|---|---|
| A sync page (`sync.pull`), a singer with real takes | 8.2 MB, 2.6 s | 1.5 MB, 0.15 s | A page was 200 changes whatever their size, and every take sent its peaks (about 62 KB for two minutes); the takes query also read 201 takes' peaks to send a few | A page ends once its takes' peaks pass 250,000 (`Changes::PEAKS`; the contract already lets the server end a page, with `meta.more`), each take's count comes from its length, and only the takes sent have their peaks read |
| `explore.list` (the gallery, newest) | 110 ms (72 ms in one query) | 21 ms | MySQL read every ready harmony and sorted them (a row is about 24 KB) | An index `(visibility, status, deleted_at, id)`, forced on the plain newest-first list (MySQL's planner wouldn't pick it): 115 ms to 4 ms for that query |
| `bin.list` | 371 ms | 51 ms | It read every binned take's peaks, and never sends them | `Take::WITHOUT_PEAKS` |

**No N+1 anywhere:** each hot read makes the same number of queries for 2 items as for 12.
`QueryBudgetTest` now also holds `usage.show`, `bin.list`, `groups.list`, `auth.config`,
`billing.prices` and `account.show` to a ceiling (the table above, "The query budgets", plus these:
13, 11, 10, 8, 7 and 10 queries today).

**The load test** (`beta.js` unchanged, against `http://127.0.0.1:8091`, five singers' tokens,
5 minutes): 2,051 requests, **no failures**; 61% answered `429` by design (one machine is one
network: 60 signed-out requests a minute); public reads p50 40 ms, p95 315 ms, p99 under 1 s;
signed-in p50 72 ms, p95 506 ms; the catalogue p95 364 ms; every 304 revalidation worked. **Every
threshold passed.** The p95s carry the heavy singer's sync pages and a busy laptop; on the beta,
expect the network on top.

**Left as they are, with the numbers:**
- **Each first API call without a cookie made a session row** (the API is the web stack, for the
  PWA's cookie and CSRF): a browser made one and reused it, but a script or crawler without
  cookies wrote a row per request. **Fixed (agreed with the frontend):** an anonymous read starts
  no session, except `auth.config`, where the app gets its CSRF cookie
  (`SessionlessAnonymousReads`, `AnonymousReadsTest`).
- **Peaks were JSON numbers.** Paging stops a page from being 8 MB, but a first sync of 150 songs
  still moved 80 MB. **Done (agreed with the frontend and mobile, additive):** `?peaks=8` sends them
  as `peaks8`, one byte each in base64. A first sync of the heavy singer's 150 songs (1,200
  two-minute takes), measured locally:

  | | Pages | Size | Gzipped | Time |
  |---|---|---|---|---|
  | `peaks` (today's) | 55 | 80.2 MB | 25.6 MB | 25.9 s |
  | `peaks8` | 14 | 19.0 MB | 14.0 MB | 8.5 s |

  The seeded peaks are random, the worst case for gzip; real ones compress better. When the web
  and the apps both ask for `peaks8`, the numbers go.
- **Watch: the catalogue summary.** `templates.catalogue` loads every song's whole template to
  list them (21 to 39 ms at 22 songs). When the library passes a few hundred songs, keep a slim
  summary beside each version (key, tempo, parts, difficulty) and list from that.

**For Hillel (it costs money): Valkey or Redis for the cache, the limiters and the sessions.** The
evidence: of each request's 10 to 25 queries, 7 to 18 are the database cache's own (the limiters'
counters, read, locked and written on every request; a public page passes two limiters, 18
queries). They're over half of most requests' database time, and the writes stall when MySQL
flushes: one catalogue request spent 94 of its 135 ms of database time there. Under load, one
network's requests also queue on the same counter's row lock. DigitalOcean's Managed Caching
(Valkey) from its smallest size would take all of it off MySQL (`CACHE_STORE=redis`, and the
sessions too); nothing in the code changes but the settings. The beta's numbers don't need it yet:
it's the first thing to do if the beta's load test misses its p95.

---

[Back: The runbook](runbook.md) · [Docs guide](../README.md#a-guide-to-the-docs)
