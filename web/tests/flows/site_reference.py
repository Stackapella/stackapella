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
            assert f.js("document.querySelector('[data-ui=ref-facts], .s-vt-facts').textContent.trim().length > 0"), f"{path}: the facts"
            if family == "voice-types":
                voice_type(f, path)
            # Every play button sounds without an error (the flow fails on any page error).
            f.run("document.querySelectorAll('[data-ui=ref-card] [data-plays] button').forEach((b) => b.click())")
            if i in (2, 3) and family != "voice-types" or (family == "voice-types" and i == 2):
                f.shot(path.strip("/").replace("/", "-"))


def voice_type(f, path):
    """A voice type in Hillel's design (2026-11-11): its card bare beside its facts, every type on one scale with itself
    lit, its song parts as bars inside its range, the contents only from 900 px, and no button's label ever wrapping."""
    assert not f.js("document.querySelector('[data-ui=ref-card] [data-ui=ref-staff], [data-ui=ref-card] [data-ui=ref-facts], [data-ui=ref-find]')"), f"{path}: the card is bare"
    rungs = json.loads(f.js("JSON.stringify([...document.querySelectorAll('.s-vt-ladder a')].map((a) => [a.getAttribute('href'), a.getAttribute('aria-current')]))"))
    assert len(rungs) == 6 and [h for h, c in rungs if c == "page"] == [path], f"{path}: the ladder {rungs}"
    bars = f.js("[...document.querySelectorAll('.s-vt-bar')].every((b) => { const t = b.parentElement.getBoundingClientRect(), r = b.getBoundingClientRect(); return r.width > 0 && r.left >= t.left - 1 && r.right <= t.right + 1; })")
    assert bars, f"{path}: every bar inside its track"
    wide = f.js("innerWidth >= 900")
    assert f.js("getComputedStyle(document.querySelector('.s-vt-toc')).display !== 'none'") == wide, f"{path}: the contents only from 900 px"
    # A label never wraps: each button is one line tall and nothing overflows it (Hillel, 2026-11-11).
    wraps = json.loads(f.js("JSON.stringify([...document.querySelectorAll('.s-vt-btns a, [data-ui=ref-card] [data-plays] button')].filter((b) => b.scrollWidth > b.clientWidth + 1 || b.getBoundingClientRect().height > 64).map((b) => b.textContent.trim()))"))
    assert wraps == [], f"{path}: labels wrapping {wraps}"


run("site_reference", journey)
