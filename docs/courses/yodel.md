# Yodeling: the course outline

Status: built, all four units, 16 lessons, about 30 minutes (courses-styles, 2026-10-29), live, after the lead's review (content commit ea9ebdc): shown to staff until Plus courses open on launch day. Every lesson is five minutes or less. Its measures are `flip`, `flipPoint` and the `flipFinder` scorer in
`web/js/style-scores.js` and `web/js/lesson-scores.js`; every leap is set from the singer's own
flip note, found by lesson 1's siren. The rules every
lesson is checked against are in [plus-courses-build-plan.md](../plus-courses-build-plan.md),
sections 3 and 6a; the course's first notes are in
[plus-courses-content.md](../plus-courses-content.md), "Yodeling".

Back: [Plus courses](../plus-courses-plan.md) · [Docs guide](../../README.md#a-guide-to-the-docs)

## In short

- **The skill:** flipping cleanly, on purpose, between your chest voice (the low, speaking-like
  sound) and a light head voice (the high, hooty sound; for many men, falsetto), and back, then in
  rhythm and faster.
- **Id and prefix:** `yodel`, lesson ids `yodel-<unit>-<name>`. Four units, 16 lessons, 2 to 4
  minutes each.
- **Its promise in one line:** "Find the flip in your voice, then make it a yodel."
- **Nothing needs a licence.** The yodel refrains are our own, written for the course, on yodel
  syllables; the tunes they tag are public domain: Home on the Range (1870s) and Red River Valley
  (1890s). No recorded yodel is copied.
- **Not for changing voices:** the course page says boys whose voices are breaking should wait.

## What the app measures here

| Measure | What it is | From |
|---|---|---|
| Pitch on the low and the high note | Each note's centre against its target | `scoring.js` (exists) |
| **The flip** | How quickly the voice crosses a leap between two joined notes: clean, nearly, or slid, up and down reported apart | `flip` in `web/js/style-scores.js` (built, tested) |
| **Where your voice flips** | On a siren up and down, the pitch where the line jumps instead of gliding | `flipPoint`, `settleFlip` and the `flipFinder` scorer |
| Listen and choose | The answers | `choose` (exists) |

**What we can't hear, said plainly:** whether the voice truly changed register. The tracker hears a
fast jump between two pitches; a singer can jump in chest voice alone, and above the flip that's a
pulled yell. Two things keep the stars honest: every leap crosses the singer's own flip (below), and
every flip lesson asks "Did the top sound lighter and hootier, like a different voice?" A "same
voice" answer changes the advice ("Let the top tip over: lighter, quieter, hootier"), and "you just
did that" for a first clean flip fires only after "a different voice".

### The flip, in numbers

- The tracker reads 100 times a second. Its five-reading median keeps a jump a jump, so the readings
  between two notes are read as they come.
- For each leap of four semitones or more between joined notes, the flip is the time from the last
  reading within a semitone of the first note to the first two readings within a semitone of the
  second (arriving a little flat is normal; the note's pitch is judged on its centre, separately).
- A dropout between, and an octave slip of either note (half pitch as the folds settle), count as
  part of the flip, never as a glide. A leap of about an octave isn't octave-folded.
- **Clean:** 100 ms or less, with under a third of the readings between passing through the notes
  between. **Slid:** over 200 ms, or most readings passing through. Between is "nearly".
- Stars on a flip step: half the flips (clean 1, nearly a half), half the notes' pitch. Lesson 1
  counts nearly as a win and its stars lean on pitch.
- The result line (once the lead's scorer `line` lands): "6 of 8 flips clean. Up: all clean. Down:
  two slid. Land lightly, at speaking volume." No milliseconds: they'd mostly be noise, and they'd
  reward a slammed jerk.
- Tested on synthetic tracks (a jump, a dropout, an octave slip, a 300 ms glide, an octave leap) and
  through the tracker on `sing()` with quick and slow joins.

### Where the yodel sits in your voice: the must-have

The other courses set every note from the comfortable note, the top at most a fourth above. A
yodel's leap has to cross the singer's own flip, and the chest-to-head break is typically a fourth
to a sixth above a woman's comfortable note and around an octave above a man's (the voice teacher's
figures; they vary a lot). A top note a fourth above the comfortable note would leave most men below
their flip, earning stars for a chest-voice jump.

**So, before this course ships** (agreed with the lead, 2026-10-29; it builds the base):
- Lesson 1's siren, up and down, two or three times, finds the flip: `flipPoint` takes the median of
  the **downward** jumps (the break comes higher going up than coming down). The siren's step returns
  `personal: { flip }` (semitones above the comfortable note), kept as the course's own personal
  value, `progress.courses.yodel.personal.flip`, synced, the newer winning.
- An exercise with `base: 'flip'` lays its steps from that note (the lesson view passes the base;
  until a flip is known it falls back to five semitones above the comfortable note, with a line
  asking for the siren first). The top note sits two semitones
  above the flip, the low note at least four semitones below it, so chest is never pulled to its edge,
  and nothing sits more than a fourth above the flip.
- **No flip found** ("That's fine, many voices blend"): a second siren with a cue ("lighter at the
  top, like an owl's hoo", never "louder"); still none, the flip is set to the owl hoot's pitch (the
  top note is where you can hoot lightly) and moved with Lower and Higher. **Flips spread over more
  than about four semitones:** take the lowest steady one, and say flipping in a few places is normal.
- The song tags (Home on the Range, Red River Valley) need a key that suits the flip and the
  comfortable note at once: the line is checked against both, or trimmed.

### The guide's voice

A synth that only jumps in pitch models "same voice, just higher", the very thing the self-check
warns against. Wanted: the guide's head-voice notes sounding purer and hootier than its chest notes
(a per-note `voice: 'head'` in the guide's synth: fewer, softer harmonics; the lead's, queued). Better still, recorded
clips from a real yodeler, added to the clips to record (below). Until one of these exists, Hear
steps say "Listen for the jump. In your voice, the top will also sound lighter."

## The lessons

Each lesson runs Why, Hear, Try, Do, Hear yourself, and opens with a short easy warm-up (a lip trill
or a quiet siren). The last scored step gives the stars. Syllables put open vowels on the low chest
notes and narrow ones on the high head notes: "yo-hee-o", "lo-ee-o"; high voices start the top on
"oo" before "ee", which can pinch.

### Unit 1: Find the flip

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **yodel-1-flip: Find the flip** (the free taste) | Finding the flip, then making it on purpose | Why: herders called across valleys with a voice that flips, and the flip is already in yours; a light flick, never a crack; medium volume. Hear: a speaking "ho", an owl's "hoo" up high, then the guide's siren that jumps ("Listen for the jump. Yours will do this too, and that's the point"). Try: your siren, up and down, quiet; the jump marked on your line: "There: that's your flip." Bridge, "now on purpose": a slide from just below the flip to just above, then the jump without the slide. Do: echo the hook "yo-hee-o" three times. Hear yourself: your siren's jump, then your hook. Ends: "Next: hear your two voices." | Pitch mostly, with the flips (nearly counts), generously |
| **yodel-1-two: Two voices** | Telling chest from head voice, and holding each | "ho" below your flip in chest voice, then "hoo" above it, light, 4 beats each. An accidental crack is greeted "You found it", never counted against you. Self-check: chest buzzes, head floats | Steadiness only, and the end says so |
| **yodel-1-hear: Flip or slide?** | Hearing a clean flip | Listen and choose, 6 rounds: which one flipped, which one slid? (the guide's glides) | The choices |
| **yodel-1-challenge: The hook in time** | Four hooks in rhythm | First slowly, a gap after each hook; then four in a row over a simple I and V backing. Then and now with lesson 1's hook | The flips and the pitch |

### Unit 2: The vowel switch

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **yodel-2-yodelay: Open below, narrow above** | The vowel change at the flip | "yo-hee-o", then "lo-ee-o", slow, echoed; then two hooks with the guide and two on your own (the guide drops out) | The flips |
| **yodel-2-down: The way down** | Landing lightly back in chest voice | Downward flips only, "hee-o", each landing at speaking volume; the result reports the way down on its own | The downward flips |
| **yodel-2-leaps: Sixths, octaves, fifths** | Flipping across different leaps | A sixth, then an octave, then a fifth (the smallest and hardest, last), echoed | The flips and the pitch |
| **yodel-2-challenge: Call across the valley** | Unit 1's hook in time, as calls | The guide calls from far off (a little echo on the guide); you answer with the hook in time over the backing, three calls, each longer. "The valley carries it, so you don't have to": medium volume | The flips and the pitch |

### Unit 3: Yodel in rhythm

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **yodel-3-chord: On the chord** | Yodeling on the chord's notes | The low note on the root, the high one on the chord's third or fifth, as I and V change | Pitch and the flips |
| **yodel-3-lope: A cowboy lope** | A lilting rhythm | The hook in a lope (long, short) over chords. Self-check: did it sway? | The flips and the pitch |
| **yodel-3-hear: Which one fits?** | Hearing whether a yodel fits its chord | Listen and choose: a yodel over a chord it fits, and one it clashes with | The choices |
| **yodel-3-challenge: Home on the Range** | A real tune with a yodel tag | The chorus's last line sung plainly, then our tag after it, with unit 2's vowel switch (the result names it). Then and now | Pitch on the line, the flips in the tag |

### Unit 4: Faster

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **yodel-4-roll: The roll** | Several flips on one breath | Low, high, low, high, low; at most four flips a breath | The flips |
| **yodel-4-ladder: Slow to quicker** | Keeping the roll clean as it speeds up | The roll at 60, 76, then 92 | The flips at 92 |
| **yodel-4-hear: Clean or smeared?** | Hearing a fast flip | Listen and choose: a fast clean roll and a fast smeared one | The choices |
| **yodel-4-challenge: Red River Valley, yodeled** | Everything, in a song | A line of Red River Valley, then a fast yodel tag; then and now with lesson 1's hook | Pitch, and the flips in the tag |

## Reading the course file

`course/plus/yodel/course.json` in the courses repo (hillelcoren/stackapella-courses), in the free course's shape, as Folk's (docs/courses/folk.md,
"Reading the course file"), plus:

- **`base: "flip"`** on an exercise: its `step`s count from the singer's own flip note (the
  course's `personal.flip`, semitones above the comfortable note), not from the comfortable note.
  Until the siren has found it, five semitones above, with a notice. The hook's top note is step 2,
  its low note step -7 (a sixth below); the octave's low note is -10, the fifth's -5.
- **`score: "flip"`**: half the stars for clean flips on every leap of four semitones or more
  between joined notes (a note starting where the last one ends), half for pitch.
- **`listenOnly: true`** on a note: the guide sings it, it's never sung or scored ("the guide
  drops out after two hooks").

## As built

- **Three short sirens, a breath between** (the trace lane's `path`, its ribbon 150 cents wide):
  two from a minor third below the comfortable note to an octave above, once up and down each, then
  a higher one, twice up and down from a minor third above to 19 above, lighter, "like an owl's hoo",
  for voices that flip higher (many men's flips sit 14 to 17 above). No breath holds more than four
  register changes. The sirens are the one place the course passes a fourth above the comfortable
  note: a light, quiet slide on "oo", never a note to hold, "only as high as feels easy".
- **The flip is kept only when two agree:** each siren's downward jumps of 2.5 semitones or more join
  `personal.flipSeen`; a flip is believed only 3 to 15 semitones above the comfortable note, with at
  least 3 semitones of reached voice above it; and `personal.flip` is set once two agree within 2
  semitones (`settleFlip`). The lines say where it flipped, that one more siren will make sure, or
  that no flip was found yet and that's fine.
- **Until a flip is found, every step set from it stars the notes only,** and says why: the fallback
  (five above the comfortable note) may not cross the singer's flip.
- **The hook from the flip note:** the top note two semitones above the flip (step 2), the low note
  a sixth below the top (step -7); the octave's low note is -10, the fifth's -5.
- **On the chord:** the key's home note sits two below the flip, so the hook's low note is sol and
  its top is mi over the I chord and fa over V7, both chord notes and both above the flip.
- **The song tags** sing their line from the comfortable note (Home on the Range's closing phrase,
  "and the skies are not cloudy all day", in D; Red River Valley's first line, in G with the
  comfortable note on A), then the tag from the flip note, in separate steps.
- **Flip or slide, clean or smeared:** the guide's slide is a `style: "slide"` on the hook's notes
  (styleBend's glide), so the listening rounds and the measure agree on what a slide is.
- Every lesson starts with a try or an easy first pass; the stars come from its last step. Flip
  lessons stay short on purpose: about five minutes of flips at a time.
- **No breath holds more than four flips:** in a run of hooks there's a beat's rest after every
  second one, and the tags' roll starts a beat after the hook.
- **The guide sings every note at or above the flip in head voice** (`voice: "head"`), in every
  step set from the flip note, listening clips included, so the model is never "the same voice,
  just higher".
- **Every flip result asks** "did the top sound lighter, like a different voice?", because the app
  hears the jump, not the register.
- **The challenges' backing** is the home chord (I) under the hooks; On the chord and A cowboy lope
  move between I and V7, the top moving from mi to fa with them.
- **The app says what it can't hear:** lesson 1, the siren's lines and a self-check after lesson 1,
  Two voices and each challenge ("Did the top sound like a different, lighter voice?").
- **Breaks:** a rest card at unit 2's third lesson, and "take a break" at each challenge.
- **Two voices** stars its last "hoo" on pitch only, gently (a new head voice wavers), then asks.
- **Trace the yodel** (unit 2): a `path` that jumps (two points at the same second), ridden on the
  lane, before the vowel switch is sung.
- **After launch, from the lead's last look:** accept flips above 15 (to 17) now that the high siren reaches 19, and ask for the agreeing flips to come from two different sirens, not two readings of one.
- **Not built, after launch:** the tag in the song's own key at the octave nearest the flip (it needs
  an engine base the app doesn't have; the tag is its own step from the flip for now); enforcing the
  five-minute flip cap (said in words); then and now, the clean streak, Your flip, the share clip,
  the valley's echo, the end-screen facts and "There: that's your flip" on the siren. The
  course-page safety note comes with the lead's catalogue `safety` field.

## Fun moments

- **"There: that's your flip."** Lesson 1 shows the jump your voice already makes, then you make it
  on purpose.
- **Call across the valley:** the guide calls from far off and you answer; the valley does the work.
- **Clean streak:** "5 clean in a row", counted only when both notes land and are held a beat,
  stopped at the five-minute flip cap. Never a fastest-flip record.
- **Your flip:** a portrait on Your voice, like Your vibrato: where your voice flips and how clean,
  filling in over the days (with the lead).
- **Share your hook:** five seconds of your own yodel, with the valley echo, on the existing Share
  card.
- **You just did that:** your first clean flip (after "a different voice"), your first tag on a
  real song, your first roll all clean.
- **Facts on end screens** (the content doc's checked list): Alpine yodeling began as herders'
  calls across valleys, written about by 1545; yodelers switch vowels, open below and narrow above;
  forest peoples of Central Africa have their own rich yodeled polyphony; Jimmie Rodgers's Blue
  Yodel records of the late 1920s brought yodeling into country music.

## Safe singing

- Said once on the course page and in lesson 1: the flip is a light flick that tips over, never a
  hard catch in the throat or a crack under pressure. Medium volume. Never slam back into a loud
  chest voice, again and again: that's what irritates the folds. Land every downward flip lightly, at
  speaking volume. Stop if it tickles, tightens or hurts; don't yodel when hoarse or ill; see someone
  if hoarseness lasts more than two weeks. No app replaces a teacher.
- **The head voice stays light,** never pushed up into a loud high note.
- **Warm up first:** every lesson opens with a lip trill or a quiet siren.
- **About five minutes of flips at a time;** then the course suggests a rest or an easy lesson. At
  most four flips on one breath. After 15 minutes of singing, the end screen suggests a rest and
  water.
- Nothing sits more than a fourth above your flip, and the low note never at the bottom of chest.

## Lesson types it uses

| Type | Status |
|---|---|
| `learn`, `try`, `play` with `turn`, `chords` | Exist |
| `choose` | Exists |
| `flip` as a `play` scorer | Built and registered |
| `wave` with a `path` target (the siren), and the guide's glides | The lead's, in its queue |
| `flipPoint` (mine), the personal `flip` and an exercise `base: 'flip'` (the lead's) | **Must-have**, agreed and queued |
| The guide's head-voice sound (`voice: 'head'` per note) | The lead's, with the glides in synth.js |
| The scorer's measured line; then and now; the guess; the clean streak | The lead's |
| A little echo on the guide (the valley) | Wanted, later |

## Clips to record later

From one real yodeler, dry, each about 3 seconds: a speaking "ho" and an owl "hoo"; the hook
"yo-hee-o" clean, and the same slid; a downward flip landed lightly; a fast clean roll and a smeared
one. Until then the guide sings them, with the head-voice sound when it exists.

## What the reviews changed

From the **voice-teacher review**: the flip note became a must-have before shipping (with the top a
fourth above the comfortable note, most men never cross their flip); the break's figures corrected
(a fourth to a sixth above for many women, about an octave for many men); the siren goes up and
down, the downward flips kept; no-flip and many-flip paths; chest and head contrasted inside lesson
1; leaps ordered sixth, octave, fifth; the measure reads arrival within a semitone, treats octave
slips as dropouts, and reports up and down apart; no milliseconds as a best; the self-check steers
the advice; syllables fixed ("yo-hee-o", "lo-ee-o", "oo" before "ee" for high voices); the missing
safety lines (warm-up, landing at speaking volume, "a light flick" not "a hiccup", four flips a
breath, two weeks' hoarseness, the 15-minute rest, no app replaces a teacher); the guide's
head-voice sound or real clips; changing voices wait.

From the **game-design review**: lesson 1 hears the jump first, marks it on your siren, bridges to
"now on purpose", and ends on hearing yourself; a path for "my voice didn't flip"; the quickest-flip
best replaced by a clean streak; the valley call framed so it never invites shouting; unit 2 gets a
guide-drops-out step and a lesson on the way down instead of three echo lessons in a row; the roll
comes before the speed ladder; every challenge brings back the unit before and has then and now; the
first challenge starts slow with gaps; Your flip, the share clip and Next lines to bring people back.

## Sources

The facts in the lessons (the content review of 2026-10-29), each with where to check it:

Since 2026-10-29 these facts are in each lesson's `fact` (the lesson end's "Did you know?" card,
with its source and link), one a lesson and at most two a unit; the learn cards they'd taken teach
again. Facts that are the lesson's own content stay in its headline (as marked below).

- **Chest voice: the vocal folds thick and short; head voice: thin and stretched** (yodel-1-two).
  Ingo Titze, *Principles of Voice Production*; [Vocal register](https://en.wikipedia.org/wiki/Vocal_register).
- **The Mbuti of the Congo and Hawaiian falsetto singers use the flip too** (yodel-1-hear).
  [Yodeling](https://en.wikipedia.org/wiki/Yodeling): "The Mbuti of the Congo incorporate distinctive
  whistles and yodels into their songs"; Hawaiian *ka leo kiʻekiʻe* exaggerates the break "as a yodel".
- **"Yodel" is from German jodeln, to utter the syllable "jo"** (yodel-2-down).
  [Yodeling](https://en.wikipedia.org/wiki/Yodeling); Duden, "jodeln".
- **Rousseau wrote that Swiss soldiers abroad were forbidden the Ranz des vaches, for homesickness**
  (yodel-2-challenge). His *Dictionnaire de musique* (1767, the 1768 edition is the one usually cited);
  [swissinfo: Ranz des vaches](https://www.swissinfo.ch/eng/culture/ranz-des-vaches/7685444);
  [HLS](https://hls-dhs-dss.ch/fr/articles/011889). A reported rule, so the card says "Rousseau wrote".
- **Jimmie Rodgers recorded Blue Yodel in 1927, mixing yodels with blues learned from Black railroad
  workers** (yodel-3-lope). [Blue Yodel No. 1](https://en.wikipedia.org/wiki/Blue_Yodel_No._1_(T_for_Texas))
  (Camden, New Jersey, 30 November 1927); [Jimmie Rodgers](https://en.wikipedia.org/wiki/Jimmie_Rodgers).
- **Western films of the 1930s and 40s made the cowboy yodel famous** (yodel-3-hear).
  [Singing cowboy](https://en.wikipedia.org/wiki/Singing_cowboy).
- **Switzerland's first yodeling festival, Basel, 1924** (yodel-4-ladder).
  [HLS: Jodel](https://hls-dhs-dss.ch/de/articles/011891). How often it has run since differs by
  source, so the card gives only the first.
Dropped on the courses lead's list: Home on the Range as Kansas's state song (Improvising's fact)
and Red River Valley's river (Perfect your pitch's), so unit 3 has two facts.

Also in the review: the warm-up card stays in each unit's first lesson and the challenges, its other
places now holding the facts; "did the top sound lighter, like a different voice?" stays in the first
lesson, the first challenge and the last, and "the app hears the jump in pitch" in the first lesson
and the first challenge, instead of in nearly every lesson.
