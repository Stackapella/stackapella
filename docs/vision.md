# Stackapella: the vision

**Learn to sing harmony, one part at a time, until the whole song is you.**

Stackapella is an installable web app for singing harmony on your own:
- you pick a song;
- you learn one part with a guide voice, the notes in front of you and feedback on your pitch;
- you record it, and your take replaces the guide;
- part by part, the whole arrangement becomes your own voice.

You can then share it, get feedback from a real coach, or make your own arrangements for others to
learn. It's the audio-only successor to mudeo.

**Voices first, instruments welcome** (Hillel, 2026-10-27): Stackapella leans towards singing,
but it isn't voices only. A ukulele, a guitar, a beatbox or a cello can be a part anywhere a voice
can: recorded in the studio, given its own part name, carried by a template, played as a backing,
and shared. Words and design lead with singing; nothing should stop an instrument.

## Who it's for

**First, beginners who want to sing harmony but don't know which notes to sing** (Hillel's call,
2026-10-26). Everything at launch is polished for them first, and the home screen leads with
learning.

The others are served by the same tools:

| Who | What they come for | Mostly built on |
|---|---|---|
| **Beginners** (first) | Learn a part, hear themselves improve, finish a song in their own voice | Templates, Sing, singing feedback |
| Choir members | Learn their section's part before rehearsal | The same, in their part and key |
| A cappella creators | Stack their own multi-part covers and share them | The studio, export, the gallery |
| Creators, teachers and directors | Write arrangements for others to learn | The template creator, private templates (Plus) |
| Coaches | Sell take reviews (later lessons, packages and exercise packs) | The coaching marketplace |
| Choirs | Directors share songs and assign parts; with Event Schedule, a season and its practice together | Choir groups, the Event Schedule integration |

## The core loop

1. **Choose** a song from the harmony library: a template for 2, 3 or 4 singers. The range check
   suggests your part and key. **The course** (a map of short lessons, like Simply Piano) says what
   to learn next, and its harmony units are "Your first harmony".
2. **Learn** it in Sing, with chords underneath if you want something to tune to:
   - part by part with the Coach (listen, sing along, on your own);
   - or the whole song on the Stage or the Note lane.

   Guides are real voices from the beta singers (in the original key), and the synthesized voice
   otherwise.
3. **Hear how it went:** notes light up as you hit them, then stars, a kind report and one thing to
   try. XP, voice levels, badges, a weekly goal and today's workout keep you coming back.
4. **Record** your part. The take lines up to the sample, and replaces the guide.
5. **Repeat** for the next part, until "every part is you".
6. **Share** it, sounding its best (Make it sound good): a link, or a short video for TikTok and
   Reels; invite friends to sing the other parts; or get feedback from a coach. Once there's a
   crowd: the gallery, the open parts shelf and the song's virtual choir.

Everything else serves this loop, or grows out of it:
- the course leads into step 1 and rewards step 3;
- making templates feeds the library;
- coaching deepens step 3;
- the gallery rewards step 6.

**The measure of success:** songs where every part is the singer's own (finished harmonies), and
singers coming back to learn the next one. These are counted from our own records, never with
analytics.

## The principles (use them to decide when a plan is silent)

1. **The voices are the hero.** The stack of parts, the words and the notes come first on screen.
2. **Sing within a minute.** Ask for details only when they're needed.
3. **Feedback feels safe, and progress feels like a game.** What went well first, then one thing
   to try. No red marks, no 0%, no nagging emails. Stars, XP, levels and badges reward effort, not
   talent. The only streak is a gentle weekly goal, with no penalty for a missed day.
4. **Exact or nothing.** Every part lines up to the sample. Audio is scheduled on the audio clock,
   never with timers. Honest limits are better than guesses (head voice is explained, never
   "detected").
5. **The device first.** It works offline and installed. Analysis happens on the device. Audio
   leaves the device only when the person saves it to their account, shares it, sends it to a
   coach, or submits it as a beta guide.
6. **Private by default.** Nothing becomes public without a clear choice, and the consent of
   everyone who sang on it.
7. **No analytics, no bulk email, no npm.** Counts come from our own records. Email is only about
   something the person did or paid for. The one reminder is opt-in web push on the person's own
   practice days.
8. **Free to learn, Plus for the whole library.** The beginner course, four songs (plus a song of
   the month), recording and sharing are free. Plus is every song (new ones each month), the full
   course and workouts, history, unlimited projects and storage, MP3 and stems. Coaches earn
   through the app, and Stackapella takes a commission. No licensed music: public-domain songs and
   our own originals.
9. **Real people check the content.** A musician checks every official arrangement, and a voice
   teacher every tip and exercise. Hillel approves every coach.

## What it isn't

- **Not a DAW:** no plugins, no MIDI editing beyond the template editor, no effects rack.
- **Not video:** audio only.
- **Not live:** no singing together in real time. Collaboration is taking turns on a project.
- **Not a social network first:** the gallery celebrates finished harmonies. There's a Following
  tab, but no endless feed is designed to keep people scrolling.
- **English first, but built for every language:** the interface, tips and syllable splitting are
  English at launch, and adding a language must only mean dropping in translation files
  (`docs/architecture.md`, "Languages"). Words in
  other languages work, split by hand.

## Launch, and after

**Everything that doesn't need a network, at launch** (Hillel's call, 2026-10-26; it replaces "a
small launch, then grow"). A feature ships at launch if it's useful to one person, or to people
who already know each other:
- real voices, Sing, the whole course and the harmony gym, with chords, slow practice and Make it
  sound good;
- accounts, cloud save and Plus;
- share links and videos, and inviting friends to sing the other parts;
- **the template creator,** with templates shared by link (the public library waits);
- **choir groups,** and choirs across Event Schedule and Stackapella;
- **coaching:** coaches sign up and sell to people who come through their own profile link
  (searching for a coach waits), with lessons through Event Schedule;
- Invoice Ninja.

**What waits for a network:** anything that depends on strangers finding each other:
- the public gallery and Explore;
- the public template library and its search;
- the coach directory;
- leagues;
- the open parts shelf;
- the virtual choir;
- song requests.

Opt-ins are collected from launch, so each opens full. **The admin's network features panel tracks
each one against a threshold,** and says when to build it and when to switch it on.

The order of work, split between the backend and frontend agents, is in [roadmap.md](roadmap.md).

## Where things are written

The order to read the docs in, with what each is for, is the guide in the
[README](../README.md#a-guide-to-the-docs). Start with [roadmap.md](roadmap.md) for the order of
work, and [architecture.md](architecture.md) for how the pieces fit.

---

[Back: README](../README.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Roadmap](roadmap.md)
