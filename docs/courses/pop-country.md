# Pop and country styling: the course outline

Status: built, all four units, 16 lessons, about 40 minutes (courses-styles, 2026-10-29), live, after the lead's review: shown to staff until Plus courses open on launch day. The guide sings
each move from the note's `style` (styleBend). The rules every lesson is checked against are in
[plus-courses-build-plan.md](../plus-courses-build-plan.md), sections 3 and 6a; the course's first
notes are in [plus-courses-content.md](../plus-courses-content.md), "Pop and country styling".

Back: [Plus courses](../plus-courses-plan.md) · [Docs guide](../../README.md#a-guide-to-the-docs)

## In short

- **The skill:** the small moves that make a plain tune sound like a record (a scoop up into a
  note, a fall-off at its end, a slide between notes, a little cry), a tone colour to suit the song,
  and the taste to use them sparingly.
- **Id and prefix:** `pop`, lesson ids `pop-<unit>-<name>`. Four units, 16 lessons, 2 to 4 minutes
  each.
- **Its promise in one line:** "Make a plain tune sound like a record."
- **Public-domain tunes and words only,** styled our own way, sung over their chords:

| Tune | Source | Used in |
|---|---|---|
| Down in the Valley | Traditional American, a waltz; a pre-1929 printing | 1-style, 1-challenge, 4-challenge |
| Bury Me Not on the Lone Prairie | Words from Lomax, *Cowboy Songs* (1910); the tune is The Ocean Burial (E. H. Chapin's poem of 1839, set by George N. Allen) | 2-challenge, 3-twang |
| Aura Lee | Poulton and Fosdick (1861), Fosdick's words only (never the 1956 lyrics to the same tune) | 3-crooner, 3-challenge |
| Beautiful Dreamer | Stephen Foster (1864), its first line only (the full verse runs past the fit rule) | 4-yours |

  Each is transcribed from its source, measured against the fit rule (the top at most a fourth
  above the comfortable note, the lowest at most a sixth below) and trimmed where it doesn't fit.
  No recording is imitated and no living singer is named as a model; styles are described.
- **Moves stay inside the range:** in a song, no scoop or fall-off goes below the line's lowest
  note; in a drill on a few notes, they stay inside the course's range (at most a sixth below the
  comfortable note); a cry's peak stays within a fourth above the comfortable note. Moves go only on notes of half a
  second or more, so every note keeps a centre to judge its pitch.

## What the app measures here

| Measure | What it is | From |
|---|---|---|
| Pitch on each note | Its centre, after any scoop and before any fall-off | `scoring.js` (exists) |
| **A scoop** | Starting a semitone or more below and rising smoothly into the note | `scoop`, using courses-skills' `landing()` for the settle, with a stricter start |
| **A fall-off** | The note's end dropping away | `fallOff` in `web/js/style-scores.js` |
| **A slide** | A deliberate glide between two joined notes (sung on one breath, no gap) | `slide`, the other side of Yodeling's `flip` |
| **A cry** | A quick flick up into the lighter voice and straight back down | `cry`, its shape only |
| Listen and choose | The answers | `choose` (exists) |

**What we can't hear, said plainly:** tone colour (breathy, bright, twang), the sob in a cry, and
whether a move sounds good. Tone is self-checked (the `selfcheck` step: hear the model, record, hear
yours beside it, answer two questions) and says "We don't score the tone yet". Each tone lesson rides
on a move we can measure, so its stars mean something real.

**The guide can't model tone yet.** Its voice is one fixed "oo"; it can't sound breathy, bright or
twangy, or sob. Unit 3's models come from real-singer clips (below); until they exist those lessons
describe each colour in a picture and a sentence, and the course stays "soon" until the clips are in
or the lead agrees the described version is good enough.

### The moves, in numbers

A lesson's notes can each carry a `style`: `scoop`, `fall`, `slide` (into the next note) or `cry`.
A `play` step with `score: 'styled'` judges:
- **A scoop:** starting 100 to 400 cents below (a smaller dip is an accidental flat start, which
  untrained singers make, not a style), rising smoothly with no flat stretch over 80 ms before the
  climb, arriving within the lesson's window (80 to 400 ms; a lazy scoop's lesson allows 500).
- **A fall-off:** a drop of at least 200 cents, counted from the note's last steady readings until
  the readings stop (a soft tail fades out of hearing), searching 200 ms past the note's end.
- **A slide:** 150 to 700 ms between the notes, most readings passing through the notes between.
- **A cry:** a rise of 100 to 400 cents above the note (the small version; real cries often flip a
  third to a fifth), lasting 60 to 250 ms, then on the note.
- **Stars:** half for the moves asked for, half for pitch on each note's centre. Where you choose the
  moves (unit 4), stars come from pitch and any one move heard, never more for more.
- **Taste, never a grade:** moves heard on notes that didn't ask for them cost nothing. If more than
  half the notes carry one, the result says "Less is more: save it for the long notes." Every move we
  caught is marked on your line, with "Meant more? We may have missed a small one."
- **Breathy takes are judged generously:** more air means lost readings and a flatter pitch, so the
  lessons sung breathy (and Your colour, Your record) are at the relaxed tolerance, and their
  measures lines say the tone is yours to judge.
- Every number is a named constant, tested on synthetic tracks and on `sing()` (its `scoop` and
  `legato` options, and an added fall), and tuned on the beta.

## The lessons

Each lesson runs Why, Hear, Try, Do, Hear yourself, over the tune's chords, and ends naming the next
lesson. Every challenge brings back the unit before and has then and now.

### Unit 1: Scoops and fall-offs

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **pop-1-style: Plain to styled** (the free taste) | Hearing and singing what one move does | Why: a plain tune and a styled one are the same notes; the difference is a few small moves. Hear Down in the Valley's first line with a scoop into "val-". Try the scoop on that one note, at your own pace. Do: the line plain, then styled, over chords. Hear yourself: each take's Listen back (back to back in one player when the lead's then and now lands). If no scoop was heard: "Try a bigger, slower one" and one more go. Ends: "Same notes, different song. Next: the scoop, up close." | The styled take: the scoop and pitch, generously |
| **pop-1-scoop: The scoop** | Sliding up into a note on purpose | On the trace lane: a semitone, then a third, quick then lazy. Two "scoop or straight?" listening rounds. Self-check: did it sound meant, not missed? | The scoops, and pitch on arrival |
| **pop-1-fall: The fall-off** | Letting a note's end drop away | Long notes that fall off: short, then longer. "Let the pitch go, keep the breath steady" | The fall-offs |
| **pop-1-challenge: Styled valley** | Scoops and falls in a verse | Down in the Valley's verse with three marked scoops and two falls, over chords. Then and now: lesson 1's plain take beside today's | Moves and pitch |

### Unit 2: Slides and the cry

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **pop-2-slide: The slide** | A smooth glide between two notes | Slides up and down a third, then a fifth, echoed; then a line with two slides | The slides |
| **pop-2-cry: The little cry** | A quick flick up into the lighter voice and back | Why: a quick flip up into your lighter voice, where it changes gear, and straight back down, at the start of a word or inside a held note; light, never a hard catch. We hear the flick's shape, not the sob. A slow practice cry first, then quicker, echoed on three notes | The cries, and pitch |
| **pop-2-hear: Which move?** | Hearing the moves | Listen and choose, 6 rounds: plain, scoop, fall-off, slide or cry; then "which one overdid it?" (a clip styling nearly every note) | The choices |
| **pop-2-challenge: Lone prairie** | Slides and a cry, with unit 1's scoop | Bury Me Not on the Lone Prairie, a verse with marked slides, a cry and a scoop. Then and now | Moves and pitch |

### Unit 3: Colours

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **pop-3-colours: Three colours** | Breathy, bright and twang, by ear and by feel | Self-check: one line three ways (each modelled or described), your three takes back to back. "Which felt most like you?" (kept for the challenge). Safety for each, below | Practising, said so ("We don't score the tone yet") |
| **pop-3-crooner: The crooner** | Breathy and close, with fall-offs | Aura Lee's first line, a little air, quiet, with fall-offs on the line ends. Self-check: air in it, still on pitch? | The fall-offs and pitch (generous); tone self-checked |
| **pop-3-twang: The country edge** | A little twang, with a cry | Twang found from a light, bratty "nyeh", then a line of Lone Prairie with a cry. The nose-pinch check: if the sound changes a lot, it was nasal, not twang | The cry and pitch; tone self-checked |
| **pop-3-challenge: Your colour** | Choosing a colour for a song | Aura Lee's verse in the colour you chose, with unit 2's slides. Compare yours with the model. Then and now | Slides and pitch (generous for breathy); tone self-checked |

### Unit 4: Your way

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **pop-4-where: Where the moves go** | A rule of thumb: the long notes and the line ends | Listen and choose, factual: "Which one styled only the long notes?", "Which one saved the cry for the last word?" Then a line where you choose, as a try | The choices |
| **pop-4-answer: Answer back** | Hearing a styled line and answering in your own moves | Call and response: the guide styles a line; you answer the same notes with different moves | Pitch, and any one move heard |
| **pop-4-yours: Your way** | Styling a plain line yourself | Beautiful Dreamer's first line, plain on screen; style it your way, twice, from the whole palette (a scoop, a fall, a slide, a cry, a little wave at the end); hear both | Pitch, and any one move heard; less is more |
| **pop-4-challenge: Your record** | Everything, on lesson 1's song | Down in the Valley's verse, your way, in your colour, over chords. "Then: plain. Now: your record." | Pitch, any one move heard, tone self-checked |

## Reading the course file

`course/plus/pop/course.json` in the courses repo (hillelcoren/stackapella-courses), in the free course's shape, as Folk's (docs/courses/folk.md,
"Reading the course file"), plus:

- **`style`** on a note: `scoop`, `fall`, `slide` (from this note into the next, joined one) or
  `cry`. It's both what the guide sings (`styleBend` in `web/js/style-scores.js` turns it into the
  synth's bend) and what a `score: "styled"` step listens for. A move goes only on a note of half a
  second or more.
- **`score: "styled"`**: half the stars for the moves asked for, half for pitch on each note's
  centre (after its scoop, before its fall-off). With no `style` on any note, your own way: pitch,
  and any one move heard.
- **`window`** on an exercise: a lazy scoop's time to arrive, in seconds (0.4 by default).
- **`chords`**: Down in the Valley's I and V7, `root` in semitones from the comfortable note.

## As built

- **Unit 3's colours are self-checks with the colour described** (breathy, bright, twang), one
  self-check step per colour in Three colours, because the guide's one voice can't sound any of
  them and a self-check model can't name a recorded clip yet (asked of the lead). The crooner, the
  country edge and Your colour star the move each colour rides on (fall-offs, a cry, slides), judged
  at the relaxed tolerance where the tone is breathy.
- **Answer back** is a call and response: the guide's call carries its moves; your `answer` carries
  none, so the `styled` scorer reads the answer and stars pitch and any one move of yours.
- **Your way** and **Your record** ask for no moves, so any one heard counts, never more for more.
- Every move sits on a note of half a second or more; a scoop starts about a whole step below (the
  guide's 200 cents), a fall-off drops about 350 cents, a cry's peak stays within a fourth above the
  comfortable note.
- Every sung line has chords (or a drone) under it from its first beat, so the key is always there:
  Down in the Valley's I and V7 (V7 on "low", I on "blow"), Lone Prairie in F with C7 under "ly",
  Aura Lee's G, A7, D7, Beautiful Dreamer's C and G7.
- Most lessons start with a try or an easy first pass; the stars come from the last scored step.

**About the course file:** each tune's lines appear in several steps (hear it, try it, sing it, a
listening clip), each copy at its own beats; the lines are written once in the build and placed
where each step needs them, so a fix to a line must be made in every copy (search its words).

## Fun moments

- **Plain, then styled:** lesson 1 plays your two takes back to back.
- **Share "Plain / Styled":** your two five-second takes on the existing Share card.
- **"Which one overdid it?"** An ear game for taste, and moves marked on your own line.
- **Your style:** a portrait on Your voice of the moves you use most ("mostly scoops and falls"),
  filling in from every lesson (with the lead).
- **You just did that:** your first scoop, your first cry, your first line styled your way.
- **Later:** Today's line (a fresh public-domain line to style each day) and Remember this? with your
  cleanest cry.
- **Facts on end screens** (the content doc's checked list, worded with care): crooning became
  popular when electric microphones arrived in the mid to late 1920s; Auto-Tune was invented by Andy
  Hildebrand, an engineer who had worked on oil exploration with sound (named as its inventor, never
  as a model).

## Safe singing

- Said once on the course page and lesson 1: moves are small and light; medium volume, middle of
  your voice; stop if anything tightens, tickles or hurts; don't sing when hoarse or ill; see
  someone if hoarseness lasts more than two weeks. No app replaces a teacher.
- **Breathy:** a little air, not a whisper; your phone is your microphone, so stay quiet and don't
  push; water nearby, short lessons (breathy singing dries the voice).
- **Bright:** it comes from the vowel and a slight smile, not from more volume or higher notes. This
  isn't belting.
- **Twang:** narrow and bright, never pressed; start from a light "nyeh"; stop if the throat feels
  squeezed or burns.
- **The cry:** a light flick, never a hard catch. **Fall-offs:** let the pitch go, keep the breath
  steady, never a collapse into a creak.
- Moves stay small (a scoop at most a third, a fall within the line's range).

## Lesson types it uses

| Type | Status |
|---|---|
| `learn`, `try`, `play` with `turn`, `chords` | Exist |
| `choose`, `selfcheck` | Exist (the lead's) |
| `wave` with a `path` target (curves to follow), and the guide's glides (scoops, falls, slides, cries) | The lead's, queued |
| `styled` as a `play` scorer, with `scoop`, `fallOff`, `slide` and `cry` | Mine, `web/js/style-scores.js` |
| A note's `style` | Read by the scorer from the step's own `exercise.notes`, so nothing new |
| The scorer's measured line; then and now; Your style | The lead's |

## Clips to record later

From one real singer, dry, each a line of about 4 seconds on Down in the Valley: plain; with a scoop;
with fall-offs; with a slide; with a cry; breathy; bright; with a little twang. Until then the guide
sings the moves it can (with the glides), and unit 3 describes the colours.

## What the reviews changed

From the **voice-teacher review**: a styled scoop starts at least a semitone below and rises
smoothly, so an accidental flat start never earns style stars; moves stay inside the range (no fall
below the line's lowest note, a cry's peak within a fourth above the comfortable note); the guide
can't model tone, so real clips, and the course "soon" until then; the cry's numbers (60 to 250 ms)
and plain words ("where it changes gear"), and honesty that we hear its shape, not its sob; fall-offs
counted until the readings fade; the tone safety lines (bright isn't belting, twang from "nyeh" with
the nose-pinch check, breathy as a little air without pushing at a phone); breathy takes judged
generously; the vibrato lesson cut (this course doesn't teach vibrato); lazy scoops and slow slides
given longer windows; the tunes' sources named (Lomax 1910 and The Ocean Burial, 1839; Fosdick's
words for Aura Lee), Beautiful Dreamer cut to its first line, Red River Valley left to Yodeling; the
crooning fact reworded.

From the **game-design review**: the three tone lessons merged into Three colours, with your three
takes back to back and each colour then riding on a scored move (the crooner with fall-offs, the
country edge with a cry); lesson 1 scores one move it teaches, over chords, with another go if none
was heard; the finale back on lesson 1's song, "Then: plain. Now: your record."; taste asked as fact
("which one styled only the long notes?"), and your-way stars from any one move, never more for
more; misses softened (longer windows, slow cries first, "we may have missed a small one"); the scoop
lesson on the trace lane with listening rounds; a call-and-response lesson instead of the bloom;
then and now and Next lines everywhere; Share "Plain / Styled", Your style, and Today's line later.

## Sources

The facts in the lessons (the content review of 2026-10-29), each with where to check it:

Since 2026-10-29 these facts are in each lesson's `fact` (the lesson end's "Did you know?" card,
with its source and link), one a lesson and at most two a unit; the learn cards they'd taken teach
again. Facts that are the lesson's own content stay in its headline (as marked below).

- **Kurt Weill built a 1948 folk opera on Down in the Valley** (pop-1-style).
  [Kurt Weill Foundation: Down in the Valley](https://www.kwf.org/works/down-in-the-valley/)
  (premiered at Indiana University, 15 July 1948).
- **The cry is a tiny yodel; Hank Williams was famous for it in the late 1940s** (pop-2-cry).
  [Lovesick Blues](https://en.wikipedia.org/wiki/Lovesick_Blues) (1949) and the
  [McGraw-Hill listening guide](https://highered.mheducation.com/sites/0073526576/student_view0/chapter13/listening_guides.html):
  "rapidly between falsetto and chest voice".
- **Cowboys reworked The Ocean Burial, E. H. Chapin's 1839 poem; Lomax printed their words in 1910**
  (pop-2-challenge). [The Ocean Burial](https://en.wikipedia.org/wiki/The_Ocean_Burial) (Chapin's poem,
  June 1839, set by George N. Allen); and as in the tune table above: John A. Lomax, *Cowboy Songs and Other Frontier Ballads* (1910).
- **Crooning came with the electric microphone in the 1920s** (pop-3-crooner, its headline).
  [Crooner](https://en.wikipedia.org/wiki/Crooner).
- **Aura Lee (Poulton and Fosdick, 1861) became Love Me Tender** (pop-3-crooner).
  [Aura Lea](https://en.wikipedia.org/wiki/Aura_Lea).
- **Twang comes from narrowing a ring of muscle just above the vocal folds (the aryepiglottic
  sphincter), so it rings without more volume** (pop-3-twang). Yanagisawa, Estill and others, "The
  contribution of aryepiglottic constriction to 'ringing' voice quality", *Journal of Voice* 3(4), 1989;
  Estill Voice Training. A frequency figure (2 to 4 kHz) was found only secondhand, so the card gives none.
- **A Jordanaires singer (Neal Matthews Jr.) wrote vocal parts as numbers in the late 1950s: the
  Nashville Number System** (pop-4-answer). [Nashville Number System](https://en.wikipedia.org/wiki/Nashville_Number_System);
  [Country Music Hall of Fame: The Jordanaires](https://countrymusichalloffame.org/artist/jordanaires/).
- **Beautiful Dreamer was published in 1864, just after Stephen Foster died** (pop-4-yours).
  [Beautiful Dreamer](https://en.wikipedia.org/wiki/Beautiful_Dreamer).
