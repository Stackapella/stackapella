"""The studio's tracks as players (docs/game-elements-plan.md, idea 11, a reskin agreed with frontend2):
in game mode each track's name has "1P", "2P"... before it in HUD type and the armed track is ringed in
your accent (all round, never a stripe down one side); Record has a ▸ that blinks 5 times. Serious mode
and the Character select switch give today's studio.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, light  # noqa: E402
from help_shots import clear_projects, new_song  # noqa: E402

TAG = "getComputedStyle(document.querySelector('[data-tracks] section [data-track-name]'), '::before').content"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}", "document.querySelector('[data-tracks] [data-track-name]')")
    if f.skin == "serious":
        assert f.js(TAG) in ("none", "normal", ""), f"serious mode: no tag ({f.js(TAG)})"
        clear_projects(f)
        return
    assert "counter(ge-player)" in f.js(TAG), f"the tracks are counted as players: {f.js(TAG)}"
    ring = f.js("document.querySelector('[data-next-track]') ? getComputedStyle(document.querySelector('[data-next-track]')).boxShadow : 'none'")
    assert ring != "none", "the armed track is ringed"
    f.no_sideways("the studio as players")
    f.shot("studio")
    light(f)
    f.shot("studio-light")
    dark(f)
    f.run("(await import('/js/game/effects.js')).effects.select = false")
    f.wait(f"!{TAG}.includes('counter')", what="the switch off: no tags")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    clear_projects(f)


if __name__ == "__main__":
    run("game_studio", journey)
