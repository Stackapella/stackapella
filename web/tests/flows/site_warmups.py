"""The free warm-ups' pages (/warmups and /warmups/<warm-up>, docs/seo-growth-plan.md item 8), on the server's own site
like site_integration: the list links every warm-up, and each page opens with the tools' brand row (its logo leads to
the landing, ?ref=warmups, one source in the counts), the player mounted from the page's own data with its tempo beside the logo,
Start, the instruction and the courses lead's stop line on the first screen, nothing sideways; Start plays and lights
a key, Stop stops; the searched heading follows the player.

    python3 -I tests/flows/site_warmups.py http://127.0.0.1:8791
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('[data-tool] [data-ui=tool]')"
Q = lambda s: f"{TOOL}.querySelector('{s}')"  # noqa: E731
SEEN = "(el) => { const r = el.getBoundingClientRect(); return !el.hidden && r.height > 0 && r.bottom <= innerHeight + 1; }"


def journey(f):
    f.go("/warmups", "document.readyState === 'complete'")
    f.no_sideways("the warm-ups list")
    ids = f.js("JSON.stringify([...document.querySelectorAll('main a[href^=\"/warmups/\"]')].map((a) => a.getAttribute('href').split('/')[2]))")
    ids = __import__("json").loads(ids)
    assert len(ids) >= 4, f"the list links {ids}"
    assert f.js("document.querySelectorAll('h1').length") == 1, "one h1 on the list"
    f.shot("list")
    for i, warmup in enumerate(ids):
        f.go(f"/warmups/{warmup}", f"{TOOL} && document.querySelector('[data-tool-bar] [data-tool-key] [data-step]')")
        f.run(f"localStorage.removeItem('stackapella.tools.warmups-{warmup}')")
        f.go(f"/warmups/{warmup}", f"{TOOL} && document.querySelector('[data-tool-bar] [data-tool-key] [data-step]')")
        f.no_sideways(warmup)
        assert f.js("[...document.body.children].find((el) => el.tagName !== 'SCRIPT') === document.querySelector('body > header.s-top') && !!document.querySelector('body > header.s-top + [data-tool-bar]')"), f"{warmup}: the site header first, then the tool bar"
        home = f.js("document.querySelector('header.s-top .s-brand').getAttribute('href')")
        assert home == "/?ref=warmups", f"{warmup}: the logo leads to {home}"
        assert f.js(f"({SEEN})({Q('[data-ui=tool-primary]')})"), f"{warmup}: Start above the fold"
        for part in ("[data-how-to]", "[data-stop-line]"):
            assert f.js(f"{Q(part)}.textContent.trim().length > 0 && ({SEEN})({Q(part)})"), f"{warmup}: {part} on the first screen"
        assert f.js("document.querySelectorAll('h1').length") == 1, f"{warmup}: one h1"
        fits = f.js(f"(() => {{ const k = {Q('[data-keyboard]')}.getBoundingClientRect(), p = {Q('[data-ui=tool-panel]')}.getBoundingClientRect(); return k.height >= 60 && k.bottom <= p.bottom + 1; }})()")
        assert fits, f"{warmup}: the keys whole inside the panel"
        if i == 0:
            f.shot(f"{warmup}-ready")
        f.run(f"{Q('[data-ui=tool-primary]')}.click()")
        f.wait(f"{TOOL}.querySelector('[data-keyboard] [data-marked]')", what=f"{warmup}: a key lit as its note sounds")
        if i == 0:
            f.shot(f"{warmup}-playing")
        f.run(f"{Q('[data-ui=tool-primary]')}.click()")
        assert f.js(f"{TOOL}.dataset.playing") == "false", f"{warmup}: stopped"


run("site_warmups", journey)
