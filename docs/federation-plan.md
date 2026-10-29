# The directory: self-hosted songs on stackapella.com (federation)

**Status:** planned (written 2026-10-27). Nothing is built, and no code is changed. It comes after
the open-source launch (`docs/open-source-plan.md`), because there are no self-hosted installs to
list until then, and it needs the hub's gallery (N1) built, which it is, behind the `gallery` flag.

Hillel's ask: "It seems like a great fit: self-hosters can share their songs in our directory.
They host the MP3 files, but our users find the songs on our site and listen there. Same approach
[as Event Schedule], with nice nudges to get self-hosters to opt in. Make it awesome."

**In the words people see, this is "the stackapella.com directory".** In code and in this plan it's
federation, as in Event Schedule. "The network" already means the network features (N1 to N7), so
the UI never calls this "the network".

Words used below:
- **the hub:** stackapella.com, the one install with `FEDERATION_HUB=true` (Event Schedule's
  "nexus");
- **an origin:** a self-hosted Stackapella that has joined the directory;
- **a listing:** one harmony (or, later, one template) from an origin, as the hub holds it.

## In short

- **Event Schedule's model fits, and most of its hard-won rules carry over:** an operator switch
  plus a per-person yes, an hourly signed push with a full-manifest reconcile, the hub approves an
  install once, per-item refusals with reasons, blocked rows as tombstones, a clean withdraw, and
  the nudges (section 1).
- **Recommended: the hub keeps a verified copy of each song on our media CDN,** fetched once from
  the origin, rather than streaming from the origin's server. The origin stays the source of
  truth; our copy lives only as long as its listing does. Privacy, our CSP, the apps, reliability
  and takedowns all point the same way (section 3).
- **Harmonies first, then templates in the library.** Ideas stay out (they need people to sing on
  someone else's install); a later phase can list them as links out.
- **The same rules as local content, enforced twice:** the origin only sends what its own copy of
  our code says is public, adult, consented and licensed; the hub checks again, holds an
  instance's first songs for review, and can hide a listing, block it for good, or suspend a whole
  install (section 6).
- **Three things Event Schedule doesn't have:** requests signed with Ed25519 keys (the hub never
  holds a secret that could forge an origin), a domain proof at registration, and a back channel
  so reports, decisions and appeals reach the origin (sections 4 and 6).
- **No ActivityPub.** Event Schedule doesn't use it ("Does Event Schedule federate like
  Mobilizon? Not over ActivityPub", `MarketingController.php` line 5495), and a hub-and-spoke
  directory is a fraction of its surface (section 4.9).
- **Nudges that never phone home:** an admin banner once there's something to list, a one-click
  join with a preview rendered locally by the same card component the hub uses, a per-singer ask,
  a "Listed on stackapella.com" badge, and stats returned on the sync the origin already makes
  (section 7).
- **The order of work** is split across the backend, frontend, landing and mobile agents in
  phases F0 to F5 (section 11), and **13 questions for Hillel** are at the end, each with a
  recommendation (section 14).

---

## 1. How Event Schedule does it

Read-only study of `~/Code/eventschedule` (2026-10-27). Paths are from its root.

### 1.1 The pieces

| Piece | Where | What it does |
|---|---|---|
| The switch between hub and install | `config/app.php` lines 136 and 144: `is_nexus` (`IS_NEXUS`), `nexus_url` (`NEXUS_URL`, default `https://eventschedule.com`) | One codebase plays both roles |
| The sender | `app/Services/FederationService.php` (1,265 lines) | Opt-in checks, identity, register, push, reconcile, withdraw, the payload, the preview, the prompts |
| The hourly run | `app/Console/Commands/FederateEvents.php` (`federation:push`), scheduled hourly in `routes/console.php` lines 220 to 222 | Retries a pending withdraw, reconnects when rejected, pushes, then reconciles |
| The hub's intake | `app/Http/Controllers/Api/ApiFederationController.php`; routes in `routes/api.php` lines 58 to 66, nexus only | `POST /api/federation/register` (throttle 10 a minute), `/events` (60), `/reconcile` (30) |
| The hub's upkeep | `app/Console/Commands/FederationMaintenance.php` (`federation:maintain`, hourly, `routes/console.php` line 225) | Fetches and stores listing images, prunes expired listings, tombstones, abandoned and stale installs |
| Models | `app/Models/FederatedInstance.php`, `FederatedEvent.php`, `FederationClicksDaily.php` | An install (status, host, encrypted secret, version, flags), a listing, a daily click count |
| Migrations | `database/migrations/2026_07_26_000000` to `000008`, `2026_07_28_000001`, `2026_08_28_000000`, `2026_09_17_000004`, `2026_09_18_000000` | The tables above; `events.federated_at`, `federated_hash`, `federated_skipped_at`; `roles.federation_enabled` (nullable: undecided); `users.federation_prompt_dismissed` |
| Moderation | `app/Http/Controllers/AdminFederationController.php`; routes `routes/web.php` lines 1166 to 1177; view `resources/views/admin/federation.blade.php` | Pending, approved, suspended and flagged tabs; approve, suspend, delete, bulk, welcome, accept a new address, block a listing |
| Blocking from the browse page | `MarketingController::toggleFederatedBlock()` (line 7312), `routes/web.php` line 1365 | An admin hides one listing where they see it |
| Display | `MarketingController` lines 7049 to 7102 (`/browse`'s own labelled section, filters by country, language and instance) | Cards link straight to the origin |
| Click counts | `MarketingController::federatedClick()` (line 7214), a beacon to `POST /browse/federated/{hash}/click`; `FederationClicksDaily::incrementClick()` | One row per install per day; the link itself stays a plain followable link |
| Hub stats | `app/Services/FederationStats.php`, `resources/views/admin/dashboard/_federation.blade.php` | Installs by status and version, live listings, clicks sent |
| Welcome email | `app/Services/FederationWelcomeService.php`, `app/Jobs/SendFederationWelcome.php`, `app/Mail/FederationInstanceWelcome.php`, `FederationInstanceReviewed.php` | Sent once on approval (an atomic claim), in the operator's language, with steps chosen by version |
| The operator's settings card | `AdminController` lines 2881 to 2973 (data) and 3390 to 3507 (save); `resources/views/admin/settings.blade.php` lines 172 to 268 | Switch, contact email, "also list these schedules" checklist, connection status, last sync, classified errors, the preview with a state per event |
| The nudges | `resources/views/partials/federation-prompt.blade.php` and `federation-listing-prompt.blade.php`; `HomeController` lines 318 to 326 and 1353 to 1466; routes `routes/web.php` lines 597 to 599 | The admin's "share your events" prompt; each schedule owner's "List on the network" prompt |
| Per-schedule opt-in | `app/Models/Role.php`: `scopeFederationEligible()` (line 2520), `FEDERATION_FIELDS` (line 343), the guard at lines 894 to 905; `RoleUpdateRequest` line 264 | Three states: undecided (null), listed, not listed |
| Docs | `resources/views/marketing/docs/partials/federation-content.blade.php`, wrapped by `docs/saas/federation.blade.php` and `docs/selfhost/federation.blade.php` | Turning it on, per-schedule control, what a listing looks like, sync, and a field-by-field "what is shared" table |
| The terms | `resources/views/marketing/self-hosting-terms.blade.php` | Federation listed as an opt-in crossing; the Data Use clause reaches only what an install sends |
| Tests | `tests/Feature/Federation*.php` (13 files: API, browse, docs, hardening, listing prompt, prompt, reconcile chunks, recurring, review, settings, share, welcome) and `AdminDashboardFederationTest.php` | |

### 1.2 How it works

- **Opt-in, twice.** The operator turns the install on (`Setting federation_enabled`), and each
  schedule opts in for itself (`roles.federation_enabled`: null means undecided, which shares
  nothing). On a co-listed event, any participant's explicit "not listed" vetoes the listing.
  Existing schedules were reset to undecided rather than enrolled silently
  (`2026_07_28_000001_reset_federation_enabled_to_undecided.php`).
- **Identity.** The install mints a UUID (`instanceId()`) and a 32-byte secret (`secret()`) only
  once it opts in. `register()` sends both, with `site_url`, name, contact email, version and the
  admin's locale. A new id registers unauthenticated and lands `pending`; re-registering an
  existing id must be signed with the secret on record (rotation). A host change on register puts
  an approved install back in review and flags it.
- **Signing.** Every request is an HMAC-SHA256 of the raw body in `X-Federation-Signature`
  (`send()`, line 768; `verifySignature()`, line 466). Validation runs on the signed JSON body
  only (`validateSignedBody()`, line 490), because the merged input would let `?is_final=1` on the
  URL drive a full sweep. The hub stores the secret encrypted, since it needs the plaintext.
- **Push, hourly.** `push()` sends what's never been sent (500 a run, 100 a request), plus
  recurring events whose dates moved (a hash), with two budgets so recurring events can't starve
  new ones. The hub validates each item on its own and answers `skipped_ids`; the sender marks
  those `federated_skipped_at`, never `federated_at`, so reconcile doesn't re-send them forever.
- **Reconcile, hourly.** Events are hard-deleted, so there are no tombstones to send. Instead the
  sender sends the full list of ids it still considers shareable, in chunks under one `run_token`;
  the hub stamps each with the token, and on the `is_final` chunk deletes everything else (except
  blocked rows). The response names ids the hub doesn't hold, and the sender re-queues them, so
  neither side can deadlock.
- **Withdraw.** Turning sharing off sends an empty final manifest at once, which drops everything;
  if the hub can't be reached, `federation_withdraw_pending` retries on every run, even while the
  switch is off.
- **What the hub stores:** the listing's display fields, and a copy of its image, fetched out of
  band through an SSRF-safe GET with magic-byte checks ("Hotlinking instead would leak visitor IPs
  to arbitrary hosts and force img-src open", `FederationMaintenance::fetchImages()`). There's no
  copy of the event page: every card links to the origin with a normal followable link, never a
  tracking redirect.
- **Trust.** An admin approves an install once; after that its events publish automatically.
  Backlinks must be on the registered host or a subdomain (`FederatedInstance::ownsUrl()`), so an
  approved install can't become a backlink farm. Titles go through the same junk filter as local
  events. Per-install cap 5,000 listings; at most 500 pending registrations; registration never
  emails the attacker-supplied address.
- **Moderation.** Approve, suspend (kept as a tombstone, so re-registering can't escape it),
  delete, block one listing (survives re-pushes because `blocked_at` isn't fillable), accept a new
  address. Pruning: expired listings after a week, tombstones after 180 days, pending installs
  quiet for 60 days (or 7 days with nothing ever pushed and 3 days quiet), listings of suspended
  installs quiet for 60 days.
- **Failures the operator can act on.** `classifyFailure()` maps responses to `rejected`,
  `over_limit`, `validation`, `not_available` and `error`, each with its own sentence on the card;
  the raw response is never shown.
- **Versions.** Every request carries `app_version`; the hub picks the welcome email's steps by
  version (`ONE_CLICK_LISTING_VERSION`). There's no protocol number.
- **Nudges.** The admin prompt appears only when enabling would really publish something (the real
  query, cached ten minutes), never on a demo, never again after the install has connected once,
  dismissible for good. Once joined, each schedule owner is asked on the dashboard and on the
  schedule's page, up to 20 schedules at a time, each dismissible, and never while suspended or
  for someone who turned suggestions off. A listed schedule shows "Listed on the network".
- **Privacy.** The docs list every field sent and where it shows. No attendee, ticket or private
  data; not even the joining link of an online event (only that there is one).
- **Lessons written into its comments** that we keep: register routes everywhere and 404 at run
  time (tests pin `IS_NEXUS=true`, so registration-time gates are untestable); validate only the
  signed body; strip control characters from remote text; never fetch remote URLs on the request
  path; never mint an identity before the operator opts in; a refusal is permanent, so null a bad
  optional field rather than refuse the item.

---

## 2. What Stackapella already has to build on

- **The gallery (N1),** built behind the `gallery` flag (`app/Support/Gallery/Gallery.php`): a
  harmony is listed when unlisted, `inGallery`, live, rights confirmed, not held, its owner 18 or
  over with a public profile; `Consents::allAgreed()` for friends who sang on it; first two posts
  held below trust level 3.
- **The share page** `/h/<id>` (`public.harmony`): AAC in MP4, peaks, part colours and waveforms,
  a 1200 x 630 card, credits from the server, `noindex` while unlisted. Files live in `private`
  and are copied to `media` only while live (`App\Support\Sharing\HarmonyFiles`), with new random
  keys after any break, and `PurgeCdn` on the way out.
- **The library (N3)** and its licensing declaration (`original` or `public_domain`, `ownWork`),
  and **ideas** (`ideas` flag).
- **Moderation:** polymorphic `reports` (3 people hide a page), `notices` with one appeal within 6
  months (`Notice::APPEAL_MONTHS`), the trust and safety queue, the blocklist, flags with reasons
  and sudo, trust levels.
- **Safe outbound HTTP:** `App\Support\Http\PublicAddress` (https only, no private ranges, the
  connection pinned to the checked IP), already used for self-hosted Event Schedule and Invoice
  Ninja.
- **A fake-service pattern** for outside systems (`FakeEventSchedule`, `app:event-schedule:fake`).
- **The rules this must keep:** no licensed music (public domain everywhere, or original); 13 and
  over for accounts, 18 and over for anything public; public songs' voices can become guides
  (a condition of going public on stackapella.com); no analytics (counts from our own records);
  a fresh self-hosted install makes no outbound request on its own (open-source plan 6.3); the
  CSP's `media-src` allows only our media CDN.

---

## 3. The big choice: where the audio plays from

**Decided (Hillel, 2026-10-27): D, a verified copy on our media CDN.** The other 12 questions are still open.

Hillel's framing is that self-hosters host the files and our pages play them. Four ways to do it:

| | A. Stream from the origin | B. Proxy through our PHP | C. Pull-through CDN cache | **D. Copy once to our media CDN (recommended)** |
|---|---|---|---|---|
| Listener privacy | Every listener's IP and user agent go to a third-party server we don't control. Our privacy policy would have to name every origin | Origin sees only our server | Origin sees only Cloudflare | Origin sees only one fetch by our server |
| CSP | `media-src` opened to every approved host (per page, at best); the apps need each host too | Unchanged | Unchanged | Unchanged |
| The native apps | Must play from arbitrary hosts; iOS ATS needs each to be https and well configured | Fine | Fine | Fine: same CDN as every other song |
| Reliability | A choir's small VPS or home server, asleep or down, breaks our page; range requests and CORS must be configured right by every self-hoster | Our PHP workers tied up streaming | Cold misses still hit the origin; an origin outage breaks uncached songs | Plays even when the origin is down (within the grace period, 6.6) |
| Bandwidth | The origin's (a popular song could cost a choir real money) | Ours, through PHP: the worst | Ours, at the edge | Ours, at the edge; the origin pays for one download per version |
| Integrity | The file can change after review without anyone knowing | Can change | Can change between purges | Pinned: we serve the bytes whose SHA-256 the origin signed (4.4) |
| Takedown | We can only hide our page; the file stays reachable | Instant | Needs a purge | Instant: unpublish and purge, as `HarmonyFiles` does today |
| Waveforms and cards | Trust the origin's peaks; fetch its card image | Same | Same | Computed by us from the file (can't lie), card drawn by us |
| Matches Event Schedule | No: ES copies images for exactly these reasons | | | Yes: the same reasoning as ES's image copies |
| Legal | We link | We transmit | We cache | We host a copy: the directory terms must grant it (9.2), and we answer DSA notices for it, which we do anyway |

**Recommendation: D.** The origin remains the home and the source of truth: the listing exists only
while the origin's signed manifest says so, the copy is deleted the moment it doesn't, and every
listing credits and links back to the origin. To a self-hoster it's simply better than A: their
server is never hammered, and their song plays even if their server naps.

Cost, for scale: a three-minute harmony is about 3 MB of AAC. 10,000 listings is about 30 GB on
Spaces, which is pennies a month, and served from the CDN we already pay for. Per-instance quotas
(6.5) cap it.

What the hub fetches, per listing, is exactly **one file: the audio.** No avatars (initials
instead), no card image (we draw our own with "from choir.example.org"), no peaks (we compute
them). Templates are data, sent in the signed request itself (5.3).

---

## 4. The protocol

### 4.1 Shape

Hub and spoke, like Event Schedule: origins push to the hub; the hub never calls an origin except
to fetch the well-known document and the audio file. Everything the hub needs to tell an origin
(reports, decisions, stats) rides back on the responses to the origin's own hourly calls, so a
self-hosted install needs no new inbound endpoint beyond one static JSON file.

All bodies are JSON, camelCase as the rest of our contract, times UTC ISO 8601 with `Z`. Every
body carries `"protocol": 1`. The spec lives in `contract/federation.md` with schemas
`contract/schemas/federation.*.json` and examples `contract/examples/federation.*.json`, changed
by the usual contract rules (`CONTRACT_OK=1`, `contract/log.md`).

### 4.2 Identity and signing: Ed25519, not a shared secret

ES sends a shared secret at registration and stores it encrypted, because HMAC needs the plaintext
on both sides. We use **Ed25519 signatures** instead (`sodium_crypto_sign_*`, in PHP's core
sodium extension since 7.2, so **no new dependency**):
- the origin generates a key pair when the operator joins (never before, ES's rule); the private
  key is stored encrypted in `settings`; the public key goes to the hub;
- the hub stores only public keys, so a leak of the hub's database can't be used to send listings
  in anyone's name;
- a signed item is evidence of what the origin asserted (its rights and consent attestations,
  4.4), kept with the listing for disputes.

Every request carries:
- `X-Stackapella-Instance: <uuid>`;
- `X-Stackapella-Timestamp: <unix seconds>`;
- `X-Stackapella-Signature: ed25519=<base64>` over
  `"stackapella-federation-v1\n{timestamp}\n{METHOD}\n{path}\n{hex sha256 of the raw body}"`.

The hub refuses a timestamp more than 5 minutes off, and remembers each signature for 10 minutes
(cache), so a captured request can't be replayed. As in ES, validation reads only the JSON body
(our FormRequests for these routes override `validationData()` to return `$this->json()->all()`),
never the merged input.

**Key rotation:** `register` signed with the current key may carry a new `publicKey`. **Lost
key** (a restore without the settings row): the operator re-registers as a new instance id, and the
hub links it to the old one in admin (same host, proven by the well-known file) so Hillel can carry
its standing over with one click.

### 4.3 Domain proof (new)

ES trusts the first registration's `site_url` until a person reviews it. We prove it: the origin
serves `GET /.well-known/stackapella-instance.json` (no session, cacheable 5 minutes):

```json
{ "protocol": 1, "instanceId": "8c1d...", "publicKey": "base64...", "name": "Riverside Choir",
  "directory": true }
```

It answers `404` unless the operator has joined (so a fresh install reveals nothing). On
`register`, and whenever the reported address changes, the hub fetches it through `PublicAddress`
(https, public IPs, pinned) and requires the id and key to match. A registration whose proof fails
stays `pending` with "We couldn't confirm this address" for Hillel, and is retried hourly for a day.
This closes "register as someone else's site" without a person having to spot it.

### 4.4 The endpoints (on the hub, under `/api/federation`)

Registered on every install and answering `404 not_available` unless `config('federation.hub')`
(ES's lesson), and `423 paused` while the hub's `pause_directory` switch is on. Each has a named
limiter (6.5).

| Operation | Method and path | Signed | What it does |
|---|---|---|---|
| `federation.register` | `POST /register` | First contact no; otherwise yes | Introduce or update the install: `instanceId`, `siteUrl`, `name`, `contactEmail`, `publicKey`, `appVersion`, `locale` (only from an admin's request), `termsVersion`, `showInCommunities`. A new id lands `pending` (the proof job runs); an existing id must be signed by its current key. A host change re-pends an approved install and flags it (ES). Answers `{ status, listingsUrl, minProtocol, termsVersion }` |
| `federation.items` | `POST /items` | Yes | Upsert up to 100 items (4.5). Per-item validation; answers `{ accepted, skipped: [{ externalId, reason }], status, listingsUrl }` |
| `federation.remove` | `POST /items/remove` | Yes | Remove these `externalIds` now: a delete, a withdrawn consent, a song made private, a hide on the origin. Not in ES (its events are hard-deleted with nothing left to name); we know at the moment of removal, so the hub hears within a minute instead of an hour |
| `federation.reconcile` | `POST /reconcile` | Yes | ES's full manifest: `{ runToken, isFinal, externalIds, knownIds, acks, appeals }`; the final chunk sweeps everything not named (except tombstones). Answers `{ removed, missing, status, listingsUrl, inbox: [...], stats: {...}, minProtocol }` |
| `federation.withdraw` | (a `reconcile` with an empty final manifest) | Yes | Leaving the directory: everything goes; retried on every run until it lands (ES's `federation_withdraw_pending`) |

**Error codes** (stable, `error.json`): `403 unknown_instance`, `403 signature_invalid`,
`403 timestamp_skew`, `404 not_available`, `413 too_large`, `422 validation_failed` (the
envelope only; bad items go in `skipped`), `423 paused`, `426 protocol_outdated` (with
`minProtocol`), `429 rate_limited` (with `Retry-After`), `429 queue_full` (too many pending
registrations), `422 limit_reached` (the instance's cap). A suspended instance gets `200` with
`status: "suspended"` (ES), so its operator sees the state rather than an error.

**Skip reasons** (the origin's preview shows each in plain words; ES only had ids, so its preview
could say "Not accepted" but not why): `invalid`, `off_host`, `too_long`, `licence_unclear`,
`official_song` (a copy of one of our own templates, 5.3), `title_held`, `blocked`,
`limit_reached`, `audio_unreachable`, `audio_mismatch`, `audio_not_audio`.

### 4.5 A harmony item

```json
{
  "kind": "harmony",
  "externalId": "k3Xb9QmT7wLp",
  "revision": "sha256 of the item's own fields",
  "pageUrl": "https://sing.riverside.example/h/k3Xb9QmT7wLp",
  "title": "Silent Night for Mum",
  "language": "en",
  "durationSeconds": 182.4,
  "audio": { "url": "https://media.riverside.example/h/abc.m4a", "type": "audio/mp4",
             "bytes": 2951034, "sha256": "..." },
  "parts": [{ "name": "Alto", "color": "#e4a94b", "kind": "voice" }],
  "creditLine": "Sung by Ana Ruiz",
  "singers": [{ "name": "Ana Ruiz", "handle": "ana", "profileUrl": "https://sing.riverside.example/@ana" }],
  "song": {
    "official": { "slug": "silent-night", "version": 3 },
    "title": "Silent Night",
    "credits": "Words: Joseph Mohr (1816). Tune: Franz Xaver Gruber (1818)",
    "arrangedBy": "Stackapella",
    "licence": { "kind": "public_domain", "source": null }
  },
  "attest": { "rightsConfirmedAt": "...", "everyoneAdult": true, "everyoneAgreed": true,
              "everyoneListed": true },
  "publishedAt": "2026-11-02T18:00:00Z"
}
```

- `pageUrl`, `audio.url` and every `profileUrl` must be on the instance's host or a subdomain
  (`ownsUrl`, ES); a bad optional URL is nulled, never the cause of a refusal (ES's lesson).
- `song` is null for a recording not made from a template; `licence.kind` is `public_domain`,
  `original` or `stackapella` (one of our arrangements, which anyone may sing and publish with the
  credit, `CONTENT-LICENSE` grant 2).
- `attest` is the origin's own code's answer (5.1). Every value must be true, or the item isn't
  sent; the hub refuses one that isn't (`invalid`).
- The hub recomputes peaks and duration from the file, and refuses a duration more than a second
  off (`audio_mismatch`) or over 10 minutes (`too_long`).

### 4.6 The inbox (the back channel; new)

ES's hub never tells an install anything but its status. Ours must, for trust and safety. Each
`reconcile` response carries up to 100 `inbox` entries; the origin handles them and acknowledges
their ids on its next call (`acks`); the hub resends anything not acknowledged.

| Kind | Sent when | The origin does |
|---|---|---|
| `report` | Someone reports a listing on the hub (`reason`, `note` as plain text, capped at 500) | Files a report against its own harmony from "stackapella.com", shown in its trust and safety queue; it doesn't count toward the origin's own 3-report rule (the hub has already hidden its copy if needed) |
| `decision` | Hillel (or the 3-report rule, or a DMCA notice) hides, removes or restores a listing, or holds it for review | Gives its singer a notice (`directory_hidden` or `directory_removed`, with the rule and Hillel's note, `automatic` when it was) and shows "Hidden on stackapella.com" on the harmony |
| `appeal_result` | Hillel decides an appeal | Updates the notice (`kept` or `reversed`) |
| `instance` | The instance is approved, suspended or reinstated, or its quota changes | Updates the card's status line |

Appeals go the other way: the singer asks for a review on the origin's notice (the existing
`notices.appeal`), and the origin forwards `{ decisionId, text }` in `appeals` on its next call; the
hub files it in `admin.appeals.*` with "From choir.example.org". So the DSA's statement of reasons
and appeal reach the person whose voice it is, through their own install.

### 4.7 Cadence

- **Hourly** (`app:federation:sync` in the origin's scheduler, `withoutOverlapping`): retry a
  pending withdraw, re-register if rejected or the contact address is stale (ES), push what's
  unsent or changed (100 a request, 500 a run), then reconcile (which also brings the inbox and the
  stats).
- **Soon after a change** (a queued, unique job a minute later): a song newly listed or edited, so
  it appears within minutes rather than the hour.
- **At once** (`federation.remove`): anything leaving. Privacy changes must be fast.
- **On the hub, hourly** (`app:federation:maintain`): fetch audio for new and changed items,
  re-check domain proofs, pause and purge quiet instances, prune tombstones and abandoned
  registrations (ES's rules and numbers, 6.6), recount stats.

### 4.8 Versions and compatibility

- **`protocol`** is an integer in every body. Additive changes (a new optional field, a new skip
  reason, a new inbox kind the origin may ignore) keep it. A breaking change bumps it; the hub
  serves the old and the new side by side for at least 6 months, and its responses carry
  `minProtocol` so the origin's card can say "Update by 1 May to stay listed" before it happens.
  An origin below `minProtocol` gets `426 protocol_outdated`; its listings stay up until the
  deadline, then pause.
- **Unknown fields are ignored** on both sides; unknown inbox kinds are acknowledged and logged.
- **`appVersion`** is reported (as ES) for the admin's view and to pick the welcome email's steps.
  Stackapella has no release numbers yet (`/up` reports a commit): this plan asks for semver tags
  from the open-source launch (question 9), and the first release with federation becomes
  `FederatedInstance::MIN_APP_VERSION`.
- **Golden fixtures:** each protocol version's example requests stay in `contract/examples`, and
  the hub's tests replay the previous version's against the current code.

### 4.9 ActivityPub: no

Event Schedule doesn't use ActivityPub (its own comparison page says so). For us it would mean
JSON-LD actors, inboxes and outboxes per person, HTTP Signatures, delivery queues, and content
arriving from any server in the fediverse, with moderation we can't bound. Stackapella is "not a
social network first", and what Hillel asked for is a directory: one hub, opted-in installs, our
rules. The signed push above is a few hundred lines; ActivityPub compatibility would be a project
of its own with little to show for a singer. Revisit only if self-hosters ask to follow each other
across installs, and even then as its own plan.

---

## 5. What gets listed

### 5.1 On the origin: what's "listable" (one query, its own code)

Like ES's `federatableQuery()`, one definition, mirrored by an in-memory check for the preview. A
harmony is listable when **all** of these hold:

1. The install has joined (`Setting federation_enabled`, never on the hub itself).
2. It's live (`Harmony::isLive()`: unlisted, ready, not moderated, not deleted).
3. Its owner chose to list it (`harmonies.directory_opt_in_at`) and confirmed the rights for it
   (`rights_confirmed_at`, with the directory sentence, 9.1).
4. Its owner, and every friend whose take is in it (`singer_ids`), is 18 or over
   (`adult_confirmed_at`), not suspended, and has said yes to the directory for themselves
   (`users.directory_listing = true`, below). Under-18 voices never leave the install.
5. Every friend agreed to it going public (`Consents::allAgreed()`).
6. Its song is acceptable: one of our arrangements, or a template whose declaration is `original`
   or `public_domain`, or no template at all with the owner's rights confirmation (the gallery's
   rule).
7. It isn't hidden on the hub (a tombstone the inbox told it about), and the instance isn't
   suspended.

**It doesn't require the origin's own gallery to be open.** A choir's install will rarely open a
gallery of strangers, and the directory is exactly how their songs can be found anyway. So the
directory has its own opt-in on the origin rather than reusing `inGallery`.

**The per-person setting** (ES's per-schedule three states, applied to people, since it's their
voice): `users.directory_listing` is null (not decided), true or false, with `decided_at`. Null and
false both keep that person's voice out; false vetoes any song they sang on, whoever owns it (ES's
co-listed veto). Accounts that existed before the install joined start at null, never enrolled
silently (ES's reset migration).

### 5.2 Harmonies (phase F1 and F2)

The core of it: finished harmonies, heard on the hub, credited and linked back.

### 5.3 Templates in the library (phase F4)

A template a person on the origin made, listed in the hub's library with "from
choir.example.org", so our singers can learn it:
- sent in the item itself (`kind: "template"`, the full template JSON with its `sha256`, its
  declaration, its creator), validated against our `contract/schemas/template.json` on the hub,
  with unknown fields stripped and any `guide.recording` removed (synthesized guide only in v1);
- **our own songs are never listed back:** a template whose content matches an official template
  (by slug and content hash) is skipped (`official_song`);
- on the hub it behaves like a public library template (`templates.show` `grant: "directory"`):
  "Learn this" makes a project from a copy, which the singer keeps if the listing goes (as the
  library's hidden templates already do);
- `uses` (people who made a project from it) goes back to the origin in the stats.

### 5.4 Not in v1

- **Ideas:** singing a part on someone else's tune needs an account on that install and two-way
  writes. A later phase (F5) can list ideas with "Sing a part on choir.example.org", linking out.
- **Remote profiles, follows and comments** on listings: no. Credits link to the origin's
  `/@handle`. Comments are left off because the owner can't see or answer them; likes are fine
  (question 6).
- **Guide voices from listings:** no (question 4). The consent the singer gave was to their own
  install's terms, not to ours.
- **Stems, takes, scores, pitch tracks, open parts, virtual choir voices:** never sent.

---

## 6. Trust and safety for content we don't host originally

### 6.1 The same rules, checked twice

| Rule (local) | On the origin | On the hub |
|---|---|---|
| Rights declaration | `rights_confirmed_at` with the directory sentence; the template declaration | `attest.rightsConfirmedAt` required and kept with the signed item |
| No licensed music | The song's `licence` must be `public_domain`, `original` or `stackapella` | Same check; `licence_unclear` otherwise; the first songs of every instance held for a look (6.2); DMCA as for local pages |
| 18 and over for anything public | Everyone on it confirmed 18 or over | `attest.everyoneAdult`; the operator warrants it in the terms (9.2) |
| Everyone who sang agreed | `Consents::allAgreed()` plus each person's own directory yes | `attest.everyoneAgreed`, `attest.everyoneListed` |
| Text rules | The origin's own `TextFilter` | Ours again: control characters stripped (ES), the word list holds a title (`title_held`: shown as "Untitled song" until Hillel looks), no links in titles, lengths capped |
| A shared harmony looks like one made in the app | `ProcessHarmony` on the origin | ffprobe, codec AAC or MP3, duration and size match the signed item, 10 minutes at most, SHA-256 equal to the signed one |

### 6.2 Review on the hub

- **Instances:** approved once by Hillel (ES), after the domain proof. Instance names containing
  reserved words ("Stackapella", "official", "admin") are held for a look (the handle rules).
- **Probation (new):** a newly approved instance's first **5** harmonies (a setting) wait in the
  trust and safety queue as "Directory: first songs", like a level-2 singer's first two gallery
  posts. After that, its items list automatically. Hillel can set an instance to **Trusted** (never
  held) or **Watch** (every item held).
- **A changed file** (new SHA-256) during probation or on a Watch instance is reviewed again; the
  old copy keeps playing until the new one is approved or the old one is removed.

### 6.3 Reports, hiding and blocking

- **The Report button** on every directory card and page, with or without an account, the same
  form and limits as `/h/<id>` (`reports.create` gains `directoryItemId`). Reports are polymorphic
  already, so a `federated_items` row is simply reportable.
- **3 reports from different people hide the listing** on the hub until Hillel looks
  (`under_review`), and every report goes to the origin's inbox (4.6).
- **Hillel's actions on a listing:** hide, restore, remove (files gone), **block** (a tombstone
  that survives re-pushes, ES's `blocked_at`, kept 180 days after the origin stops sending it).
- **On an instance:** approve, suspend (every listing hidden; the row kept as a tombstone, so
  re-registering can't escape it, ES), delete, **block the host** (a new blocklist kind,
  `instance_host`, so a fresh instance id from the same domain can't register).
- **Strikes:** each upheld copyright or offensive removal is a strike on the instance; 3 within 90
  days suspend it pending Hillel's look (proposed). A DMCA notice hides at once, as for local pages,
  and counts toward our repeat-infringer policy per instance.
- **Switches:** `directory` (a feature flag: off, every directory read answers `404` and intake
  `404 not_available`) and `pause_directory` (a switch: stop accepting new items and fetching, while
  existing listings keep playing). Both audit-logged with a reason, as every flag.

### 6.4 Statement of reasons and appeals

The hub's decisions reach the singer through their install (4.6): the origin raises a notice of
kind `directory_hidden` or `directory_removed`, and the appeal comes back. The operator is told
too (their admin queue shows the hub's decision beside the report).

### 6.5 Limits (proposed, all in `config/limits.php`, changeable live in admin)

| What | Limit |
|---|---|
| `register` | 10 a minute per IP; at most 200 pending instances at once (`429 queue_full`) |
| `items` | 60 requests a minute per instance; 100 items a request; 64 KB a harmony item, 512 KB a template item |
| `remove` | 120 a minute per instance |
| `reconcile` | 30 a minute per instance; 2,000 ids a chunk |
| Listings per instance | 2,000 harmonies and 500 templates (`limit_reached`) |
| Audio per instance | 5 GB stored |
| Audio fetches | 50 per instance and 200 in all per hourly run; 25 MB a file; 30 seconds a fetch |
| Plays counted | One per listing per page view; limiter keyed by the IP HMAC (as every limiter), nothing stored |

Hillel can raise one instance's caps in admin.

### 6.6 When an instance goes quiet, changes or removes a song

| What happens | What the hub does | What a listener sees |
|---|---|---|
| The origin removes a song (delete, made private, consent withdrawn, hidden there) | `federation.remove` at once (the reconcile sweep is the safety net): files unpublished and purged from the CDN, row deleted (or kept as a tombstone if blocked) | `410`: "This song is no longer on stackapella.com. It may still be on choir.example.org" (the link only while the instance is approved) |
| The origin edits the title or credits | Updated on the next push | The new title |
| The origin replaces the audio | Fetched and verified; the copy swaps to a new random key; the old one is purged | The new recording, once checked |
| The origin is unreachable for a fetch | Retried hourly for a day, then `audio_unreachable` | Nothing listed yet |
| No sync for **7 days** | Its listings **pause**: hidden from Explore and the library; their pages answer `503` with a kind page | "This song's home, choir.example.org, hasn't been in touch for a while, so it's resting here. It'll be back when its home is." |
| No sync for **60 days** | Purged: files deleted, rows dropped (ES's stale rule) | `410`, as above |
| The origin leaves the directory | Everything removed at once (withdraw) | `410` |
| The origin changes its address | Flagged and re-pended (ES), with the domain proof re-run; Hillel accepts the new address in one click | Listings keep playing from our copies meanwhile |
| The origin is suspended | Every listing hidden | `410`, without the link back |

The pause exists because removals depend on the origin telling us: a server that died can no
longer pass on its singers' deletions, so we don't keep their voices public indefinitely on its
behalf.

---

## 7. The nudges (for self-hosters)

Event Schedule's nudges, adapted, with one rule above all: **nothing on a self-hosted install
contacts stackapella.com until its operator joins.** The README promises it (open-source plan
6.3), and the nudges keep it.

1. **The admin banner** (ES's adoption prompt): shown to admins once the install has at least one
   harmony that would be listable if the install joined (the real query with "not decided" people
   counted as willing, cached ten minutes, ES), never on the hub, never after the install has
   joined once, dismissible for good per admin. The words:
   > **Let the world hear your choir.** Your singers' songs could be heard by over 12,000 singers on
   > stackapella.com, each one credited to them and linked back here. Off until you say so, and
   > only the songs each singer chooses.
   >
   > [Preview your listing] [Not now] [How it works]

   The number is honest and offline: `config/federation.php` `reach` (singers active in the last
   month on stackapella.com, rounded down to the thousand, with its date), set by Hillel from the
   admin overview at each release. When it's unset, the sentence has no number.
2. **One-click join:** "Preview your listing" opens a styled modal (never a browser dialog) with:
   - the install's own songs drawn **exactly as stackapella.com would show them**, by the same card
     and page components the hub uses (it's the same code), with "from your-host" on each: a real
     preview with no request;
   - the terms in four plain lines (what's shared, that we keep a copy while it's listed, that
     singers choose for themselves, that leaving removes everything), and a link to the full text;
   - the contact email, filled in with the admin's;
   - one button: **Join the directory.** It registers, and the card shows "Waiting for review".
3. **The settings card** (Admin, Settings, "The stackapella.com directory"): the switch, the
   contact email, "Show our community on stackapella.com/communities", the status line (Waiting
   for review, Listed, Suspended, Not connected) with the last sync, the classified errors in plain
   words (ES's list plus `protocol_outdated`), the preview of what will go out with a state for
   each song (Listed, Next sync, Waiting for review, Not accepted with its reason, Hidden on
   stackapella.com), and "See your listings on stackapella.com".
4. **The singers' ask** (ES's per-schedule listing prompt): once the install has joined, each adult
   singer with a live harmony gets one card in the app: "List your songs on stackapella.com too?
   Your public songs can be heard there, credited to you. You choose song by song." [Yes, list
   them] [Not now]. Not now is remembered; never shown to under-18s, while the install is suspended,
   or to someone who turned suggestions off.
5. **The share sheet:** on a joined install, "Also list it on stackapella.com" under the visibility
   choice, ticked when the person said yes, with the directory's rights sentence.
6. **Badges:**
   - on the origin's own song page and the singer's harmony card: "Listed on stackapella.com",
     linking to the hub's page;
   - for the operator's website: a small "Our songs are on stackapella.com" SVG badge shipped in
     the code (`web/icons/directory-badge.svg`, served by their own install, never hotlinked), with
     a copy-paste snippet on the settings card linking to their community page.
7. **Stats they can see,** on the admin dashboard tile and each singer's own harmony, from counts
   we keep anyway, returned in the reconcile response (no extra call):
   - songs listed, and waiting for review;
   - plays on stackapella.com in the last 30 days (question 5), per song and in all;
   - likes on stackapella.com;
   - people who learned their templates there (`uses`, F4);
   - sign-ups on their own install from the directory, from their own records (`?ref=d-<id>` on the
     link back, recorded as `ShareSignUps` already does).
8. **Onboarding:** `php artisan app:doctor` lists "Join the stackapella.com directory (optional)"
   among its suggestions, and the README's self-host steps end with it.
9. **The welcome email** once approved (ES's `FederationWelcomeService`, one atomic claim), in the
   operator's language, with the three things to do next (ask your singers, add the badge, check
   the preview); a short email on suspension and reinstatement. No other email, ever.

---

## 8. The design

### 8.1 Configuration

`server/config/federation.php` (no `env()` elsewhere):
- `hub` (`FEDERATION_HUB`, false; true only on stackapella.com);
- `hub_url` (`FEDERATION_HUB_URL`, `https://stackapella.com`; staging and tests point elsewhere);
- `fake` (`FEDERATION_FAKE`, false; local only, an in-process hub that approves at once, like
  `FakeEventSchedule`);
- `reach` (`['singers' => null, 'asOf' => null]`), `protocol` (1), `min_protocol`,
  `terms_version`.

Routes are registered on every install and answer `404` at run time by role (ES's lesson).

### 8.2 Data model

**On the hub:**

| Table | Columns (main ones) |
|---|---|
| `federated_instances` | `id`, `instance_uuid` (unique), `site_url`, `host`, `reported_site_url`, `name`, `contact_email` (encrypted), `public_key`, `key_rotated_at`, `app_version`, `protocol`, `locale`, `terms_version`, `status` (pending, approved, suspended), `trust` (probation, trusted, watch), `held_first` (default 5), `domain_proved_at`, `approved_by`, `approved_at`, `last_seen_at`, `flagged_at`, `welcomed_at`, `welcomed_email`, `show_in_communities`, `caps` (json), `stored_bytes`, `strikes`, timestamps |
| `federated_items` | `id`, `public_id` (12 random characters, as harmonies), `federated_instance_id`, `kind` (harmony, template), `external_id` (unique per instance), `revision`, `page_url`, `title`, `title_held_at`, `language`, `duration_seconds`, `credit_line`, `singers` (json), `parts` (json), `song` (json), `official_slug`, `attest` (json), `signed_item` (the raw signed body, for disputes), `audio_url`, `audio_type`, `audio_bytes`, `audio_sha256`, `audio_key` (our private copy), `media_audio_key`, `media_card_key`, `peaks` (json), `status` (fetching, held, listed, hidden, paused, failed), `hidden_reason`, `blocked_at`, `manifest_token`, `failure`, `published_at`, `listed_at`, timestamps |
| `federated_template_versions` | `federated_item_id`, `version`, `template` (json), `sha256`, `declaration` (json), `created_at` |
| `federated_item_days` | `federated_item_id`, `date`, `plays`; unique per item and day (ES's `federation_clicks_daily` pattern, upsert-increment) |
| `federated_item_likes` | `federated_item_id`, `user_id`, `created_at` |
| `federation_inbox` | `id`, `federated_instance_id`, `kind`, `external_id`, `payload` (json), `created_at`, `delivered_at`, `acked_at` |

Plus: `reports` and `notices` already take any model (`reportable`, `subject`); `blocks` gains the
`instance_host` kind; appeals gain a `source` (local or the instance).

**On the origin:**

| Where | What |
|---|---|
| `settings` | `federation_enabled`, `federation_instance_id`, `federation_private_key` (encrypted), `federation_public_key`, `federation_contact_email`, `federation_registered_email`, `federation_status`, `federation_last_synced_at`, `federation_last_error`, `federation_listings_url`, `federation_withdraw_pending`, `federation_show_in_communities`, `federation_stats` (json), `federation_terms_version` |
| `users` | `directory_listing` (nullable boolean), `directory_decided_at`, `directory_prompt_dismissed_at`, `federation_admin_prompt_dismissed_at` |
| `harmonies` | `directory_opt_in_at`, `federated_at`, `federated_revision`, `federated_skipped_at`, `federated_skip_reason`, `directory_hidden_at` (the hub's decision, from the inbox), `directory_plays`, `directory_likes` |
| `templates` (F4) | the same `directory_*` and `federated_*` columns |
| `federation_inbox_received` | `hub_inbox_id` (unique), `kind`, `payload`, `handled_at`: makes handling idempotent |

Every new model has a factory (CLAUDE.md).

### 8.3 Code (by the conventions: Actions, Support, Jobs, thin controllers)

**Origin side:**
- `App\Support\Federation\Keys` (generate, sign, rotate), `Listable` (the one query, 5.1),
  `Payload` (item builders), `HubClient` (signs and sends through Laravel's `Http`, classifies
  failures into the card's states; the only place install data leaves, so the opt-in check lives in
  it, as in ES's `send()`).
- Actions: `Federation\JoinDirectory`, `LeaveDirectory`, `UpdateDirectorySettings`,
  `SetDirectoryListing` (a person's yes or no), `ListHarmonyInDirectory`, `HandleInbox`
  (reports, decisions into notices, appeal results).
- Jobs: `PushToDirectory` (unique, delayed a minute), `RemoveFromDirectory` (at once).
- Command: `app:federation:sync` (hourly), with `--register`.
- Controller: `WellKnownInstanceController` for `/.well-known/stackapella-instance.json`.

**Hub side:**
- Controllers in `Http/Controllers/Federation/` (`Register`, `Items`, `RemoveItems`, `Reconcile`),
  each with a FormRequest that reads only the signed body, and the `federation.signed` middleware
  (timestamp, replay cache, Ed25519 check, instance status).
- Actions: `Federation\RegisterInstance`, `UpsertItems`, `RemoveItems`, `ReconcileManifest`,
  `ModerateDirectory` (hide, restore, remove, block), `ReviewInstance` (approve, suspend, trust,
  block host, accept address, delete), `Directory` (the listed query: listed, instance approved,
  not paused, flag on).
- Jobs on the `long` queue: `ProveInstanceDomain`, `FetchDirectoryAudio` (`PublicAddress`, size
  cap, SHA-256, ffprobe, ffmpeg remux to AAC in MP4 with metadata stripped, or transcode from MP3,
  peaks, card via `ShareCard`), `PublishDirectoryFiles` (the `HarmonyFiles` pattern: media copies
  only while listed, new random keys after any break, `PurgeCdn` on the way out).
- Command: `app:federation:maintain` (hourly).
- Emails: `DirectoryWelcome`, `DirectoryReviewed` (approval only via the atomic claim, ES).

### 8.4 The hub's API and pages (additive contract changes)

- `explore.list` items gain `source`: `null` (here) or `{ "kind": "directory", "instance": {
  "name", "host", "url" } }`; a directory item's `pageUrl` is `/d/<id>`. New query `?source=all`
  (the default once open), `here` or `directory`, and `?instance=<id>`. Featured may include
  directory items (Hillel features them as any other).
- `library.list` and `templates.show` the same way (F4), with `grant: "directory"`.
- `likes.create` and `likes.delete` accept `directoryItemId`.
- `reports.create` accepts `directoryItemId`.
- `directory.plays.create`: `POST /api/directory/<id>/plays` (`204`, no body, no session), the
  player's first play per page view; resolved through the listed query so counts can't be inflated
  for hidden items (ES's click beacon rule).
- **Admin:** `admin.directory.instances.list` (tabs: pending, approved, suspended, flagged; bulk
  approve, suspend, welcome), `.show` (its items, reports, strikes, storage, version, proof),
  `.update` (status, trust, caps), `.delete`, `.acceptAddress`, `.blockHost`;
  `admin.directory.items.held`, `.decide`, `.block`. Hub numbers on `admin.overview` and a
  **Directory** row on the network features panel (instances approved, listings live, plays).
- **Public pages** (Blade, no session, cacheable, as `/h/<id>`):
  - `/d/<id>`: `public.directory-harmony`, the `/h/<id>` view model plus `$source` (`name`,
    `host`, `url`, `pageUrl`) and `$links['origin']`; "Learn this harmony" goes to our own song
    page when the song is one of ours (`song.official`), which turns other installs' recordings
    into a way in for our singers. `<link rel="canonical">` points at the origin's page, and the
    link back is a plain followable link (the backlink is the self-hoster's reward, as in ES);
  - `public.directory-gone` (`410`) and `public.directory-resting` (`503`, the paused page);
  - `/communities`: the joined installs that chose to be shown, each with its name, host, songs
    listed and a link.

### 8.5 The origin's operations (in its own contract section)

`admin.directory.show` and `.update` (the settings card), `admin.directory.preview` (the songs that
would go, with their states), `account.update` `directoryListing`, `harmonies.update`
`inDirectory`, `directory.prompt.dismiss`, and `notices` kinds `directory_hidden` and
`directory_removed`.

---

## 9. Privacy and legal

### 9.1 What's sent, field by field (for the docs page and the settings card)

| Sent | When | Where it shows on stackapella.com |
|---|---|---|
| The song's title, language, duration, part names, colours and kinds | Every listed song | The card and page |
| The recording (AAC or MP3) | Fetched once per version | Played from our CDN; the origin's server is never contacted by listeners |
| Singers' display names; handles and profile links when their profile is public there | Every listed song | The credit line and links back |
| The song's title, credits, licence and arranger | Every listed song | The page |
| The link to the song on your install | Every listed song | The link back |
| The rights and consent answers (true or false, with when) | Every listed song | Not shown; kept with the listing as the record of what was confirmed |
| A template's notes, words and creator (F4) | Every listed template | The library |
| Your install's address, name, version, contact email and admin language | When you join, change them, or reconnect | Address and name on every card; the rest only to the reviewer, never published |
| A random install id, a public key, and an id per song | Every request | Not shown |

**Never sent:** email addresses of singers, ages (only "everyone is 18 or over"), takes, stems,
pitch, scores, practice, private or link-only songs that weren't listed, anything from under-18
accounts, IP addresses, payment data.

**On the hub:** listeners get no cookie on directory pages (as `/h/<id>`); plays are a daily total
per song with nothing about who; our copies are deleted when the listing goes (at once on a
removal; within an hour if only the sweep catches it; after 60 days of silence at the latest).

### 9.2 The directory terms (a draft for the lawyer; accepted when joining, versioned)

The operator:
1. is 18 or over and may bind the organisation running the install;
2. lists only songs that follow the directory rules: public domain everywhere, original, or
   Stackapella's own arrangements; no licensed music;
3. confirms that everyone heard on a listed song is 18 or over and agreed, and that their install's
   own terms and privacy notice tell its singers about the directory;
4. grants Stackapella a non-exclusive, worldwide, royalty-free licence to copy, store, transcode,
   stream and display listed songs and to make share cards for them, **only while they're
   listed**, ending when they're removed (with copies deleted within a stated time);
5. handles notices about content on their own install; Stackapella handles notices about its
   copies, and tells the install about reports and decisions;
6. accepts that Stackapella may refuse, hide or remove any listing, or suspend the install, with
   reasons given through the install;
7. may leave at any time, and everything listed is removed;
8. doesn't get the right to use the Stackapella name or mark (`TRADEMARK.md`), beyond the badge.

### 9.3 Documents to change when it ships

- The self-hosting terms' Data use clause and its "what crosses" table: the directory is an opt-in
  crossing that sends song data and the install's address, name and contact email (ES's review
  note: leaving it out made the page's claim false).
- The README's "What a fresh install connects to": add the directory, off until an admin joins.
- Our privacy policy: directory listings, what's kept, for how long, and where it comes from.
- The copyright policy: directory listings are covered, with the per-instance repeat-infringer
  rule.
- `/open-source` and `/selfhost`: a "Join the directory" section.

### 9.4 Children and consent

Only adults' voices are ever listed (5.1, 6.1). The origin can't prove ages to us, so the operator
warrants it (9.2 item 3), and the same code that enforces it on stackapella.com enforces it on their
install. A song from a self-hosted school install, where singers are often under 18, simply never
qualifies; the preview says so ("Only songs where everyone is 18 or over can be listed").

---

## 10. Reuse versus rewrite

| From Event Schedule | Verdict | Notes |
|---|---|---|
| One codebase, two roles (`is_nexus`, `nexus_url`) | **Reuse the idea** | `federation.hub`, `federation.hub_url` |
| Operator switch plus per-entity three-state opt-in, reset to undecided, veto | **Reuse** | Per person (their voice), not per schedule |
| Push, chunked and capped, with watermarks (`federated_at`, `federated_hash`, `federated_skipped_at`) | **Reuse** | `revision` in place of the occurrences hash |
| Full-manifest reconcile with `run_token` and `is_final`, `missing` re-queue | **Reuse** | Plus the explicit `remove` call |
| Withdraw as an empty final manifest, retried while pending | **Reuse** | |
| Per-item validation with a skipped list | **Reuse, extended** | Reasons, not only ids |
| `ownsUrl` host rule, control-character stripping, `validateSignedBody` | **Reuse** | |
| Instance states, flagged address, re-pend on host change, pending ceiling, suspension as tombstone | **Reuse** | Plus trust (probation, trusted, watch) |
| HMAC with a shared secret | **Rewrite** | Ed25519, timestamp and replay window |
| First-contact trust in `site_url` | **Rewrite** | Domain proof via the well-known file |
| Image fetch and store (`safeFetch`, magic bytes) | **Rewrite for audio** | `PublicAddress`, ffprobe, remux, our peaks and card, `HarmonyFiles` publishing |
| No back channel | **New** | The inbox: reports, decisions, appeals |
| Maintenance pruning rules and numbers | **Reuse** | Plus the 7-day pause |
| Welcome service with an atomic claim, emails by version and locale | **Reuse** | |
| Admin review screen with bulk actions | **Reuse the shape** | Built in the admin PWA (Vue), not Blade |
| Settings card with status, classified errors and a stateful preview | **Reuse the shape** | Plus a visual preview from the hub's own components |
| Adoption prompt rules and per-entity listing prompt | **Reuse** | Plus the one-click join modal and the share-sheet tick |
| Daily click table | **Reuse as plays** | If Hillel agrees (question 5) |
| The "what is shared" docs table | **Reuse** | 9.1 |
| Tests per concern (13 files) | **Reuse the split** | Plus the two-instance run (section 12) |

From Stackapella itself: `Gallery`, `Consents`, `HarmonyFiles`, `ShareCard`, `ProcessHarmony`'s
checks, `PublicAddress`, `Report`, `Notice` and appeals, `TextFilter`, flags, limits, the blocklist,
`ShareSignUps`, and the `public.harmony` view and player.

---

## 11. The order of work

**Before any of it:** the open-source launch (repos public, `IS_HOSTED`, `/selfhost`), Hillel's
answers to section 14, and the lawyer's read of 9.2. Everything is built behind `directory` (off)
and lands dark.

### F0. The contract and the decisions (backend leads; frontend and mobile agree)
- **Backend:** `contract/federation.md` (4.1 to 4.8), its schemas and examples, the well-known
  schema; the additive changes to `explore.list`, `library.list`, `likes.*`, `reports.create`; the
  origin's operations (8.5); log the decisions.
- **Frontend and mobile:** agree the additive fields by message; mobile syncs the examples.
- **Landing:** draft the `/selfhost/directory` page's words from 9.1 and 9.2 (not published).

### F1. The pipe, dark
- **Backend (origin):** config, settings, migrations, keys, the well-known file, `Listable`,
  `Payload`, `HubClient` with classified failures, join, leave (withdraw), the per-person setting,
  `app:federation:sync`, the soon and at-once jobs, `FEDERATION_FAKE`.
- **Backend (hub):** migrations and factories, the signed middleware, register with rotation and
  the domain proof, items with reasons, remove, reconcile with the sweep, the inbox (empty for
  now), limits and caps, `app:federation:maintain`, admin operations for instances, the welcome
  and reviewed emails.
- **Frontend (origin admin):** the settings card: switch, contact email, terms tick, status, last
  sync, errors, the preview list with states.
- **Frontend (hub admin):** the Directory screen: instance tabs, bulk actions, an instance's page.
- **Tests:** the two-instance run (section 12) from this phase on.

### F2. Listening on stackapella.com
- **Backend (hub):** `FetchDirectoryAudio`, the card, `PublishDirectoryFiles`, item states,
  probation holds in the trust and safety queue, reports and the 3-report rule, hide, restore,
  remove, block, strikes, block host, the pause and purge, `explore.list` `source` and filters,
  likes, plays, `/d/<id>` and its gone and resting pages (view models), the inbox's reports,
  decisions and appeal results, appeals in.
- **Backend (origin):** handling the inbox: reports into its queue, notices with appeals forwarded,
  `directory_hidden_at`.
- **Frontend (hub):** the source chip on Explore cards, the From the directory filter, the `/d/`
  page's markup (the `/h/` player with the source block), the Report button, the admin item queue.
- **Frontend (origin app):** the per-person setting in Settings, the singers' ask, the share-sheet
  tick, the "Listed on stackapella.com" chip, the two notice kinds.
- **Mobile:** read `source` on Explore items, show "from choir.example.org", open the origin's
  link in the system browser, the gone and resting states, Report on directory items. Audio plays
  from our CDN like any other song, so the player is unchanged.
- **Landing:** `/selfhost/directory` (how it works, what's shared, turning it off) and a "Join the
  directory" section on `/selfhost`.

### F3. The nudges and the stats
- **Backend (origin):** the admin banner's rule (5.1's query with undecided counted, cached), its
  dismissal, `reach`, stats from the reconcile response stored and served, sign-ups from the
  directory counted, the `app:doctor` line.
- **Backend (hub):** stats in the reconcile response (per song: plays and likes over 30 days),
  `show_in_communities`, the `/communities` view model, the network panel row and overview numbers.
- **Frontend (origin):** the admin banner, the one-click join modal with the local preview
  (reusing the hub's card and `/d/` components with local data), the dashboard tile, per-song stats
  on the singer's harmony, the badge snippet.
- **Landing:** `/communities`, the badge SVG, the self-hosting terms and README crossings
  (9.3), the launch kit's line for r/selfhosted.
- **Mobile:** nothing new (optionally a "From the directory" filter on Explore).

### F4. Templates in the library
- **Backend (both sides):** the template item, schema validation and stripping, `official_song`
  detection, `federated_template_versions`, `library.list` `source`, `templates.show`
  `grant: "directory"`, projects keeping their copy, `uses` in the stats.
- **Frontend:** the source chip in the library, Learn this from a directory template, the
  origin's "Also list it on stackapella.com" in the template's publish flow with the declaration.
- **Mobile:** the library's source chip, opening a directory template.
- **Landing:** the templates row on `/selfhost/directory`.

### F5. Later, each its own decision
- Ideas listed as links out ("Sing a part on choir.example.org").
- Following a community on stackapella.com.
- Directory songs as guide voices, if Hillel wants it and with a consent written for it.
- Telling an origin when we feature one of its songs (a `featured` inbox entry), so it can
  celebrate.

### When it opens
Hillel turns on `directory` (with its reason) once F2 is built and the hub's gallery is open, and
announces it to self-hosters in the release notes. F3 and F4 open as they land.

---

## 12. Tests

**PHPUnit (both roles in one app, MySQL as always):**
- The hub and the origin share one test database: their tables don't overlap, and a test sets
  `federation.hub` per call. `Http::fake()` routes `https://hub.test/api/federation/*` into the
  application's own kernel (so the origin's real `HubClient` talks to the real hub controllers),
  and `https://origin.test/*` to the well-known file and an AAC fixture (`web/tool`'s generated
  audio). That's two instances in one process, against the real code on both sides.
- Signing: a wrong key, an old timestamp, a replayed signature, `?isFinal=1` on the URL, and an
  unsigned re-registration are all refused.
- Domain proof passes and fails; a host change re-pends and flags.
- Listable: each rule in 5.1 alone keeps a harmony out (an under-18 friend, a friend at not
  decided, a declined consent, a licence outside the list, a held page).
- Items: each skip reason; an off-host link nulled or refused; the caps.
- Reconcile: multi-chunk manifests sweep only on the final chunk; tombstones survive; `missing`
  re-queues.
- Remove and withdraw: files unpublished and the CDN purged; a failed withdraw retried while the
  switch is off.
- Audio: a mismatched hash, a non-audio file, too long, too big, a private address, a redirect to
  a private address.
- Probation, trust and watch; 3 reports hide; block survives a re-push; suspension survives a
  re-register; block host stops a new id.
- Pause at 7 days and purge at 60; a resting page answers `503`, a gone one `410`.
- The inbox: a hub report reaches the origin's queue once (idempotent), a decision raises the
  right notice, an appeal travels back and its result returns.
- Plays: counted once per page view, not for hidden items, nothing stored about the listener.
- The opt-in guarantee: with the switch off, no request leaves (`Http::assertNothingSent()`), no
  key or id is minted, and the well-known file answers `404`.
- Protocol: the previous version's golden examples still work; an old protocol gets `426` with
  `minProtocol`.
- Contract: every `federation.*` example validates against its schema (the existing check).

**The two-instance CI job** (a GitHub Actions workflow, nightly and on changes to federation
code): two databases (`stackapella_hub`, `stackapella_origin`), two `php artisan serve` processes
on different ports (the hub with `FEDERATION_HUB=true`; the origin with `FEDERATION_HUB_URL` at the
hub and local http allowed, as `PublicAddress` allows locally), queues run synchronously. A script
(`server/tool/federation-e2e.php`) seeds an adult singer and a harmony with a real AAC on the
origin, joins, approves on the hub by artisan, syncs, checks the song is listed and plays from the
hub's storage, reports it on the hub, syncs, checks the origin's queue, deletes it on the origin,
and checks the hub answers `410`.

**Web tests (headless Chrome, the mock API):** the settings card's states and errors, the join
modal and preview, the admin banner's rules, the singers' ask, the share-sheet tick, Explore's chip
and filter, the `/d/` page with its source block, gone and resting pages, the notice kinds.

**By Hillel, once on staging:** join from a second install, approve, hear a song, report it,
appeal it from the origin, leave, and see everything gone.

---

## 13. Risks

| Risk | Mitigation |
|---|---|
| **Licensed music** from installs where our rule isn't enforced by us | The licence check on both sides, held first songs, the title word list, DMCA hides at once, strikes and suspension, the terms (9.2) |
| **Children's voices** listed by a careless or dishonest operator | Only adults' voices qualify in the code they run; the operator warrants it; under-18 data never leaves; reports with reason `personal` hide fast |
| **Consent stretched** beyond what friends agreed to | Each person's own directory yes, plus `Consents::allAgreed()`; anyone's no vetoes |
| **Fake or hostile instances** (spam, impersonation, backlink farms) | Domain proof, human approval, pending ceiling, host-only links, reserved names, block host |
| **Bait and switch** (a clean file reviewed, a bad one served later) | We serve only bytes whose SHA-256 was signed; a new file is re-fetched and, on probation or watch, reviewed again |
| **Removals not reaching us** (an install dies) | The 7-day pause and 60-day purge |
| **The hub as a target** (a database leak used to forge listings) | Public keys only on the hub |
| **Moderation load on Hillel** | Probation per instance, bulk actions, trusted instances, the 3-report rule, `pause_directory` |
| **Cost** (storage and bandwidth) | Quotas per instance, 10-minute cap, one fetch per version, the CDN |
| **Protocol drift** between releases | A protocol number, a 6-month overlap, golden fixtures, the two-instance job |
| **Breaking the self-hosting promise** ("no outbound request on its own") | Off by default, nudges rendered locally with a baked-in number, the opt-in check in `HubClient`, a test that nothing is sent |
| **Nagging** | One banner per admin, dismissed for good; one ask per singer; nothing for under-18s or people who turned suggestions off; no email beyond approval and suspension |
| **Legal role** (we host copies, so DSA and copyright duties apply) | The lawyer reviews 9.2; our notice and appeal flows reach the singer through the inbox |
| **The name** (instances presenting as Stackapella) | Reserved words in instance names, `TRADEMARK.md`, "from <host>" on every card |
| **Opening before the hub has an audience** | It opens after N1; the reach number appears only when it's real |

---

## 14. Questions for Hillel

Each answer goes into `docs/decisions.md` when it's made.

| # | Question | Recommendation |
|---|---|---|
| 1 | Play songs from the self-hoster's server (as you described), or keep a verified copy on our CDN that lives only while the song is listed? | **A copy on our CDN** (section 3): listeners' privacy, our CSP and apps, reliability, takedowns, and no bandwidth bill for small choirs. The origin stays the source of truth |
| 2 | What goes in the directory first? | **Harmonies, then templates** (F4); ideas later as links out |
| 3 | Is the operator's switch enough, or does each singer choose too? | **Each singer chooses** (not decided, yes or no), as ES does per schedule; it's their voice |
| 4 | May a directory song's parts become guide voices here, as public songs on stackapella.com can? | **No.** The singer agreed to their own install's terms; revisit with a consent written for it |
| 5 | Count plays of directory songs (a daily total per song, nothing about the listener)? We don't count plays of our own songs today | **Yes,** as ES counts clicks: it's the number self-hosters most want, and it stays a count in our own records. Show our own singers the same count later, for fairness |
| 6 | Likes and comments on directory songs? | **Likes yes, comments no:** the owner can't see or answer comments |
| 7 | How much review? | **Approve each install once, hold its first 5 songs,** then automatic, with trusted and watch settings per install |
| 8 | Sign requests with Ed25519 keys, rather than ES's shared secret? | **Yes:** no secret on the hub, no new dependency (PHP's sodium) |
| 9 | Tag self-hosted releases with version numbers from the open-source launch (v1.0.0), so the directory can name a minimum version? | **Yes,** as ES does; the directory needs the first release that ships it |
| 10 | When should it open? Should self-hosted opt-ins count toward the gallery's N1 threshold? | **After N1 opens; no:** N1's numbers stay stackapella.com's own |
| 11 | What do we call it? | **"The stackapella.com directory"** (your word); "the network" already means N1 to N7 |
| 12 | A public `/communities` page listing installs that choose to be shown? | **Yes,** opt-in per install: social proof for joining, and a way in for singers near a choir |
| 13 | Per-install quotas | **2,000 songs, 500 templates, 5 GB,** raisable per install in admin |

---

[Back: Open-source release](open-source-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs)
