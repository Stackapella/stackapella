# Perfect your pitch: the course outline

Status: built, all 17 lessons (courses-skills, 2026-10-29), coming soon until the lead's last look.
A voice-teacher review and a game-design review are folded in (section 8), then the build review
(section 10) and the lead's quality review (section 11). In the course file, `step` always means
semitones from the learner's comfortable note. The course's place in the list and its facts are in
[plus-courses-content.md](../plus-courses-content.md) (Singing skills, 9). How a Plus course is
built, the lesson flow and the fun and safety rules are in
[plus-courses-build-plan.md](../plus-courses-build-plan.md), which this follows.

Back: [Plus courses](../plus-courses-plan.md)

---

## 1. What it teaches, in one line

Land a note dead centre without sliding in, lock it to a drone, keep it there while the harmony
moves, and find it again from memory. It's relative pitch for harmony singers, not a promise of
"perfect pitch". The course page says so: true absolute pitch is rare and mostly set in childhood,
and relative pitch is what anyone can sharpen, and what this course trains.

- **Course id** `pitch`, lesson prefix `pp`, colour 2, picture `bullseye` (a new Learn picture).
- **4 units, 17 lessons, about 3 minutes each, under an hour in all.**
- Every note is in steps from the learner's comfortable note (do), never more than a fifth above
  it or a fourth below, at a medium volume. Holds are at most 8 seconds.
- **Solfège is taught in lesson 3** and shown with numbers beside it everywhere: do is your note
  (1), re 2, mi 3, so 5 up; la and ti below are written "la (below)" and "ti (below)".

## 2. What the app measures (and what it doesn't)

From the pitch track the app already makes, through `web/js/pitch-skills.js` (pure, unit-tested on
synthetic readings and through the real tracker in `tests/pitch-skills.test.js`). Every threshold
is a named constant, tuned on real voices on the beta before stars depend on it. Every reading is
folded to the nearest octave, so a voice an octave down is never "off".

| Scorer | What it is | Stars |
|---|---|---|
| **landing** | The centre of the first settled 150 ms window (staying within ±25 cents of itself), and how the note began: `clean`, `scoop` (from over 50 cents below, for over 60 ms), `fall` (from above) or `slide` (never settled) | Per note: full within ±25 cents, two stars' worth within ±35, none at a semitone; a scooped or fallen start keeps half. Lesson 1 is generous (full to ±35, a fair try lifted) |
| **lock** | A held note's line on a 400 ms rolling median (a natural vibrato or jitter isn't a tuning error) against the target | Each reading: within ±10 cents is full (three stars), ±15 two, ±20 one. **Only do and so lock.** Any other note in a lock step is judged on its centre, because a third tuned to a drone sits 14 cents under equal temperament |
| **centre** | The note's median | Full within ±20 cents, none at a semitone |
| **drift** | The last second's centre less the first second's (sag or creep) | Half from the centre, half from the drift: full under 10 cents, most under 20 |
| **memory** | A note sung cold, from the landing | Three stars within a semitone, two within two, one for any heard try. Within 50 and 20 cents are "You just did that" moments, not the bar |

The scorers are registered in `web/js/lesson-scores.js` (the lead's registry) as `landing`,
`lock`, `centre`, `drift` and `memory`.

**Not measured, never starred:** a stiff neck, jaw or tongue, loudness, tone, or whether you heard
the note in your head first. Those get a self-check line instead.

**Over a speaker** the app rests the drone during a scored turn so the mic hears only your voice,
which would leave nothing to lock to (and the tracker must never "lock" onto the drone itself).
So lock steps ask for headphones, worn one ear off or with a hand cupped behind the other ear,
at a moderate volume (closed headphones make people sing loud and sharp). On a speaker, the drone
plays before your turn, you hold from memory, and the result says so.

## 3. The lessons

Each lesson runs **Why, Hear, Try, Do, Hear yourself**. "Echo" means the guide sings, a beat's
gap, then you. Every scored step ends in Hear yourself, in plain words first ("a hair sharp", "a
quarter-step low", "a tone low") with the cents small beside them. After two weak takes, a step
offers **Sing it with the guide** (the guide sings with you on the next try, unscored).

**Every unit challenge opens with the same short Bullseye round** (five echoes, about 30 seconds; first, because a lesson's stars come from its last scored step),
shown beside your first one: then and now, like with like.

### Unit 1: Land it

Clean entries: think the note, breathe, start on it. A scoop is a habit, not a lack of talent.

| Lesson | The skill | Why, Hear, Try, Do | Stars from |
|---|---|---|---|
| **pp-1-bullseye: Bullseye** (the free taste) | Echo a note and see where you land | Why: landing on a note, not sliding into it, is what makes a harmony ring. Hear the guide sing a clean "dah". Try two echoes. Do: **two rounds of five echoes**, each a different note within a fourth of yours; each dart lands on the board as you finish the note. Between the rounds, one tip picked from round 1 ("You started low and slid up: think the note, then breathe"). Hear yourself: "Round 2 moved in 14 cents", and a read on your voice that leads on ("You tend to start low and slide up. The next lesson fixes that.") | landing, generous, on round 2 |
| **pp-1-scoop: Scoop or clean** | Hear a scoop, then leave it out | Why: a scoop is sliding up into a note from below. Hear: listen and choose, 4 rounds, "Scooped or clean?" Try a scoop on purpose, then a clean start. Do: four clean echoes | landing |
| **pp-1-leaps: Think it, then leap** | Clean entries after a jump, with solfège | Why: do is your note, so is five up (the steps with numbers). Hear the guide sing do-so. Try: two silent beats marked "think" before each of your entries; self-check "Did you hear it in your head first?" Do: echo do-so (up), so-do, do-mi, la (below)-do, short "dah"s with a rest between | landing on the second note of each pair |
| **pp-1-challenge: The clock chimes** | Clean entries in a tune | Hear the Westminster quarters (the hour's four changes, low so to mi). Try the first change. Do: all four on "dong", each note detached, over a quiet drone; then the Bullseye round | landing, with each start's clean share (one scoop costs a little, not a star) |

### Unit 2: Lock to the drone

Tune to a steady note: smooth when you're on it, a beating "wah-wah" when you're near. Every lesson
carries the soft-neck self-check ("Neck, jaw and tongue soft?"), because tuning effort shows up as
tension, and tension pushes you sharp.

| Lesson | The skill | Why, Hear, Try, Do | Stars from |
|---|---|---|---|
| **pp-2-ring: Hear the ring** | Hear in tune, and which way is out | Why: two notes a little apart beat; the beats slow as you get closer and stop when you're there. Hear the guide over the drone 30 cents off, then 10 off ("slower as you get closer"), then in tune. Listen and choose, 6 rounds: "Which one rings?", then "Is the guide sharp or flat?" Try: start a little under and slide up slowly until the beating stops. Do: hold do for 6 beats | lock (headphones) |
| **pp-2-fifth: The fifth and the octave** | Lock to the drone's strongest partners | Why: the fifth differs from a pure one by 2 cents, the octave by none, so both ring true here. Hear the guide on so over the drone. Try so. Do: hold so over the drone; then the drone drops an octave and you hold your own do over it (the octave lock). Self-check: "Did it get smoother when you found it?" | lock on each |
| **pp-2-home: Leave and come back** | Return to exactly where you were | Why: stepping away and back is where pitch slips. Hear do-re-do. Try it. Do: do-re-do, do-ti (below)-do, do-so (up)-do, each do held a beat | centre on the returning do's, against your first do |
| **pp-2-long: No sag** | Keep a long note and repeated notes from drooping | Why: the end of a breath and repeated notes pull you flat; slow the breath out, and "think up" on the way down. Never squeeze to stay up. Hear a sagging hold beside a steady one. Try do for 6 beats. Do: 8-beat holds on do and so, then mi-mi-mi-re-do | drift on the holds, centre on the repeats |
| **pp-2-challenge: Red River Valley** | Stay in tune through a song | The Bullseye round (then and now); then the cowboy song's first two lines at 72, with the guide, then on your own over a drone on your note (its melody runs from so below to so above, exactly the span) | lock on its long do, centre on the rest |

### Unit 3: Hold your ground

Stay in tune while other parts move: the skill harmony singers need most.

| Lesson | The skill | Why, Hear, Try, Do | Stars from |
|---|---|---|---|
| **pp-3-chords: The chords change, you don't** | A held note under changing chords | Why: when the chord changes, your note's job changes, not its pitch. Hear do held through I, IV, I, V, I. Try it with the guide. Do: hold do for 8 beats through the changes (over V it rubs: hold on) | lock |
| **pp-3-pull: Don't get pulled** | Hold against a near part | Why: a part close to yours pulls; listen to both. Hold do while the guide (quieter) moves around a third above; a 2-beat taste against a half step above; then do for 4 beats against a whole step above | drift (either way, not only toward the guide), on the last |
| **pp-3-stack: Stack yourself** | Hold a note against your own voice | Hold mi over the drone; then hold do while your own mi plays with you (`under`, headphones): a third, made by you | lock on do |
| **pp-3-challenge: The held note** | A pedal note under a hymn | The Bullseye round (then and now); then the guide sings Amazing Grace's first line over I-I-IV-I (partner line, `with`) while you hold do in two breaths, a breath after "grace" | lock, in both breaths |

### Unit 4: Pitch memory

Your comfortable note is an anchor you carry with you. Find it cold, then find others from it.
Each lesson warms up first with a siren or lip trill (no note given), then a hum.

| Lesson | The skill | Why, Hear, Try, Do | Stars from |
|---|---|---|---|
| **pp-4-cold: Your note, cold** | Sing your comfortable note before you hear anything | Why: many people start a song they know well within a semitone or two of the record's key (Levitin, 1994); your own note moves a little through the day, too. Try: a siren, then hum. Do: no sound, sing your note on "oo" when you're ready; the drone comes in and shows how close you were; three tries | memory |
| **pp-4-keep: Keep it in mind** | Hold a note through a distraction | Why: holding a note in your head while others sing is what harmony asks. Hear one round. Do: the guide sings a note, then two other notes, then it's your turn to sing the first one back; then three distractions, then four | landing on the remembered note |
| **pp-4-from: From your anchor** | Find mi and so from your note, without help | Why: one anchor gives you every note of the scale. Try do, mi, so with the drone. Do: sing do cold, then mi and so from it; the drone joins after to check you | memory on do, landing on mi and so (`cold`) |
| **pp-4-challenge: Start it yourself** | Start a tune with no note given | Sing do cold, find mi from it, and sing Ode to Joy's first line from there (mi mi fa so so fa mi re do do re mi mi re re: do to so); the drone joins at the end to show your key; then the Bullseye round | memory on do, then centre on the line |

## 4. The fun

- **Bullseye**, the course's game and its own step kind (`bullseye`, `components/bullseye.js`): the
  guide sings a note or a short group, you sing it back, and every landing is a dart. How far it is
  from the centre is how far off it landed (the gold is ±10 cents, the inner ring ±25, the outer
  ±50 and the rim a semitone; further pins to the rim, never off the board); above the centre is
  sharp, below is flat. Darts fan out sideways so each one shows (built: a time-to-land axis would
  have made the distance mean two things at once, so a slow start shows as a scoop's trail
  instead). Darts land live, as you finish each note, and are placed again from the whole take.
  Never red: a dart outside ±50 is glass. Hear yourself names each landing in plain words with the
  cents beside it. (To come: the special sound on a gold dart, once a take.)
- **The lock glow**: over a drone, the lane warms as you close in (a graded glow from ±50 to ±10,
  with a soft "higher" or "lower" while you're out), and rings when you've held ±10 for a full
  second (the glow brightens; any sound is scheduled on the audio clock).
- **Your pitch memory**: a portrait like Your vibrato's, not a grade. Each cold try is a dot (cents
  off your note), the newest largest, with "Closest yet: 9 cents". Only a sitting's first cold try
  counts (minutes after singing, a note isn't cold). It says "Usually within 60 cents" only once
  there are six dots or more.
- **"You just did that"**, four times: a gold dart in Bullseye's second round, your first
  2-second ring, "No note given. 22 cents from yours." (a cold note within 50), and finishing a
  unit's challenge.
- **Your best to beat**: replaying a lesson shows your best average landing or lock.
- **Then and now** at every challenge (the same Bullseye round, first and latest).

## 5. Safe singing

On the course page and lesson 1's Why card, briefly:
- Sing at a medium volume, in the middle of your voice.
- Pitch is fixed by breath and listening, never by squeezing: tightening pushes you sharp, running
  out of breath lets you sag.
- Keep headphones at a moderate volume, one ear off.
- Stop if anything tightens, tickles or hurts. Don't practise when hoarse or ill, and see someone
  if hoarseness lasts more than two weeks.
- Hold the phone at eye level, chin level.
- No app replaces a teacher.

In the lessons: the soft-neck self-check (neck, jaw and tongue) on every lesson in units 2 and 3;
clashes held at most 4 beats with the guide quieter than you, and the semitone only as an unscored
2-beat taste; cold starts after a siren or lip trill and a hum, never a hard attack; nothing
outside a fourth below to a fifth above your note.

## 6. What it needs that isn't built

| Need | Owner | Status |
|---|---|---|
| The five scorers | me (`pitch-skills.js`), registered in `lesson-scores.js` | Built; registered when the lead's registry lands |
| `guide: false` (+ `droneAfter`) for cold starts, `listenOnly` distraction notes | courses-harmony, in its `exercise.js` change (the lead's call: one agent in that file) | Agreed; for unit 4 (echoes run on Bullseye) |
| A guide note's `cents` offset (the beating demo) | courses-harmony's M6 | Coming |
| `choose` (scooped or clean, which one rings, sharp or flat) | the lead | Building |
| The lock lane with the graded glow | the lead's `wave` lane (a straight target), or my own over it | To agree |
| Bullseye (the live board and its result) | me, `components/bullseye.js`, the `bullseye` step kind | Built (unit 1 runs on it) |
| Sing it with the guide, after two weak takes | the lead (the engine's sibling of Try it slower) | To ask |
| Playing an earlier step's take back (Stack yourself) | the lead (the build plan's "keep the step's take") | To ask |
| The pitch memory portrait | me, with the frontend for Your voice | Later in the build |
| The `bullseye` Learn picture | me | With unit 1 |

If a shared piece isn't ready when a unit is, the lesson ships without it and says nothing about it
(for example Stack yourself waits; pp-3 has three lessons until it lands).

## 7. Songs and their status

All public domain: the Westminster quarters (William Crotch, 1793), "Red River Valley"
(traditional, printed by 1896; the melody from the song library's checked template), "Amazing Grace" (John Newton's words, 1779; the tune "New
Britain", 1829 and 1831), and Ode to Joy's melody (Beethoven, 1824; sung on "loo", no words).

## 8. What the reviews changed

**Voice teacher:** the octave lock sings your own do over a drone an octave down (an octave up was
outside the span); lock is starred only on do and so, thirds on their centre (a drone-tuned third
is 14 cents under equal temperament), the fifth's 2-cent difference named; lock on a 400 ms
rolling median with stars at ±10, 15 and 20; memory at a semitone and two (Levitin's finding
reworded), with 50 and 20 cents as moments; the scoop rule widened to 50 cents for 60 ms (the usual
scoop is 30 to 80), landing tightened after lesson 1 to ±25 for three stars and ±35 for two; drift
unified at 10 and 20 cents; the beating demo at 30, then 10, then in tune, as a listening round;
solfège taught, with numbers, la and ti written as below; the intonation traps named (repeated
notes sag, think up going down, flat is low energy, sharp is pushing); headphones one ear off and
the speaker case made honest; the unit 2 challenge written out exactly (built with Red River Valley, from the song library's checked melody, in place of Hush, Little Baby, whose versions vary); Amazing Grace's pedal in
two breaths over I-I-IV-I; Hear and Try in every lesson; a warm-up before cold starts, and only a
sitting's first cold try in the portrait; the clash lesson eased (a moving third, then a tone,
the semitone an unscored taste, the guide quieter, at most 4 beats); units 3 and 4 swapped so
memory is the capstone; the distraction grows from 2 notes to 4; eye level and "jaw and tongue
soft" added.

**Game designer:** the dartboard's axes mean something (sharp up, flat down, time to land across,
a semitone at the rim); Bullseye is two rounds with live darts, a tip between, "you moved in" and
a read on your voice that leads to lesson 2; the lock glow graded with a higher or lower cue; then
and now compares the same Bullseye round at every challenge; "Hear it first" merged into the leaps
lesson; listen-and-choose rounds for scooped or clean, which rings, and sharp or flat; Stack
yourself added; the pedal challenge in two breaths with its entries scored; the chimes starred on
the share of clean starts, not "no scoops"; Sing it with the guide after two weak takes; plain
words before cents; the memory portrait's line held back until six dots; a best to beat on replay;
the moments renamed so the free lesson reliably earns one.

**Changed from the content doc** (logged in decisions.md): the free lesson is Bullseye, with the
distraction moved to unit 4's Keep it in mind; "Naming a note cold" became "Hold your ground"
(holding against moving parts), and naming notes is left to Ear training and Sight-singing.

## 9. Reading the course file

The lessons are `web/course/plus/pitch/course.json` (see `web/course/plus/README.md`), in the
free course's shape: units, lessons and steps, every word as `{ "en": ... }`. Lesson ids never
change once landed (progress is kept by them). A step's `kind` is one of:

- `learn`: the Why card. `headline`, up to three `steps` (`title`, `detail`), a `visual` (a Learn
  picture's name) and `listen`, an exercise the guide plays for Hear it.
- `bullseye`: the echo game (`web/js/components/bullseye.js`). Its `exercise`:
  - `tempo`: beats a minute (72 if left out).
  - `echoes`: the groups, in order. Every note is in **steps**, semitones from the learner's
    comfortable note (0 is do, 7 is so, -3 is la below). A number is one note; an array is a few
    notes sung back as they were (`[0, 7]` is do then so); `{ "call": [0], "sing": [0, 5] }` is
    a call answered by different notes; add `"chord": { "root": 0, "quality": "maj" }` for a
    chord under the call and the answer (the root in steps from do).
  - `lengths`: each note's length in beats, by position in its group (default 1, the last 1.5).
    A group may carry its own `lengths` for its call (a tune's phrase in its own rhythm).
  - `think`: beats of quiet between the call and your answer (default 0.5).
  - `drone`: `{ "step": 0 }` for a drone on that note under the whole take.
  - A group may also name its `key` (steps from your note, default 0): its home chord and reveal
    drone are on that key's do.
  - `home`: true plays two beats of each group's I chord before its call, so a leap is heard in
    a key.
  - `reveal`: true plays a drone on the key's do for two beats after each answer (a note sung from
    memory, shown how close it was).
  - `named`: true when a leap is asked for by name (Ear training): a sung note nearer another
    semitone than the target is a miss, and Hear yourself says which leap was sung; Hear it right
    plays the guide singing what was asked.
  - `stack`: true adds "Hear it with the guide's note": your take with each call's note held
    under your answer.
  - `score`: `"cold"` stars each answer's first note on memory and the notes after it on where they
    landed from that first note (a cold do 60 cents off with mi and so true to it is right about mi
    and so); `"memory"` stars a note sung with nothing before it on how close it came; otherwise
    stars are from where the sung notes landed (`landing` in `web/js/pitch-skills.js`).
  - `generous`: true in a free lesson only: full credit to ±35 cents and a fair try lifted.
  - `compare`: `{ "step": 2 }` shows that earlier step's darts faint behind this one's (this
    visit only) and says how many cents closer this round landed.
  - `under`: `{ "step": 1 }` plays that earlier step's take beneath this one (with headphones).
  - `keep`: `"pitch.bullseye"` saves this round's first take on the device (never synced); a later
    step's `thenAndNow` with the same name shows those darts faint behind today's, with a line
    comparing them and Hear your first round.
- `play`: the free course's sung exercise (with `with` partner lines that sound while you sing, and
  `under: { "step": n }` to sing over that earlier step's take), with `score` naming one of this course's scorers
  (`landing`, `lock`, `centre`, `drift`, `memory`; section 2).
- `text` is the line over a step; `measures`, on a lesson's last scored step, says what its stars
  measured.

The rules every lesson keeps (span, hold length, minutes, a Why first, a `measures` line) are
checked by `web/tests/pitch.test.js`, so a change that breaks one fails the tests.

## 10. What the build review changed

A voice teacher and a code reviewer checked the built course against this outline (2026-10-29):

- **Cold starts are cold:** pp-4-cold's Hear no longer sings do; From your anchor warms up in
  another key (a tone lower); Start it yourself has no Bullseye round just before it (its then and
  now is left to the other three challenges) and its Hear is a tone lower. Each opens with a siren
  and a hum. Only a first try is truly cold, and the stars' line says so.
- **Notes after a cold do** are judged from where that do landed, not from the piano.
- **The Westminster chimes** are the hour's changes 2 to 5, ending on do.
- **The guide gives you do** before you hold against a near part (Don't get pulled, Stack yourself),
  and The chords change, you don't now sings with the guide over the chords before you go alone.
- **Leave and come back** is an echo, one shape at a time; **The fifth and the octave** holds so
  over a drone on do (a true fifth), then do over the drone an octave below.
- **Listening rounds added:** "Scooped or clean?" in Scoop or clean, "Which one sagged?" in No sag.
- **Amazing Grace's** I chord returns on "sound".
- **Scoring as built, differing from section 3:** Think it, then leap stars both notes of each leap;
  The fifth and the octave stars the octave lock (so is the warm-up); Leave and come back judges each
  note against its target; the half-step taste in Don't get pulled sits before the scored hold, so
  it never counts.
- **Code:** joined notes in an echo are each looked for from their own start (they had been judged
  on the note before); live darts keep time on any screen; only notes you landed widen your range.

## 11. What the lead's quality review changed

- **Cold starts:** each memory lesson opens with a siren on "ng" (a `wave` with a `path` target,
  unscored) and a hum; Your note, cold and From your anchor reveal the drone only after the last try
  (`reveal: "last"`), with a wider window and "wait for Your turn"; Start it yourself is a lane with
  Ode to Joy's rhythm (a `play` with `guide: false`, the drone revealing the key under its last
  note), scored `cold`: the first note from memory, the rest from where it landed.
- **Short notes** in a lock or centre step (Red River Valley's) are judged on their landing, so every
  note counts, not only the long ones.
- **Stack yourself** is scored on the centre: your own mi may be tuned pure, putting do a little off
  equal temperament.
- **Lesson 1** says what the course trains (relative pitch; true absolute pitch is rare) and the
  safety line; the scoop is heard (a `bend`), in Hear and in the listening rounds; No sag's Hear
  sags; long holds stay at or under a fourth above your note (so below over the low drone; do and
  mi in No sag).

**The lead's last look** (same day): the siren is said in words, not a lane (its lane and Hear sat on
do, a reference right before a cold try), and From your anchor has no warm-up in another key (a
drone a tone below pulled the cold do); a note after a cold do that isn't sung is a miss; the
challenge ends with the guide singing do with the drone, so the key shows through a speaker too.

## Sources

Checked in the content review (2026-10-29); every fact a lesson states comes from these.

- Tonic sol-fa's movable do: John Curwen from the 1840s, building on Sarah Glover's Norwich sol-fa, and his hand signs: https://en.wikipedia.org/wiki/Tonic_sol-fa
- The Westminster Quarters, written in 1793 for Great St Mary's, Cambridge, and the Messiah phrase: https://en.wikipedia.org/wiki/Westminster_Quarters ; https://www.classicfm.com/discover-music/what-tune-does-big-ben-chime/
- The beat rate equals the difference between the two frequencies: http://hyperphysics.phy-astr.gsu.edu/hbase/Sound/beat.html ; https://www.sfu.ca/sonic-studio-webdav/handbook/Interval.html
- Red River Valley, known in Canada before its 1896 New York printing as "In the Bright Mohawk Valley": https://thecanadianencyclopedia.ca/en/article/the-red-river-valley-emc ; https://www.mhs.mb.ca/docs/MB_history/72/redrivervalley.shtml
- The twelve-bar blues on home, four and five, and its place in early rock and roll: https://en.wikipedia.org/wiki/Twelve-bar_blues
- The tanpura's drone on the home note and its fifth, the home note chosen by the singer: https://en.wikipedia.org/wiki/Tanpura
- People start well-known songs near the recording's key (Levitin, 1994): https://web-archive.southampton.ac.uk/cogprints.org/643/index.html
- Absolute pitch and tone-language speakers (Deutsch et al., 2006, JASA): https://doi.org/10.1121/1.2151799 ; https://acoustics.org/pressroom/httpdocs/148th/deutsch.html
- The tuning fork, credited to John Shore, Handel's trumpeter, around 1711 (Britannica gives a later date, hence "around"): https://en.wikipedia.org/wiki/John_Shore_(trumpeter) ; https://www.whipplemuseum.cam.ac.uk/explore-whipple-collections/acoustics/historical-notes-brief-chronicle-tuning-fork
