"""The landing's player (pages/landing.blade.php, js/public/mixer.js), on the real server's home page: Play sings the
song on the audio clock and the words light up in turn; a lane pressed mid-song is heard alone without stopping the
song, and pressed again brings all four back; Stop ends it and puts it back at its start. The theme switch flips light
and dark and is kept. The recorded voices are stubbed as landing_page.py stubs them (this machine's database has none);
the whole page is landing_page.py's. Needs the server's page:
`python3 -I tests/flows/landing_integration.py http://127.0.0.1:8000`.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from landing_page import READY, STATE, VOICES  # noqa: E402

PAGE = "document.getElementById('lnf')"


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
    f.go("/", READY)
    assert f.js("[...document.querySelectorAll('[data-lnf-lanes] [data-voice]')].map((v) => v.dataset.voice)") == ["soprano", "alto", "tenor", "bass"], "the four lanes"
    assert f.js("document.querySelectorAll('[data-voice][data-solo], [data-voice][data-off]').length") == 0, "every voice on, none alone, at first"
    assert f.js("document.documentElement.scrollWidth <= innerWidth"), "no sideways scrolling"
    f.shot("ready")

    f.run("document.querySelector('[data-lnf-play]').click()")
    f.wait(f"{PAGE}.hasAttribute('data-playing')", timeout=40, what="playing")
    f.wait("document.querySelectorAll('.lnf-line [data-done]').length >= 2", timeout=20, what="the words lit in turn")
    assert f.js("document.querySelectorAll('.lnf-line [data-now]').length") == 1, "one word lit at a time"
    before = f.js(STATE)

    # A lane mid-song: that voice alone, the song carrying on; again, and all four are back.
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", timeout=20, what="the alto alone")
    assert f.js("[...document.querySelectorAll('[data-voice][data-off]')].map((v) => v.dataset.voice)") == ["soprano", "tenor", "bass"], "the others step back"
    time.sleep(1)
    alone = f.js(STATE)
    assert alone["playing"] and alone["p"] > before["p"], f"the song carries on: {before['p']} then {alone['p']}"
    f.shot("alone")
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("!document.querySelector('[data-voice][data-solo], [data-voice][data-off]')", timeout=20, what="all four again")

    # Stop: back at its start.
    f.run("document.querySelector('[data-play]').click()")
    f.wait(f"!{PAGE}.hasAttribute('data-playing')", timeout=10, what="stopped")
    time.sleep(0.5)
    assert f.js(STATE)["time"].startswith("0:00"), "Stop puts the song back at its start"

    # Light and dark by the top line's switch, kept for the next page.
    was = f.js("document.documentElement.dataset.theme")
    f.run("document.querySelector('[data-lnf-theme]').click()")
    f.wait(f"document.documentElement.dataset.theme !== {was!r}", timeout=5, what="the other look")
    now = f.js("document.documentElement.dataset.theme")
    assert was.endswith("-light") != now.endswith("-light"), f"the switch flips light and dark: {was} then {now}"
    f.go("/pricing", "document.querySelector('main')")
    assert f.js("document.documentElement.dataset.theme") == now, "the choice is kept on the next page"
    f.go("/", READY)
    f.run("document.querySelector('[data-lnf-theme]').click()")
    f.wait(f"document.documentElement.dataset.theme === {was!r}", timeout=5, what="the first look again")


if __name__ == "__main__":
    run("landing_integration", journey)
