# Onboarding review: from "Start singing" to day 7

Status: **review and proposal, nothing built yet.** Written 2026-10-29 for Hillel's ask: "have a
UX/usability/engagement expert review our flow when you click 'Start singing it's free' ... ask
them to install the PWA, set their name, create an account and share something ... moments of
wonder ... anonymously track progress so we can A/B test". Everything marked **(new)** is a
proposal. Everything else is described from the code as it stands on `main` (7cd986d9).

Sources read: the landing (`server/resources/views/pages/landing.blade.php`,
`server/app/Support/Pages/Landing.php`, `server/lang/en/pages.php`), the app shell and router
(`web/js/main.js`, `web/js/router.js`), the lesson (`web/js/views/learn.js`,
`web/js/components/listening.js`, `mic-check.js`, `results.js`, `share-result.js`), the course
(`web/course/course.json`, `web/js/course.js`, `web/js/stores/learning.js`), the home
(`web/js/views/projects.js`, `web/js/components/home.js`), the asks (`install.js`, `birth.js`,
`views/auth.js`, `views/reminders.js`, `components/share.js`, `components/boards.js`), the counts
(`web/js/usage.js`, `server/config/counts.php`, `server/app/Actions/Counts/RecordCounts.php`,
`contract/api.md` `counts.create`, `docs/decisions.md` 2026-10-28 "anonymous usage counts" and
2026-10-29 "Anonymous totals"), `docs/skins-plan.md`, and the screenshots in
`~/Code/shots-review/atlas/`, `home-design-3/`, `lesson-step/` and `warm-up/`. I did not drive the
app live for this review; the screenshots and the code were enough to map the flow.

---

## 1. The flow today, step by step

| # | What happens | Where it lives |
|---|---|---|
| 1 | On the landing (public site host), the hero's **Start singing, it's free** goes to `app.stackapella.com/learn/1-comfortable` (with `?ref=` when the visitor came with one). "Hear four voices" beside it plays the hero's four-part line in the page. | `Landing::links()` |
| 2 | The app loads on another host. It sets the `sa_app` cookie, counts `app_open`, listens for `beforeinstallprompt` (kept, not shown), and opens lesson 1 directly. Before launch (`comingSoon`), a guest is sent to `/signin` instead. | `main.js` |
| 3 | **Lesson 1, step 1 of 2 (Learn):** "Every voice has an easy note. There's no wrong one." Three cards (Hum a note, Find where it's easy, Sing it on "oo") and the ladder picture. Back and Continue. Nothing sounds. | `course.json` `1-comfortable` |
| 4 | **Continue opens step 2 (Your note)** and the mic starts at once (the Continue tap is the gesture). The browser's own mic permission prompt appears with no word from us first. Denied: "We need your mic to hear you", Try the mic again, and the per-browser help sheet (very thorough, including in-app browsers). | `NoteFinder`, `mic-help.js` |
| 5 | First time on this mic: an inline **mic check** inside the card: "First, a quick mic check", "Say something or hum", then "Got you, your mic works" with a sound, and after 1.4 s it goes on. | `MicCheck inline` |
| 6 | **Find your note:** "I'm listening. Sing any easy note, like a long 'ahh'", then "I hear you. Now hold that note...", "That's it, keep holding" with the pitch bar and a hold ring. Found: the note big ("A3"), Play it (a synthesized note), Sing again. Gentle nudges after 6 s of nothing. Skip for now gives a typical note and one star. | `NoteFinder` |
| 7 | **Finish** (the step's own button) shows the lesson end: stars pop with a chime and the celebration burst, "+XP", "Your comfortable note is A3" huge, a keyboard with the easy range, "Notes around A3 suit tenor and alto parts. Every lesson will now start here." Buttons: Play again for more stars, Next lesson. Badge toasts ("New badges: First note...") arrive. "How did you hear about us?" is asked here, but only of a signed-in person, so never of a new guest. | `NoteResult`, `celebrate.js`, `badges.js` |
| 8 | **Next lesson** goes straight to `1-match` (Learn, Try with "Wait for me", Sing it scored). The guide voice is heard for the first time here. A take with stars shows TakeResult (pitch chart, Listen back) and a **Share** button for the result card (made on the device, "no name or photo on it", a link that lands a friend on the same lesson). | `learn.js`, `ShareResult` |
| 9 | Lesson end screens chain on with **Next lesson** through unit 1 (5 lessons: comfortable note, match, higher and lower, breathing, sing back five notes). Each end has stars, XP, the unit path, the level bar and an Up next card. | `LessonEnd` |
| 10 | The **home** is only seen if the person presses Back (or comes back later). There: the course card (Continue lesson), the Plus courses by group, the **install card** (plain offer, Chrome one tap, iPhone "tap Share, then Add to Home Screen", Mac Safari "File, then Add to Dock"; one "Not now" ends the offer for good), then Your songs with "0 of 3 free songs used" and **Get Plus**, then the library. **Sign in** is a quiet link in the header. | `projects.js`, `install.js` |
| 11 | The **weekly goal** is 3 days by default and is never asked. It can be changed on the course page (`/learn`), where "Remind me on my practice days" shows only to someone signed in. | `course.js` `goal: 3`, `CourseStats` |
| 12 | **Reminders** (`/settings/reminders`) need an account, and on an iPhone the installed app. One push on practice days at a chosen time. No email is ever sent for this (CLAUDE.md). | `reminders.js` |
| 13 | **Account:** every sign-in link goes to `/signin`, titled "Sign in to back up your songs", sign-in first, with "New to Stackapella? Create an account" small at the bottom. Sign-up asks name, email, password, month and year of birth, and a Turnstile check, then a verification email. Google or Apple ask "When were you born?" afterwards in a modal that can't be dismissed. Guest progress merges into the account. Terms are accepted by the line on every sign-up screen. | `auth.js`, `birth.js` |
| 14 | **Name:** only asked at sign-up ("How you appear in Stackapella"). The result card has no name. | `auth.js`, `share-result.js` |
| 15 | **Sharing a harmony** needs an account ("Sign in to share, so the link stays yours"). After a take in Sing: "Your best", "#2 among friends", Challenge a friend. Leaderboards say "Make an account to compete" to guests. | `share.js`, `boards.js` |
| 16 | **The course's harmony comes late.** Units 1 to 3 are single-voice exercises (unit 2 adds a drone). Unit 4 (lesson 14 or so) is the first time you sing inside a song with other voices (Amazing Grace's tune with three guide parts). Unit 5's challenge (lesson 21 or so) is the first time you stack your own voices. The first `record` step is unit 4's challenge. | `course.json` |

---

## 2. Friction points, ranked by likely drop

1. **No harmony in the first session.** The landing promises "Harmony, made by you" and shows
   "Alto · you" in a four-voice stack. The first ten minutes give one held note, then single
   notes against a guide. The first time a newcomer hears themselves inside a chord is around
   lesson 21 (unit 5's challenge). Most people who will ever leave, leave before that.
2. **The mic moment is unannounced and doubled.** Continue on a reading screen fires the browser's
   permission prompt with no word from us about why or what we do with the sound. Then the person
   is asked to make a sound twice in a row: "Say something or hum" for the mic check, then "Sing
   any easy note" for the note. For a shy beginner this is the scariest ten seconds of the product.
3. **In-app browsers find out late.** Visitors from Instagram or TikTok (the `heardFrom` options
   list both) land in a browser that often can't use the mic. The help sheet explains "Open in
   browser" well, but only after the mic has failed, on step 2 of lesson 1.
4. **No ask lands at a win; the asks that exist land before value or never.** A new singer can go
   through all of unit 1 by Next lesson without ever seeing the home, so they see no install card,
   no sign-in, no weekly goal and no reminder offer. Then they close the tab, and nothing brings
   them back. The home, when seen on a first visit, shows the install card and "0 of 3 free songs
   used · Get Plus" before the person has sung anything.
5. **Guest progress on iPhone Safari is fragile.** Safari can clear a site's storage after seven
   days without a visit (the install card's own words). A guest who sings on Monday and comes back
   the next Tuesday may find their note, stars and level gone. Install or an account is what keeps
   them, and neither is offered in the flow.
6. **The weekly goal is decided for them.** A silent default of 3 days is a goal nobody chose. A
   goal you pick is a commitment; a goal you're given is a judgement. The reminder that would make
   it work needs an account and, on iPhone, the installed app, and is offered only on the course
   page to signed-in people.
7. **Sign-in framing, not sign-up.** Every guest-facing "Sign in" link opens a page titled "Sign in
   to back up your songs" with account creation in small print at the bottom. A new person who
   wants an account reads that it isn't for them yet. Email sign-up then waits on a verification
   email.
8. **Sharing exists but is hidden at the wrong moment.** The result card's Share sits on the take
   result inside a step, not on the lesson end screen where the celebration is. Lesson 1 (the
   note) has no share at all, though "I'm an alto" is the most shareable fact of the first session.
   The card can't carry a name, so it says nothing about who sang.
9. **Smaller ones.** The badge toast covers the lesson header (back button and title) during the
   next lesson's sung step (`lesson-step/sing-it-phone.png`). The found note shows twice (in the
   card, then on the end screen). The first screen after a click that said "Start singing" is a
   reading screen. The Plus course groups sit right under the course card for someone who hasn't
   finished a lesson. "How did you hear about us" is never asked of guests, and asked of new
   accounts only at lesson 1's end, which they have usually passed by the time they sign up.

---

## 3. The proposed onboarding, click to day 7

### Principles

- **Value before every ask.** No ask before the first note is sung, never during singing, never on
  a screen that already has a Plus moment, and at most one ask per screen and one per session.
- **Each ask rides a win and names what it protects** ("your note, your 9 stars, your level"), and
  each is a card in the flow or a styled sheet, never a browser dialog.
- **"Not now" is a real answer.** It's remembered on the device and moves the ask to its next
  milestone, at least 3 days later; after three Not nows the ask stops offering itself and lives
  only in Settings. Nothing is pre-ticked, nothing is shamed, and guests can always carry on.
- **One flow, two skins.** The same steps and asks in both. Game mode adds the arcade rewards;
  serious mode says the measure plainly. The wonder moments are musical, so they stay in both.

### Session 1 (day 0, about 8 to 12 minutes)

**Step 1. The click and the arrival.**
- Sees: lesson 1's Learn step as today, with one new line at the top **(new)**: "In about two
  minutes you'll hear your own voice in a chord." The landing link carries `from=start` so the app
  knows this arrival came from the CTA **(new)**.
- In-app browser check **(new)**: if the app can tell it's inside Instagram, TikTok or Facebook's
  browser (the cause detection already in `mic-help.js`), this step shows a styled card first:
  "Singing needs your mic, and this app's browser can't share it. Open in Safari / Chrome", with
  Copy link. Carry on anyway stays.
- Wonder: none yet; the promise sets one up.

**Step 2. The mic, explained, then one hum (merged check).**
- Sees **(new)**: before the browser prompt, the Continue button reads "Turn on my mic", and under
  it one line: "We listen while you sing to show your notes. Nothing is recorded or sent unless
  you choose to save a take." Then the browser prompt.
- Does: one hum. **(new)** The first hum does both jobs: the mic check's "Got you" and the start of
  finding the note, so there's one ask for sound, not two. The full mic check still opens when
  nothing is heard (today's no-sound panel is good and stays).
- Game: the ring glows and the pitch bar's ball leaves a short trail. Serious: today's calm bar.

**Step 3. Your note, then your note in a chord (wonder 1).**
- Sees: today's end screen (the note, the keyboard, "suits alto and tenor parts"), then **(new)** a
  button that plays itself once: **"Hear your note in a chord."** The last two seconds of the held
  note (kept in memory only, never saved) play back, and two guide voices join it, a third and a
  fifth above or below to fit the range, so it lands as a warm major chord. Three lanes appear like
  the home's "Alto · you" picture, with yours lit. Line: "That's you, and that's harmony. The
  course teaches you to sing those other parts too."
- The audio pieces exist: the guide synth (`renderPart`) and the mix used by Bullseye's stacked
  listen-back (`bullseye.js listenBack(true)`). New: keeping a short buffer in `NoteFinder`.
- Game: stars pop, "+XP", the "First note" badge, then the chord with a sparkle along the lanes.
  Serious: "Your note: A3. Held 4.1 s, steady within 12 cents." then the chord, no confetti.
- Ask: **Share, lightly** (new): a secondary "Share my note" beside Next lesson: a card "My voice
  sits around A3: alto and tenor parts suit me", with the optional name field (step 6 rules).

**Step 4. Lesson 2, the guide voice answers (wonder 2).**
- As today: Listen, then sing it back, the music waiting for you on Try. **(new)** On the take
  result, "Hear it together" (the label exists for the Counterpoint course) plays your take with
  the guide under it, so the first scored exercise ends with two voices, one of them yours.
- The result card's Share moves (or is copied) to the lesson end screen **(new)**, shown on two or
  three stars. Game: "Challenge a friend". Serious: "Share result".

**Step 5. Lesson 3 end: the first-session wrap (the "keep it" moment).**
- Sees **(new)**: after lesson 3's end (or when the person presses Back to the course during
  session 1 after at least two lessons), a wrap card under the result, not a modal:
  "Session one: your note A3, 3 lessons, 8 stars." Then two short rows:
  1. **Your week.** "How many days a week shall we aim for?" 2, 3 or 5 (3 pre-selected visually but
     not saved until tapped). This is the weekly goal, chosen, not given.
  2. **Exactly one keep-it ask**, by platform:
     - iPhone or iPad in Safari, not installed: **Install** first (it's what protects guest
       progress there). A sheet with two drawn steps: the Share icon, then Add to Home Screen,
       and "then open Stackapella from your home screen". iOS Chrome and Edge (16.4 and up) get the
       same with their own Share menu position.
     - Android Chrome, desktop Chrome or Edge with `beforeinstallprompt` kept: **Install** with
       one tap (the browser's own sheet).
     - Desktop Safari: "File, then Add to Dock" (today's text). Firefox desktop: no install; offer
       the account instead.
     - Already installed, or no install possible: **Save your progress** (account, below).
  - "Not now" on either row is remembered (principles above).
- Game: a level bar fill and the unit path glowing. Serious: plain totals.

**Step 6. Unit 1 done: your voice card (wonder 3, the first real share).**
- For people who keep going in session 1 (or on day 1): after the unit's challenge, **(new)** a
  voice card: the note, the easy range on a keyboard, the voice type line ("Your range suits alto
  parts best"), and the unit badge. This is identity, the most shareable thing a beginner has.
- **Name, where it's used** **(new):** the share sheet has one optional field, "Put your name on
  it?", empty for guests (kept on this device only), filled from the account when signed in. A name
  typed here is offered as the name at sign-up later. Never a separate modal.
- Share uses the existing result-card sheet (Post or Story, the device's share sheet, Download,
  Copy text) and the existing lesson link so a friend lands on lesson 1.

### Day 1 (the day-2 return)

**Step 7. Welcome back.**
- Sees **(new)**: the home's course card says "Welcome back. Yesterday: A3, 8 stars. Today:
  Hold a note, 4 min." with one big Continue. The Plus course groups and "0 of 3 free songs used ·
  Get Plus" stay out of a first-week home (shown once a song is made, or after unit 4).
- **The best moment for install and reminders** (they came back on their own): if not installed,
  the platform-aware install sheet's second (of three) offers; if installed, **reminders**:
  "Want a nudge on your practice days? One a day at most, at a time you pick." Reminders need an
  account today, so the chain is: Remind me, then (if a guest) the account sheet titled "Reminders
  need an account, so we know where to send them", then the reminders screen. On iPhone without the
  installed app, install comes first, so a guest is never sent through two walls at once; the
  sheet says which step is next.
- Unit 2's drone lesson **(new use of existing parts)**: "Hear it together" plays your held note
  with the drone, a fifth or an octave apart: wonder 4, small but real ("you and one other note,
  ringing").

### Days 2 to 4

**Step 8. The workout and the week ring.**
- As today: the workout strip and the ring on the home. **(new)** The ring's goal is the one chosen
  in step 5. Game: XP, levels, combo badges. Serious: one comparison line on the lesson end,
  "Steadier than Monday: 9 cents closer" (needs Hillel's call: history over time is on Plus today,
  and a single "since last time" line may or may not count as history).
- **Account, second milestone:** the first recorded take (unit 4's challenge records Amazing
  Grace's tune). Today Sing says "Saved automatically". **(new)** For a guest, one line under it:
  "Saved on this device. Make a free account to keep it safe and open it anywhere", with Continue
  with Apple, Continue with Google and Use email, opening the sign-up form (not sign-in) when the
  person picks email. Not now moves it to the next milestone.

**Step 9. Unit 4: the choir joins you (wonder 5).**
- Today: the first Use it step opens Sing with three guide parts around your tune. **(new)** The
  first time this happens, a one-time intro in Sing: the other three lanes fade in one by one
  under a short "Here come the other voices", then you sing. Game: a "Choir joined" badge. Serious:
  the same fade, no badge.

### Days 5 to 7

**Step 10. Your first harmony (wonder 6).**
- Unit 5's challenge records the round's 2nd voice over the 1st: the person hears two of their own
  parts together for the first time. This is the product's promise kept. **(new)** Before the
  playback, the screen goes quiet and plays both voices with the lanes stacked, titled with the
  name if one was given: "Are You Sleeping, sung by Maya and Maya".
- **Share, the big one:** Share the harmony (link or video, existing sheet). Needs an account
  today; here the account ask is functional and honest: "To share a link that stays yours, make a
  free account. Your progress and songs come with you."
- Game: "First harmony" badge (exists), leaderboard entry and Challenge a friend. Serious: "Your
  harmony" saved, the measures for each part.

**Step 11. Day 7: your first week (new).**
- On the home in the week's last practice day: "Your first week: 3 days sung, 11 lessons, note A3,
  range G2 to D4." Game: the week ring bursts and "1 week in a row". Serious: the same facts in a
  plain card. Share is offered (the third natural share moment), and the install sheet's last offer
  if it's still not installed and not declined three times.

### Where each ask lands (summary)

| Ask | First offer | Second | Third | After three Not nows |
|---|---|---|---|---|
| Install (platform-aware) | Session 1 wrap (step 5), phones first | Day-2 return (step 7) | First week card (step 11) or first recorded take | Settings row "Install the app"; the existing "songs only on this device" card stays (once there are recordings) |
| Account | Session 1 wrap, when install isn't possible or is done | First recorded take (step 8) | First harmony share (step 10, required to share a link) | Header Sign in only; never blocks learning, recording, or the result card |
| Name | Share sheet field (step 3 or 6) | Sign-up (prefilled) | First harmony's title (step 10) | Never asked on its own |
| Share | Lesson end with 2 or 3 stars, the note card (step 3) | Voice card (step 6) | First harmony (step 10), first week (step 11) | Always available, never pushed |
| Weekly goal | Session 1 wrap (step 5) | Course page, as today | | |
| Reminders | Day-2 return once installed or on desktop (step 7) | Course page, as today | | Settings > Reminders |
| Plus | Not in week one, except the existing limits and unit 7's end | | | |

The device keeps one small record of what was offered and declined (`stackapella.asks`), local
only, never sent.

---

## 4. Measuring the funnel, anonymously

### What the rules allow (and what exists)

- Today: `js/usage.js` sends batches of `{ kind, n }` with `signedIn` to `counts.create`; the
  server adds them to `usage_counts (day, kind, audience, country, count)`; nothing else is kept.
  Kinds are an allowlist in `config/counts.php`. Sign-ups are counted by the server.
- The rule (CLAUDE.md, decisions 2026-10-28): no IDs, cookies, fingerprints or IPs to follow
  anyone; only anonymous first-party daily totals. Admin shows totals, never paths.
- What this proposal adds keeps to that, with one change to say out loud: today `usage.js`
  promises "nothing kept on the device". The funnel needs the device to remember **which firsts it
  has already counted** and **its experiment variant**. Neither is an identifier (a variant letter
  is shared by half of all devices) and neither ever leaves the device except as a total. This
  needs Hillel's OK, an entry in `docs/decisions.md`, and a line on the privacy page.

### The funnel steps (new kinds, each counted once per device, ever)

Each is a "first": counted the first time it happens on this device, so step totals are devices,
not events. Many can be read from what the device already keeps (`learning.progress`: lessons,
`startedAt`, `practice` days, badges), so few new flags are needed.

| # | Kind (new) | Counted when | Notes |
|---|---|---|---|
| 0 | `f_site_start` | The landing's Start is clicked | Sent by the site (marketing), not the app; gives landing to app drop |
| 1 | `f_arrive` | The app's first open on this device (`f_arrive_start` when it came by `from=start`) | The variant is drawn here |
| 2 | `f_mic` | The mic first opens | Drop here = permission refused or in-app browser |
| 3 | `f_note` | First note found (or skipped: `f_note_skip`) | |
| 4 | `f_lesson1` | First lesson done | |
| 5 | `f_chord` | The chord moment played to its end | Only once built |
| 6 | `f_lesson2` | Second lesson done | First guide voice |
| 7 | `f_lesson3` | Third lesson done | End of a good first session |
| 8 | `f_unit1` | Unit 1 done | |
| 9 | `f_goal` | Weekly goal chosen | |
| 10 | `f_install` | `appinstalled`, an accepted prompt, or the first open in standalone mode | iOS counts on the first standalone open |
| 11 | `f_name` | A name given (card field, sign-up or settings) | |
| 12 | `f_account` | First time this device is signed in after being a guest | New or existing account; the server's `sign_up` still counts new ones |
| 13 | `f_share` | First result card or harmony share | |
| 14 | `f_remind` | Reminders turned on | |
| 15 | `f_take` | First take recorded in Sing | |
| 16 | `f_harmony` | First song with two of the person's own parts | |
| 17 | `f_day2` | The app opened on the calendar day after its first open | |
| 18 | `f_week1` | The app opened on any of days 2 to 7 | |
| 19 | `f_goal_met` | The weekly goal met in the first week | |

And for each ask (install, account, name, share, goal, remind): `ask_<name>_shown` (first time
only) and `ask_<name>_yes`, so we can read an ask's acceptance without anyone's path.

### A/B tests that fit the rules

- **The bucket.** At `f_arrive`, the device draws a variant with `crypto.getRandomValues` for the
  one running experiment (from `auth.config`, e.g. `{ id: "onb1", arms: ["a", "b"], enrol: true }`)
  and keeps `{ id, variant }` in `localStorage`. Devices that arrived before the experiment get no
  variant and count as today (no variant), so they never dilute an arm. Landing experiments: the
  site draws the variant and puts it on the Start link (`?v=b`); the app adopts it at `f_arrive`.
- **What's sent.** The batch gains one field: `"variant": "onb1-b"` (or none). The server keeps
  only `(day, kind, audience, variant, count)` for funnel kinds: **no country on experiment rows**,
  so the cells stay coarse at low traffic. The server accepts only variants listed in
  `config/counts.php` and drops the rest.
- **One experiment at a time.** Two at once would need both variants on every row, which halves
  every cell; sequential tests are simpler and keep totals coarse.
- **Reading it.** For each arm, conversion to step k = total(k) / total(`f_arrive`) over the test
  window. Compare arms with a two-proportion test and show the difference with a 95% interval.
  Check the split first (sample ratio mismatch): arrivals per arm should be within a few percent
  of 50/50; a skewed split means a bug, not a result.
- **Fixed horizon.** Decide the sample size before starting, stop enrolment at it (`enrol:
  false`; bucketed devices keep counting), wait 7 more days for `f_day2` and `f_week1` to mature,
  then read. No peeking decisions.
- **No per-person linking**: the server sums and forgets; a batch's body is never logged.

### What to build for it (new)

- Frontend (S to M): `js/funnel.js` (the firsts, the variant, the ask records), the new kinds in
  `usage.js`, the `variant` field, the `from=start` and `v=` link params.
- Backend (M): a `variant` column (default `''`) on `usage_counts` with the unique key widened,
  the kinds and experiments in `config/counts.php`, `counts.create` taking `variant`, the contract
  updated, and **Admin > Funnel**: per experiment and arm, the step totals, each step as a share
  of arrivals, the difference and its interval, the split check, and "not enough yet" until the
  planned sample is in.
- Marketing (S): the landing's Start beacon (`f_site_start`) and the variant on the link for
  landing tests.

### What it can and can't tell us

Can: where the biggest drops are (arrival to mic, mic to note, lesson 1 to lesson 2, session 1 to
day 2); whether a change moves a step's rate for new devices; how often each ask is taken; guest
versus account at each step.

Can't: anyone's path, time between steps, or why someone left; whether the same person did two
steps (we infer it from the ratio of totals, which holds because firsts are counted per device and
both arms are random); people across devices (two devices are two arrivals); devices that clear
storage (they arrive again, in a fresh bucket); exact day-by-day cohorts (daily totals mix
arrivals from different days, so read whole windows); small effects at low traffic; and anything
about counts lost offline or to a refused send (the loss should hit both arms equally).

### How long a test needs

Per arm, for 80% power at a 5% two-sided level: n ≈ 15.7 × p(1 − p) / d², with p the baseline
rate and d the lift worth detecting. The baselines below are guesses: replace them with the first
two weeks of funnel data before starting.

| Metric | Baseline | Lift to detect | Per arm | Both arms |
|---|---|---|---|---|
| Lesson 2 done per arrival | 35% | +5 points | about 1,470 | about 2,950 |
| Account made per arrival | 5% | +2.5 points | about 1,470 | about 2,950 |
| Day-2 return per arrival | 15% | +4 points | about 1,390 | about 2,800 |
| Install per arrival | 4% | +2 points | about 1,510 | about 3,000 |

So roughly 3,000 arrivals a test. At 100 arrivals a day: about 30 days, plus 7 for returns to
mature. At 300 a day: 10 days, rounded up to 14 (whole weeks, so weekdays and weekends balance).
At 1,000 a day: 3 days, but run a full 7. Below about 100 a day, use the funnel to find the biggest
drop and ship clear fixes without a test, or test only bold changes (a 10-point lift needs a
quarter of the sample).

### The first three experiments

1. **The chord moment** (`onb1`). A: lesson 1 ends as today. B: "Hear your note in a chord" plays
   after the note. Primary: `f_lesson3` per arrival (does wonder pull people through session 1?).
   Secondary: `f_day2`. Guardrail: `f_lesson2` must not fall.
2. **The first-session wrap** (`onb2`). A: today (no in-flow asks; install card on the home only).
   B: the wrap card after lesson 3 with the chosen weekly goal and one platform-aware keep-it ask.
   Primary: `f_install` + `f_account` per arrival. Secondary: `f_day2`, `f_week1`. Guardrail:
   `f_unit1` and `f_day2` must not fall (asks must not cost engagement).
3. **Reminders on the day-2 return** (`onb3`). A: reminders only on the course page. B: the day-2
   return offers the reminder (install first on iPhone). Primary: `f_week1` and `f_goal_met`.
   Secondary: `f_remind`, `ask_remind_yes`.

Next in line: the mic explainer line versus none (`f_mic` per arrival), the voice card share versus
none (`f_share`), and offering serious mode at the first lesson's end ("Prefer a calm look?")
versus not (`f_day2` among adults is the open question).

---

## 5. Priorities: the changes with the biggest expected impact

| # | Change | Why it matters | Size | Owner |
|---|---|---|---|---|
| 1 | **Funnel counting with variants**: the firsts, the device-only bucket, the `variant` column, Admin > Funnel (section 4) | Every other change needs it to be judged; it's the key funnel Hillel asked for | M | backend (M), frontend (S) |
| 2 | **The mic moment**: "Turn on my mic" with the one-line why, the in-app browser card before the prompt, and one hum for both the check and the note | The biggest early cliff: no mic, no product | S | frontend |
| 3 | **The chord moment** at lesson 1's end, and "Hear it together" on early takes and the drone | Delivers the landing's promise in the first two minutes, in both skins | M | frontend |
| 4 | **The first-session wrap**: chosen weekly goal plus one platform-aware keep-it ask (install on phones, account elsewhere) with remembered Not now | The first ask placed after a win; protects iPhone guests' progress from Safari's clearing | M | frontend |
| 5 | **Day-2 welcome back** on the home, with the install or reminders offer, and Plus and the free-song meter held back in week one | Turns the return into a habit loop; removes the upsell before value | S | frontend |
| 6 | **Share where the joy is**: Share on the lesson end, the note and voice cards, the optional name field kept on the device for guests, the first harmony's "sung by" title | Puts name and share asks where they're used; the voice card is the most shareable early fact | M | frontend |
| 7 | **Sign-up framing**: sign-in links from asks open in sign-up mode with the context's title ("Save your progress", "Reminders need an account"), the name typed earlier prefilled | Removes the "this isn't for me yet" page for new people | S | frontend |
| 8 | **The Start link and landing copy**: `from=start` and the variant on the link, the `f_site_start` beacon, and a landing line that matches the app's first promise | Lets the landing join the funnel and its tests | S | marketing |

Smaller fixes worth folding in: move the badge toast off the lesson header while singing; show the
found note once; ask "How did you hear about us" of new accounts at their first sign-in rather
than only at lesson 1's end.

---

## 6. Questions for Hillel (to log in decisions.md when answered)

1. The funnel needs the device to remember which firsts it counted and its experiment variant
   (local only, never an identifier). OK to change `usage.js`'s "nothing kept on the device"?
2. Experiment rows without country, to keep cells coarse: OK?
3. In week one, hide the Plus course groups and "0 of 3 free songs used · Get Plus" from the home?
4. Serious mode's "steadier than last time" line on the lesson end: fine on the free plan, or is
   that "history over time", which is Plus?
5. The chord moment keeps two seconds of the held note in memory for playback (never saved or
   sent). OK, with the mic explainer line saying so?
6. A "Stack yourself" taste earlier in the course (sing a note, then a third over your own take,
   using the existing `exercise.under`), as a bonus step in unit 2: worth a content change to the
   reviewed free course?
