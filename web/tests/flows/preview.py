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
    # How many singers waits for a first song (the first-session review, first-song.js sungYet): one lesson sung here.
    f.wait("(await import('/js/stores/learning.js')).learning.loaded", what="the course progress")
    f.run("(await import('/js/stores/learning.js')).learning.progress.lessons['1-comfortable'] = { stars: 1, at: Date.now() }")
    f.wait("(await import('/js/first-song.js')).sungYet()", what="a first song sung")
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
    f.press("Escape")
    # A card's Play on a song not opened yet (Sentry JAVASCRIPT-C): the library's summary has no notes, so the song loads
    # first and plays; nothing throws.
    f.run("(await import('/js/templates.js')).forgetTemplates?.()")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card] [data-song-play]')")
    f.js(f"{AUDIO}.unlock()")
    f.run("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace')).querySelector('[data-song-play]').click()")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.positionSeconds() > 0.5", timeout=30, what="the card's Play playing")


if __name__ == "__main__":
    run("preview", journey)
