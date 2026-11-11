# Harmony, made by you: the Stackapella showreel

A 76.5-second film built as one HTML page: the drawn shots are code (Figtree, the glass gems, the
arcade lanes and their pitch camera, the split screen, the share card, the four-bar mark), and between
them the **real app**, captured from the mock app and the site's own pages and shown in a drawn phone
or window. **Its sound is Amazing Grace sung by the app's real singers**: the song's four default
real-voice guides from the public catalogue, the soprano alone and then a voice a phrase, then all four
the second time through. It renders in two cuts on one timeline, dark (Stage) and light (Warm). After
Event Schedule's showreel (`~/Code/eventschedule/resources/promo/showreel`), whose ideas it reuses.

**Status (2026-11-11):** v5, "The Stack", with its polish pass (`docs/showreel-v5-plan.md`; Hillel: "ship it"):
the final set is rendered as version `9b4cd32976`. Three cuts from one film: the hero (76.5 s, dark and
light), the short (40.5 s, `stackapella-short.mp4`) and the social (vertical, `stackapella-social.mp4`); the
last two are for posting and sit outside the manifest. The shot list under "How the film is put together"
below is the second cut's; v5's is in its plan.

**Before it (2026-11-08):** the second cut (`docs/showreel-v2-plan.md`, Hillel approved the plan and
"the verse twice"). The first cut (39 s, the synth guide at 96 BPM) is in git history before it, with
the launch cuts made from it.
- **Where the files are:** `server/public/videos/showreel/`. The videos, audio, GIF, the captures and
  the real voices are gitignored; `manifest.json`, `launch-manifest.json`, `captures.json`, the posters
  and the `.vtt` are in git, and every file matches its manifest's sha256.
- **On the CDN:** `php artisan app:showreel:upload` puts each render in its own folder,
  `showreel/<version>/` (the manifest's hash); `SHOWREEL_URL` is already set in production, so a new
  render needs only the upload (keys: Hillel's).

## Files

| File | What it is |
|---|---|
| `showreel.html` | The shell: the stage, the preview player, and the film's length (`DUR`, which `render.py` reads). |
| `film.css` | Every colour, as tokens (the app's palettes, `web/tool/palettes.json`), and the drawing of every object: the glass recipe (`.gem`, copied from `web/css/input.css`), and the phone and window that hold the app. |
| `js/lib.js` | Maths, easing, and small DOM helpers that touch the DOM only on a change. |
| `js/score.js` | The music: Amazing Grace from `web/templates/amazing-grace.json`, twice, its beat, your voice as a pitch curve, `CUE` (the beats the picture and the sound share), the credit line and the captions. |
| `js/stage.js` | The camera (a CSS 3D world), `project()` for what is painted in screen space, the sparkles, specks and grain, and the palette read from the tokens. |
| `js/film.js` | The film. The world (lanes, share card, end card), the app layer, and `SHOTS`, the cut. |
| `js/audio.js` | The soundtrack: the real voices, the app's pad (`renderBacking`) and chimes, mixed on an `OfflineAudioContext`. |
| `js/breaths.js` | Ducks the singers' breaths between phrases: only the unpitched air in each part's rests. |
| `js/main.js` | `seek(t)`, `settle()` and the preview player, whose clock is the AudioContext's while it plays. |
| `render.py` | Serves the page, drives headless Chrome over the DevTools protocol, encodes with ffmpeg; `--voices` fetches the real voices. No packages but Pillow. |
| `capture.py` | Captures the real app (stills and screencast recordings) for both cuts, into `captures/`, listed in `captures.json`. |
| `blend.py` | Averages the motion-blur samples (Pillow). |
| `fonts/` | Figtree 500, 700 and 800 (copies of the apps' bundled font, SIL Open Font License, `OFL.txt`), and game mode's Stackapella Pixel (`web/fonts/`, `OFL-pixel.txt`). |

`seek(t)` draws the frame at time `t` as a pure function of `t`, so any frame renders on its own and
renders the same twice. Nothing may remember the frame before. An app shot loads its screen as an
image; `settle()` resolves once it has decoded, and `render.py` waits for it before each grab.

## How the film is put together

**The song is the clock.** The real voices are recorded at the song's own 80 BPM and can't be
stretched, so the film is the verse twice (48 beats each, the pickup included) and the last chord held
two bars more under the pad: 102 beats, 76.5 seconds, and the next loop's pickup ("A-") lands on the
film's first frame. Every cut sits on a beat (0.75 s) or a half beat, and each act is one of the song's
phrases (9 s):

| Time | Sung | Act |
|---|---|---|
| 0:00 | "Amazing grace...", the soprano alone | Learn: the lanes close up, then Sing, notes lighting, in step with the song |
| 0:09 | "that saved...", the alto joins | Stack: the studio with three parts sung, then the lanes from above |
| 0:18 | "I once was lost...", the tenor joins | Game mode: the landing's own switch clicked; a CRT power-on into the arcade, and the film itself goes arcade: the take, then the daily challenge on Home |
| 0:27 | "was blind...", the bass joins | Still the arcade: the split screen of four takes of you, the share card and friends joining; then a power-off |
| 0:36 | all four, the second time | The season's slot: the carols page (lights, snow, the Santa hat), then its songbook with I sing on Alto (`--season`, below) |
| 0:40.5 | | Courses: the courses page scrolling, then the first lesson on a phone with no account |
| 0:46.5 | | Tools, four beats each: tuner, drone, vocal range test, each named big with a few words and its own panel on a card; the row ends "and more" |
| 0:55.5 | | Choirs: the director's group, a member's |
| 1:03 | | Open source: the site's /open-source page, the pointer on View on GitHub, then GitHub's page for the organisation (signed out); then everyone on the lanes, and on "see." the mark and the end card, held to 1:16.5 |

**The season's slot.** Beats 48 to 54 show a songs page, picked with `render.py --season=`: `carols`
(the default, /songs/christmas-carols) in season, then `hymns` (/songs/hymns) or `songbook` (/songs).
`capture.py` takes all three; a swap is the season's renders (`--final`, `--mobile` for each theme),
`--manifest` and the upload, nothing else (the posters are the end card, the captions what's sung).

**The arcade** (film.css, "GAME MODE"): from the switch to the end of the first pass the film follows game
mode's guide, always the dark arcade (in the light cut too, as the app turns dark with game mode), and the CRT (`crt()` in film.js) switches it on and off on the beat.
The app's recordings that know where in the song they began (`songAt` in captures.json) play in step with it.
The singers' breaths between phrases are ducked (`js/breaths.js`).

**Picture and sound share their beats** through `CUE` in `js/score.js`: when each voice joins, when
friends join, when the mark forms. Move a cue and both follow.

## The real voices

`python3 render.py --voices` asks the public catalogue (`GET https://stackapella.com/api/templates`,
the call the app makes; `CATALOGUE=` to point it elsewhere) for Amazing Grace, refuses it if its version
isn't the template's here (so the notes on screen are the ones sung), and downloads each part's default
real voice from the public URL it lists into `voices/` with `voices.json` (their file details and
credits). Nothing else is read from production, and the files are never committed. The end card and
the captions credit them as the app does, a part at a time ("Soprano and alto sung by ..."); a singer
who chose no credit is not named.

## App footage

`capture.py` films the real app for both cuts (`--cut=dark|light`, `--only=id,id`): the mock app
(`API=mock php -S 127.0.0.1:<port> web/tool/serve.php`) and the site's pages (the Laravel server,
`php -S 127.0.0.1:<port> server/tool/serve.php`), each on its own port (`--app=`, `--site=`). The dark
cut films the app's dark themes, the light cut its light ones, in the skin each shot names. Stills are
JPEGs; a recording is Chrome's screencast, resampled to 30 fps. A singing shot records the alto over
loopback with the alto's own notes played in, so the lane lights as the app does for a singer; the
tuner and range test hear a sung note through a stand-in mic. **Only made-up names**: the mock's people
are renamed Maya, Sam and Leo in every shot, and no singer's credit is filmed.

## Preview

```bash
python3 render.py --serve       # then open http://127.0.0.1:8790/server/resources/promo/showreel/showreel.html
```

The page imports the app's own `web/js/synth.js` and reads the song's template, so it must be served
from the repository's root (opening the file from disk won't work). Space plays or pauses (with
sound), the arrow keys step one frame (Shift: half a second), the scrubber seeks, and T switches
between the dark and the light cut on the same frame. URL parameters:

- `?t=16.5` opens paused on one frame
- `?from=15&to=22.5` loops one section
- `?cam={"rx":40}` nudges the camera of whatever shot is showing, for trying a framing
- `?theme=light` or `?theme=dark` picks the cut; without it the preview follows the OS

## Two cuts

Every colour lives in the tokens at the top of `film.css` (the dark cut overrides them under
`:root[data-theme="dark"]`), and what code paints on a canvas reads the same tokens (`T` in
`js/stage.js`). Never write a colour anywhere else. The dark cut is Stage (true black, the part
colours of the mark); the light cut is Warm (cream, deeper part colours), with less glow and more
edge and shadow, as the app does. The mark keeps its own colours in both.

## Render

Needs Google Chrome, Python 3 with Pillow, and ffmpeg with libx264, libvpx-vp9 and libopus
(`brew install ffmpeg`; override with `CHROME=` or `FFMPEG=`).

```bash
python3 render.py --preview --theme=dark --out=preview.mp4        # 960x540, 30fps, no blur, sound; about a minute
python3 render.py --stills=2.95,12.6,28.2 --theme=light --out=stills   # PNG stills (half scale; --scale=1 for full)
```

The outputs go to `server/public/videos/showreel/` (see below):

```bash
for th in dark light; do
  python3 render.py --final  --theme=$th     # 1920x1080 60fps, WebM (VP9 + Opus) and MP4 (H.264 + AAC)
  python3 render.py --mobile --theme=$th     # 960x540 30fps MP4
  python3 render.py --posters --theme=$th    # the poster: JPEG, and WebP at 960, 1440 and 1920
done
python3 render.py --audio                    # the soundtrack alone: WAV, M4A, Opus (run --voices first)
python3 render.py --manifest                 # manifest.json: every file's name, size and sha256
```

`--from`/`--to` render a range, `--workers=N` sets parallel Chrome instances, `--port-base=N` and
`--http=N` move the ports so two renders can run at once, `--keep` leaves the frames behind,
`--silent` skips the sound.

Motion blur is real: each frame averages 8 renders across a 180-degree shutter, and 16 inside the
fast windows the page lists in `FAST` (the lanes swinging in, the mark forming, the card flying in,
the fold). A frame's samples stay on its own side of a cut (`CUTS`), so no frame dissolves two shots.

The poster is a clean still of the end card (`POSTER_AT`), with no blur: it is what a visitor sees
before the reel plays, and all they see under reduced motion.

## Launch cuts

The launch's cuts (vertical, square and the Product Hunt thumbnail, listed in `launch-manifest.json`)
were made from the first film and posted as they are; this film is wide only (`--launch=vertical` and
`square` refuse). They can be re-cut from this one if Hillel wants new ones.

## Where the outputs live

Videos and sound are never committed (the repo goes public, and binaries bloat it for good). They
render to `server/public/videos/showreel/`, whose `.gitignore` keeps them out, and are served from
the CDN; `manifest.json` (in git) lists each file's name, size and sha256 for the upload. Posters
under 300 KB are committed beside it.

## Rules for the film

- **Every line stays up long enough to read:** about 0.3 s a word plus a second, counted from when
  its rise is mostly done. `window.captions()` in the preview lists each line's time against that.
- **Honest words only.** Every line says what the app does, in the site's or the app's own words
  where it has them ("Learn your harmony from a real singer.", "Your notes light up as you hit them.",
  "Every course is free.", "Try the first lesson without an account.", "Tools for every practice.",
  "Harmony, made by you.", "For one voice, or a whole choir."). Only what's live, never "coming
  soon". No numbers, no prices, no em dashes, and nothing about any company but Stackapella.
- **No drawn people**, and only made-up names on screen (Maya, Sam, Leo); a tap is a ring and a soft
  disc, never a hand.
- **The last frame is the first frame.** The last beat cuts back to the opening note, a beat early,
  and everything that drifts is periodic over `DUR`. Check with stills at `0` and `76.49`.
- **Keep the top left for the line** each shot carries, at 76 px: it must read on a phone, where
  the picture is about 343 px wide and one film pixel is a sixth of a pixel.
- **Keep both bottom corners clear.** The landing lays two glass buttons on the picture, inset
  16 px with 44 px targets: pause and play bottom left, sound bottom right, in about 80 x 80 CSS px
  each. That is 140 film px on a desktop and about 450 on a phone, so nothing that matters sits in
  the bottom 450 x 450 of either corner.
- **The captions track** (`--vtt`, `stackapella-showreel.vtt`) gives each phrase's line of the hymn
  and says who joins; the bass sings on "oo", the others the words. The singers' credit is on the end card, not in the captions.

## On the landing

The landing agent builds the frame (`docs/public-site-plan.md`'s owner): a band after the hero's
mixer headed "See it in action", the page's width, 16:9, a 28 px radius (32 px from 1024 px) and a
1 px border in the line colour. The cut matching the theme plays muted once the frame is in view
and the visitor has scrolled; a theme switch mid-play seeks the other cut to the same moment and
crossfades; reduced motion or Save-Data shows the poster with a play button. The base URL and a
version come from `site.showreel` in config (falling back to `/videos/showreel`). Bump the version
after every re-render: the files keep their names.
