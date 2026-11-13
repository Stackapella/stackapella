"""A song page's hero player picks the part (public/song.blade.php, js/public/song-page.js, js/public/mixer.js; option C
of the song page review, Hillel 2026-11-13): nothing looks picked at first and Learn names the melody's part; tapping a
part's row hears it alone and leaves Learn as it was; its "I sing this" makes it yours ("My part") and both Learns, under
the rows and at the page's close, name it and open the app on it. How each part goes has nothing to pick, and each of its
rows links to its part page. Needs the real server (a site page)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

STATE = """JSON.stringify({
  labels: [...document.querySelectorAll('[data-learn] [data-learn-label]')].map((l) => l.textContent.trim()),
  hrefs: [...document.querySelectorAll('[data-learn]')].map((l) => l.getAttribute('href')),
  mine: [...document.querySelectorAll('#song-mixer .s-is-you')].map((r) => r.dataset.voice),
})"""
PLAYER = "document.querySelector('#song-mixer')"


def journey(f):
    f.go("/songs/amazing-grace", f"{PLAYER} && document.querySelector('[data-learn]')")
    # The scripts have run once the player says it's ready (mixer.js) and the page knows Learn's parts.
    f.wait(f"{PLAYER}.hasAttribute('data-ready') && document.querySelector('[data-learn-parts]')", what="the player ready and Learn's parts")
    before = json.loads(f.js(STATE))
    assert len(before["labels"]) == 2 and len(set(before["labels"])) == 1, f"two Learns, saying the same: {before}"
    assert "soprano" in before["labels"][0].lower() and before["mine"] == [], f"the melody's, nothing picked: {before}"
    assert f.js("document.querySelectorAll('.s-sg-pick, [data-picked]').length") == 0, "no card buttons, nothing marked picked"
    assert f.js("document.querySelectorAll('.s-sg-how-row a[data-part-link]').length") >= 2, "How each part goes links each part's page"

    # Tapping a row hears it alone: Learn stays.
    f.run(f"{PLAYER}.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    assert json.loads(f.js(STATE)) == before, "a row heard alone changed Learn"
    f.run(f"{PLAYER}.querySelector('[data-voice=alto] [data-hear-alone]').click()")

    # I sing this: yours, and both Learns name it.
    f.run(f"{PLAYER}.querySelector('[data-voice=alto] [data-pick]').click()")
    alto = json.loads(f.js(STATE))
    assert alto["mine"] == ["alto"], f"the alto row is yours: {alto}"
    assert all("alto" in label.lower() for label in alto["labels"]), f"Learn names the alto: {alto}"
    assert all("part=alto" in href for href in alto["hrefs"]), f"Learn opens the alto: {alto}"
    f.shot("alto")


run("song_page_pick", journey, sizes=("narrow", "phone", "desktop"))
