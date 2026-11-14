"""The tool variants' pages (docs/seo-growth-plan.md item 3, SitePages::TOOL_VARIANTS) on the server's own site, like
site_integration: /tools/ukulele-tuner and /tools/violin-tuner open the tuner on that instrument, /tools/voice-tuner on
Voice, /tools/tanpura the drone in tanpura mode and /tools/432-hz-tuning-fork the forks with A432 chosen first. Each has
the brand row first (its logo counted as ?ref=tool-<slug>), its tool above the fold, its own heading after it, its tool
featured below, and nothing sideways. The preset is for the visit: the tool's saved settings stay the person's.

    python3 -I tests/flows/site_variants.py http://127.0.0.1:8791
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

PRESSED = "document.querySelector('[data-ui=tool-modes] [data-segment={}]')?.getAttribute('aria-pressed') === 'true'"
READY = "document.querySelector('.s-tool')?.dataset.state === 'ready'"
CHECKS = {
    "ukulele-tuner": ("tuner", f"{PRESSED.format('instrument')} && document.querySelector('[data-instrument]').value === 'ukulele'"),
    "violin-tuner": ("tuner", f"{PRESSED.format('instrument')} && document.querySelector('[data-instrument]').value === 'violin'"),
    "voice-tuner": ("tuner", PRESSED.format("voice")),
    "guitar-tuner": ("tuner", f"{PRESSED.format('instrument')} && document.querySelector('[data-instrument]').value === 'guitar' && document.querySelector('[data-tuning]').value === 'standard' && document.querySelector('[data-tuning-quick]').value === 'standard'"),
    "bass-tuner": ("tuner", f"{PRESSED.format('instrument')} && document.querySelector('[data-instrument]').value === 'bass4'"),
    "cello-tuner": ("tuner", f"{PRESSED.format('instrument')} && document.querySelector('[data-instrument]').value === 'cello'"),
    "viola-tuner": ("tuner", f"{PRESSED.format('instrument')} && document.querySelector('[data-instrument]').value === 'viola'"),
    "mandolin-tuner": ("tuner", f"{PRESSED.format('instrument')} && document.querySelector('[data-instrument]').value === 'mandolin'"),
    "banjo-tuner": ("tuner", f"{PRESSED.format('instrument')} && document.querySelector('[data-instrument]').value === 'banjo'"),
    "tanpura": ("drone", PRESSED.format("tanpura")),
    "432-hz-tuning-fork": ("tuning-forks", "document.querySelector('[data-fork-name]').textContent === 'A432' && [...document.querySelectorAll('[data-shelf] [data-fork]')].map((c) => c.dataset.fork).join() === 'a432,c256,a440'"),
}


def journey(f):
    for slug, (tool, opened) in CHECKS.items():
        # Someone who keeps the tuner on Voice, the drone on Drone: the variant shows its preset, theirs stays saved.
        mine = {"v": 1, "updatedAt": 1, "settings": {"mode": "voice", "instrument": "chromatic"}} if tool == "tuner" else ({"mode": "drone"} if tool == "drone" else None)
        f.go(f"/tools/{slug}", "document.readyState === 'complete'")
        f.run(f"localStorage.removeItem('stackapella.tools.{tool}')" if mine is None else f"localStorage.setItem('stackapella.tools.{tool}', {json.dumps(json.dumps(mine))})")
        f.go(f"/tools/{slug}", READY)
        f.no_sideways(slug)
        assert f.js(opened), f"{slug} opens its tool with its preset"
        if tool == "tuner":
            # An instrument's own page has no Voice to switch to (the tools review, 2026-11-14); the voice tuner keeps both.
            hidden = f.js("document.querySelector('[data-ui=tool-modes]').hidden")
            assert hidden == (slug != "voice-tuner"), f"{slug}: Voice | Instrument {'shown' if not hidden else 'hidden'}"
        assert f.js("[...document.body.children].find((el) => el.tagName !== 'SCRIPT') === document.querySelector('body > header.s-top') && !!document.querySelector('body > header.s-top + [data-tool-bar]')"), f"{slug}: the site header first, then the tool bar"
        home = f.js("document.querySelector('header.s-top .s-brand').getAttribute('href')")
        assert home == f"/?ref=tool-{slug}", f"{slug}: the logo leads to {home}"
        assert f.js("(() => { const b = document.querySelector('[data-ui=tool-primary]'); if (!b) return true; const r = b.getBoundingClientRect(); return r.height > 0 && r.bottom <= innerHeight + 1; })()"), f"{slug}: its primary action above the fold"
        assert f.js("document.querySelectorAll('h1').length") == 1, f"{slug}: one h1"
        assert f.js(f"!!document.querySelector('a.s-tfeat[href=\"/tools/{tool}\"]')"), f"{slug}: its tool featured below"
        if mine is not None:
            kept = f.js(f"JSON.parse(localStorage.getItem('stackapella.tools.{tool}') || 'null')")
            assert kept == mine, f"{slug}: the person's own {tool} settings kept, not {kept}"
        f.shot(slug)


run("site_variants", journey)
