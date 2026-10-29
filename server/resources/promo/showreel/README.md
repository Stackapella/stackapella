# Harmony, made by you: the Stackapella showreel

A 39-second film built as one HTML page, so every frame is code: no editing project, no stock
footage, nothing to license. It tells Stackapella's story in the app's own look (Figtree, the glass
gems, the arcade lanes and their pitch camera, the landing's mixer, the creator, the four-bar mark,
the Stage and Warm themes), and **it has sound**: Amazing Grace's verse sung by the app's own guide
voice (`web/js/synth.js`), one voice and then four, with the soft "oo" pad. It renders in two cuts on
one timeline, dark (Stage) and light (Warm). After Event Schedule's showreel
(`~/Code/eventschedule/resources/promo/showreel`), whose ideas it reuses.

**Status (2026-10-28):**
- **The film is final:** Hillel approved it (`docs/decisions.md`, "the showreel"), and both cuts, the
  phone cuts, the posters, the audio and the captions are rendered. It plays in the landing's "See it
  in action" band (`server/resources/views/pages/landing/_showreel.blade.php`,
  `web/js/public/showreel.js`), which shows only once its videos can be reached.
- **The launch cuts are final:** vertical, square and the Product Hunt thumbnail (below). They're
  posted to the platforms directly, not served from the CDN.
- **Waiting on Hillel's CDN keys:** the film's files go up with `php artisan app:showreel:upload`
  (it reads `manifest.json`), then `SHOWREEL_URL` is set in production.
- **Where the files are:** `server/public/videos/showreel/`, in the main checkout and in the
  `agent/showreel` worktree (`~/Code/stackapella-showreel`). The videos, audio and GIF are
  gitignored; `manifest.json`, `launch-manifest.json`, the posters and the `.vtt` are in git, and
  every file matches its manifest's sha256. Re-rendering rebuilds them all (see Render).

## Files

| File | What it is |
|---|---|
| `showreel.html` | The shell: the stage, the preview player, and the film's length (`DUR`, which `render.py` reads). |
| `film.css` | Every colour, as tokens (the app's palettes, `web/tool/palettes.json`), and the drawing of every object, the glass recipe included (`.gem`, copied from `web/css/input.css`). |
| `js/lib.js` | Maths, easing, and small DOM helpers that touch the DOM only on a change. |
| `js/score.js` | The music: Amazing Grace from `web/templates/amazing-grace.json`, its beat, your voice as a pitch curve, and `CUE`, the beats the picture and the sound share. |
| `js/stage.js` | The camera (a CSS 3D world), `project()` for what is painted in screen space, the sparkles, specks and grain, and the palette read from the tokens. |
| `js/film.js` | The film. The world (lanes, mixer, creator, share card, end card) and `SHOTS`, the cut. |
| `js/audio.js` | The soundtrack, mixed on an `OfflineAudioContext` from `renderPart` and `renderBacking`. |
| `js/main.js` | `seek(t)` and the preview player, whose clock is the AudioContext's while it plays. |
| `render.py` | Serves the page, drives headless Chrome over the DevTools protocol, encodes with ffmpeg. No packages but Pillow. |
| `blend.py` | Averages the motion-blur samples (Pillow). |
| `fonts/` | Figtree 500, 700 and 800 (copies of the apps' bundled font, SIL Open Font License, `OFL.txt`). |

`seek(t)` draws the frame at time `t` as a pure function of `t`, so any frame renders on its own and
renders the same twice. Nothing may remember the frame before.

## How the film is put together

**The song is the clock.** Amazing Grace's verse is 48 beats with a one-beat pickup. The film holds
four of its long notes longer, as fermatas, so the words on screen can be read: "me.", "found," and
"now" a bar each (`STRETCH` in `js/score.js`; `m()` turns a beat of the template into one of the
film), and the last chord two bars (`HOLD`), so the end card stays up. At 96 BPM those 63 beats
last exactly 39.375 seconds, and the next loop's pickup ("A-") lands on the film's first frame.
Every cut sits on a beat (0.625 s) or a half beat, and the four acts are the song's four phrases:

| Phrase | Time | Act |
|---|---|---|
| "Amazing grace, how sweet the sound" | 0:00 | One voice, learning it |
| "that saved a wretch like me." | 0:07.5 | Stacking the other voices, then the mixer |
| "I once was lost, but now am found," | 0:16.9 | Making your own: a hum, harmonised, with chords |
| "was blind, but now I see." | 0:24.1 | Sharing it, friends singing; the end card, held to 0:37.5 |

There is one world, and each set (the lanes, the mixer, the creator, the share card) has its own
place in it; the split screen of the four singers is drawn over it. Each act has its own look: the
Learn act is close-ups on the dark stage, Stack tilts, goes top down, splits into four and runs
along the beam, Make your own stands on a wash of the accent, and Share is the app's own UI (the
mixer and the share card, square on, with a fingertip's taps). `SHOTS` in `js/film.js` is the cut: each shot says when it starts, which set it shows,
where the camera is (and how it moves within the shot), and the act and line it carries. A shot
runs until the next begins, and every cut is hard.

**Picture and sound share their beats** through `CUE` in `js/score.js`: when each voice joins, when
the mark forms, when the chords land, when friends join. Move a cue and both follow.

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
python3 render.py --audio                    # the soundtrack alone: WAV, M4A, Opus
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

The same source makes the launch's cuts (`--launch=`), each reframed rather than cropped: the line in
the platform's safe area, the split screen laid out two by two under it, the end card's headline in
two lines, and the shots that follow the playhead keeping it a third of the way across. Each is a
few stretches of the film end to end (`EDIT` in `js/film.js`), joined where the music can take it,
and ends on the opening note, so it loops. They are listed in `launch-manifest.json`.

| File | What it is |
|---|---|
| `stackapella-launch-vertical.mp4` | 1080x1920 30fps, 18.7 s: Learn and Stack, then the end card |
| `stackapella-launch-square.mp4` | 1080x1080 30fps, 15 s: "Amazing grace," then Stack, then the end card |
| `stackapella-launch-thumb.gif` / `.mp4` | 240x240 20fps, a 3.75 s loop: the mark's four glass bars lit voice by voice, for Product Hunt |

```bash
for f in vertical square thumb; do python3 render.py --launch=$f; done; python3 render.py --manifest
python3 render.py --format=vertical --stills=1.6,10.8 --out=stills    # stills of a format
```

## Where the outputs live

Videos and sound are never committed (the repo goes public, and binaries bloat it for good). They
render to `server/public/videos/showreel/`, whose `.gitignore` keeps them out, and are served from
the CDN; `manifest.json` (in git) lists each file's name, size and sha256 for the upload. Posters
under 300 KB are committed beside it.

## Rules for the film

- **Every line stays up long enough to read:** about 0.3 s a word plus a second, counted from when
  its rise is mostly done. `window.captions()` in the preview lists each line's time against that.
- **Honest words only.** Every line is the site's or the app's own: "Learn a part with a guide
  voice", "Your notes light up as you hit them", "Stack your own voices, exactly in time", "Sing the
  part that suits you", "Share the whole harmony", "Invite friends to sing the other parts",
  "Harmony, made by you.", "For one voice, or a whole choir." No numbers, no
  prices, no em dashes, and nothing about any company but Stackapella.
- **The last frame is the first frame.** The last beat cuts back to the opening note, a beat early,
  and everything that drifts is periodic over `DUR`. Check with stills at `0` and `39.37`.
- **Keep the top left for the line** each shot carries, at 76 px: it must read on a phone, where
  the picture is about 343 px wide and one film pixel is a sixth of a pixel.
- **Keep both bottom corners clear.** The landing lays two glass buttons on the picture, inset
  16 px with 44 px targets: pause and play bottom left, sound bottom right, in about 80 x 80 CSS px
  each. That is 140 film px on a desktop and about 450 on a phone, so nothing that matters sits in
  the bottom 450 x 450 of either corner.
- **The captions track** (`--vtt`, `stackapella-showreel.vtt`) gives each phrase's line of the hymn
  and says what is heard. The guide voice sings on "oo", so a cue never claims the words are sung.

## On the landing

The landing agent builds the frame (`docs/public-site-plan.md`'s owner): a band after the hero's
mixer headed "See it in action", the page's width, 16:9, a 28 px radius (32 px from 1024 px) and a
1 px border in the line colour. The cut matching the theme plays muted once the frame is in view
and the visitor has scrolled; a theme switch mid-play seeks the other cut to the same moment and
crossfades; reduced motion or Save-Data shows the poster with a play button. The base URL and a
version come from `site.showreel` in config (falling back to `/videos/showreel`). Bump the version
after every re-render: the files keep their names.
