# Plus courses: the build plan

Status: reviewed, for the coordinator and Hillel (courses agent, 2026-10-29). Nothing here is
built yet. The courses are listed in [plus-courses-plan.md](plus-courses-plan.md), their outlines
and facts in [plus-courses-content.md](plus-courses-content.md), and the two headline courses in
[vibrato-plan.md](vibrato-plan.md) and [course-review-plan.md](course-review-plan.md), section 6.
This plan says how Plus courses plug into the app as it is, what "educational and fun" means here
in things we can build and test, and the first course, Vibrato, lesson by lesson. A vocal-teacher
review and a game-design review were folded in (section 9 lists what changed).

Back: [Plus courses](plus-courses-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs)

---

## 1. The decisions in short

| # | Decision | Why |
|---|---|---|
| 1 | **Vibrato is the first Plus course; Counterpoint is the second.** | Vibrato is one voice over a drone, its measurement is half built (`vibrato()` in scoring.js gives rate and width), and `8-vibrato` already points to it. Counterpoint needs partner lines, answers, listen-and-choose and new templates (the course review's M1, M2, M4, M5); Vibrato builds listen-and-choose and the course plumbing first, so Counterpoint starts on them. |
| 2 | **A Plus course is the free course's shape: a JSON file of units and lessons**, played by the same lesson view, exercises, stars, XP and course map. | One engine, one look, and every fix to the free course reaches the Plus ones. |
| 3 | **Lesson 1 of every course is free** (Hillel, 2026-10-28); the rest need Plus. | One rule, "Try the first lesson free". |
| 4 | **Plus courses are hidden from everyone but staff until Plus opens** (the coordinator, for Hillel). They ship to `main` and the beta, visible only to an account with `staff` set, and later to everyone through a `plus_courses` dark feature. | Hillel can sing every lesson on the beta as admin; nobody else sees them, and launch is one switch. |
| 5 | **Each course keeps its own progress** (`progress.courses.<id>.lessons`), beside the free course's `progress.lessons`. XP, levels, the weekly goal, practice days and badges stay one per learner. | "Start the course over" (`account.learning.reset`) empties only `lessons`, so it never wipes a Plus course; one learner, one level. |
| 6 | **New lesson types live in their own files**, mounted by the lesson view through one small switch. | Course content and new lesson types are the courses agent's; the lesson view is the frontend agent's, and it changes by a few lines. |
| 7 | **The course's songs are public-domain tunes transcribed as steps**, sung over their chords, as the free course's scored song lines are. The last challenge is The Water Is Wide, not Danny Boy. | The course needs nothing outside itself (no Plus song, no template from the account), and every line can be set in the learner's key. Danny Boy spans a twelfth and peaks high. |

## 2. How Plus courses plug in

### 2.1 The catalogue and the files

- `web/course/plus/courses.json`: the list. Per course: `id`, `title`, `line` (one sentence),
  `colour` (a part colour), `visual` (a Learn picture for its card), `file`, `status`
  (`live` or `soon`), and `minutes` (the whole course, roughly). Order is the order shown.
- `web/course/plus/<id>/course.json` (first `web/course/plus/vibrato/course.json`), with any course-only assets beside it,: the same shape as `course.json`
  (`id`, `version`, `reviewed`, `title`, `units[]`, each unit with `lessons[]`, each lesson with
  `steps[]`). Lesson ids carry the course's prefix (`vib-1-swing`), so an id is unique across every
  course and `/learn/<lesson id>` keeps working unchanged.
- Words in the course files are `{ "en": ... }` objects, as in `course.json` (read with
  `localized()`); the chrome (Try the first lesson free, Plus, Restart this course) goes in
  `en.json` under `courses.*`.
- The course files are public static files, as `course.json` (with its Plus unit 8) already is.
  The gate is what the app lets you do, not a secret. Plus songs still never ship in the app's
  files, and this course uses none.
- `web/js/courses.js` (new, pure, unit-tested): loads the catalogue, finds a lesson in any course,
  and answers the gates: `courseVisible(course, account)`, `lessonOpen(course, lessonIndex, progress,
  { plus })`, and the next lesson in a course (course.js's rules on the course's own progress).
- Every new file joins `sw.js`'s offline list.

### 2.2 Where they show

- **The Learn hub (`/learn`)** gains a "More courses" band under the free course's map: a card
  per visible course with its picture, line, progress ("3 of 17 lessons") and the button: Start
  (lesson 1, for anyone), Continue (started, with Plus), or Try the first lesson free (free plan).
- **A course's page (`/learn/c/<id>`)**: the free hub's layout (Up next, the course map) for that
  course, in its own colour, with the not-a-teacher line and Restart this course. The week, the
  level and the workout stay on `/learn` only.
- **The home's course card** keeps pointing at the free course (unchanged for now).
- **The free `8-vibrato`** ends with "There's a whole course on this" and a link to Vibrato (shown
  only where Vibrato is visible).
- **The public site** (`/courses/vibrato`, the marketing agent's) says Coming soon until launch.

### 2.3 Gating

| Who | Sees the courses | Lesson 1 | Lessons 2 on |
|---|---|---|---|
| Signed out or free, before launch | No | No | No |
| Staff (`account.me.staff`), any plan, before launch | Yes | Yes | Yes, so Hillel can try every lesson |
| Free plan, after launch | Yes | Yes | The Plus moment |
| Plus, after launch | Yes | Yes | Yes |

- Inside a course, units open as in the free course: two thirds of the unit before's stars.
  A Plus course never waits on the free course, but needs your comfortable note: lesson 1 finds it
  first (the existing `home` step) when it isn't known, and skips that step when it is.
- A locked lesson shows its title and "Part of Plus. The first lesson is free." with See Plus
  (`/plus?from=plus_course`). Never mid-take, never a pop-up, as every upgrade moment.
- The free-plan view is checked by the flow tests on the mock (`?api=mock` with a free account),
  not by a switch in the app.

### 2.4 Progress, stars and sync

- `progress.courses = { vibrato: { lessons: { 'vib-1-swing': { stars, bestPercent, at } } } }`.
  Missing means not started. No `version` bump: older apps ignore the key.
- `finishLesson` takes the lesson's course and writes to the right place; XP, practice time and
  the week count as for any lesson (the daily XP cap still holds).
- `mergeProgress` (course.js) merges `courses` lesson by lesson, best stars and best percent, as it
  does `lessons`.
- **Restart this course** (a styled confirm naming the course) clears only `progress.courses.<id>`,
  through an ordinary `account.learning.update`.
- History: `scores.create` already takes `lesson: "<lesson id>.<step>"`; nothing changes.
- **Size:** about 70 bytes a lesson; twenty courses of twenty lessons is about 28 KB, inside the
  256 KB limit.

### 2.5 What needs the other agents

| Change | Owner | Kind |
|---|---|---|
| `account.learning.reset` empties only `lessons` and `testedOut`, and `undoReset` brings back only those, so `courses` is never touched: tested, and said in the contract | backend | Done (eededf17) |
| A `plus_courses` dark feature (`config/flags.php`), and `account.show`'s `plusCourses: boolean` (true when it's on, or for staff) | backend, contract | Additive, before launch. Until then the app reads `staff`. |
| `billing.checkout`'s `moment` accepts `plus_course` | backend, contract | Additive |
| `learn.js`: find lessons in any course; mount the new step kinds; the gate; the course's colour; keep the step's take to play back | frontend (I propose the lines) | About 30 lines |
| `course-map.js` and `views/course.js`: take a `course` prop (default the free course); the More courses band | frontend | Small |
| `stores/learning.js`: load the catalogue and courses; `finishLesson` per course; restart a course | frontend | Small |
| `course.js`: `mergeProgress` merges `courses` | frontend | A few lines and a test |
| `synth.js`: an optional per-note `vibrato: { hz, cents, after, jitter }` for the guide (straight, wobble, a delayed bloom, a little natural unevenness) | frontend | Small, tested |
| `/courses/<slug>` flips from Coming soon at launch | marketing | At launch |

Deferred until the course has been sung on the beta: the workout bringing back Plus lessons, and
the home card following a Plus course.

## 3. What "educational and fun" means here

These are rules each lesson is checked against before it lands.

### 3.1 Teach, then practise

Every lesson runs **Why, Hear, Try, Do, Hear yourself**:

1. **Why** (one Learn step): one or two short lines on why this matters, with a picture, and the
   safety line where it applies. No lecture.
2. **Hear**: the guide voice models it first, and where it helps, the other version beside it
   ("This is a wobble. This is a vibrato.").
3. **Try**: an unscored first go, where nothing can fail.
4. **Do**: the scored step.
5. **Hear yourself**: the result plays your take back with your line drawn over the target, and
   says what we measured, plainly, with one thing to try next: "Even: 5.6 a second, about plus or
   minus 30 cents. Your waves were all the same size." or "Straight for 3.1 of 4 seconds. The
   wave came in near the end: keep the breath slow there." **Never vague praise.** What we can't
   measure (the jaw, the throat, ease) gets a self-check instead: "Rest a fingertip on your chin.
   Did it move?"

Facts from the content doc go on end screens where they fit the lesson (there are four checked
vibrato facts, not one per lesson).

### 3.2 Short, one skill, honest stars

- 2 to 5 minutes a lesson, one skill each. `lessonMinutes` must say 5 or less, or the lesson splits.
- Stars mean the skill got better, by its own measure: straight-tone lessons on straightness, wave
  lessons on evenness within the band, the bloom on when the wave starts. Pitch is always judged on
  the wave's centre; vibrato never costs pitch.
- **No credit for wider.** A wave wider than its band earns nothing extra, and wider than one and
  a half times the band says "Smaller, never harder."
- **Natural vibrato is never demanded.** It shouldn't be forced, and a beginner may not have one
  yet. Wherever a lesson hopes for one (units 3 and 4), a steady, easy straight tone can still earn
  every star, the wave part says "not yet", and the vibrato is described when it comes.
- The end screen says what the stars measured (the existing `measures` line).
- The first lesson scores generously: a first try earns two stars for a fair attempt.

### 3.3 Variety

Each unit mixes at least three of these:

| Activity | What you do | Built from |
|---|---|---|
| **Sing over a drone** | Hold a note, straight or easy, against a steady floor | `play` with `drone` (exists) |
| **Trace the wave** | The target wave scrolls toward you as a ribbon; your pitch line rides it | `wave` (new) |
| **The ghost wave** | Trace a wave that fades out, and keep going on your own | `wave` with `fade` |
| **Listen and choose** | No mic: hear two clips, tap the answer (straight or wave; wobble or vibrato) | `choose` (new, the review's M5) |
| **Echo** | The guide holds a note, straight or with a wave; you sing it back the same way | `wave` with `turn: true` |
| **Call and response** | The guide sings straight or wavy notes; you answer each the same way | `wave` with `turn: true` and several notes |
| **A real song moment** | A long note of a real tune, straight or blooming, over its chords | `play` with `chords`, the tune as steps |
| **Unit challenge** | Earlier skills together, on a real phrase | the lesson flagged `challenge` |

### 3.4 Real songs and recognisable moments

Public-domain tunes, set in the learner's key so the top note is at most a fourth above their
comfortable note:
- **Amazing Grace**, first line, straight (slow, long notes, about a sixth).
- **Shenandoah**, the held "a-way" of the last line, free (its long notes suit a natural wave).
- **The Water Is Wide**, a line sung straight and then free, and the last challenge with a
  blooming long note.

Straightness and the bloom are judged only on notes a second or longer.

### 3.5 A little delight, never childish

- **Hear yourself** (3.1) is the main reward: your voice, with your line on the ribbon.
- **Then and now**: each unit's challenge shows your first lesson's wave beside today's, and plays
  both. The first lesson's take is kept on the device only.
- **The cycle combo** counts even waves in a row on the steered drills ("12 even waves"). It's off
  wherever the wave should come by itself (unit 3, the bloom), where it would reward forcing; there
  it counts seconds of steady centre instead, capped at 8 seconds.
- **The reveal**: the first time the app is sure it heard a natural vibrato (a held note with no
  target wave, even from cycle to cycle, at a natural rate), it says so with your line drawn large:
  "That's vibrato. Yours: 5.6 a second." Once, with the special sound. Never on a steered drill,
  and never on an uneven wave.
- **Your vibrato**: a portrait, not a grade. Your rate and width as a dot, with earlier readings as
  smaller dots, over a soft band "where most voices are" (4.5 to 7.5 a second). Rate is mostly
  personal; the card says width is what you shape. It fills in from any held note in any lesson of
  the course, so it's never a dead end, and it shows on Your voice.
- **"You just did that"**, about five in the whole course: your first straight 8-second note, your
  first natural vibrato, your first bloom, finishing a unit's challenge. Plain words, the existing
  celebration.
- **Shareable**: the existing Share result card works on these lessons too.

### 3.6 Spaced review

- **Each unit's challenge brings back an earlier unit's skill** (unit 4's needs unit 1's straight
  tone; unit 2's challenge holds unit 1's steady centre between waves).
- **Then and now** looks back at the start of the course.
- **Remember this?** (built after units 1 and 2 have been sung): a 20-second personal-best try at
  an earlier skill ("Last time: 4.2 s straight. Beat it?"), only on the first lesson of a sitting,
  only when that skill is a day or more old, with Skip warm-up always there.

### 3.7 Safe singing

- The course page and lesson 1's Why card say it once, briefly: vibrato comes from steady breath
  and a free throat, never from shaking the jaw, tongue or belly or squeezing the throat; sing at a
  medium volume, in the middle of your voice; stop if anything tightens, tickles or hurts; don't
  practise when hoarse or ill, and see someone if hoarseness lasts more than two weeks. The course
  page also says no app replaces a teacher.
- **Vocal tremor**: a shake at 4 to 7 a second can be a tremor, not vibrato, especially if it's
  uneven. We never celebrate an uneven wave, and the course page says: "If your voice shakes when
  you speak too, that isn't vibrato. A doctor can check it."
- **A safety line on every lesson in unit 2**, on the vowels-and-places lesson and on the long
  holds: "Tiny slurs between two notes. Jaw and belly still."
- **Steered wave drills are capped at about 5 minutes a sitting**; after that, the course suggests
  an easy lesson or a rest. After 15 minutes of singing in one sitting, the end screen suggests a
  rest and some water.
- Wave widths never go past plus or minus 50 cents, and every exercise sits around the learner's
  comfortable note, never at the edge of their range. Holds are at most 8 seconds.

## 4. The first course: Vibrato

Four units, 17 lessons, about 3 minutes each, under an hour in all. Every exercise is in steps
from the learner's comfortable note, at a gentle tempo (60 unless said), over a drone unless it
says otherwise. A lesson's last scored step gives its stars, as in the free course. Widths are
written as plus or minus cents (half the full swing, as scoring.js measures them); the words on
screen describe the whole swing ("a semitone from top to bottom").

### Unit 1: Swing and settle

Ease first, then a straight line: a vibrato is a wave around a steady, easy centre.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **vib-1-swing: Swing your voice** (the free taste) | A playful pitch swing, then an easy note | Why (you're about to see your voice draw a wave; safety in one line); Hear the guide swing on a hum; Trace one continuous ladder on "mm" or "oo", soft: 2 a second at ±50, 3 at ±40, 4 at ±30 (about 12 seconds); then one easy long hum, 6 beats, no target; Hear yourself: your take over the ribbon, the numbers, and the reveal only if the long hum had a natural vibrato. Ends: "A real vibrato isn't steered. It grows from an easy, steady note. Next: the easy note." | The easy hum at the end: steady and held (the swing is shown, not starred) |
| **vib-1-easy: The easy long note** | A relaxed held note, at a medium volume | Why; a sigh into "ah", 6 beats over the drone; the fingertip-on-chin self-check | A steady centre and the length held |
| **vib-1-laser: A calm, straight line** | A straight tone that's light, not a held throat | Why (chords ring on it); Hear straight beside wobbly; hold 4, 6, then 8 beats | Seconds held straight (wave under ±12 for full stars, under ±20 for one; the centre drifting under 20 cents), ignoring the first 250 ms and last 200 ms |
| **vib-1-hear: Hear the difference** | Tell a straight tone from a wave, and a wobble from a vibrato | Listen and choose, 6 rounds (straight or wave first, then wobble or vibrato, then a bloom) | The rounds heard right first time |
| **vib-1-challenge: Straight through a line** | A straight tone across a phrase | Amazing Grace's first line, straight, over its chords | Straightness on the long notes, and pitch |

### Unit 2: Steer the wave

Deliberate pulses, slow to quicker, wide to narrow: Miller's slow oscillation, as tiny slurs
between two notes. Every lesson carries the safety line and a self-check (chin, throat, belly).

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **vib-2-ladder: Slow to quicker** | An even wave at 2, 3, then 4 a second | Trace each rung; a fifth rung at 5 a second is there to try, unscored | Evenness at any rate from 3 to 4 (rate within ±0.4 a second at 2 and 3, ±15% above) |
| **vib-2-narrow: Smaller waves** | Same speed, less width | At 4 a second, from ±40 to ±20 | Staying inside the band, evenly (nothing for wider) |
| **vib-2-echo: Echo the guide** | Hear a wave and copy it | The guide holds a note straight, or with a wave at 3 or 4 a second; you echo each, 4 rounds | The kind and rate matched |
| **vib-2-challenge: Wave a tune** | Waves on three held notes, a steady centre between | do, mi, so held, a wave on each, straight on the steps between | Evenness across the three, and the centres on pitch |

### Unit 3: Let it happen

Natural vibrato, from ease. The stars reward a steady centre, easy holds and no tremble, which we
can measure; a natural wave is described, and celebrated when it comes. The cycle combo is off.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **vib-3-ghost: The ghost wave** | From steering to letting go | Trace at 4 a second while the ribbon fades out over 2 seconds; keep the note going and let it settle on its own | A steady centre after the fade; a wave that carries on or a calm straight tone both earn the stars |
| **vib-3-vowels: Vowels and places** | The same ease on oo, oh and ah, low to high in your comfortable range | Three held notes a third apart around your note, one vowel each | Steadiness on all three, no tremble; the result names the vowel with the most even wave |
| **vib-3-yours: Your vibrato** | Measure your natural vibrato | Three long notes; the Your vibrato portrait | Three easy, steady holds (all three stars possible with no vibrato found) |
| **vib-3-challenge: The rolling river** | Let it happen in a song | Shenandoah's last line, the held "a-way" free | Pitch, and steadiness of the long note |

### Unit 4: Choose it

Control: straight or wave, and when the wave starts, by style. A learner with no natural vibrato
yet still earns stars on the straight parts; the wave parts say "not yet".

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **vib-4-bloom: Straight, then bloom** | The delayed vibrato | Hear it; hold 8 beats: straight for 2 (2 seconds), then let the wave in | Straight for at least 1 second, then the wave settled at least 1.5 seconds before the end |
| **vib-4-switch: On and off** | Choose on each note | Call and response: the guide sings notes of 2 seconds or longer, straight or wavy; you answer each the same way | Each note's kind matched |
| **vib-4-style: One line, two styles** | Straight for a choir, free for a folk song | The Water Is Wide's first line straight, then again free | Straightness on the first, ease on the second |
| **vib-4-challenge: The water is wide** | Everything, in one phrase | Two lines of The Water Is Wide: straight starts, the long notes blooming; then and now | Pitch, the straight starts, and the bloom where it comes |

### What the app measures, and how

A new pure module, `web/js/vibrato.js`, tested on synthetic voices (`sing()` in
`tests/fixtures.js` makes vibrato, scoops and noise, and gains per-cycle unevenness) and on the
recorded guides. Every threshold is a named constant in one place, tuned on real voices on the beta
before stars depend on it.

- **Per held note** (judged from 0.8 seconds, three cycles at 4.5 a second): the centre (median),
  rate (from peaks and troughs), width (± cents), evenness (how much cycle lengths and sizes vary),
  onset (seconds until the wave settles), and a kind:

| Kind | Rule |
|---|---|
| `straight` | The regular wave (3.5 to 8 a second) under ±12 cents, the centre drifting under 20 |
| `vibrato` | 4.5 to 7.5 a second, ±15 to ±120 cents (over ±80 described as "wide"), cycle lengths varying under 20% |
| `wobble` | Under 4.5 a second, or wider than ±100 cents at under 5 a second |
| `tremble` | Over 7.5 a second, or over 7 and under ±20 cents (after a take only, never live) |
| `uneven` | Cycle lengths varying over 20%: never called vibrato, never celebrated |
| `unclear` | Too short, or not heard well enough |

- **Steered drills** are judged against their own target (rate and band), not the kinds above.
- **The bloom**: delayed when the note is straight for at least 1.0 second (or 40% of it) and the
  wave settles at least 1.5 seconds before the note ends.
- **Live**: the same over the last second, at about 50 readings a second, for the lane and the
  combo. Octave slips fold back to the centre's octave.
- `scoring.js`'s `vibrato()` stays as it is; the frontend agent can switch to the new module later.

## 5. New lesson types

| Kind | What it is | Files (all new) |
|---|---|---|
| `wave` | The trace lane (below), then Hear yourself | `components/wave-lane.js`, `vibrato.js` |
| `choose` | Listen and choose: two clips, tap the answer, a one-line why after each | `components/choose.js` |
| `review` | Remember this? (later, section 3.6) | `courses.js` picks it |

Existing kinds (`learn`, `try`, `play`, `record`, `home`) are reused as they are. `play` exercises
gain an optional `score: 'straight' | 'bloom'`, read by the new module, and a guide note may carry
`vibrato`.

**The trace lane**, so it plays like a game and not a test:
- Drawn on a canvas, about 1.5 seconds of the target ahead, the "now" line about 30% in from the
  start edge (mirrored for right-to-left).
- The target is a soft ribbon as wide as the tolerance, not a thin line. Where you ride it, it
  fills with your part colour; missed stretches stay glass, never red.
- Each peak and trough is a small gem that pops when you reach it (scaled to 1.15 for 120 ms),
  with an optional soft blip at its pitch, scheduled on the audio clock.
- Every 8 even cycles the ribbon glows; the combo shows from 3.
- **The pitch tracker's lag is measured and taken off**, so your line sits on the wave rather than
  trailing it.
- Failure is gentle: in Try, silence pauses the lane (as Wait for me does); in the scored take,
  silence leaves a gap in your line. After two weak takes it offers "Try it slower" (10% slower).
  A take never ends early.
- Everything that sounds (the drone, the guide, the blips) is scheduled on the AudioContext clock
  through the engine; the screen only reads the position and the mic.

**Clips for listen and choose**: the guide voice with the new per-note vibrato and a little natural
unevenness to start, replaced by short recorded clips from a real singer when we have them (a beta
singer brief: eight 3-second notes, straight, vibrato, a wobble, a bloom). No tremble round: it's
too hard to hear, even sung.

## 6. Building it

Each step lands green on its own: `web/tool/test.sh` on its own port, the leak check, phone and
desktop screenshots in light and dark sent to the coordinator first, a ux-changes line, and
decisions logged.

1. **Plumbing, behind staff:** `courses.json`, `courses.js` (tests), the frontend's agreed lines
   (lessons in any course, progress per course, the course prop on the map and hub, the More
   courses band, the gate), and Vibrato's unit 1 as data with `learn` and `play` steps only.
2. **The measure:** `vibrato.js` and its tests; the guide's per-note vibrato (frontend's synth).
3. **The `wave` lane** and Hear yourself, then unit 1's swing, easy note and laser in full.
4. **`choose`**, then Hear the difference and unit 1's challenge. Review pass.
5. **Unit 2.** Review pass.
6. **Unit 3**, the reveal and the Your vibrato portrait (Your voice shows it, with the frontend).
7. **Unit 4**, with then and now. Review pass of the whole course.
8. **Launch-ready:** the `plus_courses` flag and `plusCourses` field (backend), the Plus moment,
   the free `8-vibrato` link. Then Remember this?, once units 1 and 2 have been sung on the beta.
   Hillel tries it on the beta and says go.

Every lesson is `reviewed: false` until the voice teacher's check (vision principle 9).

## 6a. How to build a course (for every course agent)

Four course agents build the Plus courses: **courses** (the lead: Vibrato, the lesson engine's
shared step types, the quality bar), **courses-skills**, **courses-harmony** and **courses-styles**
(the coordinator's list, docs/launch-plan.md). Each works in its own worktree, commits as
`STACKAPELLA_AGENT=courses`, and lands as section 6 says.

### Files

**Since the switch (2026-10-29), the courses and songs live in their own public repo,
`hillelcoren/stackapella-courses`** (docs/repos-plan.md): `course/` there is what main serves as
`web/course/`, pinned in main's `.do/content.lock`. Content (a course file, the catalogue, clips.json,
the format) is edited only in your own clone, `~/Code/content-<you>` (author `Hillel Coren
<4629496+hillelcoren@users.noreply.github.com>`, never the gmail address), checked there with
`python3 -I tools/course_check.py`, tried against the app with `CONTENT_DIR=~/Code/content-<you> sh
server/tool/content.sh link` from your main worktree (it copies: run it again after each edit), and
handed in as a commit hash to the coordinator, who pushes it and bumps main's pin. Never push. Code
(step types, scorers, measures, the lesson view) stays in main, landed as before. In the table below,
read `web/course/...` as `course/...` in the courses repo.

| What | Where | Who edits it |
|---|---|---|
| The course itself | `web/course/plus/<id>/course.json` (and its own assets in that folder), the shape of `course.json` (units, lessons, steps); lesson ids `<prefix>-<unit>-<name>` | The course's agent |
| Its catalogue entry | `web/course/plus/courses.json`: `id`, `prefix`, `file` (`<id>/course.json`), `status` (`soon` until every lesson plays, then `live`), `colour`, `visual`, `minutes`, `title`, `line` | The course's agent, its own entry only |
| Its outline and reviews | `docs/courses/<id>.md`: the units and lessons, what each scores, the reviews and what they changed | The course's agent |
| Its own measures | `web/js/<measure>.js`, pure, unit-tested (as `vibrato.js`, or skills' `pitch-skills.js`) | The course's agent |
| Its tests | `web/tests/<id>.test.js` (listed in `tests/index.html`), and `tests/flows/course_<id>.py` with the shots | The course's agent |
| Lesson words | In the course file, as `{ "en": ... }` | The course's agent |
| A step type's words | `web/locales/en.json`, under the step's own key (`wave.*`, `choose.*`, `selfcheck.*`) | The step's owner |
| Step types | `web/js/components/<step>.js`, registered in `web/js/lesson-steps.js` | Its owner (table below); the lead registers it |
| The course format | `web/course/format/` (`course.schema.json`, `steps.json`: every kind and field, and which app plays it), checked by `web/tool/course_check.py` in the web tests | The lead; a new kind or field is added to `steps.json` with its line, or the tests fail. When the courses move to their own repo (docs/repos-plan.md), the checker lives twice (main's `web/tool/` and the courses repo's `tools/`): change both together |
| Play scorers (a `score` on a `play` step) | The measure module, registered in `web/js/lesson-scores.js` | The course's agent; the lead registers it |
| The lesson view, exercise, scoring, synth, course map | `learn.js`, `exercise.js`, `scoring.js`, `synth.js`, `course-map.js` | The frontend's: every change agreed with it first, and every line told |

Every new file goes in `sw.js`'s offline list. No course edits `course.json` (the free course).

### Who builds which step type

| Type | What it is | Built by | Lives in |
|---|---|---|---|
| `wave` | The trace lane: a ribbon to ride (a steered wave, a straight line, a bloom, a free hold), then Hear yourself | courses (built) | `components/wave-lane.js`, `vibrato.js` |
| `wave` with a `path` target | Any contour: sirens, roller-coaster lip trills, range slides, slides and fall-offs | courses (next) | the same |
| `choose` | Listen and choose: clips from the guide voice, tap the answer, a one-line why | courses (first) | `components/choose.js` |
| `selfcheck` | What we can't hear yet: hear the model, record yourself, play both back, answer the self-check questions; "We don't score this yet" | courses (first) | `components/selfcheck.js` |
| Range sweep | Slide low to high; your span drawn | courses-skills | `components/range-sweep.js` |
| Stave | The line on a stave before you sing it (reusing the Staff view's drawing, read-only) | courses-skills | `components/stave-step.js` |
| Partner lines `with`, answers `answer`, any-of-these `choices`, per-note `cents` | The course review's M1, M2, M4, M6: extensions of the `play` exercise, so the free course can use them too | courses-harmony, agreed with the frontend | `exercise.js`, `scoring.js` (the frontend's), plus harmony's own measures |
| Landing, lock, drift and memory scoring | `score: 'landing' \| 'lock' \| 'drift' \| 'memory'` on a `play` step | courses-skills | `pitch-skills.js`, registered in `lesson-scores.js` |
| A silent guide (`guide: false`) and heard-only notes (`listenOnly`) | Cold starts and distraction notes, for Perfect your pitch | courses-harmony, folded into its `exercise.js` change (courses-skills sends the spec) | `exercise.js` |
| Flip, slide, key drift, legato | Pure measures for yodels, scoops and fall-offs, unaccompanied lines, gaps between notes | courses-styles | its own module, registered in `lesson-scores.js` |
| The guide's glides | The guide sings a contour (a siren, a scoop, a flip): a per-note pitch bend in `synth.js` | courses, agreed with the frontend (with the `path` target) | `synth.js` |
| A whistle's range | The tracker hearing up to about 3 kHz when a step asks (`whistle: true`), so voices are unchanged; a find-your-whistle-note step | courses-styles, agreed with the frontend | `pitchtrack.js`, `listen.js` (the frontend's) |
| A looping step | Live looping's record-and-loop | courses-styles, agreed with the frontend, last | the studio (the frontend's) |
| Loudness | Dynamics and belting volume: the browser's auto gain flattens it | Not yet: self-check until it's measured honestly | |

Only one agent edits a frontend file at a time: the lead adds the two registries first (one
change in `learn.js` and one in `exercise.js`), then courses-harmony's exercise changes go in.
Anyone needing a new type or a change to a shared one asks the lead first.

### A lesson's shape

- **Why, Hear, Try, Do, Hear yourself** (section 3.1): one Learn step with a picture and at most
  three cards; the guide models it; an unscored try; the scored step; feedback in numbers we
  measured, never vague praise.
- **2 to 5 minutes**, one skill. A lesson's stars come from its **last scored step**, and the end
  screen's `measures` line says what they measured.
- **Variety:** each unit mixes at least three activities (section 3.3) and ends in a challenge
  (`"challenge": true`) that brings back an earlier unit's skill.
- **Safety:** a one-line note wherever the voice could strain; holds around the comfortable
  note; nothing at the edge of the range.
- **Lesson 1 is the free taste:** it must sell the course in about three minutes, and end on
  something the learner hears themselves do.

### What we can't hear yet: self-check courses

Belting, diction, singing in another language, beatboxing, vocal percussion, overtone singing,
character voices and accents score what we can (pitch, timing, length) and are honest about the rest.
Their lessons model it, let you practise, record you, play you back beside the model, and ask
two or three self-check questions ("Did your jaw stay loose?"). They say "We don't score this
yet", never a made-up score: a lesson of only `selfcheck` steps earns its stars for practising,
and says so.

### Content rules

- **Public domain only** for tunes and words (check the composer's and lyricist's dates; when in
  doubt, pick another). Our own arrangements and exercises are ours.
- **No living famous people** named as models; styles are described.
- **Facts** only from the content doc's "checked" lists, or checked again with a source.
- No em dashes; every word through the course file or `en.json`.
- **No stars in course words**: serious mode hides them. A step's line says what it's "Scored on …", a threshold is "the top score needs …". The course check fails on "star" or "stars" in any course word.
- **One house pattern for a step's line**: "Scored on [what's measured]." then at most one listen-back question. A fact is the lesson's own `fact`, the lesson end's "Did you know?" card, with its source. No "Next:" teasers. A choose step says "Scored on the rounds you got right first time." Facts: one or two a unit, each in one course only (the free course's win). A term the free course doesn't teach (a half step, minor, cents) is said in plain words where a course first uses it. The full safety line once early, and a short one where a step climbs or pushes.

### The review step

1. Before building, write the course's outline in `docs/courses/<id>.md` and have it reviewed:
   a voice-teacher reviewer (pedagogy, safety, numbers) and a game-design reviewer (fun, variety,
   the free lesson). Fold in what they find, list it at the end of the outline, and send the lead a
   short summary.
2. After each unit: your own pass against this section's checklist, then fix what it finds.
3. Before a course goes `live`: the lead reviews it against the quality bar.

### Landing and shots

- Ports: the ones the coordinator's brief gave you (courses uses 8297).
- Each code landing in main: rebase on main, then `sh server/tool/content.sh link` (main has no
  courses or songs without it), `web/tool/test.sh` on your port, your course flow and the
  `plus_courses` flow, the server suite through `server/tool/test.sh` when `web/locales`, `web/course` (the server reads the catalogue for the site's course pages) or
  `server/` changed, `sh server/tool/leak-check.sh`, then the fast-forward. Run the tests
  again after the last rebase. Don't pipe a step into `tail` when deciding whether to go on: it
  hides the step's failure.
- **Shots:** your course flow takes the course page, lesson 1 and each new step type, at phone and
  desktop, in Warm light and Stage dark (`OUT=~/Code/shots-review/<id> PORT=<yours>
  tool/flows.sh course_<id>`; see `tests/flows/plus_courses.py`'s `picture()`). Send the paths to the
  coordinator before landing.
- A `docs/ux-changes.md` line for each user-facing change; decisions in `docs/decisions.md`.

## 7. Then Counterpoint

The course review's units 9 to 11 become the Counterpoint course (with its free lesson 1, the
"Row, Row, Row Your Boat" round from the content doc). It reuses everything above (`choose`, Hear
yourself, the catalogue, the gates) and adds partner lines (M1), answers (M2) and any-of-these
(M4), plus the new templates (Hey Ho, Tallis's Canon, the partner songs).

## 8. Hillel's answers, and the clips to record later

- **The listening games use the practice voice for now** (Hillel, 2026-10-29: "Practice voice for
  now"), with the per-note vibrato and a little natural unevenness.
- **The free `8-vibrato` stays as it is**, with a link to the course added (Hillel, 2026-10-29).

**The eight clips to record later**, by one real singer, each a single held "ah" on a comfortable
note of about 3 seconds, dry, no reverb, in a quiet room:

| # | Clip | How to sing it |
|---|---|---|
| 1 | Straight | No wave at all, light and calm |
| 2 | Natural vibrato | Your own easy vibrato, at a medium volume |
| 3 | Wide vibrato | Easy, but a clearly wider wave than clip 2 |
| 4 | Wobble | A slow, wide wave, about 3 a second |
| 5 | Bloom | Straight for about 1.5 seconds, then let the vibrato in |
| 6 | Straight, higher | As clip 1, a fourth higher |
| 7 | Vibrato, higher | As clip 2, a fourth higher |
| 8 | Steered pulses | Deliberate, even slurs at about 3 a second |

Until they exist, the practice voice plays each with the matching settings.

**For Yodeling and Pop and country** (courses-styles; recorded in the same session if one singer can
do both, dry, each about 3 to 4 seconds):

| # | Clip | How to sing it |
|---|---|---|
| 9 | Speaking "ho" | Low, in chest voice, like calling a friend |
| 10 | Owl "hoo" | High and light, in head voice |
| 11 | Yodel hook, clean | "yo-hee-o", low, high, low, flipping cleanly each way |
| 12 | Yodel hook, slid | The same, sliding instead of flipping |
| 13 | Downward flip | "hee-o", landing lightly at speaking volume |
| 14 | Fast roll, clean, and smeared | Low, high, low, high, low, quickly: once clean, once smeared |
| 15 | Down in the Valley, plain | The first line, no moves |
| 16 | The same, styled | With a scoop into "valley" and a fall-off on "low" |
| 17 | The same, with a slide and a cry | A slide between two notes, a small cry on one word |
| 18 | The same, breathy | A little air, quiet and close |
| 19 | The same, bright | Clear and forward, at a medium volume, not belted |
| 20 | The same, a little twang | Narrow and bright, never pressed |

The guide can sing 11, 12, 14 and 15 to 17 once it has glides and its head-voice sound; 9, 10, 13 and
18 to 20 need the real voice, because they're about tone.

## 9. What the reviews changed

From the **vocal-teacher review**: ease before the straight tone (the easy note moved into unit 1,
the laser reworded as "light, not a held throat"); lesson 1 framed as a pitch swing on a hum, not
as vibrato; unit 2 as tiny slurs with chin, throat and belly self-checks, starred on evenness at 3
to 4 a second with 5 unscored, and a 5-minute cap on drills; unit 4 never demanding a natural
vibrato; the tremor guard and the hoarseness and volume notes; corrected words (vibrato is made in
the larynx, not "when tension leaves"; "shouldn't be forced"; chords ring straight, not "choirs sing
straight"; Shenandoah's held "a-way" is on the last line); all the measurement numbers in section 4;
songs set within a fourth above your note, Simple Gifts and Danny Boy dropped.

From the **game-design review**: Hear yourself after every scored take, and then and now at the
challenges; lesson 1 as one continuous ladder ending on an easy hum, with a bridge to lesson 2;
unit 3's long holds varied with the ghost wave, and its lessons merged; unit 2's ladder merged and
an echo added; the trace lane's ribbon, gems, glow, lag correction and gentle failure; the combo off
where it would reward forcing; the reveal only when sure; Your vibrato as a portrait that fills in
from any held note; about five "you just did that" moments; Remember this? only on a sitting's first
lesson, skippable, as a personal best, and built later; The Water Is Wide as the last challenge;
cut for now: a fact per lesson, the workout's Plus review, the home card following a Plus course, a
free-view switch, live tremble, and the chord-ring listening round.
