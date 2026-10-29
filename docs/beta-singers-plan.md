# Beta singers: real voices for the official guides

**Status:** planned, not started (2026-10-26), revised after a review for misses and UX the same
day. Built in M2 (2026-10-26). It's roadmap M2, on the foundation from M1 (where the shared pitch tracker and scoring are
built). Build it in the order under "Order of work".

## Context

Before launch, Hillel wants to share Stackapella with real singers and have them **replace the
synthesized guides with real singing**. It all happens in the app:
- he invites a singer, who records parts of the official templates;
- he reviews their takes;
- with one action, an accepted take becomes **that part's default guide for everyone**.

Real voices make templates far more appealing to learn from (a risk named in the product review),
and the beta doubles as early user testing.

This is the app's **first backend piece**, built to the conventions (`docs/conventions.md`):
Laravel with Actions, FormRequests, API Resources, Sqids IDs, MySQL, PHPUnit, Pint and Larastan.
It's hosted on DigitalOcean App Platform with Spaces, on the same origin as the web app.

**Hillel's answers (2026-10-26):**
- **Invite links on a minimal backend:**
  - each singer gets a private link, with no password;
  - takes upload to Spaces;
  - Hillel reviews them on an admin page in the app.
- **Key changes fall back to the synth.** Recorded guides play in the template's original key. In
  any other key the synthesized guide plays, with a note saying so. There's no pitch-shifting.
- **Credit:** a recorded part shows "Alto sung by Sarah K." if the singer chooses, or stays
  anonymous. Credit shows in the template and in exports.
- **Payment (decided 2026-10-26): singers are paid their normal hourly rate** for the time
  they spend.
  - The rate and a time estimate (about 20 to 30 minutes a part, practice included) are agreed
    before they start.
  - Hillel pays them himself (a bank transfer or similar, outside the app), against a short
    invoice or a list of the parts they recorded.
  - Admin records each singer's rate and the time per accepted part, so Hillel can see what's owed
    and what's paid (below).

**Before the first take:**
- **The arrangements are reviewed and frozen first.** All 8 templates are drafts awaiting a
  musician (`docs/templates-review.md`). A take is tied to its template version, so a note fixed
  after recording orphans every take of that part. A template opens for recording only once it's
  marked `reviewed: true`.
  **Update (2026-10-28):** Hillel waived the musician's review; the beta singers are the reviewers.
  Twenty templates are `reviewed: true` after the arranger's fixes
  (arrangements-fixes-applied.md). A fix after a part is recorded still needs a new version,
  and that part goes back to the synth until it's sung again.
- **Each template has one recording key,** its original key, which should suit most singers of each
  part. Moving the key later means re-recording, so it's checked during the musician's review.
- **Guides sing the words** as written under each note (the synth sings "oo"). Parts written on
  "oo" (the bass in Amazing Grace) are sung on "oo".

**Recommendations open to veto (made in the review, not asked yet):**
1. **Learners can switch a part between the real voice and the synth.** Real is the default. The
   synth's steady pitch is easier to match for some beginners, and some will just prefer it.
2. **Existing projects pick up a new real voice** the next time they're opened, with a one-time
   note ("A real voice now sings the alto. Prefer the synth? Switch it here."). Only guides change;
   a learner's takes are never touched.
3. **Official recorded guides are free for everyone.** The pricing plan's "Recorded (human) guides:
   Plus" row is about guides people record for their own templates (storage they use), not the
   official library.
4. **Different singers can make up one template.** Hillel judges the blend by playing the template
   in all its real voices before accepting a take, and can prefer one singer per song where it
   matters.

The whole backend, of which this is the first slice, is planned in
[backend-plan.md](backend-plan.md). **One change from it:** the singers' licence also covers their
guides heard in harmonies people make and share in the public gallery.

## The singer's experience

1. **The invite link** (`/beta/<token>`) opens to a welcome:
   - who Hillel is, what the beta is, and what they're being asked to record;
   - about 20 minutes per part, worked out from the part's length;
   - **a 20-second sample** of a template already sung by real voices (once there is one), so they
     hear what they're helping make;
   - **a browser check:** a browser that can't record well (for example an old Safari) is told
     which to use, before any setup.
   - **A revoked or expired link** says so kindly: "This link has expired. Ask Hillel for a new
     one."
2. **Consent** (once):
   - a plain-language summary, then the full agreement (see "Consent and rights");
   - "Credit me as: [name] / Keep me anonymous";
   - "I'm 18 or over".
   - Acceptance is recorded with the agreement's version.
3. **Setup** (once, and on a new device):
   - headphones on;
   - the mic picker;
   - **the timing check, required** (a measured delay, not an estimate, so their take lines up
     exactly);
   - the range check, to suggest parts;
   - **a sound check:** sing for 10 seconds, hear it back, and see the same quality checks as
     before sending (level, noise, clipping, bleed). This catches a bad room or mic before they sing
     a whole part, not after.
   - **On iPhone,** the installed app keeps its own sign-in, separate from Safari. My parts shows a
     6-character code ("Open the Stackapella app and enter K7Q-2MX") so the link's session moves
     into the installed app. The same code works on another device.
4. **My parts:**
   - the songs and parts Hillel assigned, or open parts the singer can pick;
   - each shows its status: To record, Submitted, In review, **Now the guide**, or "Hillel asked for
     another take" with his note;
   - a thank-you count: "You're the voice of 3 parts."
5. **Record a part,** using the app's existing learn-then-record flow (the singing UX once it's
   built):
   - learn with the synth guide and the other parts, which use any real voices already accepted, so
     the songs sound more human as the beta goes on;
   - record the whole part, in the original key, with the count-in;
   - several takes are fine: **pick your best**;
   - takes stay on the device until they're sent, so the singer can stop and come back later;
   - beta takes live apart from their own projects and don't count toward the free limit of 3.
6. **The quality check, before sending,** done on the device and phrased kindly:
   - **pitch accuracy against the notes** (the coaching plan's scoring), for example "92% on pitch,
     lovely";
   - **timing,** against the click and the notes;
   - **clipping** (too loud), **background noise**, **silence**, and **bleed** (the other parts
     heard through the mic);
   - each problem comes with a fix ("A bit quiet: move closer to the mic") and Record again, but the
     singer may always send it anyway, with a note;
   - the report points at the phrases that need work ("phrase 3 was a little flat"), with a Loop
     button for each, instead of a single score;
   - problems are shown with an icon and words, never by colour alone;
   - **Hear it as learners will:** the take in the mix, at the level learners hear it, before
     sending.
7. **Send:**
   - the take uploads with its details (template version, part, key, sample rate, measured delay,
     device and mic, scores);
   - a failed upload is retried from the start when the connection is back (the take waits on the
     device), with a Wi-Fi tip;
   - a take recorded offline uploads later from the device.
8. **Status and thanks:**
   - the link always brings them back to My parts;
   - when a take is accepted: "You're now the alto of Amazing Grace!", with their credit shown as
     learners will see it;
   - "Hillel asked for another take" opens the part with his note **pinned to its phrase**, and
     that phrase ready to loop;
   - they can change their credit (or go anonymous) at any time.
9. **Feedback, from anywhere in the beta:** a "Tell Hillel" button for anything ("this note feels
   wrong in bar 12", "the timing check failed"), with the template, part and position attached.
   Singers are the first real users, so this is the beta's user testing. It's explicit feedback
   stored on the server, not analytics.

## Hillel's side (admin, in the app)

- **Access:** only Hillel, through Google sign-in restricted to his Google ID, with the second
  factor and sudo for destructive actions. This is the iaw admin pattern (`config/admin.php`,
  `AdminGate`), at `/admin`.
- **Invites:**
  - create one (name, optional email for his records, a note, assigned parts), and **Copy link**;
  - he sends it himself; the app sends no email (`docs/conventions.md`). On a phone, **Share**
    opens the system share sheet (Messages, WhatsApp);
  - **Copy a status message** for a singer ("Your alto for Amazing Grace is now the guide, thank
    you!"), since the app sends nothing itself;
  - **New link** replaces a lost or forwarded one (the old one stops working);
  - revoke, or set an expiry;
  - see who has consented and who is active.
- **The review queue** (newest first, filter by song and part). For each take:
  - **Play it in the mix,** with the other parts (synth or accepted voices), solo, and **A/B
    against the synth guide**;
  - **the pitch chart** (from the coaching plan): the notes, the singer's line, the scores, and
    the automatic checks, and the singer's own note;
  - **fix lightly:** nudge the alignment (ms), trim and fade the start and end, and set the gain.
    Takes are levelled to the guides' loudness (about -20 dBFS RMS);
  - **Accept as the guide,** **Ask for another take** (with a note the singer sees, pinned to a
    phrase by clicking the chart), or **Reject** (with an optional kind note);
  - **Hear the whole template in real voices:** every part's default guide together, with this take
    in place, to judge the blend before accepting;
  - **keyboard:** J and K for next and previous, Space to play, A to accept.
- **Guides per part:**
  - one **default** guide, plus any other accepted takes kept as **alternates**;
  - switch the default in one click; the change appears to learners at once;
  - later, learners could choose a voice, or hear several voices together as a small choir.
- **Progress:** per template, "3 of 4 parts are real voices", and which parts still need a singer.
- **Feedback inbox:** singers' "Tell Hillel" notes, each with a link to the template and position.
- **Layout:** built for desktop (the chart and mix need room), but the queue, listening and the
  three decisions also work on a phone.

## How a recorded guide plays

- **The template format** (`docs/template-creator-plan.md`) already plans an optional
  `guide.recording` on each part. Here it gains:
  - `url`, an AAC file on the public CDN at an immutable address;
  - `sampleRate`, `offsetSeconds` and `gain`;
  - `credit` (a name, or null for anonymous);
  - `templateVersion`.

  **Notes stay required:** they drive the Note lane, the Staff, the scoring and the synth fallback.
- **The player** (session store and engine): the guide voice (`<part>:guide`) loads the recording
  instead of rendering the synth when:
  - the key is the original (`transpose` is 0), the speed is 100%, and the recording matches the
    project's template version.

  Otherwise it renders the synth, with a kind note that doesn't feel like a downgrade: "A real
  singer recorded this in G. You're singing in A, which suits your voice better, so you'll hear our
  practice voice", with "Sing in G with the real voice" one tap away
  (`docs/more-features-plan.md`, feature 16).
- **Where the player finds it:** a project keeps its own copy of the template
  (`startFromTemplate` clones it), so recorded guides aren't stored in that copy. They're looked up
  in the catalogue by `templateId@version:partKey`, which is how existing projects pick up a new
  voice (recommendation 2).
- **Real voice or synth, per part** (recommendation 1): a small switch on the part, remembered in
  `project.practice`.
- **Practice mixing is unchanged:** `voiceLevels` treats the guide voice the same whatever plays it.
- **Sample-exact alignment survives the AAC encode.** AAC adds encoder delay (priming) at the
  start, and browsers differ in whether `decodeAudioData` removes it. The server writes an MP4 with
  the edit list (`edts`), and stores the exact frame count **and its sample rate**. On decode, the
  app compares the decoded length with the frame count **scaled to the AudioContext's rate**
  (`Math.round(frames * ctx.sampleRate / sampleRate)`, since `decodeAudioData` resamples). If the
  priming is still there, it's skipped, and the guide's offset is scaled the same way. A test checks the result against
  the WAV master to the sample, in Chrome, Safari and Firefox.
- **Credit** shows on the part (in the strip, the cards or the rail), in the template's details
  ("Sung by Sarah K., Ben T. and Hillel"), and in exported file names and notes.
- **Offline:** recorded guides of templates you've opened are cached, by the service worker and in
  IndexedDB, so an installed app works offline. Safari can clear a website's storage after
  about a week without use (not an installed app's), so a cleared guide is simply fetched again,
  or the synth plays when offline.
- **Size:** AAC at about 128 kbps is roughly 2.8 MB for a 3-minute part, against 17 MB as WAV. Every
  browser can decode it, through `decodeAudioData`.

## The backend (the first slice of `/server`)

- **Laravel 13, set up per the conventions:**
  - Actions, FormRequests, API Resources, Sqids;
  - MySQL, with a per-session test database;
  - PHPUnit with the QueryGuard and architecture tests;
  - Pint and Larastan level 8;
  - the GitHub Actions CI;
  - `.do/app.yaml` (web, scheduler, migrate) and Spaces disks (`media` is public through the CDN;
    `private` holds the masters).
- **Tables:**
  - `beta_singers`: name, optional email, credit name and mode, invite token hash, expiry, consent
    version and time, revoked time;
  - `assignments`: singer, template id, part key, status;
  - `submissions`:
    - links: the assignment, template id and version, part key, key;
    - audio: sample rate, measured latency frames, duration;
    - review: scores (JSON), status, admin note;
    - files: master key (WAV, private) and delivery key (AAC);
    - adjustments: offset, trim, gain;
  - `template_guides`: template id and version, part key, submission, default or alternate, and
    the published URL;
  - `templates`: the catalogue, **seeded from the repo's `web/templates/*.json`**, plus the guides;
  - `beta_feedback`: singer, template, part, position, text, and whether Hillel has read it.
- **Endpoints:**
  - **singers:**
    - `POST /api/beta/session` swaps the invite token for a session cookie. The app then removes
      the token from the address bar (`history.replaceState`), and `Referrer-Policy: no-referrer`
      keeps it out of other sites' logs;
    - `POST /api/beta/session/code` swaps the 6-character code (short-lived, one use) for a
      session, for the installed iPhone app or another device;
    - `GET /api/beta/parts`;
    - `POST /api/beta/submissions` returns a presigned PUT URL to the private bucket;
    - `POST /api/beta/submissions/{id}/complete`;
  - **public:** `GET /api/templates` is the catalogue, cacheable, with the app's bundled templates
    as the offline fallback;
  - **admin:** invites, submissions, accept, ask, reject, adjust, the default guide, publish.
- **Jobs:**
  - `ProcessSubmission`: check the WAV, measure loudness, make the AAC with ffmpeg (via the
    `Aptfile`), and work out the outline for the waveform;
  - `PublishGuide`: apply trim, fade, gain and offset, write the AAC to the public bucket at an
    immutable key, and update the catalogue.
- **Rate limits** on uploads, as iaw does (per invite: per minute and per day), and on the code
  exchange, so codes can't be guessed.
- **Admin code isn't precached** by the service worker, and every admin route is checked on the
  server; hiding the screens is not the protection.
- **Storage housekeeping:** a rejected take's master is deleted after 90 days (a scheduled job);
  accepted masters are kept, so a guide can be re-published with different trims later.
- **Hosting:** a **beta domain** (for example `beta.stackapella.com`). Invite links work there;
  everyone else sees "coming soon". This is the first real deployment, with DO Managed MySQL and
  two Spaces buckets.
- **Guides outlive the beta domain:** they're served from the Spaces CDN's own address, not the
  beta domain, so they keep working when the app moves to its launch domain.

## Consent and rights

- **A plain-language agreement,** reviewed by a lawyer before the first invite. The singer:
  - grants Stackapella a worldwide, royalty-free licence to use, edit and distribute their
    recordings as guides in the app and in its promotion, **including inside the harmonies people
    make and share or monetise elsewhere** (`docs/go-to-market-plan.md`);
  - chooses whether to be credited;
  - can **ask to have their recordings removed** from future use (Hillel removes the guide, the
    file and its CDN copy; exports already made, and copies cached on learners' devices until they
    next connect, aren't recalled);
  - confirms they're 18 or over (no recordings from minors in the beta).
- **What's stored:** the agreement's version, the time it was accepted, and the credit choice.
- **Personal data is minimal:** a name, an optional email held only for Hillel's records, and the
  recordings. Deletion on request.
- **Payment:**
  - **the data:** `beta_singers` gains `hourly_rate` and `currency`, and `submissions` gains
    `minutes` (agreed per part) and `paid_at`;
  - **in admin:** each singer's owed and paid totals, with "Mark as paid" (audit-logged);
  - **the agreement** gains a payment clause: the rate, what's paid for, and when;
  - **tax:** singers are paid as contractors, so the accountant confirms what Hillel needs (for
    example a W-9 and a 1099 for US singers above the threshold);
  - **the budget:** roughly 20 songs at about 3.5 parts each is 70 parts, or about 25 to 35
    hours, at each singer's rate.

## Review (misses, UX, code fit)

**Missed, now covered:**
- **Timing must be measured.** The timing check is required before recording a guide (an estimate
  can be tens of ms out). Admin can still nudge.
- **Version drift:** if a template's notes change, its guides are tied to the old version. They're
  kept but flagged in admin ("recorded for version 2"), and the synth plays until a new take is
  accepted.
- **Keys and speed:** the original key and 100% only; otherwise the synth plays, with a note.
- **Phone mic quality:** the app records with the voice processing off (echo cancellation and noise
  suppression), as it already does. The quality check catches the rest.
- **Large uploads on mobile data:** a Wi-Fi tip, automatic retries from the device, and the take is kept
  on the device until it's confirmed.
- **No automatic email** (the existing rule). Status lives in My parts, through the invite link.
  Hillel can email singers himself.
- **Minors:** 18 or over only in the beta.
- **Bleed and clipping** are checked before sending.
- **A singer's own projects** stay on their device, like everyone's. Only the takes they send
  upload.

**UX:**
- **For singers:**
  - one link, no password;
  - a short consent;
  - setup once;
  - a clear list of parts;
  - the quality check that helps before they send;
  - status in their own words ("Hillel asked for another take: 'a little rushed in phrase 3'").
  - The best thanks is hearing your voice become the guide, so My parts plays it back once accepted.
- **The songs improve as the beta goes:** later singers hear earlier real voices, not the synth.
- **For Hillel:** a fast queue with keyboard shortcuts, the mix and A/B, light fixes instead of
  asking for retakes, and a per-template "real voices" progress view.
- **For learners:** credits are visible; a note explains when the synth plays instead; nothing else
  changes.

**Code fit:**
- **Reuses:**
  - the recording, latency compensation and quality signals (`isSilent`, empty quanta);
  - from the coaching plan, the pitch tracker and scoring;
  - from the template creator, `guide.recording`;
  - from the singing UX, the session store and recording state;
  - `voiceLevels`, unchanged.
- **Can come before the singing UX:** singers can record through today's studio "Record my part"
  and practice bar. The singing UX takes over when it's built.
- **The shared pitch tracker and scoring** (`docs/vocal-coaching-plan.md`, step 1) are built here
  first if this comes first: one implementation for all three plans.

## Second review: misses and UX (2026-10-26)

| Found | Change |
|---|---|
| All 8 templates are drafts; a note fixed after recording orphans the takes | Recording opens only for reviewed templates (step 0) |
| One fixed key per template, and the synth sings "oo" | The key is checked in the musician's review; guides sing the words |
| AAC encoder delay would break sample-exact alignment | Edit list plus a decoded-length check, tested against the master |
| The installed iPhone app doesn't share Safari's sign-in | A 6-character code moves the session into the app |
| Invite tokens in the address bar can leak | Removed from the URL after use, no referrer, New link replaces a lost one |
| Projects keep their own template copy, so new guides wouldn't reach them | Guides are looked up in the catalogue by template version; a one-time note tells the learner |
| Some learners pitch-match a synth more easily than a voice with vibrato | A Real voice / Synth switch per part |
| The pricing plan lists recorded guides as Plus | Official guides are free; the Plus row is for people's own templates |
| Singers find a bad room only after singing a whole part | A 10-second sound check at setup |
| A single score doesn't say what to fix | The report and Hillel's notes point at phrases, each with Loop |
| Status changes reach no one, since the app sends no email | Copy a status message, and Share for invites |
| The beta is also user testing, but there was no way to report problems | Tell Hillel, with the position attached, and an inbox |
| Different singers per template may not blend | Hear the whole template in real voices before accepting |
| Beta takes would count toward the free project limit | They're kept apart from projects |
| Removal can't reach exports or offline copies | Said plainly in the agreement |
| Guides would break when the beta domain changes | Served from the Spaces CDN address |
| Rejected masters pile up in storage | Deleted after 90 days; accepted ones kept |

## Order of work (each step committed and tested)
0. **The arrangements are reviewed** by a musician and marked `reviewed: true` (already pending;
   `docs/templates-review.md`). Server and app work can start before this; recording can't.
1. **Server foundation:**
   - `/server` scaffolded to the conventions;
   - CI;
   - `.do/app.yaml`, Spaces disks, and the beta domain;
   - Google sign-in for the admin only;
   - README setup steps (local MySQL through DBngin, since the `mysql` CLI isn't on PATH yet).
2. **Recorded guides in the app,** tested with a local file:
   - `guide.recording` in the format and validation;
   - the player loads it in the original key at 100%, otherwise the synth with a note;
   - the catalogue lookup by template version, so existing projects pick up new voices;
   - the Real voice / Synth switch;
   - the AAC priming check, in all three browsers;
   - credit display;
   - caching;
   - the catalogue endpoint, with the bundled fallback.
3. **Invites, consent and My parts:** tables, Actions, FormRequests, Resources, feature tests;
   singer screens, including the expired-link screen and the code for the installed app.
4. **Recording, the quality check and upload:**
   - the shared pitch tracker and scoring (the coaching plan's step 1);
   - the checks, and the sound check at setup that reuses them;
   - Hear it as learners will;
   - the presigned upload, retried from the device's outbox;
   - `ProcessSubmission` (ffmpeg);
   - feature tests with a fake Spaces.
5. **The admin review:** the queue, mix and A/B, the pitch chart, light fixes, accept, ask, reject,
   `PublishGuide`, defaults and alternates, and "real voices" progress.
6. **Polish:** status messages (and Copy a status message), the thank-you, Tell Hillel and the
   feedback inbox, removal on request, and docs (`docs/beta-guide.md`, for Hillel: inviting,
   reviewing, publishing).

**Later, if singers ask for it:** **fix one phrase** (punch in). Recording is sample-exact, so
re-singing one phrase and crossfading it into a take is feasible. It's left out at first because
a guide sung straight through usually sounds more natural, and it adds editing to the review.

## Verification
- **Server (PHPUnit, MySQL):**
  - invite tokens (hashed, expiring, revocable);
  - consent required before uploading;
  - presigned uploads scoped to the singer's own submission;
  - the installed-app code: short-lived, one use, rate-limited;
  - a new link stops the old one working;
  - only templates marked reviewed can be recorded;
  - only Hillel's Google ID reaches the admin;
  - accept publishes a guide and updates the catalogue;
  - version-drift flags;
  - rate limits;
  - the architecture tests.
- **Web (`web/tool/test.sh`):**
  - the player uses the recording only in the original key and version, otherwise the synth;
  - the quality checks flag a clipped, a silent and a bleeding take, and pass a good one
    (synthesized);
  - the catalogue falls back to the bundled templates offline;
  - a decoded AAC guide lines up with its WAV master to the sample (run in Chrome; Safari and
    Firefox by hand until the browser tests cover them);
  - an existing project made before a guide was accepted plays the new voice, and the Synth
    switch brings the synth back.
- **Flow scripts** (`web/tool/cdp.py`, muted, fake mic or loopback):
  1. **As a singer:** invite link, consent, setup, record the alto of Amazing Grace, the quality
     report, send. The status is Submitted.
  2. **As admin:** review, nudge, Accept. The singer's link then shows "Now the guide".
  3. **As a learner:** open Amazing Grace and hear the recorded alto with its credit; change key,
     and hear the synth with the note.
  4. **On iPhone (by hand):** open the invite in Safari, install the app, enter the code, and see My
     parts in the installed app.
- **By Hillel:** invite one friend first, end to end, before inviting more.

---

[Back: Harmony templates](templates-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Sing](singing-ux-plan.md)
