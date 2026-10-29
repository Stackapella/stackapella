# Counterpoint: the course outline

Status: built, all 5 units and 22 lessons (courses-harmony, 2026-10-29), staff only and coming soon
until the lead's review passes; the outline was reviewed by a voice teacher and a game designer, and
the course by the lead (section 9). It follows the lead's [build plan](../plus-courses-build-plan.md) (section 6a,
how to build a course) and turns the course review's units 9 to 11
([course-review-plan.md](../course-review-plan.md), section 6) into a Plus course of its own. Facts
come from the checked list in [plus-courses-content.md](../plus-courses-content.md). Section 9 lists
what the reviews changed.

Back: [Plus courses](../plus-courses-plan.md) · [Build plan](../plus-courses-build-plan.md)

---

## 1. The course in short

- **The skill:** singing your own line while another voice pulls at your ear, then making one.
- **Who it's for:** someone who has held a harmony part (the free course's unit 5, or any choir
  singer) and wants to know why lines fit, and to write their own.
- **Shape:** 5 units, 22 lessons, 2 to 4 minutes each, about 70 minutes in all. Each unit ends in a
  challenge that brings back an earlier unit's skill.
- **The arc:** hold your own (drones, pedals, patterns) → the same tune chasing itself (rounds and
  canons) → two different lines (call and response, countermelody, descant, partner songs) → the
  rules, sung (note against note) → your own counterpoint.
- **Catalogue:** `id: counterpoint`, prefix `cp`, colour 3, visual `round` (as `courses.json` has it),
  `status: soon` until every lesson plays.
- **Lesson 1 is free:** Row, Row, Row Your Boat. You sing it and three voices come in behind you, so
  your solo becomes a four-part round. Then all four voices become your own.

## 2. What the app measures, and how the stars are honest

Only what our pitch tools measure. Timing is never starred in the course (`timing: 'none'`).

| Measure | What it is | Where |
|---|---|---|
| **Pitch** | Percent of your notes on pitch, as every `play` step (±35 cents, "close" counts half) | `scoring.js` (exists) |
| **Hold** | How long you held the note on pitch, and how steadily | `score: 'hold'` (exists) |
| **Own line** | Notes in a row on pitch while a partner sounds: the combo, kept as a personal best per lesson | the exercise's combo (exists) |
| **Pulled** | A note you sang on a partner's pitch instead of your own (see below) | `harmony.js` (mine, pure) |
| **Motion** | For two lines, each move named: contrary, similar, parallel or oblique | `harmony.js` |
| **Parallels** | Fifths or octaves (unisons too) twice in a row, both voices moving the same way | `harmony.js` |
| **Your line** | With `choices`, which of the lit pitches you sang on each note | `harmony.js` |

**Pulled, carefully.** A note counts as pulled only when its middle sat off your pitch and on a
partner's, that partner's note is a different note no more than a major third from yours, and it
isn't your own previous or next note (so singing late or early is never called a pull). Further
away, or with three voices outlining a chord, a wrong note is just off pitch. Takes have their
latency removed by the engine, so "at the same moment" is real. **On a speaker the partners are
silent, so nothing is ever called pulled there.**

**The stars:**
- Lessons where you sing a given line: pitch percent, as the free course (3 at 90%, 2 at 70%).
- Pedal lessons: hold.
- Listen-and-choose lessons: the choices, and the lesson ends on them.
- `choices` lessons: the percent of notes landing on a lit pitch, and the `measures` line says that
  landing is the easy part. The real feedback is the rules checklist after: "Contrary 5 of 8, no
  parallels, ended ti to do". Three stars also need at least 3 contrary moves (cp-5-yours) or every
  rule (cp-5-challenge).
- A pulled note already scores off pitch, so pulls are said, never subtracted again.
- Lesson 1 scores generously: a fair first go earns two stars.
- **Not measured, so self-checked**, at the unit challenges only: blend and balance. "Could you
  still hear the other voices while you sang?"

**Hear it together** (the course's Hear yourself): after every scored take with partner lines, your
take plays back mixed with the other voices, your line drawn over the target, and the numbers we
measured: "14 of 16 notes on pitch. Own line: 11 in a row. Pulled once, on 'down': you slid up to
the other voice's so. Keep your mi there."

**When you keep getting pulled** (two takes with 3 or more pulls, or the same note pulled twice),
the step offers, never forces: **Partners quieter**, then **Sing with my guide** (your own line sung
softly with you; it fades on the next take). It says what changed ("Partners quieter this time").
Stars stay on pitch, so the easier setting still earns them. Try again is always one tap.

## 3. Ranges and keys

All steps are semitones from the learner's comfortable note (home, do = 0). **Every line the learner
sings stays within +5 and -9** (a fourth above, a major sixth below). Partner voices sound wherever
the music needs them, often an octave away, since the guide is not the learner. Each take is set in
its own key, so a tune's do sits where its range fits:

| Tune | Its span (do = 0) | Where its do goes | The learner sings |
|---|---|---|---|
| Row, Row, Row Your Boat | 0 to +12 | -7 | -7 to +5 |
| Three Blind Mice | 0 to +12 | -7 | -7 to +5 |
| Are You Sleeping | -5 to +9 | -4 | -9 to +5 |
| O Come, All Ye Faithful (the tune) | -5 to +7 | -2 when you sing it; -7 under the descant | the tune, or the descant within 0 to +5 |
| Are You Sleeping in minor | -5 to +9 | -4 | -9 to +5 |
| The original canon | -5 to +5 | 0 | -5 to +5 |

Tempo 60 to 80 unless said. Every exercise has a lead-in: the partners or chords sound for a bar
first, with soft onsets. No hold over 8 seconds.

## 4. The units

### Unit 1: Hold your own

The gentlest independence: one note, or a short pattern, while something moves around it.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **cp-1-round: Be a round** (the free taste) | Lead a round while voices join behind you | Why ("One tune that fits itself when it starts at different times"; "Best with headphones: you'll hear the voices join as you sing"); Hear the guide sing the tune; Try the first phrase with the guide (Wait for me); **Do**: sing it twice through, and this is the first time the three voices come in, 2 bars apart; **Hear it together**: your voice alone for 2 bars, then each voice blooming in; **Encore**, unscored: "Now all four voices are you", your own take delayed 2, 4 and 6 bars, mixed on the audio clock. Ends: "By the end of the course you'll write your own line against a melody", with 5 seconds of one (cp-4-first's duet). | Pitch, generously |
| **cp-1-hear: Which voice moved?** | Hear two lines as two | Why; Listen and choose, 6 rounds (the lead's `choose`): two voices, "Which one held still?", then "Same tune or different?" | The choices |
| **cp-1-pedal: Be the drone** | Hold do while a tune moves above you | Why (the organ's pedals hold a low note forever); Hear Ode to Joy over a held do; Try your do and the so below; Do: four holds of 3.5 beats with a quick breath between, under Ode to Joy's second phrase (it ends on do); then the same on so, below you (-5) | Each hold on its own (`holds`) |
| **cp-1-ostinato: A pattern that repeats** | Keep a four-note pattern going under a moving line | Why (ostinato is Italian for "stubborn"; "a darker colour you'll meet properly in unit 2"); Hear the pattern under the line; Try the pattern (do ti do la, steps 0, -1, 0, -3: Shchedryk's motif, the minor key's 3-2-3-1); Do: after the guide's do and a bar, the pattern eight times under an original falling line, ending on a held la (-3) | Pitch |
| **cp-1-challenge: Hold the floor** | Pattern and pedal together, in major | Row, Row's tune in the guide, an octave above you (its do at +5); you sing do so do so (-7, 0, -7, 0), a note to each dotted quarter, then hold do (-7) from "merrily" to the end in two holds with one breath; the blend self-check | Pitch, then the hold |

### Unit 2: Rounds and canons

The same tune, chasing itself. Now you enter second, against a line already moving.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **cp-2-follow: The second voice** | Come in against a moving tune | Why (the follower hears the leader singing other notes); Hear Are You Sleeping as a round; Try your entry phrase; Do: the guide leads, you enter 2 bars later and sing it through | Pitch, pulls said |
| **cp-2-minor: A round in minor** | Meet the minor key | Why (the same tune with mi and la a half step lower: darker; Mahler made this round a slow march in minor in his First Symphony); Hear the minor tune; Try its first four bars; Do: Are You Sleeping in minor as the third voice of three, two already singing | Pitch |
| **cp-2-canon: Lead, then follow** | A canon at the octave, both roles | Why (a round is a canon that loops back; a canon can start at any distance or pitch); Hear the canon, two voices; Do: you lead and the guide follows a bar behind, an octave down; then you follow the guide, an octave up. The canon is original: do re do la, so ti la do, re mi fa mi, fa mi re do, checked against its follower (every strong beat consonant, the one weak fourth a passing step, no parallels) | Pitch, on the follow |
| **cp-2-challenge: In the middle** | The hardest seat in a round, then the floor | Why; Listen and choose, 3 questions: how many voices by the end?; Do: Are You Sleeping as voice 3 of 4, then "ding, dang, dong" (-4, -9, -4) four times under the others, unit 1's pattern in the round's own notes; the partners at 0.35 so the guide comes through; the blend self-check | Pitch |

### Unit 3: Two lines, one song

The voices now sing different things, but each line is simple and familiar. Every line you sing
here is written for you: improvising a part is its own course.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **cp-3-call: Call and response** | Answer a phrase with a different one | Why (a phrase that ends away from do wants an answer that lands on it; from West African singing into gospel, blues and work songs); Hear: do re mi, answered mi re do; Do: that pair with `answer`; then four calls in a row, each answered in turn (the calls `listenOnly`, faint on the lane): do re mi / mi re do, so la so re / re mi re do, do mi so fa / mi re ti do, mi fa so la / fa mi re do | Pitch on your answers |
| **cp-3-counter: A slow line under a tune** | A countermelody | Why ("Your line keeps falling while the tune turns, so the two never move the same way for long"); Hear it; Do: do, ti, la, so (0, -1, -3, -5), four beats each, under Ode to Joy's opening | Pitch, then hold |
| **cp-3-descant: Float above it** | A descant: longer notes over the tune | Why (a descant floats above the tune in longer notes, so the tune comes through; choirs save it for the last verse); Hear; Try; Do: an original descant over O Come, All Ye Faithful's first line and its chords (the tune's do at -7): so, do, ti, do ti do la, so. Written against the library's chords: every note a chord tone, consonant with the tune | Pitch |
| **cp-3-partners: Two songs at once** | A partner song | Why (songs on the same chords fit, whatever their tunes: a quodlibet, "whatever you please"); Hear them together; Do: sing Row, Row, Row twice through while the guide sings Are You Sleeping swung in 12/8, so it fits bar for bar (one pass of it is two of Row). You sing only Row: no one key fits both tunes in your range | Pitch |
| **cp-3-challenge: The last verse** | Tune, then descant | Verse one: you sing the tune (its do at -2) while the guide sings the descant above you; the last verse: your descant over the tune and its chords, both lines, ending on a held do' over the home chord; the blend self-check | Pitch, and the last hold |

Checked, cp-3-counter: each bar's downbeat is a third or a sixth (M3, m6, m3, M6). In bar 2 the
tune's fa over your ti is a tritone, as a passing note. Bars 2 and 4 move the same way, so the Why
claims only that the lines never move together for long.

The partner pair is Row, Row with Are You Sleeping swung in 12/8 (the review's fallback): Three
Blind Mice is twelve bars against Row's eight. Row's bar 7 (on V) passes over Are You Sleeping's held
so and do as passing notes.

### Unit 4: Note against note

Fux's method, sung: first the sounds, then the rules in your ear, then lines written to them. The
cantus firmus is original, written for Stackapella, and sounds an octave below you: do re fa mi so
fa mi re do (steps -12, -10, -7, -8, -5, -7, -8, -10, -12), two beats each.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **cp-4-consonance: Smooth and rubbing** | The intervals, and resolving a rub | Why (unisons, thirds, fifths, sixths and octaves are smooth; seconds and sevenths rub, and in this style a fourth counts as rubbing too); Hear each one; Listen and choose, 6 questions: smooth or rubbing?; Do: over a drone on do at -12, sing -8, -5, -3 and 0 (a third, a fifth, a sixth and an octave above it), then re (+2) stepping home, then fa (-7, a fourth) stepping to mi | Pitch |
| **cp-4-motion: Ways to move** | Hear and sing contrary, similar and oblique motion | Why (Fux counts parallel motion as a kind of similar); Listen and choose: "Same way, opposite ways, or one held?", then "Which one moves in fifths?" (two voices in fifths fuse into one hollow sound, as in medieval organum); Do: eight notes against the guide, mi re do mi fa mi re do under do re mi mi re do ti do an octave up: four contrary moves, one oblique, two parallel sixths | Pitch |
| **cp-4-first: First species** | Sing a counterpoint, one note to each cantus note | Why (in 1725 Johann Joseph Fux taught counterpoint as a dialogue between a teacher and a student, in steps he called species; the first is note against note: start and end on an octave, only consonances, mostly by step, prefer contrary motion; Haydn, Mozart and Beethoven learned this way); Try with Wait for me; Do: do, ti, la, do, ti, re, do, ti, do (0, -1, -3, 0, -1, 2, 0, -1, 0) over the cantus | Pitch |
| **cp-4-second: Two against one** | Second species: passing notes | Why (a note that rubs is fine on its way somewhere by step); Hear; Try; Do: so la, ti do, re la, so la, ti do, re la, do so, la ti, do (-5, -3, -1, 0, 2, -3, -5, -3, -1, 0, 2, -3, 0, -5, -3, -1, 0), one beat each over the cantus's two, the final do held for two | Pitch |
| **cp-4-challenge: Hold the cantus** | Roles swapped, as in cp-2-canon: hold a slow line under a quicker one | You sing a second cantus, do fa mi re so fa mi re do (do at -7: -7, -2, -3, -5, 0, -2, -3, -5, -7), while the guide sings its counterpoint (do la do re ti re do ti do) close above you: unit 2's skill of not being pulled; the blend self-check | Pitch, pulls said |

Checked, cp-2-canon: the leader's last bar is la so ti do over the follower's fa mi re do, not a held
do (which made a seventh over re on a strong beat), so every strong beat of both roles is consonant.

Checked, cp-4-first: P8, M6, M3, m6, M3, M6, m6, M6, P8; six moves contrary, two parallel sixths,
the M6 to P8 cadence. cp-4-second, bar by bar (strong, weak): P5, M6 | M6, m7 passing | M6, M3 |
m3, P4 passing | M3, P4 passing | M6, M3 | m6, m3 | P5, M6 | P8: every downbeat consonant, every weak
dissonance a passing step, no parallel fifths or octaves between downbeats, and Fux's P5 M6 P8
cadence. cp-4-challenge: P8, M3, m6, P8, M3, M6, m6, M6, P8; six moves contrary, the inner octave
approached by contrary motion.

### Unit 5: Make your own

You choose the notes. The app lights every good one and tells you what you made. Every lesson here
involves a choice.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **cp-5-yours: Your choice** | Make a first-species line, live | Why (counterpoint was always learned by choosing, note by note); Hear an example; a practice pass, not starred; Do: the cantus at 4 beats a note at tempo 60, and at each note every consonance in your range lights (`choices`, one octave of each). A fifth or octave reached in parallel or similar motion dims, and so does an awkward leap from your last note while another choice stays lit; the last two notes light only ti and do. After: the checklist and your line drawn over the cantus | Notes lit, with at least 3 contrary moves for three stars |
| **cp-5-hold: Hold on, then let go** | A 4-3 suspension | Why (hold a note into the next chord, where it rubs, then fall by step); Listen and choose: which one leans before it lands?; Do: a bass partner sings do (-12, 4 beats), so (-17, 4 beats) with a partner re (-10) above it, then do; you sing do for 4 beats, do again for 2 over the new chord (the rub, scored as its own note), ti (2 beats), then do | Pitch |
| **cp-5-challenge: Write a counterpoint** | Your own line, by the rules | A third original cantus, do re fa mi la so mi fa re do (-12, -10, -7, -8, -3, -5, -8, -7, -10, -12), `choices` on every note (one octave of each; no choice that would leap a tritone into the cadence's ti); Hear it together. Later: Perform it, name it (section 7a) | Nearly every note on a lit pitch, and every rule: half the moves contrary, no parallel or direct fifths or octaves, ti to do at the end |

## 5. Variety and fun

Each unit mixes at least three activities:

| Unit | Activities |
|---|---|
| 1 | A round with partners and its self-round encore, listen and choose, a pedal, a pattern, a challenge |
| 2 | Enter against a moving line, a minor round, lead and follow a canon, record every voice in Sing |
| 3 | Call and response, a countermelody, a descant, a partner song, a two-verse challenge |
| 4 | Drone work, listen and choose (motion, fifths), sing a written line, passing notes, a new cantus |
| 5 | Choose your notes live, choose when to resolve, write and perform a duet |

**The fun moments:**
- **The round blooms** in lesson 1 during your own take, and the **self-round encore** turns all four
  voices into you.
- **Hear it together** after every partner take: your voice inside the harmony is the reward.
- **Own line**, the combo of notes in a row on pitch against a partner, with a personal best per
  lesson.
- **Then and now** at the unit 2 and unit 4 challenges (your pulls then and now, both takes played),
  and lesson 1 beside the last.
- **"You just did that"**, four times: your first four-voice round, every voice of a round your own,
  your first counterpoint by the rules, the first line you wrote.
- **Your duet**, at the end: performed, named, played back and shared with the Share result card.
- **Facts on end screens**, from the checked list: Sumer Is Icumen In (about 1260, the oldest known
  round in English); a round works because one melody fits its own harmony; Bach's family singing
  quodlibets at reunions; Fux's 1725 dialogue. **Bach's crab canon** is a 10-second Hear moment in
  unit 4 rather than a line of text: a line played forwards against itself backwards (our own line,
  not Bach's notes, unless taken from a public-domain edition).

Later, once the course has been sung on the beta: **Your lines**, a shelf of every line you chose in
unit 5, each playable, and **Your motion**, a portrait of your contrary, similar, oblique and
parallel moves; and saving your duet to Sing as two tracks, you singing the cantus too.

## 6. Safe singing

- The course page and lesson 1 say it once: **don't sing louder to hear yourself; listen more
  instead.** Stop if anything tightens, tickles or hurts, and don't practise when hoarse (the shared
  lines from the build plan, 3.7).
- The partners play about 6 dB under your guide's usual level, never loud. Wired headphones, or one
  ear lifted, help you hear yourself (and Bluetooth adds a delay).
- Every line sits around your comfortable note, within +5 and -9. No hold over 8 seconds.
- After 15 minutes of singing in a sitting, the end screen suggests a rest and some water (the
  lead's shared rule).

## 7. Headphones and speakers

The partner voices are the point of this course, and through a speaker the mic would hear them too.
- With headphones, the partners sound as you sing.
- On a speaker, each partner lesson plays the partners first (Hear), then you sing with them drawn
  faint on the lane but silent, and Hear it together puts you back together afterwards. The step
  says so before you start ("With headphones you'll hear the other voices as you sing"), and
  nothing is called pulled.

## 7a. Later

- **Every voice is you**: recording all three voices of a round in Sing, as the free course's `record` steps do, once a Plus course can open a song in Sing.
- **Then and now** at the challenges, once a lesson's first take can be kept on the device.
- **Tries with the cantus sounding** (cp-4-first, cp-4-second): Wait for me plays only your own notes today.
- **Perform it, name it**: cp-5-challenge singing your chosen line again with no lights, naming the
  duet and sharing both lines, once a step can carry the line chosen in the step before it. For now
  Hear it together plays the duet you wrote.
- **The self-round encore** in lesson 1 (your take as all four voices).

## 8. What it needs built

| Piece | What | Who |
|---|---|---|
| `with` (M1) | Partner lines on a `play` exercise: note lines in steps from home that sound while you sing (faint on the lane, never scored), on the engine's clock like the drone; resting on a speaker during a scored turn | courses-harmony, in `exercise.js` (the frontend agreed the shape: named helpers, the free path unchanged) |
| `answer` (M2) | In a `turn: true` exercise, the notes you sing back differ from the call | the same |
| `choices` (M4) | A note lights for any of several pitches; parallels dim live; the result reads which you sang | the same, with `harmony.js` |
| Hear it together | After a take with `with`, your take mixed with the partners; a new "Hear it together" key in TakeResult | the same |
| Easier settings | Partners quieter; Sing with my guide | the same |
| `harmony.js` | Pure: pulls, motion, parallels, your line from `choices`, the checklist; unit-tested | courses-harmony (built) |
| `choose` | Listen and choose | the lead |
| Learn pictures | `canon`, `motion` (two lines, contrary), `suspension` | courses-harmony, in `learn-visual.js` with the frontend's OK |

**Material**, all public domain or ours: Row, Row, Row Your Boat (words 1852, tune 1881); Three
Blind Mice (Ravenscroft's Deuteromelia, 1609); Are You Sleeping (traditional, 18th century), in major and in minor (as in Mahler's First Symphony); Ode to
Joy's tune (Beethoven, 1824); the Shchedryk motif (traditional Ukrainian; we use the four-note
figure, never the name "Carol of the Bells" or its 1936 English words); O Come, All Ye Faithful
(tune attributed to John Francis Wade, 1740s; never a modern hymnal's harmony or descant). The
cantus firmi, the patterns, the countermelody, the descant and the call-and-response pairs are
original. Each tune is transcribed as steps when built and checked against an old source.

## 8a. The course file

`web/course/plus/counterpoint/course.json`, in the shape every course has (see
`web/course/plus/README.md`). What's unusual here:

- **Beats are eighth notes in 6/8 tunes.** Row, Row is written a beat to an eighth at tempo 150 (a
  dotted quarter at 50), so its notes are whole numbers and its quick "merrily" notes are wide
  enough to read on the lane. A bar is 6 beats.
- **Each tune's do sits where its range fits** (section 3): Row, Row's steps run from -7 (its do) to
  +5 (its high do).
- **`with`** on a `play` exercise holds the partner voices. In cp-1-round they're the sung line
  itself, `delay` 12, 24 and 36 beats later (2, 4 and 6 bars), an octave up, in unison and an
  octave down. They play about 6 dB under the guide, and on the lane they're drawn faint in your own
  octave.
- **The lesson's stars** come from its last `play` step, on pitch; `strictness: relaxed` makes
  lesson 1 generous.

**Stars in unit 5** (`score: "species"`, `rules: "contrary" | "all"`): your notes on pitch, judged as
the lit note you sang; when the step's rules don't hold, the percent stops at 89, just short of three
stars, never lower (js/harmony.js `speciesPercent`). The choices at each cantus note are every note of
the key in your range (-9 to +5) that sounds smooth over it, do or so to start, then ti and do to end.

**For pull-request authors:** on a note with `choices`, its `step` is only what's drawn before you
sing (the first choice); what's scored is the choice you sang. The cantus firmi and the written lines
appear in several lessons on purpose (heard, tried, sung); the course file is generated from one
script, so change a line in every place it appears.

## 8b. Field reference (for the course schema)

All are optional on a `play` step's `exercise` (or a `learn` step's `listen`); without them an
exercise plays exactly as before. Pitches are semitones from the learner's comfortable note, beats
are on the exercise's own timeline (beat 0 is where it starts), as for `notes`.

| Field | On | Type and units | Default | Meaning |
|---|---|---|---|---|
| `with` | exercise | array of partner lines | none | Voices that sound while you sing, never scored. On a speaker they rest from the scored turn. The exercise ends half a beat after the last voice. |
| `with[].notes` | partner line | array of notes `{ beat, length, step, cents? }` | | A written line, on the exercise's timeline. |
| `with[].delay` | partner line | number, beats | | Instead of `notes`: the sung line itself, this many beats later (a round, a canon). |
| `with[].steps` | partner line | number, semitones | `0` | With `delay`: the copy moved up (or down, negative), `12` an octave. |
| `with[].volume` | partner line | number, 0 to 1, a gain | `0.5` (about 6 dB under the guide) | How loud the line plays. |
| `answer` | exercise (`turn: true`) | array of notes `{ beat, length, step, lyric?, cents?, choices? }` | none | What you sing back instead of `notes`; its beats start at 0, and it starts a beat after the call ends. |
| `choices` | note | array of numbers, semitones from home | none | Any of these is right; the one sung (nearest, in any octave) is the one scored. With a `with` line, the first partner is the cantus: a fifth or octave that would make a parallel with your last note is dimmed and can't light. |
| `cents` | note | number, cents (100 to a semitone) | `0` | Tunes the note off the piano's pitch, for the guide and the target alike (`-14`: a pure major third). |
| `score: "holds"` | exercise | | | Several holds with breaths between, each judged as a hold of its own and averaged. |
| `score: "species"`, `rules` | exercise | `rules`: `"contrary"` or `"all"` | | A chosen line against the first `with` line: on pitch, three stars only when the rules hold. |

What the result says (measured, in plain words, whatever the skin): how many notes were on pitch;
with partners heard, how often you were pulled onto another voice's note; with `choices` against a
partner, your contrary moves, any parallel fifths or octaves, and whether you ended ti to do. The
"Own line" run of notes in a row is a game-skin line only.

## 9. What the reviews changed

From the **voice-teacher review** (every written line checked in semitones; the species lines, the
cadences, the suspension and the Shchedryk motif held up):
- Every learner line within +5 and -9, with each tune's do placed to fit (section 3); cp-1-pedal's
  so moved below (-5); cp-4-consonance rewritten over a low drone so nothing goes above +2.
- cp-1-pedal split into four 4-beat holds (it broke the 8-second rule).
- cp-1-challenge's minor pattern clashed with Row's major tune: a major pattern instead.
- The partner songs: Are You Sleeping in simple time against Row (6/8) is two against three, and
  Row's bar 7 is on V: Three Blind Mice was tried first, but it's twelve bars to Row's eight, so the
  pair is Row with Are You Sleeping swung in 12/8 (the review's fallback); the Why says "the same
  chords".
- Pulled counts a partner note only within a third, never your previous or next note, and never on
  a speaker.
- cp-3-counter's Why no longer overclaims contrary motion; the tritone noted.
- cp-4-second's check sentence corrected ("no parallel fifths or octaves between downbeats").
- cp-3-call's Why matched to its examples, and its answers are written lines.
- Unit 4 reordered (consonance, motion, first species) with the intervals heard before they're
  named; cp-1-hear moved to lesson 2; cp-1-ostinato doesn't say "minor" before unit 2.
- cp-5-yours slowed to 4-beat notes at tempo 60 with a Try; three stars need 3 contrary moves; a
  lit fifth or octave dims when it would make a parallel; only ti and do at the end.
- Star honesty: listen-and-choose lessons end on their choices; "this line moves contrary" said of a
  given line; "Which one moves in fifths?" asked, not "which sounds better", and not starred.
- cp-5-hold gained a partner re so the suspension rubs, and the bass's octave is stated.
- Terms: round and canon the right way round; the fourth "counts as rubbing in this style"; the
  unison among the consonances; Wade "attributed"; Fux's parallel inside similar.
- Safety: the shared stop-if-it-hurts lines, partners 6 dB under, soft onsets, wired or one-ear
  headphones, "best with headphones" on lesson 1.
- Copyright: no "Carol of the Bells" name or words; Hey, Ho and Tallis from old sources; no modern
  descants.
- The two missing cantus firmi written and checked.

From the **game-design review**:
- Lesson 1 no longer plays the whole round first (the self-round encore is later, section 7a): the voices join for the first time during your
  take, Hear it together stages the bloom, and the self-round encore makes all four voices you. Its
  last line sells where the course goes, with 5 seconds of a finished duet.
- The unit 5 trap fixed (a lit note could cost the third star): parallels dim live, and the result
  is a checklist, not pass or fail.
- "Pulled 0 times" replaced by **Own line**, a combo with a personal best; then and now compares
  pulls and plays both takes.
- Help when pulled: Partners quieter, then Sing with my guide, said plainly, never forced.
- Unit 4's theory slump: motion and fifths merged into one lesson, the listening as a warm-up;
  second species moved into unit 4; cp-2-minor's major-or-minor quiz cut.
- Unit 2's samey entries: cp-2-canon sings both roles.
- Unit 5 is all choosing: cp-5-hold asks you to choose when to resolve.
- The ending: Perform it, name it, share both lines; lesson 1 beside it.
- Your lines and Your motion planned for after the beta; the blend self-check at challenges only;
  the crab canon as a Hear moment.

From the **lead's quality review** (units 1 to 3):
- cp-1-challenge's "so" was written as fa (a clash with Row on "boat", "down" and "stream"): now so,
  and do held to the end in two holds.
- Safety on lesson 1's cards (never louder to hear yourself; stop if anything tightens or hurts), and
  cp-2-follow's card no longer invites pushing.
- Lesson 1 generous (two stars for a fair first go, with the exercise's `generous`), and its Try covers
  the quick "merrily" half.
- Unit 2's challenge brings back unit 1's skill: come in third, then the round's own "ding, dang,
  dong" as a floor; a listening warm-up (how many voices?) adds a different activity.
- cp-3-challenge: verse one is the tune with the guide's descant above; the last verse carries the
  descant through the hymn's second line, over its chords.
- cp-1-hear: answers mixed, a both-voices-move question, "questions" in its line; the descant written
  against the hymn's chords; the pedal under the phrase that ends on do, with a Try; the ostinato
  slower, eight times, with a starting do; words fixed (minor, the call's history card, "line up").

From the **lead's quality review** (units 4 and 5):
- cp-5-hold's rub is its own scored note, so stepping off early no longer lights it; its second
  question asks a fact ("Which one leans before it lands?"), not a preference.
- `choices` keep one octave of each note (octave folding couldn't tell a high fa from a low one, and
  miscounted contrary motion), and no choice leaps a tritone into the cadence.
- Direct fifths and octaves (both voices moving the same way into a perfect interval) and awkward
  leaps (tritone, major sixth, seventh) now dim while another choice stays lit, and the checklist
  reports them; three stars in the challenge need no directs. Its words say "nearly every note".
- cp-5-yours gained a practice pass; cp-4-motion's line is eight notes and shows every kind of motion;
  cp-4-consonance hears smooth or rubbing first and resolves a fourth; cp-4-challenge swaps roles (you
  hold the cantus under its counterpoint); Fux, 1725 and "species" are named; organum, not chant;
  "for centuries"; the suspension's listen is split by voice.

## Sources

The claims and facts in the course, checked 2026-10-29 (a fact-check by a music-history reviewer):

- Species counterpoint, first and second species, passing notes, perfect intervals at the ends: [Puget Sound Music Theory, First species](https://musictheory.pugetsound.edu/mt21c/FirstSpecies.html); [Open Music Theory, Species counterpoint](https://viva.pressbooks.pub/openmusictheory/chapter/species-counterpoint/); [fourth species](https://viva.pressbooks.pub/openmusictheory/chapter/fourth-species-counterpoint/)
- Fux's Gradus ad Parnassum (1725), and its dialogue of Aloysius (Palestrina) and Josephus (Fux): [Gradus ad Parnassum](https://en.wikipedia.org/wiki/Gradus_ad_Parnassum); [Johann Joseph Fux](https://en.wikipedia.org/wiki/Johann_Joseph_Fux); [Newberry Library essay](https://italian.newberry.t-pen.org/essay/075)
- Descants and their place in a late verse; Willcocks's descant (1961): [Descant](https://en.wikipedia.org/wiki/Descant); [Open University](https://www.open.ac.uk/blogs/news/arts-social-sciences/art-literature-music/the-choirmaster-whose-carols-became-the-sound-of-christmas)
- Quodlibet, partner songs, Bach's "Quodlibet" (Goldberg Variations, 1741): [Quodlibet](https://en.wikipedia.org/wiki/Quodlibet)
- Call and response and its roots: [African-American music](https://en.wikipedia.org/wiki/African-American_music)
- Sumer is icumen in (about 1250): [Sumer is icumen in](https://en.wikipedia.org/wiki/Sumer_is_icumen_in); [Reading Museum](https://www.readingmuseum.org.uk/node/16041)
- Row, Row, Row Your Boat in print by 1852: [Row, Row, Row Your Boat](https://en.wikipedia.org/wiki/Row,_Row,_Row_Your_Boat); [Ballad Index](https://balladindex.org/Ballads/FSWB412C.html)
- Pachelbel's Canon: [Pachelbel's Canon](https://en.wikipedia.org/wiki/Pachelbel%27s_Canon)
- Helmholtz (1863) and beating: [SFU Handbook, Consonance](https://www.sfu.ca/sonic-studio-webdav/handbook/Consonance.html); [Plomp and Levelt (1965)](https://www.math.miami.edu/~armstrong/592sp15/Plomp_Levelt_1965.pdf)
- Tune transcriptions: [hymnary.org, Adeste Fideles](https://hymnary.org/search?qu=tuneTitle%3Aadeste%20fideles%20in%3Atunes); the app's own checked templates (Ode to Joy, O Come, All Ye Faithful)
