# The marketing site, redesigned

Status: approved by Hillel, 2026-10-29 (all four questions yes); building in the order below.

Hillel asked for a best-in-class public site: "have marketing redo each page to improve the
design", "a full review of the marketing pages with design ideas from these samples. I like keeping
the bubbly style of marketing to match the app though", and "try to take whats good". This plan is
the review of all 37 pages (the shots are in `~/Code/shots-review/marketing/`, phone and desktop),
one shared section system, the page-by-page changes, and the order of work.

## What we take from the samples, and what we keep

The samples (9.png, the hero now built in 0b4f3277; 10.png, a "Guide voices" section on a dark
band) are good for five reasons, and we take all five:

1. **An editorial type scale.** A small coloured label, then a big, tight headline, then a calm
   paragraph at a readable size. Ours is upside down in places: related links ("Goes well with",
   48px) outrank the content headings (34px), and leads are small grey text.
2. **Calm spacing.** Fewer, larger things, with room around them.
3. **Real product mocks beside the copy, at real scale.** The sample's share card fills its frame.
   Ours often float small in a big cream box with a hairline border, and read as empty wireframes.
4. **Dark bands for rhythm.** Ours is one long cream scroll: about ten sections on the landing, all
   on cream, with nothing saying "new chapter".
5. **Ticked points and a text link with an arrow**, instead of more cards or ghost buttons.

We keep what's ours: the glossy glass buttons and gem notes, the round bubbly shapes, the four part
colours, every theme (light and dark), the drawn pictures made from the app's own words, and the
rules (no left-edge stripes, no em dashes, nothing unbuilt claimed, no famous people, no picture
twice on a page, no Vue on public pages).

## The shared section system

Every page is built from these sections, as Blade partials under
`server/resources/views/components/site/sections/`, styled in `web/css/input.css` (`.s-*`). A
page's words stay in `lang/en/site.php` and `pages.php`; the partials take words, never hold them.

**Foundations, first:**

- **Type scale.** Hero H1 56 to 96px (the landing keeps its 136px), page H1 40 to 64px, section H2
  36 to 48px, row heading 28 to 36px, eyebrow 12px capitals in an accent, lead 19 to 21px in the
  body colour (not muted), body 17px, small 14px. Supporting headings ("Goes well with", "Related")
  drop to eyebrow size with the link on the right.
- **The ink band.** A full-bleed section on near-black in the light themes (each light theme's own
  dark twin, emitted by `web/tool/themes.py` as `.s-ink` variables, so the glass, gems and part
  colours inside it render exactly as the app's dark theme does); in the dark themes, a raised
  panel with a stronger edge, so the rhythm survives. At most two per page.
- **The picture surface.** `.s-pic` becomes a raised glass card with depth and a soft shadow (no
  hairline box), sized so the drawing fills it; chips inside drawings become glass. The hero
  picture is one size up from the rest.
- **Spacing.** Sections 96px apart on desktop, 64px on a phone; one width (1200px) with a reading
  measure (68ch) for prose.

**Sections:**

| Section | Desktop | Phone |
|---|---|---|
| `hero` | Eyebrow, H1, lead on the left with two glass buttons on the right (sample 9), or a split with the page's own picture on the right | Stacked, buttons full width, picture last |
| `hero-soon` | A glass "Coming soon" pill, one "Where it stands" line, the button to what works today, a picture marked "Preview" | The same, stacked |
| `facts` | Three or four short true facts in a row, each with a small gem | 2 by 2 |
| `steps` | Numbered gem discs joined by a line, only for a real order | Vertical, the line at the side |
| `split` | Eyebrow, H2, short paragraph, up to three ticks, a text link with an arrow; the picture takes the bigger column (7/5), sides alternate | Copy, then the picture full width |
| `band` | The sample-10 moment on the ink band: a `split` with a large mock | Stacked, the mock last |
| `bento` | A 12-column grid of mixed spans with real mini mocks (the features page's "Works today", our best card work, kept) | One column, mocks capped in height |
| `details` | Two or three columns of short items with an icon, for sections without a picture | One column |
| `promises` | Ticked trust points, usually inside a band | The same |
| `plan-strip` | A small Free and Plus comparison with a link to Pricing | Stacked |
| `chips` | A row of glass chips with icons (audiences, coming features) | Wraps, or a scroll row with faded edges |
| `rail` | Three related tiles, chosen per page, under a small heading with "Everything it does" on the right | A scroll-snap row |
| `faq` | Heading left, glass plus and minus folds right | Stacked |
| `end` | The page's own last word, two buttons and the stacked-bars mark, on the ink band when no band is near | The same |
| `article` | 680px column, 17 to 18px body, a 20px lead, a meta line, an optional sticky contents list | One column, one "Contents" fold |
| `callout` | Tip, Note or Fix: a raised glass panel with a round icon chip, never an edge stripe | The same |
| `table` | Grouped rows with icon cells (no em dash for "not included"), sticky header | Sticky header, labels wrap to two lines at most |
| `timeline` | A date column on the left, entries at a reading measure | The date as a sticky chip |

## Page by page

### Landing

Keep: the hero (done), the four-voice mixer (the best thing on the site), the four voices as four
steps, the FAQ, and "Your first voice is the hardest."

1. `hero` with the mixer (done), then the showreel when it's available.
2. `facts`: free to start, runs in your browser, every song in four parts, N songs to learn (the
   count from the catalogue; no invented numbers).
3. `steps` as a `bento`: "Four voices, four steps", Learn and Share wide, Real voice and Record
   narrow; the mocks show progress (days ticked, stars lit), not empty states. The leftover
   paragraph about coaches goes (it's an FAQ answer already).
4. `band`: **Guide voices**, sample 10 almost as drawn: "Your voice could be the one others learn
   from.", the three ticks (opt-in each time, 18 and over with everyone on the song agreeing; a
   harmony shared only by link is never used; opt out any time), "How guide voices work" linking
   to Real voices, and the real share card (Link only, Public, "Let Stackapella use my part as a
   guide voice"). "We may also feature it on X and Bluesky" stays: the share sheet and the terms
   both say so.
5. `split`, mirrored: "Got a tune in your head?" with the creator's Sing it, Write it and Start
   from chords. It says exactly what works today, so it can't be confused with Hum a tune.
6. `chips`: who it's for, eight glass chips to the /for pages (coaches marked Coming soon), not
   eight cards. The same row on Features, instead of a copied block.
7. Plans: two compact cards and "Compare the plans", no toggle (Pricing has the whole thing).
8. On the way: one row of up to six chips and "See what's new". Today the landing says "coming
   soon" more than ten times and reads half built.
9. `faq`, its lead "Short answers. More in Help."
10. `end` on the ink band.

### Pricing

Keep: the headline, the currency switch, the Free card, the grouped comparison and the fine print.

1. `hero` with the currency switch and, where it applies, Monthly and Yearly (one pattern
   site-wide).
2. The plan pair. While checkout is paused, Plus says so on the card itself, not only in small
   grey text under it.
3. The comparison `table`, straight after the plans (it's why people come): sticky Free and Plus
   header on a phone, an icon for "not included" (today it's an em dash).
4. `faq` with the landing's glass folds and only true answers (cancel any time, what happens to my
   songs, the trial).
5. **Courses coming to Plus**, moved below the table onto the ink band: one Coming soon pill for
   the section and "The first lesson of every course is free" (Hillel, 28 Oct), then three columns
   of names (Singing skills, Styles, Beyond singing). Today it's 27 cards with a badge each, about
   60% of the phone page, between the plans and the table.
6. `end`.

### Songs

1. `hero`: an eyebrow that says something ("The songbook") and the count ("N arrangements, M free
   to sing now").
2. `split`: the song of the month, with a play bubble (the hero mixer's player, already public
   code) and its four part ranges.
3. Free to sing now, then With Plus: each card with a play bubble, its range bars instead of the
   repeated glyph, and its style; filter chips above. The lead promises "Hear any of them before you
   start" and today no card plays.
4. `end`: "Pick a part and start."

### Features (the overview)

1. `hero`, split, with a picture not used in the bento.
2. `bento`: Works today, kept, each card with two lines and an arrow to its page.
3. `band`: one deep dive (the timing check: "every part, exactly in time").
4. On the way: `chips` with one line each, linking to What's new.
5. `chips` for who it's for (shared with the landing), then `end`.

### The feature pages (15, one template)

Today: four identical rows on cream, then cards, then tiles; pictures small in empty frames; the
related links outrank the content; "Start singing" is the only button, even for coaching and
leagues; 9,000 to 10,000px tall on a phone.

The new template: `hero` (split, the page's own picture; learn-a-part and studio stop sharing the
mixer), `facts`, at most three `split` rows with bodies of 35 words or fewer, one `band`, then
`details`, a `plan-strip` where Free and Plus differ, a `rail` chosen for this page, and the page's
own `end`. Coming-soon pages use `hero-soon`, `steps` for how it will work, `promises` in the band,
a "You can already" bridge to what works today, and their own `end` (never the generic one).

| Page | Specific changes |
|---|---|
| Learn a part | Own hero (the Sing stage). The recording picture draws your voice as a line; the app now shows a dot with a trail: redraw it. The recording stage is the band. |
| Course | The unit list as a numbered path, not eight ticks; a bigger course path in the hero; the `plan-strip`. |
| Studio | Own hero (the stack or the recording view); the timing picture's clipped waveform fixed; an instrument row ("Ukulele") in the instruments picture. |
| Real voices | The Choose your guide picture has two identical options: fix; the Credited picture shows a credit ("Sung by"); one of its pictures repeats learn-a-part's lane. |
| Share | A large vertical-video frame with the words lit; Free and Plus into the strip; Help others learn and Yours to use as the band's promises. |
| Create | "Coming: instrument parts" and the chorus and bridge line wear a Coming soon chip, not a tick. |
| Choirs | Hero and assign pictures are near twins: use a "See who's ready" picture; the button speaks to directors. |
| Hum a tune, Coaching, Gallery, Leagues, Library, Virtual choir, Song requests, Apps | `hero-soon` with an honest Preview drawing instead of an empty icon box; the button to what works today (the creator, the course, installing the app); steps; trimmed cards. |

### The "for" pages (8, one template)

Today: "How it works" numbers things that aren't steps (a checklist, Today and Coming soon mixed),
pictures are tiny in big frames, the `share` picture stands in for unrelated things, coming-soon
tiles are bare icons, and fewer than three tiles leaves a hole.

The new template: `hero` (split, the right picture at full size), `facts` (for beginners, "What
you need"), `steps` only where there's an order, one `band`, up to two `split` rows, a Coming soon
list kept apart from what works, the quiet notice under the section it qualifies, "What you'll
use" tiles (built features only, a real picture each, the grid adapting to two or three), `chips`
to the other audiences, and the page's own `end`.

Per page: beginners' band is kind feedback (lights up, never red, waits for you); choir singers
use `export` or `practice` for practice tracks; directors use `assign` in the hero and `groups` in
the band ("see who has their part", never their recordings or contact details); a cappella uses
`timing` and `video`; songwriters split Today (the band, `singline`) from Coming soon;
instrumentalists stop showing two near-identical pictures; friends and family get their own hero
(`listener`) and no tile gap. **Coaches** needs the most: the whole offer is coming, so the hero
wears the pill and its button is "Write to us to be among the first" (our contact address, no
list kept), its five pseudo-steps become what you'll offer and how you'll be paid, and the coach
icon stops appearing twice.

### About, press, what's new

- **About:** the letter in a centred 68ch column at 18px with the signature (the words are
  approved and stay), a pull line beside the hero on desktop, a facts card (address, contact, press
  kit), and an `end`. A band of the letter's own promises only if Hillel likes it (it repeats
  approved words).
- **Press:** left-aligned like every other page, the press contact first as a glass button,
  boilerplate cards that select in one tap, the mark on light and dark tiles with its downloads as
  chips, facts as a definition list, and the screenshots as a gallery on the ink band (desktop two
  up, phones three up at one ratio; a scroll row on a phone, which today is about 12,000px).
- **What's new:** a `timeline`, the newest big change as a feature card with its picture, area
  tags as small pills in part colours instead of the same sparkle on every entry, and an anchor per
  entry.

### Guides, help and legal: one reading system

One `article` layout and one header pattern (eyebrow and breadcrumb) for all three, "Guides" in the
top nav, leads at 20px in the body colour, and a single "Contents" fold on a phone.

- **Guides index:** an editorial header, one featured guide (How to sing harmony) with a mixer
  mock, then three groups (Start here, Learn your part, Skills), each card with a part-colour glyph
  and the song it ends in, and an `end` band.
- **A guide:** the best page today (the measure and the mixer where the steps begin are right).
  Drop the paragraph that repeats the lead, add "Updated" and the reading time, put the song card
  at the end on a phone, and don't follow it with a second "start a song".
- **Help index:** search first, Start here as one numbered path, then six even group tiles of title
  links (no summaries repeated, no page listed twice; today mic, headphones and timing each appear
  twice), "Still stuck?" on the ink band.
- **A help article:** screenshots framed and beside their steps on wide screens, cropped to the
  moment (some clip a card or carry 200px of empty frame; the frontend is reshooting the guide's
  shots), prose for description and numbered steps only for actions, 32px under the lead, a
  Previous as well as a Next.
- **Legal:** a sticky contents list with anchors (the prose gives headings no ids today), a 680px
  measure (it runs to about 90 characters a line), "The short version" in a raised glass panel,
  retention periods and processors as tables, and "Last updated".

## Fixes to make whatever the design (found in the review)

- **The legal pages show unfinished placeholders live:** "[16 or over...]", "[What happens to
  inactive free accounts.]", "[period]", and in the accessibility statement "[date]", "[how we
  checked...]" and "A draft our lawyer is still checking". These wait for the lawyer and Hillel,
  not design; they should be hidden or finished before launch.
- **Choir groups:** Features lists them under "Works today"; Pricing ticks them for Plus only; while
  checkout is paused nobody can get Plus. Say "with Plus" on Features.
- **Ticks for unbuilt things** (Create's "Coming:" lines): a Coming soon chip instead.
- **Coming-soon pages ending with "Start singing" and the generic end card.**
- **The pricing table's em dash** for "not included".
- **The learn-a-part words now say "a glowing dot"; its picture draws a line.**
- **The coaches page shows the coach icon twice.**

## The order of work

Each batch is built, then shot at phone and desktop in light and dark, sent to the coordinator, and
landed with the full suite, the web tests and `landing_audio.py`:

1. **Foundations:** the type scale, the ink band (with `themes.py`), the picture surface, the
   section partials. No page changes look yet, except type sizes.
2. **The landing.**
3. **Pricing and Songs.**
4. **Features, the feature template, and the 15 feature pages** (the built ones first, then the
   coming-soon variant).
5. **The "for" template and its 8 pages.**
6. **About, press and what's new.**
7. **Guides, help and legal** (the reading system).

Each batch keeps the public pages free of Vue, their performance budget (no new library; the
song previews reuse the mixer's player), accessibility (contrast on the ink band in every theme,
headings in order, focus visible, reduced motion respected), and both themes.

## Hillel's answers (2026-10-29: yes to all four)

1. **About:** a band of the letter's own promises (private unless shared, kind feedback, no
   tracking, credited public-domain songs), repeating approved words. Yes or no? Recommended: yes.
2. **Songs:** a play preview on every song card (the hero's player, no new library). Recommended:
   yes.
3. **Coaches:** "Write to us to be among the first" as its button, a plain email to our address (no
   list kept). Recommended: yes.
4. **Pricing:** the courses moved below the comparison table as three lists on the ink band, rather
   than 27 cards above it. Recommended: yes.
