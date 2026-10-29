# The song builder: start from chords, sing over a backing, or find chords for your tune

**Status:** being built (2026-10-27): step 1, `theory.js` with tests and test vectors, done (and reviewed by a songwriter); step 2, the backing (voices on "oo" and keys, looped on the engine), done; step 3, Start from chords in the creator (feel, key and tempo, sing over it, parts on those chords), done; Hillel's answers below. Built by the frontend agent straight
after the harmony-suggestion rework (it shares `web/js/arrange.js`), before "Hum a tune".

Back: [Your tune, sung](your-tune-plan.md) · [The template creator](template-creator-plan.md)

## The idea

Hillel loves the creator and wants it to help people **write a song, not only transcribe one**:

1. **Pick a progression** (I IV V I, I V vi IV, the 50s doo-wop I vi IV V, 12-bar blues...) in a
   key that suits **their** voice, at a tempo and feel they choose.
2. **The app plays a simple backing loop** of it, and they sing or hum over it to find a melody.
   The creator's Sing it transcribes the melody as it does today.
3. **Flesh out the song:** once there's a verse, the app suggests a chorus that lifts and blends
   with it, and an optional bridge. Each section is its own progression, and the song is an order
   of sections (Verse, Chorus, Verse, Chorus, Bridge, Chorus).
4. **The reverse:** sing a tune with no chords, and the app maps chords to it (the creator's
   `chooseChords` already does this; it becomes visible and editable as a chord row).

Stackapella leans vocal (instruments are welcome too), so **the chords become the harmony**: the
alto, tenor and bass are voiced from the chosen progression, so the backing the singer hummed over turns into the parts
others will sing.

## Hillel's answers (2026-10-27)

| Question | Answer |
|---|---|
| What the backing sounds like | **The guide voices sing the chords on "oo"** (so the backing is already the A, T and B parts), **plus a simple keys or strum sound one switch away**, synthesised on the `AudioContext` with no sample files and no library. |
| Free or Plus | **Free**, like the rest of the creator. Plus keeps what it has (your own voice kept as a guide). |
| When | **Right after the harmony-suggestion rework**, then "Hum a tune". |

## The music theory (the frontend agent writes it as data plus small pure functions)

A new module, `web/js/theory.js`, with tests, shared by the creator, `arrange.js` and later the
mobile app (same test vectors).

**Progressions, in Roman numerals, by family** (each with a plain name, a one-line feel and
tags used for the chorus and bridge suggestions):
- *Foundations:* I IV V I; I IV I V; I V I.
- *Pop:* I V vi IV ("the four chords"); vi IV I V (the same, sadder start); I vi IV V (50s
  doo-wop, perfect for a cappella); I IV vi V; IV I V vi.
- *Warm and soulful:* I iii IV iv (the minor iv's ache); I I7 IV iv (gospel); ii V I (jazz cadence);
  I vi ii V (rhythm changes' turnaround).
- *Minor:* i bVII bVI V (Andalusian); i bVI bIII bVII; i iv v i; i bVI bVII i.
- *Classic:* the Canon (I V vi iii IV I IV V); the circle (vi ii V I).
- *Folk and modal:* I bVII IV I (Mixolydian); i IV (Dorian vamp).

**Blues** as its own family, with choices:
- *Forms:* 12-bar (I I I I / IV IV I I / V IV I V), **quick change** (I IV I I ...), **slow
  change**, 8-bar blues, minor blues (i iv v), and 16-bar.
- *Turnarounds* for bars 11 and 12: I V (plain), I IV I V, I vi ii V, I VI7 ii V, the chromatic
  descent (I I7 IV iv then I V), and bIII bVI bII ending on I (jazzier).
- *Feels:* shuffle (swung eighths), straight, slow 12/8, and a gospel-tinged one with the
  dominant-seventh colour on I and IV.

**Choosing a key the person sings well in:**
- Use the range the app already knows (the range check, the course's comfortable note, or Your
  part's range); without one, ask once: "Sing your lowest comfortable note, then your highest"
  (the existing range check), or pick by voice type.
- Place the tonic so a typical melody over the progression (roughly a fourth or fifth below the
  tonic up to a sixth above it, more for a chorus) sits inside the comfortable range with a little
  headroom, and prefer the more singable of near-ties. Show it plainly: "C major suits your voice"
  with a "Higher" and "Lower" pair, never a key-signature quiz.
- Every suggestion re-checks that A, T and B still fit their ranges once voiced.

**Suggesting a chorus that blends with the verse:**
- The chorus contrasts in a way that still belongs: it **starts on a different chord** than the
  verse (often IV or vi when the verse starts on I, or I when the verse starts on vi), its
  **melody sits higher** (the suggestion nudges the range up a third or so), the **harmonic
  rhythm** often changes (a chord a bar instead of every two bars, or the reverse), and it **lands
  on I** at the end where the verse may end open on V.
- Offer three, ranked: a "lift" (same family, brighter start), a "contrast" (relative major or
  minor emphasis), and a "classic" (the most-used pairing for that verse). Each plays over the
  verse's last line straight into the chorus so they're judged by ear.

**The optional bridge:**
- It goes somewhere new and returns: start on IV, vi or bVII, visit the relative minor or the IV
  area, and **end on V** to set up the last chorus. Two or three suggestions, also auditioned in
  context.

**The reverse (sing, get chords):**
- The existing melody-to-chords path (`chooseChords`), improved by the voicing rework, shows its
  answer as a **chord row** under the lane, one chip per bar or half bar. Tap a chip for "Try
  another chord" (already planned), and see a short list ranked by fit with the melody notes on
  the strong beats.
- If the singer used the builder first, the chosen progression is the answer and the reverse only
  checks for clashes ("The G on 'home' clashes with F; try Dm or keep F?").

## The flow (in the creator, one step at a time, as the Coach style asks)

1. **Start:** "Start from chords" sits beside "Sing it" and "Write it" (and inside "Hum a tune"
   as "Hum over a backing").
2. **Pick a feel:** cards per family with a Play on each (two bars, in your key), the blues as one
   card that opens its forms, turnarounds and feels. Names are friendly ("The four chords",
   "Doo-wop", "12-bar blues, shuffle"); Roman numerals and chord letters sit under Advanced, as
   the creator already does.
3. **Key and tempo:** the suggested key for your voice (Higher, Lower), a tempo with a tap-tempo
   button, and the time feel.
4. **Sing over it:** the backing loops (voices on "oo", or keys, or both) with a count-in, and
   they sing or hum the melody as in Sing it. A line at a time, or freely over a few loops.
5. **Build the song:** a section strip (Verse, then "+ Chorus" with its three suggestions, "+
   Bridge (optional)", and the order to drag). Each section can be sung over in the same way.
6. **Harmony:** the A, T and B are voiced from the sections' chords by the reworked arranger, and
   the creator carries on as today (fix by hand, Try it as a learner, save, share as an idea).

## Rules it keeps

- Everything that must line up (the backing, the count-in, the recording) is scheduled on the
  `AudioContext` clock; no timers.
- No new library and no sample files: the keys sound is a small synthesised voice (a couple of
  oscillators, a filter and an envelope) and the "oo" pad is the existing guide voices.
- Beginners judge by ear: every suggestion is playable in context before it's chosen.
- The theory is data plus pure functions with tests, reviewed by the musician alongside the
  arrangement review.

## Order of work

1. `theory.js`: progressions, blues forms and turnarounds, sections and their suggestions, the key
   chooser, with tests (and exported test vectors for the mobile app).
2. The backing player: "oo" pad and keys, loops, count-in, swing.
3. The creator's "Start from chords" steps 2 to 4.
4. Sections and the chorus and bridge suggestions (step 5).
5. The chord row and the reverse.
6. Review (a music-theory review and a UX review), fixes, then the landing's "coming soon" list
   gains it until it's on.
