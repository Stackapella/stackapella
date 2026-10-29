# The course, reviewed by a choir teacher: teaching, theory and counterpoint

Status: proposed, awaiting Hillel

**Written** 2026-10-28 by Claude, reviewing as a choral director and vocal teacher would (Kodály,
solfège, Dalcroze, Gordon's audiation, barbershop tag teaching, Fux). It covers
`web/course/course.json` (8 units, 32 lessons), what each step kind does in
`web/js/components/exercise.js`, `web/js/views/learn.js`, `web/js/scoring.js` and `web/js/course.js`,
the free templates, and the words in `web/locales/en.json`. **Nothing in the course or the code has
changed.** This review doesn't replace the voice teacher and the musician (vision principle 9):
every Learn step is still `reviewed: false`, and so is everything drafted here.

Back: [The vocal coaching plan](vocal-coaching-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs)

---

## 1. Verdict in short

**What already works, and should be kept:**
- Every exercise is built from the learner's own comfortable note (`base` in `exercise.js`). Most
  apps make a bass sing a soprano's "C"; this one never does.
- Tuning to a drone comes in unit 2, before harmony. That's the right order, and rare.
- "Listen, then your turn" is call and response, and Wait for me is the best pitch-finding tool I've
  seen in an app: it waits, it says which way to go, and it never marks a note wrong.
- The order of the harmony path (drone, third, round, thirds, bass, inner voice, whole song) is
  close to the classic choir order, and the free course ends with a whole harmony in your own voice.
- The words are kind, short and plain. Octave folding (a man singing the alto line an octave down
  is on pitch) is exactly right.

**The five findings that matter most:**

1. **The harmony units are the least supported part of the course, and the least measured.**
   In `5-third` the learner never hears the third sound *with* the drone before being asked to sing
   it: the Learn step plays do then mi one after the other, and the scored step plays only the drone,
   with no starting note and no Try step. On a speaker, every `turn: false` exercise with a drone or
   chords (`2-drone`, `2-challenge`, `5-drone`, `5-third`, `6-hold`) plays **nothing at all**, so the
   learner sings with no reference (`exercise.js` lines 389 to 392). And four of the nine lessons in
   units 5 and 6 (`5-round`, `5-thirds`, `6-bass`, `6-tenor`) give three stars for 20 seconds in the
   song (`learn.js` lines 34 and 292), without hearing a note.
2. **Some explanations are musically wrong or misleading.** The bass is told to "Move less" (the bass
   leaps more than any other part); `5-drone` says "Step above it" for what is a third; `5-thirds`
   says the alto is "a third below the tune" when it starts and ends a fourth below; "The buzz stops
   when you're right" and "how choirs tune" overstate. All are easy fixes, with words in section 2.
3. **The steps are too big at three seams.** From three-note patterns to Ode to Joy (a new note, fa,
   and 15 notes from memory); from patterns to Amazing Grace (3/4, a pickup, a leap of a fourth never
   taught, a full octave, in a key not fitted to the learner); and from one lesson on the bass to
   recording all four parts, which span G2 to D5, nearly three octaves, in one voice. Each course song
   is also its own project, so the soprano (unit 4) and bass (unit 6) already recorded don't stack
   into unit 7's "whole song is you".
4. **The stars measure pitch only, so lessons about holding, breath, dynamics, phrasing and entries
   are scored on something else.** Notes not heard are left out rather than counted, so singing two
   of `1-challenge`'s five notes well scores 100% and three stars. A single held note can only score
   100%, 50% or 0%, so "close" earns the same one star as "not yet".
5. **Staples of teaching singing are missing:** a warm-up, audiation (hearing it before you sing it),
   listening games without the mic, review of earlier skills, rhythm and entries, the minor mode, and
   any "why it works". And there is no road from holding a harmony part to singing a line of your own
   (rounds, partner songs, descants, counterpoint), although the library already has the songs to
   start one and the engine is one small mechanic away (a partner line, section 6).

---

## 2. Lesson-by-lesson review

### How the step kinds behave (what the review assumes)

| Kind | What the learner does | What decides the stars |
|---|---|---|
| `learn` | Reads a headline and three cards, sees a picture; some have a `listen` to hear | Nothing (a lesson with only Learn and Use gets 3 stars) |
| `try` | Wait for me: each note plays and holds until found | Nothing; must match every note, or skip |
| `play` | Hears it, then sings (`turn: true`), or sings at once over a drone or chords (`turn: false`) | % on pitch: 3 stars at 90%, 2 at 70%, 1 for finishing |
| `home` | Finds a comfortable note | 3 stars (1 if skipped) |
| `use` | Opens the song in Sing | Done after 20 s sung in it; 3 stars |
| `record` | Records the part in Sing, then "Check my recording" | % on pitch of the take (averaged across parts) |

Shared rules: a lesson's stars come only from its **last** `play` or `record` step; timing is never
judged in the course (`timing: 'none'`); tolerance is ±50 cents through unit 4 and ±35 from unit 5
(`course.js` line 41); "close" (up to twice the tolerance) counts half; notes not heard are left out
(`scoring.js` lines 60 and 66); a unit opens at two thirds of its stars. A `turn: false` exercise
starts singing at beat 0, with no count-in, and plays no guide.

A word on the tolerances: ±50 cents means "the nearest half step", the right first bar for adults who
aren't sure they can match a note. ±35 is fair for songs. Both accept a pure (just) major third,
14 cents below the piano's, which matters for the barbershop unit in section 6.

### Unit 1: Find your voice (`find-your-voice`)

The unit's job is to convince an adult who "can't sing" that they can match a note. It mostly does.
It lacks the two oldest tools for that: **sirens** (sliding through the voice on "oo" or "ng", which
the coaching plan lists for this unit but the course never does) and **a moment of hearing the note
in your head** before singing it.

**`1-comfortable` Your comfortable note**
- **Goal:** find an easy note to build everything on. Right first lesson.
- **Words:** "Every voice has an easy note. There's no wrong one." is ideal. Keep it.
- **Add:** a quick way in that speech therapists use: *"Say 'mm-hmm' as if you agree, then hold the
  'mm'."* It lands most people near their natural pitch. Add a fourth card or replace "Hum a note":
  - Title "Say 'mm-hmm'", detail "As if you agree. Then hold the 'mm'."
- **Scoring:** 3 stars for finding a note, 1 for skipping. Fair.
- **Missing:** a gentle siren first ("Slide from high to low on 'hoo', like a sigh") loosens the
  voice and gives the tracker a voice to hear.

**`1-match` Match a note**
- **Goal:** sing back a note you've just heard. The right second step.
- **Sequence:** Learn with a listen, Try (Wait for me), then Play. Exactly right: sound, then guided,
  then alone.
- **Words:** good. Add audiation to the steps: "Hum it in your head first / Then out loud."
- **Exercise:** the play step goes 0, +2, -2. The -2 is "te", a note outside the do-re-mi scale the
  course teaches in unit 3. Harmless here, but use -3 (la) so every note in the course belongs to one
  scale until minor is taught.
- **Scoring:** fair (relaxed). The play text "One star for trying, more for matching" is honest.

**`1-high-low` Higher and lower**
- **Goal:** hear direction (up or down) before singing.
- **Words:** "A note sits above or below your easy note." is flat. Suggest headline: *"Notes go up or
  down. Hear which way before you sing."* The card "Point to it / In your head, before you sing" is
  unclear: "point" where? Make it physical, as Dalcroze and Kodály do: *"Show it with your hand / Up
  or down, as you hear it."*
- **Exercise:** 0, +4, 0, -3 (mi above, la below). Good choices.
- **Missing:** the skill is hearing direction, and nothing checks hearing alone. A no-mic question
  ("Did it go up or down?") would train it directly (the `choose` mechanic, section 6).

**`1-breath` Breathing for singing**
- **Goal:** low, quiet breath and a steady release.
- **Words:** "Breathe in low / Like you're filling your belly" is a common image but not accurate (air
  goes to the lungs; the belly moves because the diaphragm flattens). Suggest: *"Breathe in low / Let
  your belly and lower ribs widen."* The shoulders card is excellent.
- **Exercise and scoring:** "hold this long note" for 6 beats, scored on pitch only. Stopping after
  two seconds still scores 100% if those seconds were on pitch. Score the hold (section 6, `hold`).
- **Order:** breath is a foundation; put it before `1-match` or fold it into a warm-up at the start
  of every first lesson of the day.

**`1-challenge` Sing back five notes**
- **Goal:** short memory plus matching. do re mi re do.
- **Sequence:** these are steps, which unit 3 teaches. Fine as a preview, but say so: "five notes,
  each a step from the last".
- **Scoring:** the loophole above: sing two of the five well and it's 100%. Count unsung notes as
  "not yet" once the take has been heard.

### Unit 2: Steady notes (`steady-notes`)

The best unit. A drone before harmony is what good choir teachers do. Two issues: the hold gets
*shorter* than in unit 1, and the drone exercises have no lead-in.

**`2-hold` Hold a note**
- **Goal:** a clean, steady note.
- **Words:** good. "Start it cleanly / Right on the note, no scoop" is exactly the right tip.
- **Exercise:** 4 beats at 72 (3.3 s), shorter than `1-breath`'s 6 s. Make it a ladder: 4, then 6,
  then 8 beats (three play steps or one exercise with three held notes and breaths between).
- **Scoring:** pitch of the note's middle; steadiness is measured (`steady`) but doesn't count.
  Count it here (section 6, `hold`).

**`2-drone` Against a drone**
- **Goal:** tune a unison to a drone. Excellent.
- **Words:** "Singing against a drone is how choirs tune." overstates: choirs tune mostly to each
  other and to chords. Suggest: *"Singers have tuned to drones for centuries, from Indian classical
  music to choir warm-ups."* "The buzz stops when you're right" is close to the truth (two near notes
  make a slow wobble called beating, which stops when they match) but "buzz" suggests a fault.
  Suggest: *"Hear them melt / A slow wobble means close. When it stops, you match."*
- **Exercise:** `turn: false`, so the drone and "Your turn" begin together. Start the target note at
  beat 2 so the drone sounds alone first (a data change only). On a speaker, nothing plays (finding
  1): say "Headphones make this one work" before it starts.

**`2-vowels` Vowels**
- **Goal:** keep pitch while the vowel changes. Good, and essential for blend later.
- **Words:** "Your mouth moves; the pitch stays." is perfect.
- **Scoring:** pitch only; the vowel isn't heard. Fine, and honest if the result says "we listen for
  the note".

**`2-challenge` Eight beats with the drone**
- **Goal:** hold 6.7 s against a drone. Right challenge.
- **Scoring:** duration isn't measured (stop after three beats and it's still 100%). Needs `hold`.
  Same lead-in fix as `2-drone`.

**Missing in unit 2:** a "tune in" exercise, starting a little off and sliding to the drone, so the
learner *feels* the wobble slow and stop. And a first word about head voice and chest voice (one
Learn card: "Your voice has a lower, speech-like sound and a lighter, higher one. Both are yours.").

### Unit 3: Moving notes (`moving-notes`)

Steps before leaps, do-re-mi before songs: the right order. But solfège arrives without a word of
explanation and then disappears, and the challenge jumps.

**`3-steps-up` Steps up**
- **Goal:** move by step, and meet do, re, mi.
- **Words:** "Most tunes move by step" is true and well said. The cards "Do / Your starting note",
  "Re / One step up" name syllables nobody has explained. Add one line to the headline or a first
  card: *"Singers name the steps do, re, mi. Do is home, wherever your note is."*
- **Exercise:** do re mi, sung on those syllables. Good. Add the drone on do: steps sung over a
  drone are steps *in a key*, and are better in tune.
- **Missing:** a Try step (Wait for me) before the Play.

**`3-steps-down` Steps down**
- **Goal:** the same, downward. No Learn step; fine as repetition.
- **Suggest:** merge with steps up into one five-note scale lesson (do re mi fa so, up and down),
  because the challenge needs fa and so and nothing has taught fa.
- **Words for a theory card:** the half steps (mi to fa, ti to do) are the scale's character.

**`3-jumps` Small jumps**
- **Goal:** do, mi, so, mi, do.
- **Words:** "A jump skips a note. Hear the landing first." is audiation in one line. Excellent.
- **Missing:** the name. These three notes are a major chord, the most important thing in the course
  to come. Say it here (theory bite, section 4).

**`3-challenge` Ode to Joy's opening**
- **Goal:** a real tune that moves by step. Ode to Joy is the right choice (public domain, stepwise).
- **Sequence:** too much at once: a new note (fa), 15 notes, sung back from one hearing at 84 bpm.
  Short-term memory for a new tune is about one phrase. Split it: Try the first phrase (Wait for
  me), Play phrase 1, Play phrase 2, then the whole as the challenge.
- **Scoring:** relaxed, fair, once the loophole is closed.

### Unit 4: Singing a tune (`singing-a-tune`)

Good idea (words, phrases, a whole verse). The jump from the exercises is the course's biggest.

**`4-words` Following the words**
- **Goal:** syllables on notes, with the guide.
- **Words:** "In a song, each word sits on a note." isn't quite true: syllables do, and in this very
  line "-zing" in "Amazing" takes two notes. Suggest: *"In a song, each syllable sits on a note. Some
  stretch over two."*
- **Material:** Amazing Grace is the right song (public domain, pentatonic, slow). But it opens with
  a leap of a fourth ("A-ma-", so up to do) never taught, in 3/4 with a pickup. Add a Play step
  first: "The jump in 'A-ma-'": steps -5 then 0, so to do.
- **Key:** course songs open in the template's key (`startFromTemplate` with no transpose), not one
  fitted to the home note the course found in lesson 1. `suggestKey` in `theory.js` already does this.
  Use it for every course song.

**`4-phrasing` Phrasing**
- **Goal:** breathe at line ends. Good.
- **Words:** "A phrase is one breath's worth of song." is lovely. Keep.

**`4-challenge` Amazing Grace, verse 1**
- **Goal:** record the melody. Right.
- **Scoring:** fair. **Keep this project:** it should become the first layer of the learner's own
  Amazing Grace (finding 3).
- **Missing in unit 4:** entries on time (the coaching plan names it for this unit; the course never
  asks). A song is the first place rhythm matters.

### Unit 5: Your first harmony (`first-harmony`)

The heart of the course and the place it most needs care. The order is close to right. What's missing
is the scaffold: hear the two notes together, find the note with help, then hold it alone.

**`5-drone` Hum against a drone**
- **Goal:** first harmony: two notes at once.
- **Words:** "Harmony is two notes at once." is the clearest definition I know. Keep. The cards "Step
  above it" and "Then step below" are wrong: the exercise sings +4 (a third above) and -3 (a third
  below), and unit 3 just taught that a "step" is the next note. Suggest: *"Climb to mi / Two steps
  above the drone"* and *"Drop to la / Below the drone"*.
- **Exercise:** `turn: false`, so no starting note, and silent on a speaker. Make it `turn: true` (the
  guide sings each note over the drone, then you do), and start at beat 2.
- **Tolerance:** this is the first lesson at ±35 cents, the same moment harmony arrives. Keep ±50 for
  `5-drone` and `5-third`: one new difficulty at a time.

**`5-third` Find the third**
- **Goal:** sing a third above and below a held note. The single most important skill in the course.
- **Words:** "A third is the sweetest harmony." is a fair claim for beginners (thirds are the most used
  harmony), but say what it is: *"Mi above do is a third: two notes that ring together."* The Learn
  `listen` plays do then mi in turn: add the drone so they hear mi *against* do.
- **Sequence:** add the missing middle. Today it's Learn, then sing it cold. Make it:
  1. Learn, hearing mi over the drone;
  2. Try (Wait for me) with the drone, which plays the target and the drone;
  3. Play `turn: true`: hear mi over the drone, then sing it over the drone;
  4. Play `turn: false`: drone only (the real skill);
  5. the same for the third below.
- **Words, again:** the third below is a *minor* third (three half steps), the third above a *major*
  third (four). Say so in the bite: two sizes of third is the first step to major and minor.
- **Scoring:** only the last step (the third below) counts. Fine once both are practised.

**`5-round` A round**
- **Goal:** hold a line while the same tune runs against it. The classic first polyphony.
- **Words:** "In a round, the harmony happens by itself." is lovely and true. Add a card for the real
  difficulty: *"Keep your own words / Look at your line, not theirs."*
- **Missing:** the learner has never sung Frère Jacques. Learn the tune first (a Play step of its
  first two bars), then the second voice.
- **Scoring:** 3 stars for 20 s in Sing. Add a scored step (see "Scoring the songs" below).

**`5-thirds` Two parts in thirds**
- **Goal:** sing a harmony part that shadows the tune.
- **Words:** "a third below the tune" isn't accurate for `michael-row`'s alto, which starts and ends a
  fourth below (A3 under D4) and has fourths and a fifth along the way. Suggest: *"mostly a third
  below the tune, landing on the note below do at the ends"*.
- **Sequence:** this lesson has no Learn step. Parallel thirds are the hardest kind of harmony to
  *hold*, because the tune is so close it pulls you onto it. Add one: *"Your line has the tune's
  shape, a third lower / Listen to the tune, but sing your own."*
- **Scoring:** 3 stars for 20 s. Needs a scored step.

**`5-challenge` Record the round's 2nd voice**
- **Goal:** right challenge, scored on the recording. Good.

### Unit 6: Low and inner voices (`low-and-inner`)

**`6-bass` The bass line**
- **Goal:** the bass's role.
- **Words:** "The bass is the floor the others stand on." is good. Two cards are wrong:
  - "Sing the chord's root / The lowest, steadiest note": the root is *the note the chord is named
    after*, not its lowest note.
  - "Move less / Others move; you anchor": the opposite is true. In four-part writing the bass leaps
    (do down to so, up to fa) while the alto and tenor move least.
  Suggest: *"Sing the chord's root / The note the chord is named after"*, *"Big jumps are normal / The
  bass leaps from root to root"*, and keep "Sing 'oo' / Round and warm".
- **Range:** this bass is G2 to D3, too low for most women and teens. Octave folding already scores it
  an octave up; tell them: *"Too low? Sing it an octave up. We'll still light your notes."*
- **Scoring:** 3 stars for 20 s. Needs a scored step.

**`6-tenor` The tenor**
- **Goal:** an inner voice.
- **Material:** in `amazing-grace` the tenor sings G3 on almost every note, with a few A3s and one
  C4: a pedal on the home note. That's a gift for teaching, and the course doesn't use it. Put `6-hold` first,
  then say here: *"In this song the tenor keeps coming back to G, the home note, while the chords
  change around it. You just practised exactly this."*
- **Missing:** a Learn step, and a scored step.

**`6-hold` Hold your note**
- **Goal:** keep a note while the chords move. The real skill of every harmony part.
- **Words:** good ("Your note is your note"). Add the musical point: *"Hear it change colour / The same
  note sounds different over each chord."*
- **Exercise:** do held over C, F/C, Am, C: well chosen (do is the root, then the fifth, then the
  third). `turn: false` with no starting note: let the first chord sound for two beats first.
- **Order:** make it lesson 1 of unit 6. It prepares both the tenor and the bass.

**`6-challenge` Record the bass of Amazing Grace**
- **Goal:** right. Uses the 3-singer voicing, while unit 7 uses 4. Use 4 here so the take can carry on.

### Unit 7: The whole song is you (`whole-song`)

**`7-blend` Hearing the blend**
- **Goal, as written:** listen to how your parts sit together, then nudge them in the studio.
- **Problem:** the step is "Sing the alto of Amazing Grace with the other parts", so the lesson is
  really *learning the alto*, with words about mixing parts that don't exist yet. Split it:
  - `7-alto`: Learn (the alto moves mostly in thirds and sixths under the tune), Use, and a scored
    step.
  - Keep blend as the Learn step *after* the recording, where it makes sense: match vowels, match
    volume, keep vibrato light.

**`7-challenge` All four parts of Amazing Grace**
- **Goal:** the finished harmony. The right end of the free course.
- **Problems:**
  - Four recordings in one sitting, from a new, empty project. Make units 4, 6 and 7 build **one**
    Amazing Grace: the soprano from unit 4, the bass from unit 6, then alto and tenor here.
  - The four parts span G2 to D5. Offer a voicing that fits the learner's range: for higher voices,
    tenor and bass an octave up, and say honestly that the chords will sound lighter; or three parts
    (S, A, B) when the range is under two octaves. Later, SSA and TTB arrangements.

### Unit 8: Expression (`expression`, Plus)

Sound vocal advice, but the stars can't see any of it. Either measure loudness (the level meter
already reads it) or say plainly that the stars are for the notes.

**`8-dynamics` Loud and soft**
- **Words:** "Expression is how you sing, not just which notes." Good.
- **Scoring:** pitch only; "starting strong and ending soft" isn't measured. Until it is, the result
  should say *"Your stars are for the notes. The shape is for you to feel."*

**`8-vibrato` A gentle vibrato**
- **Words:** "Vibrato comes by itself when the breath is steady." promises too much. Suggest: *"Vibrato
  often comes on its own when the breath is steady and the throat is easy. If it doesn't yet, that's
  fine: a straight tone is beautiful too."* Add when to use each: choirs and barbershop keep it light
  so chords ring.
- **Scoring:** the scorer already detects vibrato (`result.vibrato`, its speed and width). Show it,
  descriptively ("a gentle vibrato, about 5.5 a second"), never as a grade.

**`8-high-vowels` Vowels on high notes**
- **Words:** "High notes like room", "'ee' leans to 'ih'", "'ah' gets taller, not wider": textbook
  vowel modification, well put. Keep.
- **Exercise:** the top is la (+9) above the home note, which for most people is mid-range, not high.
  Use the learner's known range (`progress.range`) to put the top in their upper third.

**`8-phrasing` Shaping a line**
- Good spiral from `4-phrasing`. "Grow toward the peak / The highest or longest note" is right.

**`8-challenge` Danny Boy, with expression**
- **Material:** the soprano spans Ab3 to E5, an octave and a sixth: the hardest range in the
  library. Fit the key to the learner. As with `8-dynamics`, say the stars are for the notes.
- **Placement:** expression for a harmony course is as much about ensemble (matching, balance, straight
  tone) as solo shaping. Consider moving vibrato and blend to an ensemble lesson.

### Scoring the songs (applies to `use` steps)

A `use` step is done after 20 s sung in the song, and a lesson with no `play` step gets three stars.
For the harmony lessons, add a scored step on the hardest phrase: either a `play` exercise carrying
that phrase's notes with `chords` underneath, or (better) score the phrase the learner just sang in
Sing's Coach, which already scores phrases (`scoreTake` returns `phrases`).

---

## 3. Teaching principles to adopt across the course

| Principle | What it means here | Why it matters |
|---|---|---|
| **A warm-up first** | 30 to 60 s before the first lesson of the day: a sigh-siren on "hoo", lip trills or "ng" slides, then five notes up and down. Reuse the workout's `warmUp(range, home)`. Skippable. | Cold voices are flatter and tighter, and the first take sets the learner's belief about themselves. It also turns the mic on before the lesson needs it. |
| **Sound before symbol** | Every Learn step gets a `listen`. Words come after the sound; theory comes after the singing (the bite on the end screen). | Kodály and Gordon agree: we learn music as we learn speech, by ear first. Today 17 of 21 Learn steps are words only. |
| **Audiation** | Steps that ask the learner to hear it in their head: "Listen, then hum it silently, then sing" (a beat of silence before the turn), and later, hear do, then sing mi with nothing playing. | Holding a harmony part *is* audiation: you must hear your note before you sing it while other notes sound. Gordon's whole method rests on it. |
| **Call and response** | Already the core (`turn: true`). Extend it so the answer can differ from the call (the `answer` mechanic). | It's the oldest way music is taught, from spirituals to Indian classical music, and it builds memory one phrase at a time. |
| **Drones** | A drone under more exercises (steps, jumps, the warm-up), not only in units 2 and 5. | Intervals sung over a drone are heard in a key, so they're better in tune and the ear learns how each note "feels" against home. |
| **Tuning to a drone, by feel** | Teach the wobble (beating): slide from slightly off into the note and hear it slow and stop. | It's the only physical cue a beginner has that they're in tune, and it's how barbershop's "ring" is found later. |
| **Movable-do solfège, yes; hand signs, optional** | Keep movable do (with la-based minor, as `notes.js` already does). Use the syllables as lyrics in every scale exercise, not only unit 3. Pick **"so"** and use it everywhere: the course sings "so" but Sing's labels say "sol". Show Curwen hand signs as a picture on two or three Learn steps, never required; ask learners to *move a hand up and down with the pitch* instead. | Solfège names how a note relates to home, which is exactly what a harmony singer needs ("I'm on mi, the tune is on so"). Hand signs help children in a room; adults on a phone gain most from simply showing height. |
| **Intervals by song and by scale step** | Teach an interval as two scale steps in a key ("do up to so") with a song to remember it by, melodically first, then against a drone. See the table below. | Abstract interval drills are slow for beginners; anchoring them to tunes they know is fast and lasting. |
| **Holding a part, in the classic order** | Pedal and ostinato, then rounds, then partner songs, then descants, then parallel thirds and sixths, then independent lines. Inside each lesson: learn the line alone, sing it with your guide louder, then even, then guide off (Sing's modes already do this). | The pull to the melody is the beginner's main problem. Lines that are short and repeated, or *are* the melody (a round), are easiest to hold; a line that shadows the tune (thirds) is harder than it looks. |
| **One new thing at a time** | Split steps where two or three new things land together (Ode to Joy, Amazing Grace, `5-third` at a stricter tolerance). | When a learner fails a step with three new things, they can't tell which one beat them. |
| **Review, spaced** | Open each lesson with a 20 s "remember this?" from the lesson before; let each unit's challenge use an earlier skill; and let the workout bring back three-star lessons after 1, 3 and 7 days, not only low-star ones. | Skills sung once fade within days. Spaced review is the most reliable finding in learning research. |
| **Rhythm and entries** | A count-in on every exercise, a lesson on pickups (Amazing Grace starts on one), and timing judged in rounds once the timing check is done (the scorer can already). | In rounds and counterpoint, the entry *is* the skill. |
| **Listening without the mic** | Quick taps: same or different, up or down, which voice moved, major or minor, which one has parallel fifths. | Ear training on the bus; and it separates hearing from producing, which helps learners who can hear but not yet sing. |
| **Honest, kind feedback** | Already strong. Add: say what the stars measure when a lesson teaches something else. | Vision principle 4. A learner who shaped a phrase beautifully and got one star for a flat note learns the wrong lesson. |
| **Look after the voice** | "No push, no strain" is in lesson 1. Repeat it before high notes and long sessions; suggest a sip of water and a rest after 20 minutes. | Adult beginners push, especially on high notes and when chasing stars. |

**Intervals to remember by song** (all public domain; the first four are already in the library):

| Interval | Up or down | Song | Notes |
|---|---|---|---|
| Major second | Up | Are You Sleeping ("Are you") | do re |
| Major third | Up | Michael, Row the Boat Ashore ("Mi-chael") | do mi |
| Perfect fourth | Up | Amazing Grace ("A-ma-") | so do |
| Perfect fourth | Down | Are You Sleeping ("ding, dang") | do so |
| Unison and steps | Both | Ode to Joy ("Joy-ful, joy-ful") | mi mi fa so |
| Major second | Down | Mary Had a Little Lamb | mi re |
| Minor third | Down | This Old Man | so mi |
| Perfect fifth | Up | Twinkle, Twinkle, Little Star | do so |
| Major sixth | Up | My Bonnie Lies Over the Ocean | so mi |
| Minor sixth | Up | The Entertainer (Joplin, 1902) | mi do |
| Octave | Up | Take Me Out to the Ball Game (1908) | do do |

---

## 4. Music theory: a "Why it works" for each lesson

**How to show them.** A new optional field on a lesson, `why: { en }`, shown on the end screen under
the stars (after the singing, never before). Two or three sentences, no notation, tied to what was just
sung. Drafts below, for the voice teacher and the musician to check.

| Topic Hillel asked for | Where it's taught |
|---|---|
| Scales | `3-steps-up`, `3-steps-down`, `4-challenge` (pentatonic) |
| Intervals | `1-high-low`, `5-third`, `5-thirds` |
| The overtone series and sweet intervals | `2-vowels`, `5-drone`, `7-blend` |
| Chords and triads | `3-jumps`, `6-hold`, `6-challenge` |
| Keys | `4-words` |
| Major and minor | `5-third`, then unit 9 (a minor round) |
| Cadences | `6-bass`, `7-challenge` |
| Voice leading | `6-tenor`, unit 11 |
| Consonance and dissonance | `5-drone`, unit 11 |
| Parallel fifths | `5-thirds` (a first mention), unit 11 |

**Unit 1**
- `1-comfortable`: Your voice makes sound with your vocal folds, two small folds in your throat that
  vibrate as air passes. Your comfortable note is where they vibrate without effort, and every
  exercise from now on starts there.
- `1-match`: A note is a vibration that repeats at a steady speed. Matching means your vocal folds
  settle at the same speed as the guide's. When they agree, the two sounds seem to become one.
- `1-high-low`: Higher notes vibrate faster, lower notes slower. Double the speed and you're an
  octave higher, which sounds like the same note, only higher. That's why a man and a woman can sing
  "the same note" an octave apart.
- `1-breath`: When you breathe in, a dome of muscle under your lungs, the diaphragm, flattens to make
  room, so your belly and lower ribs move out. Letting the air out slowly and evenly is what keeps a
  note steady.
- `1-challenge`: Do, re, mi, re, do: the first three steps of a scale, a ladder of notes most tunes
  climb up and down. You'll learn its names in unit 3.

**Unit 2**
- `2-hold`: Musicians measure tiny pitch differences in cents: a hundred cents make one half step.
  Nobody's voice is perfectly still; a note lights up when you stay within a few dozen cents.
- `2-drone`: When two notes are nearly the same, you hear a slow wobble called beating. Its speed is
  the difference between them, so as you tune closer it slows, and when you match it stops.
- `2-vowels`: The note comes from your vocal folds; the vowel comes from the shape of your mouth and
  throat, which boost some of the voice's overtones. That's why the vowel can change while the note
  stays.
- `2-challenge`: Drones are among the oldest harmony there is. Bagpipes, the Indian tanpura and many
  folk traditions keep one note sounding under the tune.

**Unit 3**
- `3-steps-up`: Do, re, mi are solfège, names for the steps of a scale. In "movable do", do is wherever
  home is, so the names tell you how notes relate, in any key.
- `3-steps-down`: A major scale has seven notes before do comes back an octave higher: do re mi fa so
  la ti. Most neighbours are a whole step apart, but mi to fa and ti to do are half steps, the
  smallest step in Western music.
- `3-jumps`: Do, mi and so sounded together make a major chord, the most common chord in Western
  music. You just sang one, a note at a time.
- `3-challenge`: The opening you sang never leaves the five notes from do to so, and almost every
  move is a step. Beethoven finished it in 1824, when he was almost completely deaf.

**Unit 4**
- `4-words`: A song's key is its home note and the scale around it. Move the key and every note moves
  by the same amount, so the tune keeps its shape: that's how a song can be fitted to your voice.
- `4-phrasing`: Music comes in phrases, like sentences. Amazing Grace has four lines, and each ends on
  a long note, a natural place to breathe.
- `4-challenge`: Amazing Grace uses only five notes: do re mi so la. That's a pentatonic scale, found
  in folk music all over the world, and it has no half steps, which is part of why it's so easy to
  sing.

**Unit 5**
- `5-drone`: Some pairs of notes blend smoothly (consonance) and some rub (dissonance). Every note
  carries quieter notes above it, its overtones; when two notes share many overtones, like an octave or
  a fifth, they sound smooth together.
- `5-third`: Thirds come in two sizes. Do up to mi is a major third (four half steps), and la up to do
  is a minor third (three). Major thirds sound bright and minor thirds darker: that's the seed of
  major and minor.
- `5-round`: A round works because the tune keeps to one chord, or a few, so any part of it fits any
  other part sung at the same time. The oldest round we know, "Sumer is icumen in", is over 700 years
  old.
- `5-thirds`: Singing a tune a third lower is one of the oldest harmony tricks in folk music.
  Classical composers welcomed parallel thirds but avoided parallel fifths, because two voices moving
  in fifths melt into one and lose their independence.
- `5-challenge`: In a round, each voice is the leader for a moment and the harmony for the rest. That
  idea, independent lines that fit together, is called counterpoint.

**Unit 6**
- `6-bass`: The bass usually sings the root, the note a chord is named after. Bass lines leap by
  fourths and fifths because that's how chords move: listen for so down to do at the end of a line,
  music's full stop, called a cadence.
- `6-tenor`: "Tenor" comes from the Latin for "to hold": in medieval music the tenor held the long
  notes of the chant while others sang around it. Here the tenor holds the home note, G, while the
  chords change.
- `6-hold`: One note can belong to several chords. Do is the root of the home chord, the fifth of the
  four chord and the third of the six chord, so it fits all three. Good voice leading keeps notes like
  this where they are.
- `6-challenge`: The lowest note colours the whole chord. With the root in the bass a chord sounds
  settled; with another note there, it sounds on its way somewhere.

**Unit 7**
- `7-blend`: Voices blend when they match vowels, volume and vibrato. Matching vowels makes your
  voices boost the same overtones, which is why a good choir can sound like one instrument.
- `7-challenge`: Soprano, alto, tenor and bass, SATB, has been the shape of choir music for over 500
  years. "Alto" and "bass" come from Latin for high and low: two parts that grew out of one line sung
  "against the tenor". Many hymns end "A-men" on the four chord moving home, the "Amen cadence".

**Unit 8**
- `8-dynamics`: Loud and soft have Italian names, forte and piano. The piano is named after them: its
  full name, pianoforte, means it could play both.
- `8-vibrato`: Vibrato is a regular wave in pitch, about five to seven times a second in trained
  singers. Choirs and barbershop quartets keep it light so their chords tune and ring.
- `8-high-vowels`: Each vowel has its own resonances. On high notes the note can rise past a vowel's
  first resonance, so singers open "ee" toward "ih" to keep the sound free.
- `8-phrasing`: Most phrases rise to a high point and fall away, like a sentence building to its key
  word. Following that arc is what makes notes sound like singing.
- `8-challenge`: The tune, the Londonderry Air, was published without words in 1855; Frederic
  Weatherly's "Danny Boy" words were set to it in 1913. Its climb to the high note on "here" is one of
  the great peaks in folk song.

---

## 5. Interesting music facts

For loading moments ("Getting your lesson", "Listening back"), results and the course map. **Sure**
means I'm confident and the source will confirm it; **Check** means it's probably right but a detail
(a date, a number, an attribution) should be confirmed before it ships. Nothing here is invented.
Sources: Grove Music Online and Encyclopaedia Britannica cover nearly all of these; specific ones are
named.

| # | Fact | Source | Fits | Sure? |
|---|---|---|---|---|
| 1 | Do re mi comes from a hymn: around 1025, Guido of Arezzo used the first syllables of the lines of "Ut queant laxis" (ut, re, mi, fa, sol, la), each line starting a note higher, to teach singers new chants. | Britannica, "Guido d'Arezzo" | `3-steps-up` | Sure |
| 2 | "Ut" became "do" in the 1600s because it's easier to sing; the change is usually credited to Giovanni Battista Doni. | Grove, "Solmization" | `3-steps-up` | Check (attribution) |
| 3 | In 1800s England, Sarah Glover and John Curwen's tonic sol-fa used "te" (often written "ti") for the seventh note, so every syllable starts with a different letter. | Grove, "Tonic Sol-fa" | `3-steps-down` | Sure |
| 4 | The hand signs for do re mi were published by John Curwen in the 1870s and later made famous by Zoltán Kodály. | Grove, "Curwen, John" | Loading | Sure |
| 5 | Gregorian chant is one unaccompanied melody. It's named after Pope Gregory I (died 604), but most of it took shape in the 700s and 800s. | Grove, "Plainchant" | `2-challenge` | Sure |
| 6 | The first written-down harmony, organum, appears in a treatise of about 900 called Musica enchiriadis: a second voice singing the chant a fourth or fifth away. | Grove, "Organum" | `5-drone` | Sure |
| 7 | Around 1200, Pérotin at Notre-Dame in Paris wrote organum for four voices, some of the earliest four-part music we have. | Grove, "Pérotinus" | `7-challenge` | Sure |
| 8 | "Sumer is icumen in", from 13th-century England, is the oldest known round: four voices in canon over two more singing a repeated "pes". | Grove, "Sumer is icumen in" | `5-round` | Sure |
| 9 | "Tenor" comes from the Latin tenere, to hold: the tenor held the chant in long notes while other voices moved around it. | Grove, "Tenor" | `6-tenor` | Sure |
| 10 | "Alto" and "bass" come from contratenor altus and contratenor bassus, the high and low parts "against the tenor" in 15th-century music. | Grove, "Contratenor" | `7-challenge` | Sure |
| 11 | English music of the early 1400s was famous for sweet thirds and sixths; a French poet of about 1440 said Dufay and Binchois had taken up "the English manner". | Grove, "Contenance angloise" | `5-thirds` | Sure |
| 12 | Thomas Tallis's motet Spem in alium (around 1570) has 40 separate voice parts, written for eight choirs of five. | Grove, "Tallis, Thomas" | Unit 11 | Sure |
| 13 | In 1770 the 14-year-old Mozart heard Allegri's Miserere in the Sistine Chapel and wrote it down from memory, as his family's letters tell. | Mozart family letters; Grove, "Allegri" | Audiation steps | Sure |
| 14 | In 1725 Johann Joseph Fux published Gradus ad Parnassum, a dialogue between a teacher and a student that teaches counterpoint in five "species". Haydn, Mozart and Beethoven all studied it. | Grove, "Fux"; Mann's translation, The Study of Counterpoint | Unit 11 | Sure |
| 15 | Bach harmonised hundreds of Lutheran hymns for four voices. His son C.P.E. Bach and Kirnberger published 371 of them in 1784 to 1787, and students still learn harmony from them. | Grove, "Chorale" | `7-challenge` | Sure |
| 16 | Bach ended his Goldberg Variations with a quodlibet: two popular songs of his day woven into the counterpoint. | Grove, "Goldberg Variations" | Partner songs | Sure |
| 17 | Pachelbel's Canon is a canon for three violins over a two-bar bass line repeated 28 times. | Grove, "Pachelbel" | Unit 9 | Check (the count) |
| 18 | Thomas Ravenscroft published "Three Blind Mice" as a round in 1609. | Grove, "Ravenscroft"; Deuteromelia (1609) | Unit 9 | Sure |
| 19 | A = 440 vibrations a second became the international tuning standard at a 1939 conference in London, and an ISO standard in 1975. | ISO 16:1975 | Loading | Sure |
| 20 | Before that, pitch wandered: a tuning fork said to be Handel's gives A at about 422.5, roughly a third of a half step lower. | Grove, "Pitch" | Loading | Check (the number) |
| 21 | Many Baroque groups today tune to A = 415, about a half step below modern pitch. | Grove, "Pitch" | Loading | Sure |
| 22 | The Pythagoreans knew that an octave is a 2 to 1 ratio and a fifth 3 to 2. The story that Pythagoras heard it from blacksmiths' hammers doesn't work in real physics. | Grove, "Pythagoras"; Britannica | `5-drone` | Check (the hammer physics) |
| 23 | Every sung note contains overtones at 2, 3, 4 and 5 times its frequency. The first few spell out a major chord, which is part of why it sounds so natural. | Any acoustics text, e.g. Benade, Fundamentals of Musical Acoustics | `3-jumps` | Sure |
| 24 | A piano's major third is about 14 cents wider than a pure one. Barbershop singers tune it lower, so the overtones line up and the chord "rings". | Grove, "Just intonation"; Barbershop Harmony Society | Unit 12 | Sure |
| 25 | When a barbershop chord is perfectly tuned, the overtones reinforce so strongly that listeners hear a note nobody is singing, sometimes called the "fifth voice". | Barbershop Harmony Society | Unit 12 | Sure |
| 26 | Barbershop's signature chord is the dominant seventh, which barbershoppers call the "barbershop seventh". | Barbershop Harmony Society | Unit 12 | Sure |
| 27 | In Tuvan throat singing (khöömei), one singer sounds two pitches at once by boosting a single overtone. | Grove, "Tuva" | `2-vowels` | Sure |
| 28 | UNESCO named Georgian polyphonic singing a masterpiece of humanity's oral heritage in 2001, and the Georgian song "Chakrulo" flies on the Voyager Golden Record. | UNESCO; NASA, Voyager Golden Record | Unit 11 | Sure |
| 29 | Village women's choirs in Bulgaria sing close seconds that ring rather than clash: what sounds consonant is partly what you grew up with. | Grove, "Bulgaria" | `5-drone` | Sure |
| 30 | At the 2009 World Science Festival, Bobby McFerrin got an audience to sing the pentatonic scale just by jumping from spot to spot. Nobody had taught them. | World Science Festival, "Notes & Neurons" (2009) | `4-challenge` | Sure |
| 31 | Bobby McFerrin's "Don't Worry, Be Happy" (1988) was the first a cappella song to reach number one on the Billboard Hot 100. | Billboard | Results | Sure |
| 32 | Amazing Grace's words are by John Newton, a former slave-ship captain who became a clergyman and abolitionist (published 1779). The tune "New Britain" was first paired with them in 1835. | Britannica, "Amazing Grace"; William Walker, Southern Harmony (1835) | `4-challenge` | Sure |
| 33 | Amazing Grace, Auld Lang Syne and Swing Low, Sweet Chariot all use just the five notes of the pentatonic scale (checked against our arrangements). | Our templates | `4-challenge` | Sure |
| 34 | Mahler turned "Frère Jacques" into a slow minor-key funeral march in his First Symphony. | Grove, "Mahler" | `5-round` | Sure |
| 35 | "Silent Night" was first sung on Christmas Eve 1818 in Oberndorf, Austria, with Franz Xaver Gruber playing guitar. | Britannica, "Silent Night" | Library | Sure |
| 36 | Beethoven was almost completely deaf when his Ninth Symphony, with Ode to Joy, was first played in 1824. The tune is now Europe's anthem. | Britannica; Council of Europe | `3-challenge` | Sure |
| 37 | "Happy Birthday" began as "Good Morning to All" by Mildred and Patty Hill (1893). A US court ruled in 2015 that the long-claimed copyright on it wasn't valid. | Britannica; Rupa Marya v. Warner/Chappell (2015) | Library | Sure |
| 38 | "Shchedryk", the Ukrainian New Year song, became "Carol of the Bells" after Mykola Leontovych's 1916 choral setting. Its four-note pattern repeats all the way through. | Grove, "Leontovych" | Unit 9 (ostinato) | Sure |
| 39 | The Fisk Jubilee Singers, students from Fisk University, toured from 1871 to save their college, singing spirituals like "Swing Low", and sang for Queen Victoria in 1873. | Fisk University; Britannica | Library | Sure |
| 40 | "Simple Gifts" is an 1848 Shaker dance song by Joseph Brackett. Aaron Copland made it famous in Appalachian Spring (1944). | Britannica, "Appalachian Spring" | Library | Sure |
| 41 | Your recorded voice sounds strange because you normally hear yourself partly through the bones of your head, which adds low warmth. A recording has only the sound through the air. | Any hearing-science text; e.g. Scientific American | First Listen back | Sure |
| 42 | When you sing the A above middle C, your vocal folds open and close 440 times a second. | Basic voice science (Titze, Principles of Voice Production) | `1-match` | Sure |
| 43 | Adult men's speaking voices sit roughly an octave below women's: about 100 to 120 vibrations a second against 200 to 220. | Titze, Principles of Voice Production | `1-comfortable` | Sure (round numbers) |
| 44 | In a 2013 study, the heartbeats of choir singers sped up and slowed down together as they sang. | Vickhoff et al., Frontiers in Psychology (2013) | Unit 7 | Sure |
| 45 | Rachmaninoff's All-Night Vigil asks the basses for a low B-flat (B-flat 1), below the lowest string of a guitar. | Grove, "Rachmaninoff" | `6-bass` | Sure |
| 46 | The Queen of the Night's aria in Mozart's The Magic Flute climbs to a high F (F6). | Grove, "Zauberflöte" | `8-high-vowels` | Sure |
| 47 | "A cappella" is Italian for "in the manner of the chapel": voices with no instruments. | Grove | Loading | Sure |
| 48 | "Harmony" comes from the Greek harmonia, a joining or fitting together. | Oxford English Dictionary | `5-drone` | Sure |
| 49 | The oldest complete piece of music we can read, words and tune, is the Seikilos epitaph, carved on a gravestone about 2,000 years ago. | Grove, "Seikilos epitaph" | Loading | Sure |
| 50 | Most untrained adults can sing about an octave and a half; trained singers often reach two octaves or more. | General voice-teaching guidance | Your voice | Check (a rule of thumb) |

---

## 6. New units: from holding a part to singing counterpoint

### What's needed

Most of the ladder runs on what exists (`learn`, `try`, `play` with `drone`, `chords` and `turn`,
`use`, `record`), the library's own songs, and these new pieces:

| # | Mechanic | What it does | Size |
|---|---|---|---|
| M1 | **Partner line** (`with`) | An exercise carries one or more extra note lines, in steps from home like `notes`, that sound while you sing (drawn faint on the lane, never scored). Rendered like the drone with `renderPart`. Headphones only, as the drone. | S |
| M2 | **Answer** (`answer`) | In a `turn: true` exercise, the notes you sing back can differ from the call (call and response; a question and its answer). `layout()` uses `answer` for `target` when present. | S |
| M3 | **Hold scoring** (`score: 'hold'`) | Stars from how long you held the note on pitch and how steady it was, not just its middle (`longestHold` and `steady` exist). | S |
| M4 | **Any of these** (`choices`) | A note lights if you sing any of several pitches (each a consonance). Afterwards, the result names the motion you chose (contrary, parallel, oblique) and flags any parallel fifths or octaves. This is where you *make* counterpoint, not just sing it. | M |
| M5 | **Listen and choose** (`choose`) | No mic: hear two or three clips, tap the answer (up or down, same or different, major or minor, which has parallel fifths). | M |
| M6 | **Pure tuning targets** (`cents` per note) | A note's target can be a few cents off the piano's, so a ringing major third is scored at -14 cents. | S |
| M7 | **Rhythm without pitch** | Onsets scored from the mic level, for vocal percussion. | L, later |

New Learn pictures: `ostinato`, `canon`, `motion` (two lines, contrary), `suspension`, `ring`.

**Free or Plus** is Hillel's call. These units sit after unit 8, so by today's rule (units past 7 are
Plus) they're Plus. I'd move one ostinato lesson and one partner-song lesson into the free unit 5,
because they make the free harmony path easier, not longer.

All steps below are in semitones from the learner's home note (do = 0), like today's exercises.

### Unit 9: Your own line (pedals, ostinatos, rounds)

The unit makes "hold your own line" a habit with short, repeated material, the gentlest kind.

**`9-pedal` Be the drone**
- **Goal:** hold do while a tune moves above you (the reverse of unit 2).
- **Steps:** Learn (listen: Ode to Joy's opening over a held do); Try; Play `turn: false`, your do for
  16 beats, `with` the Ode to Joy opening (already in the course's data) starting at beat 2.
- **Scored:** hold (M3). **Needs:** M1, M3.
- **Why it works:** a note held under changing harmony is called a pedal, after the organ's pedals,
  which can sustain a low note forever. The tune sounds richer because it keeps leaning against it.

**`9-ostinato` A pattern that repeats**
- **Goal:** keep a short pattern going while the song happens around you.
- **Steps:** Learn (listen to the four-note pattern); Try the pattern (la-based minor: do ti do la,
  steps 0, -1, 0, -3); Use `shchedryk`, soprano (the template's soprano sings the four-note motif
  throughout).
- **Material:** Shchedryk, traditional Ukrainian (the template is original and doesn't use
  Leontovych's 1916 setting). **Scored:** add a Play of the pattern four times `with` the alto's line
  (M1), until Use steps are scored.
- **Why it works:** a repeated pattern is an ostinato, Italian for "stubborn". It's the easiest
  harmony part to hold, because once your body knows it, it keeps itself going.

**`9-row` A round in a new time**
- **Goal:** the second voice of Row, Row, Row Your Boat, in 6/8 (two beats, each in three).
- **Steps:** Learn (a beat in three: "ROW-row-row"); Play the first phrase; Use `row-row-row`, part 2
  (the alto); Record.
- **Material:** in the library (public domain: words 1852, tune 1881). **Scored:** the recording, with
  timing once the timing check is done.
- **Why it works:** 6/8 groups the beat in threes, which gives Row, Row its rocking boat feel. The
  round fits because the whole tune sits on one chord until the very end.

**`9-minor` A round in minor**
- **Goal:** meet the minor mode, in a round.
- **Steps:** Learn (listen: do mi so, then la do mi; "same shape, darker"); a `choose` step, major or
  minor (M5); Use and Record a minor round.
- **Material:** "Hey, Ho, Nobody Home" (traditional English round, public domain). **Needs a new
  template** (original round setting) and M5.
- **Why it works:** a minor key is built on la instead of do. The third above home is a minor third,
  a half step lower than in major, and that one note changes the whole mood.

**`9-canon` A canon at the octave**
- **Goal:** follow a leader who sings first, an octave away.
- **Material:** Tallis's Canon (Thomas Tallis, 1560s, public domain), where a second voice sings the
  melody a bar behind the first, an octave lower (the musician to confirm which voices). **Needs a
  new template.**
- **Why it works:** a canon is a round where the follower can start at any distance or pitch. Tallis
  made his so the second voice enters while the first is still in the opening line.

**`9-challenge` Record a round, every voice**
- Record three voices of Row, Row, Row Your Boat (`part: "all"`, 3 singers). Your voice, three times,
  chasing itself.

### Unit 10: Two lines, one song (call and response, countermelodies, descants, partner songs)

The voices now sing *different* things, but each line is simple and familiar.

**`10-call` Call and response**
- **Goal:** answer a phrase with a different one.
- **Steps:** Learn (listen: "do re mi" answered by "mi re do"); Play with `answer` (M2): the call is
  so la so mi, the answer re mi re do; then Use `wade-in-the-water`, an answering part (the template's
  verses have the soprano calling and the others answering).
- **Material:** Wade in the Water (public domain spiritual, in the library). **Needs:** M2.
- **Why it works:** call and response comes into gospel, blues and work songs from West African
  singing. A question that ends away from home (on re or so) wants an answer that lands on do.

**`10-counter` A slow line under a known tune**
- **Goal:** sing a countermelody that moves against the tune.
- **Steps:** Learn (contrary motion: "the tune climbs, you fall"); Play `turn: false`, four whole notes
  do, ti, la, so (steps 0, -1, -3, -5, four beats each) `with` the Ode to Joy opening.
- **Material:** Ode to Joy's opening (in the course's data); the countermelody is original. Checked:
  every bar starts on a third or a sixth, and the fourths in between are the tune's passing notes.
- **Scored:** pitch, then hold. **Needs:** M1.
- **Why it works:** your line falls while the tune rises: contrary motion, the surest way to make two
  lines sound independent. Composers have used it for 500 years.

**`10-descant` A descant above the tune**
- **Goal:** a higher line that floats over the melody.
- **Material:** O Come, All Ye Faithful (in the library) with an **original descant** for its last
  verse (the famous published descants are still in copyright). Lower voices get a countermelody
  below instead. **Needs new template content** (a descant part).
- **Why it works:** a descant sits above the tune, usually in longer notes, so the melody can still be
  heard through it. Choirs often save it for the last verse, to lift the ending.

**`10-partners` Partner songs**
- **Goal:** two different songs, sung at once, that fit.
- **Steps:** Learn (listen to each alone, then together); Use and Record.
- **Material:** Are You Sleeping and Row, Row, Row Your Boat (both in the library), sung together in
  one key: both rest on one chord, so they fit. **Needs a new template** joining them. A second pair to
  try: When the Saints Go Marching In with This Train (both traditional); the musician must check
  that they fit in our keys before it's built.
- **Why it works:** songs on the same chords fit together, whatever their tunes. Singing two at once
  is called a quodlibet, Latin for "whatever you please"; Bach ended his Goldberg Variations with one.

**`10-challenge` Record both partner songs**
- Both songs, both your voice. The first time the learner is two different people at once.

### Unit 11: Counterpoint, note against note

Fux's method, sung. The learner first *sings* lines written to the rules, then *makes* their own.

**The material.** An **original cantus firmus** (a slow fixed tune, written for Stackapella), sounding
an octave below the learner:
- cantus, `with`: do re fa mi so fa mi re do, steps -12, -10, -7, -8, -5, -7, -8, -10, -12, two beats
  each.

**`11-motion` Three ways to move**
- **Goal:** hear and sing oblique, contrary and parallel motion.
- **Steps:** Learn (picture: two lines); a `choose` step: "Did they move the same way or opposite
  ways?" (M5); Play contrary motion: the partner climbs do re mi (steps 12, 14, 16) while you fall mi
  re do (4, 2, 0), moving apart through a sixth, an octave and a tenth.
- **Needs:** M1, M5.
- **Why it works:** two voices can move the same way (parallel or similar), opposite ways (contrary),
  or one can hold while the other moves (oblique). Contrary motion keeps them most independent.

**`11-consonance` Smooth and rubbing**
- **Goal:** feel consonance and dissonance against a drone, and resolve a dissonance.
- **Steps:** Play `turn: true` with a drone on do: sing do, mi, so, la, do' (consonant), then re, and
  slide from re down to do (a dissonance resolving).
- **Needs:** nothing new.
- **Why it works:** in two-voice counterpoint, unisons, thirds, fifths, sixths and octaves count as
  consonant; seconds, fourths and sevenths as dissonant. The rub of a dissonance makes you want it to
  move, and the move is the music.

**`11-first` First species: sing it**
- **Goal:** sing a counterpoint, one note against each cantus note.
- **Steps:** Learn (the rules in plain words: start and end on an octave, sing only consonances, move
  mostly by step, prefer contrary motion); Try (Wait for me, with the cantus); Play `turn: false`
  `with` the cantus.
- **Your line** (checked against the rules: every interval consonant, perfect ones only at the ends,
  six of eight moves contrary, the end approached ti to do):
  - do, ti, la, do, ti, re, do, ti, do: steps 0, -1, -3, 0, -1, 2, 0, -1, 0, two beats each.
- **Needs:** M1.
- **Why it works:** in 1725 Fux taught counterpoint as a dialogue between a teacher and a student,
  starting here: one note against one. Haydn, Mozart and Beethoven learned the same way.

**`11-fifths` Why not parallel fifths?**
- **Goal:** hear why the rule exists.
- **Steps:** a `choose` step: two short versions of the same two-voice phrase, one in parallel fifths,
  one in thirds and sixths; "Which sounds like two voices?" Then sing the good one.
- **Material:** original, four notes each. **Needs:** M1 (in `listen`), M5.
- **Why it works:** a fifth is so smooth that two voices moving in fifths fuse into one hollow sound,
  as in medieval organum. Avoiding them keeps every line its own; folk and pop break the rule on
  purpose for that very sound.

**`11-yours` First species: your choice**
- **Goal:** make your own counterpoint, live.
- **Steps:** Learn; Play with `choices` (M4): at each cantus note, any third, sixth, fifth or octave
  lights; afterwards, the result says "You moved in contrary motion 5 times" and gently names any
  parallel fifths or octaves. Shown as choices on the lane, never as wrong notes.
- **Needs:** M1, M4.
- **Why it works:** this is how counterpoint was always learned: by choosing, note by note, with a
  few rules to keep each line singable and free.

**`11-second` Second species: two against one**
- **Goal:** two notes to each cantus note, the second free to pass by step.
- **Your line** (checked: every strong beat consonant, every weak-beat dissonance a passing note
  between two steps, no parallel fifths or octaves on any two beats in a row, the end ti to do):
  - so la, ti do, re la, so la, ti do, re la, ti do, re ti, do: steps -5, -3, -1, 0, 2, -3, -5, -3,
    -1, 0, 2, -3, -1, 0, 2, -1, 0, one beat each against the cantus's two, with the final do held
    for two.
- **Steps:** Learn (passing notes: "a step between two chord notes"); Try; Play `with` the cantus.
- **Needs:** M1.
- **Why it works:** a note that rubs is fine if it's on its way somewhere by step. These passing notes
  are how melodies glide between the notes of a chord.

**`11-challenge` A two-part piece**
- Record both voices of a real two-voice piece: a canzonet from Thomas Morley's First Book of
  Canzonets to Two Voices (1595) or a bicinium by Orlando di Lasso (1577), both public domain.
  **Needs a new template**, transcribed from an original or public-domain edition (check the edition's
  licence: many modern online editions carry their own).

### Unit 12 (optional): Tension and ring

Suspensions, a ringing chord, a bass line, and a barbershop tag. Later, vocal percussion.

**`12-suspension` Hold on, then let go**
- **Goal:** a 4-3 suspension: hold do as the bass moves to so, then fall to ti.
- **Steps:** Play `turn: true` with a bass partner line (M1): bass do (4 beats), so (4 beats), do;
  you: do held for 6 beats (its last 2 a rubbing fourth over so), ti (2 beats), do.
- **Why it works:** a suspension holds a note over from one chord into the next, where it clashes,
  then resolves down by step. It's one of the oldest ways to make a listener lean forward.

**`12-ring` Make a chord ring**
- **Goal:** tune a pure major third against a root and fifth.
- **Steps:** Learn (hear the beating of a piano third, then a pure one); Play `turn: false` with do
  and so as partner lines; your mi scored at -14 cents (M6).
- **Needs:** M1, M6, and Precise tolerance for this one step.
- **Why it works:** a pure major third vibrates exactly 5 times for every 4 of its root, so their
  overtones line up and the beating stops. That lock is what barbershop singers call "ring".

**`12-bass` Roots under a song**
- **Goal:** sing the bass's roots under I, IV and V: do, fa, so, do (steps 0, -7, -5, -12).
- **Steps:** Play with `chords`; then Use `sweet-adeline`, bass.
- **Why it works:** most songs use the three chords built on do, fa and so. A bass that sings their
  roots carries the whole harmony, which is why a whole song can rest on four notes.

**`12-tag` A barbershop tag**
- **Goal:** learn one part of a short, ringing ending, then all four.
- **Material:** an **original four-bar tag** (most published tags are in copyright), parts named the
  barbershop way: tenor (above the melody), lead (melody), baritone, bass. **Needs a new template.**
- **Why it works:** in barbershop the melody is in the second voice from the top, and the tenor floats
  above it. A tag is the song's ending, sung again and again for the joy of the chord.

**`12-challenge` Record the tag, all four parts**

**Vocal percussion and beatbox** need M7 (rhythm scored without pitch). Plan them once a percussion
part can be judged honestly; until then, a bass-line lesson does the same job for the groove.

---

## 7. Priorities

Sizes: **S** under a day, **M** a few days, **L** a week or more.

**Fix now** (copy and data, plus two small code changes)

| # | Change | Where | Size |
|---|---|---|---|
| 1 | Correct the wrong words: `6-bass` "Move less" and "lowest note"; `5-drone` "Step above / below"; `5-thirds` "a third below"; `4-words` "each word"; `2-drone` "how choirs tune" and "buzz"; `1-breath` "belly"; `8-vibrato` "comes by itself" | `course.json` | S |
| 2 | Give `turn: false` exercises a lead-in: target notes from beat 2 so the drone or chords sound first | `course.json` | S |
| 3 | Rebuild `5-third` as listen with drone, Try, Play with guide, Play alone; same shape for `5-drone`; keep Relaxed tolerance for both | `course.json`, `course.js` | S |
| 4 | Speakers: say before every drone or chords exercise that it needs headphones (today it plays nothing) | `exercise.js`, en.json | S |
| 5 | Close the loophole: notes not heard in a heard take count as "not yet" | `scoring.js`, tests | S |
| 6 | Reorder unit 6 (`6-hold` first) and tie `6-tenor` to it; tell high voices the bass may be sung an octave up | `course.json` | S |
| 7 | One word for "so" (course and `notes.js` agree); `1-match` -2 becomes -3; `2-hold` lengthens to a 4, 6, 8 beat ladder | `course.json`, `notes.js` | S |

**Next** (what makes the harmony path teach, and the stars honest)

| # | Change | Size |
|---|---|---|
| 8 | Scored steps for `5-round`, `5-thirds`, `6-bass`, `6-tenor` and the new `7-alto` (score the phrase in Sing, or a `play` with `chords`) | M |
| 9 | One Amazing Grace across units 4, 6 and 7 (takes carry over, 4 singers throughout), and a capstone voicing fitted to the learner's range | M |
| 10 | Course songs open in a key fitted to the home note (`suggestKey`) | S |
| 11 | Hold scoring (M3) for `1-breath`, `2-hold`, `2-challenge` | S |
| 12 | "Why it works" on the end screen (`why` field) and the facts pool for loading and results; drafts in sections 4 and 5, checked by the voice teacher and musician | S code, M content |
| 13 | A `listen` in every Learn step; Try steps before each new pattern; bridges (so to do before Amazing Grace, a five-note scale before Ode to Joy, Ode to Joy in phrases) | S |
| 14 | A warm-up before the first lesson of the day (reuse the workout's) | S |
| 15 | Partner lines (M1) and answers (M2): small, and they open units 9 and 10 | S each |
| 16 | Say what the stars measure in `8-dynamics`, `8-challenge` and other non-pitch lessons; show vibrato descriptively | S |

**Later** (new teaching)

| # | Change | Size |
|---|---|---|
| 17 | Unit 9 (pedals, ostinato, rounds), with the Hey Ho and Tallis's Canon templates | L |
| 18 | Listen and choose (M5), used for direction, motion, major and minor | M |
| 19 | Unit 10 (call and response, countermelody, descant, partner songs), with the descant and partner-song templates | L |
| 20 | Unit 11 (species counterpoint), with "any of these" and the motion check (M4) | L |
| 21 | Spaced review: a 20 s "remember this?" at the start of each lesson; workouts revisit three-star lessons too | M |
| 22 | Unit 12 (suspension, ring, bass, tag), with pure tuning targets (M6) | L |
| 23 | Loudness shape for dynamics; rhythm scoring for vocal percussion (M7) | M, L |

**Decisions for Hillel** (to log in `docs/decisions.md` once answered):
- "so" or "sol" across the app.
- Are the new units Plus (after unit 8), and does an ostinato or partner-song lesson move into the free
  unit 5?
- For the free capstone: an octave-up option for high voices, a three-part voicing, or both?
- Should the course's song steps keep a learner's earlier takes (one Amazing Grace), even though that
  changes how course projects are made?

---

[Back: The vocal coaching plan](vocal-coaching-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs)
