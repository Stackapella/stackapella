"""The film (js/public/showreel.js) on /how-it-works's first screen (Hillel, 2026-11-16), where its frame waits for a
press: the poster and a play button, no video fetched even once the page is scrolled, then the film muted on Play.
The look's cut shows (game's under data-skin="game", calm's amber one otherwise: Hillel, 2026-11-17), one poster
only, and a skin switch mid-play hands over to the other look's own cut.
Needs the film's files on the server (Showreel::available) and a page that's scrolled, as a visitor's is."""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

REEL = "document.querySelector('[data-reel]')"
CC = "document.querySelector('[data-reel-cc]')"
SOUND = "document.querySelector('[data-reel-sound]')"
VIDEO = f"{REEL}.querySelector('video[data-cut=' + {REEL}.dataset.cut + ']')"
MODE = f"[...{VIDEO}.textTracks].map((t) => t.mode).join()"
LINE = "document.querySelector('[data-reel-caption]')"


def to_film(f):
    f.wait(f"!!{REEL}", what="the film")
    f.js(f"(() => {{ {REEL}.scrollIntoView({{ block: 'center' }}); window.dispatchEvent(new Event('scroll')); }})()")
    f.wait(f"{REEL}.hasAttribute('data-playing')", timeout=30, what="the film playing")
    f.wait(f"!{CC}.hidden", what="CC shown with the other buttons")


def journey(f):
    # A fresh visitor: the setting cleared from a plain file on the same origin, so the page itself loads once.
    f.go("/robots.txt", "document.readyState === 'complete'")
    f.js("(() => { try { localStorage.removeItem('stackapella.reelCaptions'); } catch (e) {} })()")
    # (The film's own band on the home page went with the old landing, 2026-11-16: the home page plays the song itself.
    # What it checked there, CC and its line under a film that starts by itself, has no page now.)
    on_how_it_works(f)


ROOT = "document.documentElement"
LOOK = f"({ROOT}.dataset.skin === 'game' ? '' : 'calm-') + ({ROOT}.dataset.theme.endsWith('-light') ? 'light' : 'dark')"
POSTERS = "[...document.querySelectorAll('.s-reel-poster')].filter((i) => getComputedStyle(i).display !== 'none').map((i) => i.dataset.cut).join()"
FILE = f"{VIDEO}.getAttribute('src').split('?')[0].split('/').pop()"


def switch_looks(f):
    """Mid-play, the other skin's cut takes over with its own files, then back."""
    was = f.js(f"{ROOT}.dataset.skin")
    for skin in ("serious" if was == "game" else "game", was):
        f.run(f"{ROOT}.dataset.skin = '{skin}'")
        f.wait(f"{REEL}.dataset.cut === {LOOK}", timeout=30, what=f"the {skin} cut handed over")
        f.wait(f"!{VIDEO}.paused", timeout=15, what=f"the {skin} cut playing")
        calm = f.js(FILE).startswith("stackapella-showreel-calm")
        assert calm == (skin != "game"), f"the {skin} look plays its own files: {f.js(FILE)}"


# Media requests rarely show in resource timing, so a video counts as fetched once it has a source.
VIDEOS = "[...document.querySelectorAll('video')].filter((v) => v.currentSrc || v.getAttribute('src')).length + performance.getEntriesByType('resource').filter((r) => /\\.(mp4|webm)/.test(r.name)).length"


def on_how_it_works(f):
    f.go("/how-it-works", "document.readyState === 'complete'")
    f.wait(f"!!{REEL}", what="the film on the first screen")
    f.js("window.scrollBy(0, 40)")
    time.sleep(1.5)
    assert f.js(f"getComputedStyle(document.querySelector('[data-reel-start]')).display") == "grid", "Play shows on the poster"
    assert not f.js(f"{REEL}.hasAttribute('data-playing')"), "it never starts by itself"
    assert f.js(VIDEOS) == 0, "no video fetched before a press"
    assert f.js(f"{REEL}.querySelectorAll('video[data-cut]').length") == 4, "game's two cuts and calm's two"
    assert f.js(f"{REEL}.dataset.cut") == f.js(LOOK), f"the look's cut: {f.js(REEL + '.dataset.cut')} for {f.js(LOOK)}"
    assert f.js(POSTERS) == f.js(LOOK), f"only the look's poster is shown: {f.js(POSTERS)}"
    f.run("document.querySelector('[data-reel-start]').click()")
    f.wait(f"{REEL}.hasAttribute('data-playing')", timeout=30, what="the film playing on Play")
    assert f.js(f"{VIDEOS} > 0 && {VIDEO}.muted"), "it plays muted, the sound a press away"
    f.shot("how-it-works-playing")
    switch_looks(f)


run("reel_captions", journey)
