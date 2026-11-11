"""A song's page and a part's page each share with one quiet secondary beside their primary (docs/retention-plan.md,
"Sharing"): the device's share sheet where there is one, else the link copied, the link tagged ?ref=share-<surface>,
the share counted as sh:<surface>.<method> (the kind only), and the label never wrapping, "Link copied" at the same
width. Needs the real server (public pages): python3 -I tests/flows/share_buttons.py http://127.0.0.1:<port>"""

import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))

from flow import run  # noqa: E402

SPY = """window.__beacons = []; window.__shared = []; window.__copied = [];
navigator.sendBeacon = (url, body) => { body.text().then((text) => window.__beacons.push([url, text])); return true; };"""
SHEET = "navigator.share = (data) => { window.__shared.push(data); return Promise.resolve(); };"
NO_SHEET = """delete Navigator.prototype.share; navigator.share = undefined;
Object.defineProperty(navigator, 'clipboard', { value: { writeText: (t) => { window.__copied.push(t); return Promise.resolve(); } } });"""
BUTTON = "document.querySelector('[data-share]')"


def kinds(f):
    f.js("new Promise((r) => setTimeout(r, 200))")
    return [e["kind"] for url, text in f.js("window.__beacons") if url == "/api/counts" for e in json.loads(text)["events"] if e["kind"].startswith("sh:")]


def fitted(f):
    f.wait(f"{BUTTON}.dataset.fit === '1'", what="the Share button fitted to its row")
    f.js("document.fonts.ready.then(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))))")


def one_line(f, where):
    fitted(f)
    # Never a row of its own: beside the button before it, the label on one line or the icon alone.
    lines = f.js(f"(() => {{ const l = {BUTTON}.querySelector('[data-share-label]'); return l.hidden ? 1 : l.getClientRects().length; }})()")
    assert lines == 1, f"{where}: the Share label stays on one line ({lines} lines)"
    # Beside its row's button wherever the two fit (the icon alone if it must); under it, whole, only where they can't.
    stacked, icon, full, fills = f.js(f"(() => {{ const b = {BUTTON}, p = b.previousElementSibling, w = b.parentElement.clientWidth; return [!!p && b.offsetTop > p.offsetTop + p.offsetHeight / 2, b.querySelector('[data-share-label]').hidden, !!p && p.offsetWidth >= w - 1, b.offsetWidth >= w - 1]; }})()")
    if stacked:
        assert not icon, f"{where}: on a line of its own, Share shows its whole label"
        # Under its row's button only where that button fills the row by design (a hero on a phone), and then as wide;
        # else only on the narrowest phones.
        assert full or f.js("innerWidth") <= 360, f"{where}: Share falls under its row's button only on the narrowest phones"
        assert fills or not full, f"{where}: under a full-width button, Share is full width too"
    f.run(f"{BUTTON}.scrollIntoView({{block: 'center'}})")


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": SPY + SHEET})
    f.go("/songs/amazing-grace/alto", f"!!{BUTTON}")
    assert f.js(f"{BUTTON}.dataset.surface") == "page-part"
    one_line(f, "part page")
    f.no_sideways("the part page with its Share")
    f.shot("part page")
    f.run(f"{BUTTON}.click()")
    f.wait("window.__shared.length === 1", what="the share sheet opened")
    shared = f.js("window.__shared[0]")
    assert "ref=share-page-part" in shared["url"] and "/songs/amazing-grace/alto" in shared["url"], shared
    assert "alto part of Amazing Grace" in shared["text"], shared
    assert kinds(f) == ["sh:page-part.sheet"], kinds(f)

    # No share sheet (a desktop browser): the link copied, the label says so at the same width.
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": NO_SHEET})
    f.go("/songs/amazing-grace", f"!!{BUTTON}")
    assert f.js(f"{BUTTON}.dataset.surface") == "page-song"
    one_line(f, "song page")
    f.no_sideways("the song page with its Share")
    f.shot("song page")
    width = f.js(f"{BUTTON}.getBoundingClientRect().width")
    f.run(f"{BUTTON}.click()")
    f.wait("window.__copied.length === 1", what="the link copied")
    assert "ref=share-page-song" in f.js("window.__copied[0]"), f.js("window.__copied[0]")
    f.wait(f"{BUTTON}.textContent.includes('Link copied') || {BUTTON}.getAttribute('aria-label') === 'Link copied'", what="the button saying it's copied")
    assert abs(f.js(f"{BUTTON}.getBoundingClientRect().width") - width) < 1, "the button keeps its width while it says Link copied"
    one_line(f, "song page, copied")
    f.shot("song page copied")
    assert "sh:page-song.copy" in kinds(f), kinds(f)

    # The songbook's collections and the carols: in the hero's row beside its pick, never a row of its own.
    for path, surface in (("/songs/hymns", "page-collection"), ("/songs/christmas-carols", "page-carols")):
        f.go(path, f"!!{BUTTON}")
        assert f.js(f"{BUTTON}.dataset.surface") == surface, path
        one_line(f, path)
        f.no_sideways(f"{path} with its Share")
        f.shot(path.split("/")[-1])


run("share_buttons", journey)
