# Your tune, sung: hum it, hear it harmonised, hear it sung

**Status:** planned (2026-10-27); step 3 decided by Hillel the same day. Step 3's server side
(ideas, voices, the bell and the daily email) was built 2026-10-27, behind the `ideas` flag. Parts exist (the creator's Sing it and Write it, `arrange.js`'s
suggestions); the story, the highlighting and "real voices for your tune" are new.

Back: [The template creator](template-creator-plan.md) · Next: [More features](more-features-plan.md)

## The idea

Most people with a melody in their head can't sing it well, let alone arrange it. Stackapella
can take them all the way:

1. **Hum it or draw it.** Hum the tune (the creator's Sing it transcribes it), or draw it: drag
   notes on a simple grid with a finger or the mouse (the note editor, made playful on a phone).
2. **Hear it harmonised.** "Suggest harmonies" writes the alto, tenor and bass under it, and
   plays all four with the guide voices straight away. (Cool.)
3. **Hear it sung by real people.** Share the idea so singers on Stackapella record its parts:
   your tune comes back as a real a cappella recording, credited to you as the writer and to
   each singer. (Amazing.)

Hillel's view: "super powerful concept". It's the most distinctive thing the app can do: nobody
else turns a hummed idea into a real choir.

## Where it shows

- **The landing page:** its own section, "Got a tune in your head?", with the three steps above
  and a playable example (a hummed line, then the four-part suggestion, then the same tune sung by
  real voices when we have one). A strong candidate for the hero's second slide or the main story.
- **The home:** the "Make your own" card becomes **"Hum a tune"** ("Hum it or draw it, and hear
  it harmonised"), with the same three-step promise in a line.
- **After the suggestion plays:** "Want real singers to sing it?" leads to step 3.
- **The course:** a late lesson ends with "Now make your own: hum a tune".
- **Share pages and posts:** "Written by <name>, sung by <names>" with "Hum your own tune".

## Step 3: real voices for your tune (decided)

- **Share it as an idea in a public gallery:** "Share as an idea" publishes the tune with **every
  part open** (the melody too), with the suggested parts as guides, the writer's note and their
  credit. It sits on an **Ideas** shelf in the gallery ("A tune by Sam, waiting for voices").
- **Anyone may choose to sing it:** any part, in Sing as usual, then publish their part to the
  idea. No queue, no assignment, no promise that anyone will.
- **If it happens, the writer is emailed:** "Sam sang the alto of your tune" with a link to hear
  it, and a second email when every part has a voice. This is per-event notification email about
  the person's own idea, which Hillel allowed (2026-10-27): at most one email per idea per day
  (a digest of that day's new voices), an on/off setting under Settings, and a one-click
  unsubscribe in each email. The in-app bell gets the same.
- **Free:** sharing an idea and singing someone's idea are free.
- **Rights:** the writer confirms the tune is theirs (or traditional) and agrees others may sing
  it and share the result, credited; singers agree their part joins the writer's song under the
  "public songs' voices" rules. Reports and takedown as for any public song.
- **The Ideas shelf needs the gallery** (N1): it can open on its own flag (`ideas`) before the rest
  of the gallery, since it's what gives the shelf its first content.

## Questions for Hillel

1. **Decided:** free; ideas are shared in a public gallery for anyone to sing (step 3).
2. **Paid singers later?** A writer could commission a specific singer to record all the parts
   (a marketplace like coaching, M8's payments). Big potential; later.
3. **Lyrics:** can a tune come with words (the creator's lyric fitting exists), and should
   singers sing words or "oo"?

## Building it (once the questions are answered)

- **Frontend:** the "Hum a tune" entry and the playful draw mode; "Hear it harmonised" straight
  after the melody; "Share as an idea"; the Ideas shelf; the writer's view of an idea's progress; the landing's
  section is the landing agent's.
- **Backend:** ideas as all-parts-open harmonies on an Ideas shelf (its own `ideas` flag), the
  notification email (daily digest per idea, the setting, unsubscribe) and the bell, the rights
  confirmations.
- **Landing agent:** the section and example on the landing page.
