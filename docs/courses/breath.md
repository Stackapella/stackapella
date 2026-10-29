# Breath and stamina: the course outline

Status: built, all 15 lessons (courses-skills, 2026-10-29), coming soon until the lead's last look.
A voice-teacher review and a game-design review are folded in (section 8), and the lead's quality
review after the build (section 10). In the course file, `step` means semitones from the learner's
comfortable note. The course's facts are in
[plus-courses-content.md](../plus-courses-content.md) (Singing skills, 5); the rules for building
it are in [plus-courses-build-plan.md](../plus-courses-build-plan.md), section 6a.

Back: [Plus courses](../plus-courses-plan.md)

---

## 1. What it teaches, in one line

Breathe low and wide, let the air out slowly so a note stays steady **to an ending you choose**,
let the breath turn straight into a note, and catch quick breaths between phrases without being
late or going flat. The free course has one breathing lesson (the flower) and a hold ladder to 8
beats; this course is about the ending, the ribs and the phrase, not about holding longer.

- **Course id** `breath`, lesson prefix `br`, colour 4, picture `breath` (the free course's flower).
- **4 units, 15 lessons, about 3 minutes each.**
- Every note is around the learner's comfortable note (never more than a fourth below or a fifth
  above), easy and medium-soft.
- **Length is never the goal.** Lesson 1 sets your own baseline (the steady seconds of your first
  hold); later goals are your baseline plus a few seconds, with no floor and never over 15.
  Longer than that is a "You just did that", never a star gate.

## 2. What the app measures (and what it doesn't)

Through `pitch-skills.js` (built: landing, centre, drift) and four new scorers there:

| Scorer | What it is | Stars |
|---|---|---|
| **sustain** (new) | One hold: its **steady seconds** (on pitch within ±50 cents, steady on the rolling median, gaps under 0.12 s bridged) and its **tail**: did the last second stay steady (you stopped on purpose) or sag, crack or wobble (the air ran out)? The take ends half a second after you go quiet | The tail and steadiness first; the step's goal (your baseline plus a little) is met or not, and going past it earns nothing more |
| **release** (new) | When a note stopped, against when it should have | Within ±120 ms of the written end (the breath taken on time) |
| **entry** (new) | When a note after a breath started, against the beat | Within ±80 ms of your own usual offset (the scorer's rule) |
| **line** (new) | A phrase: landing on its notes, then its last note's tail | Landing 70%, the tail 30% |
| **landing** | The settled start, on pitch from the first moment (built) | As in Perfect your pitch |

**Not measured, never starred:** the shoulders, ribs or belly, the size of a breath, a breathy,
pressed or hard (glottal) start, loudness (the browser's auto gain flattens it), whether a lip
trill really bubbles. A start "on pitch from the first moment" is all landing can say; a hard
attack can land fast and a breathy one can land perfectly, so **no result ever calls a start
"clean"**. Those get `choose` rounds (hear the difference) and self-checks instead.

**Voiced sounds only:** a hiss has no pitch, so the steady-stream lessons use "vv", "zz", "mm" and
the lip or tongue trill. **To check on the beta before unit 2 ships:** that the tracker follows
"zz" and a lip trill without gaps over 0.12 s; if not, those become "vv" and "mm".

## 3. The lessons

Each lesson runs **Why, Hear, Try, Do, Hear yourself**. Hear yourself leads with the ending:
"Steady to the end, and you stopped while it was easy: 11.4 steady seconds." Between holds the
screen says "Breathe normally" for at least three easy breaths (about 15 seconds), with no
countdown (a countdown makes people breathe in early and hold it).

**Every unit challenge starts with the same 8-second "oo" on do**, compared with your first one
on steadiness and the tail, never on length (then and now, like with like).

### Unit 1: One easy breath

| Lesson | The skill | Why, Hear, Try, Do | Stars from |
|---|---|---|---|
| **br-1-long: The long note** (the free taste) | A long note that ends on purpose | Warm up: 15 seconds of humming slides. Why: a long note isn't about big lungs; it's letting the air out slowly and stopping while it's still easy. Hear a note that runs out (it sags and cracks) beside one that ends on purpose. Do 1: hold "oo" over the drone as long as it's easy, no goal (this is your baseline). A tip from it ("Your note sagged in its last second: stop a moment sooner, and breathe low"). Do 2: again, with the bar showing your baseline. Hear yourself: both bars, "2.1 more steady seconds, and steady to the end." Ends: "Next: a breath that lasts." | sustain, generous, on Do 2 |
| **br-1-wide: Keep the ribs wide** | Low, wide breath (appoggio) | Why: good support slows the ribs from collapsing; singers call it appoggio, "leaning". Hands on your lower ribs at the sides: belly, sides and back widen as you breathe in, shoulders stay down. Try: a big breath (not your biggest) and sing at once, then a normal one: which felt easier? Do: hold "vv" to your baseline, keeping the ribs wide. Self-check: "Did your ribs stay wide? Your shoulders down?" | sustain |
| **br-1-song: A long line** | Holding the long notes in a real tune | Why: in songs the long notes come at the ends of lines. Hear the line. Try it. Do: the chorus of Swing Low, Sweet Chariot, slowly, breathing at the marks | line |
| **br-1-challenge: The easy hold** | A longer hold, still ending on purpose | Then and now; then hold "oo" to your baseline plus 2 seconds | sustain |

### Unit 2: The slow stream

| Lesson | The skill | Why, Hear, Try, Do | Stars from |
|---|---|---|---|
| **br-2-buzz: The buzz** | An even flow of air | Why: a buzz shows whether the air comes out evenly. If a lip trill stops, keep the air flowing and the lips loose; fingertips at your cheeks help. Hear "vv", "zz" and a lip trill. Try each. Do: 10 seconds of your pick (or "mm" or a tongue trill) on your note | sustain (the bubble is a self-check) |
| **br-2-hill: Trill the hill** | Even air through a moving line | Why: the air stays even while the pitch moves. Try with the guide, then Do: on a trill or "vv", in one breath, do up to so and back (stepped notes until the trace lane's path exists) | centre on each note |
| **br-2-end: End on purpose** | A note that stops on time, still steady | Why: the end of a breath lets pitch fall; plan the ending instead. (Listen and choose, "Which one ran out of air?", once the real-voice clips are recorded.) Try with the guide; Do: a 6-beat hold on do over the drone, stopping exactly at its end | release |
| **br-2-challenge: Your own chord** | Three long holds that stack | Then and now; then hold do to your goal, then mi over your do, then so over both: a chord made of your breaths | sustain on each |

### Unit 3: Starting on the breath

| Lesson | The skill | Why, Hear, Try, Do | Stars from |
|---|---|---|---|
| **br-3-onset: Breath into note** | The breath turning straight into the note | Why: breathe in and let the breath turn into the note with no hold (a pause after breathing in usually means the throat has closed). Think an "h", don't sing it. Listen and choose, 6 rounds: a hard start, a breathy one and a balanced one (only heard, never practised hard). Try soft starts too: soft isn't whispery (whispering can tire the voice more than singing softly). Do: six "ah"s, a breath before each. Self-check: "Balanced, breathy or hard?" | landing ("your starts were on pitch from the first moment") |
| **br-3-bounce: Short and bouncy** | Short notes from the breath | Why: short notes start with a small bounce low in the belly; the throat stays open. Do: "ha ha ha" on do-mi-so-mi-do at 80, eighth notes (about 375 ms each) | landing |
| **br-3-challenge: Ah, vous dirai-je** | A line on the breath, ending held | Then and now; then the French tune Mozart wrote variations on (known in English as Twinkle, Twinkle), its first line on "dah", detached, a breath at the halfway, the last note held | line |

### Unit 4: Catch breaths

| Lesson | The skill | Why, Hear, Try, Do | Stars from |
|---|---|---|---|
| **br-4-catch: The quick breath** | A breath in half a beat, back on time | Why: the breath comes out of the last note, not the next one: shorten the note before, drop the jaw, and let the air in through the mouth, silently. Do: four 2-bar phrases at 80, half a beat (0.4 s) between | release, then entry |
| **br-4-plan: Plan your breaths** | Breathing where the music lets you | Why: breathe at the commas, not when you run out. Hear Simple Gifts' first two lines, the breath after "free" marked. Try with the guide. Do: the two lines, breathing at the mark | entry |
| **br-4-turns: Breathe in turns** | Stagger breathing, as choirs do | Why: a choir holds a long chord by breathing at different moments. Do: hold do for 4 bars, breathing at bar 2; then hold mi over your recording, breathing at bar 3. On playback, the chord never breaks | entry after each breath, and centre |
| **br-4-challenge: The long phrase** | Long phrases with quick, planned breaths | Then and now; then Simple Gifts' first verse at 84, with a quick breath before each line | entry |

## 4. The fun

- **The breath bar**: a hold fills a bar in your part colour. A steady stop past your goal seals it
  with a glow and the special sound; glass marks only the wobbly stretches. Stopping is the win.
- **Two tries and a tip** in the free lesson, so the learner hears themselves get better in three
  minutes.
- **Your stamina**: "Steady to the end: 8 of your last 10", with your longest steady hold shown
  capped ("20 s is the top"). It counts only steady seconds, so pushing past easy never pays.
- **Your best to beat** on replay: steadiness and the tail, never seconds.
- **"You just did that"**: your first note steady to its very end (lesson 1 reliably earns it),
  your first 16 and 20 steady seconds ending on purpose, a verse with every breath on time, the
  unbroken chord in Breathe in turns, each unit's challenge.
- **Later, with the lead's `review` kind**: a 90-second daily breath (a flower breath, 6 seconds of
  "vv", one easy hold to your goal) as a sitting's warm-up, skippable, feeding the portrait.

## 5. Safe singing

On the course page and lesson 1:
- Sing easy and medium-soft, tall and easy, chest comfortably high. Breathe low and wide, never
  gasp. Stop while it's still easy; never squeeze out the last drop.
- Never hold your breath before singing, never lock your stomach, never hold until you're dizzy.
- If you have asthma or another lung or heart condition, or are pregnant, keep holds short and
  easy. A cough, wheeze, tight chest or lightheadedness means stop. Sit for long holds if you've
  ever felt lightheaded.
- Don't practise when hoarse or ill. See someone if hoarseness lasts more than two weeks. No app
  replaces a teacher.

In the lessons: at least three easy breaths between holds; at most three long holds in a row;
goals capped at 15 seconds; the hard start is heard, never practised; the tail rewards stopping
while steady, so pushing to the end costs stars.

## 6. What it needs that isn't built

| Need | Owner |
|---|---|
| `sustain`, `release`, `entry` and `line` scorers | me, in `pitch-skills.js` |
| A personal baseline per course (the first hold's steady seconds), readable by the scorer, and the hold's goal from it | the lead (the scorer's arguments) and `progress.courses.breath` |
| The take ending half a second after you go quiet, on a hold | the lead or the frontend (the exercise) |
| The breath bar (sealing glow, glass for wobbles) | me, as a hold picture, or the lead's straight-target lane |
| `choose` (ran out of air, hard or breathy or balanced) | the lead |
| The trace lane's `path` (Trill the hill) | the lead |
| An earlier step's take played back (Your own chord, Breathe in turns) | the lead; the guide holds the lower note until it lands |
| The stave with breath marks (Plan your breaths) | me, `stave-step.js` |

## 7. Songs and their status

All public domain, in our own arrangements: "Swing Low, Sweet Chariot" (spiritual, 1860s),
"Ah! vous dirai-je, maman" (French tune, 1761), "Simple Gifts" (Joseph Brackett, 1848, from the song library's checked
melody; in place of Michael, Row the Boat Ashore and Aura Lee, whose melodies aren't in the library). Every tune's span is checked against the
course's limit (a fourth below to a fifth above your note) when it's written into steps; one that
doesn't fit is swapped, never squeezed.

## 8. What the reviews changed

**Voice teacher:** no fixed 12, 16 and 20 second minimums (normal maximum phonation time is about
15 to 25 seconds at full effort, less with age): goals are personal and capped at 15, with longer
holds as moments; Hear yourself compares steady seconds, not raw length; "clean starts" are never
claimed from landing ("on pitch from the first moment"), and balanced, breathy and hard starts are
a listening round plus a self-check; the "tiny pause" before a note removed (it teaches a closed
throat) for "let the breath turn into the note, think an h"; the lip trill's Why corrected (it
stops from too little air or tight lips) with a tongue trill and "mm" as alternatives; breath
size and shoulders never starred (Not too much folded into Keep the ribs wide as an unscored try,
"a big breath, not your biggest, sing at once"); appoggio taught (Keep the ribs wide); staccato
from "a small bounce low in the belly, throat open", notes at least about 300 ms; catch breaths
through the mouth, silently, shortening the note before, with the release scored; a warm-up before
the first hold; holds easy and medium-soft; the safety lines for asthma, heart conditions,
pregnancy, lightheadedness, hoarseness and three breaths between holds; Auld Lang Syne and Loch
Lomond dropped (each spans about a ninth); the tracker to be checked on "zz" and trills.

**Game designer:** stopping is the win (the bar seals with a glow on a steady stop, glass only for
wobbles, the take ends when you go quiet); goals personal and titles without numbers; the portrait
counts "steady to the end", the longest capped; unit 2 varied (The buzz merges zz and trill, Trill
the hill on a path, End on purpose with a "which one ran out of air?" round, Your own chord as the
challenge); Breathe in turns added (stagger breathing, the app's own trick); a song in unit 1;
Soft starts folded into Breath into note; Twinkle reframed as the French tune Mozart varied;
then and now as the same 8-second "oo"; a best to beat in steadiness; the free lesson's Hear as
"runs out versus ends on purpose", a moment it reliably earns and a bridge to lesson 2; a daily
breath for later.

## 9. Reading the course file

The lessons are `web/course/plus/breath/course.json` (see `web/course/plus/README.md`), in the
free course's shape. Lesson ids never change once landed. Beyond `learn`, `try` and `play`, the
course's own step is:

- `breathhold`: a long note over a drone (`web/js/components/breath-hold.js`). The take ends half
  a second after you go quiet, so stopping is the win. Its `exercise`:
  - `step`: the note, in semitones from your comfortable note (0 is do).
  - `lyric`: the sound to hold. Only voiced sounds the pitch tracker can hear: `oo`, `ah`, `vv`,
    `zz`, `mm`, `brr` (a hiss has no pitch).
  - `drone`: `{ "step": 0 }` for a drone on that note (with headphones it stays under the hold;
    through a speaker it plays only before you sing).
  - `lead`: seconds of drone before you sing (default 2); `max`: the longest the take runs, in
    seconds (default 20).
  - `baseline`: true on the first hold: its steady seconds become your baseline (kept in the
    course's personal values and synced).
  - `goal`: `{ "plus": 2 }` sets the goal at your baseline plus that many seconds, never over 15.
    Without it, or before a first hold sets a baseline, there's no goal.
  - `compare`: `{ "step": 1 }` marks that earlier step's steady seconds on the bar (this visit), or
    `"baseline"` marks your baseline.
  - `generous`: true in the free lesson only.
  - `keep` and `thenAndNow`: the first hold is kept on this device (`"breath.first"`), and the
    challenges' holds show it beside today's, comparing how much was steady and how it ended, never length.
  - `under`: `{ "steps": [3, 4] }` plays those earlier steps' takes beneath this one (with
    headphones), so your own holds stack into a chord.
  - Stars: `sustain` in `web/js/pitch-skills.js`: how the hold ended (half), how steady it was
    (30%) and the goal met (20%, nothing more for going past it).
- `play` with `score: "line"`: a phrase, starred on where its notes landed (70%) and its last note
  ending steady (30%).
- `play` with `score: "entry"`: each note after a rest (a breath), starred on when its first sound
  came against the beat (60%, full within 0.08 s) and where it landed (40%).
- `play` with `score: "release"`: a note that ends on time, starred on when it stopped against its
  written end (60%, full within 0.12 s) and its ending steady (40%).
- A mid-lesson `learn` step with no `listen` is a rest: "Breathe normally" between long holds.

The rules every lesson keeps are checked by `web/tests/breath.test.js`.

## 10. What the lead's quality review changed

- **Safety in the app:** a card before the first hold (never hold your breath first, don't lock your
  stomach, stop if dizzy, wheezy or tight, take care with asthma, a heart condition or pregnancy,
  not when hoarse or ill), and a rest card ("Breathe normally for three easy breaths", no
  countdown) between holds: The long note, Keep the ribs wide (its two holds now separate steps) and
  Your own chord (its then and now dropped so the lesson fits five minutes).
- **Goals have no floor:** your own first hold plus a little, never over 15 seconds, and no goal at
  all until a first hold sets one (a floor of 8 would push an easy 5 seconds past easy). The buzz
  has no extra seconds (a trill uses more air).
- **The ending** is read before the release's natural fall (the last 0.2 s), its sag on the last half
  second; a test covers a realistic release. **To check on the beta with real voices**, with the
  tracker on "zz" and a lip trill, before the buzz and the hill ship.
- **Entries after a breath** skip the first note, earn nothing when the voice never stopped for the
  breath, and are judged against the singer's own usual offset (up to 0.1 s: a consonant like the t
  of "'Tis" starts unvoiced). Releases and entries say what they measured ("You stopped 0.3 s
  early. Steady to its end.", "Back in 0.2 s late after your breaths, on average").
- **Through a speaker** every hold has a drone to sing to (Your own chord, Breathe in turns).
- **Trill the hill** is starred on landing (each note is too short to judge as a hold); the long
  phrase's last note is three beats; Hear models a note running out of air (a `bend`); End on
  purpose's rounds are on four notes; Breath into note describes the three starts and puts a drone
  under both clips; self-check lines on the hill and the bounce; wording fixed.
- **Then and now** never says "more seconds" against your baseline, only against a try this visit.

**The lead's last look** (same day): then-and-now holds say "as long as is easy, up to eight
seconds" (a fixed eight would push a shorter easy hold); Breathe in turns has shorter stretches and a
rest card between its two holds; every sung play step starts with two beats of drone, so there's
always something to sing to, through a speaker too; Swing Low at 72; a short safety line opens each
unit; the hill's measures line says a slow slide counts half; with no goal the bar grows with you.


## Sources

Checked in the content review (2026-10-29); every fact a lesson states comes from these.

- Appoggio, from the Italian appoggiare, "to lean", and its meaning in singing: https://explore.rider.edu/sites/default/files/files/vthc-a-review-of-the-breathing-mechanism-for-singing-part2.pdf ; https://www.dummies.com/article/academics-the-arts/music/voice/singing-with-outie-breathing-or-appoggio-179075
- Whispering: 69 of 100 voice patients showed more tightening above the vocal folds than in normal voice (Rubin et al., 2006, Journal of Voice): https://repository.li.mahidol.ac.th/handle/123456789/23267
- Pitch rises and falls with the air pressure under the vocal folds (Titze, 1989): https://iro.uiowa.edu/esploro/outputs/journalArticle/On-the-relation-between-subglottal-pressure/9984719749002771
- Lip trills and straw singing (semi-occluded vocal tract exercises, Titze, 2006): https://pubs.asha.org/doi/abs/10.1044/1092-4388(2006/035)
- Maximum phonation time norms (why goals are capped at 15 seconds): https://www.sydney.edu.au/content/dam/corporate/documents/faculty-of-medicine-and-health/research/research-centres/sydney-voice-lab/acoustic-analysis-normative-data-reference-sheet-20250416_v4.0-sydney-voice-lab.pdf.coredownload.pdf
- Noisy breathing in can have medical causes (paradoxical vocal fold motion, Journal of Singing): https://www.nats.org/_Library/JOS_On_Point/JOS-079-1-2022-049.pdf
- Mozart's Twelve Variations, K. 265, Vienna, 1781 or 1782: https://henle.de/fr/12-Variations-sur-Ah-vous-dirai-je-Maman-K.-265/HN-165
- "Ah! vous dirai-je, maman", printed 1761; Jane Taylor's words 1806: https://en.wikipedia.org/wiki/Ah!_vous_dirai-je,_maman
- Swing Low, Sweet Chariot, first printed 1872 (Fisk Jubilee Singers); the 1909 recording in the National Recording Registry: https://hymnologyarchive.com/swing-low-sweet-chariot ; https://www.loc.gov/item/jukebox-128141/
- Simple Gifts, a Shaker song of 1848 (Joseph Brackett): https://en.wikipedia.org/wiki/Simple_Gifts ; Appalachian Spring and the 1945 Pulitzer Prize: https://blogs.loc.gov/loc/2014/10/documenting-dance-the-making-of-appalachian-spring/
