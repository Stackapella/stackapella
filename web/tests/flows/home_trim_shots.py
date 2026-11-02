"""Home for a returning singer (past the first week), whole page, for Hillel's review of the trimmed
Home (2026-10-30: "it feels like we're throwing a lot at the user"): mid-course, three songs of
their own and a Plus course begun, in each skin, at phone and desktop. Written to OUT as
home-<label>-<size>-<skin>.png; LABEL=before or after.

    OUT=~/Code/shots-review/home-trim LABEL=before FLOW_SKINS=game,serious PORT=8410 tool/flows.sh home_trim_shots
"""
import base64
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects, new_song, progress  # noqa: E402

OUT = Path(os.environ.get("OUT", "~/Code/shots-review/home-trim")).expanduser()
LABEL = os.environ.get("LABEL", "after")
HOME = "/?api=mock&scenario=&as=member"


def whole_page(f, path):
    f.run("window.scrollTo(0, 0); document.activeElement?.blur?.();")
    time.sleep(0.8)
    metrics = f.chrome.send("Page.getLayoutMetrics")["cssContentSize"]
    width, height = f.chrome.send("Page.getLayoutMetrics")["cssLayoutViewport"]["clientWidth"], metrics["height"]
    data = f.chrome.send("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": True, "clip": {"x": 0, "y": 0, "width": width, "height": height, "scale": 1}})["data"]
    OUT.mkdir(parents=True, exist_ok=True)
    path.write_bytes(base64.b64decode(data))


def journey(f):
    f.go(HOME, "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    f.mic_checked()
    clear_projects(f)
    # Past the first week: arrived ten days ago, two units sung, a note and a range.
    unit1 = {lesson: {"stars": 3, "bestPercent": 92, "at": 0} for lesson in ("1-comfortable", "1-match", "1-high-low", "1-breath", "1-challenge")}
    unit2 = {lesson: {"stars": 2, "bestPercent": 84, "at": 0} for lesson in ("2-hold", "2-drone", "2-vowels")}
    progress(f, {**unit1, **unit2}, 640, days=2, extra=", home: 57, range: { low: 52, high: 69 }, courses: { vibrato: { lessons: { 'vib-1-swing': { stars: 2, bestPercent: 80, at: 0 } } } }")
    f.run("const k = JSON.parse(localStorage.getItem('stackapella.funnel') || '{}'); localStorage.setItem('stackapella.funnel', JSON.stringify({ ...k, firsts: { ...(k.firsts ?? {}), f_lesson1: true }, arrived: new Date(Date.now() - 10 * 86400000).toISOString().slice(0, 10) }));")
    for template in ("amazing-grace", "silent-night", "amazing-grace"):
        new_song(f, template)
    f.go(HOME, "document.querySelector('#lead-title') && document.querySelector('[data-new-song]')")
    f.wait("document.querySelector('[data-home-courses]')", timeout=20, what="the courses")
    time.sleep(1.5)
    whole_page(f, OUT / f"home-{LABEL}-{f.size}-{f.skin}.png")
    print(f"     shot {OUT / f'home-{LABEL}-{f.size}-{f.skin}.png'}")
    clear_projects(f)


run("home_trim_shots", journey)
