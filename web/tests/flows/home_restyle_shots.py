"""Home, full page, for the 2026-11-23 restyle's before and after (the coordinator: before/after shots per family): a guest's
first visit, and a member a month into the course with own_choir on (the choir panel, the workout, the picks, Your songs).
Every size and skin; saved as <OUT>/<label>-<size>-<skin>.png (OUT defaults to the flows' shots folder).

    OUT=~/Code/shots-review/2026-11-23/restyle-home/before FLOW_SKINS=game,serious PORT=8411 tool/flows.sh home_restyle_shots
"""
import base64
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SHOTS, run  # noqa: E402


def full_page(f, out, label):
    f.run("await document.fonts.ready")
    f.js("new Promise((r) => setTimeout(r, 1200))")  # the covers and the lead's lanes drawn
    width = f.js("document.documentElement.clientWidth")
    height = f.js("Math.ceil(document.documentElement.scrollHeight)")
    png = f.chrome.send("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": True, "clip": {"x": 0, "y": 0, "width": width, "height": min(height, 9000), "scale": 1}})["data"]
    (out / f"{label}-{f.size}-{f.skin}.png").write_bytes(base64.b64decode(png))


def journey(f):
    out = Path(os.environ.get("OUT") or SHOTS).expanduser()
    out.mkdir(parents=True, exist_ok=True)
    # A guest's first visit: the course's hero, nothing sung yet.
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('[data-lead]')")
    f.run("localStorage.clear(); sessionStorage.clear(); const db = await import('/js/db.js'); await db.clearAll?.()")
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('[data-lead] h1')")
    full_page(f, out, "01-guest")
    # A member a month in, the choir on: the lead with its week, the choir panel, the workout, the picks, Your songs.
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockChoirState', 'fresh');")
    f.run("const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js'); await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 30 * 86400000, lessons: { '1-comfortable': { stars: 3, at: Date.now() - 86400000 }, '1-match': { stars: 2, at: Date.now() - 86400000 } }, home: 57, range: { low: 50, high: 67 } }, 'progress');")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-lead] h1') && document.querySelector('[data-choir-panel]')")
    full_page(f, out, "02-member")
    f.run("sessionStorage.removeItem('stackapella.mockOwnChoir'); sessionStorage.removeItem('stackapella.mockChoirState');")


if __name__ == "__main__":
    run("home_restyle_shots", journey)
