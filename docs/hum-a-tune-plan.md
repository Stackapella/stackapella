# Hum a tune: the musical side

Status: approved by Hillel (2026-10-29): free time with a click optional, a wordless tune sings "mm", up to 32 bars or a minute in one take. Being built: steps 2 and 3 (`hum.js`, `noteEvents`) are written and tested, waiting for the frontend's review

**What this is:** the plan for the launch's Hum a tune (`docs/launch-plan.md`): hum or sing a tune
with no words, and the app writes the notes and suggests a harmony for 2 to 4 singers. The story,
the home card and sharing as an idea are already planned (`docs/your-tune-plan.md`); this plan is
the music: turning a hummed take into clean notes and rhythm, the key, and a good harmony. The
screens are the frontend's, inside the song builder, and the files are agreed with it before
building.

## What already exists, and is reused

| Piece | Where | What it does today |
|---|---|---|
| The pitch track | `web/js/pitchtrack.js` | YIN every 10 ms, octave slips repaired, a median filter, in a worker |
| Notes from a sung line | `web/js/transcribe.js` | splits the track into notes (silences, pitch moves, new onsets), folds scoops, takes each note's steady middle, removes the singer's drift, snaps to a grid. **It needs the tempo:** the line is sung to a click |
| The key | `transcribe.js` `findKey` | a pitch-class profile weighted by length |
| Check the notes | `components/create/check.js` | the notes the transcriber wasn't sure of, octave slips and notes outside the key, one at a time, with hear, move, split, join |
| The harmony | `web/js/arrange.js` | chords for the tune (a Viterbi plan with cadences), then alto, tenor and bass voiced as a choir, keeping the third in the soprano or alto so 3 singers have it |
| Tap tempo, count-in, the backing | the song builder | for "Hum over a backing" |
| Sharing | Ideas (`ideas` flag) | every part open for anyone to sing |

## What's new

1. **Free time: finding the beat without a click.** Most people hum a tune in their own time.
   A new pure module, `web/js/hum.js`, takes the note events (onsets, lengths, pitches, loudness)
   from the transcriber run with a nominal tempo, and finds:
   - **the tempo:** candidates from 50 to 160 bpm from the gaps between onsets, each scored by how
     well the onsets fall on a grid of that beat;
   - **a beat that may drift:** a beat tracker (a Viterbi search over beat times, each beat
     allowed to move a little) so a singer who slows down at the end, or rushes a line, still gets
     bars that line up;
   - **the metre:** 4/4, 3/4 or 6/8, from where the long and loud notes fall and how long the
     phrases are (phrases of 2, 4 or 8 bars score higher);
   - **the pickup:** the first downbeat is usually the first long or stressed note, not the first
     note;
   - **phrases:** a breath (a silence over about a quarter of a second) ends a phrase; each phrase
     start gets `br`, and the phrases make the song's sections.
   - **How sure it is.** When it isn't sure (no clear beat, or two metres score close), it asks
     once: "Tap along while it plays" (tap tempo over the playback), which fixes the beat.
2. **Cleaning the rhythm.** Snap to the beat grid, preferring simple rhythms: an eighth note grid
   by default, sixteenths and dotted rhythms only where the take clearly has them, and syncopation
   only where it repeats. A phrase's last note holds to the next breath or bar line. The song ends
   on a bar line.
3. **Cleaning the pitch.** What the transcriber already does, and the Check step's safe fixes
   (`creator.js` `fixFor`: octave slips and notes outside the key), reused rather than repeated,
   plus: a hummed note's pitch from its steady middle, the median of its middle 50% of readings
   (`noteEvents`; Sing it's `transcribe` takes the middle 60%), since humming scoops and fades more
   than words do; notes outside the found key are kept but flagged in Check ("Was this note meant?"), never
   silently moved; a hum sung by a low voice is placed for the soprano an octave up, as Sing it
   does.
4. **Words.** A hummed tune has none, and every phrase needs a syllable (the template rules).
   Each note gets one syllable, the same for the whole tune: "mm", a true hum (Hillel), and the
   creator's words step stays open to add real words later.
5. **The harmony at 2 to 4 singers.** `arrange.js` already keeps the third for 3 singers. It gains
   the same rules as the library (`docs/arrangements-voicings-plan.md`): the alto takes the third
   when the tune doesn't, no bare fourth or fifth on a strong beat in the duet, no seventh leaps in
   the inner voices, and a complete last chord at every size. A hummed tune then gets voicings for
   2, 3 and 4 like every library song, and `app:templates:check`'s rules are run on the result in
   the web tests.
6. **Chords** come from the arranger's own planner and the song builder's families
   (`create/chord-walk.js`), not a new set of rules.
7. **Hum over a backing** (already in the song builder) is the other way in: the chords are chosen
   first and the tempo is known, so only steps 2 to 5 apply.

The file split below was agreed with the frontend (2026-10-29).

## How we know it works

- **Synthetic hums of the library.** The 22 songs' tunes are known exactly, so the tests render
  each one as a hummed pitch track with what real humming does: a drifting tempo (up to 10%),
  scoops into notes, vibrato, a flat drift, breaths at phrase ends, a quiet start. The bar is:
  the key right for every song, the metre right for at least 20 of 22, at least 90% of notes at
  the right pitch and beat, and every result passing the template rules.
- **Real hums.** Hillel and the beta singers hum a few of the library songs and a tune of their own
  into the dev build; each take is kept as a test fixture (pitch tracks only, no audio).
- **The harmony** is checked with `ArrangementCheck`'s rules on every synthetic result, and a
  choral reviewer listens to the suggestions for a handful of the real hums.

## Who does what (to agree with the frontend)

| File | Who | Change |
|---|---|---|
| `web/js/hum.js` and `web/tests/hum.test.js` | arranger | new: tempo, beat, metre, pickup, phrases, rhythm cleanup, the synthetic hums, and test vectors exported for the mobile apps (as `theory.js`'s are) |
| `web/js/transcribe.js` | arranger, the diff reviewed by the frontend before landing | a new export for free time (note events without snapping) and the hummed-note pitch; `transcribe()` keeps its signature and snapping, which Sing it line by line and its flows pin |
| `web/js/arrange.js` | arranger, the diff reviewed by the frontend before landing | the duet and trio rules |
| The Hum a tune steps (hum, "Here's your tune", tap along, Check, hear it harmonised, how many singers, save or share) | frontend | reuses Sing it's stage, the Check step and Suggest |

## Hillel's answers (2026-10-29)

| Question | Answer |
|---|---|
| Free time or a click? | "Free time, click optional": hum in your own time, with "Use a click" one tap away and Tap along when the app isn't sure of the beat. |
| What a wordless tune sings | "mm", a true hum (not "loo"). |
| How long | "32 bars or a minute", in one take, with Again for any line. |

## Where it stands (2026-10-29)

`hum.js` and `transcribe.js`'s `noteEvents`, tested on the 22 library tunes hummed with a human's
timing (the tempo drifting up to 8%, onsets 25 ms early or late, notes let go early, breaths
between lines), three random takes each:

| | Today | The plan's bar |
|---|---|---|
| With a click or Tap along | every note, the bars and the key right | |
| The key, in free time | 21 of 22 | every song |
| The key of a short take (a tune's first two lines), in free time | 74 of 132 takes (was 55); Amazing Grace's two lines always G | |
| The bars (metre and pickup), in free time | 21 of 22 | 20 of 22 |
| Notes at the right pitch and beat, in free time | 92 to 99% | 90% |
| Through the pitch tracker (a flat, vibrato hum) | Amazing Grace note for note | |

What finds the bars best is the phrasing: people breathe between lines, and lines run 2, 4 or 8
bars, so the beats between breaths give the bar's length. The hard cases left: Wade in the Water's
syncopated opening (one take in three lands its beat a little wrong), Shenandoah's pickup after a
long held note, and Down to the River, a pentatonic tune that reads as E as well as A. With a
rougher hum (onsets 40 ms out, the tempo drifting 12%), notes fall to 70 to 86%: the next thing to
make sturdier, and where Tap along earns its place. The "sure of the beat" flag now trips when the onsets sit loosely on the
beat (the fit over 0.025 of a beat) or when the best beat grid is a poor fit even so (its cost over
1.8). On the labelled takes in tests/fixtures/hum-unsure.json (every fixture tune hummed gently and
roughly, eight seeds) it catches 5 of 7 wrong gentle takes and 21 of 33 wrong rough ones, and rises on
12 of 169 right gentle takes (about one in fourteen), so Tap along is offered where it's needed. The
rough takes it misses are confident and wrong (the beat locked half a beat out, or the bars misread):
nothing in the beat finder's own costs tells them apart, so a click or Tap along is their cure. A take of 3/4 can come
back as 6/8 (and 6/8 as 3/4) with the same bars and rhythm.

**The harmony at 2 to 4 singers (step 4), measured:** the creator's arranger, run on the 20 library
tunes from their melodies alone, voices every four-part chord with its third, but the duet and the
trio (soprano and alto, plus the bass) miss it at about a third of the chord changes (32%), where
the hand-written library now misses it rarely. Weighting "the third in the soprano or alto" more
helps only so far: at the weight that brings it to 20%, the inner voices leap over a fifth four
times as often, because holding the alto off the third is often what avoids parallel octaves with
the tune at the next chord. The fix is a voice exchange planned across chord changes (the alto
takes the third, the tenor the root or fifth, swapping back when the tune moves), as the library
was rewritten by hand: the next piece of work, its diff to the frontend before landing.

**Step 4, first part, built (2026-10-29):** the alto may start a slot just under the tune's note
there (the note pass moves it under a lower note), "the third in the soprano or alto" and the
duet's bare intervals weigh more, a leap over a sixth costs far more, and one placement of the alto
and tenor under each note of the tune is used by the search and the note pass alike, so the search
judges what is sung. On the 20 tunes, in all three styles, the duet and trio miss the third at 19 to
22% of chord changes (from 32 to 35%), and the four parts are no worse: no fewer chords with their
third, no more parallels, no crossings, no inner leap over a sixth (there were 3), as many or fewer
leaps over a fifth in all. The test holds those (`hum.test.js`), with the bar at 22%. What keeps it
from 10%: a tune too low for an alto third under it, and a third that would cost parallels at the
next chord. After launch: a search that plans the alto across two chords at once, so a third that
would cost a leap or parallels at the next chord can be reached another way (letting the alto
cross briefly above a low tune was tried and gained almost nothing: Hillel and the coordinator
agreed not to build it). The arranger's bass floor stays
E2 (`RANGES.bass`, 40), a semitone under `VOICE_RANGES`' F2, as it was; the test allows it, and
the 31 port keeps the same floor.

**For the screens (asked by the frontend, built):** `humToMelody` returns `phrases` as
`{ fromNote, toNote, startBeat, endBeat, startSeconds, endSeconds }` and the beat `grid`, so "Again"
can re-record one line; `rehum(melody, index, events)` puts a line hummed again in that line's
place on the same grid, keeping the tempo, the metre and the key; and
`tests/fixtures/hum-two-lines.wav` is two lines of Amazing Grace hummed (flat, with vibrato, a breath
between them) for the flows to play into the mic.

## Order of work

1. This plan, Hillel's answers, the files agreed with the frontend.
2. `hum.js` with the synthetic hums, until the bar above is met.
3. The free-time mode in `transcribe.js` and the hummed-note pitch.
4. The harmony rules in `arrange.js`, checked with the library's rules.
5. The frontend's steps, then real hums from Hillel and the beta singers, and a choral review.

---

[Back: Your tune, sung](your-tune-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs)
