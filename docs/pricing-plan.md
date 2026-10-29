# Pricing and billing plan

**Status:** agreed in outline (2026-10-25), and **revised 2026-10-26** after a review against the
product as it now stands (a beginner-first course, real voices, everything that doesn't need a
network at launch). Nothing here is
built yet, except the free plan's 3-project limit, which the browser enforces for now.
- **Decided:**
  - the tax approach (Stripe with Cashier and Stripe Tax);
  - the free songs;
  - the launch price;
  - course work never counts toward the limit.
- **A few numbers are still open** (see [Open decisions](#open-decisions)).
- **When it's built** ([roadmap.md](roadmap.md)): limits in M4, billing in M5, the plan pages in
  L (launch readiness).

**Context:** Stackapella is free with a paid plan, Stackapella Plus. This file is what to follow
when billing is built. It needs accounts and cloud save first, and it reuses Event Schedule's
billing patterns (ES below).

## The story, in two lines

- **Free: learn to sing harmony.** The whole beginner course, four songs (plus a song of the month),
  recording, Make it sound good, share links and videos.
- **Plus: the whole library, and everything you make.**
  - every song, with new ones each month;
  - the full course and unlimited workouts;
  - your history;
  - unlimited projects and more storage;
  - MP3 and stems.

The pricing page, the plan gates and `docs/features.md` all tell this story in these words.

## What changed in the review (2026-10-26)

| Before | After | Why |
|---|---|---|
| Every official song free | **Four songs free, the rest Plus,** with a free song of the month | Hillel's call. A growing library is what people pay for in learning apps (Simply Piano's main value); "everything free" left beginners little reason to pay |
| Every song started counted toward the 3 | **Course work never counts** | The free course alone used 2 or 3 songs, so beginners hit the limit just as they finished it |
| Prices open | **$4.99 a month or $39.99 a year** at launch, yearly shown first, with a 7-day trial | Hillel's call: growth at launch matters more than revenue per person. The price rises later, for new subscribers only |
| No rule about licensed music | **Public-domain songs and our own originals only** (`docs/templates-plan.md`) | Hillel's call; it keeps the library free of rights questions |
| Plus was a list of unrelated extras | One story: free to learn, Plus for the whole library and everything you make | Easier to explain, easier to want |
| No rule for where upgrades are offered | **Upgrade moments** (below), never a lock wall | Offer Plus when it's wanted, never in the middle of singing |
| Plus songs' files were open to anyone | Plus songs come through the API to Plus accounts only | The repo's bundled templates would otherwise give the library away |

## The plans

Rows marked *later* need a network, and open when the admin's network features panel says so
([roadmap.md](roadmap.md)). Everything else is in the launch.

| | Free | Plus |
|---|---|---|
| **Songs and learning** | | |
| Official songs | **4:** Amazing Grace, Are You Sleeping, Michael Row the Boat Ashore, Happy Birthday | **All of them,** with new songs added each month |
| Song of the month | One Plus song, free to everyone for that month | Included |
| Preview any song (hear it, see its parts) | Yes, every song | Yes |
| The course: map, lessons, stars, XP, levels, badges, the weekly goal | Units 1 to 7 ("Your first harmony" to "The whole song is you") | All units and paths |
| Today's workout | One a day | Unlimited, plus the full exercise library |
| The report on your latest take | Yes | Yes, plus history, trends and detailed insights |
| Chords underneath, real voices or synth, key changes | Yes | Yes |
| **Making and keeping** | | |
| Saved projects (that you own) | 3 (course work doesn't count) | Unlimited |
| Storage for recordings | An allowance (proposed 1 GB, about 110 three-minute parts) | Proposed 50 GB |
| Export the mix as WAV, with Make it sound good | Yes | Yes |
| Export the mix as MP3 | No | Yes |
| Export each part on its own (stems) | Only via "Download and delete" | Yes |
| **Sharing** | | |
| Share links and share videos | Yes | Yes |
| *Later:* the public gallery, likes, comments, follows, leagues | Yes | Yes |
| *Later:* the open parts shelf, the virtual choir, song requests | Yes | Yes |
| **Making templates** (at launch; the public library *later*) | | |
| Create and publish a public template | Yes | Yes |
| Private or unlisted templates | 1 | Unlimited |
| Your own recorded guides | No | Yes |
| Choir groups: share templates privately and assign parts | No | Yes (the choir plan is open, below) |
| *Later:* learn from the community's public templates | Yes | Yes |
| **Coaching** (at launch; the directory *later*) | | |
| Buy take reviews (and later lessons and packages) | Yes, at the coach's price | Yes, at the coach's price |
| Send to my teacher | No | Yes |
| The harmony gym | One game a day | All of them |
| **Trial** | | 7 days, once per account |

**Selling as a coach** (at launch): coaches get Plus free while active (proposed), and Stackapella keeps
a commission (proposed 15%; `docs/coaching-marketplace-plan.md`).

## Songs: four free, the library on Plus

- **The free four:**
  - Amazing Grace, Are You Sleeping and Michael Row the Boat Ashore: the songs the free course
    (units 5 to 7) teaches with;
  - **Happy Birthday,** the song people most want to sing for someone and share (public domain
    since 2016; it's added to the library for launch).
- **Everything else is Plus,** including songs the course's later units use.
- **Song of the month:**
  - one Plus song is free to everyone for a calendar month, shown on the course map and in the
    library;
  - **projects started from it during the month stay fully usable afterwards,** so you can finish
    your song;
  - the choice rotates through the library, and Christmas songs come in December.
- **Offline:** a Plus song stays usable offline for 14 days after the last subscription check, then
  becomes read-only until the app is online again (`docs/operations-plan.md`).
- **Every song can be previewed by anyone:** hear it, see its parts and ranges. Starting it is what
  needs Plus. This is the "sample, not a lock wall" rule.
  - **How, without giving a Plus song away:** each song has a **20 to 30 second preview clip**, a
    finished mix (AAC, on the public `media` CDN).
  - The catalogue and the song pages carry the clip's URL and each part's name and range. They
    **never carry the notes, the words in full, or a single part on its own.**
  - The 4 free songs still preview in full in the app, as today.
- **New songs every month on Plus** is the promise that keeps Plus worth paying for. It needs a
  steady pipeline:
  - Claude drafts;
  - the musician checks;
  - beta singers record the real voices.

  Its pace is set in the roadmap.
- **Only public-domain songs and our own originals.** No licensed music. The rule and the song
  list are in `docs/templates-plan.md`.
- **Downgrading:** projects made from Plus songs become read-only, as all projects over the limit
  do. They can be played and exported as WAV, and nothing is deleted.

**How Plus songs are kept for Plus** (enforced on the server, unlike MP3):
- the four free songs are bundled with the app, as today, so they work offline and signed out;
- **Plus songs aren't in the app's files:**
  - their template JSON comes from `GET /api/templates/{id}`, only to Plus accounts (or anyone,
    for the song of the month);
  - their recorded guides are at unguessable immutable keys, given out by the same call;
- once downloaded, a Plus song is cached for offline use while the account is on Plus;
- the catalogue lists every song for anyone:
  - title, parts with their names and ranges, and `previewUrl`;
  - `access: free | plus`, and `freeUntil` for the song of the month;
  - no note arrays and no part audio.

## Course work never counts

- **What counts toward the 3:** projects you keep in your library, whether started from a song or
  blank.
- **What never counts:**
  - lessons, practice and today's workout;
  - **the recordings the course asks for** (units 5 to 7): they're stored with the course
    (`source: course`).
- Course recordings still use storage, which is small.
- **Practising never needs a free slot.** Only keeping a new song in your library does.

## Prices

- **At launch: $4.99 a month, or $39.99 a year.** Yearly is shown first, as "$3.33 a month,
  billed yearly". It's a launch price, set low for growth (Hillel, 2026-10-26).
- **Currencies:** USD, EUR and GBP at launch. Prices include tax where the law expects it (EU and
  UK), through Stripe Tax.
- **A 7-day trial,** once per account, offered at the upgrade moments below. Whether a card is
  needed up front is open (#3). The trial-ending email goes out before any charge (the existing
  rule).
- **Raising the price later:** as the library grows, the price rises for new subscribers. **Everyone who subscribed at the launch price
  keeps it while subscribed,** which is also the founder offer, and a reason to join early. The
  pricing page says so ("Launch price: keep it as long as you stay").
- **For comparison:** Simply Piano is about $119.99 a year, with a 7-day trial (its App Store
  listing, August 2026). Learning-app subscriptions generally run about $119 to $170 a year.

## At checkout (the consumer rules)

- **Before paying,** Checkout shows:
  - the price with tax;
  - that it renews, and when;
  - how to cancel (online, any time);
  - for the trial, the date it turns into a charge.
- **In the EU and UK,** Checkout asks for consent to start Plus at once, which affects the 14-day
  right of withdrawal, and the refund policy says how it works.
- **The trial-ending email** goes out before the first charge.
- The lawyer confirms the wording ([trust-and-safety-plan.md](trust-and-safety-plan.md), "Legal
  duties").

## Where upgrades are offered (the upgrade moments)

Plus is offered when someone wants something it gives, never while they're singing or recording,
and never as a wall in front of the free course:

| Moment | What they see |
|---|---|
| Starting a Plus song (after Preview) | "This song is on Plus", with the trial |
| Finishing unit 7 ("The whole song is you") | A celebration first; then "Keep going: the whole library and the next units on Plus" |
| The second workout in a day | "One workout a day is free. Unlimited on Plus." |
| A 4th project | The existing limit message, with Download and delete as the free way out |
| MP3 export, stems | "Plus", beside the free WAV |
| The storage meter near full | The meter, with the Plus allowance |

There are no pop-ups on opening the app, and no countdowns. The plan-gate component shows a sample
(a song's preview, a history chart with sample data) rather than a lock.

## Rules

### Who a feature belongs to

- **Account features follow the person using them.** Plus songs, MP3 and stems belong to the user
  who is singing or exporting. A Plus singer can export MP3 from a free user's project they've
  joined.
- **Project capacity follows the project's owner.** These all count against the owner:
  - whether a project counts toward a limit;
  - the storage its recordings use;
  - how many people can be invited.
- **Every check goes through one place.** That's a single `Plans` support class: the plan for a
  user, and the capacity for a project. A team or choir plan can then move billing from a person to
  a group without touching every gate. ES bills the schedule rather than a person for this reason
  (`app/Traits/RoleBillable.php`).

### Limits

- **Real limits are enforced on the server:**
  - project count, storage and invites;
  - and access to Plus songs.

  The numbers live in `config/usage.php`, can be overridden by env, and use per-plan keys
  (`project_limit_free`), as ES does. Each limit is a method returning `?int` (null means
  unlimited), with a `canX(): bool` beside it.
- **A refusal is a 403 with a stable `code`** (`project_limit`, `storage_limit`, `invite_limit`,
  `plus_song`), so the app shows the right message. This follows ES's
  `app/Exceptions/EventCreationLimitException.php`.
- **MP3 export is checked in the browser only,** because the mix is rendered and encoded on the
  device. **Accepted:** tinkerers rarely become customers, and every limit that costs money is
  enforced on the server. The MP3 encoder (for example lamejs from the CDN, pinned with SRI) needs
  Hillel's OK as a dependency.

### Downgrades, failed payments and cancelling

- **Nothing is ever deleted by a downgrade.**
  - Projects over the free limit, and projects made from Plus songs, become **read-only**: they can
    be played and exported as WAV, but no parts can be added or changed until the account
    upgrades (or, for the limit, is back under it).
  - Which projects stay editable is an open decision (#6).
- **A failed payment keeps Plus working during Stripe's retries** (ES keeps access while
  `pastDue()`, `RoleBillable`). After the last retry fails, the account drops to free under the
  rule above.
- **Cancelling** is done online through the billing portal (ES: `redirectToBillingPortal` in
  `app/Http/Controllers/SubscriptionController.php`). Plus lasts until the end of the period
  already paid for.
- **Refunds** follow a written refund policy (one of the legal pages).

### Freeing a slot without losing work

**Download and delete** zips the mix and every part as WAV, then deletes the project. It's free,
because without it a free user who deletes a project to make room loses their work. It's the
one way a free user gets stems.

### Projects made before accounts, or offline

- **Projects made before accounts:** projects live in the browser (IndexedDB). On first sign-in
  they're uploaded into the account. If there are more than 3 (not counting course work), the
  extras stay on the device and are marked "not saved to your account" until there's room or the
  account upgrades.
- **Projects made offline:** the installed app can make a project with no connection. If the
  account turns out to be at its limit when it syncs, the project stays on the device (as above).
  The server never refuses silently.

### Templates people make (at launch, without the public library; decided 2026-10-26)

- **Creating templates is free; private and group use is Plus.** Free creators grow the public
  library, so creating and publishing a public template stays free.
- **The people who make the most templates are likely choir directors and teachers.** What they
  need is private: templates shared with their own singers, guides in their own voice, and parts
  assigned to people. That's where Plus is.
- **Learning from the community's public templates is free.** The official library is what Plus
  sells.
- **A free account holds 1 private or unlisted template.** Making it public frees the slot.
- **Recorded guides use real storage,** so people's own are Plus. The official guides, sung by
  beta singers, come with the songs.
- **Downgrading** turns private templates over the limit read-only. They stay usable by the
  projects already made from them, and are never deleted.
- **Moderation:** the licence confirmation, reporting, and the review hold on a new creator's first
  public templates are built before public publishing opens.
- **Limits:** `private_templates_free` (1), with the stable code `template_limit`.

### A choir plan (open, needed for M7)

Choir directors are the group most likely to pay, so the shape is sketched now:
- **one subscription for the director;**
- **member seats:** members learn the songs their director assigns (Plus songs included) while
  they're in the choir;
- the director's view of who has learned which part.

The price, the seat count, and the rules for school choirs with members under 18 are decided
before it's built (roadmap M7, choir groups). The `Plans` class's ownership rule already allows a group to pay.

### Emails

Each email goes out once, about something the person did or owns, which fits the
no-bulk-email rule:
- **Receipts:** Stripe sends them.
- **Payment failed** and **trial ending soon** go through Cloudflare Email Service. Some places require the
  reminder before a trial turns into a charge.

### Data and privacy

- **Deleting an account** deletes its recordings: a queued job removes the files from storage.
- **Inactive free accounts:** the retention policy is open (#8).
- **Counting without analytics:**
  - sign-ups, trials, upgrades, cancellations and failed payments;
  - **which upgrade moment led to each trial;**
  - finished harmonies and second songs.

  These are counted on the server from our own records and Stripe's. There's nothing in the
  browser.

### Keeping claims honest

- `docs/features.md` is the single list of what each plan gets, as ES's `docs/FEATURES.md` is.
- A test fails when the pricing page or an in-app label claims something the code doesn't enforce,
  as ES's `tests/Feature/MarketingTicketingTierTest.php` does.

## Open decisions

| # | Decision | Options | Recommendation |
|---|---|---|---|
| 1 | Sales tax and VAT | **Decided 2026-10-26:** Stripe with Cashier and Stripe Tax | An accountant advises on registrations before the first charge ([backend-plan.md](backend-plan.md), D8) |
| 2 | Prices and currencies | **Decided 2026-10-26:** a launch price of $4.99 a month and $39.99 a year; USD, EUR, GBP | Raise for new subscribers once the library has grown and growth is steady |
| 3 | The trial's card rule | Card up front, or not | Card up front on yearly (as Simply Piano), with the reminder email. A no-card monthly trial only for trust level 2 (Singer). **Either way, one trial per card,** checked after Checkout (`docs/trust-and-safety-plan.md`) |
| 4 | Storage allowances | Free and Plus sizes | Proposed 1 GB and 50 GB; confirm from real FLAC sizes in M4 |
| 5 | Free invites per project (later) | 1, 2, 3 | 2 |
| 6 | Which projects stay editable after a downgrade | The 3 most recently edited, or the person picks | The person picks, with "most recently edited" as the default |
| 7 | Founder price for early subscribers | **Covered by the launch price:** early subscribers keep $4.99 and $39.99 while subscribed | Nothing extra needed |
| 8 | Inactive free accounts | Keep forever, or delete recordings after a long notice (with an email first) | Decide before launch |
| 9 | Promo codes | | Allow (Stripe supports them), for launch and choirs |
| 10 | How fast the library grows | Songs a month | Proposed 2 a month, with real voices where beta singers are ready (the synth otherwise) |
| 11 | The choir plan | Price, seats, under-18 rules | Before roadmap M7 |

## Order of work

**M4** (`docs/backend-plan.md` C6):
- the `Plans` class, `config/usage.php` and the limit checks in the Actions;
- **course projects excluded** from the count;
- the storage meter;
- read-only after a downgrade;
- `docs/features.md` and the claims test.

**M5** (`docs/backend-plan.md` D8):
- Cashier, Stripe Tax, the prices, the trial, Checkout and the portal;
- the grace period; the emails;
- **Plus songs through the API,** with the catalogue's `access` and the song of the month;
- the plan gate and the upgrade moments in the app;
- testing Checkout inside the installed iPhone app (it leaves the app and must come back).

**L:**
- the pricing page and landing page;
- the legal pages, before the first charge: terms, privacy, refunds;
- the launch library in place (`docs/roadmap.md`).

**Later:**
- founder pricing and promo codes;
- the choir plan;
- if a native app ever ships, App Store billing rules apply to it (a PWA isn't affected).

## How we'll know it works

- **Feature tests (PHPUnit, MySQL):**
  - each limit refuses at the line with its `code`, and allows below it;
  - **course projects don't count;**
  - **a Plus song's template is refused to a free account (`plus_song`) and served to Plus, and
    to everyone during its month;**
  - a project started in the song of the month stays editable after the month;
  - a downgraded account's extra projects and Plus-song projects are read-only, and nothing is
    deleted;
  - a Plus collaborator can export MP3 from a free project, but can't push it past its storage;
  - `pastDue` keeps Plus; the final failure drops to free;
  - webhooks (Stripe's test events) move the plan correctly.
- **The claims test** passes against `docs/features.md`.
- **Browser tests** (headless Chrome, `web/tool/test.sh`):
  - the plan gates show at each upgrade moment;
  - Preview works on every song signed out;
  - MP3 is offered only on Plus;
  - browser projects move into a new account.
- **By hand,** with Stripe in test mode:
  - upgrade from a Plus song's page and from the end of unit 7;
  - a failed card;
  - cancel, and resubscribe;
  - on desktop and in the installed iPhone app.

## References

| Pattern | Where |
|---|---|
| Billing on the account that owns the work, plus the past-due grace period | ES `app/Traits/RoleBillable.php` |
| Billing portal | ES `app/Http/Controllers/SubscriptionController.php` |
| Limits as numbers in config | ES `config/usage.php` |
| A limit error with a stable code | ES `app/Exceptions/EventCreationLimitException.php` |
| The plan-gate component | ES `resources/views/components/plan-gate.blade.php` |
| The single list of what each plan gets | ES `docs/FEATURES.md` |
| A test that marketing matches the code | ES `tests/Feature/MarketingTicketingTierTest.php` |
| The free limit in the app today | `web/js/stores/projects.js` (`FREE_PROJECT_LIMIT`) |
| Simply Piano's price, for comparison | Its App Store listing, as reported by [appshunter.io](https://appshunter.io/ios/app/1019442026) and [ThePricer](https://www.thepricer.org/how-much-does-simply-piano-cost/) (2026) |

---

[Back: Becoming a better singer](vocal-coaching-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Go to market](go-to-market-plan.md)
