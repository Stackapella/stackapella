# MVP: the backing, real instruments, Harmonise my song and instrument parts

Status: in progress (web only; the Flutter apps follow later). Asked by Hillel on 2026-10-28, to be
built lean: good defaults, few options, and an honest list of what's left for production.

## What the MVP covers

| Piece | Owner | MVP |
|---|---|---|
| Instrument loader | frontend | One shared loader for the CC0 sample sets, lazy and cached, on the audio clock, falling back to the synth when the samples aren't there |
| The backing | frontend | Sampled piano or electric piano chords, a bass on the roots with simple walks, an optional drum or brushes groove, in 2 or 3 styles |
| Harmonise my song | landing agent | "Harmonise this" on a recorded melody take: transcribe, find the key, suggest alto, tenor and bass, Try another chord, add them as guide parts |
| Instrument parts | landing agent | A part can be piano, guitar, bass or drums, playing chords, the bass line or a groove through the loader |
| Finding the samples | backend | The manifest and CDN base for the web, a clean fallback, and the upload steps in the runbook |

## Hillel's part

Upload the sample sets to the media CDN (the runbook's steps); until then everything plays on the
synth.

## Remaining for production

Each agent adds what's untested or missing here when it lands its part.

### Harmonise my song and instrument parts (landing agent, 2026-10-28)

- **Transcription on real voices.** Tested only on the test singer's synthesized voice (with vibrato
  and noise) and on pure noise. Real takes with breath, consonants, rubato, a drifting tempo and
  room echo are untried; the "too unclear" line (fewer than 4 notes, over 40% unsure) is a guess
  to tune on real recordings.
- **Tempo and beat.** It assumes the song's own tempo (the click it was sung to). Detecting the
  tempo from unaccompanied singing, a tempo map that follows drift, and the tap-along check from
  docs/harmonise-my-song-plan.md aren't built.
- **More chord vocabulary.** The arranger's palettes (triads, a dominant seventh, the warm style's
  extras). No secondary dominants, borrowed chords, slash chords or inversions chosen for the bass
  line, and nothing for jazz or gospel colour.
- **Instrument choices.** Four (piano, nylon guitar, electric bass, drums), one groove and one
  broken-chord pattern each, at most two at a time; no electric piano, ukulele, upright bass,
  strings, organ or brushes yet, though the sample sets exist. No choice of pattern, feel (swing),
  fills or endings, and a song's instruments can't be changed after Add to my song except by
  deleting a part.
- **Mixing.** Instrument parts play at the guides' level with no balance against the voices, no
  pan spread and no per-instrument level; the template's `mix` field isn't used.
- **Singing over an instrument part.** The studio treats it like any guide: it can be recorded
  over, and Sing would score pitch on it (meaningless for drums). Sing and the scoring should leave
  instrument parts out, or record a real instrument there with pitch feedback off.
- **Tests on real recordings.** The flow and unit tests use synthesized singing and the synth
  fallback; the real samples have only the frontend's fixture set. Needed: three real takes
  (steady, drifting, rubato) judged by ear, with before and after audio for Hillel, and a check
  with the real sample sets once they're uploaded.
- **Device checks.** Not run on iPhone Safari or Android Chrome: rendering a six-part song with
  instruments offline (OfflineAudioContext memory and time on a phone), and the creator's Parts
  step with the instrument chips at phone width.
- **The Flutter port.** None of it is in the apps yet: Harmonise this, adoptTemplate, the
  instrument parts and their rendering all need porting (docs/ux-spec.md has both entries).

### The instrument loader and the backing (frontend, 2026-10-28)

- **Never heard on the real samples.** Nothing is uploaded yet, so every test plays the synth
  fallback, or the frontend's one-zone test set (a sine, web/tests/fixtures/instruments/). Needed:
  each style (Gentle, Pop, Swing) listened to on the uploaded piano, electric piano, upright and
  electric bass, drums and brushes, with the levels and the attack placement checked by ear
  against a vocal (contract/instruments.md's onset and priming rules are followed, but unproven
  on real files and on Safari's decoder).
- **Loading.** A set is downloaded and decoded whole (2 to 6 MB each) before it plays, and the
  first play of a style waits for up to three sets. There's no progress shown and no partial play
  from the nearest zone; on a slow connection the first Pop backing can take seconds, then plays on
  the synth if a file fails. Decoded sets stay in memory for the session (no eviction).
- **The styles are few and plain.** Three, each one pattern: Gentle (a held chord and the root each
  change), Pop (keys and bass on every beat, kick, snare and eighth hats), Swing (comping on the back
  beats, a walking bass, brushes). No fills, endings, intros, dynamics across sections, voice
  leading in the keys (each chord is a fixed close voicing), 3/4 or 6/8 grooves (they play the
  4/4 patterns bar by bar), a guitar style, or a choice of instruments within a style.
- **Mixing and levels.** Fixed track levels (keys 0.7, bass 0.85, drums 0.45) under the voices, no
  pan, no room on the backing, and the synth fallback's balance differs from the samples'. Sing's
  chords level and Export's polish apply on top, untuned for the band.
- **Where it's used.** The song builder (its backing loop, with the "oo" voices now off unless
  asked), Sing's chords underneath (the style chosen in Session settings or Song settings; drums
  only in Pop and Swing there) and Export's piano (the keys alone). The template songs' own `mix`
  and any per-song style in the template format aren't used; the style is kept per song in its
  practice settings, so it isn't shared with friends singing the same song.
- **Mobile performance.** Rendering a whole song's band on an OfflineAudioContext on a phone
  (memory and time, with three sets decoded) is untested; nor is the service worker's cache of
  the sample files on iOS (storage limits).
- **Licences.** All sets are CC0 and credited in THIRD-PARTY-NOTICES by the backend's build
  (`credit` in each manifest); nothing in the app shows credits for them, and none is required.
- **Device checks.** iPhone Safari (installed and in the browser), Android Chrome, Safari and
  Firefox on a Mac: each style in the song builder and Sing, Export with piano, and offline after
  one play.
- **The Flutter port.** The loader (the same manifest and playing rules), the band's arrangement
  and the style choice all need porting; docs/ux-spec.md has the entries.
