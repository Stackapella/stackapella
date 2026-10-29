# More features: sounding good, a learning path, and singing together

**Status:** planned (2026-10-26). Built in M3 (2026-10-27): features 2 (the harmony path, as course units 5 to 7), 3 (chords underneath), 9 and 10 (intervals in the course, slow practice), 11 (re-sing a phrase, brought forward from M5), 14 (note names your way) and 16 (the kind word about the real voice's key).
- **At launch:** features 1 to 4, and 9 to 16 (added from a product review, below).
- **Needing a network** (opened when the admin's network features panel says so): 5, 6 and 8.
  The harmony gym (7) needs no network, so it's in the launch (M10).
- Their places are in [roadmap.md](roadmap.md).

## Context

Hillel asked what else Stackapella should have, then chose these eight to add to the plans
(2026-10-26). Each one serves the vision ([vision.md](vision.md)):
- **beginners finish a harmony that sounds good:** features 1 to 3;
- **people share it where their friends are:** feature 4;
- **the gallery becomes a place to sing together:** features 5 and 6;
- **people come back to get better:** features 7 and 8.

Considered and left out: gentle pitch correction, gifts, a choir director's progress view (it comes
with choirs), licensed pop songs, live singing together, built-in video recording, a general feed,
and streaks or reminder emails.

| # | Feature | When | Frontend | Backend |
|---|---|---|---|---|
| 1 | Make it sound good | Launch (M4) | Most of it | None |
| 2 | Your first harmony (the course's harmony units) | Launch (M3) | Most of it | Progress sync (M4) |
| 3 | Chords underneath | Launch (M3) | Most of it | Template schema only |
| 4 | Share as a video | Launch (M6) | The share flow | The render job |
| 5 | Open parts | Inviting a friend to a part at launch (M7); the public shelf when ready (N5) | The flows | The data and rules |
| 6 | The virtual choir | When ready, song by song (N6) | The flows | The choir mix |
| 7 | The harmony gym | Launch (M10) | All of it | Scores sync (exists) |
| 8 | Song requests | When ready (N7) | The pages | The data |

---

## 1. Make it sound good (launch, M4, frontend)

**Why:** home recordings sound dry and thin. A gentle room, level parts and an even loudness are
the biggest jump in quality a beginner will hear. That makes finishing, and sharing, feel good.

**What the person sees:**
- **On Export, Publish and Share as a video:** a **Make it sound good** switch, on by default. Under
  it, one choice of **Room**:
  - Dry;
  - Small room (the default);
  - Hall;
  - Chapel.
- **In the studio and on Just recorded:** a **Hear it polished** toggle, so playback can sound as it
  will when shared.
- **No other controls.** It's one switch and one choice, not a mixer ("Not a DAW", vision.md).

**What it does** (`web/js/polish.js`, pure where possible, unit-tested):
1. **Levels the parts:** each part's loudness is measured and brought to the same level. The
   melody sits slightly forward (about 1.5 dB). The person's own volume settings still apply on
   top.
2. **Evens out loud and quiet moments:** gentle compression on each part and a light limiter on
   the mix, with `DynamicsCompressorNode` in the `OfflineAudioContext`.
3. **The room:**
   - a `ConvolverNode`, with the impulse response **generated in code** (decaying filtered noise,
     with a decay time and brightness per room), so there are no audio files to download;
   - the parts are spread a little left and right, by voice (bass in the middle).
4. **The final loudness:** about -14 LUFS for sharing, as streaming and social apps expect, with a
   true-peak ceiling of -1 dB.

**Rules:**
- **Takes are always stored dry.** Polish is applied only on the way out (export, publish, video)
  and in playback when the toggle is on.
- **It never touches what's measured:**
  - the timing check and calibration chirps;
  - the recorder's input;
  - bleed detection (which compares the mic with the dry mix);
  - every score.
- **Exact timing is kept:** the reverb tail is added after the last note, and nothing moves.

**Tests:**
- a known mix comes out at -14 LUFS (±0.5) with no sample above -1 dBFS;
- two parts at different levels come out level;
- the timing check gives the same result with "Hear it polished" on;
- the render is identical on a second run (no randomness without a seed).

## 2. Your first harmony: a learning path (launch, M3, frontend)

**Now part of the course** (`docs/vocal-coaching-plan.md`, rewritten 2026-10-26 in the style of
Simply Piano): this path is the course's **units 5 to 7**, shown on the course map with stars,
not as a separate card. The steps below are those units' lessons; their progress lives with the
course's.

**Why:** a beginner doesn't know what to learn next, and the library alone doesn't say. A short
path, from easy to harder, explaining why each step is next, turns "a library" into "a way in".

**What the person sees:**
- **The first run** (roadmap M3) ends with "Start your first harmony", which opens the path.
- **On the projects page,** the path's card is above the library: "Your first harmony: step 2 of
  6". It shows the next step, why it's next, and Start.
- **Each step:**
  - a template, how many singers, and which part (for example, "Are You Sleeping, the 2nd voice");
  - a goal ("Hold your line while another voice sings a different one");
  - a short "why this is next";
  - it's ticked when the part is recorded, or after a good practice run (the scores from
    `scoring.js`), whichever comes first.
- **Skipping is always allowed,** and the path never locks the library.

**The first path** (drafted by Claude, checked by the musician, like templates):

| Step | What | Built on |
|---|---|---|
| 1 | **Hum against a drone:** match one long note, then move to the note above and below | A harmony exercise (`web/exercises`), the drone |
| 2 | **A round:** Are You Sleeping, 2 voices. The same tune, at different times | The existing template |
| 3 | **Two parts in thirds:** Michael, Row the Boat, 2 singers, the alto | The template's 2-singer version |
| 4 | **The bass line:** Amazing Grace, 3 singers, the bass (roots, on "oo") | The template |
| 5 | **The inner voice:** Amazing Grace, 4 singers, the tenor | The template |
| 6 | **The whole song is you:** finish all 4 parts of Amazing Grace | Templates and Sing |

**How:**
- **Paths are data:** `web/paths/*.json` (`id`, `version`, `reviewed`, `title`, and `steps` with
  the template, singers, part, goal, why, and what counts as done), checked like templates.
  `web/js/paths.js` loads them.
- **Progress** is in IndexedDB (the `learning` store, db.js version 2 in
  `docs/architecture.md`). It syncs with the account from M4 (`account.learning`, a small JSON on
  the server).
- **Later paths:** "Your first choir part", "Singing low (for basses)", "Harmony by ear" (using the
  gym, feature 7).

## 3. Chords underneath (launch, M3, frontend)

**Why:** holding a harmony note against a lone guide line is hard, because there's nothing to tune
to. A soft chord underneath gives beginners the harmony to lean on.

**What the person sees:**
- **In Sing and in practice,** a **Chords** toggle (off by default; the path turns it on for steps
  1 to 3), with a level slider.
- **Two sounds:** **Pad** (soft and sustained, the default) and **Piano**.
- **While recording,** the chords can play in the headphones, but they're never recorded into the
  take. They're **left out of exports by default** ("a cappella"), with **Include the chords** as
  an option.

**How:**
- **The template format** gains an optional `chords`: `[{ "beat": 0, "root": 7, "quality": "maj",
  "bass": null }]` (the root as a pitch class, transposed with the key). The JSON Schema in
  `/contract` gets it too.
- **The 8 official templates get chords:** written by Claude from each arrangement (the arranger
  that wrote them already chose the chords), and **checked by the musician** along with the notes.
- **Templates without chords:** the chords are worked out from the parts on the fly, using the
  chord step of the template creator's `arrange.js` when it exists (M7). Before that, the Chords
  toggle is hidden for them.
- **The sound:** `renderBacking(chords, { bpm, sampleRate, transpose, speed, voice })` in
  `synth.js` (additive synthesis, in the synth worker). It's rendered once per key and cached by
  `guides.js`, like the guide voices.
- **Mixing:** `voiceLevels` (`web/js/practice.js`) gains a `backing` voice, with its own level, and
  muted in exports unless included.

## 4. Share as a video (launch, M6, both tracks)

**Why:** most sharing happens on TikTok, Reels and Shorts, and a link to audio barely gets played
there. A short vertical video with the words and the moving waveform is how a harmony travels.

**What the person sees:**
- **Share, then Share as a video,** on any harmony (at launch, harmonies are private or unlisted;
  sharing a private one first makes it unlisted, and says so).
- **Choose:**
  - **the section** (the chorus by default, up to 60 seconds, or the whole song when it's
    shorter);
  - **the look:** the part colours on dark (the default), or on light;
  - **words** on or off (on by default).
- **While it's made,** a progress bar (about 20 to 40 seconds). Then **Share** (the system share
  sheet with the file, on phones) or **Download**.

**The video:**
- 1080 x 1920, MP4 (H.264 and AAC), under about 15 MB;
- **inside it:**
  - the title and singers at the top, from the share card;
  - each part's waveform moving in its colour;
  - **the words, karaoke style,** from the template's syllables;
  - a 2-second end card: "Made with Stackapella", and the harmony's link.
- **The words double as captions,** so it works with the sound off.

**How it's made, on the server** (one render that looks the same from every browser, and no new
JavaScript library):
- **The audio:** the harmony's published mix, already rendered on the device with Make it sound
  good. The server never remixes it.
- **`RenderShareVideo`** (a job on the `long` queue) runs ffmpeg:
  - the background and title, drawn with GD as the share card is;
  - the waveforms, with `showwaves`, coloured per part from the stored peaks;
  - the karaoke words as **ASS subtitles with `\k` timing,** written from the template's syllables
    and beats;
  - the end card;
  - our font on the server (Bricolage Grotesque TTF, as the share cards use).
- **Projects not made from a template** have no words, so they get the waveforms and the title
  only.
- **Storage:** `private` `tmp/`, for 7 days, downloaded by `temporaryUrl()`. It can be made again
  at any time.
- **Limits:** a few videos an hour per person (a named limiter), because rendering is expensive.
- **Contract:** `videos.create` (harmony, section, look, words), `videos.get` (status, progress,
  URL).

**Tests:**
- the ASS file times each syllable to its beat at the project's tempo;
- a 30-second test harmony renders to a valid MP4 of the right size and length, checked with
  ffprobe;
- the limiter refuses with `rate_limited`;
- a private harmony becomes unlisted only after the person confirms.

---

## 5. Open parts: finish someone's harmony (the shelf when ready, N5)

**Status:** the server side was built in N5 (2026-10-27), behind the `open_parts` flag.

**Why:** a harmony with a part left open is an invitation. It's mudeo's "join a song" for an audio
app, and it makes the gallery somewhere to sing, not just listen.

**What the person sees:**
- **When publishing,** "Leave parts open for others" (for example, the tenor). An open part plays
  its guide in the published mix, or is silent, the owner's choice.
- **On the harmony's page and in Explore** (an **Open parts** shelf): "The tenor is open. Sing it."
- **Sing it:**
  - opens a new project made from the harmony (a remix), with the other parts as takes and the
    open part as the slot to learn and record, in Sing as usual;
  - when published, the new harmony credits everyone and links back.
- **The original owner** is told in the app ("Sam sang the tenor in your Danny Boy"). They can
  **feature a fill** on their harmony's page.

**Rules:**
- **Only the owner opens parts,** and only on a harmony everyone on it agreed to make public.
- A fill is published by the person who sang it, under the gallery's rules: it's private by
  default, needs their consent, and can be reported.
- **Only the original key and tempo,** so the parts line up.

**How:**
- `harmonies.open_parts` (part keys), and `harmonies.parent_id` on fills.
- **The audio:** the other parts' takes are shared from the original (the takes table's reference
  counts, `docs/backend-plan.md` C2). So a published harmony with open parts keeps its stems, which
  the owner agrees to when opening parts.
- **Contract:** `harmonies.open` and `harmonies.fills.list`, plus the remix call from
  collaboration.

## 6. The virtual choir (when ready, song by song, N6)

**Status:** the server side was built in N6 (2026-10-27), behind the `virtual_choir` flag.

**Why:** you're one voice of many, so nobody hears your mistakes, and the song grows as people
join. It's an emotional hook, it's kind to beginners, and no other app can do it.

**What the person sees:**
- **Each official template has a choir,** for example "The Danny Boy choir: 214 voices".
- **After recording a part,** "Join the Danny Boy choir with your alto". The choir page plays the
  current choir mix, and shows the voices per part and the singers (credited by handle, or
  anonymous).
- **When their voice is in,** they're told in the app: "You're one of 58 altos in the Danny Boy
  choir." On the choir page, **Hear my voice louder** brings theirs forward in the mix.

**Rules:**
- **The original key and tempo only,** so every voice lines up.
- **Joining publishes your part** inside the choir (it's never shown on its own). You can leave at
  any time, and the next mix drops you.
- **A quick check before joining,** done on the device and phrased kindly:
  - on pitch above a threshold (Relaxed strictness);
  - no bleed or clipping.

  A take that doesn't pass gets "Almost! Practise phrase 3 and try again". Reports and moderation
  work as in the gallery.
- **18 or over for now?** It follows the account age rule (`docs/backend-plan.md` B), since voices
  are public.

**How (the one place the server mixes audio):**
- **An exception to "the server never remixes parts"** (`docs/architecture.md`). That rule exists
  so that published audio is exactly what the person heard. A choir mix is a new piece of work
  that nobody heard before, so the rule doesn't apply. Logged in `docs/decisions.md`.
- **`BuildChoirMix`** (the `long` queue, at most hourly per choir, when voices changed):
  - ffmpeg levels each take, spreads the voices across the stereo field by part, mixes up to a
    cap per part (proposed 60, the newest and featured first), and adds the hall from Make it
    sound good (the same impulse response, generated in PHP);
  - it writes the AAC to `media` at a new immutable key.
- **Tables:**
  - `choirs` (template version, the current mix, counts);
  - `choir_voices` (the choir, user, part, take, scores, status).
- **Contract:** `choirs.get`, `choirs.join`, `choirs.leave`, `choirs.voices.list`.

## 7. The harmony gym (launch, M10, frontend)

**Why:** hearing harmony is the real skill beginners lack. Two-minute ear games give a reason to
open the app each day, without streaks.

**What the person sees:**
- **"Harmony gym, 2 min"** next to "Warm up, 5 min", and as a step in later paths.
- **The games,** each one round of a few questions, with the same kind feedback as the reports:
  - **Hold your note:** a chord plays, and you sing and hold the note shown while the others move,
    with the live pitch line;
  - **Find the third:** a note plays; sing the third above, then below;
  - **Which voice moved?** Two chords; tap the part that changed (no mic needed);
  - **Match the chord:** the alto line is missing; sing the note that completes the chord.
- **Free:** one game a day. **Plus:** all of them, at every level (the warm-ups' rule).

**How:**
- Games are data like exercises (`web/exercises/`, with `type: "game"`), built on:
  - the synth;
  - `renderBacking` (feature 3);
  - `pitchtrack.js` and `scoring.js`;
  - the warm-up runner (`docs/vocal-coaching-plan.md`).
- **Checked by the voice teacher** with the warm-ups.
- **Results** go in the warm-ups log, so they show on Your voice.

## 8. Song requests (when ready, N7)

**Status:** the server side was built in N7 (2026-10-27), behind the `song_requests` flag.

**Why:** it tells Hillel what to arrange next, and people feel heard. It runs on our own records,
with no analytics.

**What the person sees:**
- **"Request a song"** at the end of the library, and when a search finds nothing.
- **The request:**
  - a title, who wrote it (if known), and why ("our choir sings it at Christmas");
  - matching requests are suggested first, so people vote instead of duplicating.
- **The requests page:** the most wanted songs, each with its votes and a status:
  - Requested;
  - Checking it's free to use;
  - Being arranged;
  - **Ready to learn** (a link to the template);
  - Can't do this one yet (still under copyright), with a kind line.
- **When a request is released,** everyone who asked or voted is told in the app. No email (the
  rule).

**How:**
- **Tables:**
  - `song_requests` (title, writer, the status, the template once released, merged into);
  - `song_request_votes` (one per account).
- **Admin:**
  - merge duplicates;
  - set the status;
  - link the released template.
- **Reports and limits** as for comments.
- **Contract:** `requests.create`, `requests.list`, `requests.vote`, `admin.requests.*`.

---

## Added from a product review (2026-10-26)

A review of the app against its goal (beginners learning harmony) found the risks are less about
missing features than about three gaps:
- **hearing intervals arrives late;**
- **one hard phrase forces a whole retake;**
- **after the first finished song, little points to the second.**

These are the thin, launch-sized answers. Each was adopted because it uses something already
planned.

| # | Feature | When | Builds on |
|---|---|---|---|
| 9 | Intervals in the free course | M3 (units 5 and 6) | The gym's games, the chords |
| 10 | Slow practice | M3 | The synth's `speed` |
| 11 | Re-sing a phrase | M5 | Phrases, the engine's exact recording |
| 12 | Practice tracks, and sharing just my part | M4 and M6 | `voiceLevels`, export, share links |
| 13 | What's next, after a finished song | M5, then L | The range, difficulty, our own counts |
| 14 | Note names your way | M3 | The Note lane |
| 15 | What's using space | M4 | The storage meter, the bin |
| 16 | A kind word when the real voice can't follow your key | M3 | The real voice / synth rule |
| 17 | Trim the starts and ends (added by Hillel) | M4 | The engine's offsets, the take's peaks |

### 9. Intervals in the free course (M3)
**The problem:** "I can match the guide, but my part sounds wrong with the others." Monophonic
scoring (my pitch against the note) can't teach the relative pitch harmony needs.

**What changes:** two of the harmony gym's games become **lessons in units 5 and 6** of the free
course, ahead of the gym itself:
- **Find the third:** a note plays; sing the third above, then below. Then sing it while that
  note keeps sounding;
- **Hold your note:** a chord (Chords underneath) or the other part plays, and you hold your note
  as it moves around you, with the live line.

They're scored by the same `scoring.js` against the expected interval, so there's no new
measuring. The rest of the gym follows in M10.

**Later, with the gym:** feedback on **relative tuning** in the report ("a little flat against
the alto"). It measures your pitch against the other parts actually sounding: the synth's exact
notes, or the real voices' pitch tracks, worked out once when a guide is published. Absolute
scoring misses a lot of what makes a blend sound right.

### 10. Slow practice (M3)
- **85% and 70%,** beside the key control, without changing pitch.
- **The synth renders at any speed** (`renderGuide` already takes `speed`).
- **Real voices stay at 100%:** slowing a part switches to the synth for it, with the kind note
  (feature 16).
- **Recording is always at 100%,** so takes line up.
- **Lessons can slow a phrase by themselves** for beginners ("Let's try that slower").

It moves from "later" to the launch.

### 11. Re-sing a phrase (M5)
**The problem:** one hard phrase forcing a whole retake is a retention killer.

**What the person sees:**
- on Just recorded and on the phrase path: **Re-sing this phrase**;
- the phrase loops with the others, with a count-in;
- the person records just that phrase;
- **Keep it** or Try again.

**How:**
- the recording is sample-exact, so the new phrase replaces exactly its frames in the take;
- there's a 20 ms crossfade at each end, placed in the rest around the phrase where there is one;
- **the result is a new take** (takes are immutable), and the old one goes to the bin, so Undo
  works;
- each phrase's scores update.

It replaces "re-sing one phrase, later", in a simple form. Free-form punch-in at any point stays
later.

**Built (2026-10-27), with Hillel's join rules** (js/patch.js, tests/patch.test.js, tests/flows/resing.py):
- **Where the joins go:** a rest around the phrase if there is one; otherwise the quietest point
  between two notes within a beat of the phrase's edge, found from the take's own audio (the
  arrangements are written legato, so a singer's breath only shows in the audio). Never mid-note:
  when a note is held right across the edge, the join moves out to that note's end (or start),
  and the singer is told "Sing on to 'lost' so it joins smoothly" before recording.
- **Matching loudness:** the new phrase's singing is gain-matched to the take's singing around it,
  within 6 dB.
- **When it still won't match:** the room's noise floor (more than 10 dB apart) or the tone (the
  balance of highs, more than 6 dB apart) is compared; when it differs, the card says so kindly,
  with Try again first and "Record the whole part again" offered.
- **The splice:** 20 ms equal-power crossfades centred on each join, at exact sample positions;
  tested to leave the take untouched outside the joins, the new phrase exact inside, no step at
  a join beyond the signals' own, and no energy bump across a crossfade.
- **Where:** Just recorded's "Re-sing a phrase" (the phrase path gets it with the Sing redesign).
  The phrase is recorded with the others and a count-in and stops by itself after the join; it's
  heard in place before Keep it, nothing is saved until then, and Undo puts the old take back
  (the bin keeps old takes once M4's sync exists).

### 12. Practice tracks, and sharing just my part (M4 and M6)
These serve choir members and section leaders from launch, without choir groups:
- **Export a practice track** (M4, free, WAV):
  - **"Everything but my part"** (for singing along);
  - **"My part louder"** (the part-predominant track choirs use);
  - with or without a click and a count-in.

  It's the same render as Export, with `voiceLevels` set for it.
- **Share just my part** (M6): Share offers **the whole harmony** or **just my part**, as an
  unlisted link or file, for a director or teacher to hear. It's the same share flow, rendered on
  the device. "Send to my teacher" (with the review workspace) still comes with the marketplace.

### 13. What's next, after a finished song (M5, then L)
**The vision's measure is the second song,** so the celebration of a finished harmony ends with a
**What's next** card:
- **For you:**
  - a song whose parts fit your range (from the course's range or the range check);
  - one step harder than the one you finished;
  - the song of the month;
  - "**try a different part** of this song" (the bass after the alto).
- **From L, from our own records** (shown once a song has enough finishers to mean something): "**1,240 people have finished this harmony**", and "**people
  who finished this also learned…**". These are aggregate counts from the server, computed nightly,
  with no tracking of anyone.
- **The course map** shows the same card when there's no lesson waiting.

### 14. Note names your way (M3)
In the Note lane, the Staff and the course:
- **Note labels:** letters (D4), solfège (movable do: do, re, mi), numbers (1 to 7), or off;
- **a small keyboard** down the lane's side, with the current note lit;
- remembered per person.

The Stage stays the words-only view.

### 15. What's using space (M4)
- **A "Storage" page** from the meter:
  - each project's size, biggest first;
  - the bin (with **Empty the bin**);
  - old takes not in use.
- **Clear actions:** **Download and delete** (free), Delete, and "Keep only the current takes".
- **Before the limit, not at it:** the meter turns amber at 80% with "See what's using space".

### 16. A kind word when the real voice can't follow your key (M3)
**The problem:** real voices are recorded in one key. When the range check moves a singer to
another key, the part switches to the synth, which can feel like a downgrade.

**What the person sees:**
- **when the key changes,** once per song: "A real singer recorded this in G. **You're singing in
  A, which suits your voice better,** so you'll hear our practice voice. It follows any key and
  speed";
- **"Sing in G with the real voice"** is one tap away, with a note of whether G fits their range;
- **the part shows** "Practice voice (in your key)", never "Synth", and the real singer's credit
  stays on the song.

### 17. Trim the starts and ends (M4)
Hillel's call (2026-10-26). A take often starts with a breath, a cough or a few seconds of room
noise, and ends with the singer reaching for the stop button. **Basic trimming lets people line up
where each part starts and ends,** without anything like a full editor ("Not a DAW").

**What the person sees** (on a part's menu, and on Just recorded):
- **Trim:** the take's waveform, zoomed, with a handle at each end:
  - drag the start or end in (desktop);
  - on a phone, a sheet with the two handles, plus nudge buttons in small steps;
  - **Play from the start** and **Play to the end**, to hear each edge with the other parts;
  - **Snap to the beat** when the project has a tempo (on by default).
- **Trim the silence:** one tap moves both edges to where the singing starts and stops, with a
  little room kept (about 100 ms). It works from the take's loudness outline.
- **Line up the ends:** for the whole project:
  - **Same end:** every part ends where the song ends (the template's last note, or the longest
    part);
  - **Same start:** every part starts on the first beat.

  The singer chooses either, and Undo puts everything back.
- **Reset** returns a part to its full take.

**Rules:**
- **Trimming never changes the recording.** It's two numbers on the part, `trimStartFrames` and
  `trimEndFrames`, beside `offsetFrames`. The take stays whole and dry, and you can always widen it
  again.
- **Trimming the start doesn't move the singing in time:** the part keeps its place against the
  others. Moving a part in time is what the nudge is for (feature 11's "Sound off? Nudge timing").
- **Every edge gets a 10 ms fade,** so a cut never clicks.
- **Everything that plays honours the trims:** playback, export, practice tracks, share links and
  videos, and the harmony published to a share link. Scoring ignores the trimmed-off parts.
- **Frames, scaled like offsets:** trims are frames at the take's sample rate, scaled to the
  AudioContext's rate (the architecture's invariants).
- **Undo** works for every trim, and trims sync as part settings (versioned, so a conflict asks).

**How:**
- The engine already places each part by offset. It gains a start and end within the buffer
  (`AudioBufferSourceNode.start(when, offset, duration)` on the audio clock), plus the edge fades.
- `engine.render` uses the same values, so exports match playback.
- Trim the silence uses the take's `peaks` (already stored), with a loudness threshold relative to
  the take's own level.

**Tests:**
- a trimmed part starts and ends at the exact frames, in playback and in a render (loopback);
- trimming the start doesn't shift the rest of the part in time;
- the fades remove the click (no sample jump above a threshold at the edges);
- Trim the silence finds a synthesized take's first and last sung notes within 20 ms;
- Line up the ends gives every part the same end frame;
- Undo restores the previous trims.

## Pricing

All of them are free (the practice tracks and "just my part" included), because they're the core
of learning and growing. The harmony gym follows the
warm-ups' rule: one a day free, everything on Plus. Make it sound good and Share as a video are
free on purpose, because shared harmonies bring people in.

## Review (misses, UX, code fit)

**Missed, now covered:**
- **Polish can't touch what's measured:** the timing check, bleed detection and scores all use the
  dry signal.
- **Takes stay dry,** so a new room or a better polish later improves old harmonies too.
- **Chords never go into a take,** and are left out of exports unless chosen.
- **Templates without chords** hide the toggle until the arranger can work chords out.
- **A video of a private harmony** first makes it unlisted, with the person's OK.
- **Video rendering is expensive,** so it's rate-limited and kept for 7 days only.
- **Open parts need stems,** so the owner agrees to share them when opening parts.
- **The choir mix is the only server mix:** an exception logged with its reason. Joining needs a
  quick check, leaving takes effect at the next mix, and the age rule applies.
- **Requests for songs under copyright** get a kind "can't do this one yet", not silence.
- **Path progress** works offline and syncs, and the path never locks the library.

**UX:**
- **One switch and one choice** for the sound.
- **The path shows one next step,** with why it's next.
- **Chords are off by default,** except where the path turns them on.
- **Video choices** are the section, the look and the words, all with sensible defaults.
- **The choir celebrates the person** ("one of 58 altos") and lets them hear themselves.
- **Every check is phrased kindly,** with one thing to try.

**Code fit:**
- **Reuses:**
  - the engine's `render` and `OfflineAudioContext`;
  - `synth.js` and `guides.js` (the backing voice is cached like guides);
  - `voiceLevels`;
  - `pitchtrack.js` and `scoring.js`;
  - the warm-up runner;
  - the template loader and schema;
  - the share card (GD);
  - ffmpeg on the `long` queue;
  - the takes' reference counts;
  - the gallery's consent, reports and notifications.
- **No new dependencies:**
  - reverb impulse responses are generated;
  - the video uses ffmpeg (already in the `Aptfile`);
  - the ASS subtitles are written by our own code.

## Where each goes in the roadmap

| Feature | Milestone | Frontend track | Backend track |
|---|---|---|---|
| 2. Learning path | M3 | The course's units 5 to 7 (`docs/vocal-coaching-plan.md`) | (M4: `account.learning` sync) |
| 3. Chords | M3 | `renderBacking`, the toggle, `voiceLevels` | The `chords` field in the template schema |
| 1. Make it sound good | M4 | `polish.js`, the switch, Hear it polished | None |
| 4. Share as a video | M6 | The share flow, the choices, progress, the share sheet | `RenderShareVideo`, `videos.*` |
| 5. Open parts | M7 (inviting to a part), N5 (the shelf) | The flows | `open_parts`, fills |
| 6. Virtual choir | N6, song by song | The join check, the choir page | `BuildChoirMix`, `choirs.*` |
| 7. Harmony gym | M10 | The games | None (the warm-ups log syncs) |
| 8. Song requests | N7 | The pages | `song_requests`, admin |

**For the musician:** the chords for the 8 templates and the first path, checked with the notes.
**For the voice teacher:** the gym games, with the warm-ups.

## Verification
- **`web/tool/test.sh`:**
  - polish loudness, peaks, levelling, and determinism;
  - `renderBacking` puts the right chord tones on the right beats, and transposes;
  - the backing voice is muted in exports by default;
  - the path loader rejects a broken path;
  - a step is ticked by a recorded part or a good run;
  - the gym games score a synthesized voice.
- **PHPUnit:**
  - the video job writes valid ASS and calls ffmpeg as expected (faked);
  - choir joins need passing scores, and leaving drops the voice from the next mix;
  - open parts need the owner;
  - one vote per account;
  - the limiters.
- **Flow scripts:**
  - the first run leads into the path, and step 2 ticks after recording;
  - Chords on in Sing;
  - export with and without Make it sound good;
  - share a harmony as a video and download it;
  - (when each opens) fill an open part; join a choir; request a song and vote.
- **By Hillel:**
  - hear the four rooms on headphones and a phone speaker;
  - post a test video to TikTok and Reels and check how it looks there;
  - walk the first path as a beginner would.

---

[Back: The template creator](template-creator-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: The coaching marketplace](coaching-marketplace-plan.md)
