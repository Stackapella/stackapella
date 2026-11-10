"""The free tools embedded in another site (js/public/tools/embed.js, the /embed/tools/<tool> view): on the embed demo page
framed at 300 and 600 px wide, the tuner and the metronome mount compact (no full-screen height, the bar in the flow),
take ?theme= and ?skin= for the frame without storing them, keep everything inside the frame's width, and end with the
credit, which opens a new tab. Both skins (FLOW_SKINS=game,serious)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


IN = "document.querySelector('iframe').contentWindow"


def journey(f):
    for tool in ("tuner", "metronome"):
        for width in (300, 600):
            f.go(f"/tests/embed-host.html?tool={tool}&w={width}&h=720", f"{IN}.__embed")
            doc = f"{IN}.document"
            f.wait(f"{doc}.documentElement.dataset.embed !== undefined", what="marked as an embed")
            assert f.js(f"{doc}.documentElement.dataset.theme") == "warm-light" and f.js(f"{doc}.documentElement.dataset.skin") == "serious", "the look from the link"
            assert f.js(f"!JSON.stringify({IN}.localStorage).includes('warm-light')"), "the link's look isn't stored"
            assert f.js(f"{IN}.getComputedStyle({doc}.querySelector('[data-ui=tool-bar]')).position") == "static", "the bar in the flow"
            wide = f.js(f"[...{doc}.querySelectorAll('[data-embed-stage] *')].filter((el) => el.getBoundingClientRect().right > {width} + 1).map((el) => el.dataset.ui || el.tagName).slice(0, 5)")
            assert not wide, f"{tool} at {width}: past the frame's edge: {wide}"
            assert f.js(f"{doc}.documentElement.scrollWidth <= {width}"), f"{tool} at {width}: the frame scrolls sideways"
            credit = f.js(f"(() => {{ const a = {doc}.querySelector('[data-ui=embed-credit]'); return a && [a.textContent, a.target, a.rel, a.href].join('|'); }})()")
            assert credit and credit.startswith("Free tools by Stackapella|_blank|noopener|https://app.stackapella.com/"), credit
            f.shot(f"{tool}-{width}")


if __name__ == "__main__":
    run("tool_embed", journey, sizes=("desktop",))
