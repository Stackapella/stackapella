"""The song sheet and the in-app songs list, for the 2026-11-23 restyle's before and after (the coordinator: before/after
shots per family): a member a month in with own_choir on opens Browse all (the filters, the categories, the cards), then
a song's sheet (its facts, how many sing it, what you'll hear, the choir row, Start, Hear it and Edit, the credits open);
then a guest's first sheet. Every size and skin; saved as <OUT>/<label>-<size>-<skin>.png (OUT defaults to the flows'
shots folder).

    OUT=~/Code/shots-review/2026-11-23/restyle-songs/before FLOW_SKINS=game,serious PORT=8411 tool/flows.sh songs_restyle_shots
"""
import base64
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SHOTS, run  # noqa: E402

SHEET = "document.querySelector('[role=dialog]')"


def save(f, out, label, full=True):
    f.run("await document.fonts.ready")
    f.js("new Promise((r) => setTimeout(r, 900))")  # the covers drawn
    width = f.js("document.documentElement.clientWidth")
    height = f.js("Math.ceil(document.documentElement.scrollHeight)") if full else f.js("window.innerHeight")
    clip = {"x": 0, "y": 0 if full else f.js("window.scrollY"), "width": width, "height": min(height, 9000), "scale": 1}
    png = f.chrome.send("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": full, "clip": clip})["data"]
    (out / f"{label}-{f.size}-{f.skin}.png").write_bytes(base64.b64decode(png))


def sheet_shots(f, out, label):
    """The sheet at its top, then scrolled to its foot (it scrolls inside itself on a phone)."""
    f.js(f"{SHEET}.scrollTop = 0")
    save(f, out, f"{label}-top", full=False)
    if f.js(f"{SHEET}.scrollHeight > {SHEET}.clientHeight + 4"):
        f.js(f"{SHEET}.scrollTop = {SHEET}.scrollHeight")
        save(f, out, f"{label}-foot", full=False)


def journey(f):
    out = Path(os.environ.get("OUT") or SHOTS).expanduser()
    out.mkdir(parents=True, exist_ok=True)
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]')")
    f.run("localStorage.clear(); sessionStorage.clear(); const db = await import('/js/db.js'); await db.clearAll?.()")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockChoirState', 'fresh');")
    f.run("const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js'); await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 30 * 86400000, lessons: { '1-comfortable': { stars: 3, at: Date.now() - 86400000 }, '1-match': { stars: 2, at: Date.now() - 86400000 } }, home: 57, range: { low: 50, high: 67 } }, 'progress');")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]') && document.querySelector('[data-browse-all]')")
    # The songs list: Browse all, the filters over the whole library by category.
    f.run("document.querySelector('[data-browse-all]').click()")
    f.wait("document.querySelector('[data-library-filters]') && document.querySelector('[data-library-heading]')", what="the whole library")
    f.js("document.querySelector('#band-songs').scrollIntoView({ block: 'start' })")
    save(f, out, "01-songs-list", full=False)
    f.run("document.querySelector('[data-library-group]:not([data-library-group=\"\"])').click()")
    f.wait("document.querySelector('[data-library-group][aria-pressed=true]:not([data-library-group=\"\"])')", what="a category chosen")
    f.js("document.querySelector('#band-songs').scrollIntoView({ block: 'start' })")
    save(f, out, "02-songs-category", full=False)
    # A song's sheet, the whole of it: how many sing it, what you'll hear, the choir row, the three actions, the credits open.
    f.run("document.querySelector('#home-songs [data-song-card] button').click()")
    f.wait(f"{SHEET} && {SHEET}.querySelector('[data-song-start]') && {SHEET}.querySelector('[data-song-facts]')", what="the song's sheet")
    if f.js(f"!!{SHEET}.querySelector('[data-song-credits-toggle]')"):
        f.run(f"{SHEET}.querySelector('[data-song-credits-toggle]').click()")
        f.wait(f"{SHEET}.querySelector('[data-song-credits]')", what="the credits open")
    sheet_shots(f, out, "03-sheet")
    f.press("Escape")
    f.wait(f"!{SHEET}", what="the sheet closed")
    f.run("sessionStorage.removeItem('stackapella.mockOwnChoir'); sessionStorage.removeItem('stackapella.mockChoirState');")
    # A guest's first sheet: nothing sung yet, the first line to taste.
    f.run("localStorage.clear(); sessionStorage.clear(); const db = await import('/js/db.js'); await db.clearAll?.()")
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#home-songs [data-song-card]')")
    f.run("document.querySelector('#home-songs [data-song-card] button').click()")
    f.wait(f"{SHEET} && {SHEET}.querySelector('[data-song-start]')", what="a guest's sheet")
    sheet_shots(f, out, "04-sheet-guest")
    f.press("Escape")


if __name__ == "__main__":
    run("songs_restyle_shots", journey)
