# Singing UX: the whole song (Stage, Notes, Staff) or part by part (Coach and Phrase path)

**Status:** planned (2026-10-26). Step 1, the session store: built in M1 (2026-10-26). The rest is
M3: built in M3 (2026-10-27), then redesigned the same night after Hillel's originals 2a and 2b
(`docs/decisions.md`, "the Sing redesign"). The course (`docs/vocal-coaching-plan.md`) builds on it: its lessons run in these screens, the
Note lane becomes the play-along screen, and the phrase path shows stars. Build it in the order
under "Order of work".

## Context
Hillel explored learning-mode directions in Claude Design and chose:

- **First, ask how to sing:** "The whole song" (sing live) or "Part by part" (learn one part at a
  time).
- **Part by part** combines **2d Coach** (one step at a time, the "Your turn" card, the step-done
  screen) with **2e Phrase path** (the song as a vertical path of phrases, ticked as you practise).
- **The whole song** switches between **2a Stage** (karaoke words; Hillel added an optional
  **music staff**) and **2b Note lane** (notes scrolling toward a now line).
- **1a Karaoke stage** is a source of design cues. Hillel also shared the **desktop layouts** for
  2a (listening) and 2b (recording).

Today learning happens in the studio's practice bar. This builds a dedicated **Sing** experience on
top of the existing engine (segments, phases, recording), practice logic (`voiceLevels`) and
templates. The studio stays as the mixer and editor.

## UX review: the best of every design, as one set of rules

| Element | Taken from | Rule (phone and desktop) |
|---|---|---|
| **Each part's current note and syllable** | 2a desktop part cards ("Alto D4 'grace'"), 2b desktop rail | The part strip always shows what every part sings right now. Phone: compact chips with the note; desktop: cards (Stage) or a left rail (Notes). |
| **Part status** | 1a strip (singing, "you and the guide", resting), 2b rail (Guide, Recording, Recorded) | One status per part: singing, resting, you (guide / your take), recording, recorded, muted. |
| **Header status chip** | 2a desktop "Alto · Listening", 2b desktop "Recording alto · 0:07" | Always shows your part and the phase: listening, get ready, your turn, recording with a timer. Red while recording. |
| **Big words** | 1a, 2a phone and desktop | Current line huge, left-aligned on desktop and phone; the next line dim beneath. Unsung words dim, sung words bright, the current syllable in your part's colour. |
| **Lit syllable** | 2a (coloured and underlined), 1a your turn (solid pill) | Listening: coloured and underlined. Your turn and recording: a solid pill in your colour. |
| **"Now D4, next D4, then C4"** | 1a your turn, 2b "D4 now" card | One "now" element: the note big and named, the syllable, and the next two notes with the direction. Stage: a line under the words; Notes: the card top-left. |
| **Lane bars labelled** | 2b desktop ("grace, D4", "how B3") | Your bars carry the syllable and note name; the others are faint, in their colours, and can be hidden. |
| **Live waveform of your take** | 2b desktop recording | While recording, your take draws live behind the now line, so you can see the mic is hearing you. |
| **Mic level** | 1a recording (segmented bar), 2b desktop rail | Shown whenever the mic is open: a segmented bar on the phone, at the bottom of the rail on desktop. |
| **Lyric line in the Notes view** | 2b desktop bottom bar | The Notes view always shows the current line (lit word) in the bottom bar, so words never disappear. |
| **Get ready** | 1a ("Your turn in 2", dots, "Breathe in", "Next: start on D4"), 2a phone card | One bar before every entry after a rest of a bar or more: a countdown with beat dots, "Breathe in", and the first note and line. Phone: takes over the stage; desktop: a large overlay in the stage area. |
| **Your turn colour** | 1a (the stage turns your colour) | Phone: the stage background becomes your part's colour, with dark text. Desktop: a strong tint of your colour, plus a coloured frame (full colour across a big screen is harsh). |
| **Recording** | 1a and 2a phone (red edge, timer, one big Stop), 2b desktop (red pill, red now line) | Phone: a red edge, the timer, the mic bar, a big Stop. Desktop: a red header pill with the timer, a red now line (Notes) or frame (Stage), and Stop in the bottom bar. Controls never fade while recording. |
| **Controls** | 1a (fade after 2 s, tap anywhere), 2a desktop (always in the bottom bar) | Phone: they fade after 2 s while playing and come back on tap. Desktop: a permanent bottom bar with the time, play/pause, record, the mode, and loop. |
| **Mode** (Listen, Sing along, On your own, Record) | 1a idle tabs, 2b phone chips | Phone: tabs in the control sheet; desktop: a segmented control in the bottom bar. In part by part, the coach sets it. |
| **First note** | 1a ("D4 first note"), 2a, 2b ("Your first note B3, Play it"), 2e | Always one tap away: next to Play on the phone, and in the bottom bar on desktop. |
| **The first line idle** | 1a idle | Before playing, the stage already shows the first line and the first note. |
| **Coach steps** | 2d | A 4-segment progress bar, "Step N of 4", one plain sentence, one big action. |
| **Phrase path** | 2e | Phrases as a vertical path: ticked with "practised N times", "you are here" with Listen, then repeat and the starting note, and what comes next. |
| **Your turn card** | 2d | During a Listen-then-repeat turn: a full card in your colour ("Your guide is off now. The soprano, tenor and bass are still singing with you."), the line large, **Guide back on** and **Again**. |
| **Step done** | 2d | A tick, "Lovely. You sang the alto on your own.", the checklist, the next step's button, and "Practise once more". |
| **Escape hatches** | 2d | "Choose another part" and "Skip to record" on every coach step. |

### Layouts

**Phone (390 wide, full height, safe areas):**
- **Whole song:** the header chip, then the stage (words) or the lane, then the now line, then the
  part chips, then the fading controls.
- **Part by part:** the coach header with progress, then the phrase path (2e), with the current
  phrase's card holding the step action. The Your turn card and the step-done screen take over the
  screen.

**Desktop (1440 x 900):**
- **Whole song, Stage (2a desktop):**
  - header: the logo, "Song · section · key · BPM", and the status chip on the right;
  - the stage: huge left-aligned words, the next line, and the now line beneath;
  - a bottom row of **part cards** (each with its current note and syllable; yours highlighted);
  - on the right: the time, play/pause, Record, plus the mode, view switch, loop and first note.
- **Whole song, Notes (2b desktop):**
  - header with the status chip (red while recording);
  - a **left rail** of parts: name, range, status, the current note on the right, and the mic level
    at the bottom;
  - the lane: all parts, yours labelled, the now line red while recording, your live take
    waveform, and the "now" card top-left;
  - a bottom bar with the time, the current lyric line, and the controls (or Stop).
- **Part by part (not designed; derived from 2d and 2e plus the desktop rules):**
  - a **left column** with the coach header, step progress and the phrase path;
  - a **main area** showing the current phrase in the Stage or Notes view (whichever you used
    last), so learners see the words and notes while practising;
  - the Your turn card and the step-done screen take over the main area;
  - the bottom bar as above.

**Staff (Hillel's addition to the Stage view):**
- A **Words / Staff** switch.
- Staff shows the current line as a staff above the words: clef by part (treble for S and A, treble
  with an 8 below for T, bass clef for B), the key signature, notes, ledger lines, accidentals,
  syllables beneath, and the current note lit. It pages line by line, like a music stand.
- On the phone it replaces the big words with a smaller line under the staff. On desktop it sits
  above the words.

## Decisions made while Hillel was away (veto any)
1. **The Sing screens replace the studio's practice bar.** The studio keeps a big **Sing** button,
   the mixer and Export.
2. **The chooser** opens the first time a template project is opened, and from Sing. The choice is
   remembered per project.
3. **Recording goes through the whole-song recording state** in both flows (part by part step 4
   hands over to it, then returns).
4. **The turn colour is your part's colour.** Full on phones, a tint plus a frame on desktop, with
   dark text where it's full colour.
5. **Desktop part by part** uses the derived layout above (there's no design for it).

## Architecture

### 1. A shared session store (refactor first)
The audio session logic moves out of `web/js/views/studio.js` (994 lines) into
`web/js/stores/session.js`, unchanged:
- loading the project (`openProject`), `prepareGuides`, `ready()`;
- mixing: `levels`, `voiceMix`, `syncMix` over `voiceLevels` (`web/js/practice.js`);
- the transport: `play(from | loop | repeat)`, `stop`, and a tick loop exposing the position, phase,
  beat and done, from `engine.positionInfo()`;
- recording: `recordInto(slot)`, `finishRecording` with live peaks, `storeTake`, `clearTakeOf`, Undo,
  wake lock;
- practice state (`project.practice`), phrases, lyric lines, `templatePart`, `persist`.

The studio and the Sing screens both use it.

### 2. Pure helpers in `web/js/sing.js` (unit-tested)
- **`partTimeline(notes, beatSeconds, perBar)`:**
  - at any position: the current note and syllable, and the next notes;
  - the state: singing, resting, or getReady (with the beats left before an entry that follows a
    rest of a bar or more).
- **`nowParts(slots, template, position)`:** each part's current note and syllable, for the cards
  and the rail.
- **`nowText`:**
  - "Now D4, next D4, then C4 (down)";
  - "Coming up, you start on B3";
  - "Alto sings D4 on 'grace'".
- **`partStatus`:** singing, resting, you (guide or take), recording, recorded, muted.
- **Phrase progress:** `practice.progress[partKey] = { step, phrases: { index: count } }`.
  - **What counts as practised in each step:** Listen = the phrase played through to its end;
    Sing along = a round of the phrase with the guide quieter; On your own = a finished
    Listen-then-repeat turn. Loops count each round.
  - A step is done when every phrase has been practised in it, but **Next** and **Skip to record**
    are always allowed.
- **`staffLayout(notes, { key, transpose, clef, beatsPerBar, pickupBeats })`:**
  - each note's staff step, its accidental against the key signature (sharps or flats by key),
    ledger lines, hollow or filled head, stem direction, and bar lines;
  - `clefFor(part)` picks the clef.

### 3. Components (`web/js/components/sing/`)
- **`chooser.js`:**
  - "Part by part: learn one part at a time, then record it" and "The whole song: sing live with
    the others";
  - then choose your part (with the range check's suggestion);
  - suggests installing the app if it isn't installed (for no browser bar on iPhone).
- **`header.js`:** the song info and the status chip.
- **`parts.js`:** `PartChips` (phone), `PartCards` (desktop Stage), `PartRail` (desktop Notes, with
  the mic level). They share `nowParts` and `partStatus`; tap to switch part.
- **`stage.js`:** the words (dim, sung, current), the next line, the now line, the turn colour, and
  the Words / Staff switch.
- **`staff.js`:** the canvas staff from `staffLayout`.
- **`lane.js`:**
  - a canvas lane (about 6 s visible, redrawn from the tick loop through refs);
  - your labelled bars, the others faint (or hidden);
  - the now line (red while recording), the live take waveform, and the now card.
- **`getready.js`:** the countdown, dots, "Breathe in", and the next note and line.
- **`controls.js`:** the bottom bar (desktop) or the fading sheet (phone): time, play/pause, record
  or stop, the mode, view switch, loop, first note, and the mic bar.
- **`coach.js`:** the steps and progress, the phrase path, the Your turn card, the step-done screen,
  Choose another part, and Skip to record.
- **`recorded.js`:** Just recorded (with **"Sound off? Nudge timing"**, added after a review: wireless
  mics can change their delay mid-take, so the singer can slide the take earlier or later by ear in
  5 ms steps, up to 150 ms, hearing it against the others, without singing again. **"Line it up for
  me"** suggests the nudge from the median entry offset that `scoring.js` measures against the
  notes. For template parts it's offered when the median is more than 20 ms; it's saved as the
  part's offset):
  - hear it in the mix, Compare, Try again, Next part (`nextSlot`);
  - everyone done: a celebration, then Export.

### 4. Where it lives
- **The route** `/p/<id>/sing` (`web/js/router.js`) renders `SingView` (`web/js/views/sing.js`).
- **Back** returns to the studio, and the browser's back button works through history.
- **The studio** gets a **Sing** button (header and transport); non-template projects don't show
  it.
- **Landscape phones:** a single row for the strip and controls, and the words scale.

### 5. Engine
Only one addition: the beat index in `positionInfo()`. Everything else exists already: segments
(loop, repeat), phases, `preview()`, recording with live chunks, and `inputLevel()`.

## Review notes folded in
- **Missed:**
  - **Parts without words** (bass on "oo") default to the Staff, with note names.
  - **Rounds:** Get ready counts into a late entry (the alto at bar 3).
  - **Keys:** transposing updates note names and the key signature. When the real voice can't follow
    the key, a kind note explains it (`docs/more-features-plan.md`, feature 16).
  - **Added from a product review** (`docs/more-features-plan.md`): slow practice at 85% and 70%
    (feature 10), note labels and a keyboard on the lane (14), Re-sing this phrase on Just recorded
    and the phrase path (11), and the What's next card after a finished song (13).
  - **The offline list** gains the new files.
  - **Wake lock** during practice playback.
  - **Reduced motion:** no fades or pulses, and the lane steps instead of gliding.
  - **Screen readers:** Your turn and Get ready are announced politely, once per change.
  - **Keyboard:** Space, 1/2/3, L, N, R, and V (switch view).
- **UX:**
  - Skip to record everywhere; the chooser explains each option and remembers it.
  - The first line idle; the first note always one tap away.
  - Celebrate each step and the end.
  - The view and staff choice are remembered.
  - Controls never fade while recording.
  - The studio's mute, solo and volume carry into Sing for the other parts.
  - Practice modes govern only your part.
- **Code fit:**
  - The session is extracted first and proven by the existing tests and flow scripts.
  - Canvas views redraw through refs, not Vue re-renders.
  - Guides render once at the engine's rate; switching views never re-renders them.
  - Progress saves through the debounced `persist`.
  - `PracticeBar` is removed at the end; `NotesView`'s drawing code moves into the lane.
- **Scope:** "The whole song" is one singer singing their part straight through with the others
  playing; singing together live is out of scope. iPhone Safari has no full-screen mode for a
  page, so the screens fill the viewport (100dvh), and installing the app removes the browser bar.

## Order of work (each step committed and tested)
1. **The session store,** extracted unchanged. The existing tests and template flow scripts pass.
2. **`sing.js` helpers and their tests:** the timeline, now texts, part status, progress rules,
   `staffLayout` and clefs.
3. **The route,** the chooser, part choice, the header, and the parts (chips, cards, rail), with
   the Sing button in the studio.
4. **The whole song:**
   - the Stage (words, now line, turn colour, Get ready, fading controls or the bottom bar);
   - then Staff, then the Notes lane (labels, others, now card, lyric bar);
   - then recording (red edge or frame, timer, mic, live take waveform);
   - then Just recorded.
   - Phone and desktop throughout.
5. **Part by part:** the coach (steps, path, Your turn card, step done, skip) with progress saved,
   on phone and desktop (the left column plus the main phrase view), handing over to recording.
6. **Clean-up:** remove the practice bar, update the offline list, keyboard, accessibility and docs.

## Files
- **New:**
  - `web/js/stores/session.js`, `web/js/sing.js`, `web/js/views/sing.js`;
  - in `web/js/components/sing/`: `chooser`, `header`, `parts`, `stage`, `staff`, `lane`,
    `getready`, `controls`, `coach`, `recorded`;
  - `web/tests/sing.test.js`, `web/tests/staff.test.js`.
- **Changed:**
  - `web/js/views/studio.js` (UI only, plus Sing);
  - `web/js/router.js`, `web/js/main.js`;
  - `web/engine/engine.js` (the beat index);
  - `web/sw.js`, `web/css/input.css`;
  - `web/tests/index.html`; docs.
- **Reused:**
  - `voiceLevels`, `nextSlot`, `PRACTICE_DEFAULTS`;
  - `phrases`, `lyricLines`, `partsFor`;
  - `renderPart`, `renderGuide`, `midiName`, `rankParts`;
  - `Engine.play`, `positionInfo`, `preview`, `inputLevel`;
  - `BigLyrics` and `joinSyllables`;
  - the headphones, timing, range and mic dialogs.

## Verification
- **`web/tool/test.sh`:** every existing test, plus new unit tests:
  - the timeline: get ready one bar before the round's alto entry;
  - the now texts, and `nowParts` (each part's note at a moment);
  - the progress rules;
  - `staffLayout`: E4 on the treble clef's bottom line, B3 with a ledger line, F# in G major with no
    accidental, Bb in C with a flat, G2 on the bass clef's bottom line.
- **Flow scripts** (`web/tool/cdp.py`, muted, fake mic), each at **390 x 844 and 1440 x 900**:
  1. a new template opens the chooser;
  2. **Part by part:**
     - the alto, steps 1 to 3 on phrase 1;
     - the Your turn card during the turn, and "practised" counting up;
     - step done, then Skip to record;
     - recording, then Just recorded and the next part;
  3. **The whole song:**
     - the Stage idle shows the first line;
     - the part cards show each part's note while playing;
     - Get ready before the round's alto entry;
     - the turn colour;
     - Notes, with labelled bars and the now card;
     - Staff;
     - recording, with the red pill or edge, the mic level, and the live waveform;
  4. reload mid-coach, and progress comes back;
  5. Back returns to the studio.

  Screenshots of each state are compared with the 1a, 2a (phone and desktop), 2b (phone and
  desktop), 2d and 2e designs.
- **By Hillel:** sing Amazing Grace both ways on an iPhone in headphones, and on desktop.

---

[Back: Beta singers](beta-singers-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: The backend](backend-plan.md)
