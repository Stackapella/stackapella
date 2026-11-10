"""A song part page counts its first play once (part_play, beside the page's own sv_part): pressing Play, pausing and
playing again sends one count, of the kind only (no song, part or path), and only when the footer says counts are on.
Needs the real server (a public part page): python3 -I tests/flows/part_page_count.py http://127.0.0.1:<port>"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SPY = """window.__beacons = [];
navigator.sendBeacon = (url, body) => { body.text().then((text) => window.__beacons.push([url, text])); return true; };"""
PLAY = "document.querySelector('[data-ui=part-play]')"


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": SPY})
    f.go("/songs/amazing-grace/alto", f"!!{PLAY}")
    on = f.js("!!document.querySelector('[data-site-visit]')")
    f.wait("window.__beacons.length >= (document.querySelector('[data-site-visit]') ? 1 : 0)", what="the page's own count")
    before = f.js("window.__beacons.length")
    f.run(f"{PLAY}.click()")
    f.wait(f"{PLAY}.dataset.state === 'playing'", timeout=25, what="the part playing")
    f.run(f"{PLAY}.click()")
    f.wait(f"{PLAY}.dataset.state === 'paused'", what="the part paused")
    f.run(f"{PLAY}.click()")
    f.wait(f"{PLAY}.dataset.state === 'playing'", timeout=25, what="the part playing again")
    f.js("new Promise((r) => setTimeout(r, 300))")
    sent = [json.loads(text) for url, text in f.js("window.__beacons")[before:] if url == "/api/counts"]
    plays = [s for s in sent if any(e.get("kind") == "part_play" for e in s["events"])]
    if not on:
        assert plays == [], f"no count where the footer says counts are off: {plays}"
        return
    assert len(plays) == 1, f"one part_play a page load, not {len(plays)}"
    assert plays[0] == {"events": [{"kind": "part_play"}], "signedIn": False}, f"the kind only: {plays[0]}"


run("part_page_count", journey)
