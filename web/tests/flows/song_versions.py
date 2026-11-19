"""A song's versions (site/_song-versions.blade.php, js/public/song-version.js; Hillel, 2026-11-18, "One song, US/UK
switch"): It Came Upon the Midnight Clear's page opens on the song's own version here (no country locally), with a small
"Version" row, US chosen. UK switches to the UK version (?version=uk): the switch says so, the player sings that file
(Noel), and the page's address is still the song's. The pick is kept on the device: the song's page without a query
opens on UK again, and so does a part page, whose part chips keep ?version=uk. Needs the real server (a site page) with
the content's it-came-upon-the-midnight-clear-uk synced."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SONG = "it-came-upon-the-midnight-clear"
STATE = """JSON.stringify({
  shown: document.querySelector('[data-song-versions]')?.dataset.version ?? null,
  current: [...document.querySelectorAll('[data-song-versions] [aria-current]')].map((a) => a.dataset.versionPick),
  canonical: document.querySelector('link[rel=canonical]')?.getAttribute('href') ?? '',
  query: location.search,
})"""


def state(f):
    return json.loads(f.js(STATE))


def journey(f):
    f.scheme("light")  # calm in its light theme (game mode keeps its own dark)
    f.go(f"/songs/{SONG}", "document.querySelector('[data-song-versions]')")
    f.run(f"localStorage.removeItem('stackapella.songVersion.{SONG}')")
    f.go(f"/songs/{SONG}", "document.querySelector('[data-song-versions][data-ready]')")
    us = state(f)
    assert us["shown"] == "us" and us["current"] == ["us"], f"the song's own version here, chosen: {us}"
    assert f.js(f"document.querySelector('#song-mixer [data-score]')?.dataset.slug") == SONG, "the player sings the song's own file"
    assert f.js("Math.min(...[...document.querySelectorAll('[data-version-pick]')].map((a) => a.getBoundingClientRect().height))") >= 40, "each version is big enough to tap"

    # UK: the UK version, on the song's own address.
    f.run("document.querySelector('[data-version-pick=uk]').click()")
    f.wait("location.search.includes('version=uk') && document.querySelector('[data-song-versions]')?.dataset.version === 'uk'", what="the UK version's page")
    uk = state(f)
    assert uk["current"] == ["uk"] and uk["canonical"].endswith(f"/songs/{SONG}"), f"UK chosen, one address: {uk}"
    assert f.js("document.querySelector('#song-mixer [data-score]')?.dataset.slug") == f"{SONG}-uk", "the player sings the UK file"
    assert "Noel" in f.js("document.querySelector('.s-ver-note')?.textContent ?? ''"), "the UK version names its tune"
    assert "CAROL" not in f.js("document.querySelector('.s-lead')?.textContent ?? ''"), "the lead is the UK version's, not the US tune's"
    f.wait("document.querySelector('[data-score-view]')?.hasAttribute('data-engraved')", what="the UK version's engraved score")
    f.wait("document.fonts.status === 'loaded'", what="fonts")
    f.no_sideways("the song page, UK version")
    f.shot("song-uk")

    # Kept on the device: the page without a query opens on UK again.
    f.go(f"/songs/{SONG}", "document.querySelector('[data-song-versions]')?.dataset.version === 'uk'")
    assert state(f)["query"] == "?version=uk", "the kept pick opened"

    # A part page follows the pick, and its part chips keep it.
    f.go(f"/songs/{SONG}/alto", "document.querySelector('[data-song-versions]')?.dataset.version === 'uk'")
    part = state(f)
    assert part["current"] == ["uk"] and part["canonical"].endswith(f"/songs/{SONG}/alto"), f"the alto's UK page: {part}"
    hrefs = f.js("JSON.stringify([...document.querySelectorAll('a.s-spart-chip')].map((a) => a.getAttribute('href')))")
    assert all(h.endswith("?version=uk") for h in json.loads(hrefs)), f"every part chip keeps the version: {hrefs}"
    f.wait("document.querySelector('[data-ui=part-lane] svg, [data-ui=part-lane] canvas')", what="the alto's part, drawn")
    f.no_sideways("the alto's page, UK version")
    f.shot("part-uk")
    f.run(f"localStorage.removeItem('stackapella.songVersion.{SONG}')")


run("song_versions", journey)
