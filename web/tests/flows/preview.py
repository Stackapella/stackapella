"""A template's preview: changing the number of singers while it plays keeps playing from the same
place, the parts the new number doesn't sing just going quiet (Hillel's request).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]')")
    f.wait("[...document.querySelectorAll('#home-songs [data-song-card]')].some((c) => c.textContent.includes('Amazing Grace'))", what="Amazing Grace in the library")
    f.run("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace')).querySelector('button').click()")
    f.wait("document.querySelector('[role=dialog]')", what="the song")
    f.js(f"{AUDIO}.unlock()")
    f.wait("document.querySelector('[data-song-play]')", what="Play")
    f.run("document.querySelector('[data-song-play]').click()")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.positionSeconds() > 1.5", timeout=30, what="the preview playing")
    assert f.js(f"{AUDIO}.engine.parts.get('preview:tenor').mix.audible"), "the tenor isn't heard with 4 singers"
    before = f.js(f"{AUDIO}.engine.positionSeconds()")
    f.run("[...document.querySelectorAll('[role=dialog] [role=radio]')].find((b) => b.textContent.trim() === '2').click()")
    f.wait(f"!{AUDIO}.engine.parts.get('preview:tenor').mix.audible", what="the tenor quiet for 2 singers")
    after = f.js(f"{AUDIO}.engine.positionSeconds()")
    assert f.js(f"!!{AUDIO}.engine.playing") and after >= before, f"the preview restarted: {before} then {after}"
    f.click_t("studio.stop")


if __name__ == "__main__":
    run("preview", journey)
