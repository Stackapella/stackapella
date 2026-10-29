# Your voice, live: the pitch display in Sing

A design study for Stackapella's live pitch line: what other apps and games do, four options
mocked up on Amazing Grace's alto line, a recommendation, and a smoothing spec that any of the
options would use.

**Files in this folder**

| File | What it is |
|---|---|
| `board.png` | All four options side by side, with today's screens and the listening screens |
| `option-A-phones.png` to `option-D-phones.png` | Each option on a phone: on pitch, slightly flat and breathing (Stage dark), plus slightly flat in Warm light |
| `shots/` | Every render: `{A-D}-{phone,desktop}-{stage,warm}-{on,flat,breath}.png`, plus `{A,C}-{find,range}-{stage,warm}.png` |
| `smoothing.png` | A simulation: what the tracker hears, the smoother shipped today, and the proposed one |
| `mock.html`, `data.js`, `displays.js` | The mockups (open `mock.html?opt=A&theme=stage&moment=on&device=phone`) |
| `smoothing.js`, `smoothing.html` | The simulation, with a reference version of the proposed smoother |
| `board.html`, `render.sh`, `jobs.txt` | How the board and the PNGs were made (headless Chrome) |

---

## 1. The short version

- **Recommendation: Option A, the Ribbon lane, with Option D as its Stage form.** It is one
  component in two sizes:
  - the Notes view gets the full lane;
  - the Stage keeps its big words, with a slim strip of the same lane above them;
  - the strip replaces the horizontal tuning meter.
- **What changes in A:**
  - Each note is a bar exactly as tall as the tolerance (35 cents either side).
  - The bar fills solid where you sang it in tune, and keeps a tick. That is shape, not colour.
  - Your voice is a calm line with your vibrato as a soft band round it.
  - Flat or sharp gets a small arrow, and "A little higher" in words.
  - A breath ends the line with a fade and a hollow ring, never a drop.
- **Borrow from the others:**
  - C's close-up, with its "Higher" pill, for Wait for me, where you sing one note at a time.
  - B's phrase page for the chart in How it went, after the take, where seeing a whole phrase's
    shape is the point.
- **Find your note and the range check** become a small ribbon box: higher is up, as on the lane,
  instead of a sideways bar. The hold ring stays.
- **The "it suddenly drops down" bug:**
  - The likeliest cause is in `web/js/livevoice.js`. It folds each reading to the note being sung
    now, and leaves readings outside a note unfolded.
  - So anyone singing in another octave than written drops (or jumps) by an octave at every rest,
    every late release and every early entry. This includes a man singing a treble part.
  - The smoother shipped today (`web/js/pitch-display.js`, commit 1f7e926) holds such a jump for 4
    readings, then shows it.
  - The spec below fixes it by learning the singer's octave once and applying it everywhere.
  - It also brings vibrato back, which today's smoothing mostly erases.

---

## 2. What's wrong today

From the code (`web/js/livevoice.js`, `web/js/pitch-display.js`, `web/js/views/sing.js`
`hearVoice` and `stageTuning`, `web/js/components/sing/lane.js`) and today's screenshots
(`today/` in this folder).

| # | Problem | Why it happens | Seen as |
|---|---|---|---|
| 1 | **Octave drops at rests and note edges** | `folded()` folds a reading to within 6 semitones of the *note now*. A reading with no note (a rest, a release past the note's end, an early entry) keeps its raw octave. For a singer an octave from the written part that is a 12-semitone jump. The new smoother waits 4 readings (about 0.13 s) and then draws it. | The line plunges to the lane's bottom edge, then leaps back (`smoothing.png`, panel 2) |
| 2 | **Folding follows the target, so a late or early reading near a leap of more than 6 semitones lands an octave away** | The fold window moves when the note changes | A spike at big leaps (an octave leap in Happy Birthday, say) |
| 3 | **Vibrato is mostly erased** | Median of 5 at about 30 readings a second covers 0.17 s, about one vibrato cycle, and then an ease of 0.06 s (0.10 s holding). A 6 Hz vibrato keeps roughly 40% of its swing from the ease alone, less after the median. | A flat, slightly laggy line, then a lurch when the median gives way |
| 4 | **Stepped motion** | Readings are taken about every 33 ms (`hearVoice` throttles on `performance.now()`), and the line only gains a point per reading. A 60 or 120 Hz screen shows a staircase head. | A head that stutters on a phone |
| 5 | **A strict gate breaks breathy singing** | Sing takes a reading only at a clarity of 0.85 (Find your note uses 0.6), with no hysteresis | Soft and breathy voices flicker in and out |
| 6 | **The Stage meter can swing to an end** | It takes the last drawn point within 0.2 s and compares it to the note now. Across a note change, that point can belong to the previous note. (Worth confirming on a device; today's recording screenshot shows the dot pegged at "Higher".) | The dot jumps to "Higher" or "Lower" at note changes |
| 7 | **Colour carries "on the note" on the lane** | A lit note gains a glow and a thicker cream outline | Hard to see in Warm light; no change in shape |

Small doc drift: `docs/ux-spec.md` says the hold ease is 0.15 s, the code says 0.1 s.

---

## 3. Research: how others show live pitch

Sources were fetched pages, App Store screenshots (about 80, viewed one by one), store reviews
(about 6,000 recent US reviews filtered by keyword), the UltraStar Deluxe source, and the Harmonix
patent. "(seen)" means described from a screenshot; "(inferred)" means a reading, not a fact.
Exact tolerances are almost never published.

### 3.1 Product by product

| Product | Layout | Your voice drawn as | "On pitch" shown by | Notes, praise and complaints |
|---|---|---|---|---|
| **Yousician Singing** | Dark lane, grey pills named by note (D, A, F#), big karaoke lyrics; fixed playhead (inferred) | A white dot, no trail (seen) | Green or red on a minimap | Colour only, on or off; no direction or cents (Singing Carrots' comparison). "Not as forgiving as Simply Sing" |
| **Simply Sing** | Purple lane, syllable under each pill, fixed playhead about a third in, two-line karaoke below (seen) | A dot on the playhead | Pill turns white or teal; **"Too high" and "Too low" words** above and below; a check badge | Words for direction are good. A review complains bad notes are marked but good ones aren't. Picks a key from a range test |
| **Vanido** | No exercise screen found | | | Common Sense Media: "hard to tell how large the intervals are", "feel more like guesswork" |
| **Smule** | Yellow pills on a dark lane, fixed white playhead near the left, lyrics below (seen) | A white triangle | Scored at the end by notes hit | Pitch guides are machine-made from uploads and missing on many songs |
| **StarMaker** | No pitch lane in its store shots | | | Grades called "harsh" |
| **Sing Sharp** | Dark piano roll with named rows and grey bars; a separate round "O'Meter" tuner (seen) | A short red and white trace | | "Laggy"; reads C as B; ranges too high or low |
| **Erol Singer's Studio** | Light piano roll, staff strip below, keyboard at the bottom (seen) | **A continuous white line with an orange glow; vibrato and scoops visible** | Bar turns teal or blue; per-note percentages afterwards | Shows *how* you sang, not just hit or miss; no guide to what a good line looks like |
| **Singing Carrots** | Bars fall onto a keyboard; a web monitor with a cents bar (±5 and ±12 cent marks) | A red trace, straight when steady, wavy with vibrato | Results: syllables coloured **with up and down arrows** | Their rule of thumb: 5 cents is barely noticeable, 12 cents is heard by most |
| **SingTrue** | A vertical tube meter for one note, no timeline (seen) | The tube's fill | | Octave of the reference not always clear |
| **Swiftscales** | No pitch meter, on purpose ("you won't have a pitch meter on stage") | | | Record and listen instead |
| **Vocal Pitch Monitor** | Scrolling graph, C1 to B7, named rows, no targets (seen) | A raw, jagged yellow line with gaps | | Settings: volume threshold 0.50, zooms, semitone lines. "Constant breaks in the lines" on the vowel E |
| **Nail the Pitch** | Piano roll with a ±50 cent meter | A thick blob on steady notes, a thin line on transitions | | "Registers an E4 as an E5", "jumping up and down by over an octave" |
| **Riyaz** | Dark lane, yellow pills, playhead (seen) | A white curve that stays as history, yellow dot at the head | Red and green progress bar | |
| **SingStar** | Grey bars like a staff, one lyric line per page (seen) | **Your notes drawn as bars in your colour over the targets** | Fill | Any octave counts. Easy, Medium and Hard narrow the leeway. Early reviews: display delay |
| **Let's Sing** | Hollow outlined tubes with the syllable beside them, playhead at the centre (seen) | A glowing dot with particles | Tubes fill; gold notes have a star | Lenient on easy settings; octave allowed. Complaints: the mic hears the whole room; PS5 delay |
| **Rock Band vocals** | Tubes scroll to a now bar; harmony parts coloured per part | **An arrow that points the way to correct** | Glow, "Awesome" and "Weak" words | Octave-free scoring; a ghost arrow an octave away. The patent says the tube's thickness can show the window. Vibrato deliberately not charted; range above 2 to 2.5 octaves is "hard to read" |
| **UltraStar Deluxe** | One lyric line per page, 10 faint lines | Sung notes as bars at the detected semitone | "perfect!" and "bad!" | Source: a hit is within 2, 1 or 0 semitones by level after snapping; sung pitch folded to within 6 semitones of the target; no smoothing |
| **Melodyne** | Piano roll of "blobs", thickness by loudness | A thin pitch curve through each blob | Cents in the inspector | Splits fast "modulation" (vibrato) from slow "drift": the right model for a display that shows vibrato and judges the centre |
| **Tuners** (Cleartune, TonalEnergy, Peterson) | Needle, face, strobe | Needle or strobe | TonalEnergy's face **changes shape** and grows as you hold | Damping in three steps (Cleartune 0, I, II; TonalEnergy slow, normal, fast). Complaints both ways: "needle jumps around erratically" and "latency so high". A user asks for a colour-blind mode |

### 3.2 Patterns

- **Layout:**
  - The dominant pattern is a piano roll with a **fixed playhead**, notes scrolling in from the
    right, the voice drawn at the line. Simply Sing, Smule, Let's Sing, Rock Band, Yousician, Sing
    Sharp and Riyaz all do this.
  - The playhead sits left of centre so you can read ahead.
  - The older games (SingStar, UltraStar) page a line at a time.
  - Staff is an extra strip at most (Erol). Single-note meters (SingTrue, tuners) have no melody.
- **Targets:**
  - Rounded pills or tubes, as long as the note.
  - The syllable is on or under the pill, and the full line again at the bottom.
  - Some products name the notes.
- **The live voice comes in three styles:**
  - a head only (a dot or arrow);
  - a trail that stays (Erol, Riyaz, Melodyne, the monitors);
  - a semitone-snapped bar (SingStar, UltraStar).
  - Trails teach the most: you see scoops, vibrato and drift. Raw trails look broken and jittery.
- **On pitch:**
  - Fill, glow, particles and colour are common.
  - Shape and words are rarer: Rock Band's arrow, Simply Sing's "Too high", TonalEnergy's face,
    Singing Carrots' arrows.
  - Several products rely on colour alone.
- **Tolerance:**
  - Hidden in every game, in three levels.
  - Rock Band's patent suggests drawing it as the tube's thickness.
  - Only UltraStar's numbers are public.
- **Octave:** games forgive it (any octave, or fold to ±6). Learning apps move the key instead.
  Octave misreads are a top complaint for monitors and tuners.
- **Smoothing:**
  - People complain about both jitter and lag.
  - Tools that offer damping give three steps.
  - UltraStar Play found a median of about 3 to 10 frames best, at the cost of about 3 frames of lag.

### 3.3 What we take from it

1. Keep the fixed now line and scrolling notes (the mainstream, and what our Note lane already is).
2. Draw the tolerance as the bar's height (Rock Band's patent idea), so "in" is visible.
3. Show a hit by **filling** the target (SingStar, LIPS, Let's Sing), which is shape as well as colour.
4. Show direction with an arrow and a word (Rock Band, Simply Sing), never colour alone.
5. Keep a trail, as Erol and Melodyne do, but smoothed. Show vibrato as a band and judge the centre
   (Melodyne's modulation and drift split).
6. Forgive the octave, and do it consistently: decide the singer's octave once, not per note.

### 3.4 Sources and images

Pages:
- Yousician: https://americansongwriter.com/yousician-singing-review/ ,
  https://singingcarrots.com/blog/yousician-vs-singing-carrots/
- Simply Sing: https://americansongwriter.com/simply-sing-review/ ,
  https://apps.apple.com/us/app/simply-sing-my-singing-app/id1592900217
- Vanido: https://www.commonsensemedia.org/app-reviews/vanido-your-personal-singing-coach ,
  https://www.technologyreview.com/2017/09/28/241982/can-an-app-really-teach-you-to-sing/
- Smule: https://sing.salon/forums/topic/1658-smule-pitch-bar/
- Sing Sharp: https://apps.apple.com/us/app/sing-sharp-singing-lessons/id772052329
- Erol Singer's Studio: https://apps.apple.com/us/app/erol-singers-studio/id502780186
- Singing Carrots: https://singingcarrots.com/docs/singing-carrots-pitch-monitor
- Vocal Pitch Monitor: https://apps.apple.com/app/id842218231
- SingStar: https://en.wikipedia.org/wiki/SingStar
- Let's Sing: https://www.thumbculture.co.uk/lets-sing-2021-review-does-it-hit-the-high-notes ,
  https://press.plaion.com/Solo-duet-or-singing-face-off
- Rock Band: https://patents.google.com/patent/US8449360B2 ,
  http://docs.c3universe.com/rbndocs/index.php?title=Vocal_Authoring ,
  https://www.musical-u.com/learn/rock-band-ii-the-ultimate-ear-training-hero/
- UltraStar: https://raw.githubusercontent.com/UltraStar-Deluxe/USDX/master/src/base/UNote.pas ,
  https://raw.githubusercontent.com/UltraStar-Deluxe/USDX/master/src/base/UDraw.pas ,
  https://raw.githubusercontent.com/UltraStar-Deluxe/USDX/master/src/base/URecord.pas ,
  https://github.com/ultrastar-deluxe/play/issues/12
- Melodyne: https://www.soundonsound.com/reviews/celemony-melodyne-5 ,
  https://helpcenter.celemony.com/M5/doc/melodyneStudio5/en/M5tour_ToolModulationDrift_2
- TonalEnergy: https://www.tonalenergy.com/tet-user-guide-ios
- Trackers: https://raw.githubusercontent.com/librosa/librosa/main/librosa/core/pitch.py (pYIN),
  https://github.com/marl/crepe , https://raw.githubusercontent.com/aubio/aubio/master/src/pitch/pitch.c ,
  https://github.com/ianprime0509/pitchy

Images (store screenshots and press shots showing the pitch display):
- Yousician: https://americansongwriter.com/wp-content/uploads/sites/7/2025/05/IMG_5248.png?resize=1024,473
- Simply Sing: https://americansongwriter.com/wp-content/uploads/sites/7/2025/04/4_faa2f4.png?resize=1024,683 ,
  https://is1-ssl.mzstatic.com/image/thumb/PurpleSource211/v4/d3/18/5a/d3185ac5-d85f-6939-c048-38ade193b110/7.png/320x480bb.jpg
- Smule: https://sing.salon/uploads/monthly_2020_08/IMG_4760.jpg.c13c53fb6932f882f0f0f5ab006c66dd.jpg
- Sing Sharp: https://is1-ssl.mzstatic.com/image/thumb/PurpleSource211/v4/bc/5c/72/bc5c7244-cbe8-6a76-90bc-5af554d1ca28/6847fd14-1f98-4fe7-8dad-00b077ac6fc5_AppStore_7_2_Set01_6_U00277_U0022_p04.png/320x480bb.jpg
- Erol Singer's Studio (vibrato in the line): https://is1-ssl.mzstatic.com/image/thumb/PurpleSource115/v4/23/cd/80/23cd8077-2a46-b3ec-6f49-266e17f46f06/088db714-6e14-41a5-8ef3-267cef0f12c8_Vibrato.png/392x696bb.png
- Singing Carrots: https://is1-ssl.mzstatic.com/image/thumb/PurpleSource221/v4/9a/83/c8/9a83c857-6f2c-0298-4647-bd6ce46e5b1c/SingingCarrots_01_live_1284x2778__U00281_U0029.png/320x480bb.jpg ,
  https://is1-ssl.mzstatic.com/image/thumb/PurpleSource221/v4/7d/dc/6b/7ddc6b2a-853d-fab9-aa22-ac3f026ce2c2/SingingCarrots_04_feedback_1284x2778__U00281_U0029.png/320x480bb.jpg
- SingTrue: https://is1-ssl.mzstatic.com/image/thumb/Purple71/v4/8f/f7/d6/8ff7d6c0-1cea-3363-1b7c-659ca0a79d2e/mzl.honvinpk.png/392x696bb.png
- Vocal Pitch Monitor: https://is1-ssl.mzstatic.com/image/thumb/Purple124/v4/a7/ab/e7/a7abe772-db11-5832-1c27-0dce48082569/pr_source.png/392x696bb.png
- Nail the Pitch: https://is1-ssl.mzstatic.com/image/thumb/PurpleSource211/v4/4a/50/1d/4a501dbc-01a5-bcaa-cca4-7bbce960f361/EN-2.png/320x480bb.jpg
- Riyaz: https://is1-ssl.mzstatic.com/image/thumb/PurpleSource221/v4/2a/53/a8/2a53a852-674c-4184-ea15-6ea7ee88c707/8cf2e889-79db-42e6-ad7d-741d2722aad3_2.png/392x696bb.png
- SingStar: https://upload.wikimedia.org/wikipedia/en/f/f1/SingStar_screenshot.JPG
- Let's Sing: https://www.thumbculture.co.uk/wp-content/uploads/2020/11/Billie-1-1024x576.jpg
- Rock Band: https://keepitfishy.com/customs/docsimages/spoonman-rb3.png ,
  https://patentimages.storage.googleapis.com/bb/64/dd/2715bc5bf48d7e/US08449360-20130528-D00001.png
- UltraStar: https://upload.wikimedia.org/wikipedia/commons/b/b4/Ultrastar_082_pre-alpha_01.jpg
- Melodyne: https://dt7v1i9vyp3mf.cloudfront.net/styles/news_large/s3/imagelibrary/C/CelmonyMelodyne5_02-Aey8p9DZegWiho.pWgbkin6kJxre0QtJ.jpg
- TonalEnergy: https://images.tonalenergy.com/ios/000-screenshots/targetTuner.png ,
  https://images.tonalenergy.com/ios/000-screenshots/PitchTracker.jpg
- Peterson iStroboSoft: https://is1-ssl.mzstatic.com/image/thumb/Purple116/v4/e7/d9/1f/e7d91ff7-4bb4-98c3-72c4-852af089decc/mzl.cewdzpfv.png/392x696bb.png

---

## 4. The options

All four are drawn from the same data:
- Amazing Grace's alto, from the template, G major, 3/4, 80 BPM.
- The same sung voice: a scoop at each entry after a breath, legato glides, vibrato growing on
  long notes, and a breath after "sound".
- The same smoothing (section 6).

The three moments:
- **On pitch:** "grace", G4.
- **Slightly flat:** "sweet", E4, the centre about 40 cents under.
- **Breathing:** after "sound", before "that" on B3.

The constraints, kept in every option:
- **Your part's colour**, and "You" in it.
- **Theme tokens only:** Stage dark and Warm light. The voice line is `cream`, the highest contrast
  in both.
- **Figtree.**
- **Never colour alone:** fills, ticks, arrows, words, line weight and a hollow ring.
- **No red and no "wrong".**
- **Reduced motion:** no glow pulses or particles. The data still draws.

### Option A: Ribbon lane (recommended, with D)

The Note lane, refined. Notes scroll toward a fixed now line about a quarter in.

What's on screen:
- **Note bars are exactly as tall as the tolerance** (35 cents either side by default). Before you
  sing them they are tinted and outlined in your colour.
- **The bar fills solid where you sang it in tune**, from the left, as you sing. A lit note keeps
  a tick. The note now has a cream outline.
- **Words and note names sit just above each bar**, so your line never covers them.
- **The voice:**
  - a cream line (the vibrato centre), with your vibrato as a soft band in your colour round it;
  - at the now line, a head with a ring in your colour when you're in;
  - when you're out, a small arrow badge points the way, a dotted lead runs to the bar's middle,
    and the now card says "A little higher".
- **A breath:** the line's last stretch fades, the head becomes a hollow ring where you stopped,
  and a dashed dot marks where the next note starts. The now card says "Breathe in. You start on
  B3 on 'that', then D4".
- **Desktop:** the same lane, 12 beats wide, under the now card, with the parts rail and the
  bottom bar.

Why:
- It is what people already know from games and the best apps, and it is what our lane already is.
- The changes fix the real gaps: the tolerance becomes visible, a hit becomes a shape, the
  direction gets a word, and vibrato is shown without jitter.

Pros:
- The most familiar layout.
- Reads ahead well.
- Shows scoops, vibrato and drift: it teaches, as Erol does.
- Every state has a non-colour cue.
- Works on desktop as is.

Cons:
- A phone lane carries a lot: the keyboard, the other parts, labels and the line.
- Mitigations: the other parts can already be hidden, and labels drop to the note name when space
  is short.

### Option B: Phrase page

One phrase per page, like a music stand or SingStar.

What's on screen:
- A cursor sweeps left to right, and **your line stays on the page for the whole phrase**.
- Notes are slim bands (the tolerance) with a centre line. The syllables sit under them in a
  karaoke row.
- The line is **thick where in tune and thin where not**, so it never relies on colour. A sung note
  fills and gets a tick.
- A breath mark (a comma, as in printed music) shows where the phrase breathes. "Phrase 1 of 4"
  and the next line sit above.

Why: nothing moves but the cursor, and when the phrase ends you see its whole shape, a built-in
"how did that go".

Pros:
- Calm, and kind to reduced motion.
- Strong for reflection.
- Line weight is an accessible signal.

Cons:
- Look-ahead shrinks toward the page's end, and the page turns at the moment you breathe.
- On a phone, a phrase gets about 30 px a beat, so short notes become blobs and words crowd ("how
  sweet").
- Your eye chases the cursor, where the lane brings the music to you.

**Best use:** the phrase chart in How it went (See details), not the live view.

### Option C: Focus window

A tuner-style close-up of the note now, about five semitones tall.

What's on screen:
- The note's band is large: 20 cents is a visible gap.
- **A puck with a hold ring** (the same as Find your note's), a short trail, and **"Higher" or
  "Lower" in a pill** with an arrow.
- The next note appears at the right, or as an arrow at the edge ("next C4") when it's out of view.
- The window glides to each new note.
- On the phone it sits under the Stage words.

Why: one thing to look at, at the moment it matters. This is the tuner pattern (TonalEnergy,
SingTrue) plus Rock Band's arrow.

Pros:
- The clearest "where am I right now".
- The most precise.
- Shares its look with Find your note.

Cons:
- No melody shape, so you can't see the next jump coming.
- The window moving each note can feel like the floor moving.
- At this zoom, honest vibrato looks like wobble to a beginner.

**Best use:** Wait for me (the course's Try steps), where the music waits on one note at a time.

### Option D: Strip over the words

The Stage, kept.

What's on screen:
- The big words stay the hero, with a slim strip of A's lane above them: the same bars, line,
  band and head.
- **The syllable now fills from the left as you sing it in tune** (a karaoke wipe that only advances
  when you're on the note), and the words under the fill switch to dark text.
- The line under the words keeps "Now G4, next F#4, then E4 (down)" and the status in words.

Why:
- The Stage is the default for beginners who don't read music.
- The current meter across the width pegs at its ends and gives no melody context.
- This is the Let's Sing and Smule pattern (notes over lyrics), drawn with our lane.

Pros:
- Words first.
- One component shared with A.
- The wipe ties pitch to the word you're singing.

Cons:
- The strip is small, so pitch detail is coarse on a phone.
- Two places to look.

### Find your note and the range check

- **A's version, recommended:**
  - A ribbon box: time across, pitch up.
  - It zooms from the whole range to the octave round your note, as today.
  - A band forms round the note it's settling on, and the hold ring sits at the line's head.
  - The range check draws "Lowest so far: G2" as a dashed line, with your slide down to it.
  - Higher is up, as on the lane: today's bar puts higher to the right.
  - Steadiness is visible over time, so a beginner sees "wobbly" become "steady".
- **C's version:**
  - A vertical ruler with the same puck and ring, and a small smear for the last half second's
    wobble.
  - Simpler, but with no history.
- B and D leave these screens as A draws them.

---

## 5. Recommendation

**Build A as one `PitchLane` drawing with two sizes:** the Notes view (A) and the Stage strip (D).
Then:

1. **Notes view:**
   - Bars as tall as the strictness: 35 cents, or 50 relaxed and 20 precise, so the bar's height
     changes with the setting.
   - Fill as you hit; a tick on lit notes.
   - Labels above the bars.
   - The vibrato band round a centre line.
   - The head with its ring, the arrow badge, and the hollow ring at a breath.
2. **Stage:**
   - Replace the tuning meter with the strip (about 200 px tall on a phone, 250 on desktop).
   - Add the syllable wipe.
   - Keep "Now G4, next F#4" and the status words.
3. **Wait for me:** C's close-up, sharing the puck and hold ring with Find your note.
4. **How it went, See details:** B's phrase page as the per-phrase chart (no cursor, the whole
   phrase).
5. **Find your note, the range check, the creator's hold-to-sing:** A's ribbon box.
6. **Everywhere:** the smoothing below, and one rule for words: "On the note", "A little higher",
   "A little lower", "Breathe in", each with its icon. These are announced politely to screen
   readers at most once per note.

Why A over C as the main view:
- Harmony singers need the melody's shape to find the next note by its interval. Vanido's top
  complaint is that "intervals are hard to tell".
- C shows the next note only as an arrow.

Why A over B: the lane brings the music to a fixed place for your eyes, and look-ahead never
shrinks.

Open product questions for Hillel:
- Should lighting a note need a short hold (about 0.15 s on the centre) rather than any single
  reading? This changes how notes light, so it is his call.
- Should the bar's height follow the strictness setting? Recommended: yes, it makes the setting
  visible.

---

## 6. Smoothing and display spec

One pipeline for every display of your voice:
- Sing's lane and strip;
- Wait for me;
- Find your note, the range check and the creator.

It is the same in the apps. It is **for drawing only**: lighting and scoring keep their own,
stricter path, so the light and the score still agree.

It builds on what shipped in commit 1f7e926 (`web/js/pitch-display.js`). `smoothing.js` in this
folder has a reference version (`proposed()`) and `smoothing.png` shows it on simulated readings.

### 6.1 The pipeline, in order

1. **Read at 50 a second, stamped on the audio clock.**
   - Take a reading on each animation frame, at most one every 20 ms.
   - Stamp it with the engine's song time less the measured latency (as `hearVoice` does), not
     `performance.now()`.
   - Keep the 2048-sample window at 48 kHz. It reaches a bass's low notes, and its centre is about
     21 ms old.
   - No timers: the reading rides the frame loop, and nothing is scheduled.
2. **Voicing, with hysteresis.**
   - Start a voiced stretch when clarity is at least 0.80 and the level is above -50 dBFS for 2
     readings in a row (40 ms).
   - Stay voiced while clarity is at least 0.65 and the level is above -55 dBFS.
   - Below that, it's a gap (step 6).
3. **The singer's register (the octave guard, part 1).**
   - Work out the whole-octave offset between the singer and the part: `12 x round((reading - note) / 12)`.
   - Start from the person's known range (Find your note, the range check) when there is one.
     Otherwise use the first clear reading inside a note.
   - Then use the median of the last 3 s of offsets, once there's 1.5 s of evidence.
   - Change it only after 1.5 s of disagreement.
   - Subtract it from **every** reading, inside notes and out. This replaces the per-note fold.
   - When the offset isn't 0, say once, kindly: "You're singing an octave lower. That's fine."
     (or higher).
4. **Slips and jumps (the octave guard, part 2).** Compare each reading with the line as shown:
   - Within 0.6 semitones of a 7, 12, 19 or 24 semitone jump (fifth and octave slips): hold the
     line until **8 readings in a row** (160 ms) agree within 0.6 semitones. Then accept and snap.
   - Any other jump over 2.5 semitones: wait **3 readings** (60 ms), then snap. A real leap shows
     in about 60 ms with no easing lag.
   - Slides and scoops move less than 2.5 semitones between readings, so they pass straight
     through and are drawn as they are.
5. **Median, then ease.**
   - A median of the last **3** readings (60 ms) removes single spikes.
   - Then a one-pole ease toward it with **tau = 30 ms**, or 12 ms while the gap is over 1.5
     semitones.
   - A 6 Hz vibrato keeps about two thirds of its swing here (the shipped settings keep under 40%).
6. **Gaps: hold, bridge, fade; never fall.**
   - Gaps up to **0.12 s** (consonants, a dropped frame) are bridged: the line holds its last pitch
     at full strength and carries on.
   - Longer gaps end the stretch:
     - the last **0.3 s** of the line fades out over **0.3 s**;
     - the head becomes a hollow ring at the last pitch and fades with it;
     - nothing is ever drawn toward the floor or toward the next reading.
   - A new stretch after a gap is a new line, never joined to the old one.
7. **Interpolate at the display rate.**
   - Draw every animation frame, **one reading behind** (20 ms), interpolating between the two
     readings around that moment. Never extrapolate.
   - The head sits on the now line at song time less latency less 20 ms. It trails the line by 2
     to 3 px, which nobody sees, and it never steps.
8. **Vibrato: shown, not jittery.**
   - The drawn line is the **centre**: a 0.18 s moving mean, about one vibrato cycle.
   - Round it, a soft band in the part colour at about 38% shows the swing from that mean, at most
     half a semitone each side.
   - The band closes on any stretch that isn't steady (over 0.45 semitones from end to end of the
     window), so leaps and scoops stay a clean line.
   - At 150 px a second or more (C's close-up), draw the eased line itself instead: the vibrato is
     then wide enough to read as a wave.
9. **Hints from the centre, with hysteresis.**
   - Use the centre over the last 0.3 s.
   - "A little higher" or "A little lower" (word, arrow and badge) shows after **0.4 s** outside the
     tolerance.
   - It clears after **0.2 s** back within **25 cents**.
   - It never flickers on vibrato.
10. **The lane's scale stays put.**
    - Fixed per phrase. A voice outside the lane stays at its edge as an arrow with the note name
      ("Higher than the lane: A5").
    - It is never clipped away, and the lane never rescales to follow the voice.

### 6.2 Parameters

| Parameter | Shipped (1f7e926) | Proposed |
|---|---|---|
| Readings a second | About 30, throttled on `performance.now()` | 50, on the frame loop, stamped on the audio clock |
| Voiced | Clarity at least 0.85 | Start: at least 0.80 and above -50 dBFS for 2 readings. Stay: at least 0.65 and above -55 dBFS |
| Octave | Folded to within 6 of the note now; unfolded outside notes | The singer's register (whole octaves), learned once and applied everywhere |
| Slips | 6 or more semitones wait 4 readings (0.13 s) | 7, 12, 19 or 24 (±0.6) wait 8 readings (0.16 s); other jumps over 2.5 wait 3 (60 ms), then snap |
| Median | 5 readings (0.17 s), outliers over 0.75 replaced | 3 readings (60 ms) |
| Ease | 0.06 s singing, 0.10 s holding | 30 ms; 12 ms on a leap over 1.5 semitones |
| Short gap | Holds 0.2 s | Bridged up to 0.12 s, drawn as one line |
| Long gap | Fades over 0.15 s | The last 0.3 s fades over 0.3 s; hollow ring at the last pitch |
| Drawing | One point per reading | Every frame, one reading behind, interpolated |
| Vibrato | Mostly eased away | A 0.18 s centre line plus a band of up to half a semitone; the plain line at 150 px/s or more |
| Hints | None on the lane; the Stage meter reads the last point | On the 0.3 s centre: show after 0.4 s out, clear after 0.2 s within 25 cents |
| Lag, sound to head | About 165 ms, uneven (21 window + about 67 median + 60 ease + up to 33 between readings) | About 100 ms, steady (21 window + 20 median + 30 ease + 20 drawing delay + up to 20 between readings) |

### 6.3 Reduced motion and accessibility

- **Reduced motion:**
  - No glow pulses, particles or easing of the window (C steps between notes).
  - The fade at a breath becomes a 0.3 s hold, then the line ends.
  - The line, band, fill and ticks still draw, because they are information.
  - The lane steps as the spec already says.
- **Never colour alone:**
  - a hit fills the bar and keeps a tick;
  - out of tolerance shows an arrow badge and words;
  - a breath shows a hollow ring and "Breathe in";
  - in B, line weight carries in or out.
- **The voice line is `cream`** in every theme (the highest contrast), with the part colour only in
  its band and halo.
- **Screen readers:** the now card's sentence ("On the note", "A little higher") is announced
  politely, at most once per note.

### 6.4 How to check it

- **Unit tests** on synthesized singing, next to `web/tests/pitch-display.test.js`:
  - a man an octave down across a rest, and an early entry: no drop;
  - an octave and an octave-and-a-fifth slip of 2 to 6 readings at an onset: ignored;
  - a 60 ms breathy dip: bridged;
  - 6 Hz vibrato at ±40 cents: the band's half-width is at least 25 cents and the centre moves
    less than 5 cents;
  - a leap of a fifth: shown within 80 ms;
  - a slow slide over a third: drawn as a slide, never snapped.
- **Replay real takes:** run recorded takes (the beta takes, the creator's sung guides) through
  both smoothers and compare them by eye, as `smoothing.html` does with simulated ones.
- **On a device:** Hillel sings the alto of Amazing Grace in his own octave, in headphones, on an
  iPhone. The line should never leave the lane.
