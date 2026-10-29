# Sing: what you hear, a UX review

Status: review only, nothing changed. Written 2026-10-28 for Hillel ("a full UX review of this part
to ensure it's as easy to use as possible").

**Scope:** how a singer controls and understands what they hear while learning and recording a
part. That covers Sing's modes (Listen, Sing along, On your own), Session settings, the guide, the
review after a take and its small mixer, and how all of this connects to the Studio's mute, solo and
level.

**Read from:** `main` at d94f38ba ("the Hear the melody switch stays"), plus the frontend agent's
unlanded work in `~/Code/stackapella-frontend`, marked **(FE)**. That covers bb2fc289 (the melody
under Listen), da1f68d6 (the sidebar gone, the mic level in the header) and the in-progress fix that
makes the review's mutes temporary and has Sing ignore the studio's mutes and solos. Line numbers
are from `main` unless marked (FE). I didn't run the app; this comes from the code, the strings,
`docs/ux-spec.md` and the help screenshots.

## Summary

The decided fix (temporary review mutes, and Sing ignoring the studio's mutes) removes the cause of
the beta report. But the same pattern is still there in four other places: a setting changed in one
place quietly changes what Sing plays later, and the screen doesn't say so.

- Hearing your take in the review saves `withOthers` and `compare` into the project. From then on,
  Listen plays every part, or your own take in place of your guide, and nothing in the app turns
  that back.
- The studio's **Level** still carries into Sing. A part dragged to 0 is silent in Sing.
- **Polished**, switched on in the review, keeps Sing's practice and the recording monitor polished.
- **Record** always silences your guide, whatever mode chip is pressed. The leaderboard then tells
  the singer "Scores count with the guide off."

Second, the words about what you'll hear are often wrong or missing:

- Whole-song Listen says "Hear all 4 parts", but by default it plays your guide and the melody.
- Part-by-part Listen says "on its own", but the melody now plays under it.
- The Notes view, a phone while playing and the recording stage say nothing about what plays.

Third, with the sidebar gone and the studio's mutes no longer reaching Sing, Sing has no control for
the other parts at all. The only hearing controls left are the mode chips, plus the Hear the melody
and Chords switches inside Session settings. Whether to add a per-visit "What you hear" control is
the one real product decision here.

---

## Findings, most important first

### 1. Hearing your take in the review changes Listen for good (a dead end like the beta bug)

- **What the singer experiences:** they record and tap "Hear it with the others" (or Play phrase N,
  or Re-sing's "Hear it in place"). Later, in Listen, they no longer hear their part on its own.
  They hear every part, and until they pick a part again they hear **their own take** where the
  guide should be. It's saved with the project, so it survives leaving and coming back. Nothing on
  the screen says so, and no control turns it back. The coach still says "Hear the Alto on its own".
- **Where:**
  - `web/js/views/sing.js:1019-1020`, `:1058-1059` and `:1074-1075` call
    `setPractice('withOthers', true)` and `setPractice('compare', true)`, which are saved.
  - `compare` is reset only by `practise()` (`web/js/stores/session.js:603`). `withOthers` is never
    set back to false anywhere in `web/js`.
  - `nextPart()` (`sing.js:1083`) calls `practise()` only when there's a next part. So after the last
    part, or after leaving the review by Back or "Open in the studio", both flags stay set.
  - Levels: `web/js/practice.js:18` and `:77-86`.
- **Why it hurts:** the screen says one thing and the speakers do another, with no way out. That's
  exactly how the beta singer got stuck.
- **Recommended change:** treat the review's listening like the review's mutes. Keep a review-only
  state in the session (for example `session.reviewing`, with others on and your take in place of
  your guide), read it in `levels()`, and clear it wherever `reviewMuted` is cleared (FE). The
  review should never write to `practice`. When a project opens, reset `compare` to false and drop
  any stored `withOthers` (see finding 2 for what Listen should play).
- **Size:** S. Quick fix.

### 2. The words about what you'll hear are wrong or missing

- **What the singer experiences:**
  - **Whole song, Listen:** the stage says "Line 1 of 4 · Hear all 4 parts. Your line is
    highlighted." By default (`withOthers: false`) it actually plays only your guide, and now the
    melody. The words are right only after finding 1's leak.
  - **Part by part, Listen:** "Hear the Alto on its own, a phrase at a time", but the melody now
    plays softly under it (FE bb2fc289).
  - **Notes view:** the line hint is drawn only by the Stage (words or staff), so it's missing here.
  - **A phone while playing:** the mode chips are hidden, and the header's "· Listening" is hidden
    under the `sm` breakpoint. Nothing says what's playing.
  - **Record:** nothing says your guide will go off (see finding 4).
- **Where:**
  - `web/locales/en.json:1913` (`sing.lineHint.listen`) and `:1693` (`sing.coach.hint.listen`).
  - `sing.js:141` (`line-hint` goes to the Stage only) and `sing.js:96` (the ModeBar is
    `hidden md:flex` while playing).
  - `web/js/components/sing/frame.js:81` (the chip is `hidden sm:inline`).
- **Why it hurts:** this is how a singer tells, before pressing Play, what they're about to hear.
  When it's wrong, they stop trusting the controls, and a real bug looks the same as the normal
  behaviour.
- **Recommended change:** one "You'll hear" line, worked out from the same `levels()` map the engine
  plays (so the words can't drift from the sound). Show it under the mode chips in every view, on
  the coach card and on the recording stage. Suggested words:

  | When | Line |
  |---|---|
  | Listen, whole song | You'll hear every part. Yours is highlighted. |
  | Listen, part by part | You'll hear the Alto, with the melody softly underneath. (Melody off: You'll hear the Alto on its own.) |
  | Sing along | You'll hear every part, with your guide soft. |
  | On your own | You'll hear the others. Your guide is off. |
  | Recording | You'll hear the others. Your guide is off while you record. |
  | Any part off (finding 3) | ... Soprano is off. |

- **Decision for Hillel:** what should whole-song Listen play?
  - (a) Every part, with your line highlighted. This matches the 98.png words, the chooser's "Other
    parts play as backing" and the spec (`ux-spec.md:411`).
  - (b) Your guide with the melody under it, which is what the code does by default today.

  **Recommend (a)** for the whole song, and keep part-by-part Listen as your part with the melody
  under it. Then `withOthers` can go: it follows from the way you sing (the whole song: on; part by
  part: off), which also removes finding 1's leak.
- **Size:** S for the derived rule and the words. M if the line is built from `levels()`.

### 3. Sing has no way to turn a part down or off, and no view of what's playing

- **What the singer experiences:** after the in-progress fix, Sing ignores the studio's mutes and
  solos (FE `session.js` `levels()`), and the review's mutes last only for the review. The desktop
  parts sidebar showed every part, its note and whether it was heard. It was removed today (FE
  da1f68d6), and it was the last view of the parts inside Sing. A choir singer who wants "just me
  and the bass", or "the soprano is drowning me out", now has no control anywhere that reaches Sing.
- **Where:** `sing.js` (FE) has no parts list. Session settings' Practice section (FE
  `web/js/components/sing/sheets.js`) has Speed, Hear the melody, Chords and Guide voices, and
  nothing for the other parts. Your own guide's level in Sing along is fixed at 35%
  (`practice.js:19`, `guideLevel`), with no control.
- **Why it hurts:** the beta bug was fixed by taking control away. The need behind it (to hear less
  of a part) is still real, and the next request will be "how do I turn the soprano down?"
- **Options:**
  - **A. Nothing.** Sing always plays the whole arrangement. This is the simplest, but it loses a
    real use, and the studio no longer helps.
  - **B. A "What you hear" group in Session settings (Practice).** It holds your guide's level (Soft,
    Medium, Loud; `guideLevel` already exists), Hear the melody, and an on/off switch for each other
    part. It's kept for this visit only and cleared when you leave Sing, like the review's mutes. It
    shows as a badge on the Settings button ("1 part off"), in the "You'll hear" line, and on the
    recording stage.
  - **C. Per-part chips on the Sing screen** (98.png's M and S). This is the most direct, but it's
    crowded on a phone and brings back the clutter Hillel removed today.
- **Recommend B.** If a part is switched off in B, it should stay off while recording: the recording
  stage shows it, and it clears when you leave Sing. The beta bug came from mutes that were both
  saved and invisible, and B is neither.
- **Size:** M. **Decision for Hillel.**

### 4. Record ignores the mode chips without saying so, and the leaderboard then blames the guide

- **What the singer experiences:**
  - They practise in Sing along and tap Record. "Sing along" stays pressed above the button, but
    from the count-in their guide is silent and every other part plays. The recording stage
    (`learn-a-part--recording.png`) doesn't say this.
  - Then the leaderboard line under the score can say **"Scores count with the guide off."** about a
    take where the guide *was* off.
- **Where:**
  - `practice.js:72-73`: the part being recorded is silent, both its guide and its take.
  - `practice.js:86`: every other part plays while recording.
  - `practice.js:90`: the chords play too, if they're on.
  - `sing.js:116` passes `:guide-muted="practice.mode === 'own'"` to TakeBest, so a take recorded
    from Listen or Sing along is sent with `guideMuted: false`.
  - `en.json:4576` is the "Scores count with the guide off." line.
- **Why it hurts:** the guide the singer leant on is gone at the moment that counts, with no warning,
  and then the app tells them off for something that didn't happen.
- **Recommended change (quick):**
  - Send `guideMuted: true` for every take recorded in Sing, since the guide is always off while
    recording.
  - Say it before the count-in: "Your guide is off while you record" under Record, or in finding 2's
    line.
  - On the recording stage, next to "Alto · Take 1", add who you're hearing: "with Soprano, Tenor
    and Bass".
- **Decision for Hillel:** should Sing along keep a soft guide while recording?
  - (a) Always off, as today. Scores are fair and nothing extra leaks onto speakers.
  - (b) Follow the chip, and count the take as practice, not for the boards.

  **Recommend (a)**, and say so.
- **Size:** S.

### 5. A phone can't change what it hears mid-song, and On your own has no way back to the guide

- **What the singer experiences:**
  - On a phone, the mode chips disappear while playing. To switch, they pause, switch and start
    again.
  - In whole-song On your own, the chips disappear while playing at every width, because the "Your
    turn" band replaces the header. There's no "Guide on" button: only Pause, or M on a keyboard.
    Part by part does have "Guide on".
- **Where:**
  - `sing.js:96`: `v-if="... !bandShown ..."` and `:class="playing || coaching ? 'hidden md:flex'"`.
  - `sing.js:285` (`bandShown`).
  - `frame.js` Transport: Guide on shows only for `turn`, which is part by part (`sing.js` passes
    `way === 'parts' && phase === 'turn'`).
- **Why it hurts:** "I'm lost, give me my guide back" is the most common change a learner needs, and
  they need it in the middle of a phrase.
- **Recommended change:**
  - Give the whole-song turn band the same "Guide on" button as part by part. It switches to Sing
    along without stopping.
  - On a phone, keep the mode chips visible while playing (they're one row), or show the current
    mode as a tappable chip beside Play.
- **Size:** S. Quick fix.

### 6. "Hear the melody" is hidden, doesn't name the part, and does less than it says

- **What the singer experiences:**
  - The switch (kept today, d94f38ba) works only in Listen. In Sing along, On your own and recording,
    the melody plays at full level whatever the switch says. A singer who turns it off and then sings
    along still hears the tune.
  - It says "the melody", while every part list says "Soprano" (the melody is a template part with
    its own name), so many won't know which line it means.
  - When it's off, nothing outside the sheet shows it. The Settings badge shows only the speed.
- **Where:**
  - FE `practice.js`: the melody branch applies only when `mode === 'listen' && !withOthers`.
  - FE `sheets.js` (the Switch) and `en.json` (FE) `sing.settings.melodyHint`: "The tune plays
    softly under your harmony part, so you hear the line you sing against."
  - `sing.js` `settingsBadge` (speed only).
- **Why it hurts:** a switch that seems not to work teaches singers that the controls don't work.
- **Recommended change (quick):**
  - Name the part: "Hear the melody (Soprano)".
  - Scope the hint: "When you listen to your part, the Soprano plays softly under it."
  - Show "Melody off" in the badge or in finding 2's line.
  - If finding 3's option B is chosen, this switch becomes the melody's row there.
- **Size:** S.

### 7. The studio still reaches into Sing through Level, and Sing never says the studio mix stays behind

- **What the singer experiences:**
  - In the studio, they drag a part's level down to 0 to hear the others better. In Sing, that part
    is silent, with no sign why. It's the beta bug again, through a slider.
  - The reverse case: someone who muted a part in the studio opens Sing, hears it again, and doesn't
    know why.
- **Where:**
  - The fix overrides only `muted` and `solo`: FE `session.js` `levels()`,
    `slots.map((p) => ({ ...p, muted: ..., solo: false }))`. `volume` still carries.
  - The level slider goes to 0: `web/js/components/studio.js:115` and `web/js/views/studio.js:174`.
  - The spec still says the opposite of the fix: "Mute, solo and level carry into Sing"
    (`docs/ux-spec.md:1240`).
- **Recommended change:** Sing plays every part at the arrangement's level.
- **Decision for Hillel:**
  - (a) Ignore the studio's level in Sing too. That's one rule: the studio mix is for the song, and
    Sing plays the arrangement.
  - (b) Keep the studio's level, to balance a loud take against a soft one, and show it in Sing.

  **Recommend (a).** Also show a one-time toast when Sing opens on a song with studio mutes: "Sing
  plays every part. Your studio mutes stay in the studio." Update `ux-spec.md:1240` and `:411`.
- **Size:** S.

### 8. Speakers without headphones: everything plays at full into the mic, and the warning comes after

- **What the singer experiences:**
  - They answer "No headphones" or "Not now", and recording plays every part (and the chords, if
    they're on) at full level through the speakers.
  - After the take: "We could hear the other parts through your mic, so these stars may not be
    fair...". The review has no stars, by design.
- **Where:**
  - `web/js/record-setup.js:47` (the headphones question).
  - `practice.js:86` and `:90` (what plays while recording).
  - `en.json:2618` (`report.bleed`).
  - `ux-spec.md` 5.8b ("No stars on this screen").
- **Why it hurts:** a choir singer on a laptop is the most likely to use speakers, and they find out
  only after singing the whole part.
- **Recommended change (quick):** "We could hear the other parts through your mic, so this score may
  not be fair, and this take doesn't count against you. Headphones give you a fair score."
- **Decision for Hillel:** what plays while recording on speakers?
  - (a) The same as today.
  - (b) "No headphones" plays the others at about half level, with no chords, while recording, and
    the recording stage says "No headphones: the others play softly".
  - (c) Record against the melody only.

  **Recommend (b).** It matches what exercises already do without headphones ("the sound underneath
  rests while you sing", `ux-spec.md` 5.2).
- **Size:** S for the words, M for (b).

### 9. The review's mixer doesn't say its mutes are temporary, and its toggles are hard to read and to tap

- **What the singer experiences:**
  - After the fix, the review's mutes clear when you record or leave. The hint still says only "Tap
    a part to mute it", so someone who muted the Soprano and taps Record again can't tell whether it
    will still be off.
  - A part that's off is shown only by fading (no word), unlike the studio's "Muted".
  - A screen reader hears "Bring Soprano back, toggle button, pressed", which reads as a double
    negative, because the label flips and `aria-pressed` flips too.
  - The buttons are about 30 px tall.
- **Where:**
  - `web/js/components/sing/recorded.js:122`: `opacity-45`, the flipping `aria-label` with
    `aria-pressed`, and `py-1.5 text-xs` with no `tap-44`.
  - `recorded.js:128` and `en.json:1861` (the hint).
  - `recorded.js:111`: the Play bubble flips its label *and* has `aria-pressed`.
- **Recommended change:**
  - Hint: "Tap a part to turn it off while you listen. Everyone plays again when you record."
  - Rows that are off say "Off" in words, with a struck-through speaker icon.
  - Use a stable label ("Mute Soprano") with `aria-pressed`, and add `tap-44`.
  - The Play bubble should either keep one label with `aria-pressed`, or flip its label without
    `aria-pressed`, not both.
- **Size:** S. Quick fix.

### 10. Polished, switched on in the review, keeps changing what Sing plays

- **What the singer experiences:** they try the review's Polished switch. From then on, on this
  device, Sing's practice and the recording monitor play takes levelled, compressed, in a room, and
  spread left and right. The spread can push a part toward one ear. Nothing in Sing shows Polished
  is on.
- **Where:**
  - `recorded.js:116` sets `prefs.hearPolished`, a device-wide setting (`web/js/stores/prefs.js:13`).
  - The effects: `session.js` `engineSamples`, `applyRoom` and the pan spread in `voiceMix`.
- **Recommended change:** Sing's practice and recording always play dry. Polished applies to the
  review, the studio and sharing.
- **Size:** S. A small product call; **recommend dry**.

### 11. The same thing has different names in Sing, the review and the studio

| What | Sing | Review | Studio | Recommend |
|---|---|---|---|---|
| Silencing a part | "Your guide is off", "Guide on", "Guide back on", shortcut "M Mute guide" | "Tap a part to mute it", "Mute {part}", "Bring {part} back" | M, S, "Muted", "Not soloed" | Sing and the review say **off and on** ("Soprano off", "Turn off"). Mute and Solo stay studio words. |
| The other parts | "the others", "other parts", "Other parts play as backing" (`en.json:1672`) | "the others", "the other parts" | the part names | "the others". Keep "backing" for the chords only (`sing.chords.hint` already says "Soft backing"). |
| The melody | "Hear the melody", "The tune plays softly..." | (none) | the part's name | "the melody (Soprano)". Pick one of melody or tune. |
| Your guide | "your guide" | (none) | the "Guide" tag on any unrecorded part | Fine as is: "your guide" is yours, and "Guide" is a part's source. |
| The M shortcut | "Mute guide" | (none) | (none) | It switches between On your own and Sing along (`sing.js:804`). Call it "Guide off or on". |

- **Size:** S. These are word changes, made along with findings 2, 6 and 9.

### 12. Accessibility and phone details

- The studio's S and M are 32 px (`web/js/components/studio.js:109-110`). They're now the only
  per-part hearing controls, so add `tap-44` on a phone.
- When finding 2's "You'll hear" line changes, say it once in Sing's existing polite live region
  (`sing.js` `said`). On the recording stage, announce "Recording. Your guide is off." once at the
  count-in.
- The mode chips use `aria-pressed` correctly (`frame.js` ModeBar). Keep them.
- **Size:** S.

### 13. The docs and help pictures describe controls that are gone

- `ux-spec.md:1240` ("Mute, solo and level carry into Sing").
- `ux-spec.md:411`: "Hear all 4 parts" and "the parts' M and S ... live in Mix".
- `ux-spec.md` 5.10: "a wide screen keeps the parts strip". It was removed today.
- `web/icons/help/learn-a-part--stage.png` (and its dark twin) still shows the S A T B circles with
  "Guide" and "You", which are gone. That help article is many beta singers' first explanation.
- **Recommended change:** update the spec with the fix, and retake the stage screenshots once it
  lands.
- **Size:** S.

---

## What I'd ship first

1. **Stop the review from changing Listen** (finding 1). The review's listening becomes review-only,
   and the stored `withOthers` and `compare` are cleared on open. This removes the last saved,
   invisible change to what Sing plays.
2. **One accurate "You'll hear" line**, including on Record and the recording stage, plus
   `guideMuted: true` for Sing takes (findings 2 and 4). Hillel picks whole-song Listen (recommend:
   every part).
3. **Sing ignores the studio's Level too**, with a one-time note that the studio mix stays in the
   studio, and the spec updated (finding 7).
4. **"Guide on" in the whole-song turn band, and the mode chips visible on a phone while playing**
   (finding 5).
5. **The review mixer says its mutes are temporary**, shows "Off" in words, and gets fixed labels and
   tap targets (finding 9). Name the melody's part and scope the Hear the melody hint (finding 6).

Next, once Hillel decides: the per-visit **What you hear** group in Session settings (finding 3,
option B). It brings back control over the other parts without the hidden, saved state that caused
the beta report.
