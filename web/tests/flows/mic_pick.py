"""The site's mic picker (js/public/mic-pick.js) on Perfect your pitch's Bullseye, with two inputs faked:
the mic named in the sa_mic cookie (chosen in the app) is used when the site has no choice of its own;
the picker shows once the mic is allowed and there's more than one input, the system default first;
and choosing another mic mid-round remembers it (this address's storage, and its name in sa_mic) and
moves the open mic to it. Needs the server's pages, at phone and desktop size:
`python3 -I tests/flows/mic_pick.py http://127.0.0.1:8412`.
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import flow  # noqa: E402

# Two inputs (and Chrome's "default" copy of the first), and every getUserMedia ask recorded; the real
# fake device answers each, so the round runs.
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


def journey(base, size):
    f = flow.Flow(base, size, "mic_pick", skin="serious")
    try:
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": FAKE})
        f.go("/courses/perfect-your-pitch#bullseye", "document.readyState === 'complete' && document.querySelector('[data-pt-mic]')")
        assert f.js("document.querySelector('.cp-pt2-mic-pick').hidden"), "the picker waits until the mic is allowed"
        f.run("document.querySelector('[data-pt-mic]').click();")
        f.wait("!document.querySelector('.cp-pt2-mic-pick').hidden", 10, "the picker, once the mic is open")
        asks = [json.loads(a) for a in f.js("window.__asks")]
        assert any(a["audio"].get("deviceId", {}).get("exact") == "fake-2" for a in asks), f"the app's mic, by name: {asks}"
        options = f.js("[...document.querySelectorAll('[data-pt-mic-pick] option')].map(o => o.textContent)")
        assert options == ["Default - Fake Mic One", "Fake Mic One", "Fake Mic Two"], f"the inputs, the default first: {options}"
        f.shot("picker")
        f.run("const s = document.querySelector('[data-pt-mic-pick]'); s.value = 'fake-1'; s.dispatchEvent(new Event('change'));")
        f.wait("window.__asks.some(a => a.includes('\"exact\":\"fake-1\"'))", 5, "the open mic moving to the one picked")
        assert f.js("localStorage.getItem('stackapella.site-mic')") == "fake-1", "kept on this address"
        assert "sa_mic=Fake%20Mic%20One" in f.js("document.cookie"), "its name kept for the app"
        time.sleep(0.3)
        f.run("document.querySelector('[data-pt-mic]').click();")
    finally:
        f.close()
    print(f"ok   mic_pick ({size})")


if __name__ == "__main__":
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081").rstrip("/")
    for size in ("phone", "desktop"):
        journey(base, size)
