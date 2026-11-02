"""Home's song covers (Hillel: "Can we make the bars match the actual structure of the song parts?"):
a row per real part in its colour, a dash per phrase where it sings, a late entry set in. Are You
Sleeping, a round, shows its four entries a phrase apart.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

CARD = "[...document.querySelectorAll('[data-song-card]')].find((c) => c.textContent.includes('Are You Sleeping'))"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-song-card]')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    f.wait(CARD, what="Are You Sleeping's card")
    f.run("document.querySelector('[data-song-card]').closest('section').scrollIntoView({ block: 'start' }); window.scrollBy(0, -16)")
    f.shot("song-grid")
    cover = f"{CARD}.querySelector('[data-cover]')"
    f.wait(f"{cover}.dataset.cover === 'song'", what="a cover from the song's own parts")
    rows = f.js(f"[...{cover}.querySelectorAll('[data-cover-row]')].map((r) => r.dataset.part)")
    assert rows == ["soprano", "alto", "tenor", "bass"], f"a row a part: {rows}"
    # Each part comes in a phrase after the one above: its first dash starts further in.
    starts = f.js(f"[...{cover}.querySelectorAll('[data-cover-row]')].map((r) => r.querySelector('[data-cover-dash]').getBoundingClientRect().left)")
    assert all(b > a + 4 for a, b in zip(starts, starts[1:])), f"staggered entries: {starts}"
    assert f.js(f"{cover}.getAttribute('aria-hidden')") == "true", "the cover is hidden from screen readers"
    f.no_sideways("Home's song grid")


if __name__ == "__main__":
    run("song_covers", journey)
