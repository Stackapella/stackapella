"""The landing film's captions (js/public/showreel.js): off by default, with the sound on too; CC turns them on and is
remembered after a reload; they're written in a line under the film, never drawn on it (the track stays hidden),
and the end card's credit isn't repeated.
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
    f.go("/", "document.readyState === 'complete'")
    to_film(f)
    assert f.js(f"{CC}.getAttribute('aria-pressed')") == "false", "CC starts off"
    assert f.js(f"{LINE}.hidden"), "captions off by default"
    assert "showing" not in f.js(MODE), f"never drawn on the picture: {f.js(MODE)}"
    f.run(f"{SOUND}.click()")
    time.sleep(0.4)
    assert f.js(f"{LINE}.hidden"), "the sound alone doesn't turn captions on"
    f.run(f"{CC}.click()")
    f.wait(f"!{LINE}.hidden", what="the caption line shown on CC")
    assert "showing" not in f.js(MODE), "still never drawn on the picture"
    assert f.js(f"{CC}.getAttribute('aria-pressed')") == "true"
    assert f.js("localStorage.getItem('stackapella.reelCaptions')") == "on", "remembered"
    f.wait(f"([...{VIDEO}.textTracks][0].cues || []).length > 0", what="the cues")
    f.wait(f"{LINE}.textContent.trim().length > 0", timeout=30, what="a cue written under the film")
    assert "sung by" not in f.js(f"{LINE}.textContent").lower(), "the end card's credit is left to the card"
    f.shot("captions-on")
    # Remembered after a reload.
    f.go("/", "document.readyState === 'complete'")
    to_film(f)
    f.wait(f"!{LINE}.hidden", what="captions remembered after a reload")
    assert f.js(f"{CC}.getAttribute('aria-pressed')") == "true"
    f.run(f"{CC}.click()")
    f.wait(f"{LINE}.hidden", what="captions off again")
    assert f.js("localStorage.getItem('stackapella.reelCaptions')") == "off"


run("reel_captions", journey)
