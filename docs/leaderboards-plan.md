# Leaderboards for song parts

Status: foundation in progress (backend and landing agent); the rest is planned. Decided by Hillel on
2026-10-28: lean friends and worldwide boards at launch, a sound architecture, as little built now
as possible.

## What a score is

The best score a person has for one part of one song: pitch and timing together, as the app scores
a take. A take counts only when it's sung "On your own" or recorded, in the song's own key, at full
speed, with the guide muted, on a device with a passed timing check and not over Bluetooth.

## Built now

- **part_scores:** one best row per user, template, template version, part and scoring version,
  holding the score, the facts it was sung under (mode, key offset, speed, guide muted, timing
  check, output type), the latency or alignment offset used, the pitch detector version, the
  per-note results (lit, cents off, timing off) as compact JSON, a reference to the take, and a
  flag status.
- **Connections** (a pair of people and how they're connected: a challenge link, a song sung
  together, a group), which friends' boards read.
- **Rules on the server:** qualifying takes only; the worldwide board is 18 and over and opt-in,
  shown by profile name; under-18s never connect through an open challenge link; boards compare only
  within one template version and scoring version; deleting an account, a take behind a score, or
  opting out removes the entry at once.
- **Screens:** "Your best" after a take (or why it didn't count), Friends and Everyone per part on
  the song page with "Be the first on this part", the opt-in, the challenge link, and "Make an
  account to compete" for guests. Admin can remove entries.
- **Blocks:** two people who blocked each other never see each other's entries, on either board.
- **Flag review (2026-10-29):** a new best that's a perfect 100, 25 or more over the singer's own
  best, or 15 or more above the global board's best (once it has 5 entries) is held off the global
  board for 48 hours, except for its singer, unless an admin clears it sooner
  (`admin.boards.flags.list` with a link to the take, `admin.boards.scores.review`); taking it off
  is the existing delete. The digest counts what's waiting. Friends' boards aren't held.
- **Report this name (2026-10-29):** a signed-in viewer reports someone else's entry
  (`reports.create` `boardEntryId`); 2 different people's reports hold it off the global board until
  an admin looks, however long, and it joins the flag list as `reported`. Clearing dismisses the
  reports; taking it off upholds them.
- **Earlier arrangements (2026-10-29):** `boards.show?version=N` shows an earlier version's board,
  read only, while it has entries; `olderVersions` lists them and `current` says which is shown.
- **A friend beat you (2026-10-29):** when a new best goes above a friend's on the same part, where
  they'd matched or led, the friend's bell says so (`board_beaten`, pushed if they want it, never
  email), once a day per friend and part; a friend under 18 shows by first name only.

## Planned for later

- **Rescoring with a better pitch detector.** Every input the scorer used is kept, and a take behind
  a score is kept while the score is (deleting it removes the score). `pitchtrack.js` and
  `scoring.js` stay pure modules, so a future `app:scores:rescore --detector=N` can run them
  headless over those takes and rebuild the boards under the new scoring version.
- Percentage ranks ("Top 15%") instead of large numbers.
- "Our best harmony": the parts' best scores together, for friends who sang a song together.
- The apps, scoring the same way through the shared test vectors.

## Design

The launch screens are lean placeholders on purpose. Hillel wants them redesigned later
(2026-10-28).
