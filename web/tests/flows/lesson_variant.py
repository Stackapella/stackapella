"""An experiment's arm of a lesson (js/experiments.js, docs/experiments-plan.md): a lesson's
`variants` swapped in for the arm a device is in, drawn as the lesson opens, kept, and carried on the
counts as "<id>-<arm>"; arm a (or out) is the lesson as written; ?x= previews an arm, never counted.
Against the mock, with the experiment served by it and lesson 1's variant as the course has it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

EXPERIMENT = {"id": "short1", "arms": [{"arm": "a", "weight": 50}, {"arm": "b", "weight": 50}], "enrol": True, "audience": {"firstWeek": True}}
STEPS = "document.querySelectorAll('[data-step-bars] > span').length"


def open_lesson(f, kept, query=""):
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    f.run(f"localStorage.setItem('stackapella.experiments', {json.dumps(json.dumps(kept))}); sessionStorage.removeItem('stackapella.experimentPreview');"
          f"sessionStorage.setItem('stackapella.mockExperiments', {json.dumps(json.dumps([EXPERIMENT]))});")
    f.go(f"/learn/1-comfortable{query}", "document.querySelector('main h1, #app h1')")


def journey(f):
    # Arm b: the variant's one step.
    open_lesson(f, {"short1": "b"})
    f.wait(f"{STEPS} === 1", what="arm b's one step")
    # Its own line, from the course (the content's short1-b), which carries the learn card's steps.
    f.wait("document.querySelector('main h1')?.textContent.includes('softly on')", what="arm b's own line")
    # (This browser lets the mic in without asking: told the question is still to come, the lesson opened again.)
    f.run("const query = navigator.permissions.query.bind(navigator.permissions); navigator.permissions.query = async (d) => (d.name === 'microphone' ? { state: 'prompt' } : query(d));"
          "const { navigate } = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    # Its one step finds your note, so it says why we listen first, as a step before it would (the
    # courses lead's gate for short1): Turn on my mic and its line, then the note finder.
    f.wait("document.querySelector('[data-mic-first] [data-mic-why]') && !document.querySelector('[data-listening]')", what="the mic moment before the note finder")
    f.shot("arm-b")
    ua = "Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 350.0.0"
    f.run(f"Object.defineProperty(navigator, 'userAgent', {{ get: () => {ua!r}, configurable: true }}); const {{ navigate }} = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    f.wait("document.querySelector('[data-mic-first] [data-in-app]')", what="the in-app card on arm b's step")
    f.shot("arm-b-in-app")
    f.run("delete navigator.userAgent; const { navigate } = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    f.wait("document.querySelector('[data-mic-first-go]')", what="Turn on my mic")
    f.run("document.querySelector('[data-mic-first-go]').click()")
    f.wait("!document.querySelector('[data-mic-first]')", what="on to the note finder")
    f.wait("JSON.stringify((await import('/js/experiments.js')).enrolledVariants()) === '[\"short1-b\"]'", what="the counts carrying the arm")

    # Arm a: the lesson as written.
    open_lesson(f, {"short1": "a"})
    f.wait(f"{STEPS} > 1", what="arm a's steps, as written")

    # A new device: drawn as the lesson opens, and kept.
    open_lesson(f, {})
    f.wait("JSON.parse(localStorage.getItem('stackapella.experiments') || '{}')['short1']", what="an arm drawn on opening")
    drawn = f.js("JSON.parse(localStorage.getItem('stackapella.experiments'))['short1']")
    assert drawn in ("a", "b"), drawn

    # A preview: arm b in this tab, never kept or counted.
    open_lesson(f, {}, "?x=short1-b")
    f.wait(f"{STEPS} === 1", what="the preview's one step")
    kept = f.js("JSON.parse(localStorage.getItem('stackapella.experiments') || '{}')")
    assert "short1" not in kept or kept["short1"] in ("a", "b"), kept
    assert f.js("(await import('/js/experiments.js')).experiment('short1').preview"), "a preview"


if __name__ == "__main__":
    run("lesson_variant", journey)
