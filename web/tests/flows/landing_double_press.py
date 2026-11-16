"""Pressing the landing's Play fast never plays two takes (Hillel, 2026-11-01: "i clicked start game but nothing
happened for a second so i clicked again and ended up with 2 demos playing"). On the landing (pages/landing.blade.php)
a press while the voices load says so at once, and a second press then cancels it: nothing plays, then or once the
voices are in. One press plays one set of voices (each of the four, once for each time through), and pressing a lane
while it plays starts no second song. It counts the audio sources started. The recorded voices are stubbed, slowly, as
landing_page.py stubs them. Runs against the site (SITE, as landing_audio.py).
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402
from landing_page import READY, VOICES  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
# Every audio source started on the page, counted.
COUNT = """window.__started = 0; const start = AudioBufferSourceNode.prototype.start;
    AudioBufferSourceNode.prototype.start = function (...args) { if (this.buffer && this.buffer.length > 1000) window.__started += 1; return start.apply(this, args); };"""
PAGE = "document.getElementById('lnf')"
PLAY = "document.querySelector('[data-lnf-play]')"


def presses(f):
    one = f.js("JSON.parse(document.querySelector('#lnf [data-song]').textContent).parts.length") * f.js(f"Number({PAGE}.dataset.passes)")
    # Two fast presses while the voices load: the first says it's loading, the second cancels. Nothing plays.
    loading = f.js(f"(() => {{ {PLAY}.click(); const loading = {PAGE}.hasAttribute('data-loading'); {PLAY}.click(); return loading; }})()")
    assert loading, "the first press says it's loading at once"
    f.wait(f"!{PAGE}.hasAttribute('data-loading')", timeout=5, what="the second press cancels")
    time.sleep(7)
    assert not f.js(f"{PAGE}.hasAttribute('data-playing')") and f.js("window.__started") == 0, f"cancelled, nothing plays: {f.js('window.__started')} sources"
    # One press: one set of voices, however long it waits.
    f.run(f"{PLAY}.click();")
    f.wait(f"{PAGE}.hasAttribute('data-playing')", timeout=40, what="the song playing")
    time.sleep(1)
    assert f.js("window.__started") == one, f"one press, one set of voices ({one}): {f.js('window.__started')}"
    # A lane pressed while it plays hears that voice alone: no second song.
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", timeout=10, what="the alto alone")
    time.sleep(0.8)
    assert f.js("window.__started") == one, f"a voice alone starts nothing new: {f.js('window.__started')}"
    # Stop, then Play again: the one set again, never two at once.
    f.run(f"{PLAY}.click();")
    f.wait(f"!{PAGE}.hasAttribute('data-playing')", timeout=10, what="stopped")
    f.run(f"{PLAY}.click(); {PLAY}.click(); {PLAY}.click();")
    f.wait(f"{PAGE}.hasAttribute('data-playing')", timeout=40, what="playing again after three fast presses")
    time.sleep(1)
    assert f.js("window.__started") == 2 * one, f"three fast presses end as one play: {f.js('window.__started')} of {2 * one}"
    f.run(f"{PLAY}.click();")


def main():
    failures = []
    for size in ("desktop", "phone"):
        f = flow.Flow(SITE, size, "landing_double_press", skin="serious")
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": COUNT})
        try:
            f.go("/?double&voiceslow", READY)
            presses(f)
        except AssertionError as error:
            failures.append(f"{size}: {error}")
        finally:
            try:
                f.close()
            except AssertionError as error:
                failures.append(str(error))
    print("FAIL landing_double_press\n     " + "\n     ".join(failures) if failures else "ok   landing_double_press")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
