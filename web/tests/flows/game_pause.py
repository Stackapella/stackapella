"""Batch 4's pause menu as a reskin of Sing's own pause (docs/game-elements-plan.md; Hillel: "reskin, don't add"):
stopped part of the way through, Sing is [data-paused]; the header chip's word is Paused in both skins (it said Ready);
in game mode with the Score strip on, the word is in the pixel face and blinks, the stage dims and Play's ring blinks.
Nothing new to press or tab to: the same buttons as while playing. Play resumes and the pause look goes.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from bidi import ChromeOnly  # noqa: E402  (flow put tool/ on the path)
from game_frames import every_theme  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402

PAUSED = "document.querySelector('[data-paused]')"
FOCUSABLE = "[...document.querySelectorAll('button:not([disabled]), a[href], [tabindex]:not([tabindex=\"-1\"])')].filter((e) => e.offsetParent).length"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    f.mic_checked()
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.wait("document.querySelector('[data-way=whole]')", what="the ways")
    f.run("document.querySelector('[data-way=whole]').click()")
    f.wait("!!document.querySelector('[data-select-sing]') || !document.querySelector('[data-way]')", what="the part step or in")
    # Game mode's select (Sing Tenor), or serious mode's list of parts (the first).
    f.run("(document.querySelector('[data-select-sing]') ?? document.querySelector('.card-interactive'))?.click()")
    f.wait("!!document.querySelector('[data-play]')", timeout=10, what="into Sing")
    assert f.js(f"!{PAUSED}"), "not paused at the start"
    f.js(f"{AUDIO}.unlock()")
    f.run("document.querySelector('[data-play]').click()")
    f.wait(f"{SESSION}.session.playing && {SESSION}.session.position > 2", timeout=20, what="playing past two seconds")
    f.run("document.querySelector('[data-play]').click()")
    f.wait(PAUSED, timeout=5, what="paused part of the way through")
    if f.size == "desktop":
        word = f.js("document.querySelector('[data-ui=phase-word]').textContent")
        assert t(f, "sing.phase.paused") in word, f"the chip says Paused: {word}"
    stage = "getComputedStyle(document.querySelector('[data-ui=sing-stage]')).opacity"
    if f.skin == "serious":
        assert f.js(stage) == "1", "serious mode: no dimming"
        if f.size == "desktop":
            assert f.js("getComputedStyle(document.querySelector('[data-ui=phase-word]')).animationName") == "none", "serious mode: no blink"
        f.shot("paused-serious")
    else:
        f.wait(f"Number({stage}) < 0.9", timeout=3, what="the stage dimmed")
        # Play's ring: a box-shadow ring, or the outline ring round Play's coin (game mode's discs are coins).
        assert f.js("getComputedStyle(document.querySelector('[data-play]')).animationName") in ("ge-paused-ring", "ge-paused-coin"), "Play's ring blinks"
        f.run("for (const a of document.getAnimations()) { try { a.finish(); } catch {} }")
        # A reskin: the same things to press as the plain pause (the Score strip off).
        reskinned = f.js(FOCUSABLE)
        f.run("(await import('/js/game/effects.js')).effects.hud = false")
        time.sleep(0.3)
        assert f.js(FOCUSABLE) == reskinned, "the pause look adds nothing to press"
        f.run("(await import('/js/game/effects.js')).effects.hud = true")
        time.sleep(0.3)
        f.run("for (const a of document.getAnimations()) { try { a.finish(); } catch {} }")  # the blink, rested
        f.no_sideways("the pause")
        f.shot("paused")
        # Reduced motion: a steady Paused and a steady ring, no blinking. (Safari's driver can't emulate it:
        # Chrome and Firefox check it.)
        try:
            f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
            emulated = True
        except ChromeOnly:
            emulated = False
        if emulated:
            time.sleep(0.3)
            steady = f.js("JSON.stringify({ play: getComputedStyle(document.querySelector('[data-play]')).animationName, ring: getComputedStyle(document.querySelector('[data-play]')).boxShadow, word: document.querySelector('[data-ui=phase-word]') ? getComputedStyle(document.querySelector('[data-ui=phase-word]')).animationName : 'none' })")
            assert '"play":"none"' in steady and '"word":"none"' in steady and '"ring":"none"' not in steady, f"reduced motion: steady, no blink: {steady}"
            f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": ""}]})
        every_theme(f, "the pause")
        # The Score strip off: the plain pause (the word Paused still, nothing dimmed).
        f.run("(await import('/js/game/effects.js')).effects.hud = false")
        time.sleep(0.4)
        assert f.js(stage) == "1", "the Score strip off: no dimming"
        f.run("localStorage.removeItem('stackapella.gameEffects'); (await import('/js/game/effects.js')).effects.hud = true")
    # Play resumes: the pause look goes.
    f.run("document.querySelector('[data-play]').click()")
    f.wait(f"{SESSION}.session.playing && !{PAUSED}", timeout=10, what="resumed")
    f.run("document.querySelector('[data-play]').click()")
    clear_projects(f)


if __name__ == "__main__":
    run("game_pause", journey)
