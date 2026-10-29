# Game elements: the 90s arcade moments in game mode

Status: **building**. Landed: the switches, fonts and cues engine; the HUD reskin, READY?, NEW
RECORD, the title card, Home as title screen, the stage clear, versus, the stage map, character
select (all reworked as reskins); the combo bonus and Phrase clear next; then batch 4. The plan was
approved by the coordinator (2026-10-29). Owner: the game agent (worktree `~/Code/stackapella-game`,
branch `agent/game`). The proposal is ~/Code/shots-review/designs/game-elements/ (REPORT.md, the
boards in index.html); the look is the arcade landing's style kit and the Game mode toggle.

Hillel, 2026-10-29: "EVERYTHING!!!! I LOVE IT ALL, take all the time you need and make the
animations awesome as well". His answers: bonus pop-ups go in the HUD strip above the lane ("Yes,
HUD strip"); game sounds are off by default, and Settings lets people turn each element on or off
("let people choose sub-elements of game mode in the settings to customize to suit their tastes");
the app allows light in game mode.

## 1. The rules every element keeps

- **Reskin, don't add** (Hillel, 2026-10-29: "I'd prefer we reskinned these elements to be more
  game like"). Each element restyles the piece a screen already has (the course card as a stage
  card, its bars as power bars, the result's own chips and stars, the Recording pill), keyed to
  its switch through `<html data-game-off>`; overlays and replacements (a title card, a pop-up, the
  versus page, the map) are fine, new stacked rows are not. Before and after shots for each.
- **Game mode only.** Each element checks `game()` and its own switch (section 3). Serious mode
  never shows, plays or announces any of it, and skins-plan's section 3 table stays true.
- **Nothing covers the notes ahead,** the now line or the voice's dot. Pop-ups sit beside the combo
  or float under the recording stage's header; title cards and READY? come before the first gem moves.
- **Silence while the mic listens.** A chip cue plays only while the engine plays nothing
  (`engine.playing` is null): never during a take, a scored lesson turn or a count-in.
- **Honest numbers.** Every count is a real measure: notes lit, on pitch, on time, combo, XP, stars,
  your best and your rank. The combo bonus is a real XP rule (section 6), said as "Combo bonus +N".
- **Motion:** transform and opacity only; each animation is interruptible and a tap (or any key)
  snaps it to its end; nothing waits on it (Next is live from the first frame). Reduced motion
  gives a still, complete screen (final numbers, no slides, bobs or pulses). Nothing a person must
  read blinks; nothing blinks faster than 2.5 a second, and each blink stops after a few.
- **Audio on the AudioContext clock,** never timers. Visual count-ups use requestAnimationFrame.
- **Colour:** the arcade palette's AA pairs in game dark and game light (light HUD labels use the
  darker score yellow #7f5c00, light cyan #076f88, light pink #c81872). The person's accent is used
  where game mode means "you": the 1P cursor, your row on a board, your side of the versus, "You
  are here", Player 1.
- **Controls stay the forms system's** (flat; gloss only on note and star gems).
- **Screen readers:** the HUD's numbers have plain labels and are not live per note; a pop-up is
  announced at most once a phrase; HUD type is never the only way a number is given.
- **Words** through `t()` (`game.*` keys in web/locales/en.json); no em dashes.

## 2. Where it lives

New code is mine, in its own folder, so others' files change by one agreed line each.

| File (new, mine) | What |
|---|---|
| `web/js/game/effects.js` | The switches (section 3): a reactive store on this device, `effect(name)` = game mode and that switch on, `still()` for reduced motion |
| `web/js/game/motion.js` | Shared helpers: a skippable timeline on requestAnimationFrame, count-up numbers, tap-to-skip |
| `web/js/game/chip.js` | The 16-bit cues, made with oscillators on the AudioContext (no files), quiet, and refused while the engine plays |
| `web/js/game/fonts.js` | Loads Bungee and Press Start 2P only while game mode is on (the CSP already allows Google Fonts) |
| `web/js/game/settings.js` | `GameElements`: the plain switch rows for Settings' Game mode section |
| `web/js/game/hud.js` | The Sing HUD strip (YOUR TURN, LIT, COMBO, TIME) with the pop-up slot |
| `web/js/game/popups.js` | The pop-up queue: real events only, one at a time, 1.2 s, none in a take's first 2 s |
| `web/js/game/title-card.js` | The lesson's title card ("Stage 2-3") and READY? |
| `web/js/game/tally.js` | Stage clear: the count-up tally, the star gems' drop, the level bar |
| `web/js/game/record.js` | NEW RECORD with "Best 82 to 87", and the board row that climbs |
| `web/js/game/versus.js` | The challenge's VS screen and Here comes a new challenger |
| `web/js/game/stage-map.js` | The open unit as a Mario World path; the special stage's target |
| `web/js/game/title-screen.js` | Home's Player 1 line, Press start, the marquee bulbs |
| `web/js/game/part-select.js` | Choose your part: the select grid, rating bars, lock-in |
| `web/css/input.css`, the `game-elements:start` to `:end` block | Every `ge-` rule and keyframe, under `[data-skin="game"]` |

Their files, each touched by one mount line or a small hook, agreed with the owner first:

| Their file | Owner | My change |
|---|---|---|
| `web/js/views/settings.js` (Game mode section) | frontend2 (sweeping Settings), frontend | `<GameElements />` under `<GameModeSwitch />` |
| `web/js/components/sing/frame.js`, `web/js/views/sing.js` | frontend | `SingHeader` shows `<GameHud>` in game mode with the HUD on; Sing passes lit, combo and time, and emits pop-up events; READY? in the settle before the count-in |
| `web/js/components/exercise.js`, `web/js/views/learn.js` | frontend | The lesson's HUD, the title card on a lesson's first sing, and the pop-up events |
| `web/js/components/results.js` (`LessonEnd`, `TakeResult`) | frontend | The tally in game mode with celebrations on |
| `web/js/components/boards.js` (`TakeBest`, `PartBoard`) | frontend | NEW RECORD and your row's climb |
| `web/js/views/challenge.js` | frontend | The versus screen |
| `web/js/components/course-map.js` | frontend | The open unit drawn by `stage-map.js` |
| `web/js/components/home.js` | frontend | The title-screen line, Press start, bulbs, Daily training, Song select |
| the part choosers (`sing/sheets.js` `PartSheet`, the song sheet) | frontend | The select grid in game mode |
| `web/js/main.js` | frontend | `import './game/fonts.js'` |
| `web/sw.js` | frontend | The new files in the offline list |
| `web/js/sounds.js` | frontend | Nothing: the chip cues are their own module; the existing reward sounds stay as they are |

## 3. The Settings switches

In Settings' Game mode section, under the Game mode toggle, shown only while game mode is on, as
plain calm rows (the forms system's switch, one line of help each). Kept on this device
(`localStorage` `stackapella.gameEffects`), like the singing settings; every one respects reduced
motion.

| Switch | Covers | Default |
|---|---|---|
| **Game sounds** | The 16-bit cues around takes | **Off** |
| **The HUD** | Sing's and a lesson's HUD strip (off: today's header and combo chip) | On |
| **Bonus pop-ups** | "10 in a row!", "Phrase clear" in the HUD slot; the on-fire trail | On |
| **Title cards and READY?** | The lesson's title card, READY? before the count-in, the CRT boot | On |
| **Celebrations** | Stage clear's tally, NEW RECORD, the item get for badges, the level-up | On |
| **Title screen** | Home's Player 1, Press start, marquee bulbs | On |
| **Stage select** | The course map's path, the special stage | On |
| **Versus screens** | A challenge's VS, Here comes a new challenger | On |
| **Character select** | Choose your part as a select grid | On |

Off, each falls back to today's game-mode screen (still the game skin's look and rewards), never to
serious mode. The rewards themselves (stars, XP, the combo number, a new best as a plain line)
stay: they're information.

## 4. The order of work

Each item lands with: phone (390 x 844) and desktop (1440 x 900) shots in game dark and game light,
a frame strip of its animation and its reduced-motion still, a flow check that serious mode shows
none of it, and a test for its logic.

**Batch 1, the foundations**
1. `effects.js`, `motion.js`, the Settings rows, the `ge-` CSS block and its tokens.
2. The game fonts in the app (`fonts.js`).
3. `chip.js` with its guard (no cues wired yet).

**Batch 2, the top 10's small ones (REPORT.md's 1, 3, 4, 8)**
4. The Sonic HUD on Sing and in lessons, the pop-up slot reserved at its right end.
5. NEW RECORD and the climb (`TakeBest`'s `improved` is the real, board-eligible best).
6. The title card and READY?: once per lesson, 1.2 s, tap to skip; READY? fills the mic's 0.8 s
   settle; the count-in stays "1 2 3 4" in big HUD digits, counting up, with no GO.
7. Home as the title screen: Player 1 and your name, the level line, Press start (5 blinks, then
   rest), the week as bulbs, Daily training, Song select.

**Batch 3, the top 10's bigger ones (2, 5, 6, 7, 9, 10)**
8. The stage-clear tally (under 2.4 s, Next never waits).
9. Bonus pop-ups from real events.
10. Versus and Here comes a new challenger.
11. The stage-select map and the special stage.
12. Choose your part, with Range, Busy, Leaps, Your stars and Fits your voice.
13. The 16-bit cues, wired around takes.

**Batch 4, the rest of the ranked list (11 to 21, then 26)**
4P press start in the studio, the item get for badges, training mode, Continue? (no countdown), the
pause menu, the super meter, marquee bulbs and weeks x3, the instant replay tag, the on-fire trail,
the CRT boot, Here comes a challenger in the studio, and screen shake only on NEW RECORD and a
level-up (3 px, 200 ms, never while singing).

## 5. Not built, and why

- **27 Rank letters (S/A/B/C):** they would argue with the stars, which already rank a take from
  real thresholds. Skipped, as the proposal advised.
- **28 The continue countdown:** pressure and a fake deadline; built instead as Continue? with no
  timer (item 14).
- **29 Attract mode on Home:** something playing by itself where people came to choose. Skipped in
  the app (the landing has it).
- **23 to 25** (the daily challenge, three-letter initials, the ghost of your best take) need
  backend data, and initials need Hillel's call on moderation: later.

## 6. The combo bonus (the multiplier, made honest)

The coordinator (2026-10-29, option A): XP is per exercise, not per note, so there is nothing to
double. Instead, in a lesson's exercise each note lit while a run of 10 or more holds adds 1 XP to
that exercise's award (course.js `xpFor`'s `bonus`), within the daily cap, the base award untouched,
the same in both skins. Game mode's pop-up says "Combo bonus +N", never "2x". It never touches
stars, scores or leaderboards. Sing has no exercise award, so no bonus there; its pop-ups add
"Phrase clear".

## 7. Done means

`web/tool/test.sh` passes; the flows run in both skins (`FLOW_SKINS=game,serious`); the server suite
when `web/locales` changed; the leak and contract checks; shots and frame strips sent to the
coordinator; a `docs/ux-changes.md` line per change (and the native apps told, for 31); decisions
logged.
