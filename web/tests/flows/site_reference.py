"""The reference pages (/intervals, /chords, /voice-types and every page each lists; docs/seo-growth-plan.md item 4) on
the server's own site, like site_integration: each index links its pages in one table; each page has the brand row
first (its logo counted as ?ref=<family>), one H1 before the card, frontend's card mounted from the page's own words
and data (js/public/reference-cards.js), its play buttons sounding without a page error, and nothing sideways.

    python3 -I tests/flows/site_reference.py http://127.0.0.1:8791
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

FAMILIES = ("intervals", "chords", "voice-types")
READY = "document.querySelector('[data-tool]')?.dataset.state === 'ready' && document.querySelector('[data-ui=ref-card]')"


def journey(f):
    for family in FAMILIES:
        f.go(f"/{family}", "document.readyState === 'complete'")
        f.no_sideways(f"/{family}")
        assert f.js("document.querySelectorAll('h1').length") == 1, f"/{family}: one h1"
        assert f.js("document.querySelector('header.s-top .s-brand').getAttribute('href')") == f"/?ref={family}", f"/{family}: the logo's tag"
        pages = json.loads(f.js(f"JSON.stringify([...document.querySelectorAll('main table a[href^=\"/{family}/\"]')].map((a) => a.getAttribute('href')))"))
        assert len(pages) >= 6, f"/{family} links {pages}"
        if family == "intervals":
            f.shot(family)
        for i, path in enumerate(pages):
            # The room held before the card mounts never overshoots it (a jump up is a layout shift too).
            f.go(path, READY)
            held = f.js("(() => { const s = document.querySelector('[data-tool]'); const room = parseFloat(getComputedStyle(s, null).minHeight) || 0; s.removeAttribute('data-state'); const before = parseFloat(getComputedStyle(s).minHeight) || 0; s.dataset.state = 'ready'; return [before, Math.round(s.getBoundingClientRect().height)]; })()")
            assert held[0] <= held[1] + 1, f"{path}: holds {held[0]}px for a {held[1]}px card"
            f.no_sideways(path)
            assert f.js("document.querySelectorAll('h1').length") == 1, f"{path}: one h1"
            assert f.js("document.querySelector('h1').compareDocumentPosition(document.querySelector('[data-ui=ref-card]')) & Node.DOCUMENT_POSITION_FOLLOWING"), f"{path}: the heading before the card"
            assert f.js("document.querySelector('header.s-top .s-brand').getAttribute('href')") == f"/?ref={family}", f"{path}: the logo's tag"
            assert f.js("document.querySelectorAll('[data-ui=ref-card] [data-ui=keyboard] [data-marked], [data-ui=ref-card] [data-marked]').length > 0"), f"{path}: notes marked on the keyboard"
            assert f.js("document.querySelector('[data-ui=ref-facts]').textContent.trim().length > 0"), f"{path}: the facts"
            # Every play button sounds without an error (the flow fails on any page error).
            f.run("document.querySelectorAll('[data-ui=ref-card] [data-plays] button').forEach((b) => b.click())")
            if i in (2, 3) and family != "voice-types" or (family == "voice-types" and i == 2):
                f.shot(path.strip("/").replace("/", "-"))


run("site_reference", journey)
