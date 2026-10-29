# Launch QA: the first week, as a new learner

Status: one pass, 2026-10-29 (courses-styles, for the coordinator), on main 47f6f827. Each finding
has an owner; nothing here was fixed by the QA pass. Open: all of them.

Back: [Launch QA of the courses](courses/launch-qa.md) · [Onboarding review](onboarding-review.md) ·
[Docs guide](../README.md#a-guide-to-the-docs)

## How it was checked

- **The landing** (`web/tests/flows/journey_landing.py`, against the Laravel site): at both sizes,
  in both skins and both themes; where Start goes and what it carries; Start followed into the app.
- **The first week in the app** (`web/tests/flows/journey_qa.py`, against the mock), as one new
  device, in order:
  - arriving from Start;
  - lesson 1's mic moment, the note found and the chord moment;
  - lessons 2 and 3 sung for real, with Hear it together played;
  - the session wrap: its goal chosen, and its keep-it ask;
  - Home, then a library song: its sheet and Play, Start, the chooser, the coach, the mic check,
    the timing check, a take and Just recorded;
  - the studio, Share as a guest, signing up, Share signed in;
  - Settings, with Game mode switched both ways and its element switches;
  - the next day: Home and its next lesson.

  A tone over loopback stood in for the voice. The flow checks every screen for:
  - sideways scroll;
  - raw translation keys, "undefined" or "NaN";
  - page errors;
  - a screen slow to be ready;
  - in the serious skin, star, XP, combo or streak words.

  The day after is the same device with its arrival and its lessons moved back a day.
- **Four runs cover every pair** of skin, size and theme once: phone in game light, phone in
  serious dark, desktop in serious light, desktop in game dark. 164 shots are in
  `~/Code/shots-review/launch-qa-journey/`, numbered in journey order.
- Not checked here:
  - "Turn on my mic" and the in-app browser card: headless Chrome has the mic allowed, so the way on
    reads Continue.
  - The browser's own install sheet, a reminder arriving, the email link, and Google or Apple
    sign-in. The mock's member stands in for a verified account.
  - The weekly goal after a day: the day shift moves the lessons, not the practice days.
  - The next day in the game skin, and whether a brand-new account keeps the device's skin. The
    mock's member, signed in after sign-up, brings its own serious skin, so every day-2 screen here
    is serious.
  - The onb1 control arm.
  - Audio by ear, and a real voice.

## What works

- **The landing:** its Start carries `from=start` in every look and opens lesson 1. The chord
  promise shows above the fold on both sizes. The game skin's landing is always dark (Hillel's
  call).
- **Lesson 1:**
  - its first step promises the chord;
  - the note step listens at once and the first hum is the mic check ("Got you");
  - the note is found;
  - the result is the note, the keyboard and the parts it suits;
  - the chord moment plays by itself (A3 as the third of F major), then says what it means. It does
    the same in both skins; the game skin adds the stars and XP, and serious has none.
- **Lessons 2 and 3:**
  - the headphones question comes once;
  - Wait for me ends by itself;
  - the takes are scored ("100% on pitch" in serious);
  - Hear it together plays your take with the guide;
  - the lesson end offers Share on a starred result ("Challenge a friend" in game, "Share result" in
    serious).
- **The session wrap** comes after lesson 3 with the session's totals. 3 days shows as chosen and is
  kept on a tap, and there's one keep-it ask (Install, in Chrome).
- **The song path:**
  - the library sheet's Play plays and stops;
  - Start opens the chooser, and "Tenor fits your range" comes from lesson 1's note;
  - the coach shows the phrases;
  - Sing's mic check and timing check come before the first take;
  - the take ends in Just recorded, then the studio.
- **Sharing, signing up and Settings:**
  - Share asks a guest to sign in;
  - an email sign-up ends on "Check your email";
  - signed in, the guest's lessons and song are still there, and Share offers the link;
  - Settings' Game mode switch changes the skin both ways, and its element switches show only in
    game mode.
- **The next day:** Home's course card (on the desktop) says "Welcome back. Yesterday: A3, …", the
  reminders offer shows, and Continue opens lesson 4.
- No sideways scroll, raw keys, "undefined", "NaN" or page error on any screen of the four runs.
  Nothing was slow except the timing check's own measuring (about 10 s, as it should be).

## Findings

### 1. The serious skin still speaks in stars, XP and levels on the first-week path (frontend)

Serious mode's promise is "just your measures"; these screens break it, at both sizes:

- **The session wrap:** "Session one: your note A3, 3 lessons, 9 stars." and its ask "Keep your
  note, your 9 stars and your level: install the app." (`wrap.title`, `wrap.install`,
  `wrap.account`). Serious could say "3 lessons" and "your note and your progress".
- **Day 2's welcome:** "Welcome back. Yesterday: A3, 9 stars." (`home.lead.welcomeYesterday`,
  `welcomeLast`). Serious could count the lessons sung.
- **Home's course card:** "Level 1: First notes · 75 XP" with a level bar, after session 1 and on
  day 2 (and "Level 2 · 25 / 200 XP" on the desktop hub).
- **Just recorded:** "…so these stars may not be fair… Headphones give you accurate stars."
- **Settings:** Sounds says "A short sound when your stars land"; Start the course over says "Your
  lessons and stars go back to the start. Your note, range, XP and badges stay."

### 2. Day 2 on a phone loses its welcome back (frontend)

Once a song was made that week, a phone gets Home's shorter course band, and that band has no
welcome line (`web/js/components/home.js:178`, the `!short` branch is the only one with
`data-welcome`). That's the learner who did the most on day 1. The desktop shows the line from the
same data. Seen in both phone runs (serious by then; the band is the same in game).

### 3. Day 2's Home makes two asks at once (frontend)

The install card and "Want a nudge on your practice days?" show together, one under the other, at
both sizes (Chrome, where install is possible; seen in the serious skin, see "Not checked"). The decision has the reminders
offer come once install is done or can't be, and the onboarding principles allow one ask per
screen.

### 4. The reminders card's Not now runs to the card's edge on a phone (frontend)

On the phone, "Remind me on my practice days" and "Not now" sit in one row that doesn't wrap, so
"Not now" touches the card's right border (shot 38, both phone runs).

### 5. Just recorded can say two opposite things (frontend)

After a take the mic heard little of, it shows both "We could hear the other parts through your
mic, so these stars may not be fair, and this take doesn't count against you" and "Couldn't hear you
clearly this time, so no stars and nothing lost". The first talks about stars the take hasn't got.
Loopback caused the bleed here, but a real device with no headphones and a quiet singer gets the
same pair. When the take is unheard, the bleed line could go.

### 6. Share asks a new guest only to "Sign in" (frontend, low)

"Sign in to share, so the link stays yours. Sign in." A first-week guest has no account yet. The
wrap says "Make a free account"; Share could offer that too, or go to the sign-up form.

### 7. The wrap's goal choices break onto two lines on a phone (frontend, low)

"2 days a / week", "3 days a / week", "5 days a / week" each wrap inside their pill (shot 16,
phone). "2", "3", "5" with "days a week" once beside them would fit.

### 8. The first suggested part sings one note for a phrase (arranger, a question)

For an A3 voice, Amazing Grace's chooser suggests the Tenor ("Tenor fits your range"), whose phrase 1
is seven G3s (shot 23). It may be right for the harmony, but it's a flat first song to learn a
part from. Is another part, or another first song, a better first suggestion?

## Owners

| Owner | Findings |
|---|---|
| frontend | 1, 2, 3, 4, 5, 6, 7: open |
| arranger | 8: a question |
