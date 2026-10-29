# Singing with vibrato

Status: planned (Hillel, 2026-10-28), like the counterpoint units in
[course-review-plan.md](course-review-plan.md). Nothing is built from this plan yet; it records how
vibrato fits every part of the app, what already handles it, and what to add.

## What vibrato is, for us

A regular wave in pitch around a centre note: about 4.5 to 7 cycles a second, usually 20 to 100
cents either side in trained singers, often starting a moment after the note begins. It comes from
an easy throat and steady breath, not from pushing. A **wobble** (slow, wide, under about 4 cycles a
second or wider than about 150 cents) and a **tremble** (fast and narrow, tense) are not the goal.
A **straight tone** is a skill of its own: choirs, early music and barbershop keep vibrato light or
off so chords tune and ring.

Two principles run through everything below:

1. **Vibrato is never a pitch error.** We judge the centre of the wave, never its edges.
2. **The style decides.** Some songs and parts want a straight tone, some welcome vibrato; the app
   says which, and judges each on its own terms.

## What already handles it

| Where | What it does today |
|---|---|
| `web/js/scoring.js` | Judges a note by the median of its steady middle, smooths about one cycle (0.18 s) before judging steadiness, doesn't judge steadiness on notes too short to tell vibrato from wavering, and measures a note's vibrato (`vibrato()`) |
| `web/js/listen.js` | Lets one reading swing up to 1.2 semitones from the recent centre, so vibrato is still "heard" |
| `web/js/livevoice.js` | Holds the "a little low" hint with hysteresis, so vibrato never makes it flicker |
| `web/js/pitch-display.js` | Smooths the live line but keeps about two thirds of the swing, so singers see their vibrato |
| `web/js/synth.js` | The practice voice adds a gentle 5.5 Hz, 9-cent vibrato after 0.3 s |
| The course, `8-vibrato` | One lesson, "A gentle vibrato", with the review's corrected words and theory bite |

## Area by area

### Detection and measurement
- **Now:** `vibrato()` in scoring.js. **Add:** report rate (Hz), extent (cents), regularity (how
  even the cycles are) and onset delay per held note, as one small object every view can read. Keep
  it a pure module, so it's tested with the shared vectors, runs in the apps, and can be rescored
  (see [leaderboards-plan.md](leaderboards-plan.md)).
- Classify each held note: straight, vibrato, wobble, tremble or unclear (too short).

### Scoring and stars
- Pitch is always judged on the wave's centre; vibrato never costs pitch points.
- Steadiness ignores a regular vibrato but still notices drift of the centre and an irregular wobble.
- **Style-aware:** a song part or lesson can say `style: straight | free | vibrato`. Straight-tone
  parts (barbershop, choral, early music) gently note a wide vibrato ("try a straighter tone so the
  chord rings") without failing the take; vibrato lessons reward a steady, even wave.
- Leaderboards use the same centre-based pitch, so singers with vibrato aren't disadvantaged.

### What singers see
- **The live pitch line:** keep showing the wave (it's motivating), with the target band wide
  enough that a centred vibrato sits inside it. The note lights on its centre, not on every crossing.
- **After a take:** the take review and the result trace show held notes' vibrato as a small badge
  ("Vibrato 5.8 Hz, plus or minus 40 cents, even") and, for straight-tone parts, "Straight: nice".
  Never a red mark for vibrato.
- **Your voice page:** a "Your vibrato" line once it's been measured on a few long notes (natural
  rate and width), with how it's changed.

### Teaching: a Plus course
- **Vibrato is a Plus course** (Hillel, 2026-10-28), like counterpoint: a course of its own beyond
  the free path, listed in [plus-courses-plan.md](plus-courses-plan.md). The free course keeps
  `8-vibrato` as a short taste that links to it.
- The Plus course grows from these steps, placed after steady notes and breath:
  1. **Straight tone first:** hold a note with no wave (the skill choirs need), scored on a flat line.
  2. **Find the wave:** a slow, deliberate pitch swing (two a second) on a drone, then faster.
  3. **Let it happen:** hold a long note with an easy throat and let it settle into its own rate;
     scored on evenness, never forced.
  4. **Choose:** the same phrase straight, then with vibrato, then straight into vibrato at the end
     of the note (the classic delayed vibrato).
  5. **In harmony:** sing your part straight while the others ring, then hear what vibrato does to
     the chord (a theory bite: beating, and why barbershop keeps it out).
- Health notes in the words: comes from ease, not effort; stop if it feels tight.
- The workout can add a "straight tone hold" and an "even vibrato hold" as warm-up options.

### Guide voices
- The practice voice's 9-cent vibrato is right for most parts; make it follow the part's style
  (none for straight-tone parts, a little more for solo lines).
- Beta singers and community guides: say in the singer brief whether each part is sung straight or
  with vibrato, so the guide matches the arrangement's style.

### Harmony and stacking
- Stacked parts with mismatched vibrato beat against each other. The studio could show, per track,
  "vibrato" or "straight" and suggest matching ("the other parts are straight; try a straighter
  tone").
- Polished must never flatten vibrato. If it ever corrects pitch, it corrects the centre and keeps
  the wave.

### Song creator and Harmonise my song
- Transcription must not turn a vibrato into extra notes or a trill: fold the wave into one note at
  its centre (the creator's and Harmonise's take-to-notes step).
- A song can set its style (straight, free, vibrato) in Song settings, which the scoring and the
  practice voice follow.

### Templates and arrangements
- Add an optional `style` per template and per part. Official choral and barbershop arrangements
  default to straight; folk and pop to free.

### The apps
- The same measurement and scoring through the shared vectors, so a vibrato scores the same on the
  web and in the apps.

## Order of work (when it's picked up)

1. **Foundation:** the measurement object (rate, extent, regularity, onset, class) in scoring.js,
   tests on recorded and synthetic voices with and without vibrato, and the `style` field on
   templates, parts and lessons (default free). Confirm centre-based pitch everywhere.
2. **Feedback:** the badge in the take review and result trace; "Your vibrato" on Your voice.
3. **Teaching:** the five-step strand in unit 8, with its words and theory bites.
4. **Style-aware scoring** for straight-tone parts, and the practice voice following the style.
5. **Ensemble:** the studio's match suggestion; the singer brief note.
6. **The apps.**

## Open questions for Hillel

- Should official barbershop and choral parts be marked straight from the start, or stay free until
  the strand exists?
