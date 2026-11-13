"""The Jewish songs' hanukkiah counts the night (App\\Support\\Pages\\Hanukkiah, js/public/hanukkiah.js; Hillel,
2026-11-12: "have it so the right number of lights are lit when you view it for the day of the holiday"): on the
device's own date and time, the shamash and that night's candles from the right, the rest a wick with no flame; outside
Hanukkah every candle is lit. The page's hero and its /songs panel both. Needs the real server (a site page)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# Date and time, from the page's first script on, as if it were this local moment (the clock still runs).
FAKE_NOW = """(() => {{ const Real = Date; const start = new Real({year}, {month}, {day}, {hour}, {minute}).getTime() - Real.now();
  class Fake extends Real {{ constructor(...a) {{ super(...(a.length ? a : [Real.now() + start])); }} static now() {{ return Real.now() + start; }} }}
  window.Date = Fake; }})();"""
LAMPS = """JSON.stringify([...document.querySelectorAll('svg.s-hk')].filter((l) => l.getClientRects().length).map((l) => {
  const flames = [...l.querySelectorAll('.s-hk-flame')].filter((f) => f.getClientRects().length);
  const lit = flames.map((f) => Number(f.closest('[data-hk]')?.dataset.hk ?? 0)).sort((a, b) => a - b);
  const b = l.getBoundingClientRect();
  return { lit, night: l.dataset.night ?? null, box: [Math.round(b.left), Math.round(b.top), Math.round(b.width), Math.round(b.height)], right: Math.round(window.innerWidth - b.right) };
}))"""


def lamps_on(f, when, path):
    """The visible lamps at `when` ((year, month from 1, day, hour, minute)) on `path`."""
    y, m, d, h, mi = when
    script = f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": FAKE_NOW.format(year=y, month=m - 1, day=d, hour=h, minute=mi)})["identifier"]
    try:
        f.go(path, "document.querySelector('svg.s-hk')")
        f.wait("document.fonts.status === 'loaded'", what="fonts")
        assert f.js("new Date().getDate()") == d and f.js("new Date().getHours()") == h, "the device's time wasn't set"
        f.wait("document.readyState === 'complete'", what="the page and its scripts")
        return json.loads(f.js(LAMPS))
    finally:
        f.chrome.send("Page.removeScriptToEvaluateOnNewDocument", {"identifier": script})


def journey(f):
    for when, night, label in [((2026, 12, 1, 12, 0), None, "outside"), ((2026, 12, 4, 17, 0), 1, "night-1"), ((2026, 12, 7, 20, 0), 4, "night-4")]:
        lamps = lamps_on(f, when, "/songs/jewish-songs")
        assert len(lamps) == 1, f"one lamp, in the next festival's card: {lamps}"
        lamp = lamps[0]
        want = list(range(0, (night or 8) + 1))
        assert lamp["lit"] == want and lamp["night"] == (str(night) if night else None), f"{label}: lit {lamp['lit']}, want {want} (0 is the shamash)"
        # The whole lamp shows, inside the page.
        assert lamp["box"][1] >= 0 and lamp["right"] >= 8 and lamp["box"][3] >= 44, f"{label}: the lamp is cut or too small: {lamp}"
        f.no_sideways(label)
        f.shot(label)
    # The /songs panel's lamp counts the same night.
    panel = lamps_on(f, (2026, 12, 7, 20, 0), "/songs")
    assert len(panel) == 1 and panel[0]["lit"] == [0, 1, 2, 3, 4], f"/songs's lamp on night 4: {panel}"
    f.js("document.querySelector('svg.s-hk').scrollIntoView({ block: 'center' })")
    f.shot("songs-night-4")
    # A song's hero shows the lamp only on a Hanukkah song (the arranger's check: Lecha Dodi, a Sabbath hymn, showed it).
    f.go("/songs/maoz-tzur", "document.querySelector('h1')")
    assert f.js("document.querySelectorAll('.s-sg-hero svg.s-hk').length") == 1, "Maoz Tzur's hero has the lamp"
    f.go("/songs/lecha-dodi", "document.querySelector('h1')")
    assert f.js("document.querySelectorAll('.s-sg-hero svg.s-hk').length") == 0, "Lecha Dodi's hero has no lamp"


run("hanukkiah", journey)
