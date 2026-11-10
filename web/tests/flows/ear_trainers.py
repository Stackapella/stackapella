"""The ear trainers for /tools (js/public/tools/interval-trainer.js and chord-trainer.js on the shared quiz,
js/public/tools/ear-quiz.js) on the free tools' one layout (js/public/tool-layout.js), under the tools' frame
(web/tests/interval-demo.html, chord-demo.html; tests/tool-frame.js's brand row holds the level stepper and
the Theme button). The first screen: the modes, the answer hero with its pill, Hillel's level bar, the
answers in the panel, Play pinned with ⋯ beside it; Play and every answer above the fold on a 390 x 844
phone and a 320 x 568 one. Play sings a round in the guide voice; an answer is marked by sign and word,
with what it was (a chord's notes lit on the keyboard); a miss offers Yours and Right; the keys answer; the
level stepper walks the ladder; the settings and results are in the sheet behind ⋯.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

TOOL = "document.querySelector('#trainer [data-ui=tool]')"
T = "window.__trainer"
PLAY = f"{TOOL}.querySelector('[data-ui=tool-primary]')"
PILL = f"{TOOL}.querySelector('[data-ui=tool-status]').textContent"


def visible(f):
    return f.js(f"[...{TOOL}.querySelectorAll('[data-answer]')].filter((b) => !b.hidden).map((b) => b.dataset.answer)")


def above_fold(f, what):
    """Play and every answer tile end above the bottom of the screen."""
    low = f.js(f"Math.max(...[{PLAY}, ...[...{TOOL}.querySelectorAll('[data-answer]')].filter((b) => !b.hidden)].map((e) => e.getBoundingClientRect().bottom))")
    assert low <= f.js("innerHeight") + 1, f"{what}: Play and the answers end at {low}, below {f.js('innerHeight')}"


def round_trip(f, page, what, first_set):
    name = page.split("-")[0]
    f.go(f"/tests/{page}?fresh", f"{T} && document.querySelector('header [data-tool-key] [data-ui=tool-key]')")
    f.no_sideways(what)
    assert visible(f) == first_set, f"{what}: {visible(f)}"
    assert f.js(f"{TOOL}.querySelectorAll('[data-ui=tool-modes] [data-mode]').length") == 3, "the ways to play it"
    assert f.js("document.querySelector('header [data-ui=tool-key] [data-step-value]').textContent") == "1", "the level in the brand row"
    above_fold(f, what)
    f.shot(f"{name}-ready")
    f.run(f"{PLAY}.click()")
    f.wait(f"{T}.current() && {TOOL}.dataset.phase === 'asking'", what=f"{what}: a round")
    key = f.js(f"{T}.current().key")
    f.run(f"{TOOL}.querySelector('[data-answer=\"{key}\"]').click()")
    assert f.js(f"{TOOL}.querySelector('[data-answer=\"{key}\"] [data-mark]').textContent") == "✓", "marked right"
    assert f.js(PILL).strip().startswith("Right"), f.js(PILL)
    assert " · " in f.js(PILL), "what it was"
    assert f.js(f"{TOOL}.querySelector('[data-ui=tool-stat]').textContent").startswith("100%"), "the stat"
    above_fold(f, f"{what}, answered")
    f.shot(f"{name}-right")
    # Next from the keyboard, answered wrong from the keyboard: both marks, Yours and Right.
    f.run(f"{PLAY}.blur(); document.body.dispatchEvent(new KeyboardEvent('keydown', {{ key: ' ', code: 'Space', bubbles: true }}))")
    f.wait(f"{TOOL}.dataset.phase === 'asking'", what="the next round, from space")
    key = f.js(f"{T}.current().key")
    wrong_index = next(i for i, k in enumerate(visible(f)) if k != key)
    f.run(f"document.body.dispatchEvent(new KeyboardEvent('keydown', {{ key: '{wrong_index + 1}', bubbles: true }}))")
    wrong = visible(f)[wrong_index]
    assert f.js(f"{TOOL}.querySelector('[data-answer=\"{wrong}\"] [data-mark]').textContent") == "✗", "marked wrong"
    assert f.js(PILL).strip().startswith("You said"), f.js(PILL)
    assert f.js(f"{TOOL}.querySelectorAll('[data-ui=level-segment]')[1].dataset.result") == "wrong", "the level bar's second answer"
    assert f.js(f"!{TOOL}.querySelector('[data-compare]').hidden"), "Yours and Right offered"
    f.run(f"{TOOL}.querySelector('[data-yours]').click()")
    f.no_sideways(f"{what}, answered")
    f.shot(f"{name}-wrong")


def lesson_moment(f, page):
    """Five answers this visit are the moment for the page's free lesson (page.js fills the bar's line), once."""
    f.go(f"/tests/{page}?fresh", T)
    for n in range(6):
        f.run(f"{PLAY}.click()")
        f.wait(f"{T}.current() && {TOOL}.dataset.phase === 'asking'", what=f"round {n + 1}")
        assert f.js("window.__lessons ?? 0") == (1 if n >= 5 else 0), f"after {n} answers: {f.js('window.__lessons')}"
        f.run(f"{T}.answer({T}.current().key)")
    assert f.js("window.__lessons") == 1, "once"


STUB_SHARE = """window.__sent = null; navigator.canShare = (d) => !!d?.files?.length;
navigator.share = async (d) => { window.__sent = { type: d.files[0].type, size: d.files[0].size, name: d.files[0].name, text: d.text, url: d.url }; };"""


def share_moment(f, page, slug):
    """Eight of the last ten right at a level is a result worth a card: Share it, a link in the level bar's caption, with the tool's
    link (?ref=share-<tool>), counted by the page (onShared). Not before ten answers, gone on the next round."""
    f.go(f"/tests/{page}?fresh", T)
    share_row = f"{TOOL}.querySelector('[data-share-wrap]')"
    for n in range(10):
        f.run(f"{PLAY}.click()")
        f.wait(f"{T}.current() && {TOOL}.dataset.phase === 'asking'", what=f"round {n + 1}")
        if n == 0:
            wrong = next(k for k in visible(f) if k != f.js(f"{T}.current().key"))
            f.run(f"{T}.answer('{wrong}')")
        else:
            f.run(f"{T}.answer({T}.current().key)")
        assert f.js(f"{share_row}.hidden") == (n < 9), f"after {n + 1} answers the share is {'hidden' if f.js(f'{share_row}.hidden') else 'shown'}"
    assert f.js(f"{TOOL}.querySelector('[data-share]').textContent") == "Share it"
    assert f.js(f"{TOOL}.querySelector('[data-level-caption]').textContent") == "9 of the last 10 right", "the caption makes room for it"
    above_fold(f, f"{page}, the share offered")
    f.no_sideways(f"{page}, the share offered")
    f.shot(f"{slug}-share")
    f.run(STUB_SHARE)
    f.run(f"{TOOL}.querySelector('[data-share]').click()")
    f.wait("window.__sent && window.__shared === 1", what="the card shared and counted")
    sent = f.js("window.__sent")
    assert sent["type"] == "image/png" and sent["size"] > 20000, sent
    assert sent["url"].endswith(f"/tools/{slug}?ref=share-{slug}"), sent["url"]
    assert sent["text"].startswith("I named 9 of my last 10"), sent["text"]
    # Where files can't be shared: the card downloads and the line and link are copied.
    f.run("navigator.canShare = () => false; window.__copied = null; navigator.clipboard.writeText = async (t) => { window.__copied = t; };")
    f.run(f"{TOOL}.querySelector('[data-share]').click()")
    f.wait("window.__shared === 2 && window.__copied", what="the card saved and the line copied")
    assert f.js(f"{TOOL}.querySelector('[data-share-said]').textContent") == "Card saved, and the line copied"
    f.shot(f"{slug}-shared")
    f.run(f"{PLAY}.click()")
    f.wait(f"{TOOL}.dataset.phase === 'asking'", what="the next round")
    assert f.js(f"{share_row}.hidden"), "the next round puts it away"


def journey(f):
    round_trip(f, "interval-demo.html", "the interval trainer", ["M2", "M3", "P4", "P5", "P8"])
    # The sheet behind ⋯: the tritone added (your own set), from a fixed note; then Together from the modes.
    f.run(f"{TOOL}.querySelector('[data-ui=tool-more]').click()")
    f.wait(f"{TOOL}.querySelector('[data-ui=tool-sheet]').open", what="the sheet")
    assert f.js(f"{TOOL}.querySelector('[data-results-rows]').children.length") >= 1, "results by answer"
    f.run(f"{TOOL}.querySelector('[data-choose] input[value=TT]').click()")
    f.run(f"{TOOL}.querySelector('[data-toggle=fixedRoot]').click()")
    f.shot("interval-sheet")
    f.run(f"{TOOL}.querySelector('[data-sheet-done]').click()")
    f.run(f"{TOOL}.querySelector('[data-ui=tool-modes] [data-mode=together]').click()")
    assert "TT" in visible(f), "the tritone to answer"
    f.run(f"{PLAY}.click()")
    f.wait(f"{T}.current().way === 'together' && {T}.current().base === 60", what="played together, from middle C")

    round_trip(f, "chord-demo.html", "the chord trainer", ["maj", "min"])
    assert f.js(f"{TOOL}.querySelectorAll('[data-ui=key][data-lit=true]').length") == 3 or f.size == "se", "the chord's notes lit"
    # The level stepper up twice: the sevenths join; played broken, going up.
    for _ in range(2):
        f.run("document.querySelector('header [data-ui=tool-key] [data-step=\"1\"]').click()")
    assert len(visible(f)) == 7, visible(f)
    f.run(f"{TOOL}.querySelector('[data-ui=tool-modes] [data-mode=up]').click()")
    f.run(f"{PLAY}.click()")
    f.wait(f"{T}.current().way === 'up'", what="broken, going up")
    assert f.js(PILL).strip() == "Which chord?", f"the question widens with the level: {f.js(PILL)}"
    above_fold(f, "the chord trainer at level 3")
    f.shot("chord-level3")
    lesson_moment(f, "interval-demo.html")
    lesson_moment(f, "chord-demo.html")
    share_moment(f, "interval-demo.html", "interval-ear-training")
    share_moment(f, "chord-demo.html", "chord-ear-training")


if __name__ == "__main__":
    run("ear_trainers", journey, sizes=("phone", "se", "desktop"))
