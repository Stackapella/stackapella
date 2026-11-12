"""An experiment's arm of a lesson (js/experiments.js, docs/experiments-plan.md): a lesson's
`variants` swapped in for the arm a device is in, drawn as the lesson opens, kept, and carried on the
counts as "<id>-<arm>"; arm a (or out) is the lesson as written; ?x= previews an arm, never counted.
Against the mock, with the experiment served by it. The flow brings its own arm, test1-b, written into lesson 1 as the
course is fetched (short1, the first lesson experiment, ended on 2026-11-12 and its variant left the course): lesson 1's
singing step alone, with its own line.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

EXPERIMENT = {"id": "test1", "arms": [{"arm": "a", "weight": 50}, {"arm": "b", "weight": 50}], "enrol": True, "audience": {"firstWeek": True}}
STEPS = "document.querySelectorAll('[data-step-bars] > span').length"
LINE = "A test arm's own line: hum any easy note."
# Lesson 1 as fetched, with test1-b: its last step (finding your note) alone, with LINE.
VARIANT = """(() => {
    const real = window.fetch.bind(window);
    window.fetch = async (input, init) => {
        const response = await real(input, init);
        const url = new URL(typeof input === 'string' ? input : input.url, location.href);
        if (url.pathname !== '/course/course.json' || !response.ok) return response;
        const course = await response.json();
        const lesson = course.units.flatMap((u) => u.lessons).find((l) => l.id === '1-comfortable');
        lesson.variants = { 'test1-b': { steps: [{ ...lesson.steps.at(-1), text: { en: %s } }] } };
        return new Response(JSON.stringify(course), { headers: { 'Content-Type': 'application/json' } });
    };
})();""" % json.dumps(LINE)


def open_lesson(f, kept, query=""):
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    f.run(f"localStorage.setItem('stackapella.experiments', {json.dumps(json.dumps(kept))}); sessionStorage.removeItem('stackapella.experimentPreview');"
          f"sessionStorage.setItem('stackapella.mockExperiments', {json.dumps(json.dumps([EXPERIMENT]))});")
    f.go(f"/learn/1-comfortable{query}", "document.querySelector('main h1, #app h1')")


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VARIANT})
    # Arm b: the variant's one step.
    open_lesson(f, {"test1": "b"})
    f.wait(f"{STEPS} === 1", what="arm b's one step")
    # Its own line, from the variant.
    f.wait(f"document.querySelector('main h1')?.textContent.includes({json.dumps(LINE)})", what="arm b's own line")
    # (This browser lets the mic in without asking: told the question is still to come, the lesson opened again.)
    f.run("const query = navigator.permissions.query.bind(navigator.permissions); navigator.permissions.query = async (d) => (d.name === 'microphone' ? { state: 'prompt' } : query(d));"
          "const { navigate } = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    # Its one step finds your note, so it says why we listen first, as a step before it would (the
    # courses lead's gate for a one-step lesson 1): Turn on my mic and its line, then the note finder.
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
    f.wait("JSON.stringify((await import('/js/experiments.js')).enrolledVariants()) === '[\"test1-b\"]'", what="the counts carrying the arm")

    # Arm a: the lesson as written.
    open_lesson(f, {"test1": "a"})
    f.wait(f"{STEPS} > 1", what="arm a's steps, as written")

    # A new device: drawn as the lesson opens, and kept.
    open_lesson(f, {})
    f.wait("JSON.parse(localStorage.getItem('stackapella.experiments') || '{}')['test1']", what="an arm drawn on opening")
    drawn = f.js("JSON.parse(localStorage.getItem('stackapella.experiments'))['test1']")
    assert drawn in ("a", "b"), drawn

    # A preview: arm b in this tab, never kept or counted.
    open_lesson(f, {}, "?x=test1-b")
    f.wait(f"{STEPS} === 1", what="the preview's one step")
    kept = f.js("JSON.parse(localStorage.getItem('stackapella.experiments') || '{}')")
    assert "test1" not in kept or kept["test1"] in ("a", "b"), kept
    assert f.js("(await import('/js/experiments.js')).experiment('test1').preview"), "a preview"

    # An experiment the device is in whose variant the course no longer has (short1 after its content left): the lesson
    # as written. test1 is kept at a here, or it would be drawn on opening and its arm b is one step half the time.
    open_lesson(f, {"short1": "b", "test1": "a"})
    f.wait(f"{STEPS} > 1", what="the lesson as written, for an arm with no variant")


if __name__ == "__main__":
    run("lesson_variant", journey)
