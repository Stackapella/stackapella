# Template creator: sing it, or write it

**Status:** planned, not started (2026-10-26; revised after a UI/UX review the same day). It's
roadmap M7, at launch: private and unlisted templates shared by link, plus choir groups. The
public library and its search wait for a network (N3). Build it in the order under "Order of work".

## Context

Today templates are JSON files written by hand, or converted by Claude from public-domain scores.
This plan adds an interface for anyone to make one, in two ways that work together:

1. **Sing it.**
   - Paste the words, then sing the melody line by line to a click, with the words in front of you.
   - The app finds the notes and fits a syllable to each, then suggests the other parts (SATB).
   - Fix anything by hand.
2. **Write it.** Enter and edit the notes and words directly, in a note editor built from the
   singing UX's Note lane, with the staff as a second view.

It follows the style and UX already chosen (`docs/singing-ux-plan.md`):
- the warm dark studio, Bricolage Grotesque, the 8 part colours;
- the Coach's one-step-at-a-time flow (2d) for the wizard;
- the Stage with Get ready (1a, 2a) while singing a line;
- the Note lane (2b) as the editing surface, with labelled bars and a part rail;
- the Staff to check;
- "Try it as a learner" opens the singing UX itself.

**Hillel's answers (2026-10-26):**
- **Words:** type or paste them, and the app fits them to the notes. No speech recognition:
  offline, free, works in every browser, and no audio leaves the device.
- **Harmony:** yes, from a melody the app suggests chords and drafts alto, tenor and bass, all
  editable.
- **The creator's voice:** the notes are always extracted. Keeping the recording as a human guide
  is optional, and a Plus feature (`docs/pricing-plan.md`).
- **Storage:** on this device for now, with export and import as a file. Sharing and the public
  library come with accounts (`docs/templates-plan.md`, Phases 2 and 3).

## UI/UX review (2026-10-26): what changed and why

| Before | After | Why |
|---|---|---|
| Title, credits and licence first | **Only tempo and time signature first;** naming, credits and the licence come at the end | Get people singing within a minute. The details are only needed to save, or, later, to share. |
| Sing, then paste the words | **Paste the words first, then sing line by line** with the line on stage (karaoke, Get ready) | You sing better reading the words. Each take is exactly one line, so fitting syllables is nearly certain and re-singing a line is natural. It reuses the Stage and Get ready designs. |
| Notes appear after singing | **A live pitch line** draws on the lane as you sing, then settles into notes | It shows the app is hearing you, and you can see the result forming. |
| Uncertain notes highlighted | **"3 notes to check":** a guided review that steps through only those notes, each with Hear and Fix | Passive highlights get ignored. A short checklist gets done. |
| Chord chips (music theory) | **Harmony styles** (Simple, Warm, Bright) to listen to and choose; **"Try another chord"** on any bar; chord names only under **Advanced** | Beginners judge by ear, not by Roman numerals. |
| An octave fix afterwards | **"Singing this part an octave up or down?"** before recording | It prevents the most common transcription error instead of repairing it. |
| An editor without sound | **Every note sounds** when placed, moved or selected, plus zoom, a **Words mode** (type and the cursor moves note to note), and a **shortcut sheet** (`?`) | Hearing each note is how people check pitch; lyric entry should feel like typing. |
| Phone: pitch up and down by button | **Phone: sing a note to set its pitch** (hold to sing), plus the buttons | Faster and more natural on a phone than tapping semitones. |
| Error states unlisted | **Every failure has a plain message and a way out** (see "When things go wrong") | No dead ends. |
| An autosaved draft, nowhere to see it | **"Draft saved"** in the header, and **Unfinished** on the projects page with Resume and Discard | People leave halfway; make it easy to come back. |

## Where it lives

- **On the projects page,** in **Learn a harmony**: a **Make your own** card leading to the
  creator; **Your templates** (yours, first); and **Unfinished** (drafts, with Resume and Discard).
- **On each official template:** a **Make a copy** button in its details, which opens it in the
  editor as a private template.
- **In the studio menu:** **Make a template from this project.**
- **The route:** `/create` (new) and `/create/<draft>` (resume).

## The flow

There's a progress bar along the top (the Coach style), Back and Next, and "Draft saved" in the
header. One clear action per step; everything else waits.

### Sing it
1. **Tempo and feel** (one screen):
   - tempo: type it, **tap it**, or pick from slow, medium and fast, with **Hear the click** to
     check;
   - the time signature (4/4, 3/4, 6/8);
   - "Does the tune start before the first beat?" (a pickup, with a picture);
   - headphones on, plus the mic picker and timing check that already exist.
2. **Words:**
   - paste the lyrics, one line per phrase;
   - the app shows them split into syllables ("A-maz-ing"), and you tap to fix a split;
   - **No words?** Choose "Just 'oo'", and each line is a phrase you name.
3. **Sing the melody, line by line:**
   - the line is on stage (Stage style), with a Get ready countdown and the click;
   - as you sing, **a live pitch line** draws on a lane under the words;
   - after each line it settles into notes, and the syllables land on them;
   - then **Next line**, **Again**, or **Hear it** (the synth from your notes, then your voice, A
     and B);
   - **Sing straight through** is an option for confident singers;
   - before the first line: **"Singing this part in a different octave?"**
4. **Check the notes:**
   - the whole melody on the lane, with the staff a tap away;
   - **"3 notes to check":** one at a time, hear it and fix it (move, split or join), or keep it;
   - any line can be **re-sung**;
   - the key is detected and shown as a chip ("In G major"), which you can change.
5. **The other parts:**
   - **Suggest them:** three **harmony styles** to hear and choose from:
     - Simple: mostly I, IV and V, with the parts moving together;
     - Warm: adds ii and vi, with smoother inner parts;
     - Bright: the alto above the tenor in a higher, open spacing;
   - then **"Try another chord"** on any bar; **Advanced** shows the chord names;
   - **Sing them:** record each part over the melody, line by line, like step 3, with the melody
     guide playing;
   - **Write them:** in the editor;
   - then choose the versions (2, 3 or 4 singers).
6. **Name and save:**
   - the title, words by, tune by, and **"Who wrote this song?"** (I did; it's traditional or
     public domain, with an optional source; someone else, which keeps it private);
   - **Try it as a learner:** opens the singing UX on the draft;
   - the checks (`validateTemplate`) appear as friendly fixes;
   - **Save** puts it under Your templates; **Export** saves a file.

### Write it
1. **Tempo and feel** (as above).
2. **The editor** (below), starting empty, from a pasted file (ABC or LilyPond, the formats the
   official templates came from), or from **Make a copy**.
3. Then **The other parts** and **Name and save**, as above.

## The note editor

**Desktop and tablet** (full editing):
- **The layout:**
  - **left, the part rail:** Soprano, Alto, Tenor, Bass, each with its range, mute and solo, the
    octave choice, and "edit this part". Others show faintly behind, or hide.
  - **centre, the lane:**
    - notes as bars, labelled with the syllable and note name;
    - a beat and bar grid, with the pickup marked;
    - the playhead; a loop region to drag;
    - zoom (pinch, or Ctrl and scroll).
  - **above, the tools:** Notes or **Words** mode; draw, select, erase; the grid (1/4, 1/8, 1/16);
    chords (Advanced); Lane / Staff; undo and redo.
  - **right, the inspector** for the selection: pitch (named), start, length, syllable, melisma,
    and the phrase.
- **Sound:** every note sounds when placed, moved or selected (`engine.preview` with `renderPart`).
- **Selecting:** click, Shift-click, drag a box, or Cmd+A. Drag a bar's end to change its length;
  drag the middle to move it. Everything snaps to the grid, with a visible snap line.
- **Words mode:**
  - type a syllable, then **Space** moves to the next note;
  - `-` joins a syllable to the next ("A-" then "maz-"), `_` holds the vowel over this note (a
    melisma), and **Enter** starts a new line;
  - pasting a line fits it from the cursor on.
- **Keyboard** (a sheet opens on `?`):
  - up and down: a semitone; Shift with up or down: an octave;
  - left and right: move by the grid; Alt with left or right: the next or previous note;
  - 1 to 6: lengths, from a sixteenth to a whole note;
  - Space: play from the cursor; L: loop the phrase;
  - Cmd+Z and Shift+Cmd+Z: undo and redo; Delete.
- **The staff:** shows the same notes for checking, read-only here.

**Phone** (fixing, not composing):
- tap a note to open a sheet;
- **hold to sing its pitch** (live note name, released to set);
- or pitch up and down with its name, longer or shorter, the syllable, and delete;
- add a note after it;
- re-sing a line;
- the phone says detailed editing is easier on a bigger screen.

## When things go wrong

| Situation | What the person sees | The way out |
|---|---|---|
| The mic is silent (all zeros) | "We can't hear your mic." | The mic picker; the system settings tip |
| No notes found in a take | "We didn't catch any notes. Sing a little louder, on 'la' or the words." | Again |
| A noisy room (low clarity throughout) | "It's noisy where you are, so some notes may be off. Check them after." | Carry on, or Again |
| Far off the click (most notes between beats) | "You were ahead of the click. Want a slower tempo?" | Slower and Again, or keep it |
| Speeding up or slowing down through a line | "You sped up toward the end." | Again with the click louder, or keep it |
| Syllables and notes don't match | "This line has 9 syllables and 8 notes." | Split the longest note, or join two syllables (one tap each) |
| An octave slip | The note shows an octave away from its neighbours, as a note to check | Fix by one tap |
| A rejected file (import) | The checks' message, plainly ("Note 12 starts before note 11 ends") | Open in the editor anyway, to fix it |

## How the parts work (all on the device, plain JS, unit-tested)

### Transcribe: `web/js/transcribe.js`, run in a worker
1. **Record** each line with the existing engine. The measured latency is compensated, and the
   click sets the beat grid.
2. **Live:** `detectPitch` on `engine.inputFrame()` every animation frame, for the pitch line.
3. **After the take:** `detectPitch` every 10 ms on 2048-sample windows, with clarity and loudness.
4. **Clean up:**
   - fix octave errors (a frame an octave from both its neighbours);
   - smooth with a median filter;
   - measure the singer's overall offset in cents and remove it (singers drift flat).
5. **Split into notes:**
   - at voiced and unvoiced edges;
   - where the pitch moves more than about 0.6 of a semitone for at least 60 ms;
   - at loudness onsets, so repeated notes on one pitch split;
   - scoops under a sixteenth fold into the next note.
6. **Pitch per note:** the median of its steady middle, rounded, nudged to the key unless clearly
   chromatic. Apply the part's octave choice.
7. **Timing:** snap to the grid (1/8 by default); measure how far the take was from the click, and
   whether it sped up, for the error messages.
8. **Key:** a pitch-class profile weighted by length.
9. **Confidence per note,** feeding "notes to check".

### Syllables and fitting: `web/js/lyrics.js`
- **Splitting words into syllables (English):**
  - Liang (TeX) hyphenation patterns from the CDN, pinned with SRI. This needs Hillel's OK as a
    new library.
  - Otherwise a built-in rule set with an exceptions list.
  - Other languages: whole words, split by hand.
- **Fitting:** each sung line takes exactly its pasted line, so alignment is within a line only. A
  small dynamic program gives each syllable a note:
  - extra notes become melismas, best on long or stepwise notes within a word;
  - fewer notes than syllables offers a one-tap split.

### Harmony: `web/js/arrange.js`
A port of the Python arranger used for the official templates (see `docs/templates-review.md`).
1. **Chords:** per half bar (a whole bar in slow 3/4), a diatonic chord scored by how much of the
   melody it covers, weighted by length and beat.
   - **Styles:**
     - Simple: I, IV, V, V7;
     - Warm: adds ii and vi;
     - Bright: Simple's chords, voiced higher and more open.
   - Cadences at phrase ends.
   - **"Try another chord"** cycles through the next best options for that bar.
2. **Parts:**
   - **alto:** the nearest chord tone below the melody;
   - **tenor:** the missing chord tone, moving as little as possible;
   - **bass:** the roots (Warm adds passing inversions).
   - Each part stays in its range.
3. **Checks:** no crossings; flag parallel fifths and octaves between the outer parts.
4. **Versions** for 2, 3 and 4 singers.

### Storage
- **IndexedDB** (version 5 in the registry in `docs/architecture.md`, adding stores):
  - `templates`: the user's templates, in the same format, marked `source: 'device'`;
  - `templateDrafts`: the wizard and editor drafts, autosaved. Undo history is in memory only.
- **The loader** (`web/js/templates.js`) merges official and device templates; Your templates come
  first, and Unfinished lists the drafts.
- **Recorded guides (optional, Plus):**
  - `part.guide` gains an optional `recording`, with the audio in the `audio` store under
    `template:<id>:<part>`;
  - the player can use it in place of the synth;
  - notes stay required.
- **Editing** a saved template makes a new version; projects keep theirs.
- **Export and import:** a `.json` file with the notes, and without recordings in this version.
  Import validates first, and offers to open a broken file in the editor.
- **The free limit** (1 private template) isn't enforced until accounts exist.

## Accessibility
- **The editor works fully from the keyboard.**
- **Every edit is announced politely** ("D4, quarter note, 'grace'").
- **Notes have a list view** for screen readers: the selected phrase's notes as text.
- **Colour is never the only signal:** to-check notes also carry an icon.
- **Touch targets** are at least 44 px.
- **Reduced motion:** the pitch line and playhead step instead of gliding.

## Review notes folded in (misses and code fit)
- **Tempo first, always singing to a click.** Finding the tempo from free singing is unreliable.
  Latency compensation matters here as much as when recording.
- **The pickup** is found from the first note against the first downbeat; the "starts before the
  first beat?" picture explains it.
- **Not in this version:** triplets and swing (the grid is straight), and the UI says so.
- **Long songs:** transcription runs in a worker per line; the lane only draws what's visible.
- **Reuses:**
  - `detectPitch` and `midiName` (`dsp.js`);
  - `renderPart` (`synth.js`);
  - the engine's recording, latency, `preview` and `inputFrame`;
  - `validateTemplate`, `lyricLines`, `phrases`, `partsFor`;
  - from `docs/singing-ux-plan.md`: the session store, the lane and staff drawing, and the Stage and
    Get ready components.

  **So build this after the singing UX.**
- **Testable without a voice:** synthesized melodies (`renderPart`) go through loopback into the
  transcriber and must come back as the same notes. Vibrato, drift, noise, scoops, repeated notes,
  rests and octave slips are all generated in tests.

## Order of work (each step committed and tested)
1. **Storage:**
   - device templates and drafts (IndexedDB version 5), merged into the shelf;
   - Unfinished, with Resume and Discard;
   - Make a copy, then export and import;
   - the optional `guide.recording`.
2. **Transcription,** with tests:
   - a synthesized Amazing Grace melody comes back note for note, line by line;
   - plus vibrato, a 30-cent flat drift, noise, a scoop, repeated notes on one pitch, rests, an
     octave slip, and singing ahead of the click (measured);
   - and key finding.
3. **Lyrics,** with tests: the syllables of the official templates' words match their files;
   line-by-line fitting places every syllable; mismatches offer the right split.
4. **Harmony,** with tests: plausible chords for the official melodies in each style, cadences, no
   crossings, parts in range, versions for 2, 3 and 4, and "Try another chord" giving different
   valid chords.
5. **The wizard (Sing it):**
   - tempo and feel, words, line-by-line singing (Stage, Get ready, live pitch line), notes to
     check;
   - then the other parts (styles), and name and save;
   - phone and desktop.
6. **The editor (Write it):**
   - desktop, with sound, zoom, Words mode, the shortcut sheet, undo and the inspector;
   - phone sheets, with hold to sing a pitch;
   - the staff read view; file import.
7. **The extra entry point:**
   - **Make a template from this project;**
   - the error states table, checked one by one;
   - **Plus markers** (recorded guides), shown but not enforced;
   - docs.

## Files
- **New:**
  - `web/js/transcribe.js` (with `transcribe-worker.js`), `web/js/lyrics.js`, `web/js/arrange.js`;
  - `web/js/stores/templates.js`;
  - `web/js/views/create.js`;
  - in `web/js/components/create/`: `wizard`, `tempo`, `words`, `sing-line`, `check-notes`,
    `parts`, `styles`, `save`, `editor`, `inspector`, `shortcuts`;
  - `web/tests/transcribe.test.js`, `lyrics.test.js`, `arrange.test.js`, `device-templates.test.js`.
- **Changed:**
  - `web/js/db.js` (version 5);
  - `web/js/templates.js`;
  - `web/js/views/projects.js` (Make your own, Your templates, Unfinished, Make a copy);
  - `web/js/views/studio.js` (Make a template from this project);
  - `web/js/router.js` (`/create`, `/create/<draft>`);
  - `web/sw.js`; docs.

## Verification
- **`web/tool/test.sh`:** every existing test, plus the transcription, lyrics, harmony and storage
  tests above.
- **Flow scripts** (`web/tool/cdp.py`, muted; phone 390 x 844 and desktop 1440 x 900):
  1. **Sing it, with the voice simulated:** a synthesized melody is fed through loopback in place of
     the mic, one line per take.
     - The live pitch line draws; each line's syllables land on its notes.
     - "Notes to check" shows a planted uncertain note.
     - Pick a harmony style, Try another chord, try it as a learner, and save.
     - It appears under Your templates.
  2. **Drafts:** leave halfway, and it appears under Unfinished; Resume brings it back.
  3. **Make a copy** of Amazing Grace, move it up a tone, save it, and start a project from it.
  4. **The editor:**
     - add, move, lengthen and delete notes; each sounds as you do it;
     - Words mode with Space, `-` and `_`; zoom; the shortcut sheet;
     - undo and redo; reload restores the draft.
  5. **Errors:** a silent mic, an empty take, a take ahead of the click, a syllable mismatch, and a
     broken import file, each showing its message and way out.
  6. **Export, then import** in a fresh profile: identical.
  7. **Make a template from a project** with a recorded part.
- **By Hillel:** sing a melody you know into the creator on an iPhone and a Mac, line by line, then
  judge the notes, the fitted words, and the three harmony styles.

---

[Back: Go to market](go-to-market-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: More features](more-features-plan.md)
