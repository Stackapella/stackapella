"""A library song's real voices play everywhere the app plays its parts, not only in Sing (real_voices.py covers
Sing). Hillel, 2026-11-02: "when i play the song on the marketing homepage I hear the real voices but in the app
when I click preview ... I hear the old sounds". The mock catalogue gives Amazing Grace's alto a recorded guide (a
3-second fixture, tests/fixtures/guide-48000.m4a) and the soprano none: Home's Preview (a library card's Play) and
the song page's Hear all parts must play the fixture for the alto and the synth (the whole song) for the soprano.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, clear_projects  # noqa: E402

FIXTURE_SECONDS = 3.0  # guide-48000.m4a: 144000 frames at 48 kHz


def seconds(f, part_id):
    return f.js(f"{AUDIO}.engine.parts.get({part_id!r})?.buffer?.duration ?? null")


def real_and_synth(f, where, prefix):
    alto, soprano = seconds(f, prefix + "alto"), seconds(f, prefix + "soprano")
    assert alto is not None and abs(alto - FIXTURE_SECONDS) < 0.05, f"{where}: the alto plays its real voice ({alto} s)"
    assert soprano is not None and soprano > FIXTURE_SECONDS + 5, f"{where}: the soprano, with no real voice, plays the synth ({soprano} s)"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]')")
    clear_projects(f)
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]')")
    card = "[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace'))"
    f.wait(card, what="Amazing Grace's card")
    f.run(f"{AUDIO}.unlock()")

    # Home: the card's Play, the song's preview.
    f.run(f"{card}.querySelector('[data-song-play]').click()")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.parts.has('preview:alto')", timeout=30, what="Home's preview playing")
    real_and_synth(f, "Home's Preview", "preview:")
    f.shot("home-preview")
    f.run(f"{card}.querySelector('[data-song-play]').click()")
    f.wait(f"!{AUDIO}.engine.playing", what="the preview stopped")

    # The song page: Hear all parts.
    f.go("/t/amazing-grace?api=mock&scenario=&as=member", "document.querySelector('main h1')")
    f.run(f"{AUDIO}.unlock()")
    hear = "[...document.querySelectorAll('main button.btn-secondary')].find((b) => b.offsetParent && b.querySelector('svg'))"
    f.wait(hear, what="Hear all parts")
    f.run(f"{hear}.click()")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.parts.has('creator:alto')", timeout=30, what="the song page playing")
    real_and_synth(f, "the song page", "creator:")
    f.shot("song-page")
    f.run(f"{AUDIO}.engine.stop()")


if __name__ == "__main__":
    run("real_voices_everywhere", journey)
