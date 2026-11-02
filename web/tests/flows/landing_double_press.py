"""Pressing the landing's players fast never plays two takes (Hillel, 2026-11-01: "i clicked start game but nothing
happened for a second so i clicked again and ended up with 2 demos playing"). In the game skin, PRESS START turns
busy at once (aria-busy, not pressable, "Opening the song..." with a pixel bar) and every press while it loads, by
mouse, keyboard or the play buttons, joins the same start: one set of voices. In the serious skin, a second press
stops the player, so presses never stack either. It counts the audio sources started. Runs against the site (SITE,
as landing_audio.py).
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
COUNT = """window.__starts = 0; const start = AudioBufferSourceNode.prototype.start;
    AudioBufferSourceNode.prototype.start = function (...args) { window.__starts++; return start.apply(this, args); };"""


def game(f):
    f.go("/?double", "document.querySelector('main h1')")
    f.run("window.stackapellaTheme?.choose({ skin: 'game' }); document.documentElement.dataset.skin = 'game';")
    f.wait("document.querySelector('[data-cab-press]') && !document.querySelector('[data-cab-press]').hidden", timeout=20, what="PRESS START")
    f.run(COUNT)
    voices = f.js("(async () => { const t = await (await fetch('/templates/amazing-grace.json')).json(); return t.parts.filter((p) => p.kind !== 'instrument').length; })()")
    # Two presses at once, then Enter on it and the play button, all before the take can have loaded.
    f.run("""const b = document.querySelector('[data-cab-press]'); b.click(); b.click();
        b.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true })); b.click();
        document.querySelector('[data-cab-play]').click(); document.querySelector('[data-cab-start]')?.click();""")
    busy = f.js("(() => { const b = document.querySelector('[data-cab-press]'); return b.hidden || (b.getAttribute('aria-busy') === 'true' && b.getAttribute('aria-disabled') === 'true'); })()")
    assert busy, "PRESS START shows it's working and can't be pressed again"
    f.wait("document.querySelector('[data-cabinet]').hasAttribute('data-playing')", timeout=30, what="the take playing")
    time.sleep(1.5)
    starts = f.js("window.__starts")
    assert starts == voices, f"one take: {voices} voices started, not {starts}"
    f.run("document.querySelector('[data-cab-play]').click()")
    f.wait("!document.querySelector('[data-cabinet]').hasAttribute('data-playing')", timeout=10, what="paused")


def serious(f):
    f.go("/?double-serious", "document.querySelector('#hero [data-play], [data-player] [data-play]')")
    f.run("window.stackapellaTheme?.choose({ skin: 'serious' }); document.documentElement.dataset.skin = 'serious';")
    f.run(COUNT)
    f.run("const b = document.querySelector('#hero [data-play], [data-player] [data-play]'); b.click(); b.click(); b.click();")
    time.sleep(4)
    starts = f.js("window.__starts")
    voices = f.js("document.querySelectorAll('#hero [data-voice], [data-player] [data-voice]').length") or 4
    assert starts <= voices, f"presses never stack: {starts} sources for {voices} voices"


def main():
    failures = []
    for size in ("desktop", "phone"):
        f = flow.Flow(SITE, size, "landing_double_press")
        try:
            game(f)
            serious(f)
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
