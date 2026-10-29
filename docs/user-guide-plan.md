# The user guide: /help, with screenshots

**Status:** planned 2026-10-27 by the landing agent, from Hillel's "we need a full user guide with
screenshots, modelled on Event Schedule's" (relayed by the coordinator). Built in three steps:
the frame, then the pages, then the screenshots, each landed on `main` with its tests.

Back: [The public site](public-site-plan.md)

## Where it lives: /help, not /docs

The guide grows out of /help instead of starting a /docs beside it:

- **People look for "Help".** It's already in the header and the footer. "Docs" is a developer's
  word, and Stackapella is for singers.
- **One place for "how do I".** The 13 help articles are already the guide's bones, written from
  the app's real labels. They become its pages; nothing is said twice.
- **/guides is taken** by the singing guides (how to sing harmony, find your part), which are
  about singing, not the app. Each links the other where it helps.
- **The addresses stay.** The app and old links point at `/help/<slug>`; every slug keeps working.
  `first-harmony` becomes `getting-started` (a 301).

## What it borrows from Event Schedule's docs

| Event Schedule | Stackapella |
|---|---|
| One manifest (`config/docs.php`) for the nav, pager, index, search and sitemap | `App\Support\Site\HelpGuide`: the groups and their pages, in order |
| A left rail (search, the groups), the article, "On this page" on the right | The same from 1200 px; under it, search and a Contents disclosure at the top, "On this page" folded |
| Previous and next at the foot of every page | The same, in the manifest's order |
| A getting-started path on the index | "Start here": three steps, then the groups |
| Search from `/docs/search-index.json`, fetched on first focus | `/help/search-index.json`: each page and each section, fetched on first focus; plain JS (`js/public/help-search.js`) |
| Screenshots as light and dark pairs, png plus webp, `page--section(-dark)`, the one matching the reader's theme | The same names under `web/icons/help/`; the frame shows the one for the theme showing (`-light` or `-dark` in `data-theme`) |
| A test that every named screenshot exists | The same, once the first set is shot |

## The pages, in order

| Group | Page (slug) | From |
|---|---|---|
| Start here | Getting started (`getting-started`) | `first-harmony`, reworked: open the app, Start singing, your first part |
| Learn | Find your voice and the course (`your-voice`) | new |
| Learn | Learn a part in Sing (`learn-a-part`) | new |
| Learn | Your part and key (`your-part-and-key`) | the article |
| Make | Recording in the studio (`studio`): takes, the timing check, the mixer, export | new, with `first-harmony`'s recording steps |
| Make | Making your own song (`create`): Sing it, Write it, Start from chords | the article |
| Share | Sharing your harmony (`sharing`) | the article |
| Share | Choir groups (`groups`) | the article |
| Your account | Plus and billing (`plus-and-billing`) | the article |
| Your account | Settings and your account (`settings`) | new |
| Your account | Your privacy (`privacy`), Your voice as a guide (`guides`) | the articles |
| Fixes | The mic (`mic`), Headphones, Parts out of time (`timing`), The app on iPhone (`iphone`), Installing the app (`install`) | the articles |

## How a page is written

The words stay in `lang/en/pages.php` (`help.pages.<slug>`), as now, so the language test, the
`:price` and `:days` fill and the mic page's own steps (`MicHelp`) keep working. A section is
`[heading, items]`, optionally `'steps' => true` for a numbered list. An item is a line of words, or
`['shot' => '<page>--<step>', 'alt' => '...']` for a screenshot, or `['tip' => '...']` for a
note. Every label in **bold** is the app's own word for it, checked against `web/locales/en.json`.

## Screenshots

Real screens only, never mockups: the frontend's screenshot flow shoots each one from the app with
demo data, in Warm light and Stage dark, at 390 by 844 (phone) for the steps and 1440 by 900 for
the studio and the mixer, as png and webp (`web/icons/help/<id>.png`, `<id>-dark.png`, the same
in webp). A screenshot that hasn't been shot yet is left out of the page, so the guide ships before
its pictures; the shot list is [docs/user-guide-shots.md](user-guide-shots.md).
