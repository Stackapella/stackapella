"""Hearing one part is tapping its row (a singer, 2026-11-12: "on a phone, it was hard to figure out how to change to
listen to one part or another"; Hillel, 2026-11-13: yes to tap-to-solo). Above a song player's part rows, "Tap a part to
hear it on its own"; each row ([data-hear-alone], components/site/mixer.blade.php, js/public/mixer.js) plays its part
alone while the song plays on, and again brings every part back; your part is a small control at the row's end. At 360
and 390 px: each row and its small control are at least 44 px tall, nothing is cut, and nothing scrolls sideways. On a
collection page's song to try and on the landing. Needs the real server (site pages)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

PLAYER = "[...document.querySelectorAll('[data-player]')].find((p) => p.querySelector('[data-hear-alone]') && p.getClientRects().length)"
STATE = """JSON.stringify((() => { const p = """ + PLAYER + """;
  const rows = [...p.querySelectorAll('[data-voice]')];
  const mine = [...p.querySelectorAll('.s-ch-mine')];
  return { playing: p.hasAttribute('data-playing'),
    solo: rows.filter((v) => v.hasAttribute('data-solo')).map((v) => v.dataset.voice),
    off: rows.filter((v) => v.hasAttribute('data-off')).map((v) => v.dataset.voice),
    voices: rows.map((v) => v.dataset.voice),
    heights: [...rows, ...mine].map((el) => Math.round(el.getBoundingClientRect().height)),
    cut: mine.some((m) => m.scrollWidth > m.clientWidth + 1),
    line: p.querySelector('.s-mix-lead')?.textContent.trim() ?? '' }; })())"""


def hear(f, path, wait):
    f.go(path, wait)
    f.wait("document.fonts.status === 'loaded'", what="fonts")
    # Game mode's landing shows its cabinet in place of the mixer (Hillel, 2026-11-13: option A): nothing to hear by part.
    if path == "/" and f.skin == "game" and not f.js("!!(" + PLAYER + ")"):
        return
    f.wait("(" + PLAYER + ")?.hasAttribute('data-ready')", what="a player with its part rows, ready")
    f.js("(" + PLAYER + ").querySelector('[data-hear-alone]').scrollIntoView({ block: 'center' })")
    before = json.loads(f.js(STATE))
    assert before["line"] == "Tap a part to hear it on its own", f"{path}: the line above the rows: {before['line']!r}"
    assert min(before["heights"]) >= 44, f"{path}: a row or its small control under 44 px: {before['heights']}"
    assert not before["cut"], f"{path}: a small control's label is cut"
    f.no_sideways(path)
    f.run("(" + PLAYER + ").querySelector('[data-play]').click()")
    f.wait("(" + PLAYER + ").hasAttribute('data-playing')", timeout=40, what="playing")
    alto = "alto" if "alto" in before["voices"] else before["voices"][1]
    tap = "(" + PLAYER + ").querySelector('[data-voice=\"" + alto + "\"] [data-hear-alone]').click()"
    f.run(tap)
    after = json.loads(f.js(STATE))
    assert after["solo"] == [alto], f"{path}: {alto} isn't the row heard alone: {after['solo']}"
    assert sorted(after["off"]) == sorted(v for v in after["voices"] if v != alto), f"{path}: not the {alto} alone: off {after['off']}"
    assert after["playing"], f"{path}: tapping {alto} stopped the song"
    f.shot(path.strip('/').replace('/', '-') or "landing")
    f.run(tap)
    back = json.loads(f.js(STATE))
    assert back["solo"] == [] and back["off"] == [] and back["playing"], f"{path}: a second tap didn't bring every voice back: {back}"
    f.run("(" + PLAYER + ").querySelector('[data-play]').click()")
    f.wait("!(" + PLAYER + ").hasAttribute('data-playing')", timeout=5, what="stopped")


def journey(f):
    hear(f, "/songs/hymns", "document.querySelector('main')")
    hear(f, "/", "document.querySelector('main')")


run("hear_part", journey, sizes=("narrow", "phone"))
