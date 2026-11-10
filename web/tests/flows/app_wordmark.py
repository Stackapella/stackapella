"""The app's header says "Stackapella" as the site's header and the logo set do (the coordinator,
2026-10-30): 18 px, -0.01em, in Figtree ExtraBold in serious mode and in the arcade's pixel face in game mode (the
arcade wordmark, data-ui="wordmark", since Home followed Hillel's original, 2026-11-01).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import home_page  # noqa: E402

WORDS = "[...document.querySelectorAll('#app header [translate=no]')].find((s) => s.textContent.trim().toLowerCase() === 'stackapella')"


def journey(f):
    home_page(f, "member")
    face, weight = ("Stackapella Pixel", "400") if f.skin == "game" else ("Figtree", "800")
    f.wait(f"document.fonts.check('{weight} 18px \"{face}\"') && {WORDS}", what=f"the name, in {face}")
    style = f.js(f"(() => {{ const s = getComputedStyle({WORDS}); return {{ text: {WORDS}.textContent.trim(), family: s.fontFamily.split(',')[0].replace(/\"/g, ''), weight: s.fontWeight, size: s.fontSize, tracking: s.letterSpacing }}; }})()")
    assert style == {"text": "Stackapella", "family": face, "weight": weight, "size": "18px", "tracking": "-0.18px"}, style
    f.run("window.scrollTo(0, 0)")
    f.shot("header")


if __name__ == "__main__":
    run("app_wordmark", journey)
