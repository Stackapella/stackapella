---
title: Accessibility statement
version: 2
reviewed: false
drafted: 2026-10-27
updated: 2026-10-30
---

# Accessibility statement

We want everyone who wants to sing harmony to be able to use Stackapella. This page says what we
do, how we check it, where we know the app falls short, and how to tell us about a problem.

## What we aim for

Our target is the Web Content Accessibility Guidelines (WCAG) 2.2 at level AA, across the app and
the website, and we check it automatically on every main screen.

## How we check

Every main screen (home, the course and a lesson, the workout, a song and Sing, the studio and its
sheets, Settings, sign-in and Contact us) is checked automatically on every change to the app: at
phone, tablet and desktop widths, in light and dark, in both looks (game and serious), and with
reduced motion on. We also go through it by keyboard. These are our own checks, not a formal audit
or certification. What a screen reader announces while you sing, and where focus goes, is checked by
automated tests; we haven't yet tried it with VoiceOver or TalkBack on real phones. This statement
was last updated on 30 October 2026.

## What the app does

- **Contrast:** text meets WCAG 2.2 AA contrast (4.5:1, and 3:1 for large text) in light and dark,
  in both looks.
- **Keyboard:** everything works from the keyboard, and focus is always visible. An open sheet keeps
  focus inside it until you close it, and Escape closes it. You can start a lesson and move through
  its steps by keyboard alone. In Sing and the studio, Space plays, or stops a recording, and the
  single-letter shortcuts (like R to record) can be turned off in Settings.
- **Screen readers:** every button, link and field has a name, and every picture has a text
  alternative. Each screen has one main region, its headings never skip a level, and its heading is
  also the tab's title. When you move to a new screen, focus moves to its heading, so your screen
  reader says where you are. While you sing, it speaks plainly and only when it matters: lesson 1
  says what it hears once it settles, then names your note in one line; Sing says the count-in once
  and when recording starts, never every beat; and after a take, focus moves to its result, so the
  measure is read first. In the song editor, notes also have a list view.
- **Not by colour alone:** your pitch is also given in words and note names, like "Now G4, next
  F#4" and "On the note", and each part's colour is picked to stay distinct.
- **Reduced motion:** if your device asks for less motion, the app's animations (celebrations,
  moving lesson drawings and game effects) stay still, and nothing moves on its own while you're
  idle.
- **Size and zoom:** every control is at least 24 by 24 pixels, and most are 44 or more on phones.
  The app works from 320 pixels wide without scrolling sideways, and pinch zoom is never blocked.
- **High contrast:** Windows High Contrast (forced colours) is supported, and controls keep their
  borders and focus rings.
- **Calmer or quieter:** sounds and game effects can each be turned off, and the serious look drops
  stars, XP, badges and streaks.
- **Captions:** when a song has words, share videos can show them as they're sung, so they work
  with the sound off.
- **Password managers** work with our sign-in forms.

## Where we know it falls short

- **It's an audio app.** Learning harmony means hearing the other parts and singing your own, and
  the feedback on your pitch comes from your microphone. Much of Stackapella will be of little use
  without hearing, or without a voice and a microphone.
- **The note lane, waveforms and pitch line are drawn as pictures.** Their main information is also
  in words, but a screen reader can't follow the picture note by note while you sing, and we haven't
  yet tested singing with VoiceOver or TalkBack users.
- **Timing depends on your device.** Speakers and headphones add a delay, which the timing check
  measures by playing short chirps into your microphone. If that fails, you can use your browser's
  own estimate instead, which can be tens of milliseconds off, and then move a part's timing by ear
  in the studio. Recording a guide voice needs the measured delay.
- **No captions without words.** A song with no words, or a share video made without them, has
  nothing to caption.
- **English only,** at launch.
- **Content from others,** such as coaches' voice notes and exercise packs, may not have a text
  version.

## Tell us about a problem

If something stops you using Stackapella, or you need something in another form, please write to
contact@stackapella.com, or use Contact us in the app. Tell us what you were trying to do, and
the device and browser you use. We aim to answer within 2 working days, and we'll tell you what
we can fix and when.
