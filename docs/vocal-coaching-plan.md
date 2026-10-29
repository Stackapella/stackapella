# Becoming a better singer: a course you play, like Simply Piano

**Status:** planned; the pitch tracker and scoring built in M1 (2026-10-26); the course, part 1 (the map, the lesson player, stars, XP, levels, the weekly goal, units 1 to 8 as data, the first run), built in M3 (2026-10-27). First written 2026-10-26, and **rewritten the same day** around a
gamified course in the style of Simply Piano.
- **Roadmap M1:** the shared pitch tracker and scoring.
- **Roadmap M3:** the course map, the lesson player, stars, XP and the weekly goal, with the
  harmony units.
- **Roadmap M5:** the singing units, live feedback, the daily workout, badges and Your voice.
- **When the network features panel says so (N2, with the gallery):** leagues and friends'
  leaderboards.

## Context

Stackapella teaches the notes of a harmony and records you singing them. This plan is the reason
to come back every day: **getting better, and enjoying it.**

Simply Piano made learning an instrument feel like a game:
- a course map of short lessons;
- music that scrolls toward you and lights up when you're right;
- a "wait for me" mode while you learn;
- stars to earn, and celebrations;
- a short daily workout.

This plan does the same for the voice:
- how accurately you sang each note, live and after;
- your pitch line against the notes;
- timing and vibrato;
- exercises in your range;
- tips that respond to how you sing.

**The analysis runs on the device.** Only the progress numbers sync with an account, never the
practice audio (see the privacy table in `docs/architecture.md`).

**Hillel's answers (2026-10-26):**
- **Feedback live and after:** a live pitch line while singing, plus a report after each take.
- **Coaching:**
  - built in;
  - "Send to my teacher" for a singer's own teacher;
  - and a marketplace of coaches ([coaching-marketplace-plan.md](coaching-marketplace-plan.md)).
- **A Simply Piano-style course, gamified:**
  - **gentle streaks:** a weekly goal, not a daily chain. There's no penalty for a missed day, and
    it's in the app only, never by email;
  - **the course's levels unlock in order, but the library is always open:** any song, any time;
  - **stars** per exercise and song section;
  - **XP and voice levels;**
  - **badges;**
  - **leaderboards,** designed so beginners aren't discouraged (below).
- **Basics free, depth on Plus** (the split for the course is under "Free and Plus").
- **Content:** Claude drafts the lessons, tips and exercises, and a qualified voice teacher checks
  them before release, like the musician's check on templates.

## The rules the game keeps (from the vision)

- **It rewards effort, not talent.** XP comes from practising, so a beginner who practises climbs
  as fast as a natural singer. Stars measure accuracy, but you can always replay for more.
- **Never red, never 0%, never "wrong".** A missed note dims and waits. A rough take says "Tricky
  one! Try it on loop with the guide on."
- **Never nagging.** The weekly goal is the only streak. Missing a week starts a fresh one, with no
  "you lost your streak" message. Nothing is sent by email. **Practice reminders are opt-in web
  push, at launch** (`docs/operations-plan.md`): offered once after setting a weekly goal, at most
  one a day on the chosen days, kindly worded.
- **The library never locks.** The course is a guided way in, never a gate in front of the song
  someone came for.
- **Sounds and celebrations happen between exercises,** never while you're singing or recording,
  and they respect reduced motion.

## UI/UX: what changed and why

| Before | After | Why |
|---|---|---|
| Separate screens: report, warm-ups, tips, Your voice | **One course** with a map, short lessons, stars and a daily workout, with everything else hung off it | One clear place to go next, as in Simply Piano |
| A score after singing | **Stars (1 to 3) per exercise and song section,** with Replay to earn more | A goal you can see, and a reason to try once more |
| Live line only | **Notes light up as you hit them,** with a soft glow and a "Nice!" pop; missed notes dim, never red | Instant, positive feedback is what makes it feel like a game |
| The music never waits | **Wait for me:** in learning steps, the music pauses on each note until you find it | Beginners learn pitches without being rushed, as with Simply Piano's wait mode |
| A level word ("Close") | Kept, beside the stars, with the number in the details | Words first, numbers second |
| Warm-ups (5 min) | **The daily workout:** 5 to 8 minutes, made for you (a warm-up, a weak spot, one new thing) | One button a day, worth coming back for |
| Progress charts (Plus) | **XP, a voice level, badges, and the weekly goal** on Your voice, plus the charts | Progress you can feel, not just read |
| No social part | **Leagues of singers at your level, ranked by practice (XP), not talent,** and friends | Light competition, without punishing beginners |
| Earlier rows kept | Feedback at the edge on the Stage; strictness levels; plain words not cents; the summary that slides up; replay with your line; practice runs scored; bleed checked in the first seconds; "analysed on this device" | They still hold |

## What we can and can't measure (honest limits)

| Measure | How | Shown as |
|---|---|---|
| **Pitch accuracy per note** | The pitch track (YIN) against the note, in cents, over the steady middle of the note | Lit (on pitch), close, or not yet, within the strictness (Relaxed 50, Standard 35, Precise 20 cents); % on pitch |
| **Sharp or flat tendency** | The average signed error, overall and by register (low, middle and high third of the part's range) | "A little flat on high notes" |
| **Steadiness** | Pitch spread in a note's middle, with vibrato removed | Steady, or wavering |
| **Scoops** | Starting more than a semitone away, then arriving within 150 ms | "You scooped up to 'grace'" |
| **Timing** | Where each sung entry starts against the note's start (latency compensated) | Early or late by N ms (only when the timing check has been done) |
| **Vibrato** | On notes of 0.6 s or more: the wobble's speed (Hz) and width (cents) | Descriptive: "gentle vibrato, about 5.5 times a second" |
| **Range** | The lowest and highest steady notes sung | On Your voice, over time |
| **Head vs chest voice** | **Not measured.** Telling them apart reliably needs more than pitch, and a wrong call would erode trust. | Lessons and tips about it, never a verdict |

## The experience

### 1. The course map (home for learning)
- **Where:** the first tab of the projects page, **Learn**, above the library. The first run (roadmap
  M3) ends here.
- **What it looks like:** a vertical path of **units**, each a chapter with an illustration and a
  colour, made of **lessons** (round nodes) that fill with stars.
  - **The current lesson** is big, with **Start**.
  - **Done lessons** show their stars.
  - **The next unit** shows as "Earn 12 stars to open".
- **At the top, always:**
  - your **voice level** and XP bar;
  - **the weekly goal ring** ("2 of 3 days this week");
  - and **Today's workout**.
- **Placement:**
  - the first run asks "Have you sung harmony before?";
  - **beginners go straight into unit 1, lesson 1,** with only the mic permission. **Unit 1 finds
    their range as they sing,** so there's no separate range check, and **the timing check waits
    until their first timed take** (recording, or timing-scored exercises from unit 4);
  - experienced singers get the range check and can **take a short check** to skip ahead (a few
    exercises, Simply Piano's "test out"), which opens the units they pass.

**The units** (drafted by Claude, checked by the voice teacher and the musician):

| Unit | Lessons are about | Ends with (the unit's song challenge) |
|---|---|---|
| 1. Find your voice | Matching a note; sirens; your range; breathing | Sing back 5 notes |
| 2. Steady notes | Holding a pitch; breath support; vowels | Hold a note against a drone for 8 beats |
| 3. Moving notes | Steps, scales, small jumps | A short tune: Ode to Joy's opening melody, **as an exercise in the unit's own data** (it moves by step, which is right for this unit; the full song is Plus) |
| 4. Singing a tune | Following the words; phrasing; entries on time | The melody of Amazing Grace, verse 1 |
| 5. Your first harmony | **The harmony path** (`docs/more-features-plan.md`, feature 2): hum against a drone; **Find the third** (feature 9); a round (Are You Sleeping); thirds (Michael, Row the Boat) | Record the round's 2nd voice |
| 6. Low and inner voices | The bass line; the tenor; **Hold your note** against moving chords (feature 9) | Record the bass of Amazing Grace |
| 7. The whole song is you | Recording every part; hearing the blend | **Finish all 4 parts of Amazing Grace** |
| 8. Expression | Dynamics; gentle vibrato (described, never forced); vowels on high notes | A song of your choice, with stars |

- **Units 5 to 7 are the "Your first harmony" path,** so the path and the course are one thing.
- **The units open in order,** earned with stars (about two thirds of the unit's stars). **The
  library and Sing stay open to everyone, always.**

### 2. A lesson (about 5 minutes)
- **Steps,** one at a time, with a progress bar along the top (the Coach style, 2d):
  1. **Learn:** a short card, with a picture and a listen ("Here's a third: two notes that sound
     sweet together").
  2. **Try:** the exercise, in **Wait for me** mode.
  3. **Play it through:** the same exercise with the music moving, scored for stars.
  4. **Use it:** a phrase from a real song, in Sing.
- **The end screen:**
  - the stars appear one by one, with a chime;
  - the XP is added up;
  - **Replay** (for more stars) or **Continue**;
  - one tip if something stood out ("Try this: hum 'ng', then open to 'ah'").

### 3. The play-along screen (Simply Piano's falling notes, for the voice)
- **The Note lane** (`docs/singing-ux-plan.md`, 2b) is the stage: the notes move toward the now
  line, labelled with the syllable and the note name. **Your pitch draws as a line.**
- **When you're on a note,** it **lights up** in your part's colour, with a soft glow, and "Nice!"
  or "Perfect!" pops above it. **A held, steady note** fills like a bar. **A missed note** dims and
  slides past, with no red and no sound.
- **Wait for me** (in Try steps and practice):
  - the music **stops on each note** and holds it softly until you find it within the strictness
    for about a third of a second, then moves on;
  - "Higher" or "Lower" arrows appear only after a moment;
  - **on speakers (no headphones),** the note plays and then goes quiet while you sing, with
    **Hear it again**. Otherwise the tracker would hear the speaker's note and count it as yours;
  - it needs no timing check: it's about pitch, not milliseconds.
  - It's built on the engine's segments on the AudioContext clock. When the pitch tracker hears
    the note, the next note is scheduled on the audio clock a few milliseconds ahead. **No timers
    schedule audio.**
- **On the Stage (2a), at the edge:** a slim tuning bar and the glowing syllable, so the eyes stay
  on the words.
- **Combo:** a gentle counter for notes in a row on pitch ("8 in a row"). It resets quietly.
- **Bleed** is checked in the first seconds, with a calm banner ("We can hear the other parts.
  Headphones give you accurate stars.").

### 4. Stars, XP and levels
- **Stars** (per exercise and per song section):
  - **one** for finishing;
  - **two** for 70% on pitch;
  - **three** for 90% on pitch with entries on time.

  The strictness is Relaxed through unit 4, then Standard. Precise is optional, and earns a
  "Precise" badge. "Couldn't hear you" (noise, silence) earns nothing and costs nothing: "Let's
  try that again somewhere quieter."
- **XP, for effort:**
  - 10 for each exercise finished, plus 5 per star;
  - 20 for a practice run in Sing;
  - 50 for recording a part;
  - 200 for finishing a harmony;
  - 25 for the daily workout.

  **A daily cap** keeps it about practice, not grinding.
- **Voice levels** with names, for example 1 "First notes", 5 "Steady voice", 10 "Harmony
  singer", 15 "Choir ready", 20 "Soloist". Each level-up gets a short celebration.

### 5. The weekly goal (the gentle streak)
- **Pick a goal:** 2, 3 or 5 days a week (3 by default). A day counts after 5 minutes of practice
  or the daily workout.
- **The ring fills during the week.** Meeting it adds to **"weeks in a row"**, shown small.
- **Missing a week:** "New week, fresh start." No broken-chain message, no freeze to buy, no
  email.
- **Reminders (opt-in):** after picking a goal, "Remind me on my practice days?" with a time. Web
  push only, at most one a day, off with one tap (`docs/operations-plan.md`).

### 6. Today's workout (5 to 8 minutes, made for you)
- **One button on the map:**
  1. a warm-up in your range (sirens, scales, a held note);
  2. **one weak spot from your recent singing** (for example, "high notes a little flat", from the
     tendencies);
  3. **one review** of a lesson whose stars are low;
  4. a phrase from the song you're learning.
- **The runner:** listen, then your turn, with **Too high** and **Too low** (they move the exercise
  a semitone and remember it), and **Skip**.
- **Breathing exercises** use a timer, with no pitch tracking.
- **Free:** one workout a day. **Plus:** as many as you like, and the full exercise library.

### 7. Badges
- **About 25 to start,** each with a picture and a line:
  - **firsts:** first note matched, first harmony, first part recorded, first finished song;
  - **range:** "Hit a high G", "Low and steady (bass)";
  - **skill:** 3 stars on a unit, a Precise run, 20 in a row on pitch;
  - **habit:** 4 weeks in a row, 10 workouts;
  - **community** (with the gallery): first share, joined a choir, filled an open part.
- **Shown on Your voice,** with the ones still to earn shown as soft outlines.
- **No paid badges.**

### 8. Leagues and friends (when ready, N2)
- **Opt-in.** Turned on from Your voice ("Join a league"), never by default.
- **Weekly leagues of about 30 singers at a similar voice level,** **ranked by XP only, never by
  accuracy,** so practice wins.
  - It resets every Monday;
  - the top 5 move up a league;
  - **nobody moves down** (as gentle as a league can be).
- **The Friends tab:** the people you follow (from the gallery), ranked by this week's XP.
- **Shown:** a handle, avatar, voice level and XP. No scores, no recordings.
- **Safety:**
  - under-18 accounts see the Friends tab only, not public leagues;
  - handles and avatars are moderated as in the gallery;
  - reports and blocks work.
- **Honest XP:** XP is counted on the device, so the server caps it per day, refuses impossible
  amounts, and flags outliers for Hillel. It's for fun, with no prizes.

### 9. The report after singing ("How it went")
Kept from the first version, as the details behind the stars:
- **The summary slides up** ("★★☆ Close: 82% on pitch, +6% since last time. See details"), so you
  can sing again at once.
- **The details** (a sheet on the phone, a side panel on desktop):
  - what went well first;
  - the level word, then the number;
  - personal bests per phrase.
- **The pitch chart:**
  - the notes as bars, your line, the tolerance band;
  - **tap a note** for A and B (the expected pitch, then yours);
  - **replay with your line**.
- **Up to 3 insights** in plain words, each linking to one tip.
- **The phrase path (2e)** shows each phrase's stars.

### 10. Tips
- **At most one per session,** dismissible, with "don't show again".
- **Each is one thing to try,** with a picture.
- **Topics:**
  - breath support; head voice, chest voice and mix (explained, never detected);
  - vowels on high notes; onsets; tuning in harmony;
  - vibrato; posture;
  - looking after your voice ("stop if it hurts", with no medical claims).
- **Triggered by the tendencies,** for example flat in the high third leads to breath support.

### 11. Your voice (your profile for singing)
- **At the top:**
  - your voice level and XP;
  - the weekly goal and weeks in a row;
  - badges.
- **Your range on a keyboard,** with a suggested voice type, linking to that part in any template.
- **Plus:** history, accuracy over time, the sharp or flat tendency, range over time, and recent
  highlights. Free users see a sample of these charts, not a lock wall.

### 12. Send to my teacher (Plus, with the marketplace)
A singer invites their own teacher, who reviews takes in the marketplace's review workspace, for
free and outside its payments
([coaching-marketplace-plan.md](coaching-marketplace-plan.md), "Send to my teacher").

## Free and Plus (proposed, open to veto)

| | Free | Plus |
|---|---|---|
| The course map, stars, XP, levels, badges, the weekly goal | Yes | Yes |
| Units 1 to 7 (through "The whole song is you") | Yes | Yes |
| Unit 8 and the later units and paths | No (shown, with a sample lesson) | Yes |
| Today's workout | One a day | Unlimited, plus the full exercise library |
| The report on your latest take | Yes | Yes, plus history and trends |
| Leagues and friends (when they open) | Yes | Yes |

**Why:** a beginner can learn their first harmony start to finish for free. That's the moment that
sells Plus, so it shouldn't be behind it.

## How it works (on the device, plain JS, unit-tested)

- **`web/js/pitchtrack.js`:** the shared pitch tracker (roadmap M1):
  - YIN every 10 ms on 2048-sample windows, with clarity and loudness;
  - octave-error repair and a median filter;
  - in a worker for a take, and on `engine.inputFrame()` live.

  It's shared with the template creator, the beta and the marketplace.
- **`web/js/scoring.js`:** per-note results, vibrato, tendencies, phrase scores, insights, and tip
  triggers, as pure functions. It gains **`stars(result, strictness)`** and the **lit-note
  events** for the play-along screen.
- **`web/js/course.js`:** the course data and the rules, as pure functions:
  - which lessons and units are open;
  - stars to open a unit;
  - XP from events, with the daily cap;
  - voice levels;
  - the weekly goal;
  - badges from events;
  - the workout picker (warm-up, weak spot, review, song phrase).
- **The course is data:**
  - `web/course/units/*.json` (lessons, steps, exercises, the song challenge), with `reviewed`
    flags;
  - `web/exercises/*.json`.

  They're checked like templates. Lessons point at templates and exercises; they never copy a
  template.
- **The free course uses only free content:** a lesson may point at the 4 free songs, or carry its
  own short melody as an exercise (as unit 3 does with Ode to Joy's opening). It never points at a
  Plus song, so the free units work signed out and offline. A test checks every lesson in units 1
  to 7 against the free list.
- **Wait for me** is a new engine segment kind, `wait`:
  - it holds a note (a looped sustain of the guide) until the app calls `advance()`;
  - the app calls it when `scoring.js` sees the note held within the strictness;
  - the next segment then starts on the audio clock.
- **Live alignment:** the live line is drawn at the position minus the measured latency. With only
  an estimate, timing stars aren't awarded, and a small note says so.
- **Storage:**
  - course progress, stars, XP, badges and the weekly goal live in the `learning` store (db.js
    version 2, `docs/architecture.md`);
  - scores stay in `project.practice`;
  - the workout log goes in `warmups` (version 4);
  - all of it syncs with the account (`account.learning`, `take_scores`).

## The server side

- **Progress sync** (roadmap M4 and M5): `account.learning` holds the course progress, stars, XP
  totals, badges and the weekly goal. `take_scores` holds the scores history (Plus).
- **Leagues** (when ready, N2):
  - **tables:** `leagues` (week, tier), `league_members` (user, league, XP this week),
    `xp_events` (capped, checked);
  - **jobs:** a weekly job closes the leagues, promotes the top 5, and forms new groups by level;
  - **contract:** `leagues.current`, `leagues.join`, `leagues.leave`, `friends.leaderboard`.

## Review (misses, UX, code fit)

**Missed, now covered:**
- **Bleed:** without headphones, the tracker follows the wrong voice. It's detected, the take is
  marked unreliable, and no stars are lost.
- **Singing an octave off on purpose** (a man singing the alto line) is scored within the octave,
  and says so.
- **Unvoiced sounds and quiet frames** are ignored. Rests aren't scored, and melismas are scored
  per note.
- **Noisy rooms** give "couldn't hear clearly", never a bad score.
- **High voices:** the tracker reaches about C6.
- **Wait for me needs no timers:** the next note is scheduled on the audio clock when the tracker
  hears the current one.
- **Gamification without pressure:**
  - the weekly goal, not a daily chain;
  - XP for effort, with a daily cap;
  - leagues opt-in, ranked by XP, with nobody demoted;
  - under-18s see friends only;
  - celebrations only between exercises.
- **Cheating on leaderboards:** server caps, impossible-amount checks and outlier flags. No prizes,
  so the stakes stay low.
- **The library never locks,** even though course units do.
- **Experienced singers** can test out of early units.
- **Accessibility:**
  - lit notes also change shape (a filled bar), not just colour;
  - stars are read aloud ("2 of 3 stars");
  - the chart has a text summary;
  - the games work with reduced motion (no pops or confetti, a plain "Nice").

**UX:**
- **One home (the map) and one button a day (the workout).** Every screen leads to the next lesson.
- **Stars give a goal, and Replay is always one tap.**
- **Feedback is warm:** lit notes, words first, what went well first, one tip at a time.

**Code fit:**
- **It extends the singing UX:**
  - the Note lane is the play-along screen;
  - the Coach is the lesson steps;
  - the phrase path gets stars;
  - the session store runs it all.
- **One pitch tracker and one scorer** for the whole app.
- **Exercises reuse:**
  - `renderPart` and `renderBacking` (the chords, `docs/more-features-plan.md`);
  - the engine's `preview` and recording.
- **Lessons, exercises and tips are data,** with a review gate (`docs/coaching-review.md`, with
  `reviewed: false` until the voice teacher checks them).

## Order of work (each step committed and tested)
1. **M1:** `pitchtrack.js` and `scoring.js`, with tests on synthesized singing:
   - 20 cents flat reads as flat by 20 ± 2 cents;
   - vibrato at 5.5 Hz and ±40 cents is measured;
   - a scoop is flagged;
   - entries 80 ms late are measured;
   - octave-off takes are scored within the octave;
   - silence and noise come back as "couldn't hear".
2. **M3:**
   - `course.js` (opening rules, stars, XP, levels, the weekly goal), with tests;
   - the course map, the lesson player and the end screen;
   - **units 5 to 7** (the harmony path), using Sing and the lit notes in the Note lane;
   - placement in the first run.
3. **M5:**
   - **Wait for me** (the `wait` segment) and the full play-along screen (lit notes, combo, edge
     bar on the Stage);
   - units 1 to 4 and 8;
   - the report and chart;
   - bleed;
   - Today's workout;
   - badges;
   - tips;
   - Your voice;
   - the Plus markers.
4. **When ready (N2):** leagues and friends.

## Verification
- **`web/tool/test.sh`:**
  - the scoring tests above;
  - `stars()` at each boundary and strictness;
  - XP from a day of events, with the cap;
  - level thresholds;
  - unit opening at two thirds of the stars;
  - the weekly goal across a week with a missed day (no penalty);
  - badges firing once;
  - the workout picks the weakest spot from a synthesized history.
- **Flow scripts** (`web/tool/cdp.py`, muted, phone and desktop, a synthesized voice through
  loopback):
  1. the first run places a beginner in unit 1, and "I've sung harmony" offers the test-out;
  2. a lesson: Learn, then Try with Wait for me (the music waits until the synthesized voice finds
     the note), then Play it through for stars, then the end screen with XP;
  3. a take 30 cents flat on the high notes gets 2 stars, "a little flat on the high notes", and
     the breath support tip;
  4. the weekly goal ring fills over three simulated days;
  5. bleed shows the headphones banner;
  6. a unit opens after enough stars, while the library stays open throughout.
- **By Hillel and a voice teacher:**
  - play units 1 to 5 as a beginner on an iPhone;
  - judge whether the stars feel fair and the celebrations feel warm, not childish;
  - check every lesson, exercise and tip.

---

[Back: Operations](operations-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Pricing](pricing-plan.md)
