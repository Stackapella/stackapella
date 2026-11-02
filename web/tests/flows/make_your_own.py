"""Make your own (Hillel's 93.png, "same list, tighter rhythm, clear tap affordance"): the sheet's
subtitle, each way in as a card with its own tinted icon tile and a chevron, Hum a tune only for those
it's switched on for, and Open a file's own hint and file input; in both skins.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.wait("document.querySelector('[data-new-song]')", what="New song")
    f.run("document.querySelector('[data-new-song]').click()")
    f.wait("document.querySelector('[data-new-sheet]')", what="New song's sheet")
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.wait_text(t(f, "create.home.chords"))
    f.js("new Promise((r) => setTimeout(r, 300))")
    # The subtitle, each way in a card with its own icon and a chevron (staff see Hum a tune), 44 px or more.
    f.wait_text(t(f, "create.home.makeHint"))
    cards = f.js("""[...document.querySelectorAll('[data-make-option]')].map((c) => ({ h: Math.round(c.getBoundingClientRect().height), icon: c.querySelector('[data-make-icon] svg')?.innerHTML.slice(0, 40), chevron: c.querySelectorAll('svg').length >= 2 }))""")
    assert len(cards) == 5, f"five ways in for staff: {cards}"
    assert all(c["h"] >= 44 and c["chevron"] for c in cards), cards
    assert len({c["icon"] for c in cards}) == 5, f"each way its own icon: {cards}"
    assert f.js("!!document.querySelector('[data-make-option] input[type=file]')"), "Open a file keeps its file input"
    f.shot("make")
    # Right to left, the chevrons point the other way.
    chevron = "getComputedStyle(document.querySelector('[data-make-option] > svg')).rotate"
    ltr = f.js(chevron)
    f.run("document.documentElement.dir = 'rtl'")
    rtl = f.js(chevron)
    f.run("document.documentElement.dir = 'ltr'")
    assert ltr != rtl, f"the chevron points the same way in RTL: {ltr}"


if __name__ == "__main__":
    run("make_your_own", journey)
