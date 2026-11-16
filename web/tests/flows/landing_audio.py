"""The landing's player (pages/landing.blade.php, js/public/mixer.js), as a visitor meets it: signed out, and again with
the app's service worker installed (someone who has used the app, at /?landing). No console error anywhere (a module
the page can't load, like a bare 'vue', fails here); Play is there and visible; pressing it starts the song on the
audio clock (the page says it's playing and its playhead moves), and Stop stops it.

The landing sings only the recorded voices; they're stubbed here as landing_page.py stubs them (this machine's database
has none). The whole page is landing_page.py's; this is the sound, on a first visit and after the app.

It runs against the server's own site (the landing is Laravel's), at SITE (default
http://127.0.0.1:8412, `php -S 127.0.0.1:8412 tool/serve.php` from server/):

    python3 -I tests/flows/landing_audio.py
    LANDING_SW=0 python3 -I tests/flows/landing_audio.py   # the first visit only, without the app's worker

The service worker stage installs the app's worker (sw.js runs under the app's CSP, so its precache
reaches the CDN), then plays the landing again with the worker in charge.
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402
from landing_page import READY, VOICES  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
WITH_SW = os.environ.get("LANDING_SW", "1") != "0"
PAGE = "document.getElementById('lnf')"
PLAY = "document.querySelector('[data-lnf-play]')"


def plays(f, label):
    """Play is there, a press starts the song on the audio clock, and Stop stops it."""
    f.go("/?landing", f"{READY} && document.fonts.status === 'loaded'")
    f.wait(f"(() => {{ const b = {PLAY}; const r = b.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(b).visibility !== 'hidden'; }})()", timeout=20, what=f"{label}: Play visible")
    f.run(f"{PLAY}.click();")
    f.wait(f"{PAGE}.hasAttribute('data-playing')", timeout=40, what=f"{label}: the song playing")
    start = float(f.js(f"{PAGE}.style.getPropertyValue('--p') || '0'"))
    time.sleep(1.2)
    later = float(f.js(f"{PAGE}.style.getPropertyValue('--p') || '0'"))
    assert later > start, f"{label}: the playhead didn't move ({start} then {later}): no audio clock running"
    assert f.js("window.__workers") == [], f"{label}: the practice voice was made: {f.js('window.__workers')}"
    f.shot(label)
    f.run(f"{PLAY}.click();")
    f.wait(f"!{PAGE}.hasAttribute('data-playing')", timeout=10, what=f"{label}: stopped")


def main():
    failures = []
    for size in ("desktop", "phone"):
        # Signed out, a first visit.
        f = flow.Flow(SITE, size, "landing_audio", skin="serious")
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
        try:
            plays(f, "signed-out")
            if not WITH_SW:
                continue
            # Someone who has used the app: its service worker in charge of the origin, then back to the landing.
            f.expected_errors.append(f"401 (Unauthorized) {SITE}/api/account")  # the app, signed out
            f.go("/?app", "document.querySelector('#app')")
            f.wait("navigator.serviceWorker.controller", timeout=30, what="the app's service worker")
            plays(f, "after-the-app")
        except AssertionError as error:
            failures.append(f"{size}: {error}")
        finally:
            try:
                f.close()  # raises on any console error the page logged
            except AssertionError as error:
                failures.append(str(error))
    print("FAIL landing_audio\n     " + "\n     ".join(failures) if failures else "ok   landing_audio")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
