<!-- Historical: the prompt used with Claude Design on 2026-10-25. Its out-of-scope list (no social feed,
profiles or payments) has since changed; see docs/vision.md. Kept as it was sent. -->

Design the UI for **Stackapella**, a web app for making multi-part vocal harmonies. Show each
screen in two sizes: mobile (390 x 844, iPhone) and desktop (1440 x 900).

## What the product is

A singer records one vocal part, then records the next part while hearing the earlier ones
through headphones. The parts stack into a full a cappella arrangement: melody, harmonies, bass,
vocal percussion. Most people work alone on their own projects. Collaborators can be invited later
(not designed yet, but leave room for a "people on this project" area).

Audiences:
- Hobby singers and choir members.
- YouTube and TikTok a cappella creators.
- Vocal teachers making practice tracks.

Many are on their phone, holding it in one hand with headphones in.

Think of it as a focused, friendly multitrack recorder built only for voices. It is not a full DAW:
no plugins, no MIDI, no piano roll.

## Design principles

- **The voices are the hero.** The stack of parts is the main thing on screen.
- **One idea per region.** Every control earns its place.
- **Recording must feel safe and obvious.** It should always be clear what is about to happen:
  count-in, recording, playing back.
- **Fast is a feature.** Recording a new part is at most two taps from a project.
- **Delight with restraint.** Small crafted details (a part's waveform drawing in as you sing, a
  gentle pulse on the count-in) rather than decoration.
- **Accessible by default.** WCAG 2.2 AA contrast and touch targets of at least 44 x 44 px. State is
  never shown by color alone: mute, solo and record also use icons or labels. Visible focus
  states. Works with screen readers.
- **Light and dark themes.** Show the main studio screen in both. Dark is the default for the
  studio.
- **UI text:** sentence case, warm and plain, no jargon where a plain word works, no em dashes.

Visual direction is open. Bring your own brand: logo, palette, type. Suggested mood: warm, human,
vocal (breath, layers, harmony), not the cold grey of pro audio software. Each part gets its own
color from a harmonious palette of about 8 colors that stay distinct in both themes and for
color-blind users.

It will be built as an installable web app (PWA) with Vue and Tailwind, so:
- Design with clear components and spacing tokens; they become CSS custom properties.
- Avoid effects that rely on hover alone (mobile has none).
- When installed it runs full screen with no browser bar, so the layouts need their own back
  navigation.

## Screens to design (mobile and desktop for each)

### 1. Sign in and sign up
- Continue with Google, or email and password.
- Sign up, forgot password, and a "check your email" verification state.
- A short line on what Stackapella is, for first-time visitors.

### 2. Projects (home)
- A list or grid of the user's projects. Each shows: title, number of parts, length, last edited,
  and a tiny stacked-waveform thumbnail.
- **Free plan: 3 saved projects.** Show a quiet usage meter ("2 of 3 projects").
- At the limit, "New project" explains the limit kindly and shows a "more projects coming with
  Stackapella Plus" message. No checkout, because billing doesn't exist yet. Keep a slot for a
  future upgrade button.
- Empty state for a brand-new user, inviting them to make a first project.
- Projects are private by default. A small lock or private indicator.

### 3. New project / project settings
- Fields:
  - Title.
  - Tempo in BPM (optional), with a tap-tempo button.
  - Key (optional).
  - Time signature.
  - Count-in (off, 1 bar, 2 bars).
  - Metronome while recording (on/off).
- Keep it light: only the title is required, so a singer can start in seconds.

### 4. Studio (the main screen; spend the most care here)
**The stack of parts.** Each row has:
- the part's name (editable; defaults like "Melody", "Harmony 1", "Bass")
- its color
- a waveform across the timeline
- who recorded it (avatar, for future collaboration)
- volume
- pan (left/right)
- mute and solo
- a menu with: re-record, nudge timing earlier or later, rename, delete

**The transport:**
- play/pause and stop
- a large, unmistakable **record new part** button
- metronome toggle
- count-in setting
- current time and total length

**A timeline** with a playhead, a bar/beat ruler when a tempo is set, and seconds otherwise.

**States to show:**
- **Empty project:** "Record your first part".
- **Ready:** a few parts.
- **Count-in:** a big beat countdown (4, 3, 2, 1) synced to the clicks.
- **Recording:**
  - The new part's waveform draws live.
  - A clear recording indicator.
  - The other parts keep playing.
  - Stop is easy to hit.
- **Just recorded:** a take review with keep or try again. When re-recording an existing part,
  the previous take stays recoverable until the singer keeps the new one.
- **Saving or uploading:** a subtle per-part sync indicator, plus a "saved" confirmation. A part
  waiting to upload while offline must clearly say it is safe on this device.
- **Many parts:** 10 or more. Show how the list scales: compact rows, scrolling, collapsible
  controls on mobile.

**Layout:**
- Mobile: portrait, one hand. Parts stack vertically. Per-part controls collapse into a tap-to-open
  sheet. The transport is pinned to the bottom within thumb reach.
- Desktop: a wider timeline, per-part controls inline on the left, and room for a future side panel
  (people, comments).

### 5. Headphones reminder (before recording)
- A friendly modal or sheet shown before the first recording of each session: "Put your
  headphones on, so the other parts don't leak into your new recording."
- Wired headphones keep timing tightest. Bluetooth works but adds delay, and the app can measure it.
- A "don't remind me again" option.
- Include a simple, charming headphones illustration.

### 6. Timing check (latency calibration)
A short guided flow (3 or 4 steps) that measures the delay between the speakers or headphones and
the mic, so new parts line up exactly.

- **Step 1: Choose what you'll record with.**
  - Laptop or phone speakers and mic: turn the volume up in a quiet room.
  - Wired headphones: hold one earbud or earcup against the mic.
  - Bluetooth headphones: same as wired.
- **Step 2: Measuring.** About 10 seconds of short chirps, with an animated progress state.
- **Step 3: Result.** "Your delay is 23 ms. New parts will be lined up automatically." There's a
  "measure again" option, and a fallback if measuring failed: "Tap along to the clicks" (the singer
  taps on each click heard), or a manual nudge slider.
- When the audio device changes, show a gentle prompt: "New headphones? Re-check timing."
- It should feel quick and reassuring, not technical. Hide the numbers behind a "details" toggle.

### 7. Export
- Mix down the project to WAV (best quality) or MP3 (smaller), with a filename field.
- Progress state, then a done state with download.
- Note that mute, solo, volume and pan are included in the mix.

### 8. Permissions and errors
- Microphone permission: a pre-prompt that explains why the mic is needed, before the browser asks.
- Mic blocked: how to allow it, with steps for Safari and Chrome.
- iPhone silent switch or "tap to enable audio": browsers need a tap before audio can start.
- Unsupported browser.
- Lost connection while editing. Changes are kept and will sync.

### 9. Account
- Name, email, sign-in method, password change, and delete account.
- Plan: "Free, 3 projects", with usage. Leave a placeholder for a future paid plan.

## Deliverables
- Both mobile and desktop frames for every screen above, and every listed Studio state.
- A small component sheet:
  - buttons (primary, secondary, record)
  - part row (collapsed and expanded)
  - transport
  - sliders (volume, pan, nudge)
  - mute and solo toggles
  - meters
  - modal and sheet
  - toasts
  - empty states
- Color tokens for light and dark, the part-color palette, the type scale and spacing.
- A short note on the reasoning behind the visual direction.

## Out of scope (don't design)
- Video.
- A social feed.
- Public profiles.
- Payments or checkout.
- Native mobile apps (this is a responsive web app).
- The collaboration UI: invitations, roles, comments. Leave room for it in the layouts.

---

# Addendum: harmony templates (learning mode)

Design these screens in the existing Stackapella style: the dark studio, Bricolage Grotesque and
the eight part colours. Show mobile (390 x 844) and desktop (1440 x 900) for each.

Harmony templates are ready-made arrangements of public-domain songs: Amazing Grace in 3 parts, or
a round like "Are You Sleeping". Each part has a **guide**, a synthesized "oo" voice singing that
part. The singer learns a part by listening and singing along, records it, and their take replaces
the guide. Part by part, the arrangement becomes entirely their own voice. The audience is
beginners who want to sing harmony but don't know which notes to sing.

## 1. Templates shelf (on the projects page)
- Sits above "Your projects": a horizontal row of cards on mobile, a grid on desktop.
- Each card shows: title, number of parts, difficulty (Beginner), key, tempo, length, and a small
  stack of the parts' colours.
- Opening a card shows the template details.

## 2. Template details
- Description, credits (words, tune, arrangement), and the parts, each with its vocal range
  (for example "Bass: G2 to D3").
- **Preview:** hear it played, with nothing saved.
- **Check my range:** see screen 6.
- **Start** makes a project. On the free plan it counts toward the 3 projects: show the "2 of 3"
  meter here, and the at-limit state, where Preview still works.

## 3. The studio in learning mode (the main screen)
- **Part rows:** each shows a **Guide** or **Recorded** badge. A guide's waveform is drawn lighter
  until a take replaces it. A **Practice** button puts a part in focus.
- **The focus part** shows its notes as **note bars by pitch**, like a simple piano roll, with the
  note being sung lit up. This is how a beginner sees which notes to sing.
- **The practice bar,** between the parts and the transport:
  - **A stepper:** Listen, then Sing along, then On your own, then Record, with **Next**.
    - Listen: the guide alone, or "with others".
    - Sing along: the guide quieter, with a level slider, and the other parts playing.
    - On your own: the guide muted.
  - **Phrase chips** (Phrase 1, 2, 3...), with **Loop** and **Listen, then repeat**.
  - **Starting note:** a button that plays the part's first note.
  - **Compare,** to switch between your take and the guide.
  - **Record my part.**
- **Lyrics:**
  - the current line large, the next line smaller below it;
  - the current syllable highlighted;
  - on desktop, also a thin lyrics lane under the ruler.
- **"Your turn":** in Listen, then repeat, the app plays a phrase, then it's your turn to sing it
  back, against the other parts. Show a clear "Your turn" moment, with a cue beat before it.
- **Ruler:** section names (Verse 1) as a band, with phrase marks.
- **After recording a part:** a prompt to go to the next part. Show the progress too, for example
  "2 of 3 parts are you".

## 4. States to show
- **Preparing guides:** a short loading state.
- **Practising a phrase on loop.**
- **The "Your turn" moment.**
- **A part just recorded:** Compare is available.
- **All parts recorded:** a celebration moment, with Export.

## 5. Key and speed
- **Key:** the template's key with a minus/plus semitone control, for example "G (+2)".
- **A warning** when changing the key after recording: your takes stay in the old key.
- **Practice speed:** 100%, 85% or 70%, with a note that recorded takes play as guides while
  slowed.

## 6. Range check
- **Sing your lowest comfortable note, then your highest.** A large live note name follows your
  voice (for example "G2"), with a level meter.
- **Result:** your range on a small keyboard or staff, the part that suits you best, and a
  suggested key change, with **Use this key**.

---

[Docs guide](../README.md#a-guide-to-the-docs) · [Next: The learning exploration prompt](design-prompt-explore-learning.md)
