"""Every free tool that listens has the site's mic chooser (Hillel, 2026-11-22: "all of the tool pages with mics need to
include a mic chooser"; js/public/mic-pick.js): the tuner, the vocal range test and the audio latency test, on their
pages, with two inputs faked. In each: the chooser waits until the mic is allowed, then shows in More with every
input, the system default first; the tuner and the range test take the mic named in the sa_mic cookie (chosen in the
app) when the site has no choice of its own; choosing another is remembered (this address's storage, and its name in
sa_mic for the app) and the tool listens on it (the tuner and the range test at once, the latency test at its next
check). The pitch matching game, the just intonation tuner and the mic test have had it since they were built
(mic_pick.py, tool_mic_test.py). Needs the server's pages, at phone and desktop size:
`python3 -I tests/flows/tools_mic_pick.py http://127.0.0.1:8412`.
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import flow  # noqa: E402

# Two inputs (and Chrome's "default" copy of the first), every getUserMedia ask recorded; the real fake device answers
# each, so the tools run.
FAKE = """
(() => {
    const md = navigator.mediaDevices;
    const real = md.getUserMedia.bind(md);
    window.__asks = [];
    md.getUserMedia = (c) => { window.__asks.push(JSON.stringify(c)); return real({ audio: true }); };
    md.enumerateDevices = async () => [
        { kind: 'audioinput', deviceId: 'default', groupId: 'g1', label: 'Default - Fake Mic One' },
        { kind: 'audioinput', deviceId: 'fake-1', groupId: 'g1', label: 'Fake Mic One' },
        { kind: 'audioinput', deviceId: 'fake-2', groupId: 'g2', label: 'Fake Mic Two' },
        { kind: 'audiooutput', deviceId: 'out', groupId: 'g3', label: 'Speakers' },
    ];
    document.cookie = 'sa_mic=' + encodeURIComponent('Fake Mic Two') + '; path=/';
})();
"""
TOOL = "document.querySelector('[data-ui=tool]')"
PICKER = f"{TOOL}.querySelector('[data-picker]')"
LABEL = f"{TOOL}.querySelector('[data-picker-label]')"
OPTIONS = f"[...{PICKER}.options].map((o) => o.textContent)"
ASKED = "(id) => window.__asks.some((a) => a.includes('\"exact\":\"' + id + '\"'))"


def asked(f, device_id):
    return f.js(f"({ASKED})({json.dumps(device_id)})")


def chooser_shown(f, what):
    f.wait(f"!{LABEL}.hidden", 10, f"{what}: the chooser, once the mic is open")
    options = f.js(OPTIONS)
    assert options == ["Default - Fake Mic One", "Fake Mic One", "Fake Mic Two"], f"{what}: every input, the default first: {options}"
    named = f.js(f"{LABEL}.querySelector('[data-w=micLabel]').textContent.trim()")
    assert named in ("Mic", "Micrófono"), f"{what}: the chooser is named: {named!r}"


def pick(f, device_id):
    f.run(f"const s = {PICKER}; s.value = {json.dumps(device_id)}; s.dispatchEvent(new Event('change'));")


def remembered(f, what, device_id, name):
    assert f.js("localStorage.getItem('stackapella.site-mic')") == device_id, f"{what}: kept on this address"
    assert "sa_mic=" + name.replace(" ", "%20") in f.js("document.cookie"), f"{what}: its name kept for the app"


def fresh(f, path):
    f.go(path, "document.readyState === 'complete' && document.querySelector('[data-ui=tool-primary]')")
    f.run("localStorage.removeItem('stackapella.site-mic')")
    f.go(path, "document.readyState === 'complete' && document.querySelector('[data-ui=tool-primary]')")


def live_tool(f, path, what, start):
    """The tuner and the range test: the app's mic by name, the chooser in More, a change heard at once."""
    fresh(f, path)
    assert f.js(f"{LABEL}.hidden"), f"{what}: the chooser waits until the mic is allowed"
    f.run(start)
    f.wait(f"({ASKED})('fake-2')", 10, f"{what}: the app's mic, by name")
    f.run(f"{TOOL}.querySelector('[data-ui=tool-more]').click()")
    chooser_shown(f, what)
    f.shot(f"{what}-chooser")
    pick(f, "fake-1")
    f.wait(f"({ASKED})('fake-1')", 5, f"{what}: the open mic moving to the one picked")
    remembered(f, what, "fake-1", "Fake Mic One")
    f.no_sideways(f"{what} with its chooser")


def latency(f):
    """The latency test: the chooser beside its mic check in More, the pick used by the next check."""
    what = "latency"
    fresh(f, "/tools/audio-latency-test")
    f.run(f"{TOOL}.querySelector('[data-ui=tool-more]').click()")
    assert f.js(f"{LABEL}.hidden"), f"{what}: the chooser waits until the mic is allowed"
    f.run(f"{TOOL}.querySelector('[data-mic-start]').click()")
    chooser_shown(f, what)
    f.shot(f"{what}-chooser")
    pick(f, "fake-2")
    remembered(f, what, "fake-2", "Fake Mic Two")
    # The next check opens the mic picked.
    f.wait(f"!{TOOL}.querySelector('[data-mic-start]').hidden", 60, f"{what}: the check done")
    f.run(f"{TOOL}.querySelector('[data-mic-start]').click()")
    f.wait(f"({ASKED})('fake-2')", 10, f"{what}: the next check on the mic picked")


def journey(base, size):
    f = flow.Flow(base, size, "tools_mic_pick", skin="serious")
    try:
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": FAKE})
        live_tool(f, "/tools/tuner", "tuner", "document.querySelector('[data-ui=tool-primary]').click()")
        live_tool(f, "/tools/vocal-range-test", "range", "document.querySelector('[data-ui=tool-primary]').click()")
        latency(f)
        time.sleep(0.3)
    finally:
        f.close()
    print(f"ok   tools_mic_pick ({size})")


if __name__ == "__main__":
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081").rstrip("/")
    for size in ("phone", "desktop"):
        journey(base, size)
