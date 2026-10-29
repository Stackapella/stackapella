"""The app's header says "Stackapella" as the site's header and the logo set do (the coordinator,
2026-10-30): Figtree ExtraBold, 18 px, -0.01em, in both skins.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import home_page  # noqa: E402

WORDS = "[...document.querySelectorAll('#app header [translate=no]')].find((s) => s.textContent.trim().toLowerCase() === 'stackapella')"


def journey(f):
    home_page(f, "member")
    f.wait(f"document.fonts.check('800 18px Figtree') && {WORDS}", what="the name, in Figtree")
    style = f.js(f"(() => {{ const s = getComputedStyle({WORDS}); return {{ text: {WORDS}.textContent.trim(), weight: s.fontWeight, size: s.fontSize, tracking: s.letterSpacing }}; }})()")
    assert style == {"text": "Stackapella", "weight": "800", "size": "18px", "tracking": "-0.18px"}, style
    f.run("window.scrollTo(0, 0)")
    f.shot("header")


if __name__ == "__main__":
    run("app_wordmark", journey)
