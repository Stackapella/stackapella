"""A song page's part picker (public/song.blade.php, js/public/song-page.js, js/public/mixer.js; Hillel's design,
2026-11-16): "Which part do you sing?" sits at left with one part chosen from the start, the tune's, and Learn names it,
under the picker and at the page's close. Choosing another makes it yours: its staff in the score is the tinted one and
both Learns name it and open the app on it. A staff's letter hears a part alone, and the pick holds while it does. How
each part goes has nothing to pick, and each of its rows links to its part page. Needs the real server (a site page)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

STATE = """JSON.stringify({
  labels: [...document.querySelectorAll('[data-learn] [data-learn-label]')].map((l) => l.textContent.trim()),
  hrefs: [...document.querySelectorAll('[data-learn]')].map((l) => l.getAttribute('href')),
  mine: [...document.querySelectorAll('#song-mixer .s-is-you')].map((r) => r.dataset.voice),
  chosen: [...document.querySelectorAll('#song-mixer [data-pick][aria-checked=true]')].map((r) => r.dataset.pick),
})"""
PLAYER = "document.querySelector('#song-mixer')"


def journey(f):
    f.go("/songs/amazing-grace", f"{PLAYER} && document.querySelector('[data-learn]')")
    # The scripts have run once the player says it's ready (mixer.js) and the page knows Learn's parts.
    f.wait(f"{PLAYER}.hasAttribute('data-ready') && document.querySelector('[data-learn-parts]')", what="the player ready and Learn's parts")
    before = json.loads(f.js(STATE))
    assert len(before["labels"]) == 2 and len(set(before["labels"])) == 1, f"two Learns, saying the same: {before}"
    assert "soprano" in before["labels"][0].lower() and before["mine"] == ["soprano"] and before["chosen"] == ["soprano"], f"the tune's part shows chosen, and is: {before}"
    assert f.js("document.querySelectorAll('[data-pick]').length") == 4, "one choice a part"
    assert f.js("Math.min(...[...document.querySelectorAll('[data-pick]')].map((b) => b.getBoundingClientRect().height))") >= 44, "each choice is big enough to tap"
    assert f.js("document.querySelectorAll('.s-sg-how-row a[data-part-link]').length") >= 2, "How each part goes links each part's page"

    # A staff's letter hears that part alone; the pick holds, so Learn stays.
    f.run(f"{PLAYER}.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    assert json.loads(f.js(STATE)) == before, "hearing the alto alone doesn't move the pick or Learn"
    f.run(f"{PLAYER}.querySelector('[data-voice=alto] [data-hear-alone]').click()")

    # Choose the alto: yours, and both Learns name it.
    f.run(f"{PLAYER}.querySelector('[data-pick=alto]').click()")
    alto = json.loads(f.js(STATE))
    assert alto["mine"] == ["alto"] and alto["chosen"] == ["alto"], f"the alto is yours: {alto}"
    assert all("alto" in label.lower() for label in alto["labels"]), f"Learn names the alto: {alto}"
    assert all("part=alto" in href for href in alto["hrefs"]), f"Learn opens the alto: {alto}"
    # A pick holds: hearing another part alone doesn't move Learn.
    f.run(f"{PLAYER}.querySelector('[data-voice=tenor] [data-hear-alone]').click()")
    assert json.loads(f.js(STATE))["labels"] == alto["labels"], "a pick holds while another part plays alone"
    f.no_sideways("the song page")
    f.shot("alto")


run("song_page_pick", journey, sizes=("narrow", "phone", "desktop"))
