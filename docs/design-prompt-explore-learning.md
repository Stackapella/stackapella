<!-- Historical: the prompt used with Claude Design on 2026-10-26. Its out-of-scope list (no social feed,
profiles or payments) has since changed; see docs/vision.md. Kept as it was sent. -->

Explore many different UX directions for **learning and recording a harmony part** in
**Stackapella**. Anything is possible: push past the obvious. I want a wide range of ideas to
compare, from calm and minimal to bold and playful, before we pick one.

## The product in one paragraph

Stackapella is a web app (an installable PWA) for making multi-part vocal harmonies alone.
Users start from a **harmony template**: a ready-made arrangement of a song such as Amazing Grace,
Danny Boy or Red River Valley, sung by 2, 3 or 4 people as Soprano, Alto, Tenor and Bass. Each part
has a **guide**, a synthesized "oo" voice singing that part's notes. The singer learns one part at
a time, records it, and their take replaces the guide. Part by part, the whole arrangement becomes
their own voice, and they export the mix. The audience is beginners who want to sing harmony but
don't know which notes to sing, plus choir members learning their section. They're often on a
phone with headphones in.

## What the app knows at every moment (design with this, it's all real)

- **The song:** title, key, tempo, time signature, sections (Verse 1, Chorus), and phrases (each
  about 4 to 8 seconds, with the words of the line).
- **Each part:** name (Soprano, Alto, Tenor, Bass), colour, vocal range (for example B3 to E5),
  whether it carries the melody, and its **notes**. Every note has a pitch (exact note name, like
  E4), a start, a length, and a lyric syllable. "Hel-" and "lo" join into "Hello", and a note with
  no syllable continues the word (a melisma).
- **Status per part:** guide only, or recorded (with a waveform of the take).
- **Playback, live:**
  - the exact position, the current bar and beat;
  - which note each part is singing right now;
  - the current syllable;
  - the phase: count-in, listening, cue ("get ready"), or "your turn".
- **Practice modes** for the part in focus:
  - **Listen:** your guide, alone or with the others.
  - **Sing along:** your guide quieter, with the others.
  - **On your own:** your guide muted, with the others.
  - **Record:** your take replaces the guide.
- **Tools:**
  - loop a phrase;
  - "Listen, then repeat" (the app plays a phrase, then it's your turn to sing it back against the
    other parts);
  - play your starting note;
  - Compare (your take or the guide);
  - change key (moves every guide);
  - a range check (sing your lowest and highest note, and the app suggests the part and key that
    fit you);
  - mute, solo and volume per part.
- **Microphone:** a live input level, plus whether the timing check has been done. Recording needs
  headphones.
- **What it can't do yet,** so don't rely on these: live pitch scoring (whether you're on pitch),
  video, other people in real time, and recorded human guides (synthesized only for now).

## Directions to explore

Give each direction a short name and a one-line idea. Show it at phone size (390 x 844, portrait),
and desktop size (1440 x 900) where it matters. Show key states: idle, playing (listening), the
"get ready" cue, "your turn", recording, and a part just recorded. Here are starting points: expand
them, combine them, and add your own.

1. **Full-screen lyrics (karaoke stage).** While playing, the words fill the screen, the current
   syllable lit in your part's colour, the next line waiting. Controls fade away and return on tap.
   What do the other parts look like here, if anything?
2. **Notes you can see.** Your part's notes as a big scrolling lane (a piano roll or simple staff),
   the current note large and named, the other parts as faint lanes behind. Make "which note do I
   sing" unmissable.
3. **The choir.** The parts as singers on a stage or in choir seats: each one lights up while it
   sings, glows in its colour once it's "you", and is tapped to practise. Make the progress
   ("3 of 4 parts are you") feel like filling a choir.
4. **A guided lesson.** One clear next step at a time, like a coach: "Listen to the alto line",
   then "Sing along with it", then "Now on your own", then "Record it". Use big buttons, little
   reading, and celebrate each step.
5. **Phrase by phrase.** The song as a row of phrase cards. Master each one (listen, then repeat,
   then a tick) before moving on. Show progress across the song like levels or a journey map.
6. **Call and response.** Built around "Listen, then repeat": a strong visual rhythm between
   "listen" and "your turn" (split screen, a conductor's cue, a breathing circle). Make the timing
   feel natural.
7. **Music stand (tablet and desktop).** Like a printed part on a stand: big, readable notes and
   lyrics for your line only, with the transport kept small. Calm and focused, made for rehearsal.
8. **The stack.** Visualise the harmony itself: the parts as coloured layers stacking up as each
   becomes yours, so the full chord is something you can see. Use it for the moment the arrangement
   is complete.
9. **Eyes closed.** An audio-first, minimal mode: one big button, voice-like cues ("your turn" as
   a sound and a pulse), almost nothing to read. Good for walking around or singing with feeling.
10. **Phone in one hand.** Everything reachable by the thumb: swipe between parts, swipe between
    modes, hold to record. Explore gestures over buttons.
11. **Your voice.** Put the singer's own range at the centre: their range on a keyboard, each
    part's range against it, the suggested part and key. How does that carry into practising?
12. **Wildcards.** Two or three ideas I haven't thought of.

## The moments that matter

Design these specifically, in whichever directions they fit:
- **Choosing a template and how many singers** (2, 3 or 4) for the first time.
- **The first 30 seconds:** someone who's never sung harmony opens Amazing Grace. What do they see
  and do?
- **"Your turn":** the switch from listening to singing must be unmistakable, with a cue beat
  before it.
- **Recording:** the count-in, what you see while singing, and the stop.
- **Just recorded:** hearing yourself in the mix, Compare, try again, and on to the next part.
- **The arrangement complete:** every part is you. Make it feel like an achievement, then Export.
- **When things go wrong:** the mic is silent, the timing check hasn't been done, or there are no
  headphones.

## Style and constraints

- **Keep the Stackapella look:** a warm dark studio, Bricolage Grotesque, and the 8 part colours
  (orange, mustard, green, teal, cyan, blue, lavender, pink). Each direction may stretch the style
  (a lighter music-stand look, a darker stage) but should still feel like the same product.
- **It's built with Vue, Tailwind and a canvas** for waveforms and notes. Animation is fine, but
  must respect reduced motion and never delay audio.
- **Accessibility:** WCAG 2.2 AA contrast, touch targets of at least 44 px, nothing shown by colour
  alone, and lyrics readable at arm's length.
- **No em dashes in UI text.** Write the copy in plain, warm sentence case.

## What to deliver

1. **8 to 12 distinct directions,** each with its name, one-line idea, phone and desktop frames,
   and the key states.
2. **For each direction:**
   - who it's best for (a beginner, a choir member, a creator);
   - what it does better than the others, and its main risk.
3. **A comparison table** with your top 3 recommendations, and how parts of different directions
   could combine (for example karaoke lyrics while singing, the choir view for choosing parts).

---

[Back: The design prompt](design-prompt.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: The runbook](runbook.md)
