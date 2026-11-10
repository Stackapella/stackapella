"""A free tool's way into the app counts as that tool's arrival: each shape of the tool pages' Try
link (a lesson, another course's lesson, a song on Sing, the app's front: ToolPagesController::tryUrl
and the brand row's Open the app) opens with ?ref=tool-<tool>, and the app keeps the tag and sends it
with the device's first open (f_arrive), the source Growth groups as "tool" (GrowthExport). The
first prod growth pull showed almost no tagged tool arrivals: this checks the app's half end to end.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# What the batch would carry: the kinds and the source, read from the mock's record of each post.
SENT = "(async () => { (await import('/js/usage.js')).send(); return (globalThis.countsSent ?? []).map((b) => ({ kinds: b.events.map((e) => e.kind), source: b.source ?? null })); })()"
TRIES = [
    ("/learn/1-comfortable", "tool-vocal-key-finder", "#app main"),
    ("/learn/pp-1-bullseye", "tool-tuner", "#app main"),
    ("/?song=amazing-grace", "tool-vocal-range-test", "#app header"),
    ("/", "tool-metronome", "#app header"),
]


def fresh(f):
    """Nothing kept on this device: cleared on a plain file of the app's origin, where no app runs to write it back."""
    f.go("/icons/favicon.svg")
    f.run("localStorage.clear(); sessionStorage.clear();")


def journey(f):
    for path, ref, ready in TRIES:
        # A first visit each time: nothing kept on this device.
        fresh(f)
        join = "&" if "?" in path else "?"
        f.go(f"{path}{join}ref={ref}&api=mock&scenario=", f"document.querySelector({ready!r})")
        f.wait(f"localStorage.getItem('stackapella.ref') === {ref!r}", what=f"{path}: the tag kept")
        f.wait("JSON.parse(localStorage.getItem('stackapella.funnel') || '{}').firsts?.f_arrive", what=f"{path}: the arrival counted")
        f.run("globalThis.countsSent = []; (await import('/js/usage.js')).count('app_open');")
        f.wait(f"{SENT}.then((sent) => sent.some((b) => b.source === {ref!r}))", what=f"{path}: the batch carries the source")
    # The arrival itself goes with the tag: the first batch from a fresh device.
    fresh(f)
    f.go("/learn/1-comfortable?ref=tool-drone&api=mock&scenario=", "document.querySelector('#app main')")
    f.wait(f"{SENT}.then((sent) => sent.some((b) => b.kinds.includes('f_arrive') && b.source === 'tool-drone'))", what="f_arrive sent with ref=tool-drone")
    # An embedded tool's credit (embed.js): ?ref=embed-<the framing site's host>, dots and all, kept and sent with the
    # arrival, so Sources counts each site that embeds a tool.
    fresh(f)
    f.go("/?ref=embed-choir.example.org&api=mock&scenario=", "document.querySelector('#app main')")
    f.wait("localStorage.getItem('stackapella.ref') === 'embed-choir.example.org'", what="the embed's tag kept")
    f.wait(f"{SENT}.then((sent) => sent.some((b) => b.kinds.includes('f_arrive') && b.source === 'embed-choir.example.org'))", what="f_arrive sent with the embed's tag")
    # A tool's lesson finished with a star (any course): f_course_lesson, once on this device, with the tool's tag, so the
    # growth pull can tell a tool that sends singers to lessons (lesson_done keeps no source). Never f_lesson1 (the free course's).
    fresh(f)
    f.go("/learn/pp-1-bullseye?ref=tool-tuner&api=mock&as=signedOut", "document.querySelector('[data-step-bars]')")
    f.run("globalThis.countsSent = []")
    f.run("await (await import('/js/stores/learning.js')).finishLesson('pp-1-bullseye', { percent: 92, stars: 3 })")
    f.wait(f"{SENT}.then((sent) => sent.some((b) => b.kinds.includes('f_course_lesson') && b.source === 'tool-tuner'))", what="f_course_lesson sent with ref=tool-tuner")
    assert not f.js(f"{SENT}.then((sent) => sent.some((b) => b.kinds.includes('f_lesson1')))"), "a course lesson is not the free course's lesson 1"
    f.run("globalThis.countsSent = []")
    f.run("await (await import('/js/stores/learning.js')).finishLesson('pp-1-bullseye', { percent: 95, stars: 3 })")
    assert not f.js(f"{SENT}.then((sent) => sent.some((b) => b.kinds.includes('f_course_lesson')))"), "once on a device"


if __name__ == "__main__":
    run("tool_arrival", journey)
