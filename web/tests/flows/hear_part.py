"""Hearing one part is one labelled tap (a singer, 2026-11-12: "on a phone, it was hard to figure out how to change to
listen to one part or another"). Under a song's player, "Tap a part to hear it on its own" and a row of chips, "Hear: All
parts | Soprano | Alto | Tenor | Bass" (components/site/mixer.blade.php, js/public/mixer.js [data-set]). At 360 and 390
px: every chip is at least 44 px tall and the row never scrolls sideways; tapping Alto while it plays leaves the alto
alone sounding and the song still playing, and All parts brings every voice back. On a collection page's song to try and
on the landing. Needs the real server (site pages)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

PLAYER = "[...document.querySelectorAll('[data-player]')].find((p) => p.querySelector('[data-hear-parts]') && p.getClientRects().length)"
STATE = """JSON.stringify((() => { const p = """ + PLAYER + """;
  const chips = [...p.querySelectorAll('[data-hear-parts] [data-set]')];
  return { playing: p.hasAttribute('data-playing'),
    pressed: chips.filter((c) => c.getAttribute('aria-pressed') === 'true').map((c) => c.dataset.set),
    off: [...p.querySelectorAll('[data-voice]')].filter((v) => v.hasAttribute('data-off')).map((v) => v.dataset.voice),
    voices: [...p.querySelectorAll('[data-voice]')].map((v) => v.dataset.voice),
    heights: chips.map((c) => Math.round(c.getBoundingClientRect().height)),
    wrapped: chips.some((c) => c.scrollWidth > c.clientWidth + 1),
    line: p.querySelector('.s-hear-k')?.textContent.trim() ?? '' }; })())"""


def hear(f, path, wait):
    f.go(path, wait)
    f.wait("document.fonts.status === 'loaded'", what="fonts")
    # Game mode's landing shows its own lane in place of the mixer: no mixer there, so nothing to hear by part.
    if path == "/" and f.skin == "game" and not f.js("!!(" + PLAYER + ")"):
        return
    f.wait("(" + PLAYER + ")?.hasAttribute('data-ready')", what="a player with its Hear row, ready")
    f.js("(" + PLAYER + ").querySelector('[data-hear-parts]').scrollIntoView({ block: 'center' })")
    before = json.loads(f.js(STATE))
    assert before["line"] == "Tap a part to hear it on its own", f"{path}: the line above the chips: {before['line']!r}"
    assert min(before["heights"]) >= 44, f"{path}: a chip under 44 px: {before['heights']}"
    assert not before["wrapped"], f"{path}: a chip's label is cut or wraps"
    f.no_sideways(path)
    f.run("(" + PLAYER + ").querySelector('[data-play]').click()")
    f.wait("(" + PLAYER + ").hasAttribute('data-playing')", timeout=40, what="playing")
    alto = "alto" if "alto" in before["voices"] else before["voices"][1]
    f.run("(" + PLAYER + ").querySelector('[data-hear-parts] [data-set=\"" + alto + "\"]').click()")
    after = json.loads(f.js(STATE))
    assert after["pressed"] == [alto], f"{path}: {alto} isn't the one pressed: {after['pressed']}"
    assert sorted(after["off"]) == sorted(v for v in after["voices"] if v != alto), f"{path}: not the {alto} alone: off {after['off']}"
    assert after["playing"], f"{path}: tapping {alto} stopped the song"
    f.shot(path.strip('/').replace('/', '-') or "landing")
    f.run("(" + PLAYER + ").querySelector('[data-hear-parts] [data-set=\"all\"]').click()")
    back = json.loads(f.js(STATE))
    assert back["pressed"] == ["all"] and back["off"] == [] and back["playing"], f"{path}: All parts didn't bring every voice back: {back}"
    f.run("(" + PLAYER + ").querySelector('[data-play]').click()")
    f.wait("!(" + PLAYER + ").hasAttribute('data-playing')", timeout=5, what="stopped")


def journey(f):
    hear(f, "/songs/hymns", "document.querySelector('main')")
    hear(f, "/", "document.querySelector('main')")


run("hear_part", journey, sizes=("narrow", "phone"))
