# Plus courses in the Flutter apps: what they need to play

Status: survey for the mobile agent, for when the apps resume (courses-harmony, 2026-10-29). Nothing is
built for this yet. It lists every step kind, exercise field, note field and scorer the ten launch
courses use, where each lives on the web, what each needs from the pitch engine, and a build order
that makes whole courses playable stage by stage. The shared format is `course/format/steps.json` in
the courses repo (linked into `web/course/format/`); its `webOnly` lists mark what the apps can't play
yet, and `course_check.py` reports how many lessons Flutter can't play.

Back: [Build plan](../plus-courses-build-plan.md) · [Course format](https://github.com/hillelcoren/stackapella-courses)

---

## 1. What the apps have today (stackapella-mobile, read only)

- **The free course only:** `lib/domain/course.dart` and `lib/ui/learn/` play the step kinds `learn`,
  `home`, `try`, `play`, `use` and `record`. An exercise has `tempo`, `turn`, `notes` (beat, length,
  step, lyric), `drone` and `chords`. Wait for me is `lib/domain/wait_for_me.dart`.
- **The guide's synth** (`lib/domain/audio/synth.dart`): notes and chords, with the default vibrato
  (5.5 a second, about 9 cents, after 0.3 s). No per-note vibrato, bend or voice.
- **The DSP** (`packages/stack_engine/lib/src/dsp/`): pitch tracking, `scoring.dart` (the web's
  `scoreTake`), quality, calibration, trim. This is the base every measure below builds on.
- **Missing:** the Plus catalogue, per-course progress, every Plus step kind, the scorer registry and
  every field marked web-only.

## 2. What the ten launch courses use

Step counts across Perfect your pitch, Breath and stamina, Ear training, Vibrato, Counterpoint,
Improvising a harmony, Barbershop and tags, Folk and early music, Yodeling, and Pop and country:

| Step kind | Steps | Courses |
|---|---|---|
| `learn`, `try`, `play` | 174, 63, 180 | all ten |
| `choose` | 42 | all ten |
| `bullseye` | 44 | Pitch, Breath, Ear |
| `wave` | 34 | Vibrato, Pitch, Yodeling |
| `selfcheck` | 13 | Pop, Yodeling, Barbershop, Folk |
| `breathhold` | 11 | Breath |

## 3. Plumbing (before any Plus lesson)

| Piece | What | Web source |
|---|---|---|
| Catalogue | `course/plus/courses.json`: id, prefix, file, status (live or soon), colour, visual, group, order | `js/courses.js` |
| Gates | Staff before launch, then the `plus_courses` flag (`account.show`'s `plusCourses`); lesson 1 free, the rest Plus; units open on two thirds of the unit before's stars | `js/courses.js` (`courseVisible`, `lessonAccess`) |
| Progress | Per course, `progress.courses.<id>.lessons`, merged lesson by lesson; XP, level and week shared; Restart this course clears one course | `js/stores/learning.js`, `js/course.js` (`mergeProgress`) |
| Strictness | Plus courses default to standard (±35 cents); a lesson's own `strictness` wins | `js/course.js` |
| Real-singer clips | `course/plus/clips.json` slots, fetched from the API (`courses.clips.list`), used by `selfcheck` models | `js/course-clips.js` |
| Kept takes | A step's take kept for this visit (`under`), or on the device under a key (`keep`, `thenAndNow`) | `js/views/learn.js` (`kept`), `components/wave-lane.js`, `components/breath-hold.js` |
| Skins | Game shows stars, XP and streaks; serious shows the measure in plain words. Every measures line already reads "Scored on…", with no star words | `js/skin.js` |

## 4. Step kinds

| Kind | What the learner does | Web source | Needs from the pitch engine |
|---|---|---|---|
| `choose` | Listens to clips (synth: notes, drone, chords, vibrato; or a word list), taps the answer, a why after each; no mic | `components/choose.js` | Nothing: synth only |
| `bullseye` | Echoes a note (or a few); each lands as a dart, sharp above, flat below | `components/bullseye.js`, `js/pitch-skills.js` | A note's landing: its first settled pitch, cents off, whether it slid in |
| `selfcheck` | Hears a model (synth, or a real singer's clip), records, plays back both, answers questions; never scored | `components/selfcheck.js`, `js/course-clips.js` | Recording and playback only |
| `breathhold` | Holds one note until they stop; the hold's length and steadiness | `components/breath-hold.js`, `js/pitch-skills.js` | Hold length, steadiness, how the note ends |
| `wave` | Rides a scrolling ribbon: a steered wave, a straight line, a bloom, a free hold, or a contour (`path`) | `components/wave-lane.js`, `js/vibrato.js` | Per-frame pitch with lag removed; vibrato rate, width, evenness, onset; straightness |

## 5. A `play` exercise's extra fields (all web-only today)

| Field | What | Used by | Web source |
|---|---|---|---|
| `with` | Partner voices that sound while you sing, never scored; or the sung line itself, delayed (rounds). Resting through a speaker; mixed back in for Hear it together | Counterpoint, Improvising, Barbershop, Pitch | `components/exercise.js` (`withPartners`, `setPartners`, `together`) |
| `answer` | Call and response: you sing these notes, not the call's | Counterpoint, Improvising, Pop | `exercise.js` (`layout`) |
| `under` | An earlier step's take plays under this one, and in Hear it together | Improvising, Barbershop, Pitch, Breath | `exercise.js`, `views/learn.js` |
| `lights` | How your notes are drawn, all still scored: fade, changes, late, off | Improvising | `exercise.js` (`drawn`) |
| `guide`, `droneAfter` | A cold start: your notes not sung first, the drone coming in later | Pitch | `exercise.js` (`heardNotes`, `droneSpan`) |
| `giveNote` | A Give me my note button during your turn | Folk | `exercise.js` |
| `score`, `rules` | A registered scorer, and its rules (`species`: contrary, all, close, against) | most courses | `js/lesson-scores.js` |
| `generous` | The first lesson's kinder stars: at least two for a fair, finished go (stars only, never the percent) | lesson 1s | `exercise.js` (`minStars`), `views/learn.js` (`starsOf`) |
| `base`, `window` | Yodeling's flip note as the base; a scoop's time to arrive | Yodeling, Pop | `exercise.js`, `js/style-scores.js` |
| `keep`, `thenAndNow` | A take kept on the device and played beside a later one (on `wave` and `breathhold` steps) | Vibrato, Pitch, Breath | `wave-lane.js`, `breath-hold.js` |

## 6. Note fields (web-only today)

| Field | What | Used by | Where |
|---|---|---|---|
| `listenOnly` | Heard, never sung or scored (an echo's call, a distraction) | 6 courses | `exercise.js` |
| `choices` | Any of these steps is right; the one sung is scored; fifths and octaves that would make parallels dim live | Counterpoint, Improvising | `exercise.js`, `js/harmony.js` |
| `cents` | A pure-tuning offset (a third at -14) | Barbershop | `exercise.js` (`laidNote`); the synth and scorer take fractional pitches |
| `ring` | A held chord note scored on ringing | Barbershop | `js/ring.js` |
| `vibrato` | The guide's own vibrato on this note (`{ hz, cents, after, jitter }`; `cents: 0` straight) | Vibrato, Barbershop | `js/synth.js` |
| `bend`, `voice`, `style` | The guide's pitch path, a lighter head voice, a scoop, fall, slide or cry | Yodeling, Pop | `js/synth.js`, `js/style-scores.js` |

## 7. Scorers (`score` on a `play` step)

| Scorer | Measures | Courses | Web source | Needs from the pitch engine |
|---|---|---|---|---|
| default | Notes on pitch | all | `js/scoring.js` (`scoreTake`) | Already in `scoring.dart` |
| `hold` | The longest hold on pitch, and its steadiness | Vibrato, Folk, Yodeling | `js/results.js` (`holdPercent`) | Chart dots (cents per frame against the target) |
| `holds` | Several holds, each on its own | Counterpoint | `js/harmony.js` | The same |
| `species` + `rules` | A chosen line on pitch; the top score only by the rules (contrary motion, no parallel or direct fifths, a cadence, small moves) | Counterpoint, Improvising | `js/harmony.js` (`resolveChoices`, `speciesChecks`, `openChoices`) | Each note's middle pitch (median), folded to the octave |
| `straight` | Held notes straight, no wave | Vibrato, Barbershop | `js/vibrato.js` | Cents per frame; the wave's rate and width |
| `landing`, `lock`, `centre`, `drift`, `memory`, `line`, `release`, `entry`, `cold` | Where notes land, a lock to a drone, a note's centre, sag, a note from memory, a phrase, an ending, an entry after a breath, a cold start | Pitch, Breath | `js/pitch-skills.js` | Onsets and endings in time, settled pitch, slopes |
| `ring`, `partring` | Held chord notes against their pure targets; a part on pitch and ringing | Barbershop | `js/ring.js` | A hold's centre over its last 1.5 s, in cents |
| `keyDrift` | A line sung alone: intervals, and how far it slid off key | Folk | `js/style-scores.js` | Each note's median pitch |
| `flip`, `flipFinder` | Yodel flips, clean or slid; where a voice flips | Yodeling | `js/style-scores.js` | Pitch jumps and their speed |
| `styled` | Scoops, falls, slides and cries asked for | Pop | `js/style-scores.js` | A note's pitch path over time |

The result lines that go with them (pulled onto another voice, contrary moves, parallels, a scorer's
own `line`) are in `views/learn.js` (`harmonyLines`) and each scorer's `line`.

## 8. A suggested build order

Each stage makes whole courses playable, so the mobile agent can ship them as it goes.

| Stage | Build | Courses that then play |
|---|---|---|
| 1 | The plumbing (section 3), and `choose` (synth clips, no mic) | none yet, but every course's listening steps |
| 2 | `bullseye` (a note's landing) | **Ear training** (only `choose` and `bullseye`) |
| 3 | `listenOnly`, `giveNote`, `selfcheck`, the scorer registry, `hold` and `keyDrift` | **Folk and early music** |
| 4 | `with` (and Hear it together), `answer`, `under`, `choices`, `lights`, `holds`, `species` (port `harmony.js`, pure and unit-tested on the web) | **Counterpoint**, **Improvising a harmony** |
| 5 | Per-note `cents` and `vibrato` in the synth, `ring`, `partring`, `straight` | **Barbershop and tags** |
| 6 | `breathhold` (with `keep` and then and now) and the `pitch-skills.js` scorers; `guide` and `droneAfter` | **Breath and stamina** |
| 7 | The `wave` lane (ribbon, `path` contours, Hear yourself, `keep` and then and now) and `vibrato.js` | **Vibrato**, **Perfect your pitch** (with stage 6's scorers) |
| 8 | `bend`, `voice` and `style` in the synth, `base`, `window`, `flip`, `flipFinder`, `styled` | **Yodeling**, **Pop and country** |

Every web measure listed is pure JavaScript with its own tests (`web/tests/*.test.js`), so each can be
ported to Dart against the same cases: `harmony.test.js`, `vibrato.test.js`, `pitch-skills.test.js`,
`style-scores.test.js` and `barbershop.test.js` (the ring). When a piece is built, its `webOnly` entry in `steps.json`
comes off, and `course_check.py`'s count of lessons Flutter can't play goes down.
