# Folk and early music: the course outline

Status: built, all four units, and live (courses-styles, 2026-10-29): it passed the lead's review,
and shows to staff until Plus courses open on launch day. The outline was reviewed by a voice teacher and a game designer, and the built file by a
third reviewer against its sources; their findings are folded in (the last section). The rules
every lesson is checked against are in [plus-courses-build-plan.md](../plus-courses-build-plan.md),
sections 3 and 6a; the course's first notes are in
[plus-courses-content.md](../plus-courses-content.md), "Folk and early music".

Back: [Plus courses](../plus-courses-plan.md) · [Docs guide](../../README.md#a-guide-to-the-docs)

## In short

- **The skill:** a plain, storytelling voice over a drone, the old scales (modes) that make a tune
  sound old, the small ornaments folk singers add, and singing a ballad on your own, with nothing
  underneath, without sliding off key.
- **Id and prefix:** `folk`, lesson ids `folk-<unit>-<name>`. Four units, 16 lessons, about 3
  minutes each, about 40 minutes in all.
- **Every tune is public domain** and set in the learner's key: the top note at most a fourth above
  their comfortable note, the lowest at most a sixth below it. Steps are written from the
  comfortable note, as Vibrato's are. Lines that don't fit are trimmed, never squeezed in.
- **Words for beginners:** the home note (the drone's note, where the tune comes to rest), the
  bright note (Dorian's sixth: one note higher than a minor tune would sing), the soft note
  (Mixolydian's seventh: one note lower than a major tune would sing). Each comes with a one-line
  picture the first time. A cent is said once: a hundredth of a semitone, the smallest step on a
  piano.

| Tune | Source to transcribe from | Used in |
|---|---|---|
| Drunken Sailor | A sea shanty, documented from the 1830s, Dorian; transcribed from The Session's version (tune 15961) | 1-crew, 2-dorian, 2-challenge, 4-challenge |
| Barbara Allen | The ballad is Child 84 (text only); the tune from Kidson, *Traditional Tunes* (1891), sung by A. Wardill of Goathland (bar 4's held note lengthened by an eighth to fill its bar) | 1-tell, 1-challenge, 3-flick, 3-turn, 3-challenge, 4-alone |
| Old Joe Clark | Appalachian, traditional (no known author), Mixolydian; only the "Fare thee well" chorus is sung, its last note held through the rest after it | 2-mixo |
| Ut queant laxis | A Latin hymn, words attributed to Paul the Deacon (8th century), which Guido of Arezzo used to name the notes (his letter, about 1030); its six syllables are sung, not its melody | 4-chant |
| Wondrous Love | American shape-note hymn: the melody from about 1701 (a cousin of the Ballad of Captain Kidd), the words in an 1811 songbook, joined in *Southern Harmony* about 1840, which printed it in natural minor; sung here with the raised sixth (B natural), as many singers do, from the abcnotation transcription; its first two lines | 4-lining |

Each tune is transcribed from its source before it's built, its range measured against the fit
rule, and its mode checked note by note. Wondrous Love's lines have the bright sixth but no third, so
they're not called Dorian: they're a tune that skips a note. Greensleeves was dropped: its mode depends
on the version, and the leading note many versions sing isn't Dorian.

## What the app measures here

Only what our tools truly hear:

| Measure | What it is | From |
|---|---|---|
| Pitch on each note | The note's centre against its target | `scoring.js` (exists) |
| A held note's length and steadiness | `score: 'hold'` | exists |
| Listen and choose | The answers | `choose` (the lead's, coming) |
| **Key drift** (new) | How far a line sung on your own has wandered from the key you started in | `keyDrift` in `web/js/style-scores.js` (built, tested) |
| **Intervals alone** (new) | Each note against the key you set yourself, so a first note a little low isn't wrong all the way through | the same |

**A plain tone is scored as a steady hold, not as straightness.** The voice teacher's point: a
±12-cent straight target fails anyone with a natural vibrato, and the usual harm is clamping the
throat to kill it. So the stars come from `hold` (on pitch, steady), a light wave still earns them,
and if a wave shows the result says "A light wave is fine. Softer, never squeezed."

**Not measured, so self-checked:** whether every word could be heard, the story's feeling, the
tone colour, and ornaments at real speed (under about a tenth of a second).

**Not used:** loudness. "Strong, not loud" is said in words, never scored or warned from the level,
because the browser's auto gain flattens it.

### Key drift, in numbers

- Each heard note's centre comes from `scoreTake` (cents from its target). Notes shorter than a
  quarter of a second (ornaments, passing notes) don't set the key.
- **The key's line** is a robust straight-line fit through those centres over time (Theil-Sen: the
  median of every pair's slope), so one wrong note, or a scale degree a singer always sings a little
  high, doesn't tilt it. **Your key** is the line at the first note, **where you ended** the line at
  the last; drift is the difference.
- **Intervals alone:** each note's centre minus the median of the five heard notes around it,
  judged at the course's standard tolerance. A line sung steadily 30 cents low is perfect
  intervals, a 30-cent key, and no drift.
- Stars on an unaccompanied step: 60% intervals alone, 40% the key held (full under 25 cents,
  falling to none at 100 cents, a semitone).
- The result: "You slid down 25 cents, about a quarter of a semitone. Singers on their own often
  drift low; think up on the falling notes."
- **The drone comes back only after the last note ends**, so you hear where you landed without the
  last note being pulled back to it.
- Every threshold is a named constant, tuned on the beta before stars depend on it; tested on
  per-note results and through the tracker on `sing()` with a slow slide.
- Exported for others: courses-skills may use `keyDrift` in Perfect your pitch. Its own `drift` is
  a single held note's sag (`pitch-skills.js`), a different measure.

## The lessons

Each lesson runs Why, Hear, Try, Do, Hear yourself (build plan 3.1). The last scored step gives the
stars, and its `measures` line says what they measured and names the next lesson in one line.

### Unit 1: The plain voice

Folk singers tell a story: the voice is plain and steady so the words come first, and a drone (one
note held underneath, like a bagpipe's) keeps you in tune.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **folk-1-crew: Join the crew** (the free taste) | Singing a real tune in tune over a drone | Why: a shanty kept a ship's crew pulling together, and a drone is one of music's oldest backings; strong, not loud, and keep the top notes light. Hear the guide sing the verse over the drone. Try: the first line only, with Wait for me. Do: the verse, echoing the guide line by line (`turn: true`). Hear yourself: your verse played with the guide singing along over the drone, a crew of two, and the notes named. Ends: "You just sang a shanty. Next: hear the wobble vanish." | Pitch, generously (two stars for a fair first try) |
| **folk-1-ring: Ring with the drone** | Hearing and singing a note truly in tune with a drone, plainly | Why: an out-of-tune note beats against the drone, a wobble in the sound; in tune, it rings. Hear it: notes closer and closer to the drone, the beats slowing until they vanish. Listen and choose, 4 rounds: which of two notes rings? (The out note is 45, then 30, 22 and 16 cents off, sharp and flat in turn; at a typical comfortable note of about 220 Hz that beats about 6, 4, 3 and 2 times a second, slower for lower voices, so the words say faster or slower, never a number.) Try: slide onto the drone's note. Do: find the note and hold it, 6 beats. The lesson is at the precise tolerance (within 20 cents), so a note that still beats doesn't count as ringing | A steady hold within 20 cents of the drone (`hold`, `strictness: "precise"`) |
| **folk-1-tell: Tell it** | Words in the rhythm you'd speak them | Why: a ballad is a story and each word gets its own time. Hear the guide sing Barbara Allen's first line the way it would be told. Try it at your own pace. Do: echo the first line, then the second, following the guide's telling. Then a self-check step: sing the first line once more, hear yours beside the guide's, and answer "Could a listener hear every word?" and "Did the long words get their time?" | Pitch on the second line, in the guide's rhythm (the self-check gives no stars) |
| **folk-1-challenge: Barbara Allen** | The plain voice through a verse | Barbara Allen's first verse over the drone, the long notes plain | Pitch (the result names a long note that wavered, from the scorer's own steadiness check) |

### Unit 2: Old scales

The modes are scales older than major and minor. One note changes, and a tune sounds centuries
old. Each starts from a tune, then points at its note, then the scale as a try.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **folk-2-dorian: The bright note** | Dorian: a minor tune with one note higher | Hear Drunken Sailor's third line again: the bright note is the second note of "drunk-en". Try: the scale from the home note up to the sixth note and back, in the tune's own key (the comfortable note on the fifth, so the sixth is a step above it). Do: the third line as plain minor would sing it (the dark sixth on "-en"), echoed; then as it goes, with the bright note, echoed | Pitch on the line with its bright note |
| **folk-2-mixo: The soft note** | Mixolydian: a major tune with one note lower | Old Joe Clark's "Fare thee well" chorus first, its soft note on "a-" of "away" pointed out. Try: the scale from the soft note below the home note up to the fifth, and back. Do: echo its first half, then sing the chorus (it fits whole: a sixth, the comfortable note on the third) | Pitch, the soft note among the notes |
| **folk-2-name: Name that mode** | Telling the modes apart by ear | Sing each first: the Dorian scale, then the Mixolydian, echoed over the drone. Then listen and choose, 8 rounds, every round two answers: minor or Dorian, then major or Mixolydian, each pair differing only in the deciding note; the two mixed rounds are one contour (the whole scale up, then down), so only the notes tell them apart. Replay is always there, and the why names the note | The choices |
| **folk-2-challenge: All hands** | A modal tune end to end, on your own over the drone | Drunken Sailor's verse with no echo now, the bright note on pitch, the long notes plain. Listen back for the long notes; then and now when the lead's feature lands | Pitch (the long notes' steadiness is for you to hear) |

Naming the bright or soft note's own tuning on the result ("Your 'drunk-en' was 20 cents low") waits for a
scorer's measured line on the take result, asked of the lead; until then the `measures` line says
the stars include it.

### Unit 3: Ornaments

Small decorations folk singers add: a flick from the next note up, and a turn around a note. Each is
learned by echo inside a real line, first as a slow practice version, then tried at speed.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **folk-3-flick: A flick from above** | A quick touch of the next note up before the main one | Why: light, from the breath, never the jaw (a fingertip on the chin). Hear the guide sing "In Scarlet Town" with flicks on "Town" and "born", at a slow practice speed: "Real flicks are under a tenth of a second. We practise a slow one." Try it at your own pace (Wait for me). Do: echo the line, each flick a quarter of a second, then once more a little quicker (72), which gives the stars. The end says to speed it up once it's easy (a try at speed would wait on each note, so it isn't one) | Pitch on every note, at the relaxed tolerance so a short note's wobble doesn't fail anyone |
| **folk-3-turn: The turn** | Above, the note, below, the note | The same shape, on "youth" in Barbara Allen's second line, then once more a little quicker | Pitch on every note, relaxed |
| **folk-3-spot: Spot the ornament** | Hearing where an ornament sits | Listen and choose, 5 rounds: one line heard, three of its words to pick from (flicks on "Town", "born", "Scar-" and "day", the turn on "youth"). A flicked line sung first as a try (unscored, so the rounds are the score), then the rounds | The choices |
| **folk-3-challenge: Decorate a ballad** | Ornaments where they fit, the plain voice between | Barbara Allen's verse, a flick on "Town" and a turn on "youth", at the practice speed, over the drone | Pitch on every note, relaxed; Listen back asks whether the long notes were plain (unit 1) |

Plus lessons are judged at the standard tolerance (35 cents), key drift included. The ornament
lessons, like lesson 1, set `strictness: "relaxed"` (50 cents): short notes wobble, and the free
taste is generous. If M6 gives a per-note tolerance,
the main notes go back to standard and only the ornament stays relaxed.

### Unit 4: On your own

Folk ballads were often sung with nothing underneath, and early chant was one melody with nothing
underneath. This unit takes the drone away, a little at a time.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **folk-4-chant: Where do re mi come from** | Stepping up the scale, the half step in tune | Why: about a thousand years ago Guido of Arezzo taught singers the notes with Ut queant laxis, whose first six half-lines each start one note higher; he named the notes after their first syllables (ut, re, mi, fa, sol, la; "ut" became "do" in 17th-century Italy). Hear the six syllables up over a soft drone on "ut", the comfortable note on mi; mi to fa is the half step. Try them up. Do: echo them up, then up and back down. Low notes easy, never pressed down | Pitch |
| **folk-4-lining: Lining out** | Singing a line back with no drone | Why: lining out is how many congregations sang before they had books, a leader singing each line and everyone singing it back; this hymn comes from a later shape-note book (about 1840), and shape-note singing is famous for singing loud, so sing it at a medium volume. Echo Wondrous Love's first line, then its second, then both, with no drone: the leader's line gives you the key | Pitch on both lines |
| **folk-4-alone: On your own** | Holding the key with nothing underneath | Barbara Allen's first line echoed over the drone, then the same line with the drone fading away as you sing it, then the whole verse on your own, with Give me my note there if you lose it: the guide gives your first note (a listen-only note, never scored), and after your last note it sings the home note, so you hear where you landed | Intervals alone and key drift (`score: "keyDrift"`) on the verse |
| **folk-4-challenge: Encore** | Everything: plain, modal, decorated, on your own | Drunken Sailor, lesson 1's tune, heard once with no drone, then sung on your own from a given first note to the guide's home note after it, a flick on "morn-". "Then: with a guide and a drone. Now: on your own." | Intervals alone and key drift |

**Ut queant laxis's own melody isn't sung:** no source we could check gives its notes as text, and
it isn't transcribed from memory. The lesson sings what Guido took from it, the six syllables on
their notes. **Wondrous Love's shape-note syllables aren't sung either:** which syllable each note
takes depends on how the book wrote its key, so the lesson sings the words, lined out.

**Built on what exists now; better when these land** (with the lead and the frontend):
- **A starting note before a take with no drone:** now there (courses-harmony's listen-only notes):
  the two unaccompanied verses start from a given note and end on the guide's home note.
- **The fading drone** and **Give me my note**: now there (`drone.fadeAfter` and `fadeOver`, and
  `giveNote` on the two verses sung on your own); the note is played quietly, once, and never costs
  stars.
- **The drift sentence on the result** ("You slid down 25 cents, about a quarter of a semitone"):
  the scorer's measured line, agreed with the lead and queued for its `exercise.js` turn. Until
  then the `measures` line says what the stars were for.
- **The guess before the result** ("Did you go low, high, or stay?"), once registered steps can read
  an earlier step's result.
- **The lane following your own key:** the stars already judge your own key; the live lighting
  still uses the written key, so a singer who starts low sees fewer lit notes than their stars.

## Reading the course file

`course/plus/folk/course.json` in the courses repo (hillelcoren/stackapella-courses) is the course, in the free course's shape. For anyone fixing a lesson:

- **`step`** on a note is semitones from the learner's comfortable note (0), so every tune sits in
  each learner's key. A clip's step may be a fraction (Ring with the drone's `0.45` is 45 cents
  sharp).
- **`drone: { step }`** is the note held underneath: the tune's home note.
- **`turn: true`** plays the notes, then the learner sings them back (an echo); `false` sings along
  at once, after the lead-in written into the first note's `beat`.
- **`strictness`** on a lesson: what counts as in tune. Plus lessons default to standard (35
  cents); `"relaxed"` (50) for lesson 1, the free taste, and the ornaments; `"precise"` (20) for
  Ring with the drone, where a note that still beats mustn't count.
- **`selfcheck`** steps: hear the model, record, hear both, answer the questions; no stars.
- **`score`** on a `play` step picks the measure: `hold` (a held note's length and steadiness) or
  `keyDrift` (a line sung on its own; `web/js/style-scores.js`). Without it, the notes' pitch.
- **`choose`** rounds: `clips` to hear, `answer` the index of the right clip, `why` said after.
- **`drone: { step, fadeAfter, fadeOver }`**: the drone fades to silence from `fadeAfter` beats
  over `fadeOver` beats. **`giveNote: true`** on a step: a Give me my note button while you sing.
- **`listenOnly: true`** on a note: the guide sings it and it's never sung or scored (the starting
  note and the home note around an unaccompanied verse).
- **`measures`** is the line under the result saying what the stars were for.
- The tunes' sources are in the table at the top; a fix to a tune's notes should say its source.

## Fun moments

- **You're the crew:** lesson 1 is a shanty sung back line by line, and you hear yourself sing it
  with the guide (Listen back's "With the guide", which the frontend is reviewing).
- **The beats vanish:** Ring with the drone makes tuning something you can hear, and the scored
  step is that moment.
- **Name that mode:** a quick two-answer ear game, with replay, and a why naming the deciding note.
- **Then and now:** the same tune at lesson 1, at unit 2's challenge (with the drone), and at the
  end (on your own). The `measures` lines ask you to listen back; playing lesson 1's take beside
  today's comes with the lead's then-and-now.
- **Did you stay in key?** You guess before we show you (once the plumbing above lands).
- **You just did that** (four in the course, when the lead's moments can name them): your first note that rings with the drone (steady
  and within 10 cents for two seconds), naming four modes in a row, your first verse with every long
  note steady, and your first verse on your own ending within 25 cents.
- **Facts on end screens**, only checked ones: Cecil Sharp found old ballads still sung in the
  Appalachians in 1916 to 1918; Dorian is minor with a raised sixth; shape-note singing; many folk
  tunes skip a note or two of the scale, so they sit well over one drone; Guido and Ut queant laxis
  (Britannica, "solmization").

## Safe singing

- The course page and lesson 1's Why say it once: a plain tone is a relaxed sigh, never a held or
  squeezed throat; sing at a medium volume in the middle of your voice; stop if anything tightens,
  tickles or hurts; don't sing when hoarse or ill, and see someone if hoarseness lasts more than
  two weeks. No app replaces a teacher.
- **Shanties and shape notes invite shouting.** Their lessons say "Strong, not loud" and "medium,
  not loud", and lesson 1 says to keep the top notes light.
- **Ornaments come from the breath**, light and small; the jaw stays still.
- **Low chant notes are easy, never pressed down.**
- Holds are at most 8 seconds; every tune sits within the fit rule above. After 15 minutes of
  singing in a sitting, the end screen suggests a rest and some water (the course engine's, as in
  Vibrato).

## Lesson types it uses

| Type | Status |
|---|---|
| `learn`, `try`, `play` with `drone`, `turn`, `strictness`, `score: 'hold'` | Exist, used |
| `choose` (with clips or with words to pick) | The lead's, used in units 1 to 3 |
| `keyDrift` as a `play` scorer | Built and registered (`lesson-scores.js`), used in unit 4 |
| Listen back "With the guide" | The frontend's; I send the lines after the lead's turn in that file |
| The scorer's measured line, a starting note, the fading drone, Give me my note, the guess | Queued with the lead and the frontend (unit 4 works without them) |

## Later

- **Organum, the first written harmony:** a parallel fifth below a chant line, once
  courses-harmony's partner lines (`with`) land.
- **A songbook:** each tune you've learned, to sing again freely over its drone.
- **Your drift:** your key drift on unaccompanied verses over the days, as a portrait, like Your
  vibrato.
- **Remember this?** with folk personal bests (seconds ringing with the drone, a mode streak), once
  the lead builds it.
- A drone with its fifth (a bagpipe's sound), and real-voice clips for the listening games.

Every challenge (and the encore) starts with a try at your own pace before the scored take.

## What the reviews changed

From the **lead's quality review**: Ring with the drone at the precise tolerance (a note 45 cents
out used to earn full stars at the relaxed one Plus lessons had); the relaxed lessons checked (lesson
1 and the ornaments keep it); Tell it says to follow the guide's telling, with the promised self-check
added; Name that mode's mixed rounds share one contour, and the lesson sings each mode first;
Dorian is starred on the bright note itself (plain minor, then Dorian), not lesson 1's lines again;
lesson 1's safety words whole on a card and its headline shorter; lining out's history put right;
All hands' stars said plainly as pitch; the flick and the turn a third pass; the sources named; "ah",
not "home", on the given note.

From the **review of the built file**, against its sources: Barbara Allen's "Bar-ba-" had been set
an octave too high (the C# below read as the one above), in five steps, one of them scored; it's
the C# below now. The lessons were too thin for 2 to 5 minutes, so the told line gained its second
line, Dorian and Mixolydian an echo first, the flick and the turn a quicker second take, lining out
both lines together, and every challenge a try first; the catalogue says about 40 minutes, from the
steps. Ring with the drone's scored hold no longer asks for a slide (the slide is in its try). The
measures lines no longer promise then and now before it exists, and each names the next lesson.
Lesson 1's Why carries the whole safety line; Dorian's take says "top notes light"; the decorated
verse says "jaw still". Name that mode's two mixed rounds now say the third decides them. Words
for beginners: the home note defined where it first appears, "plain minor with one note raised",
"three quick notes, then land", "up to the sixth note", and Ut queant laxis named.



From the **voice-teacher review**: the bright note in Drunken Sailor is on "drunk-" in the third
line, not "ear-" (the seventh); key drift became a robust line fit with short notes left out, and
the drone comes back only after the last note; the plain tone is scored as a steady hold, not
straightness, so nobody clamps their throat to kill a vibrato; scales and the chant set so they fit
(Dorian 1 to 6 with your note on 3, Mixolydian from the soft note below, Ut queant laxis with your
note on mi) and a floor rule (at most a sixth below); Greensleeves dropped (version-dependent
mode); Wondrous Love not called Dorian unless its printing shows it; Tell it scores pitch only; the
listening rounds set by beats a second, not cents; the result's maths and words fixed ("low", a
cent explained); the missing safety lines (hoarse or ill, two weeks, the 15-minute rest, shape
notes at medium, low notes not pressed); the facts corrected (one of the oldest backings, half-lines,
about 1030, attributed, 17th-century Italy, Child printed the text, singers often drift low).

From the **game-design review**: lesson 1's try is one line and its playback is you with the guide,
a crew of two, ending "You just sang a shanty" with a bridge to the next lesson; the plain-tone and
listening lessons merged into Ring with the drone, scored on the moment the beats vanish; unit 3
taught by echo inside real lines; Dorian opens on the tune, not the theory, and its choose rounds
went to Name that mode, every round two answers with replay; unit 4's cliff became a fading drone,
a Give me my note button and a guess before the result; the last challenge is lesson 1's tune on
your own; a dishonest "you just did that" (an unscored ornament) replaced; every lesson bridges to
the next. Not taken: a level-based shouting warning (auto gain makes it unreliable).

## Sources

The facts in the lessons (the content review of 2026-10-29, for Hillel's "make sure interesting facts
are included"), each with where to check it:

Since 2026-10-29 these facts are in each lesson's `fact` (the lesson end's "Did you know?" card,
with its source and link), one a lesson and at most two a unit; the learn cards they'd taken teach
again. Facts that are the lesson's own content stay in its headline (as marked below).

- **Shanties: a shantyman sang the line, the crew answered as they hauled** (folk-1-crew, its headline). Stan
  Hugill, *Shanties from the Seven Seas* (1961); [Sea shanty](https://en.wikipedia.org/wiki/Sea_shanty).
- **In Byzantine chant, some voices hold one low note, the ison, under the tune** (folk-1-ring).
  [Ison (music)](https://en.wikipedia.org/wiki/Ison_(music)): "a drone note, or a slow-moving lower
  vocal part, used in Byzantine chant", its singers the isokratima. (The beats and the tanpura are
  Perfect your pitch's.)
- **Pepys heard an actress (Elizabeth Knipp) sing Barbara Allen in 1666** (folk-1-tell). His diary,
  2 January 1665/66: [pepysdiary.com](https://www.pepysdiary.com/diary/1666/01/02/). The tune she
  sang isn't known to be today's (Latham and Matthews' edition), so the card says only the ballad.
- **Medieval Dorian took the Greek name for a different scale** (folk-2-dorian).
  [Dorian mode](https://en.wikipedia.org/wiki/Dorian_mode); Grove Music Online, "Mode".
- **The Highland bagpipe's scale has the soft (lowered) seventh** (folk-2-mixo).
  [Great Highland bagpipe](https://en.wikipedia.org/wiki/Great_Highland_bagpipe), its chanter's scale.
- **Sean-nós singers vary their ornaments from verse to verse** (folk-3-spot).
  [Sean-nós singing](https://en.wikipedia.org/wiki/Sean-n%C3%B3s_singing); Tomás Ó Canainn,
  *Traditional Music in Ireland* (1978).
- **Guido's Ut queant laxis; "ut" became "do" in 17th-century Italy**
  (folk-4-chant, its headline and card). [Solfège](https://en.wikipedia.org/wiki/Solf%C3%A8ge);
  [Ut queant laxis](https://en.wikipedia.org/wiki/Ut_queant_laxis).
- **Lining out began in 17th-century Britain, when few in church had books** (folk-4-lining, its
  headline), **and lives on in Gaelic psalm singing in the Hebrides** (its fact).
  [Lining out](https://en.wikipedia.org/wiki/Lining_out).

Softened in the same review: "real flicks are under a tenth of a second" (no source gave a figure)
is now "much quicker"; "singers on their own often drift low" is now "often drift" (the direction
isn't settled).
