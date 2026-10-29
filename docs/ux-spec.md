# The UX specification: one product on the web and in the apps

**Status:** written 2026-10-27 from what's decided and built. It's the shared UX contract for the
web app (`/web`, the Vue PWA) and the Flutter apps (`~/Code/stackapella-mobile`: iOS, Android,
macOS, Windows, Linux). The API is `contract/`; the architecture is `docs/architecture.md`; this
doc is how the product looks, behaves, reads and feels.

**How to read it.** Each rule names its source in brackets so it can be checked:

| Short form | Means |
|---|---|
| [D date "title"] | `docs/decisions.md`, the section with that date and title |
| [FE date] | `docs/decisions.md`, "Decided by the agents (delegated)", the frontend agent's entry of that date |
| [V], [C] | `docs/vision.md`, `docs/conventions.md` |
| [SUX], [VC], [TC], [SB], [YT], [BS] | the singing UX, vocal coaching, template creator, song builder, your tune and beta singers plans in `docs/` |
| [M:file] | a doc in the mobile repo (`~/Code/stackapella-mobile/docs/`) |
| a path | the web's implementation, the reference today |

A rule with no marker is built on the web. **(Decided, not built)** means decided but not yet on
the web. **(Proposed)** means this spec's own call where nothing was decided; each one is listed
again in section 11 for Hillel.

Where a row says **Web** and **Apps**, they differ on purpose. Everywhere else, both do exactly
what the rule says.

---

## 1. Principles

| Principle | What it means on screen | Source |
|---|---|---|
| **Beginners first** | The home leads with the course. Every screen is polished for someone who doesn't read music. Music theory (Roman numerals, chord letters, cents) sits under "Advanced" or in the details, never in the way. | [D 2026-10-26 "the vision and the roadmap"], [D 2026-10-26 "the home page"], [TC] |
| **Sing within a minute** | Nothing between a tap and singing: no account, no questions, no name. New song opens the studio at once; Start singing opens lesson 1 with only the mic permission. | [V principle 2], [D 2026-10-26 "new projects start at once"], [FE 2026-10-26 first Start] |
| **Hear it immediately** | Every choice that changes sound plays at once: a trim, a voice, a progression, a key, a chorus suggestion. Beginners judge by ear before they commit. | [SB "Rules it keeps"], [FE 2026-10-27 Trim sheet], [D 2026-10-26 "the template creator"] |
| **One step at a time** | One clear action per step, with the next step's button; everything else waits (the Coach, the creator's wizard, the song builder). An escape hatch is always there ("Skip to record", "Choose another part"). | [SUX "Coach steps", "Escape hatches"], [TC "The flow"] |
| **Judge by ear** | Suggestions are auditioned in context; scores are words first, numbers second. | [SB], [VC "UI/UX: what changed"] |
| **Honest and kind** | Never red, never 0%, never "wrong". What went well first, then one thing to try. "Couldn't hear you" earns and costs nothing. Honest limits beat guesses (head voice is explained, never detected). | [V principles 3 and 4], [VC "The rules the game keeps"], `web/js/scoring.js` |
| **No dark patterns** | No pop-ups on opening, no countdowns, no upgrade prompt while singing, no "you lost your streak", "Not now" is always neutral, cancelling is online and plain, deleting the account is in Settings. | [FE 2026-10-27 upgrade moments], [FE 2026-10-27 Plus page], [VC] |
| **No analytics** | Nothing tracks people. Counts come from our own records. "How did you hear about Stackapella?" is asked once, with Skip. | [C "Never"], [FE 2026-10-26 heard question] |
| **The device first** | It works offline and installed; singing is analysed on the device; audio leaves only when the person saves, shares, sends to a coach or submits a beta take. | [V principle 5] |
| **Private by default** | Nothing public without a clear choice and the consent of everyone who sang on it. | [V principle 6] |
| **Parity** | A feature behaves the same everywhere: the same steps, words, numbers, colours and states. A platform convention is used only where it helps (the share sheet, the back gesture, system text size) and never changes what a feature does. | Hillel's ask for this doc; [D 2026-10-27 "native mobile apps"] |

**Apps:** the mobile CLAUDE.md says "the web's screens are a guide, not a spec". This doc is the
spec for behaviour, words and tokens; screen layouts may still be refined per platform within it.

---

## 2. Design tokens and theming

### 2.1 Colour tokens

Screens use tokens only, never a colour literal. The values live in `web/tool/palettes.json`,
generated into `web/css/themes.css` and `web/css/accents.css` by `web/tool/themes.py` and
`accents.py` [FE 2026-10-27 themes generated]. **Apps:** generated from the same CSS into
`lib/app/theme/palettes.g.dart` by `tool/make_theme.py`; screens take `context.stack` or the
Material theme, never a literal [M:decisions.md "the skeleton: look and words"].

The names are historical: `ink` is the page background and `cream` the main text in every mode,
so in a light theme `ink` is light and `cream` is dark.

| Token | Role | Use it for | Never for |
|---|---|---|---|
| `ink` | Page background | The page; text on a `cream` fill | Text on the page |
| `panel` | A surface | Cards, sheets, the studio and Sing surfaces | |
| `raised` | A surface above a surface | Hover fills, chips, toasts, notices, inactive lesson nodes | |
| `line` | A boundary that must be seen (3:1) | Field borders, dividers, unselected chips and toggles | Card outlines |
| `edge` | A quiet hairline | Card, sheet and popover outlines (`line` mixed toward `panel`: 45% in dark, 80% in light) | Field borders |
| `cream` | Main text | Text, the primary button's fill, the Play button | |
| `muted` | Secondary text | Hints, captions, icons at rest | Anything that must pass 4.5:1 at small sizes on `raised` without checking |
| `accent`, `accent-strong`, `accent-ink` | The person's accent | Link underlines, the focus ring, selected choices (accent border plus accent-tinted fill), the eyebrow, `chip-accent`; `-strong` on hover; `-ink` for words on an accent fill | Primary buttons (they stay `cream`), part colours |
| `good`, `warn`, `bad` | Status | Status chips, notices and field errors, always with an icon and words | Decoration |
| `record`, `record-label` | Recording | The record button, the recording frame and pill, danger buttons | Anything else |
| `part-1` to `part-8` | A part's colour | Its letter circle, waveform, lane bars, your turn, "You" | Text on the page |
| `part-text-N` | Words on a filled part colour | The letter in a filled circle, words on your turn's band | |
| `part-ink-N` | Small words in a part's colour on the page | "Voice 2", "Alto sings G4 on 'grace'" | |

Sources: `web/css/input.css` `@theme`, [FE 2026-10-27 polish pass, first part], [FE 2026-10-27
polish pass, second part], [FE 2026-10-27 refined theme picker].

### 2.2 Light, Dark and Auto; colours; accents

| Choice | Options | Default |
|---|---|---|
| **Mode** | Light, Dark, Auto (follows the device, live) | **Dark** until chosen |
| **Colour** | Four, each with a light and a dark version (eight palettes), **named by mode** (Hillel, 2026-10-27): Stage is "Stage" in dark and "Spotlight" in light; Warm is "Ember" and "Warm"; Midnight is "Midnight" and "Sky"; Graphite is "Graphite" and "Paper". The ids (`stage`, `warm`, `midnight`, `graphite`) don't change. Plum is gone: a stored `plum` shows as the mode's own colour | Unchosen: **Stage in dark, Warm in light**, worked out from the mode showing, so Auto switches both |
| **Accent** | The theme's own, Coral, Amber, Green, Teal, Sky, Indigo, Violet, Rose, and **Your own colour** (Custom: the native colour picker, a preview of a button and a link in both modes). Plain colour circles with a tick on the chosen one; the theme's own is first, its name says it's the default. Each swatch has a 44 px tap area | The theme's own |

Sources: [D 2026-10-27 "themes"], [D 2026-10-27 "themes and songs, adjusted"], [FE 2026-10-27
colour stored as null], [FE 2026-10-27 refined theme picker], `web/js/theme-boot.js`.

**Where it's changed:** only the Theme button in the header (every screen with a header, Settings
included); Settings has no "How it looks" section (Hillel, 2026-10-27).

**The Appearance panel** (Hillel's 73.png, `web/js/components/appearance.js`), titled "Appearance"
with "Changes apply instantly":
- The panel restyles itself with every choice (it uses the page's own tokens): a live preview.
- **Mode** is a quiet segmented control (Light, Dark, Auto): the choice is a raised surface with
  a hairline, not a white block. Under it, what it does now: "Always dark, whatever your device
  says.", or under Auto, "Your device is dark right now, so this is dark."
- **The theme** is headed "Dark theme" or "Light theme" (and under Auto, "Theme, light and dark").
  Each card has its thumbnail, drawn in your accent, its name and a description: Stage (True
  black), Ember (Warm brown), Midnight (Deep blue), Graphite (Soft grey); Spotlight (Pure white),
  Warm (Cream), Sky (Soft blue), Paper (Cool grey). A theme is a light and dark pair, so under Auto
  each card shows both thumbnails and both names ("Spotlight · Stage"); one pick sets both
  (Hillel, 2026-10-27: keep the pairs).
- **One selection mark:** an accent ring on the theme card and the accent swatch, with no ticks
  and no tinted fills.
- **The accent** shows its name beside the heading ("Accent Teal"). The swatches are small glass
  gems, each named on hover and focus and labelled for screen readers. The rainbow swatch opens the
  colour picker (fitted per theme, as below). Below them: "Used for links, highlights and focus.
  Tuned per theme so it stays readable."
- **Reset to default** (dark, the mode's own theme, its own accent) and **Done** (glass primary)
  close the panel.

**The stored shape** is `sa_theme`: `{ "mode": "light" | "dark" | "system", "colour": <family> | null, "accent": <name> | "custom" | null, "customAccent": "#rrggbb" | null, "at": <ms since 1970> }`.

**A custom accent is fitted to each theme** before first paint (`fitAccent` in
`web/js/theme-boot.js`; `fit_custom` in `web/tool/accents.py`; vectors in
`web/tests/vectors/accent.json`). It's kept as picked if its words read 4.5:1 on the theme's ink,
panel and raised surfaces. If not, it moves in OKLCH, keeping its hue and as much chroma as sRGB
allows, to the nearest lightness that passes: from 0.74 up in dark, from 0.60 down in light. Its
strong shade is 0.08 further from the background, and its words are the theme's ink if that reads,
else black or white. When it had to move, the picker says so gently ("Made a little darker here,
so it stays readable.").

| Where it's kept | Web | Apps |
|---|---|---|
| On the device | `localStorage["stackapella.appearance"]` and the `sa_theme` cookie on `.stackapella.com` (a year, Lax, Secure on https; domain from `<meta name="theme-cookie-domain">`, host-only without it) | The `settings` table's `appearance`, the same JSON [M:offline-store.md] |
| On the account | `appearance` (null until chosen; `accent` null for the theme's own) | The same, once accounts arrive |

**Resolution, in order** (`web/js/theme-boot.js`; [FE 2026-10-27 "the theme follows you everywhere"]):

1. Take the device's choice and the cookie's; **the later `at` wins** (web only; the apps have no cookie).
2. Signed in, **the account's saved choice wins** and is adopted through the one writer
   (`stackapellaTheme.choose()` on the web), so the device and cookie follow. A reply to this
   device's own save never overrides a newer choice. This replaces [FE 2026-10-27] "a device only
   takes the account's when it has no choice of its own".
3. Mode: `light` or `dark` as stored; `system` follows the device; anything else is dark.
4. Colour: a known family, else Stage in dark and Warm in light.
5. Accent: a known name, else the palette's own.
6. Apply `<colour>-<mode>` before first paint (no flash), set the browser bar to the palette's
   `themeColor` and, on iPhone, dark status bar text on a light theme. **Apps:** the system status
   and navigation bars follow the mode the same way.

**The theme follows the person everywhere:** the app, the landing and every public page, on both
hosts, all from the same tokens; no page keeps a look of its own [D 2026-10-27 "the landing
page"]. The brand mark keeps its own colours in every theme [FE 2026-10-27 brand mark].

**The picker** (`web/js/components/appearance.js`):
- **The header's Theme button** shows only the mode's icon (sun, moon, or the device icon for
  Auto), with "Theme" beside it from 640 px up; its label is "Theme: {colour}, {mode}. Change it"
  [D 2026-10-27 "the theme picker, refined"], [FE 2026-10-27 refined theme picker].
- **The panel:** Mode (Light, Dark, Auto) above the five colours, then the Accent row. Each colour
  is a tiny screen of that theme in the mode showing (its background, a panel with a line of
  words, a button in the current accent), never an abstract dot. The current one is ringed and
  ticked. A named accent that looks the same as the theme's own is hidden. Below 400 px the
  colours are one column.
- **Choosing never closes it;** Done, Escape or a tap outside does. Under 640 px it's a sheet; from
  640 px up a popover under the button. Settings shows the same panel inline under "How it looks".
- **Both are radio groups:** arrow keys move, Home and End jump, the chosen one takes focus on open.
- The words: "How it looks", "Mode", "Light", "Dark", "Auto", "Colour", "Accent", "The theme's
  own", "Until you choose, dark uses Stage and light uses Warm." (`appearance.*` in `web/locales/en.json`).

### 2.3 Part colours

- **A part's colour is a place, 1 to 8.** A template's parts take their place by order: Soprano 1,
  Alto 2, Tenor 3, Bass 4, extra parts 5 to 8 (`web/js/stores/projects.js` `makeSlot`). A plain
  project's next voice takes the first unused place.
- **Stored as the old hex** (`PART_COLORS` in `web/js/colors.js`), which is only an identifier;
  each theme paints the place in its own shade, so stored projects never change [FE 2026-10-27
  part keeps its stored colour]. **Apps:** store the same hex strings, paint by place.
- **The same part is the same colour everywhere**: its studio row, its Sing lane, its circle, its
  turn band, and "You" when it's yours [D 2026-10-26 "the Sing redesign"].
- Every palette steps the part colours in lightness, Soprano to Bass, so they can be told apart
  with colour blindness, and each passes 3:1 against the background [D 2026-10-27 "themes"],
  [D 2026-10-27 "themes, the build"].
- Dimmed waveforms (a guide, a muted part) draw at 30% on dark themes and 50% on light
  [FE 2026-10-27 dimmed waveforms].
- Words on a filled part colour use `part-text-N`; small words in a part colour use `part-ink-N`.

### 2.4 Type

**The font is Figtree** (Hillel's pick, 2026-10-27; Google Fonts, one variable font, 300 to 800,
display=swap), with a size-matched Arial fallback so the swap moves nothing (`@font-face 'Figtree
Fallback'` in `web/css/input.css`). The share cards, link cards and emails follow [D "the font"].
Timers and counts use tabular figures (`tabular-nums`). Build to the roles below, never to the
family. The music symbols come from a Noto Music subset of eight glyphs [FE 2026-10-27 staff].

| Role | Size (phone / 768 px up) | Weight | Used for |
|---|---|---|---|
| Stage words | 48 / 72 px, line height 1.08 | 800 | Sing's current line |
| Display | 30 / 36 px, tight tracking | 800 | The home's lead title, "Sing the first voice", Your turn (30) |
| Page title | 28 px, tight | 800 | `PageHeader` |
| Big card title | 24 px | 800 | Get ready |
| Sheet title | 20 px | 700 | `Modal` |
| Card heading | 18 px | 700 | Settings cards, empty state titles |
| Body | 16 px (14 px for dense form text from 640 px up) | 400 | Text; **every field is 16 px on a phone** so iOS doesn't zoom |
| Small | 14 px | 600 for buttons and labels, 400 otherwise | Buttons, labels, captions in rows |
| Caption | 12 px | 400 to 600 | Help, chips, the eyebrow (uppercase, 0.12em tracking, `accent`) |
| Badge | 10 to 11 px | 700 | Song cover badges, row tags |

Times, notes, counts and percentages use tabular figures. Sizes are in rem on the web, so they
scale with the browser's text size. **Apps:** logical pixels scaled by the system text size (see 7).

### 2.5 Spacing and layout sizes

- A 4 px grid (Tailwind's scale).
- Page gutters: 16 px on a phone, 24 px from 640 px, 32 px from 768 px. The page container is at
  most 1152 px (the home), forms and Settings at most 672 px, prose at most 65 characters.
- Gaps: 32 px between the home's bands; 16 px (24 px from 768 px) between Settings cards; 24 px
  inside a card (`p-6`), 16 px in a dense row.
- Pages keep a stable scrollbar gutter, so pages with and without a scrollbar line up
  [FE 2026-10-27 polish pass, second part].

### 2.6 Radii, elevation and edges

| Thing | Radius |
|---|---|
| Card (`--radius-card`) | 24 px |
| The home's course band | 28 px |
| Field, skeleton (`--radius-field`) | 12 px |
| Sheet | 24 px top corners on a phone; all corners from 768 px |
| Toast, notice | 16 px |
| Buttons, chips, the part circles, round icon buttons | Full (pill or circle) |

- **Flat by default.** Surfaces read by their fill plus the `edge` hairline; cards have no shadow.
- **Shadows only for what floats** over the page: sheets and popovers (a large soft shadow),
  toasts (a medium one). The scrim behind a sheet is black at 60%.
- **Stacking:** page, then sheets (z 40), then toasts and "A new version is ready" (z 50, above sheets).
- **Never a coloured stripe down a card's edge**, anywhere: cards, callouts, toasts, rows, admin.
  Colour is carried by a small horizontal bar before a band's title, a lettered circle, a ring, a
  waveform, a soft glow from a corner or a tint [D 2026-10-26 "the home page"], [FE 2026-10-27
  studio lost its stripes], [M CLAUDE.md "Never"]. The tuning meter runs across the width, never
  down an edge.

### 2.6a Glass gems (Hillel, 2026-10-27: "bubbles made out of glass, like in Bejeweled")

One recipe for every **playable** piece: notes in every lane, star discs, the path's nodes, the
keyboard's marked keys, the main Play and Record, the part circles and the celebration gems. It's
`.gem` in `web/css/input.css` (its colour in `--gem`; `.gem-bar` for a bar), with a canvas twin for
the lanes. **Cards and panels stay calm** (flat, 2.6), so the gems pop against them; never glass a
surface.
- **Notes are drawn one way only (Hillel, 2026-10-28: "I much prefer Canvas gemBar to the CSS .gem
  bricks"):** every note brick is `js/glass.js`'s `gemBar` on a canvas (`web/js/components/gems.js`):
  the Sing lane, lessons, unit challenges, the workout, part by part's phrase, the chooser and invite
  previews, the song builder's chord shapes, and the creator's roll and Check. Its states are the
  lane's: the note now rimmed in cream and glowing, the next breathing, a hit flashing, popping and
  sparkling, a missed note dimmed, a note only to listen to a dashed ghost, a dragged note lifted. The
  words stay text over the brick, dark or white by its colour. The CSS `.gem` stays for what isn't a
  note: discs, the path, buttons, dots, progress bars and the range keyboard's keys.
- **Body:** a radial gradient in the colour, brighter in the core (top left) and darker at the rim.
- **Edges:** a 1.5 px border a little darker than the colour; a bright 1.5 px bevel along the top
  inside; a lighter refraction band along the bottom inside.
- **Light:** a white specular crescent near the top left (a gloss along the top on a bar).
- **Under it:** a soft glow in its own colour, and a small dark shadow.
- **Light themes:** less glow (about half), more edge and shadow.
- **Unlit** (`.gem-dim`): the same shape in the raised grey, desaturated, with a faint highlight;
  never hidden, and its meaning is always in words too.
- **Won just now:** it pops (from 0.3 to 1.05, then settles, 0.5 s) and a glint sweeps across it
  (0.9 s). With reduced motion it's simply there, with no pop or glint.
- Labels on a gem use the colour's ink token (AA).
- **Buttons are glass too** (Hillel, 2026-10-27: "change to the glassy buttons, on the landing and
  everywhere"; this reverses "buttons stay calm"). All of it is in `.btn-*` in `web/css/input.css`,
  so every screen and the public site follow:
  - **Primary** (`btn-primary`): a glossy pill in cream (white on dark themes, ink on light ones),
    with a specular band across the top half, a thin rim, a soft depth at the bottom, a glow in its
    own colour and a small shadow. Hover brightens (6%). Pressed sinks: an inner shadow, the
    highlight dimmed, 1 px down.
  - **Secondary** (`btn-secondary`): clear glass, a faint body in the ink colour, a rim at 26%,
    the highlight, the text in cream.
  - **Danger and Record** (`btn-danger`, `glass-record`): the same gloss on the record red.
  - **Round icon buttons** (back, Play, rewind, settings): `glass-bubble` (clear) or
    `glass-bubble-solid` (the primary's cream).
  - **Quiet buttons** (`btn-ghost`) stay plain text.
  - Text contrast stays the plain button's (the gloss sits over the top half, under the words), and
    the focus ring is unchanged.

### 2.7 Motion

| Motion | Duration | With reduced motion |
|---|---|---|
| Hover, press, colour changes | 150 ms ease | Kept (no movement) |
| Button press | 1 px down | Kept |
| A count-in number pops in on the beat | 350 ms | None |
| "Nice!" / "Perfect!" over a found note | 900 ms up and away | The word still shows, without moving [FE 2026-10-27 play-along feedback] |
| A lesson's stars, one by one, each with its chime | 450 ms each | They appear without scaling; the chimes stay |
| New stars celebrated: a shower (2) or a confetti burst (3) | 0.9 s / 1.5 s | A glow on the stars (stronger for 3) |
| Your turn pulse | 1 s loop | None |
| Sing's controls fading (phone, while listening) | 400 ms | No fade |
| The tuning dot | 100 ms | Kept |
| The Note lane and the creator's playhead | Glide | **Step** instead of gliding [SUX "Review notes"], [TC "Accessibility"] |

Celebrations and sounds happen between exercises, never while singing or recording [VC].
Anything that must line up with sound is drawn from the audio clock's position, never a timer
[CLAUDE.md "Never"].

---

### 2.8 Visual language (from Hillel's mockups, 2026-10-27)

Hillel sent about 20 mockups (home, Learn hub, lesson steps and results, Your voice, the song page,
the Sing chooser and coach, session settings, the studio, the creator's six steps, the note
editor, the landing). **Take the good parts of them, not every pixel**, and apply the same moves
everywhere, in the web and the apps:

- **Big, confident headings.** One large, tight heading per screen (the lesson title, "Tempo and
  feel", "How do you want to sing?"), a short plain line under it, and nothing else competing.
- **Small caps eyebrows** for context ("UP NEXT · UNIT 1", "STEP 3 OF 6", "YOUR RANGE"), often
  with a part-colour dot or the accent. Never an em dash; use a middle dot.
- **Mono for numbers and metadata** (BPM, ranges "B♭2–E♭3", "7/15 ★", times, "0/4 sung", XP),
  from the system monospace stack, small and muted.
- **Cards as the unit of layout:** soft fills on the page, a hairline edge, generous padding,
  grouped content (a tempo card, a "Pick your part" card). Two columns on desktop (words or
  controls left, a picture or preview right), stacked on a phone. Cards stay calm; the glass is
  for what you play and what you press (2.6a: notes, game pieces and buttons).
- **Choices as big radio cards** with a title, one line and a tag ("RECOMMENDED", "NEW TO THIS
  SONG"), or **segmented controls** for short sets (Words / Notes / Staff, 2 / 3 / 4 singers,
  Yearly / Monthly). Plain checkboxes and dropdowns only where nothing better fits.
- **Parts are always their colour:** a letter disc (S A T B, or the part's initial), a dot before
  its name, its notes, its range bar, its switch. "YOU" tags your part.
- **Show the music, not just words:** range bars against your own range (dashed), keyboards with
  your range shaded, phrase pictures, the lane, the stacked part bars as a song's picture.
- **Progress you can see:** segmented step bars with labels (the creator's six steps, the coach's
  four), the unit path with circles and a star, the day circles for the week, XP bars with "N to
  the next level".
- **A sticky footer for flows:** Back on the left, the reason the next step is disabled as plain
  text, and "Next: <step> →" as the primary pill on the right.
- **One primary action per screen,** a glossy glass pill in white (dark theme) or ink (light
  theme), or the accent for the moment's main action ("Start workout", "Make your own"); the rest
  are clear glass or quiet buttons (2.6a). UI actions are never underlined links.
- **Status chips** ("● Draft saved", "● Built-in microphone", "Muted", "Guide on") with a
  coloured dot; states say what they mean in words.
- **Empty states that teach:** a dashed area with one line and the next action ("Sing the tune
  first", "Alto is empty: click the lane to add a note").
- **Honest words win over the mockups' copy:** no "every part is you", no voice type from one
  note, no em dashes, no claims that aren't built.

## 3. Layout and navigation

### 3.1 Breakpoints

| Name | Width | What changes |
|---|---|---|
| Small phone | 320 to 639 px | The home header shows the avatar alone; the Theme button shows only its icon; the theme panel is a sheet |
| Phone | to 767 px | Sheets rise from the bottom; Sing's phone layout (one calm row at the bottom, the coach takes the screen between phrases); the songs band scrolls sideways; studio rows open in place |
| Tablet | 768 to 1023 px | Sheets become centred cards; Sing gains its bottom bar and desktop layouts; the songs band is a 3-column grid; studio rows show level and pan inline |
| Wide | 1024 px up | The home gains its side column (the week, the level, the install card); 4 song columns from 1280 px |

Every screen is checked from 320 to 1920 px wide with nothing off the right edge (the `widths`
flow) [FE 2026-10-27 polish pass, first part]. **Apps:** the same widths in logical pixels, for
phone, tablet and a resizable desktop window, by touch, keyboard and mouse [M CLAUDE.md].

### 3.2 The page header

- **Every screen but Home, the studio and Sing** uses one header (`web/js/components/page.js`): a
  40 px round back button with a hairline edge, the title at 28 px, and the page's own actions on
  the right [FE 2026-10-27 polish pass, second part].
- **Home:** the logo, then the Theme button, then the account (avatar and name, a "Plus" chip on
  Plus; the avatar alone on a small phone) or, signed out, "Sign in" as a `link-quiet`.
- **Sing:** back, the song's title and its line (section, key, tempo), and the part chip ("Alto",
  its colour dot, a chevron) that opens Your part. While recording, the recording stage (5.5) has
  its own header: the red Recording pill, the song with "Alto · Take 1", and the mic.
- **The studio:** its own header with the tap-to-name title ("Name your song"), the menu, and the
  recording pill while recording.

### 3.3 Moving between screens

There is no tab bar: **the home is the hub** and everything opens from it.

| From | To | How |
|---|---|---|
| Home | Learn (the course) | The course band: Continue (the next lesson, `/learn/<id>`), Course map (`/learn`) |
| Home | A song | The songs band, then the song's sheet (preview, singers, Start) |
| Home | Studio | A project card (`/p/<id>`); New project opens a blank studio at once |
| Home | Settings | The avatar, or the sliders icon when signed out (`/settings`) |
| Studio | Sing | A part row's "Learn it in Sing", the part sheet's "Practise", the menu's "Learn a part in Sing" (`/p/<id>/sing`) |
| Sing | Studio | "Open in the studio" (Just recorded, under the part chooser); never where leaving Sing lands by itself |
| A lesson | Sing | "Open it in Sing" / "Record it in Sing"; Sing returns to the lesson |

**Back** (`goBack` in `web/js/router.js`):
- Goes to the screen this one was opened from, through the history, so the browser's own Back
  agrees [FE 2026-10-26 leaving Sing].
- Opened cold (a shared link, a new tab): Home, or the lesson for a lesson's song.
- **Sing's back returns to where you came from** (home, studio or lesson), never always the studio
  [D 2026-10-26 "no browser dialogs"].
- **No back while recording:** the back button is hidden until Stop.
- A screen that would lose work asks first (the creator's "leave" question).

**Apps:** Android's back and iOS's edge swipe do exactly what the round back button does,
including the fallbacks; both are blocked while recording or counting in.

**Deep links land where the web URL would.** The apps' routes are the web's paths, checked by a
test against `web/js/router.js`; `/admin`, `/styleguide`, `/beta` and `/moved` stay web-only
[M:deep-links.md]. `/?song=<id>` opens that song's sheet on the home [FE 2026-10-27 What's next].

### 3.4 Safe areas and keyboards

- Page tops pad to at least the safe area (`max(1rem, env(safe-area-inset-top))`); sheets and the
  transport pad their bottoms to it; toasts sit just inside the top safe area.
- Sing fills the viewport (`100dvh`); installing removes iPhone's browser bar [SUX "Scope"].
- A sheet focuses its first field (or its primary action). Enter submits a form.
- **Apps:** content resizes so the focused field stays visible; a sheet lifts above the keyboard;
  no layout jumps when the keyboard opens during recording (there are no fields there).

### 3.5 The main screens, in order of what's on them

| Screen | From the top | Source |
|---|---|---|
| **Home** (`/`) | Header. The course band first (first visit: "Learn to sing harmony, one part at a time.", four lanes with one "you", Start singing, "Free, a few minutes, no account needed."; after: the unit's lesson path, Your next lesson, Continue, the week ring and level on a phone). Then the bands in `web/js/home.js`'s order: beta parts waiting, else a song you sang in this week; the songs; your songs; the workout. Each band starts with a small horizontal bar in the accent. **On the home, nothing that isn't a part wears a part colour** (Hillel, 2026-10-27): the course band's path, ring, level bar and unit line, the bands' marks, the avatar, the badges and the menu's icons are the accent (`accent-strong` for small text); the song cards' bars and the welcome's four lanes are parts. Wide screens: the week, level and install card at the side. Unbuilt or switched-off features don't appear. **Since Hillel's 42.png:** the header has the Theme button and an account chip with your initials as a gem. A top row: the course card in an accent gradient (the unit as an eyebrow "UNIT 1 · FIND YOUR VOICE", Course map, the unit's path as glass gems with the current one bigger and ringed, "Lesson 4 of 5 · about 6 min", the lesson's title big, Continue lesson as the glass primary and "2 lessons left in this unit"); beside it the Weekly goal card (the ring, "Sing 2 days this week", seven day bars Monday first filling as you sing, today's letter brighter; the level with its XP in tabular figures and a gem bar; Range, badges and history) and Install. Then Today's workout as a strip of its steps (WARM-UP · 2 MIN, Up and down, then hold; FOCUS · 3 MIN, the weak spot in words; REVIEW · 2 MIN, the lesson) with Start workout, and "7 min · picked for your range". Learn a harmony has All, Yours, Drafts and Plus chips and five across on a wide screen; Make your own says "Sing it, write it, or start from chords". The bands have no colour bars. On a phone it all stacks, the week and level inside the course card. **One way to start a song (Hillel, 2026-10-28, docs/home-create-plan.md):** after the course band (and anything waiting), Your songs, then the library. Your songs' first card is New song, blank with a plus (no button in its head), opening a sheet with two choices: Make a harmony (the song builder's Sing it, Start from chords, Write it, Open a file), Just record (an empty studio; the free plan's limit shows here, and stops a fourth song with the upgrade moment). Your songs holds everything of yours, newest first: recordings, songs written in the builder ("Written by you", to the song's page) and unfinished drafts ("Draft, edited 2 hours ago", Resume and Discard); a first visit shows the New song card and only the line "Nothing yet. Learn one from the library, or start your own." The library ("Song library"; a first visit: "Hear a harmony first") is the catalogue only: All and Plus, no create card, no Yours or Drafts filter, no Unfinished list [FE 2026-10-28 one way to start]. **The library's cards (Hillel, 2026-10-29):** a tap on a song starts it at once (every part; Sing picks the part that fits your range; the free limit and a Plus song's way to Plus show then). Each card's picture has two small buttons, Preview (plays the harmony a short way, or Stop; one song at a time, the playing one in the accent) and Info (named "About <song>"). Info is the simpler sheet: the description, level, key, tempo and length, the parts with ranges (the melody tagged), credits and real voices, then Start; How many singers, Check my range and Make a copy sit under More options. On a wide screen the library shows whole rows only (up to two), a See all card taking a cell when songs are left over [FE 2026-10-29 library cards]. **Your songs' cards (Hillel, 2026-10-29):** a recording has Play on its picture (its current mix: the takes, guides where nothing is sung yet) and a More menu (⋯): Share, Invite someone to sing, Open in the studio, Rename (a small sheet), then Delete in red, its styled question naming the song; a friend's song has Leave instead of Delete and no Rename. A song you wrote has Play (its harmony) and Edit and Delete; a draft resumes on a tap and has Resume and Discard. One thing plays at a time, library previews included. Escape and a tap outside close the menu, focus going back to its button. On a mouse, every card's buttons (Preview or Play, Info, More) show on hover or keyboard focus, and while playing or open; a touch screen always shows them [FE 2026-10-29 your songs cards]. **Later the same day (Hillel):** Your songs and the library show three cards a row on a wide screen (whole rows, See all a cell); a library card has no buttons and its footer is the singer range and level ("2 to 4 • Beginner", said in full to a screen reader); a tap opens the song's sheet, which has Play beside Start (More options as before). Every Your songs card looks the same (picture, title with a small tag, "Written by you", "Draft", "With Sam", one line); a tap opens it, Play plays it (a draft with no tune yet says "Nothing to play yet: add a tune first"), and ⋯ holds its actions [FE 2026-10-29 one card]. | [D 2026-10-26 "the home page"], [FE 2026-10-26 band order], `web/js/views/projects.js` |
| **Sing** (`/p/<id>/sing`) | Header; the modes as chips (Listen, Sing along, On your own) with Settings beside them; your part and Words / Notes; the stage or lane; the first-note card when idle; one calm row: Loop, Play, Record (one big Stop while recording). Part by part: the coach and the phrase path. **Part by part since Hillel's 66.png:** the coach panel says "STEP 1 OF 4" in your colour over the four steps as bars (Listen, Sing along, On your own, Record; a tap goes there), the step big and its line, Not in your range?, and "Next: Sing along"; Phrases with "0/4 heard" as cards (the number, the words, "starts on D4" in tabular figures; the one you're on outlined in your colour; a tap plays it); then Choose another part and Skip to record as quiet links. Between plays the stage is the phrase you're on: "PHRASE 1 · READY", its first note big in your colour (a tap plays it) with "starts here · 7 notes", its notes as a roll with the note names down the side (yours in your colour, the other parts dashed where they come within a semitone of your notes), and "Your notes in colour · other parts dashed when they're close to yours". On a wide screen the transport's Play does the step's action (listen, sing along, hear then sing); a phone, whose transport is hidden while coaching, keeps it as the big button [FE 2026-10-28 coach 66]. **Session settings since Hillel's 67.png:** "Session settings" with Reset beside the close (Words, full speed, no chords, the singing settings as they start); DISPLAY: Show (Words, Notes, Staff), Note names with your part's first notes as a sample ("G4 A4 B4"), Keyboard down the side ("Piano keys beside the lane, so you can see the pitch."), Other parts on the lane ("Faint outlines above and below yours."); PRACTICE: Speed with the tempo it plays at ("90 BPM") and "Recording always uses full speed.", Chords underneath ("Soft backing to tune to. Never recorded."), Guide voices with who sings yours; CONTROLS: Keyboard shortcuts with its keys (R Record, L Loop, N Next phrase, M Mute guide) and "Space always plays, even with shortcuts off. R records in the studio too." The segmented choices pick in cream glass [FE 2026-10-28 settings 67]. **The whole song since Hillel's 98.png:** the modes as one segmented control (Listen, Sing along, On your own; the picked one in cream glass). The stage starts with "Line 1 of 4 · Hear all 4 parts. Your line is highlighted." (Sing along: "Sing with your guide."; On your own: "Your guide is off: sing it on your own.") and, while nothing plays, a pill "You start with ‘maz’ on D4" with Hear note, which replaces the first-note card. On a wide screen, under the parts, the song's lines as a bar of segments that fill as they're sung (a tap goes there), and the transport has Loop line and Record as words beside Play, with Tempo - 80 BPM + on the right (70%, 85% or full speed, as Session settings). The parts' M and S buttons in 98 wait: muting and soloing a guide live in Mix (the studio) for now [FE 2026-10-28 sing 98]. **Hillel's Sing polish (2026-10-28):** the part cards along the bottom are gone at every width (the header's part chip chooses the part); the header is one row: back, the title over its details line with your part's range on it ("Soprano alone · key of Eb · 100 BPM · Alto from Bb3 to C5"), then the part chip, Words, Notes and Staff, and Settings (a phone wraps the right-hand group to a second row); the modes sit under it alone; Session settings no longer has Show (the view is chosen in the header only) [FE 2026-10-28 sing header]. **Once your range is known** (Find your voice, Find your range or a range check, a fifth or more), a song opens in Sing on the part that fits it best (as the range check ranks them), in the key that fits when the key may move, and says so once ("Tenor fits your range."); the header's part chip changes it. **Melody first (Hillel, 2026-10-29; first-week QA finding 8):** a new singer (no good take yet, 2 stars or 70% on pitch, and no other song started; the course's lessons don't count) opens every song on its melody, in their own octave whenever it fits in one (scoring hears any octave, and the key isn't moved for an octave), said once as "Soprano fits your range, sung an octave lower." (or "an octave higher"). From a first good take or a second song, the part that fits is suggested; between parts that fit, the one that moves most (the share of its note-to-note steps that change pitch: Amazing Grace's soprano 29 of 31, alto 19 of 26, bass 9 of 14, tenor 5 of 26), then the smallest key shift; the closest when none fits. A song's page picks its part the same way. The range check's own ranking is unchanged [frontend2 2026-10-29 melody first]. **Your range is stored (Hillel, 2026-10-28):** measured by singing, chosen on Your voice, or kept from the first part you pick on a song while none is known (as that part's range, "chosen by you"). Once known, no part or range picker asks again, here, on a song's page or in the creator: a song opens on the part that fits ("Tenor fits your range."), or the closest when none fits even with the key moved ("None of the parts quite fits your range, even with the key moved. Alto is the closest. Change part from the top if you like."). The header's part chip changes this song's part without changing your range. A measured range replaces a chosen one; a part picked never replaces a known range. Check my range stays in the picker and the part sheet [FE 2026-10-28 fit your range]. **The backing (2026-10-28, MVP):** a song's chords are played by a small band in one of three styles: Gentle ("Piano chords and an upright bass, held and calm."), Pop ("Electric piano on the beat, an electric bass and a kit.") and Swing ("Piano on the back beats, a walking bass and brushes."), on the real instruments when they're downloaded and the synth otherwise. The song builder's Key and tempo step has Backing sound: the three styles, Drums, and Voices on "oo" (off by default). Session settings' Chords underneath and Song settings' Backing choose the style for a song with chords [FE 2026-10-28 band]. **Hear the melody (Hillel, 2026-10-28, "Melody always"):** listening to a harmony part (Listen with the others off: the coach's Listen, the whole song's Listen, in a lesson or a challenge too), the song's melody (the template part marked `melody`) plays under your guide at 0.6 of its level, its take or its guide as the mix says; Sing along, On your own and recording already play every part. What you hear's melody row turns it off for the visit. Singing the melody, or a song without one, nothing changes [FE 2026-10-28 hear the melody]. **The Notes lane on a wide screen (Hillel, 2026-10-28):** no column of part cards beside it; the lane takes the whole width, with its own pitch labels, and the mic level (five bars) sits beside the part chip in the header, in every view, on a wide screen. A phone is unchanged [FE 2026-10-28 lane width]. **What you hear (Hillel, 2026-10-28):** Session settings' first section (Hillel: "combine what you hear into the settings panel"), "WHAT YOU HEAR · For this visit. Recording too.", with Settings on the recording stage too, where only this section shows: an on/off switch for each other part (the melody's says "The melody: plays softly under your part when you listen"), and "Your guide in Sing along" (Soft, Medium, Loud; not while recording, when it says "Your guide is off while you record."). For this visit to Sing only, never saved; changes apply at once, mid-take too; a dot on Settings while anything is changed. Without headphones ("No headphones" in the headphones question, or the speakers route) the others play at half while you record, and the panel says so. One line under the modes says what you'll hear, worked out from what plays ("You'll hear your guide and every other part."); the recording stage says "Hearing Tenor and Bass. Your guide is off while you record." (", softer: no headphones" on speakers). The whole song's Listen plays every part; part by part, your guide with the melody under it. Sing ignores the studio's level as well as its mutes and solos. The review's Hear it with the others is the review's own and never changes Listen. Every take recorded in Sing counts with the guide off. The whole song's Your turn band has Guide back on; a phone keeps the modes while playing. The review mixer: "Tap a part to turn it off for this listen. Everyone plays again when you record.", a part that's off says Off. Polished plays only in the review and the studio; Sing's practice and recording are dry [FE 2026-10-28 what you hear]. **Mutes (2026-10-28):** Sing plays every other part, listening and recording, whatever the studio's mute and solo say; the studio keeps its toggles. Just recorded's mixer turns parts off for that review only: never saved, and every part plays again when the review closes or a recording starts [FE 2026-10-28 mutes]. **Your pitch on the lane (Hillel, 2026-10-28):** a steady dot that doesn't flash: a voice already showing stays through a borderline reading, holds its pitch 0.15 s through a dropout, then fades over 0.2 s where it was. The dot is a solid core in your colour with a cream ring, a halo in the page's colour and a soft glow, above the notes, 0.8x to 1.5x as you sing louder (against your own recent peak, not the mic's level). Behind it a ribbon: its path is your pitch, its thickness how loud you sang (capped), fading with age. Notes view and the recording stage alike [FE 2026-10-28 pitch dot]. **Less clutter (Hillel, 2026-10-28):** Sing's header holds back, the title, the part chip and Settings (top-right, on a phone too, and on Your turn's band and the recording stage); no mic level anywhere in Sing (the pitch dot's size shows loudness). Words, Notes and Staff are Settings' Display > Show again. What you hear lists every part: yours first as "Alto: your guide" (as the mode plays it; on in On your own means Sing along; off while recording unless you turn it on, mid-take too, and the take then counts as sung with the guide), its level and Guide voices beside it, then the others. Guide back on (the turn band, the coach's transport) and the review's own mixer are gone: What you hear does both; the review's part rows say Off for a part that's off, and "Turn parts on or off in Settings, under What you hear." [FE 2026-10-28 less clutter]. **Phone layout and remembering (Hillel, 2026-10-28):** no "You'll hear" line under the modes (the recording stage keeps "Hearing ..."); the modes are centred on a phone as on a wide screen; on a phone the part chip is Settings' first row (YOUR PART · Alto ›, opening the part sheet), and the header is back, title and Settings. What you hear has no subtitle and is remembered: the parts on or off and your guide while recording per song on this device (never in the project, so the studio is untouched), your guide's level for every song; Settings keeps its dot while anything is off or changed; Reset clears them. No Hear the melody hint: the melody's row has a small Melody tag, and part by part Listen still plays it under your guide unless its toggle is off [FE 2026-10-28 phone layout]. **Breaths (2026-10-28):** the last half beat before each new line is a breath: the lane shows a breath mark (a small comma, as on a score) where it starts, and scoring doesn't count silence there. The arrangements keep the notes full length [FE 2026-10-28 breath]. **Loop and the view (Hillel, 2026-10-28):** part by part loops a phrase until you turn Loop off; from then on it stays off for that song (a phrase plays once, and On your own goes once), whatever you play, the phrase or the step. Words, Notes and Staff switch the stage at once, playing or stopped, at the current place; once you pick one, it replaces the phrase picture between plays for the visit. **No code font (Hillel, 2026-10-28):** note names, ranges, counts and times are Figtree with tabular figures, never a monospace face, here and everywhere in the app, the admin and the site. **How do you want to sing? (Hillel, 2026-10-29: three equal choices):** Part by part, The whole song and Open in the studio as three equal large buttons (in a row on a wide screen, stacked on a phone), each with its small picture, one line and how long it takes; a tap starts that way at once; the way chosen last is marked "Last time", never pre-picked; Use this every time below. It replaces: (Hillel's 65.png): the song and your part over it, its range and BPM in tabular figures; Part by part (New to this song) and The whole song (Know it already) as radio cards, each with a small picture of how it goes, three lines and roughly how long ("~12 min · 4 phrases", "~2 min · one take"); then Start with phrase 1 (or Sing the whole song), Use this every time (kept on this device: next time it goes straight in, and the part sheet still switches), and Open in the studio instead. With no part chosen, Which part is yours? follows. | [D 2026-10-26 "the Sing redesign"], [SUX] |
| **Studio** (`/p/<id>`) | Header; the stack of voices, each part a row (its letter, who sings it, its waveform, Solo and Mute as words), opening in place; the last row "Add voice N", which fills an empty studio (the mic listening, Headphones on, Count me in and Click as visible toggles; the transport's Record records, with no second big record button, and Play shows only once there's something to hear); the whole song (Hear it polished, Share, Export); the transport. **Since Hillel's 53.png:** a round back button, the song name in place ("Name your song"), "90 BPM · 4/4 · Saved" in tabular figures (tap for the settings); a mic chip with a dot, green while the mic is open, and the device's name (the mic sheet); Invite singers (Together), or who's in it once others are. An empty studio is a track column beside the lanes: "● Melody · Up next", tinted in its colour, beside a big tinted panel "Sing the tune first" with "Every voice you add stacks on top, and you'll hear the others while you sing."; then "Harmony 1 · Waiting" with "Stacks on top of Melody" and "Harmony 2 · Waiting" with "Add when you're ready"; a dashed "+ Add another voice" (plans one more, up to six); and "Rather learn a harmony?" with a quiet "Pick a song with ready-made parts" button. The ruler numbers the bars. The transport: the time big with "Bar 1, beat 1", Back to the start, and a big red Record that names the voice ("Record Melody", then "Record Harmony 1"), with Headphones, Count-in and Click as switch chips on the right (under the panel on a phone). **Since Hillel's 117.png (the tracks):** edge to edge, no rounded frame. The header: the song's name; under it "Key of E♭ · 90 BPM · 4/4 · Arranged by … · Saved" in plain text (tap for the settings); on the right the mic chip ("● <device> · listening", or "opens when you play or record"), Invite singers, Share (once something's recorded), Export, the settings button and Sing (primary); a phone keeps Sing and the More menu (Share, Export, settings, mic, Sing together, Hear it polished). Over the tracks: "4 parts · none recorded by you yet" (or "2 recorded by you", "all recorded by you"), then a ruler that fits the song, up to its last bar line (eight bars while it's empty), so the waveforms fill the lanes; one playhead line runs across every track (a phone draws it per lane). Each part is a compact track: a record-pick circle (glass, red when picked), its colour dot, its name (a tap opens its panel: pan, timing, trim, record again, learn it, Voice and more) and its tag (Guide, Your take, or a friend's name, even before their recording arrives), small glass S and M, and on a wide screen one level slider with its value (level × 100, so 100 is as recorded); the waveform fills the lane. The tracks share a tall window's height (88 to 150 px each); a phone stacks the name over a 56 px lane. Under them: "Tap the circle next to a part to choose which one you record. Tap a part's name for its pan, timing and more." Record follows the pick: "Record Alto", or "Re-record Alto" once it has your take (a part with your take records again from where that take starts; one without, from the playhead). The pick starts on the next of your parts with no take. The transport: the time with "Bar 1, beat 1", back to the start, a big Play, the red glass Record, and Headphones, Count-in, Click and Polished (once something's recorded) as switch chips. The whole song card is gone: Export is in the header, Hear it polished is the Polished chip [FE 2026-10-28 studio 117]. **From 81.png:** under the ruler, "Words" and the song's lines as chips where they're sung (your part's lines, else the tune's), the one now filled; a tap goes there. The header's lit lyric line is gone. Under a recorded track's name, "Recorded 1 hour ago". The picked track shows a red "Ready to record" chip in its lane. The pick starts on the next part in the song's suggested order with no take of yours, the same part Next names after a take [FE 2026-10-28 studio 81]. **Hillel's studio redesign (2026-10-29, 43.png; it replaces the record-pick circle, the explanation line and the up-next panels where they differ):** one notice: the messages that stacked at the top are one card just above the transport (the newest, with its actions such as Undo and a ×; in game mode a badge just earned is a second line); it takes its own place in the layout, so it never covers the header or the tracks. The record target is a red ring: tap it to arm a track (pressed, named "Record on Track 3"); the main button names it ("Record Track 3", "Retake Alto line"), so the line explaining the circle is gone. Tracks are short rows: the ring, the colour dot and name with "Recorded 2 min ago", S and M, then the waveform; no level slider while closed. A tap on the name opens Level (with its value), Pan (with its value: C, L 30, R 30), Timing (a − As recorded + stepper), four equal buttons, Retake, Trim, Rename and Voice (a song's part also has Learn it in Sing under them), then the takes (Play, Use this take, Delete). After the first track, the next one is an armed row with a dashed slot ("Track 3 · Armed for recording"), and "+ Add track" adds more; an empty studio keeps "Sing the tune first". On a phone the header is back, the name and its details, Sing (for a song) and ⋯ (Microphone, Invite singers, Share, Export, Song settings); a wide screen keeps them as buttons. The bar ruler and playhead follow the audio clock [frontend2 2026-10-29 studio redesign]. | [D 2026-10-27 "the Studio's redesign"], [FE 2026-10-27 Studio A] |
| **Learn** (`/learn`, `/learn/<id>`) | The course map: units in their colours, lessons as round nodes filling with stars, the challenge last, three lessons a row on a phone. A lesson: "Step N of M", one action, the end screen (stars one by one, XP, Replay or Continue, one tip). **Since Hillel's 62.png (the Learn hub):** "Learn / Sing harmony" with a round back button and a "● Your voice · Alto" chip (the voice your range suits, else "Your voice"); Up next (UP NEXT · UNIT 1, the lesson big, its line, Start lesson, "~4 min · up to 3 stars") beside Today's workout ("5 to 8 minutes", Begin); This week with day circles (sung days glass gems, today ringed, days to come dashed), "0 of 2 days", the goal as 2, 3 or 5 days, Remind me; beside it the level with its XP and "170 XP to Level 4, about two lessons."; then the units as collapsible cards, the one you're in open: the eyebrow in the unit's colour, the title, "7/15 ★" with a bar in your accent, the path as glass gems in the unit's colour (the one now hollow with an accent ring and play), three star dots and a label under each, and the picked lesson's row (its line, ~4 min, Start); a closed unit shows "7/10 ★ to open". | [VC], [FE 2026-10-27 polish pass, second part] |
| **Today's workout** (`/workout`) | Hillel's 85.png: the header with "Today's workout" over the step's name, and "1 of 3 · about 1 min" with a bar per step; the exercise's headline and how to sing it; the rounds as pills; the lane; Ready when you are with Hear it first and Start; the starting note with Lower and Higher; the foot with Skip, what Continue waits for, and Continue to step N (5.7f) | [landing 2026-10-28 workout] |
| **My parts** (`/beta`, web only: the beta singers) | Hillel's 91.png: "My parts" with chips counting what needs a retake, what's coming up and what's in the library; then sections in that order: Needs you (a retake with Hillel's note as a note, who and when, with Record another take, Practise phrase N for the phrase he noted, and Hear your last take; a part ready to record), Coming up (Not open yet: "Our musician is checking the arrangement. It opens for recording here once it's checked.", no email promised; or sent and with Hillel), Live in the library (Guide voice: "Yours is the Alto that learners hear first." and what learners see; Alternate voice: learners can choose it under Guide voice, with Try for guide), Not used this time when there are any; and one credit card with Edit (credited by name, "Link my credit to my profile": off with "Profiles open with the gallery." or "Needs a public profile (18 and over)." until both hold, then "Linked to your public profile." on the card). One status chip a card. Course clips, after the library when there are any: a card a clip with the course, "Course clip · 3 seconds", the brief, where it sits against the comfortable note (Hear your note once that note is known), and a recorder in the card: three beats counted in ("Get ready: 3"), "Sing now" with a bar over the clip's seconds, a stop by itself, then the device's check (ready, or silent, quiet, too loud or short, each with its fix), Hear it back, Record again and Send it (into the outbox) | [landing 2026-10-28 my parts] [landing 2026-10-29 clips] |
| **Settings** (`/settings`) | Hillel's 87.png: the page header with Theme; on a wide screen a sidebar of the sections (Profile, Account and sign-in, Practice and appearance, Plan and storage, Your data), the one in view marked, a tap scrolling to it; a phone stacks them. **Profile:** the photo (or a glass initial) with the name and Choose a photo; Your name and Handle side by side; About you with its count (26/300); Bluesky and X side by side; "Everything here except your name is optional, and all of it is shown on your public profile."; Save. **Account and sign-in:** rows for Email (its address, Change opening the change under the row) and Password (Set or Not set yet, Change or Add), then Ways to sign in (Google, Apple, Email and password, each with a dot, green when on, and Remove or Add). **Practice and appearance:** Appearance ("Auto · Stage theme", or with "· Teal accent", and the Theme button), Practice reminders and Choir groups as rows with a chevron, Sounds with its switch, and Ask before discarding drafts when it's off. **Plan and storage:** Free plan ("Up to 3 songs. Plus opens the whole library…") with See Plus, or Plus with Manage; Songs as slots ("2 of 3", "One more before you hit the limit.") beside Storage as a bar ("36 MB of 1 GB", See what's using space); the sync line. **Your data:** Download my data (Refresh, Download), Signing out (Sign out, Sign out everywhere), and Delete my account. Contact us last. Signed out: the sign-in empty state and Practice and appearance [FE 2026-10-28 settings 87]. **How you sing** (On this device, 31's catch): Ask each time, Phrase by phrase or Whole song; it is what Sing's "Use this every time" keeps, and the part sheet's switch changes it too while one is kept [FE 2026-10-28 how you sing]. **Start the course over** (Practice): "Your lessons and stars go back to the start. Your note, range, XP and badges stay. You can undo it for 30 days." and Start over, asked once ("Start the course over?"); then "You started the course over. You can undo it until 27 November." with Undo, and the toast offers Undo too. Signed in, the account keeps the copy, so another device follows (its next save is refused and it takes the account's) [FE 2026-10-28 start over]. | `web/js/views/settings.js` |
| **Sign in** (`/signin`) | Hillel's 74.png. The logo with **Not now** in the header (back to where you were). "Sign in to back up your songs" (or "Create an account to back up your songs") and "Optional. You can learn and record without an account. Signing in syncs your songs across your devices." Apple and Google as full-width glass buttons (the last used first and marked), "or use email", then **email first**: the email (placeholder, `inputmode=email`) and Continue, which checks only the address's shape (nothing is looked up, so it never says who has an account); then the password step with the email and **Change**, the password with **Show/Hide inside the field**, Forgot password?, Keep me signed in, and Sign in. Sign up is one form (name, email, password with Show/Hide, and "When were you born?" as Month and Year, with "Month and year only, so Stackapella suits your age. We never show it to anyone."; under 13 says kindly that it's for 13 and over). Errors sit on their fields (`aria-invalid`), the button says One moment while it works, every target is 44 px. "New to Stackapella? Create an account", the paused-sign-ups line, forgot, reset and the email links as before. | [FE 2026-10-27 sign-in links], [FE M3 one sign-in screen] |
| **Create** (`/create`) | Make your own: Sing it, Start from chords, Write it, Open a file; then one step at a time in one frame (Hillel's mockups): the header says "Make your own · Step N of M" over the step's name, with "Draft saved" (a dot) at the end; a labelled progress bar (a bar and a name for each step); the step's big heading and cards; and a footer that stays in view with Back, what Next is waiting for (or a short summary: "90 BPM · 4/4", "4 phrases", "2 of 4 lines sung"), and "Next: <step>". **Tempo:** the BPM big with minus and plus, Tap the beat and Hear the click, a slider, and Slow, Medium and Fast as cards; Beats in a bar as a segmented control with the beats as dots (ONE two three four); Pickup notes and Before you sing (headphones, the mic, the timing check) as two cards. **Words:** Words or No words beside the heading; Paste or type (with Try an example) beside What you'll see, a card a phrase with its syllables to fix. **Sing:** who's singing the tune as two radio cards that stay in view (Higher voice, Lower voice; fixed once a line is sung); with your range known, it's chosen from it (a tenor or bass sings an octave down) and said in one line, "Lower voice, from your range." with Change, which shows the cards; the line card with tabs (a tick when sung) and "N of M sung", "Line N of M", the line big with the next one faint, the lane empty until you sing, and Sing line N, which waits for a voice ("Choose a voice above first."). **Check:** Key and Play the tune beside the heading; the lane with the key's rows and a legend (Sung note, Worth a listen, In the key); "N notes worth a listen" with Review them (the guided check, one at a time); the lines with their note count (and how many to check), a play button for the line, and Sing again. **Parts:** the ways as full-width radio cards (Suggest them, tagged Recommended; Write them; Just the tune), each with chips of the parts it makes in their colours (from the template's parts, or the tune and what a suggestion adds; Just the tune dims the rest); Suggest them opens the styles to hear and pick. **Save:** two columns (the preview first on a phone): About it (Title, A line about it, How hard is it? as a segmented control), Who wrote it (three radio cards, then Words by and Tune by), Who can open it (Only me, Anyone with the link, as cards; Someone else wrote it keeps it private); beside them a live preview of the song card (every part's notes small, the title or "Untitled song", the line, the credits, chips for the level, parts, tempo and key, and who can open it) with a checklist (Give it a title, Say who wrote it) that ticks off; Save is in the footer, disabled until the checklist is met. Unfinished on the home with Resume and Discard. **The note editor** (Write it, a copy, any part; Hillel's 60.png): a toolbar card (Notes or Words, Draw or Select, Snap, Lane or Staff, zoom, Undo and Redo dimmed when there's nothing to undo, ? for the keys, and Play all on the right); the parts as cards on the left (the colour as a gem, the name, the range and the note count small, in tabular figures, "No notes yet", Mute and Solo chips), the one being edited outlined in the accent with EDITING; the lane in the middle (a bar ruler, Bar 1, Bar 2, octave labels down the side that stay put, the key's rows lighter, the edited part's notes as glass gems with the word and name inside, a selected note rimmed in cream and glowing, a dragged one lifted, the other parts as faint dashed ghosts, the playhead a glowing red beam); a help panel on the right that closes ("Alto is empty", how to add, move and resize, and "Other parts show faintly so you can write around them."), the selected note's details when one is picked. On a phone: the parts as a chip row above the lane, the toolbar compact, help under ?. The same lane (glass gems, ruler, labels) in Check, Sing it and Start from chords. | [TC], [SB] |
| **Plus** (`/plus`) | Hillel's 89.png. Page header "Stackapella Plus"; what led here (an upgrade moment) first. Two columns (stacked on a phone, value first): the headline "Every song. As many as you like. With your choir.", one line, and three highlights with glass icons (The whole library; Save as many songs as you like, which says "You're at N of 3 songs on Free" once you've saved one; Sing with your choir). Beside them the price card: Yearly and Monthly as radio cards (Yearly with "Save N%", worked out from the two prices in the currency shown, "$3.33 a month, billed yearly" and the year's price; Monthly "Billed monthly" and its price), the launch-price promise ("it stays the same for as long as you stay subscribed"), a button naming what you buy ("Get Plus yearly · $39.99", or "Start your 7-day free trial"), the renewal and Cancel any time in Settings in plain sentences, Pay in (USD, EUR, GBP) and the tax ("added at checkout, before you pay", or "included"). On Plus: the plan as it stands and Manage your plan. Under them "Free and Plus, side by side" with Only differences (first) or Everything, grouped Learn, Make and Together, a dash where Free has none, and one footnote for the age limits, coaches and course work. In the apps this is the in-app purchase screen, with the store's own price. | `web/js/views/plus.js` |
| **Choir groups** (`/groups`, `/groups/<id>`) | Hillel's 90.png. The list: each group as a card (its letter, the name, "You direct · 2 singers and you" or "Directed by Ana · 3 singers", the song count). A group: the name, an honest count ("You direct · 2 singers and you"), and for its director **Invite singers**, which shows the link card ("Anyone with this link can join the group. Send it however you like.", the URL with Copy link and Share, "Made 27 Oct. Resetting makes a new link and the old one stops working." with Reset link, which asks first; a link made before it could be shown again says to make a new one). **Songs**, with + Share a song at the top: each song a card with its summary ("2 of 4 parts covered · 1 recorded · no Soprano or Bass", or the amber nudge "Nobody has a part yet. Assign parts so everyone knows what to learn." with Assign parts outlined in amber), Assign parts and Open, and a part strip from the song's own parts (each part's colour dot and name, who sings it and how far: Recorded in green, Learning in amber, Nobody yet muted). Assign parts opens each singer's part picker in place, with Stop sharing. **Singers**: avatar, name ("(you)"), their parts ("Alto in Amazing Grace") and "active today" (their latest part update) or "joined 27 Oct"; the director marked Director; Remove asks first and says what happens. **Group**: Rename the group as a row, and End the group as a row in red with "Singers lose access to shared songs. Their own recordings stay theirs." and End group…, which asks first. A singer sees their part on each song, Open, and Leave the group in the same place. | `web/js/views/groups.js` |
| **Contact us** (`/contact`) | Hillel's 112.png. "Contact us" and "Hillel reads every message and replies by email."; one card: **What's it about?** as single-choice glass chips (Recording or sound, Something's broken, An idea, Account or Plus, Something else), **Your message** with a placeholder for that topic ("What you did, what you expected, and what happened instead." for broken), **Your email** (the account's, read-only with Change to Settings, when signed in), and **Include device details** as a switch with its summary ("Mac, Chrome 152 · app 2026.10.26. Helps with recording problems."), on for a recording problem or something broken and off for the rest until you choose, and **See exactly what's sent** opening every field that goes (never any audio). Under the card, what Send is waiting for ("Add a message to send", "Add your email so Hillel can reply") beside **Send message**, disabled until then. Sent: "Sent. Hillel will reply to you@… by email." and the message's reference. Opened from a help page, that page goes along. | `web/js/views/contact.js` |
| **Invites** (`/join/<token>`, `/invite/<code>`, `/early/<code>`, `/choir/<token>`) | Hillel's 75.png, one card for all four (`components/invite-card.js`), centred on the screen, with "I already have an account" in the header when signed out. Who invited you, from the link's own data (their avatar or initials as a glass disc and "Ana Ruiz invited you"; Hillel for his shared link and mudeo's early access), with the kind under it ("Your part: Tenor", "Thursday Singers · 3 singers", "Early access") and "invite expires in 7 days" only when the invite has an expiry. "Learn to sing harmony, one part at a time", one line for the kind, and a four-part preview drawn from a real song's notes with your part lit in glass. Signed out, on Hillel's beta link (`/invite`) and mudeo's early access (`/early`): **Start singing** first, into the app as a guest with the beta pass the link gave and no account, exactly as a visitor at launch; then "No account needed. An account saves your songs across your devices, and you can make one any time." with **Make a free account**, which opens the email form below [landing 2026-10-28 guests]. Signed out on a friend's song (`/join`) or a choir (`/choir`), which need an account: Your email, **Accept invite** (it sends a sign-in link: "Check your email", Send it again after a minute, and never a word on whether the address has an account), and "or accept with" Apple and Google. The link opens `/signin/link`: it signs in or makes the account; a new one gets **A bit about you** (name, When were you born?) first; then on to where the invite was for. A used link says so with Sign in; an expired one offers Send a new link. Signed in: Accept invite, or Join the group (with the 18-and-over question when the account hasn't answered it). Used, ended or closed links say so in the same card. | `web/js/views/join.js`, `early.js`, `groups.js` |
| **Your voice** (`/voice`) | Hillel's 63.png. "Your voice" with "Level 3 · 330 XP" in tabular figures on the right. **Your range** big ("B2 – C4"), the part it suits and "It's a starting point, not a label: sing any part you like.", with where it came from ("Measured on 28 Oct 2026", or "Chosen by you"), **Hear it** (low, comfortable and high, on the audio clock), **Measure again** (the range check, in place) and **Change it** (Hillel, 2026-10-28: a sheet with the four voices as cards with their ranges, and Lowest and Highest a semitone at a time with - and +, each step sounding, a fifth apart at least; Hear it; Use this range). A change is your range everywhere from then on. A keyboard, C2 to C6, with your range shaded in the accent and your comfortable note dotted (tap a key to hear it), and under it on the same scale each part's usual range outlined with the part of it you can sing filled and its percent, your part in bold; on a phone it scrolls to your range, the part names staying put. Four tiles (Lowest, Highest, Most comfortable, Span), then "Find a bass part to sing". **Badges** "5 of 26" with All, Firsts, Range, Skill, Habit and Together: the earned ones as cards with a glass icon in the kind's colour and the date, then **Still to earn** as rows, nearest first, with "0 / 4" and a bar where the badge counts something. Then the history (Plus, or a sample) and Your progress. | `web/js/views/voice.js` |
| **A song of your own** (`/t/<id>`) | Hillel's 64.png. The title, its line ("No description yet." for its owner), the facts as chips (level, key, BPM, length) and the credits. **Pick your part**: Singers 2, 3 or 4 as a segmented control; the parts as radio rows (the name, MELODY on the tune, the range in tabular figures, a bar on one scale with your range as a dashed box, and a play button that plays that part alone), the best fit for your range chosen first (else the tune); "One singer on each part." and the Your range key; then **Learn the bass part →** and **Hear all parts**. Its owner also sees **You made this**: a card for where it's kept (backed up; on this device and why; or "Only saved on this device" with the details to fill in and **Back it up**), Only me or Anyone with the link as two radio cards (the link to copy or share when it's open), and Edit, Make a copy, Export file and a red Delete, which asks first (Keep it first) and says what goes. Over the free limit, Learn shows the Plus moment. | `web/js/views/template.js` |

---

## 4. Components and their states

### 4.1 Buttons

One controls and forms system for both skins (Hillel, 2026-10-29: "I like new design system, I think
it works well for game mode on or off"; the spec is `~/Code/shots-review/designs/forms/NOTES.md`; every
control is on `/styleguide`). Gloss only on the note gems; every control is flat. Two colours with two
jobs: the **primary** says "do this" (cream in serious, the person's accent in game), the **accent**
says "chosen" and draws the focus ring (cyan `#3be7ff` in game). Tokens, from each theme's own
colours: `--fill` (cream at 6%: fields, chips), `--fill-2` (9%: secondary, segmented track),
`--fill-3` (12%: switch off, slider track), `--fill-4` (16%: pressed), `--lift` (the one small
shadow, only on the chosen segment, a switch knob, a checked box and a slider thumb). Corners: pills
in serious; 14 px in game (segments 10 px).

| Kind | Look | Use |
|---|---|---|
| Primary (`btn-primary`) | `--primary` fill and its ink; hover mixes 88% toward the page, pressed 78% | The one main action of a view |
| Secondary (`btn-secondary`) | `--fill-2`, `cream` words; hover `--fill-3`, pressed `--fill-4`; never an outline | Other actions ("Continue with Google" too) |
| Ghost (`btn-ghost`) | words alone in `muted`; hover `--fill` and `cream` | Quiet actions ("Keep singing without an account", Discard) |
| Danger (`btn-danger`) | `bad` fill | Only the final confirm of something destructive |
| Record | A `record` circle (studio empty: 112 px, 144 px from 768 px; Sing: 56 px outlined; the studio transport: a pill) | Recording only |
| Play | A `--primary` circle (Sing 72 px, studio 56 px) | The transport |

| Size | Height | Text |
|---|---|---|
| `btn-sm` | 36 px (with `tap-44`) | 14 px |
| `btn` | 44 px | 15 px, weight 650 |
| `btn-lg` | 52 px | 16 px |

- States: rest, hover (desktop only), pressed (a tint deeper), focus (a 2 px ring, 2 px out),
  disabled (42%, no pointer, always with a line saying why), busy (`aria-busy="true"`, a 16 px
  spinner before the label, and the label says what's happening: "Sending", "Saving").
- Hover is the button's own colour lighter or darker, never plain white, so it works in all ten
  palettes [FE 2026-10-27 buttons' hover].
- **A button names its action:** "Delete song", "Record the Alto", "Use 2 singers", never "OK"
  or "Yes" [D 2026-10-26 "no browser dialogs"].
- **Hit areas:** 44 by 44 px on the web, 44 pt on iOS, 48 dp on Android, for every control; the
  visible control may be smaller only if its hit area isn't [TC "Accessibility"]. See section 11
  for the web's smaller ones.

### 4.2 Links

- **`link`** for links inside prose: the text colour, a 2 px `accent` underline 4 px below,
  `accent-strong` on hover; a link that can't be used now is dimmed to 45% with no underline.
- **`link-quiet`** for actions beside forms and in headers (Forgot your password?, Create an
  account, Sign in, add or remove a sign-in method): the words alone, underlined in the accent
  only on hover and focus [FE 2026-10-27 sign-in links].
- **Underlines are only for links inside sentences** (Hillel, 2026-10-27). A UI action on its own is
  a real button: **`btn-go`** for "go to" actions (Course map, See all, Your voice, Reminders, Plus,
  Storage), a small bordered pill with a chevron after the words; **`btn-ghost btn-sm`** for other
  actions in rows and lists (Copy link, Delete, Skip, Not now, Loop the phrase, Open the studio).
  A "Try again" or "Sign in" at the end of a notice's sentence stays a `link`.
- **The home's logo** goes to the public site's landing page (`public-url` meta; `/?landing` on one
  host); Home is reached as before, from every screen's back.
- Links to other sites open the system browser (Checkout and the portal too, in the installed app).

### 4.3 Cards

- `card`: `panel` fill, 1 px `edge` outline, 24 px radius, no shadow, no edge stripe.
- `card-interactive`: the outline turns `muted` at 60% on hover or visible keyboard focus (on it, or on
  something inside it), never left on by a mouse click or a file picker.
- A tappable card is one link or button with one name; its secondary actions sit outside it.

### 4.4 Chips and badges

- `chip`: a status tag, 24 px high, a pill, `--fill-2`, 12 px `muted` words. Never a button.
- `chip-accent`: a wash of the accent with accent words ("Plus" by a name, Polished).
- `filter-chip`: a chip you press, 32 px with a 44 px target, `--fill-2` with `cream` words; on
  (`aria-pressed="true"`) the accent fill with its ink and a check.
- **Selected choices** (goal days, chips that toggle, segments, radio cards) are the accent, with
  `aria-pressed` or a radio role [frontend2 2026-10-29 forms system]. In Sing the
  selected mode is filled in your part's colour with a tick, because it's yours.
- **`StatusChip`** (`web/js/components/form.js`): an icon and words, never colour alone. Tones:
  good (tick), muted (dot), warn and bad (alert), info (info).
- **Song cover badges:** "Plus", "Free this month", "Real voices", "Draft", "Yours".
- **"Coming soon"** is the exact label for a planned feature on the public site; no dates
  [D 2026-10-27 "the landing page"].

### 4.5 Fields and validation

- **Anatomy** (`Field`, after iaw): the label (14 px semibold) with "(optional)" unless required, an
  `aside` slot on its row (Forgot your password?), the field, the help (12 px `muted`), then the
  error with an alert icon in `bad`, `role="alert"` [D 2026-10-26 "the look of admin and forms"].
- **The field:** 44 px, `--fill` with no border, 18 px in from each side, 16 px words everywhere (iOS
  zooms into smaller), the buttons' corners (a pill in serious, 14 px in game; a text area 22 px).
  Hover `--fill-2`; focus a 2 px ring inside and a faint halo; invalid (`aria-invalid`) a 2 px `bad`
  ring, and the error (13 px semibold, `bad`, an alert icon) replaces the help; disabled 45%;
  read-only no fill, a faint 1.5 px edge and a lock at the end (the share link); a check in progress
  (a handle) a spinner at the end. A counter ("26/300") sits at the end of the label's row.
- **Dropdowns** are fields with our own chevron (a V in `muted`, 20 px from the end, mirrored right to
  left), drawn from the theme's own colour.
- **The birth date** is two dropdowns, month and year, in a 3 : 2 grid inside a labelled group, with
  one message under both; red only after an answer that fails (under 13), never at rest.
- **Validation:** on submit, by the server's 422 field errors placed under their fields; a refusal
  that isn't about a field is a `Notice` above the button. Errors say what to do ("At least 12
  characters.").
- Password fields have "Show password"; email and name fields use the right autocomplete.

### 4.5a Controls

All in `web/js/components/controls.js`, looks in `css/input.css`:

- **Switch** (`Switch`): a `role="switch"` row (a 44 px target) with the label and an optional line
  under it; the track 48 by 28, its 22 px knob `muted` when off, the accent track with the accent's
  ink knob when on, the knob stretching to 26 px while pressed.
- **Segmented** (`Segmented`): a `role="radiogroup"` on a `--fill-2` track (4 px in, 36 px segments);
  segments `muted`, `cream` on a `--fill` hover, the chosen one the accent with the lift. The arrow
  keys move the choice and the focus (wrapping), Home and End go to the ends; only the chosen one is in
  the tab order. In a settings row on a phone it drops under the row's words and fills the width.
- **Stepper** (`Stepper`, − value +): a field with two 36 px round ghost buttons and a typed number
  (`inputmode="numeric"`, up and down arrows step it), − or + off at its limit, the ring on the whole.
- **Checkbox and radio** (`checkbox`, `radio` on the native input; `Checkbox`): 22 px in a 44 px
  `check-row`, `--fill` with a 1.5 px edge; checked, the accent with the lift and a 14 px check (or an
  8 px dot) in its ink; partly checked a 10 px bar; invalid a 2 px `bad` ring.
- **Radio card** (`radio-card`): `--fill`, `--fill-2` on hover; chosen, a wash of the accent and a
  2 px accent ring, its radio filled.
- **Slider** (`Range`, `range`): a 6 px track filled in the accent up to the value, a 22 px accent
  thumb with a collar in the panel's colour, in a 44 px input. The mixer's small row sliders
  (`mini-range`) stay as they are.
- **More contrast** gives fields, steppers, tracks and switches a 3 : 1 edge; **forced colours** a
  real border, with chosen in Highlight.

### 4.6 Notices

`Notice`: a `raised` box, 16 px radius, an icon in the tone's colour, words in `cream`;
`role="status"`, or `alert` for problems. Use it for something the person should know on this
screen ("Your storage is nearly full."). Read-only projects show a notice banner saying why, with
the way out ("Choose which stay editable", "See Plus") [FE 2026-10-27 read-only projects].

### 4.7 Modals and confirmation

- **Never the browser's or the OS's own dialogs** (`confirm`, `alert`, `prompt`, a native alert
  box) for an app decision; a test fails on any use [D 2026-10-26 "no browser dialogs"],
  [M CLAUDE.md "Never"].
- **`Modal`** (`web/js/components/ui.js`): a bottom sheet on a phone (max 92% of the height), a
  centred card from 768 px (448 px wide, or 576 px). Focus moves in, Tab wraps, focus returns on
  close; Escape, the scrim and the close button dismiss it unless it's busy or must be answered.
  A confirmation has no close button (its safe answer is the way out).
- **`confirmAction`** (`web/js/components/confirm.js`), one for the whole app:

| Part | Rule | Example |
|---|---|---|
| Title | Names the thing | "Delete "Silent Night for Mum"?" |
| Body | Says exactly what happens, where it goes and how long Undo lasts | "This moves the song and its recordings to Recently deleted, where they stay for 30 days. You'll have 10 seconds to undo." (a song the account doesn't have yet: "This removes the song and its recordings from this device. …") |
| What's lost | A quiet box of rows, label and value, where it helps tell one thing from another (Hillel's 78.png) | Deleting a song: Recorded parts 4, Length 3:42, Last edited Yesterday |
| Action | Its own button, named for the action; danger style when it destroys | "Delete song" |
| The safe answer | A destructive question focuses it, so **Enter keeps**; Escape and the scrim also say no; no close button beside it | "Keep it" (deleting a song), "Cancel" |
| Don't ask again | **Never on a destructive question.** Only discarding a draft offers it, because a discard is undoable: "Draft discarded." with Undo for 10 seconds and its countdown, putting the draft back exactly. Kept on the device; Settings shows "Ask before discarding drafts" to turn it back on | "Don't ask again" |
| Never skippable | Everything else: deleting a song or a part, fewer singers, account deletion (type "delete" to confirm; "This can't be undone."), removing a sign-in method | |
| Undo | After a delete: a toast with Undo for 10 seconds, a thin accent bar running down the time left | ""Silent Night" deleted. Undo" |

- The two buttons sit side by side, equal width, the safe one first [FE 2026-10-27 delete confirmation].
- One question at a time; a new one answers the old with no.
- **Apps:** the same styled sheet, never `showDialog` with platform alert styling or a
  `CupertinoAlertDialog`.

### 4.8 Sheets, popovers and menus

- Sing's Settings and Your part, the part sheet, Trim, Share and the theme panel are sheets on a
  phone. A sheet holds one task; its primary action is at the bottom.
- The studio's menu (Learn a part in Sing, Share or Export, Project settings, Microphone and
  timing, Sing together, Hear it polished) is a popover list of full-width rows, 12 px radius.
- A popover closes on Escape, a tap outside, or choosing (except the theme panel, which closes
  only on Done).

### 4.9 Toasts and "saved"

- **Toasts** (`Toasts`): at the very top, 0.25 rem inside the top safe area, above everything
  (sheets too), never on the content; tapping one dismisses it (Hillel, 2026-10-27); at most 448 px
  wide, `raised` with a `line` border; an error tone is tinted with `record` at 16%. Gone
  after 6 seconds; actions are text buttons ("Undo", "Sing the Bass", "Back to full speed").
  `aria-live="polite"` [FE 2026-10-27 toasts]. **Apps:** the same, at the top, not a bottom
  snackbar.
- **"A new version is ready"** heads the toasts with Refresh and Not now; never while recording,
  counting in or playing, nor part way through a lesson or a workout (it shows at the end screen or
  back on the map); Refresh sends what's waiting first (up to 8 s) [FE 2026-10-27 new version]
  [landing 2026-10-28 lessons].
  **Apps:** (Proposed) the same card when the API answers 426, with Update opening the store.
- **Saving words:** "Saving", "Saved", "Draft saved" (the creator's header), and the cloud status
  lines in section 8. Saving is automatic; there's no Save button for a project.
- Errors after an action are toasts with Try again when they're about that action, notices when
  they're about the screen.

### 4.10 Empty states

`EmptyState`: a card with a 32 px `muted` icon, a title (what's missing), a body (what to do),
and an optional action. Examples: "No parts yet" / "When Hillel asks you to sing a part, it shows
here."; "Nothing deleted recently."

### 4.11 Loading and skeletons

- A **skeleton** (`skeleton`: a `raised` block pulsing, 12 px radius) where the shape is known
  (the course band).
- Elsewhere **words in a status region**, in the present: "Getting the song ready", "Getting your
  lesson", "Preparing the guides", "Listening back to your take...". No bare spinners.
- A step that takes time names its steps ("Saving your song to your account...", "Mixing...",
  "Sending the mix...", "Making the link...").

### 4.12 Errors with a way out

- **Every failure has a plain message and a way out**; no dead ends [TC "When things go wrong"].
- The shape: what happened, that the work is safe, what to do, and a button.
  "Couldn't save just now. Your work is still here; try again in a moment."
- Never a raw exception or HTTP message [C "Never"]; the API sends codes and the app words them.
- **A raw translation key never shows**; a missing key renders nothing and the words are fetched
  again [FE M3 raw key].

---

## 5. Audio and singing UX

The heart of the product. Everything that must line up is scheduled on the audio clock, never a
timer, on both platforms [CLAUDE.md], [M CLAUDE.md].

### 5.1 The microphone

- **When it opens:** at setup (the Record tap, "Sing a note", your first turn), never on the
  count-in; it stays open for the session and closes when you leave the screen, so the mic light
  goes out [D 2026-10-26 "a review by another agent"], `web/js/record-setup.js`.
- **The first click waits 0.8 s after the mic opened** (`MIC_SETTLE_SECONDS`), on the audio clock.
- **Asking:** **Web:** the first time on a device, unless the mic is already allowed, a short card
  before the browser asks: "Stackapella needs your mic" (to hear you sing and light up the notes;
  nothing kept unless you record) and "Your browser will ask next: tap Allow", with Continue and Not
  now (`micHelp.primer*`, `explainFirst()` in `web/js/mic-help.js`); then "Asking for the
  microphone..." while the browser asks. **Apps:** (Proposed) before the OS prompt, a short card that says why
  (singing is heard, notes light up, "Your singing is analysed on this device") with one button
  that triggers the OS prompt; the OS asks only once on iOS, so this card is the only explanation
  people get. The iOS usage string and Android's rationale use the same words.
- **When it fails: never just a line of red text** (Hillel, 2026-10-27; built: `web/js/mic-help.js`,
  `web/js/components/mic-help.js`). Every screen hands the failure to one "Let's get your mic
  working" modal. It names the cause in plain words, from the error and the device:
  - blocked, not found, in use or couldn't start, an insecure page, an app's own browser, or no mic
    support;
  - iOS 26's "No AVAudioSessionCaptureDevice" counts as couldn't start, because quitting the browser fixes it.
  Then a big Try again, Reload and try on iPhone, and Copy link in an in-app browser. The numbered
  steps are for the person's own browser and device, the others under "Using something else?"; the
  Mac or Windows switch is added where it may be off. Details for support shows the raw error,
  each way of asking that was tried, the permission and the browser. It re-checks by itself, through
  the permission's change event and on coming back to the page, and once the mic opens it closes
  and the screen carries on. **Apps:** the same screen, with Open Settings and a re-check on
  returning to the foreground.
- **The modal, v2** (Hillel's 77.png, `web/js/components/mic-help.js`):
  - **The state is the header:** a tinted header with a status dot carries it: red while blocked
    ("Your mic is blocked for Stackapella" / "Stackapella needs to hear you sing. Allow it in Chrome,
    then try again."), amber while checking, and green with the level meter once it hears you,
    which then closes and carries on after 1.5 s. No separate banner.
  - **The setup as a sentence:** "For **Chrome** on **Mac** · Change"; Change opens the
    platform chips.
  - **Two steps** on a computer (Try again is the third, in the footer). The settings' own names
    are in bold, marked **like this** in the words and drawn by `boldParts`, never as markup. Beside
    them is a small drawing of the address bar with the icon to click ringed and the Microphone
    switch on (Chrome, Edge, Firefox; Android Chrome and Firefox).
  - **The device's steps fold away** under "Still blocked? Check your Mac settings" / "Your Mac
    can block the mic for every site", numbered on from the browser's. They open by themselves
    after a failed retry, and the header says so.
  - **One way out:** the close. The footer has Details for support (one tap copies them) and Try
    again (glass primary). Reload and try (iPhone) and Copy link (in-app browsers) stay above the
    steps.
- **Opening tries more than one way** (`askForMic` in `web/engine/engine.js`): the chosen mic, the
  default with raw audio, plain `audio: true`, and on iOS once with the audio session left to the
  browser. A refusal isn't retried. On iPhone the page sets the audio session to play-and-record
  before opening the mic. If moving the audio to the mic's rate fails, the mic stays open at the
  old rate.
- **The system's "now playing" controls** name Stackapella, and their pause stops as a call would
  (the take is kept); the session says playing only while something plays. Pure silence (a Mac with
  the lid closed) is still said under the picker.
- **The picker** shows "System default" named, and the wireless note: "Wireless mics change their
  delay while you sing. Wired headphones with a built-in mic give the tightest timing."
- **The level** shows whenever the mic is open: five bars, live, labelled "Microphone level"; with
  "Hearing you" in the studio's first voice.
- **The mic check** (Hillel, 2026-10-27; built: `web/js/mic-check.js`, `web/js/components/mic-check.js`):
  the first time anything needs the mic on a device (Find your note, a lesson's Try or Play, Record,
  the range check, the creator's mic), and again whenever the list of mics changes (a headset
  plugged in or out), confirm the right mic hears you. "Using: MacBook Pro Microphone · Change", the
  level meter with its voice line, and "Say something or hum". Once a voice crosses the line for
  0.4 s in all: a tick and "Got you, your mic works", and after 1.4 s it carries on by itself. Nothing over the line
  for 5 s: "We can't hear anything from <mic>. Try another mic:" with the picker open; or, when
  the mic heard something under the line (over -66 dBFS) for 0.4 s, "We can hear <mic>, but very
  quietly. Turn up its gain (the knob on an audio interface), or sing closer:" with the picker. The
  5 s count only while the audio runs: a context still waiting for a tap reads silence and counts
  nothing [FE 2026-10-27 the Scarlett report]. **Find your voice's no-sound card** (Hillel's
  Scarlett mockup, lesson 1's first step): the eyebrow "Find your voice · Step 1" over the prompt;
  after 6 s with nothing (under -70 dBFS) or only very little, one card in place of the listening
  one: an amber dot and "No sound from <mic>" (or "We hear <mic>, but very quietly: turn up its
  gain"), a segmented level meter that lights as sound comes in, the Microphone picker, a divider,
  "Things to check" numbered for the device (an audio interface, by its name: the mic in input 1,
  since a browser hears the first input; the gain for input 1 turned up until its light flickers;
  48V for a condenser mic. Any other mic: the right one picked, not muted, the computer's input
  level up), and Try again. It keeps listening underneath and goes back to listening when it hears
  you. The foot is pinned: Back in glass, and "Skip, I'll set up my mic later" (or "Skip for now"
  while it hears you) as a quiet link. **Web:** no Input picker (a browser hands over one or two
  channels, never an interface's 1 to N); **Apps:** the Input picker 1 to N for an interface with more
  than two inputs [FE 2026-10-28 no-sound card]. A mic
  that fails to open goes to the mic help. It sits inline in Find your note's card, headed "First, a quick mic check", and is a
  short sheet "Check your mic" everywhere else, with "Skip the check" (on without it, asked again
  next time). Remembered per mic, with the list of mics it was heard with. **Apps:** the same, keyed
  on the input's id. **The sheet since Hillel's 103.png:** "Check your mic" with "Sing or hum a few
  notes at your normal volume."; a card with the mic as a picker (a mic icon, its name, the
  chevron), the level as a row of 28 bars moving right to left (grey, green once it hears you), and
  "Sounds good" with a green dot (or the say, can't hear and very quietly lines); the foot Skip
  (underlined, on without it, asked again next time) and Continue, which waits until it hears you.
  The sheet no longer carries on by itself; inline in Find your note it still does
  [FE 2026-10-28 dialogs 101 to 105].
- **The level meter** (`web/js/components/level-meter.js`), on every screen that listens (Find your
  note, the mic check, the range check, the picker, the Mic sheet, the timing check, the creator's
  hold-to-sing): a bar that fills with the level (RMS, -72 to -12 dBFS) and a **voice line** at
  -48 dBFS, the same line the listening counts a voice from. While the fill is over the line, the
  fill and the line light in the accent and it says "Hearing you" (held 0.25 s through a dip, so it
  doesn't flicker); under it, the fill is grey and it says "Listening". Words go with the colour,
  never colour alone; it's a `meter` with its words as the value text.

### 5.2 Headphones

- Asked before recording, once a visit: "Headphones on?" / "Put your headphones on, so the other
  parts don't leak into your new recording." / "They're on", "No headphones", "Don't remind me again".
- The studio's visible toggle "Headphones on" skips the question [D 2026-10-27 "the Studio's redesign"].
- **Since Hillel's 102.png:** "Put your headphones on" with "Otherwise the other parts leak through
  your speakers into your take."; Wired ("Tightest timing.") and Bluetooth ("Adds a delay that can
  change between sessions.") as radio cards, each with a chip: Ready (green) or Needs timing check
  (amber; Bluetooth until its timing is measured with this mic); picking one sets the route. The
  foot: Don't ask again, Not now and Start recording (the course's says No headphones and They're
  on) [FE 2026-10-28 dialogs 101 to 105].
- Without headphones, the sound underneath rests while you sing in exercises, and Wait for me
  plays each note for a moment then goes quiet, with Hear it again [FE 2026-10-26 exercises].
- Bleed (the other parts heard in your rests) is said calmly after the take: "We could hear the
  other parts through your mic, so these stars may not be fair, and this take doesn't count
  against you. Headphones give you accurate stars." Such a take is left out of bests and badges.

### 5.3 The timing check and latency words

- **What it is:** "Measures the delay between what you hear and what the mic records, so new parts
  line up exactly." About 10 seconds of chirps.
- **Before the first take** with no timing for this mic, it's offered; "Skip, use an estimate" is
  allowed except for beta guides ("Guides need a measured delay...").
- **The route question:** "What will you record with?" Speakers and mic / Wired headphones /
  Bluetooth headphones, each with its hint.
- **Since Hillel's 101.png:** "We'll play a few clicks and listen for them, so your recordings line
  up with the other parts. It takes about 10 seconds, and you only need to do it once per setup."
  under the title; Microphone as a picker with a mic icon, the level as segments with Hearing you or
  Listening beside it; "What will you record with?" as three choices in a row (Speakers, Wired
  headphones, Bluetooth) with the picked one's hint in a box under them ("Hold one earbud or ear
  cup right against the mic."); the foot "Skip and use an estimate" (underlined) and Start the
  check [FE 2026-10-28 dialogs 101 to 105].
- **The result:** "Your delay is {ms} ms. New parts will be lined up automatically." Details
  (measured, runs agree within, the browser's estimate) are folded.
- **Wording rules:** "delay" and "timing", never "latency" in the UI; milliseconds as "{ms} ms".
- **After a take:** "Sound off? Nudge timing" (slide the take, hear it against the others) and
  "Line it up for me" [D 2026-10-26 "a review by another agent"]; studio rows nudge 10 ms at a time.
- **Apps:** the same words; the measured round trip per device and route is shown in the same
  "Show details" fold [M CLAUDE.md "Latency comes first"].

### 5.4 Count-in and Get ready

- **Sing together before the song is on the account** (Hillel's 106.png): "Sing together" with
  "Friends you invite record their own parts and hear everyone else's. You stay in charge of the
  key, tempo and title." under the title; a card "First, save this song to your account" / "Your
  takes and settings come with it. Then you'll get an invite link."; Not now and Save and invite,
  which saves it now and opens the invites (or says it couldn't, and why it might be)
  [FE 2026-10-28 106].

- **Song settings** (the studio's settings button, Hillel's 105.png): the Title, then a row each with
  its control on the right: Key (with "Find my key with a range check") as - G +; Parts with the
  names sung ("Soprano, Alto, Tenor, Bass") as 2 3 4; Count me in ("One bar of clicks before
  recording starts.") and Metronome ("A steady click while you record and play back.") as
  switches. A song of your own has Tempo with Tap, Key and Time signature as rows instead. The
  foot: Save as template (underlined, once something's sung), Cancel, and Save, which waits until
  something has changed [FE 2026-10-28 dialogs 101 to 105].

- **Count-in:** On (the default) or Off, a switch ("Count me in: A bar of clicks, then sing"); On
  is one bar (Hillel, 2026-10-27; a project saved with 2 bars keeps them until switched). Each number pops in on the beat; the header says "Recording in {count}".
- **Click:** "Click while I sing", a steady beat at the tempo, a visible toggle.
- **Get ready:** a bar before every entry after a rest of a bar or more (and before a round's late
  entry): a card in your part's colour tint, "Get ready", "Your turn in 2 beats", a dot per beat
  filling. Screen readers hear "Get ready. You start on {note}." once.
- "Coming up, you start on D4" before you come in; "Play my first note" is one tap away.

### 5.5 Recording

| Element | Rule |
|---|---|
| Sing: the recording stage | Recording strips everything else away (Hillel's 113.png): no part cards, modes or tabs. A thin `record` line across the top; top left the red glass **Recording** pill with a pulsing dot ("Recording in 3" through the count-in); centred the song and "● Alto · Take 1" (each recording of a part in a visit is the next take; a part with a take starts at Take 2); top right "Mic" and its live level. Your lane big (40% of the height) with its glass gems, the voice line, the hit notes lit, the camera and smoothing as ever, and the hint as a chip in your colour beside the voice head ("A little low · go up", "A little high · go down"). Under it the words huge: sung words bright, the syllable now in your colour and underlined, the coming ones grey, the next line smaller (a part with no words: the lane alone, its gems carrying the words). The bottom bar: a red progress line, the time ("0:23 / 0:36"), **Start over** (this take goes, nothing kept, and the same part records again, counted in), the big red glass Stop with a stop square and "Stop and keep", and "75% on pitch so far" (the share of your notes sung so far that lit). A phone puts the time and the share on a row above the buttons. Space or Esc stops and keeps [FE 2026-10-27 recording stage] |
| The studio | The recording row outlined in `record` with a faint red tint; one red pill, a pulsing dot and "Recording 0:07" (m:ss), with the mic level; "Stop recording". Space or R stops |
| Controls | Never fade while recording; nothing else to tap but Stop (and, in Sing, Start over) |
| Speed | Recording is always at full speed; at 85% or 70% the Record tap explains, with "Back to full speed" |
| Screen | Kept awake while recording and practising (the wake lock; **Apps:** the platform's keep-awake) |
| Interrupted | A call, a locked screen or a lost mic stops the take and keeps it: "Recording stopped: your take is saved up to {time}." |
| Read-only | A read-only project refuses with the reason in a toast; it still plays and exports |

### 5.6 After a take: takes and choosing your best

- **Just recorded** (Sing) has the screen: "{part} recorded", your take in the stack with the
  others (yours outlined), **Hear it with the others**, **Record again**, **Next: {part}**, and as
  quiet rows "Sound off? Nudge timing", "Re-sing a phrase", "Trim the ends". When every part is
  sung: "Every part is you!" and Share [FE 2026-10-27 Sing redesign part 4].
- **One current take per part.** Record again replaces it; the replaced take waits in Recently
  deleted for 30 days (signed in) and Undo brings it back straight after [D 2026-10-26 "the
  backend, coaching and Event Schedule"].
- **Re-sing a phrase** swaps one phrase as a new take; it says when a join "sounds a little
  different", and offers the whole part instead [FE 2026-10-27 Re-sing].
- **Trim:** handles on the kept stretch, Trim the silence, Line up every part, Snap to the beat on,
  every change plays at once, one Undo for the visit. **Since Hillel's 82.png:** "Trim <part>" with
  "Nothing is deleted. You can widen it again any time."; the whole take with the cut ends hatched
  and big grips, "Keeping 2.4s of 5.0s" under it; Starts at and Ends at as two cards, each with its
  time big ("0:00.99"), a zoomed view of that end (1.5 s either side, the cut side marked CUT), -
  and + round buttons (a beat with Snap to beat on, else 0.05 s, said under the toggle) and Hear
  start or Hear ending; a dashed suggestion when the silence finder sees one ("Cut the breath:
  start at 0:00.62"), which replaces Trim the silence; Snap to beat as a switch; Line up every part
  as a card (Same start, Same end) when more than one part is recorded; the foot Reset, Play
  trimmed part (the kept stretch with the others) and Done [FE 2026-10-28 Trim 82].
- **Beta singers** pick their best: "Use my previous take instead", and "Hear it as learners will".

### 5.7 Pitch feedback and lit notes

- **Live:** while it's your turn or you're recording, your voice draws as a line about 30 times a
  second, and **notes light up as you hit them** (in your part's colour, with a soft glow). A
  missed note dims and slides past: no red, no sound [D 2026-10-26 "the Sing redesign"], [VC].
- **On the Stage**, a tuning meter across the width under the words: a dot that sits on the middle
  mark when you're on the note, "Lower" and "Higher" at its ends, and a sentence for screen
  readers ("20 cents under the note.") [FE 2026-10-27 tuning meter].
- **Praise:** "Perfect!" within 15 cents, else "Nice!"; a combo from 3 ("8 in a row") that resets
  quietly.
- **Wait for me** (the course's Try steps): the music waits on each note; "A little higher" or
  "A little lower" after 1.5 s; Skip this note after 3 s.
- **How your pitch is drawn** (Hillel, 2026-10-27; `web/js/pitch-display.js` and `web/js/livevoice.js`,
  the same numbers in the apps; after the pitch display research, `docs/pitch-display-research.md`
  section 6). It's for display only; lighting and scores read the raw readings.
  - **About 50 readings a second**, stamped with song time less the latency.
  - **Your octave is learned once and taken off every reading**, inside notes and out. It comes from
    the first clear reading in a note, then the median of the last 3 s, and changes only after 1.5 s
    of disagreement. A man on a treble part never jumps 12 at a note's edge (the cause of "it
    suddenly drops"). It's said once, kindly: "You're singing an octave lower. That's fine."
  - **Gaps:** up to **0.12 s** is bridged at full strength. Longer, the line fades out over **0.3 s**
    where it was, never toward the floor or the next reading.
  - **Slips and leaps:** a jump within 0.6 of **7, 12, 19 or 24** semitones is held off until **8
    readings** agree. Any other jump over **2.5** waits **3 readings**, then the line snaps there.
  - **A median of 3**, then an ease of **30 ms** (12 ms across a leap over 1.5), so a 4 to 7 Hz
    vibrato keeps most of its swing.
- **The display: Option A, the ribbon lane** (Hillel's pick, 2026-10-27; `docs/pitch-display-research.md`),
  one component in two sizes (`web/js/components/sing/lane.js`):
  - **Note bars are exactly as tall as the tolerance** (35 cents either side by default; taller is
    more forgiving, so the bar follows the strictness). They're tinted and outlined in your colour
    until sung; each **fills solid where you sang it in tune**; a lit note **keeps a tick**; the
    note now has a cream outline. Words and note names sit just above each bar, never under the line,
    and never run into the next one.
  - **Your voice:** a cream centre line (a 0.18 s mean) with your vibrato as a soft band in your
    colour round it, at most half a semitone each side, closed on a stretch that isn't steady. The
    head at the now line is ringed in your colour when you're in. When you're out, a dotted lead runs
    to the bar's middle with an arrow the way to go, and the words say "A little higher" or "A little
    lower" (on the line's 0.3 s centre: after 0.4 s out, cleared after 0.2 s within 25 cents; it
    keeps its way until it clears, so a vibrato never flips it). After a breath, the head is a hollow
    ring where you stopped, and a dashed dot marks where the next note starts.
  - **A note lights on any moment on pitch** (no hold).
  - **The Stage (D)** shows a 128 px strip of the same lane over the big words on your turn, with the
    hint (or "On the note") in words under them; the old tuning meter is gone.
  - Still to come: C (the focus window) for Wait for me, B (the phrase page) after a take, and the
    ribbon box for Find your note and the range check.
- **What counts:** a note is lit within the strictness (35 cents by default; 50 relaxed, 20
  precise), close counts half, nothing is "wrong" (`web/js/scoring.js`).

### 5.6a The course's Learn steps (Hillel, 2026-10-27)

Built: `web/course/course.json` (each learn step's `headline`, `steps`, `visual`), `web/js/views/learn.js`,
`web/js/components/learn-visual.js`.
- **No long passages.** A Learn step is a **headline takeaway** ("Breath that's steady gives notes
  that are steady.", 30 px, 800) and **three numbered step cards**. Each card is a panel with the
  number in `accent-strong`, a bold title and one short line ("1 · Breathe in low / Like you're
  filling your belly").
- **A picture brings it to life.** On a wide screen the words are on the left and the picture on the
  right; on a phone the picture sits above, smaller (12 rem). The pictures are one family, in the
  accent: a pitch ladder, a steady line, a drone settling, a vowel's mouth, an echo, words lighting,
  a phrase's arc, two notes ringing, a round, the bass's floor, three parts balancing, a swell, a
  vibrato wave.
- **Breathing is a living flower.** Two rings of translucent petals bloom and turn as you breathe
  in (4 s) and fold back and dim as you breathe out on "sss" (6 s). A soft glow sits behind, a few
  motes drift off with the out-breath, and the words count it ("Breathe in", "Out on 'sss'",
  seconds left; "In · 4s  Out on 'sss' · 6s" under it). It's a canvas, drawn only while on screen.
  Under reduced motion it's a still flower whose words change once a second.
- The words are marked `reviewed: false` for the voice teacher.

### 5.7d Wait for me (Hillel's 80.png)

Built: `web/js/components/exercise.js` (mode `try`), `web/js/waitforme.js`.
- **Pills** across the top, one per note: found ones filled in the part colour with a tick, the
  one now outlined in the accent, the rest dim.
- **The lane shows one note:** its name on a long glass bar that **fills only while you're on the
  note** (the hold to light it), with your voice as a bead and a dashed guide to the note. Off the
  note: a "Music is waiting for you" chip, and after a moment of searching, "Slide up" or "Come
  down". At the end, on the lane: "All three notes matched", "Next, you'll sing them without the
  guide."
- **A big line under it:** "Find A3" / "Hum or sing 'ah'. The bar fills only while you're on the
  note, so take your time."; "That's it, hold it"; "Nicely done" / "You matched all three notes."
- **No Stop:** Hear A3 (the note again) and Start over. Skip note still comes after a long search.
- **Continue is earned:** the lesson's Continue waits for the matches ("Match the notes to
  continue." with a quiet "Skip this step").
- **One step label:** the lesson header shows "2 of 3" beside its bars; the line over each step's
  heading is gone.

### 5.7c The arcade lanes and the pitch camera (Hillel, 2026-10-27)

"Zoom in, and let the camera follow the range they're singing in", "more like a fun arcade game,
SEGA in the 90s", and "bubbles made out of glass, like in Bejeweled". One look for every note lane.
Built so far: Sing's lane and its Stage strip (`web/js/components/sing/lane.js`, a canvas), and
the exercises' lane (`web/js/components/exercise.js`, gems in the DOM). The creator's roll and the
after-take charts come next. The camera is `web/js/camera.js` and the canvas glass is `web/js/glass.js`.
- **Every note is a glossy brick** (Hillel, 2026-10-28: "more like bricks than circles", "large,
  arcade style"): a rounded glass rectangle in the part's colour, as long as its note, with its
  syllable (or its name) inside. A note only to listen to is the same brick as a dashed outline. No
  round note pieces anywhere; a round piece is your voice (the bead, the line's head). In the
  exercises' lane: bricks 34 to 48 px tall, a beat never under 48 px wide (a brick is exactly its
  note's length with a 3 px gap, so it never covers the next note), and a longer exercise scrolls
  with the playhead held 35% across. Wait for me's pills are small bricks too [FE 2026-10-28 bricks].
- **The pitch camera:** it frames the notes from 0.4 s back to 3 s ahead, plus the live voice, with
  1.5 semitones of room above and below. The span is never under 7 semitones and never over the
  part's range plus room. A voice far off moves the frame no further than half that.
  - **Motion:** a critically damped spring on the centre and the span. It doesn't overshoot and
    settles in about 0.8 s. A new frame within 0.75 semitones (centre) or 1 (span) of the goal
    changes nothing, so a quick note inside the frame moves nothing. Looking ahead means it has
    already zoomed out when a leap arrives.
  - **Game feel:** a note of a second or more, held in tune for 0.6 s, pushes in to 0.9 of the
    span. Nothing to sing in the next 1.2 s (a phrase's end) pulls back to 1.1.
  - **Reduced motion:** one frame per phrase (notes split at a rest of a beat), cut at the phrase's
    start, never continuous movement.
  - **An exercise** is framed once: all its notes, tight and centred, the lane 170 to 260 px tall.
- **The gems (2.6a):** every note is a glass gem in the part's colour, at least 28 px tall in the
  exercises (14 to 34 px in Sing's lane, by the zoom), and never shorter than the in-tune band.
  - The word and the note name sit bold inside the gem when they fit (dark or white by the colour,
    AA), else just above it.
  - **The next note** breathes (a soft glow, 1.2 s before it starts).
  - **The note now** has a cream rim and a glow.
  - **Sung in tune:** a light sheen fills the gem where you were in tune.
  - **A hit:** a flash, a pop to 1.05, eight sparkles, then it stays lit and glossy with a tick.
  - **A miss:** greyed and desaturated, never red, never hidden.
  - **Other parts:** faint dashed outlines. **Notes only to listen to** (an exercise's guide
    before your turn): dashed outlines too.
- **The stage:** a faint line for every semitone (a C stronger), a soft floor glow in the accent,
  specks in the accent drifting at a third of the notes' speed, and a vignette. The playhead is a
  glowing beam: cream while listening, your colour on your turn, red while recording.
- **Light themes:** less glow, more edge and shadow; the vignette is barely there.
- **Reduced motion:** the depth stays (gradients, gloss, shadows); no breathing, pops, sparkles or
  drifting specks.
- **Performance:** no per-frame filters. The gem's shades are worked out once per colour, and only
  the note now and a hit use a blurred glow.

### 5.7a Listening for your voice (Find your note, the range check, the creator's hold-to-sing)

Hillel, 2026-10-27, after the first lesson. Built: `web/js/listening.js`, `web/js/components/listening.js`.
- **Always clear whether it's listening:** Getting the mic ready, then **Listening** (a ring that
  breathes, and grows with the mic's level: "I'm listening. Sing any easy note, like a long
  'ahh'."), then **Hearing you** ("I hear you. Now hold that note..."), then **Holding** ("That's
  it, keep holding"), then **Got it**. Changes fade in, with no moment of doubt.
- **No "press then speak", and no Start listening** (Hillel, 2026-10-27): it starts listening the
  moment the step opens (the Continue that led there is the tap browsers need before audio). Silence
  is ignored, and measuring starts when a voice is heard; the hold starts and ends by itself. Only
  on a reload straight into the step, with no tap on the page yet, does it say "Tap anywhere to
  start listening", and any tap or key starts it (not a button). The first time on a device, the mic
  check (5.1) comes first, inline, then it goes on into listening. If the mic can't open: "We need
  your mic to hear you." with "Try the mic again", and the mic help. The buttons are optional:
  Start over, Change mic (the picker appears by itself when nothing at all is heard), Skip for now.
- **Where listening starts by itself, and where it doesn't:** it does wherever the app only listens:
  Find your note, the range check, the mic check. An explicit Play, Try or Record stays wherever
  audio must start on the clock first: a lesson's Try (Wait for me) and Play, including
  single-note exercises (the reference note, drone or chords sound before the voice is judged),
  recording over parts, Sing along, the creator's sing or hum over the looping backing, the timing
  check and the beta sound check (clicks, or two quiet seconds measured first). The **creator's
  hold-to-sing** stays a hold: it rewrites the selected note, and always listening would change notes
  from the editor's own playback or talking.
- **The level meter** (5.1) sits under the bar while listening, so people see why it started or why
  it hasn't.
- **Nothing for a while** (6 s): a gentle word under the status, and it keeps listening: "Try a
  little louder, or closer to the phone.", "We can't hear anything yet. Check the mic below is the
  right one.", or "Pick one easy note and hold it, like a long 'ahh', for a couple of seconds."
- **No flickering note names while singing:** the voice is a **marker gliding on a bar**,
  smoothed so it never jitters (an 0.18 s ease). A ring around it fills as the note is held.
- **Wide, then zoom:** the bar starts at the whole human range (C2 to C6, only the Cs named). Once
  the pitch has held for 0.6 s, it eases (0.35 s) to the octave around it, every semitone ticked and
  the natural notes named. With reduced motion, it's there at once.
- **At the end, the note:** big, in the part colour ("A3", under "Your comfortable note"), with
  Play it, a line on the parts that may suit ("Alto and tenor parts may suit you."), and Sing
  again; the lesson's Continue goes on. The note is named in letters (a single note has no key for
  solfège).
- **The range check** listens the same way: a note held for a second counts. **Since Hillel's
  108.png** it is "Find your range": the two steps as a bar (Lowest note with the note found, then
  Highest note; Lowest note goes back to it), the prompt ("Sing your lowest comfortable note on
  "ah" and hold it."), the note big (green with "Got it" once held, the lowest or highest of
  them), the hold filling, where the voice is on a strip from C2 to C6 with the held note marked,
  the level, and the foot: the mic as a quiet picker, Try again (this step from scratch) and Next
  (disabled until a note is held). The result lists the parts as before
  [FE 2026-10-28 dialogs 107 and 108]. The **creator's hold-to-sing** shows the same marker on a bar around the note being set,
  instead of a name.
- **One octave, judged well:** a note's steadiness is judged within one octave (a slip doesn't break
  the hold). It sits in the octave most readings were in, so an onset read an octave out doesn't
  set it (`web/js/listen.js`).

### 5.7a The chords step (Start from chords)

Hillel's 86.png, 2026-10-27. Built: `ChordsStep` in `web/js/components/create/chords.js`.
- The heading is a sentence: **"Pick the chords you'll sing over"**, with "Tap ▶ to hear one, tap
  the card to choose it. You can change it later."
- The families as glass pills (the chosen one lit), and **Show as: Shape / Numbers / Letters** as a
  segmented control beside them.
- **One tap to choose:** the whole card is the choice (a group of radio buttons; arrow keys move it),
  outlined in the accent with a tick when chosen. ▶ on the card only plays it. No Choose buttons.
- **Every card shows its chords.** **Shape** (the default, no theory needed): a glass block per chord,
  taller the further it is from home, coloured by its job (**Home**, **Lift**, **Pull back home**,
  from theory.js `chordRole`), with the legend "Taller = further from home." under the cards.
  **Numbers** shows the numerals (I V vi IV), **Letters** the chords in the draft's key (C G Am F).
- **Playback you can follow:** while a card plays, each chord lights in turn (read from the audio
  clock; the others fade a little).
- The **blues** is one wide card with its Shape, Turnaround and Feel; changing any of them chooses it.
- **The footer confirms the choice:** its name and its chords ("The four chords", "C · G · Am · F";
  a chord held for bars on end once, "C7 ×4"), with Next live at once. Before that: "Nothing chosen
  yet" and "Tap a card to choose. ▶ only plays it.", with Next disabled.
- The progress bar's step names wrap to two lines rather than cut off on a phone.

**Key and tempo** (Hillel's 109.png): "Key and tempo", "Pick a key that sits where you sing easily,
then set the speed.", two cards side by side (stacked on a phone).
- **Your voice:** Soprano, Alto, Tenor, Baritone, Bass as a segmented control, chosen from the range
  the app knows (the nearest voice) until you pick one; "Not sure? Check your range" opens the range
  check in place, and a range measured there leads.
- **Key** big ("F major"), with Lower and Higher as glass bubbles (each a step from the key now) and
  Hear it.
- A **range chart** on one scale, E2 to A5: your voice's range, and where the tune will likely sit in
  this key as a glass bar ("The tune · C4 to C5", from theory.js tuneSpan), then the verdict:
  "Suggested for alto · fits comfortably" (or "for your range"), "Fits comfortably" for another key
  that fits, or "A little high for you. Try Lower." / "A little low for you. Try Higher."
- **Tempo:** minus, the BPM, plus, "beats a minute", Tap the beat, and the pace in words ("Relaxed,
  walking pace · one beat every 0.67 s").
- **Feel:** Straight ("Even beats") or Swung ("Long-short, jazzy").
- **Backing sound:** Voices on "oo" ("Soft choir holding the chords") and Keys ("Piano playing the
  chords") as glass switches.
- The frame's Back names the step it goes to ("‹ Chords"), on every step.

### 5.7b The chord chart (Start from chords; "Show the chords")

Hillel, 2026-10-27. Built: `web/js/components/create/chord-chart.js`. Now shown while you sing over
the chords (the chords step shows each card's chords itself).
- A basic lead-sheet chart: the bars **four to a line**, between bar lines (`| C | % | F | G |`),
  in the chosen key's letters, plain readable type (16 px bold, tabular).
- **%** for a bar that repeats the one before; a **line that repeats the line before** is shown
  once, with "×2" (or more) after it.
- The **section's name** on the left of its first line (Verse, Chorus, Bridge, once sections exist).
- The **bar playing** is filled with the accent while a feel plays or while you hum over it.
- A feel's Play starts the loop at once; only singing or humming over it gets the bar of clicks.

### 5.7e Your key: Not in your range? (Hillel's 128.png, 2026-10-28)

- **Where:** at the foot of a lesson's exercise card (Listen, Try it, Sing it), and in the coach card
  when learning a song's part, above its big action.
- **How it looks:** "Not in your range?" in muted text, then a segmented control in a thin ring:
  Lower and Higher as glass buttons, "Key C" (or "Key D♭", "Key E minor") between them in normal
  text. A lesson's exercise then has the big round play (cream glass, a dark ring) with "Tap to
  listen", "Tap to try" or "Tap to sing" under it, and the foot Skip (quiet) and a wide glass
  Continue, disabled until the step is done.
- **What it does:** a semitone a tap. The guide, drone, chords, targets and scoring all move
  together. A lesson reaches 6 either way from your note and keeps the choice per lesson with the
  course's progress (it syncs); a song reaches 7 either way from its written key, kept as the
  song's transpose, and shows the control only while nothing's recorded in it.
- **Where it starts:** a lesson on your note (unit 1 finds it). A song's part in the key that fits
  your range the first time you pick a part (the course's range, when it spans a fifth or more);
  a range check at the start, or Lower and Higher, is your choice and is kept.
- **Skip** on a sung step goes on; skipping the scored one ends the lesson with "Skipped for now",
  no stars and nothing lost [FE 2026-10-28 your key].

### 5.7f Today's workout (Hillel's 85.png, 2026-10-28)

Built by the landing agent with the frontend agent's OK (`js/views/workout.js`, `js/workout.js`).

- **Header:** the back button, "Today's workout" small over the step's name ("Warm up", "Something
  to work on", "A review"), and on the right "1 of 3 · about 1 min" (the whole workout's length,
  from the exercises' own timing) with a short bar per step. "Step 1 of 2" no longer repeats in the
  page.
- **Headline:** what the exercise is. The warm-up: "Five notes up and down, then hold" (or "Three"
  when the top of your range is close), with "Sing "ah" for the scale and "oo" for the long note.
  Each round starts a half step higher." A weak spot or a review: its reason, as before.
- **Rounds:** the warm-up is three rounds, each a half step higher (its top note leaves room for the
  last round). The rounds show as numbered pills (sung as a glass gem, now with the accent rim, to
  come in a line), and "Round 2 · up a half step" while it runs. Start runs them one after another;
  each round's lane is laid out for that round alone, so nothing falls off it. Other steps are one
  round.
- **The lane** is the lessons' (5.7c): glass bricks, dashed while they're only to listen to. The
  mockup's purple and orange states aren't used: the lane keeps its one language (the glass rule).
- **Ready when you are:** "3 rounds starting on A3 · about 40 seconds" (or "Starting on ..." for one
  round), with Hear it first (the notes once, nothing sung; Stop while it plays) and Start (Stop
  while it runs). On a phone the buttons sit under the words, side by side.
- **Starting note:** a calm card, "Starting note: A3", "Straining at the top or rumbling at the
  bottom? Shift it.", with Lower and Higher as glass buttons (the lesson key control's words): a
  semitone each, remembered for next time, up to seven either way. They replace Too high and Too
  low.
- **Foot** (stays in view): Skip warm-up (Skip this one for the others), "Finish 3 rounds to
  continue" or "Sing it through to continue" until it's done, and Continue to step N (Finish on the
  last). Skipping everything still counts nothing.
- "A new version is ready" waits while a workout step is under way (see "A new version is ready" with the toasts).

### 5.7g Plus courses (docs/plus-courses-build-plan.md, 2026-10-29)

Staff only until Plus opens; then every account the `plusCourses` feature reaches.

- **More courses** under the course map on /learn: a card per course, its Learn picture, title, one
  line, "N lessons" (or "3 of 17 lessons" once started) and one button: Start (Plus or staff),
  Continue (started), or Try the first lesson free. Staff see a "Staff preview" chip. On a phone
  the picture sits above the words; from `sm` beside them.
- **The course page**, /learn/c/<id>: back to /learn, "Course" over its title; the Up next card
  (the unit, the lesson, its line, Start lesson, "~N min · up to 3 stars"), or on the free plan
  past lesson 1, "Part of Plus. The first lesson is free." with See Plus; the course's line and
  "No app replaces a voice teacher. If anything feels tight or hurts, stop and rest."; the course
  map (the free course's, with that course's units); Restart this course once started (a styled
  confirm naming the course; only its lessons and stars go).
- **Sharpen up** (js/sharpen.js; Hillel chose it, 2026-10-29): once three lessons of the course are
  done, a card under Up next replays the weakest. It picks lessons done and open that have a scored
  step, by fewest stars kept, then the lowest best percent, then the longest ago: two when three or
  four are done, three from five. The card has "Sharpen up", "Replay your 3 lowest-star lessons"
  (serious: "lowest-scoring"), "Straight to the part that's scored, one lesson after another. Your
  best in each one stays.", each lesson's title with its three star dots (serious: "Best 62%", no
  stars), and Start. Start opens the first at its scored step (its last scored step, or an earlier
  step it sings over or compares with), with no title card, and the header reads "Sharpen up · 1 of
  3" instead of the unit. Each lesson ends with its usual end screen and keeps its own best result.
  Up next is the session's next lesson; after the last, "That's your Sharpen up done..." and Back
  to the course. Back in the header ends the session on the course page, as does opening the course
  page. Nothing new is stored: the session is this tab's only.
- **A lesson** of a Plus course is the free course's lesson screen. Its first lesson is free; a later
  one says it's part of Plus. Back to the course goes to the course page.
- **Then and now, in every course's finale** (Hillel, 2026-10-29, docs/courses-ideas.md idea 4): a
  step marked `keep` in lesson 1 keeps its first heard take on the device (never synced, never sent:
  only the take and when it was sung); the finale's step marked `thenAndNow` with the same id shows it
  beside today's after the take. A play step gets a "Then and now" panel under its result: "In lesson
  1" (with the date it was sung) and "Today", a play button each, one playing at a time, on the audio
  clock, and no score, so it reads the same in both skins. A wave step draws both takes small with their
  lines, a bullseye step shows the first round faint behind today's ("Then: 2 of 5 within 25 cents.
  Now: 4 of 5."), a long note shows how much was steady. With nothing kept on this device, one line says
  to sing lesson 1 here first. A later take never replaces the kept one.

### 5.8 Scoring and stars

| Stars | When |
|---|---|
| 0 | "Couldn't hear you clearly": no stars, no XP, nothing lost, Try again first |
| 1 | Sung through and heard |
| 2 | 70% or more on pitch |
| 3 | 90% or more on pitch, with entries on time by a measured timing check (with an estimate, 2 and a note: "Three stars need a timing check...") |

- **The level word comes first, the number second:** Excellent (90%+), Close (70%+), Getting there
  (40%+), A tricky one. "{level}: {percent}% on pitch", "+{change}% since last time".
- **How it went:** the summary first (stars read as "2 of 3 stars"), at most one tip a visit
  (Dismiss, or "Don't show tips"); See details for what went well, up to three things to try,
  each phrase's stars with "Personal best", and the chart with a sentence that says it in words.
- **A lesson's stars** appear one by one with a rising chime; Replay is always one tap.
- **New stars are celebrated** (Hillel, 2026-10-27; `web/js/celebrate.js`), only when just earned
  (a lesson's end, the report straight after a take), never when looked at again:
  - **2 stars:** a sparkle on the stars and a light shower of about 20 pieces.
  - **3 stars:** a confetti burst of about 110 pieces from the stars, about 1.5 s.
  - In the accent and the part colours, on a canvas over the page, then gone.
  - **Reduced motion:** a glow on the stars instead, stronger for 3.
- **XP and levels** reward effort (a daily cap); the weekly goal (2, 3 or 5 days) is the only
  streak that asks anything of you; a missed week says only "New week, fresh start." The end
  screen shows days in a row with singing as a chip from 2 days ("3-day streak"), and never mentions
  a broken one.

#### 5.8a The result screens (Hillel's mockups 48 to 51, 2026-10-27)

Built: `web/js/results.js` (the maths), `web/js/components/results.js`, `web/js/views/learn.js`.
Every star, node and marked key is a glass gem (2.6a).
- **The level word:** Brilliant! (3 stars), Great! (2), Close! (1 star at 50% or more), Keep going!
  (under). It comes first and big, then a line that says what was done.
- **An exercise's result** (49), in place of the lane once it's scored: the word, "You held G3
  steady for 6.2 seconds." (one held note) or "You lit 7 of 8 notes." (several), and the stars on
  the right. Below that, a card with:
  - "Listen back" (your take, played on the audio clock) and "Target G3" (one note);
  - "With the guide", a switch under Listen back, off by default and remembered: your take with the
    guide singing your notes under it, a little quieter, mixed before it plays (never on a take
    already mixed with partner lines);
  - the target band for each note, tinted, with dashed edges as tall as the strictness;
  - your pitch as dots, one every 0.1 s: in the accent inside the band, grey outside, the scoop in
    included, folded into the target's octave;
  - the numbers: "94% on pitch", "6.2s longest hold" (a gap under 0.25 s doesn't break it; a wrong
    note does), "7 of 8 notes lit" (several notes), and "+25 XP" when finishing now would earn it.
  Then "Play again for more stars" (or "Replay" at 3), and the step's Continue or Finish. A take not
  heard keeps "Couldn't hear you clearly".
- **Your comfortable note** (48), the end of the lesson that finds it: the stars and "+25 XP", "Your
  comfortable note is", the note huge in the accent with a round Play gem, then a line about the
  parts notes around it suit. One note never names a voice type: "Notes around G3 suit tenor and
  alto parts.", or with a range of a fifth or more, "Your range suits tenor parts best." Then
  "Every lesson will now start here." A **piano keyboard** of about two octaves from the C below
  follows, with black keys, so a sharp note shows right. The easy range is tinted dark accent: the
  range check's when there is one, else a guess from three semitones under to five over, labelled
  "Likely easy range". The note is a gem; white keys are named; the legend reads "Your easy range"
  and "Your note". Then "Play again for more stars" and "Next lesson".
- **Lesson complete** (50 with 51's celebration at its top), two columns from 768 px, stacked on a phone:
  - **Left:** three star gems, the middle one bigger, popping in one after another with a glint
    for new 2 or 3 stars (the confetti of 5.8 as before). Then the word, a line ("Perfect score on
    your first try.", "Three stars. You went all the way.", "More stars than last time.", "Two
    stars. One more is in reach.", "Done. Play again for more stars when you like."), and chips:
    "+25 XP", "3-day streak" (in the warm part ink), "Lesson 3 of 5 done". Below them, the unit's
    path with labelled lesson nodes (done: a small gem with a tick; just done: a bigger gem; next:
    hollow; later: dim, each with its state for screen readers), then the level bar "Level 2 ·
    Warming up", "140 / 200 XP".
  - **"Quick one: how did you hear about Stackapella?"** as one-tap chips, once per signed-in
    person, with Skip.
  - **Right, the Up next card:** the next lesson's own Learn picture (no picture area when it has
    none), "UP NEXT · 4 MIN" (minutes from its steps), its title, its first headline as the line,
    a big "Next lesson", and "Play again for more stars" under it. With no next lesson open: a
    line and "Back to the course".
- **Did you know?** (2026-10-29, Hillel's pick from docs/courses-ideas.md): when the lesson has a
  `fact` (course file, `{ id, text, source, url? }`, steps.json lessonExtras), a calm card below the
  stars, the Up next card and the session wrap, full width: an info icon and "Did you know?", the
  fact, then "Source: Britannica, Tenor" small and muted, a link to the source's page when it has a
  url (opens outside the app). The same in both skins, never above the stars. One fact a lesson, one
  or two a unit, each told in one course only. Apps: show it the same way, or leave it out (the
  lesson still plays; it isn't web-only).
- **Reduced motion:** the gems are there at once, without pops or glints; the glow of 5.8 stays.
- **Keep this as a song** (a course finale that opts in with `keepSong`: Counterpoint's last lesson
  and Improvising a harmony's finale; docs/courses-ideas.md, idea 6): a card under the lesson end, in
  both skins and plain words: headed by the song's name, the line "Your takes and the other voices
  from this lesson, as tracks of a song of your own, to play again, change and share", and the button
  "Keep this as a song" ("Keeping it..." while it works). It makes an ordinary song on the device,
  named by the lesson ("My counterpoint", "Red River Valley, a trio of me"), the name that heads the
  card, so the heading says what you'd keep and the button is the action: one track per take you
  sang, one per partner voice and the chords, all lined up from the start, at the lesson's tempo and
  key. Then "Kept in your songs." and "Open your song", into the studio. A full free plan gets Home's
  Plus moment. It shows only when every take the finale names was sung in this visit. The lesson's
  own voices carry `fromLesson`, so the studio can badge them apart from your takes.

#### 5.8b The review after a take (Hillel's 79.png; it was "Just recorded")

Built: `web/js/components/sing/recorded.js`; the phrase feedback is `phraseReview` and `oneThing` in
`web/js/report.js`.
- **The parts across the top:** done ticked, yours outlined, the next marked "next".
- **The score is the headline:** "ALTO · YOUR TAKE" over "Close: 88% on pitch", and "Listen with
  the other parts, then keep it or fix the weak spot." No stars on this screen; lessons keep theirs.
- **A small mixer:** Play (a glass bubble), "Hear it with the others", the time and the phrase, a
  Polished switch, and each part's waveform with the playhead. Tap a part to mute it; yours is
  outlined.
- **Fine-tune the take:** two tiles, Nudge timing (opens the nudge and Line it up for me) and Trim
  the ends. With no phrase feedback (not heard yet), a third tile, Re-sing one phrase.
- **One thing to try:** the phrase with the most trouble, its words and the fix ("In phrase 1,
  “grace” and “sound” wavered. Take a fuller breath before the phrase so it lasts through the long
  notes."). The trouble is wavered (not steady), slid in (a scoop) or missed (not near); when all is
  well, "Every phrase was steady and in tune. Lovely."
- **The phrases** as cards: their words, the percent, and "2 notes wavered" or "Steady". The
  picked one (the one thing's, at first) shows its pitch line against its notes as glass gems, the
  troubled notes outlined in the warm part colour with a label chip ("“grace” wavered"), and a
  legend (Target, You, Needs another go). Under it: Play phrase N and Re-sing phrase N.
- **One clear decision, sticky:** "Saved automatically. You can come back to it later.", Record
  again, and "Keep it, record Soprano →" (or Share once every part is yours, or the studio).
  "Keep singing" is gone (Hillel: ambiguous). The bottom part strip doesn't show here.
- **Two columns** from a laptop (the mixer and fine-tune left, the feedback right), stacked on a
  phone.

### 5.9 The beta verdicts (built)

- Each beta take shows its **pitch and timing as percentages** and one verdict against the
  published bar: **Ready to send**, **Close** (with the phrases to fix, each with Loop), or
  **Not ready yet** [D 2026-10-27 "beta singers' scores"].
- **The bar is shown up front:** on the invite page and before recording (pitch 90 and timing 80
  by default, set in admin) [FE contract `bar`, contract/api.md `beta.submissions.create`].
- **Send appears only on Ready to send.** Below the bar the take can't be sent; the server refuses
  it too (`422 below_bar`).
- **After three tries below the bar** (on this device), the app kindly suggests asking Hillel for
  another part, or warming up with the course, and says they can come back any time.
- **The phrases to work on** are listed in the song's order, each named by its words ("Phrase 2:
  'that saved a wretch like me.'") with its reason ("A little flat", "Came in a little late") and
  Loop.
- Each problem keeps its words and fix pair (`beta.check.*` and `beta.fix.*`), with an icon,
  never colour alone [BS step 6].

### 5.9a The app's sounds (Hillel's picks, 2026-10-28)

Five short sounds, all CC0, in `web/sounds/` with `CREDITS.md` (the apps ship the same files):
- **Stars:** as a lesson's last star lands: one star `stars-1.mp3` (a harp glissando), two
  `stars-2.mp3` (chimes), three `stars-3.mp3` (a celesta glissando).
- **The mic heard:** `mic-ready.mp3` (a soft confirmation) when the mic check hears you for the
  first time, and when a voice brings Find your note's no-sound card back to listening (never for
  Try again, a mic change or a recount).
- **Something special:** `special.mp3` for a badge earned, a level up, a unit (or the course)
  finished for the first time, and the weeks' streak going up. Your first song with every part
  recorded earns the First harmony badge, whose sound it is (so it plays once, ever).
- Each plays once on the AudioContext clock, quiet (40%), at most 1.5 s, faded out. Never two at once:
  a special moment waits for the stars of the same moment to end.
- Settings, On this device: **Sounds** (on by default) turns them all off [FE 2026-10-28 sounds].

### 5.10 The parts, "You", and the mixer

- **A part's panel** (the studio's Voice and more, Hillel's 107.png): the part's colour dot before
  its name; Practise this part (a song's part); Guide voice as radio cards (Sung by ..., Recommended
  by Hillel or Another singer; Practice voice, "Steady synthesized pitch, easier to match"); Mix:
  Volume, Pan and Timing as sliders in the accent with their values on the right (100%, Centre, As
  recorded) and "Nudge earlier if this part sounds late, later if it's early."; the foot "Your
  take" with Delete take (red words), Trim and Re-record (a red dot). A part of your own song keeps
  its Name field and says Delete part. The mockup's play buttons beside each guide voice wait (they
  need a way to hear one voice without choosing it) [FE 2026-10-28 dialogs 107 and 108].
- **Harmonise this** (docs/harmonise-my-song-plan.md, the MVP): in the panel of a part of your own
  song (no template) that has a take, beside Trim. It opens the creator's Parts step on the take's
  notes (Step 2 of 2: Edit, then Parts), with the alto, tenor and bass suggested under it in the key
  it hears, the styles and Try another chord as in the creator; Back to Edit fixes a note. Its last
  button is "Add to my song": the same song gets the parts as guide parts (Alto, Tenor, Bass) after
  its own, the take becomes the tune's part untouched, and the studio opens on it. A take too unclear
  to arrange from (empty, noisy, fewer than 4 notes or over 40% unsure) gets "We couldn't hear the
  notes clearly", the likely reasons, Sing it again (back to the song) and Tap in the notes (the
  creator's Write it) [landing agent 2026-10-28, agreed with the frontend agent].
- **Add an instrument** (docs/mvp-music-plan.md, instrument parts): in the creator's Parts step,
  under suggested parts, "Add an instrument" with Piano, Guitar, Bass guitar and Drums as chips, up
  to two (a song has at most six parts). Each is a part played from the chords: the piano and guitar
  broken into eighths, the bass on the roots with a step into the next chord, the drums a plain
  groove. Hear it plays them too. They join the song (Add to my song, or a template's parts) as
  guide parts after the voices, credited "played by", with the real samples when they're there and
  the synth until then; the range check and the singer counts leave them out [landing agent
  2026-10-28, on the frontend agent's js/instruments.js].
- **The parts as lettered circles** (Sing, a phone): gone (Hillel, 2026-10-28). The part chip in
  the header chooses the part; a wide screen keeps the parts strip.
- **"You"** is always your part's colour (the Alto is always place 2), in Sing, the studio's row
  tag "You", and the home's lanes ("Alto · you").
- **Studio rows:** the letter circle (filled once recorded, outlined while it's a guide), the name,
  who sings it ("Sung by {name}", "Practice voice (in your key)", "Guide"), its waveform, and
  **Solo** and **Mute** as words, pressed when on. Solo plays only the soloed parts; a part that
  isn't heard says "Muted" or "Not soloed". Level and pan inline from 768 px, in the opened row
  on a phone.
- **Mute, solo and level stay in the studio** (Hillel, 2026-10-28): Sing plays the arrangement, and
  its own What you hear turns parts off for a visit [SUX "UX"]. The timeline and pan stay left to right in right-to-left languages.

---

## 6. Words

### 6.1 Where they come from

- **Every word people read is a key in `web/locales/<lang>.json`**, through `t()` (web) or the
  port of `t()` in `lib/app/l10n/words.dart` (apps) [M:decisions.md "the skeleton: look and
  words"]. **Both front ends use the same keys.** A new word is added to `en.json` first, in the
  change that needs it; the apps sync it with `tool/sync_from_web.sh`.
- The public site's words are the server's (`server/lang/en/`); emails too.
- Song titles, part names people typed, and names are content: marked `translate="no"` and never
  translated.

### 6.2 Voice and tone

- **Plain, warm, short.** Sentence case everywhere, buttons included. Everyday words: "delay"
  not "latency", "on pitch" not "intonation", "phrase" not "segment".
- **Kind and honest.** Say what went well first. Never "wrong", "failed" about the person, or
  "error". Problems are the app's ("We couldn't hear you clearly"), fixes are concrete ("Move
  closer to the mic, or sing out a little more.").
- **Who speaks:** "we" for the app, "you" for the person, Hillel by name in the beta.
- **No em dashes** in UI text or docs [C "Never"]; use a colon, a comma, "and", or two sentences.
  Straight apostrophes and quotes in English keys.
- **No pressure:** no exclamation-mark nagging, no "Don't miss out", no countdowns. Celebrate
  real moments ("Every part is you!", "Lovely. You sang the alto on your own.").

### 6.3 Numbers, times and plurals

- Plurals through ICU messages in the locale file: `{count, plural, one {# part} other {# parts}}`.
- Numbers, dates, durations and prices through `Intl` (web) and the locale (apps).
- Times as m:ss ("0:07"), milliseconds as "{ms} ms", percentages as "92% on pitch", tempo as
  "{bpm} BPM", notes as "D4" (or do re mi, 1 2 3, or off, the person's choice in Sing).
- Prices yearly first, as a month: "$3.33 a month, $39.99 billed yearly", in the region's currency.

### 6.4 The names of things

| Word | Means | Not |
|---|---|---|
| **song** | What a person sings: one of ours from the library ("Songs to learn"), or their own, made in the studio ("Your songs", "New song", "Delete song", "Song settings", "3 songs"; "Untitled song" when blank) [FE 2026-10-27 project to song] | "track", "project" (the code's word only) |
| **part** | One line of the harmony: Soprano, Alto, Tenor, Bass, or a named part (an instrument is welcome) | "track", "stem" (except the Plus export "Each part") |
| **voice** | A part in a plain project ("Voice 2", "Add voice 3"); who sings a guide ("Who sings the Alto for you") | |
| **take** | One recording of a part | "clip" |
| **guide** | The voice you learn from: "Guide", a real voice ("Sung by {name}"), or "Practice voice" | "reference" |
| **harmony** | The whole stack sung together, especially when shared ("The whole harmony", "Learn a harmony") | |
| **template** | The creator's word for a song someone made ("Your templates") | in learning screens |
| **phrase** | A line of a part, as practised and scored | "segment" |
| **Plus** | "Stackapella Plus" the first time on a screen, then "Plus" | "Premium", "Pro" |

### 6.5 Coming soon, and what isn't built

- **In the app** (web and apps): a feature that isn't built or is switched off **doesn't appear**
  [D 2026-10-26 "the home page"].
- **On the public site:** every planned feature shows, labelled **"Coming soon"**, tied to its
  feature flag, with no dates [D 2026-10-27 "the landing page"].

### 6.6 Ages

- Sign-up asks "When were you born?" (month and year). Since Hillel's decision of 2026-10-28 it is
  the only age question: the old "13 or over" answer is gone. Any account with no birth date (a
  first Google or Apple sign-in, or one that only answered the old way) is asked once; under 13,
  that account is deleted at once and the app carries on without one [FE 2026-10-28 no ageConfirmed].
- Choir groups, coaching, Send to my teacher and anything public about a person: "I'm 18 or over",
  asked once when it's first needed.
- **Apps:** rated 13+, not directed to children; sign-up also reads Apple's Declared Age Range and
  Google's Play Age Signals where the law asks [D 2026-10-27 "native mobile apps"],
  [M:children-and-app-stores.md].

---

## 7. Accessibility

| Area | Rule | Source |
|---|---|---|
| **Contrast** | WCAG AA in every theme with every accent: 4.5:1 for text, 3:1 for large text, field boundaries and part colours. The `contrast` flow measures every button and link at rest, hover, active and focus in all ten palettes with all eight accents | [FE 2026-10-27 buttons' hover], [FE 2026-10-27 refined theme picker] |
| **Never colour alone** | Status is an icon plus words; lit notes also change shape (a filled bar); problems carry an icon; to-check notes carry an icon | [VC "Accessibility"], [TC "Accessibility"], `StatusChip` |
| **Screen readers** | Icon buttons have names ("Mute Alto", "Your part: Alto. Change part or voice"); stars are read as "2 of 3 stars"; the chart and the tuning meter have sentences; Your turn and Get ready are announced politely once per change; edits in the creator are announced ("D4, quarter note, 'grace'") | [SUX "Review notes"], [TC] |
| **Focus** | Visible: a 2 px `accent` outline 2 px out on keyboard focus. Order follows reading order. Sheets trap focus and return it. Radio groups use arrow keys, Home and End | `web/css/input.css`, `Modal` |
| **Keyboard** | Everything works by keyboard. Shortcuts (web and desktop apps): Space play, pause or stop recording; R record; in Sing also 1, 2, 3 for the modes, L loop, N first note, V switch the view; the creator's sheet on `?` (arrows, Shift for octaves, 1 to 6 lengths, Cmd or Ctrl+Z undo). Shortcuts never fire while typing or in a dialog. Single-letter keys can be turned off (WCAG 2.1.4) | `web/js/views/sing.js`, `studio.js`, [TC], [FE M2 review keys] |
| **Text scaling** | The web uses rem, so the browser's text size scales it; nothing breaks at 200%. **Apps:** honour the system text size up to 200%, wrapping rather than truncating; only the Stage words may cap their scale | (Proposed for the 200% check) |
| **Reduced motion** | No fades, pulses or pops; the lane and playhead step; the words and chimes stay | section 2.7 |
| **Right to left** | Logical layout (start and end), mirrored chevrons and meters, but time and pan stay left to right | [C "Frontend"], [FE M1 timeline] |
| **Targets** | 44 px web, 44 pt iOS, 48 dp Android hit areas | [TC "Accessibility"] |

---

## 8. Offline and sync

### 8.1 What works offline

- **Everything that doesn't need another person or a payment:** the app shell, the course (`/learn`
  pages and lessons), the four free songs (bundled), Sing, the studio, recording, trimming, the
  report, WAV export, and recorded guides once cached [FE 2026-10-27 course offline].
- **Plus songs** already started stay usable offline for 14 days after the plan was last checked;
  then the project is read-only with "This Plus song needs a quick check online..." [features.md].
- **Not offline:** signing in, sharing, buying Plus, opening a new Plus song, Contact us; each says
  "You're offline. Check your connection, then try again."
- **No account needed** to learn or record; it's asked only to save to the cloud, share, buy, or
  at the free limit [D 2026-10-26 "the vision and the roadmap"].

### 8.2 Saving states

| State | Words (`cloud.status.*`) |
|---|---|
| Checking | "Checking your account." |
| Changes waiting | "{count} changes are on the way to your account." |
| All saved | "Everything is saved to your account." |
| Offline | "You're offline. Your work is safe on this device and goes to your account when you're back." |
| Email not confirmed | "Confirm your email and your recordings go to your account. Until then they're safe on this device." |
| Storage full | "Your storage is full, so new recordings stay on this device for now." |
| Read-only | "Some songs are past the free plan's limit, so their changes stay on this device." |
| Signed out | "Your songs are on this device only. Sign in to keep them in your account and open them anywhere." |

- The Storage page (`/storage`) shows the meter (amber from 80%), these states, Sync now, each
  project's size, and Recently deleted with Restore and the date each goes for good.
- A project not in the account says "Not saved to your account" in `warn` with its icon.
- "Your songs are only on this device" with Install on the home when some are [FE 2026-10-27
  Plus has no project limit].

### 8.3 Conflicts

- **"Changed on another device"**: one modal at a time, **Keep mine** and **Use theirs**, with no
  way to wave it off, and **never shown while singing or playing** (it waits for Stop)
  [FE 2026-10-27 cloud save].
- A delete that met a change elsewhere: **Delete it** or **Keep their version**.
- A difference only in practice settings is merged without asking.
- Another device's change to the open project: "Updated with a change from your other device.",
  at once when idle, else when playback or recording stops.

### 8.4 The outbox

- Every change waits in the outbox with its own `changeId`, in the server's order, retried with a
  growing wait [M:offline-store.md], `web/js/sync.js`.
- **Visible states:** waiting (the count above), sending, sent, and **refused**: a change refused
  for good is kept with the reason in words, for the person to fix or discard; never dropped
  silently.
- Beta takes: "Your take is on its way to Hillel. It's kept on this device until it arrives.",
  with the Wi-Fi tip on mobile data.

---

## 9. Platform-specific UX

| Topic | Web | Apps | Must not diverge |
|---|---|---|---|
| **Sharing** | Copy link, Post to X, Post to Bluesky, and "Share..." where `navigator.share` exists | The native share sheet for links and the video file | The share sheet's steps, choices and words (`share.*`) |
| **Back** | The round back button and the browser's Back | Android back and iOS edge swipe, same targets | Blocked while recording; Sing returns where you came from |
| **Haptics** | None | (Proposed) sparingly: a light tick when a lesson's stars land and on toggles; **never while the mic is open** (the motor is heard by the mic) | No meaning carried by haptics alone |
| **Notifications** | Practice reminders by web push, opt-in from the Turn on tap only; at most one a day on the chosen days; a tap opens the course; on iPhone outside the installed app it says to add it to the home screen first | Native push for the same reminders (device registration in [M:token-auth-plan.md]); ask permission only from the Turn on tap | Content and frequency; nothing else is pushed; the in-app bell is separate |
| **Install** | The install card ("Install Stackapella"), Not now hides it | None: the apps are installed | |
| **Buying Plus** | Stripe Checkout and the portal in the system browser; the upgrade moments | **Open: in-app purchase screens wait for Hillel's decision** [M:roadmap.md step 4] | No upgrade prompt while singing, no countdowns, the same comparison rows (`docs/features.md`) |
| **Mic permission** | The browser's prompt from a tap | (Proposed) the explainer card, then the OS prompt; Open Settings when denied | The words |
| **Desktop** | Hover states, shortcuts, the `kbd` hints | macOS, Windows and Linux: the same shortcuts (Cmd on macOS, Ctrl elsewhere), hover states, a resizable window down to 320 px wide following the same breakpoints, input and output device choice (and input channel) in Microphone and timing [M:roadmap.md "Desktop"] | Layouts at each width |
| **Keep awake** | The Wake Lock API while recording and practising | The platform's keep-awake | |
| **Updates** | "A new version is ready" | (Proposed) the same card on a 426, opening the store | Never during recording, a lesson or a workout |

**App-store rules that shape the UX:**
- **Delete my account** must be reachable in the app (Apple 5.1.1(v)); it's in Settings, as on the web.
- **Sign in with Apple** beside Google (Apple 4.8): already the web's order (Google and Apple first).
- **Digital purchases** (Apple 3.1.1, Google Play's payments policy) decide whether Plus is bought
  in the app or not shown there: open, with Hillel.
- **User content** (Apple 1.2): every place that shows other people's content has Report, and
  blocking where people can interact, as on the web.
- **13+ rating**, no Kids category or Families listing.

---

## 10. Keeping this doc true

1. **The web is the reference implementation today.** When the web and this doc disagree, the
   newest dated decision wins; fix whichever is wrong in the same change.
2. **A UX change lands in this doc in the same commit** as the code that makes it (web or app),
   with its source in brackets; a new decision is logged in `docs/decisions.md` first.
   The same commit adds one line to **`docs/ux-changes.md`**: the date, the commit, this doc's
   section, and what changed and why. It's the apps' to-do list (Hillel, 2026-10-27); the commit is
   filled in once it's known, by the next commit if need be.
3. **The apps check it every release:** a pass through sections 2 to 9 against the build, and the
   words synced (`tool/sync_from_web.sh`), noted in the mobile repo's launch checklist.
4. **Screenshots of the key screens in both,** at 390 by 844 and 1440 by 900, in Warm dark and
   Warm light, kept beside each other for comparison (Proposed location: `docs/ux-screens/web/`
   and `docs/ux-screens/apps/`): Home (first visit and returning), Sing (idle, Get ready, Your
   turn, recording, Just recorded, How it went), the studio (empty, with parts, recording), the
   course map and a lesson's end, the Theme panel, Sign in, a confirmation, the conflict
   modal, Share, and Plus.
5. Proposed items become rules only when Hillel (or, during the build, the coordinator) confirms
   them; then their "(Proposed)" mark goes.

---

## 11. Open questions, and where the decisions and the web disagree

### 11.1 Open questions

| # | Question | Today |
|---|---|---|
| 1 | **The font** | Settled: Figtree (section 2.4) |
| 2 | **In-app purchase UX** in the apps (store purchase, or no purchase in the app) | Open [M:roadmap.md step 4] |
| 3 | **The beta verdict "Close"**: its lower edge, and how many "several tries" are before the kind suggestion | Settled: within 15 points on pitch and 20 on timing; 3 tries [FE 2026-10-27] |
| 4 | **"Close" means two things:** the report's level word (70% to 89%) and the beta verdict (below the bar but near). Rename one? | Both decided |
| 5 | **The mic explainer before the OS prompt**: proposed for the apps; should the web have one too? | Settled: the web has one too (section 5.1) |
| 6 | **Haptics** as proposed in section 9 | None anywhere |
| 7 | **No tab bar in the apps** (the home as hub, as on the web) | The web has none |
| 8 | **Get ready's "Breathe in"** and the next line, in [SUX], aren't on the built card | Card shows "Your turn in N beats" |
| 9 | **"Hum a tune"** replaces "Make your own" on the home [D 2026-10-27 "your tune, sung"] | Waits for the feature; the home still says "Make your own" |
| 10 | **Text scaling to 200%** and a desktop window's minimum and default size | Not set |
| 11 | **Screenshot location** in section 10 | Proposed |

### 11.2 Where the built web differs from a decision or from itself

| # | Found | Decision or rule | Built |
|---|---|---|---|
| 1 | **The home's welcome lanes paint the Alto ("you") in place 4 (teal)**, the Tenor in 7 and the Bass in 6 | "You" in your part's colour, the Alto always place 2 [D 2026-10-26 "the Sing redesign"]; the landing uses places 1 to 4 | Fixed: Soprano 1, Alto (you) 2, Tenor 3, Bass 4 [FE 2026-10-27] |
| 2 | **The default theme's fallback** in CSS is Warm dark | Unchosen dark is Stage [D 2026-10-27 "themes and songs, adjusted"] | Fixed: @theme's fallback is Stage dark (`tool/themes.py`) [FE 2026-10-27] |
| 3 | **Selected and "on" states** don't all use the accent | "Selected choices use the accent everywhere" [FE 2026-10-27 polish pass, second part] | Fixed: the Toggle, Solo, toast actions and Hear it polished use the accent [FE 2026-10-27] |
| 4 | **Words on a filled part circle** in Sing use `ink` | Words on a part fill use `part-text-N` | Fixed: `paintText` (part-text-N) [FE 2026-10-27] |
| 5 | **Tap targets under 44 px** | 44 px [TC "Accessibility"] | Fixed: backs and Settings 44 px; chips and Words / Notes keep their look with a 44 px tap area (`tap-44`) [FE 2026-10-27] |
| 6 | **Single-letter shortcuts can't be turned off** in Sing and the studio | WCAG 2.1.4, done for the admin review [FE M2] | Fixed: Sing's settings have Keyboard shortcuts (on by default; off turns off the letters in Sing and the studio; Space always plays) [FE 2026-10-27] |
| 7 | **"Phone" is 640 px in one place and 768 px elsewhere** | One set of breakpoints | Fixed: a phone is under 768 px everywhere (the theme sheet too) [FE 2026-10-27] |
| 8 | **The turn colour on a phone** | A band at the top [D 2026-10-26 "the Sing redesign"], replacing [SUX] decision 4's full-colour stage | Fixed: the unused `.turn-stage` rules are gone [FE 2026-10-27] |
| 9 | **Three placeholders for a blank title** | "Untitled song" [FE M1] | Fixed: "Untitled song" everywhere [FE 2026-10-27] |
| 10 | **"Make an account" and "Create an account"** both used | One name | Fixed: "Create an account" [FE 2026-10-27] |
| 11 | **A stale Plus line** | Plus exists | Fixed: "or get Plus for unlimited songs" [FE 2026-10-27] |
| 12 | **Mixed apostrophes** | One style in English keys (this spec says straight) | Fixed: straight apostrophes throughout `en.json` [FE 2026-10-27] |
| 13 | **The decisions log says open questions are at its end**, but there's no such section | | Fixed: it points to section 11.1 and the plans [FE 2026-10-27] |
