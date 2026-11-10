"""The free tools in game mode (docs/tools-plan.md; the game agent's reskin of the frontend's engines): each tool's card
has the arcade edge and shadow, its pieces are square and its reading (the tuner's note, the metronome's BPM, a
trainer's streak) is in the pixel face, at AA in all eight themes; serious mode keeps today's calm card. On the demo pages, marked as the public
layout marks an arcade page (<html data-gs>, <body class="s-arcade">)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import every_theme  # noqa: E402

ARCADE_PAGE = "document.documentElement.dataset.gs = ''; document.body.classList.add('s-arcade')"
STYLE = "getComputedStyle(document.querySelector({sel!r}))"


def look(f, card, reading=None):
    """The card's frame and corners, and the reading's face."""
    frame = f.js(f"{STYLE.format(sel=card)}.boxShadow")
    corner = f.js(f"{STYLE.format(sel=card)}.borderTopLeftRadius")
    face = f.js(f"{STYLE.format(sel=reading)}.fontFamily") if reading else ""
    return frame, corner, face


def check(f, what, card, reading=None):
    frame, corner, face = look(f, card, reading)
    if f.skin == "serious":
        assert corner != "0px", f"{what}: serious keeps the calm card ({corner})"
        assert "Stackapella Pixel" not in face, f"{what}: serious keeps the app's face ({face})"
        return
    assert frame != "none" and corner == "0px", f"{what}: the arcade card ({frame}, {corner})"
    if reading:
        assert "Stackapella Pixel" in face, f"{what}: the reading in the pixel face ({face})"
    f.no_sideways(what)
    f.shot(what.replace(" ", "-"))
    every_theme(f, what)


def journey(f):
    # The tuner, listening, a note 30 cents sharp: the needle right of the band.
    f.go("/tests/tuner-demo.html?play=1", "window.__tuner")
    f.run(ARCADE_PAGE)
    f.run("document.querySelector('[data-start]').click()")
    f.wait("window.__play", what="listening")
    f.run("window.__play(440 * 2 ** (30 / 1200), 1.2)")
    f.wait("document.querySelector('[data-ui=tuner-note] [data-name]').textContent === 'A'", what="A, sharp")
    if f.skin == "game":
        # The shared tool layout (Hillel's tuner v3): a square needle that keeps its glow, the modes as the arcade's
        # segmented control, the status a square tag, and More's sheet framed.
        assert f.js("getComputedStyle(document.querySelector('[data-ui=tuner-needle]')).borderTopLeftRadius") == "0px", "a square needle"
        assert f.js("getComputedStyle(document.querySelector('[data-ui=tuner-needle]')).boxShadow") != "none", "the needle keeps its glow"
        assert f.js("getComputedStyle(document.querySelector('[data-ui=tool-modes] > *')).boxShadow") != "none", "the modes framed"
        assert f.js("getComputedStyle(document.querySelector('[data-ui=tool-status]')).borderTopLeftRadius") == "0px", "a square status tag"
        f.run("document.querySelector('[data-ui=tool-more]').click()")
        f.wait("document.querySelector('[data-ui=tool-sheet]').open", what="More's sheet")
        assert f.js("getComputedStyle(document.querySelector('[data-ui=tool-sheet]')).borderTopLeftRadius") == "0px", "a square sheet"
        f.shot("tuner-sheet")
        f.run("document.querySelector('[data-sheet-done]').click()")
        f.wait("!document.querySelector('[data-ui=tool-sheet]').open", what="the sheet closed")
    if f.skin == "game" and f.size == "desktop":
        assert f.js("getComputedStyle(document.querySelector('[data-ui=tool-main]')).boxShadow") != "none", "the desktop card framed"
    check(f, "tuner", "[data-ui=tool-panel]", "[data-ui=tuner-note] [data-name]")

    # The metronome (on the shared layout): the beats as square bulbs, the BPM in the pixel face.
    f.go("/tests/metronome-demo.html", "window.__metronome && document.querySelector('[data-dot]')")
    f.run(ARCADE_PAGE)
    if f.skin == "game":
        assert f.js("getComputedStyle(document.querySelector('[data-dot] [data-bar]')).borderTopLeftRadius") == "0px", "square beat bars"
        assert f.js("getComputedStyle(document.querySelector('[data-ui=metronome-slower]')).borderTopLeftRadius") == "0px", "a square minus"
        assert f.js("getComputedStyle(document.querySelector('[data-ui=tool-controls] :has(> [data-segment])')).boxShadow") != "none", "the subdivisions framed"
        cut = "[...document.querySelectorAll('[data-segment] span + span')].filter((s) => s.offsetWidth && s.scrollWidth > s.parentElement.clientWidth).map((s) => s.textContent)"
        assert not f.js(cut), f"a segment's word runs past its segment: {f.js(cut)}"
    check(f, "metronome", "[data-ui=tool-panel]", "[data-ui=metronome-bpm]")

    # The vocal range test (on the shared layout): square keys.
    f.go("/tests/range-demo.html?play=1", "window.__range && document.querySelector('[data-ui=range-keyboard] > *')")
    f.run(ARCADE_PAGE)
    if f.skin == "game":
        assert f.js("getComputedStyle(document.querySelector('[data-ui=range-keyboard] > *')).borderBottomLeftRadius") == "0px", "square keys"
    check(f, "range test", "[data-ui=tool-panel]")

    # The ear trainers (on the shared layout): a square level bar; a dot on the big button stays a dot.
    for name in ("chord", "interval"):
        f.go(f"/tests/{name}-demo.html?fresh", "window.__trainer && document.querySelector('[data-ui=level-bar] [data-segments] > li')")
        f.run(ARCADE_PAGE)
        if f.skin == "game":
            assert f.js("getComputedStyle(document.querySelector('[data-ui=level-bar] [data-segments] > li')).borderTopLeftRadius") == "0px", f"{name}: a square level bar"
            dot = "(i => i.className.includes('rounded-full') ? getComputedStyle(i).borderTopLeftRadius : 'not a dot')(document.querySelector('[data-primary-icon]'))"
            assert f.js(dot) != "0px", f"{name}: the big button's dot stays a dot, not a stop square"
        check(f, f"{name} trainer", "[data-ui=tool-panel]")

    # The latency test (on the shared layout): the delay in the pixel face.
    f.go("/tests/latency-demo.html", "window.__latency && document.querySelector('[data-ui=tool-panel]')")
    f.run(ARCADE_PAGE)
    check(f, "latency test", "[data-ui=tool-panel]", "[data-ui=delay]")

    # The reference cards (an interval, a chord, a voice's range): the card framed, square keys, the tuning framed.
    for query, what in (("card=interval&semis=4", "interval card"), ("card=chord&quality=dom7", "chord card"), ("card=voice&type=tenor", "voice card")):
        f.go(f"/tests/reference-cards-demo.html?{query}", "document.querySelector('[data-ui=ref-card] [data-midi]')")
        f.run(ARCADE_PAGE)
        if f.skin == "game":
            assert f.js("getComputedStyle(document.querySelector('[data-ui=ref-card] [data-midi]')).borderBottomLeftRadius") == "0px", f"{what}: square keys"
        check(f, what, "[data-ui=ref-card]")

if __name__ == "__main__":
    run("game_tools", journey)
