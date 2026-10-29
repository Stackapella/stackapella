"""Stage select on a map (docs/game-elements-plan.md; the proposal's board 07): in game mode the course
map draws the open unit as a winding dotted path, lit to the lesson you're on, "You are here" over it,
and the unit's challenge as a special stage with the course's two-star line first; the other units stay
a list; picking a node shows its row as the list did. The Stage select map switch and serious mode give
today's map.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, light  # noqa: E402
from home import progress, t  # noqa: E402

MAP = "document.querySelector('[data-game-map]')"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    progress(f, ["1-comfortable", "1-match", "1-high-low"], 150)
    f.go("/learn", "document.querySelector('[data-course-map]')")
    if f.skin == "serious":
        assert f.js(f"!{MAP}"), "serious mode: today's map"
        return
    f.wait(MAP, what="the stage map")
    assert f.js("document.querySelectorAll('[data-game-map]').length") == 1, "only the open unit is a map"
    assert f.js(f"{MAP}.querySelectorAll('.ge-map-node.is-done').length") == 3, "three lessons done"
    assert f.js(f"!!{MAP}.querySelector('.ge-map-node.is-now') && !!{MAP}.querySelector('.ge-map-here')"), "you are here"
    words = f.js(f"{MAP}.innerText")
    assert t(f, "game.map.special").upper() in words.upper() and t(f, "game.map.target", {"percent": 70}) in words, words
    # Each node is a real button named by its lesson and state; a locked one says so.
    assert f.js(f"[...{MAP}.querySelectorAll('.ge-map-node')].every((b) => b.tagName === 'BUTTON' && b.getAttribute('aria-label'))"), "named buttons"
    done = f.js(f"{MAP}.querySelector('.ge-map-node.is-done').getAttribute('aria-label')")
    assert "2" in done, f"a done lesson says its stars: {done}"
    f.run(f"{MAP}.scrollIntoView({{ block: 'center' }})")
    f.no_sideways("the stage map")
    f.shot("map")
    # Picking a node shows its row.
    f.run(f"{MAP}.querySelectorAll('.ge-map-node')[1].click()")
    f.wait("document.querySelector('[data-picked-lesson]')?.innerText.length > 0", what="the picked lesson's row")
    f.shot("map-picked")
    light(f)
    f.run(f"{MAP}.scrollIntoView({{ block: 'center' }})")
    f.shot("map-light")
    dark(f)
    f.run("(await import('/js/game/effects.js')).effects.stages = false")
    f.wait(f"!{MAP}", what="the list again")
    f.run("localStorage.removeItem('stackapella.gameEffects')")


if __name__ == "__main__":
    run("game_map", journey)
