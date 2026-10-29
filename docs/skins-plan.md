# Two skins for one app: Game and Serious

Status: **plan, waiting for Hillel's OK on the feature list (section 3)**. Owner: the frontend
agent (the app), with the marketing agent (the site) and the backend (one synced field).

Hillel (2026-10-29): "the entire system should work in either game mode or serious mode"; "it can
be part of the theme toggle: game mode on/off"; "two skins for one app"; "we move all current
'game' features (ie XP) to game mode". This plan replaces the arcade style pass: the arcade look
becomes the **game** skin (section 4, from the arcade mockup), and today's calm look is the
**serious** skin.

## 1. The rule

- **One app.** Features, layouts, flows and data are the same in both skins. A skin changes how
  things look and which game rewards show, never what you can do.
- **Game** (the default): the arcade mockup's look and every game reward (XP, stars, badges,
  combos, leaderboards...). Within game mode, the arcade-versus-calm rule still holds: arcade where
  people play (singing, scores, stars and XP, the course, leaderboards), calm where they read or
  decide (settings, billing, sign-in, legal, help, long forms, errors).
- **Serious**: today's calm look, as a plain practice tool. It shows the measures (on pitch %, in
  time %, cents) instead of the rewards.
- **Nothing is lost.** Progress is recorded the same way in both modes: XP is still earned, stars
  are still worked out, badges are still awarded, and scores still reach the boards. Switching back
  to game mode shows everything earned.
- **Nothing depends on the skin.** Unlocking, the next lesson and the workout's choices work the
  same in both modes (section 3).
- **The glossy note gems stay in both skins.** They're the app's one glossy thing.
- Both skins meet AA contrast and respect reduced motion. The flows and screenshot checks run in
  both (section 5).

## 2. The skin system

- **Where it's set.** `<html data-skin="game|serious">`, next to `data-theme`, is set before paint
  by `web/js/theme-boot.js`. (`data-mode` would clash with light/dark; this is agreed with
  marketing.)
- **Where it's kept.** `skin` is one more field in theme-boot's choice (`{ mode, colour, accent,
  skin, at }`). It rides the same localStorage and the shared `sa_theme` cookie, so the app and the
  site agree on a device. The default is `game`.
- **How it changes.** `window.stackapellaTheme.choose({ skin })` is the one way, on any page. The
  site's toggle calls it too.
- **Synced with the account,** like the theme (`adoptAccountAppearance`): account.json's
  appearance gains `skin`. This is an additive contract change for the backend.
- **The switch.** "Game mode", on or off, in the theme sheet (How it looks, `ThemePanel`) and in
  Settings > How it looks, with one line: "Off: a plain practice tool, with no XP, stars or
  badges. Nothing you've earned is lost."
- **In code:**
  - `skin()` and `game()` live in `stores/appearance.js` (reactive).
  - A `<GameOnly>` wrapper shows its slot only in game mode.
  - Skin-aware words come through `sk(key)`, which reads `<key>.game` in game mode and the plain key
    otherwise ("Stage select" or "Course map", "High scores" or "Leaderboard", "Player 1" or your
    name). It falls back to serious, so a missing game word never shows a key.
- **In CSS:**
  - `[data-skin="game"]` overrides the tokens: fonts, the action/info/score colours from the
    mockup's AA table, the effects layer (glow, scanlines on screens, the HUD).
  - The serious skin is today's tokens, unchanged.
  - Light and dark work in both in the app. Theme families (Warm, Midnight, Graphite, Stage) apply
    in both, and the game skin tints them. Game mode keeps its own light or dark (`gameMode` in the
    theme choice), dark until the person picks light in it, and serious mode keeps its own, so each
    skin comes back as it was left (Hillel, 2026-10-29: "auto set to dark but show toggle to return
    to light"). The public site's game look is dark only: theme-boot.js allows light in game only
    on a page with `<meta name="stackapella-app">` (Hillel: "the landing page with game mode on can
    always be dark mode, but the app should support both").
- **Fonts (game only).** Bungee for headings and Press Start 2P for tiny HUD labels (scores, XP,
  combo, stage numbers; never sentences). Figtree stays for everything people read.
  - They load only in game mode, from Google Fonts. This is a new dependency, so it needs Hillel's
    OK (CLAUDE.md), and the CSP must allow it.
  - Self-hosting the two files is the alternative.
- **The embedded lane** (`js/embed/lane.js`) follows the page's skin through `restyle()`. The
  landing uses the same toggle.

## 3. Every game feature, and what serious mode shows instead

This inventory was swept from the code on 2026-10-29. ✅ means my recommendation; Hillel decides.

| # | Feature (where) | Game mode | Serious mode | Recommendation |
|---|---|---|---|---|
| 1 | **Stars** on takes and lessons (`StarDiscs` in results.js; `starsFor`, course.js; `stars()`, scoring.js) | As now | The plain measures: "82% on pitch", "lit 7 of 9", "on time 90%" (scoring.js already has percent, lit, timingPercent and cents) | ✅ Still worked out and stored; only hidden |
| 2 | **Stars open the units** (`unitOpen`, course.js: 2/3 of the unit's stars) | "4/6 ★ to open" | The same rule, in measures: "Finish the lessons above, most of them at about 70% on pitch, to open this" | ✅ Keep the rule. Stars come only from percent on pitch (70 and 90%), finishing and hearing, so the gate rests on the same measures in both skins and only its words change |
| 3 | **Stars pick the next lesson** (`nextLesson`) and Home's unit progress | As now | The same; "done" shows a tick instead of stars | ✅ Unchanged logic |
| 4 | **Stars pick the workout's review** (`pickWorkout`: fewest stars of 1 or 2) | As now | The same | ✅ Unchanged |
| 5 | **XP and levels** (`LevelBar`, `CourseStats`, the Learn hub's level card, LessonEnd's "+N XP" and level-up, TakeResult's XP tile, the workout end, Your voice header) | As now | Hidden | ✅ Still earned |
| 6 | **Badges** (badges.js, 26; "New badge" toasts and the `special` sound; Your voice's badge list) | As now | Hidden: no toasts, no list | ✅ Still awarded silently |
| 7 | **Weekly goal ring and weeks-in-a-row** (`GoalRing`, `WeekCard`, the Learn hub) | As now | A plain "Practised this week" calendar: the 7 day bars and "3 days this week". No ring, no goal fraction, no streak count | ✅ The goal setting stays (it decides when a day counts) |
| 8 | **Day streak chip** (LessonEnd "4-day streak") | As now | Hidden | ✅ |
| 9 | **Combos** ("5 in a row" in exercise.js, sing.js, wait for me, the wave lane's "even waves" glow) | As now | Hidden | ✅ bestCombo is still tallied (for the badges) |
| 10 | **Praise pop-ups** ("Nice!", "Perfect!" cheer-pop) | As now | Hidden | ✅ The lane's tick on a hit note stays: it's information, not praise |
| 11 | **Level words** ("Brilliant!", "Great!", "Keep going!", "Play again for more stars") | As now | The measure ("88% on pitch") and "Sing it again" | ✅ |
| 12 | **Celebrations**: confetti (celebrate.js), star gem pop and glint, note-hit sparkles (glass.js), the step-done tick animation | As now | Hidden. The step-done tick stays, still | ✅ The gems keep their gloss; only the sparkle burst goes |
| 13 | **Reward sounds** (stars1/2/3 chimes, `special`) | As now | Off. micReady stays (feedback) | ✅ |
| 14 | **Leaderboards** (`TakeBest` after a take, `PartBoard` on the song page, the worldwide opt-in) | As now | Hidden | ✅ Scores still go up (they count once you switch). Report a name stays reachable from the song page's ⋯ |
| 15 | **Challenge a friend** (TakeBest's Challenge; /challenge/<token>) | As now | The Challenge button is hidden. An incoming challenge link opens as "Sam sent you a song: Amazing Grace" and starts it, with no score to beat | ✅ |
| 16 | **Personal bests, "since last time"** (report.js) | As now | Kept: they're measures | ✅ Both modes |
| 17 | **Share card** (result-card.js: stars, "Brilliant!", "Can you match it?") | As now | "82% on pitch" with no stars and no dare | ✅ |
| 18 | **Bullseye** (a dart board around cents) | As now | Kept as a target, labelled in cents ("within 10 cents"), with no "gold" words | ✅ It's a practice tool |
| 19 | **The bonus pop-ups, multipliers and stage clear** (docs/game-feel-plan.md, after launch) | Game only, behind a "Game effects" switch | Never | ✅ |
| 20 | **Plus courses' game bits** (the wave lane's glow, scorer words) | As the free course | Measures only, the same as rows 9 to 11 | ✅ The courses agents use `game()` |

The site claims only what each skin shows: serious copy never mentions XP, stars, badges or
boards.

## 4. The game skin, screen by screen (the arcade mockup's "App screens")

From ~/Code/shots-review/designs/arcade-landing/ (NOTES.md, the style kit, the six App screens)
and the arcade-versus-calm rule. Built only after the system (section 2) lands with the serious
skin unchanged.

- **Home:** calm cards to read, with a HUD line for level, XP and the week ("LV 3 · 40 XP ·
  ●●○").
- **Sing:** the header becomes a HUD ("YOUR TURN", the song and part, the combo). The lane sits in
  the cabinet screen (bezel, scanlines). The controls stay flat.
- **Course map:** "Stage select". Units are stages with their stars, "You are here", lesson nodes,
  and Plus units as bonus stages.
- **Lesson result:** the one celebration screen: star gems, "Great!", "+20 XP", the level bar.
- **Leaderboards:** "High scores", ranks in the HUD font, Friends and Everyone, Challenge a friend
  in the footer.
- **Calm in game mode too:** Settings, Plan and billing, sign-in, legal, help, long forms and
  errors stay in the serious look. Only the fonts' headings may change.
- **Room for later** (docs/game-feel-plan.md, after launch):
  - Bonus pop-ups ("2x!", "Stage clear") sit at the lane's top right, above the now line, never
    over the gems or the dot.
  - Settings > How it looks gets a "Game effects" switch under Game mode (pop-ups, multipliers
    and sounds), on by default in game mode.

## 5. Order of work

1. **The system, serious unchanged.**
   - theme-boot's `skin` and `data-skin`, with its tests (agreed with marketing).
   - The appearance store and the switch in the theme sheet and Settings.
   - `game()`, `<GameOnly>` and `sk()`.
   - The backend's appearance `skin` field (contract first).
   - Flows: `FLOW_SKINS=game,serious` runs any flow in both, and the screenshot checks do the same.
2. **Serious mode hides the game features** (section 3, once Hillel OKs it), one row at a time.
   Each row gets a flow check in both skins.
3. **The game skin's look** (section 4), screen by screen, with before and after shots in both
   skins, light and dark, phone and desktop, and the AA check.
4. **After launch:** game-feel (docs/game-feel-plan.md).

## Decisions to log when made

- The default skin (Hillel: game).
- The list in section 3.
- The fonts' source (Google Fonts or self-hosted).
