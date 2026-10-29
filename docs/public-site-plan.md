# The public site: its pages, addresses and shared parts

**Status:** planned 2026-10-27 by the landing agent, from Hillel's "keep going well beyond the
landing" (relayed by the coordinator). The landing (direction A, polished as board-a2) comes first;
then the pages below, one at a time, each landed on `main` with its tests.

Back: [Go to market](go-to-market-plan.md) · [The app's host](app-subdomain-plan.md)

## The rules every page keeps

- **Direction A's look:** the same header, footer and themes (the tokens, `theme-boot.js`), the
  app's buttons and cards, no coloured stripe down any edge, no em dashes, sentence case.
- **Honest:** no invented numbers, quotes, ratings or logos. A feature that isn't open says
  "Coming soon", in one quiet label, tied to its flag (below), never a date.
- **Voices first, instruments welcome:** singing leads; nothing says "voices only".
- **mudeo only for mudeo's singers** (`MudeoArrival`): nobody else sees the word.
- **Real playable mixers where they help** (the landing's player, `js/public/landing.js`), never
  autoplaying, always with the words as text.
- **Fast and light:** server-rendered Blade, a little JavaScript, no new libraries.
- **The app's links** are absolute on the app's host (`Hosts::app()`), carrying `?ref=`.

## The site map (all on the public host)

| Address | Page | Notes |
|---|---|---|
| `/` | The landing | Built first |
| `/features` | What Stackapella does: an overview, a card per feature | |
| `/features/learn-a-part` | Sing: guide voices, the note lane, live pitch, your notes lighting up | A playable mixer |
| `/features/course` | The course: units, stars, today's workout | |
| `/features/studio` | Stack parts exactly in time: the timing check, trims, Make it sound good | |
| `/features/real-voices` | Guides sung by real singers, credited; choosing a version | |
| `/features/create` | The creator and song builder: write or remix a song | Coming soon until on |
| `/features/your-tune` | Hum a tune: hum or draw it, hear it harmonised, share it as an idea | Coming soon until on |
| `/features/share` | Share links, videos with the words, export (WAV, MP3, stems) | |
| `/features/coaching` | Take reviews and lessons from approved coaches | Coming soon by flag (`coaching`) |
| `/features/choirs` | Choir groups: share songs, assign parts | |
| `/features/gallery` | The gallery, open parts to join | Coming soon by flag (`gallery`, `open_parts`) |
| `/features/leagues` | Leagues and friends' leaderboards | Coming soon by flag |
| `/features/library` | The public template library | Coming soon by flag (`template_library`) |
| `/features/virtual-choir` | The virtual choir | Coming soon by flag |
| `/features/song-requests` | Song requests and votes | Coming soon by flag |
| `/features/apps` | The apps for phone and desktop | Coming soon (config list) |
| `/for/beginners` | Never sung harmony? Start here | |
| `/for/choir-singers` | Learn your part before rehearsal | |
| `/for/choir-directors` | Directors and music teachers: groups and parts | |
| `/for/a-cappella` | A cappella creators and covers | |
| `/for/songwriters` | Got a tune in your head? | Mostly coming soon, honestly |
| `/for/instrumentalists` | Singers who play: an instrument can be any part | |
| `/for/friends-and-family` | Sing together, taking turns (accounts from 13) | |
| `/for/coaches` | Vocal coaches: earn through the app, and apply | Apply goes to the app |
| `/songs`, `/songs/<slug>` | The catalogue and each song's page | Exist (backend data, frontend markup); upgraded in A's look, agreed with both |
| `/guides`, `/guides/<slug>` | The short guides, with an index | The index is new |
| `/about` | The story, who makes it (Hillel) | mudeo's history only for mudeo arrivals |
| `/press` | Press kit: the one-liner, screenshots, the logo, contact | Screenshots from the real app |
| `/whats-new` | What's new, newest first, from one data file | |
| `/legal/accessibility` | The accessibility statement | Exists; linked from the footer |
| `/pricing`, `/help/*`, `/coaches` | Exist | Restyled with the shared parts |
| `/sitemap.xml`, `/robots.txt` | Every page above, coming-soon ones too | Exist (backend); pages added |

**Coming-soon pages are indexed now** (Hillel, 2026-10-27), with their label, so they can rank by
launch. **What's new is `/whats-new`** (Hillel).

## How it's built

- **One controller per area** (`Public\FeaturePagesController`, `Public\ForPagesController`,
  `Public\SitePagesController` for about, press, contact and what's new), thin, with each page's
  data in `App\Support\Site\*`.
- **Shared Blade components** in `resources/views/components/site/`: `<x-site.mixer>` (the
  playable four voices), `<x-site.coming-soon>`, `<x-site.cta>` (Start singing, carrying `ref`),
  `<x-site.faq>`, `<x-site.feature-card>`, `<x-site.section>`. The landing moves onto them first.
- **The words** in `lang/en/site.php` (features, for, about, press, contact), each page a block of
  headline, lead and sections, so translation later is one file per language.
- **Coming soon:** `config/site.php` lists every planned feature with its flag
  (`App\Support\Flags`), or `live: false` for one without a flag (the apps, Hum a tune, the song
  builder). `App\Support\Site\ComingSoon::open('gallery')` decides; the label goes by
  itself when it opens.
- **What's new:** `resources/whats-new/en.json`, a list of `{date, title, body, link?}`, newest
  first, written by hand when something ships.

## Tests

- **Every page renders** (a data provider over the site map), with one h1, the header, the footer
  and a canonical address.
- **Every link on every page is valid:** a public page answers 200, an app link points at the app's
  host.
- **Coming soon follows the flags:** each label shows with its flag off and goes with it on.
- **mudeo only for mudeo arrivals,** on every page.
- **In the browser** (`web/tests/flows/site_integration.py`, against the real server): every page
  in all eight themes at 320, 390, 768, 1440 and 1920 wide, with no sideways scrolling, and the
  contrast flow's AA checks; the mixers play.

## Order

1. The landing in A's polished look, on the shared components.
2. `/features` and its pages.
3. `/for/*`.
4. `/about`, `/press`, `/whats-new`. (Contact is the app's own `/contact` screen, linked from the footer with the address.)
5. The song pages and `/guides`, agreed with the backend and frontend.
6. The sitemap and robots, with the backend.

## Needs agreeing

- **The backend:** the new paths in `Hosts::PUBLIC_PATHS` (`features`, `features/*`, `for/*`,
  `guides`, `about`, `press`, `whats-new`), `config/site.php`, and the sitemap's pages.
- **The frontend:** moving the song pages' and guides' markup (`views/public/song*.blade.php`,
  `guide.blade.php`) onto the site's components, or leaving them to it.
