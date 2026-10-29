# Harmonise my song: from a recorded project to notes, harmony, chords and a band

**Status:** the MVP is built (2026-10-28, the landing agent): Harmonise this, through the creator's Parts step, then Add to my song (docs/ux-spec.md, the part's panel). The tempo map, the beat check and the band are still to come. Planned 2026-10-27, from Hillel. Built by the frontend agent after the backing
overhaul (it needs the band), reusing the creator, the song builder and the arranger. The apps
copy it later.

Back: [The song builder](song-builder-plan.md) · [The template creator](template-creator-plan.md) ·
[Your tune, sung](your-tune-plan.md)

## The idea

Someone records a song straight into a new project, with no template: they just sing it. They
should still get the harmony features **from that recording**, easily:

1. **Turn my singing into notes.** The lead take is transcribed (the pitch tracker, onsets, the
   rhythm), as the creator's Sing it does.
2. **Add harmony.** The arranger suggests the alto, tenor and bass (or whichever parts fit) under
   the melody, as guide voices.
3. **Figure out the chords.** The key and the chords come from the melody (`chooseChords` on
   `theory.js`), shown as a chord row with "Try another chord".
4. **Add a band.** The song builder's backing (voices, keys, guitar, bass, light percussion, or the
   real instruments once they're in) plays the chords in the song's tempo and feel.

Everything lands **in the same project, lined up with the original take**, as new parts the person
can keep, mute, or sing over to replace (the usual stacking). The original recording is never
changed.

## Where it lives

- **In the studio:** a "Harmonise my song" action on any project with a sung take, also offered
  once after the first take of a new project ("Want harmony and a band for this?").
- **One step at a time**, in the Coach style: Notes, then Chords, then Harmony, then Band, each
  playable in context before going on, with Back and Skip on every step.

## The steps

1. **Pick the take** (the latest by default) and its part.
2. **Notes:** "Turning your singing into notes" with progress, then the note lane over the take's
   waveform. Hear notes and take together, fix any note (drag, or sing it), and fix "3 notes to
   check" as in the creator. **Tempo and beat:** if the project had a click, use it; otherwise
   detect the tempo and the downbeat from the singing (the onsets), show a tap-along check ("Tap to
   the beat while it plays") and let them nudge it. If the singing drifts, the notes follow the
   singing (a tempo map), never the other way round.
3. **Chords:** the key ("Sounds like G major") and a chord per bar or half bar, suggested from the
   melody's strong beats; a chord row with Try another chord, and the harmony styles (Simple,
   Warm, Bright) to hear and pick.
4. **Harmony:** the parts voiced from the chords by the reworked arranger, as guide voices; choose
   which parts to add (one, two or all three), in ranges that fit (the person's own range if
   known, since they may want to sing them).
5. **Band:** the backing's style picker (Voices, Piano, Guitar, Band, Just a beat) and the small
   mixer, following the song's tempo map so it stays with the singer.
6. **Add to my project:** the new parts appear in the stack under the original, each with its
   name and colour ("Alto (suggested)", "Band"). From here it's the normal studio: mute, solo,
   levels, sing over a part to replace it, export, share. "Save as a template" is offered too, so
   others can learn it.

## Rules it keeps

- On the device: no audio leaves it for this. Free, like the creator and the song builder.
- Everything that must line up is scheduled on the `AudioContext` clock.
- Honest about confidence: notes it isn't sure of are flagged, never silently guessed.
- Voices first, instruments welcome: an instrument take (a guitar riff) can be the melody too, with
  pitch feedback off where it can't be tracked.
- Words in the shared locales; the apps follow it through `docs/ux-spec.md`.

## Hard parts (to prototype first)

- Tempo and beat detection from unaccompanied singing, and following the drift.
- Transcribing rhythm (note lengths and rests) well enough to arrange from.
- Keeping the added parts aligned with the take when the person later trims or nudges it.

## Order of work

1. A prototype on three real recordings (steady, drifting, with rubato): notes, tempo map and
   chords, judged by ear with before/after audio for Hillel.
2. The steps in the studio, then the band on the tempo map.
3. Review (music theory and UX), then the apps.
