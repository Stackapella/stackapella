"""Game mode's own modules load only in game mode (js/game/modules.js; the coordinator, 2026-11-02): serious mode's Home,
course map and Sing fetch none of them, and switching to game mode mid-session (no reload) loads them and draws the
game pieces (the stage map on the course map).
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

LAZY = ["game/chip", "game/daily", "game/ghost", "game/title-card", "game/tally", "game/badge-news", "game/stage-map", "game/part-select", "game/settings", "game/initials", "game/record", "components/boards"]
FETCHED = "performance.getEntriesByType('resource').map((r) => new URL(r.name).pathname)"


def journey(f):
    if f.skin != "serious":
        return  # it starts in serious mode itself
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.go("/learn?api=mock&scenario=&as=member", "document.querySelector('[data-course-map]')")
    time.sleep(1.5)
    fetched = set(f.js(FETCHED))
    got = [name for name in LAZY if f"/js/{name}.js" in fetched]
    assert not got, f"serious mode fetched game modules: {got}"
    assert not f.js("!!document.querySelector('[data-game-map]')"), "no stage map in serious mode"
    # Game mode on, mid-session: the modules come, and the course map draws its stage map, with no reload.
    f.run("window.__same = true")
    f.run("window.stackapellaTheme.choose({ skin: 'game' })")
    f.wait("(await import('/js/game/modules.js')).loaded.stageMap", timeout=15, what="game mode's modules")
    f.wait("!!document.querySelector('[data-game-map]')", timeout=10, what="the stage map, drawn")
    assert f.js("window.__same === true"), "the page reloaded"
    f.run("window.stackapellaTheme.choose({ skin: 'serious' })")


if __name__ == "__main__":
    run("game_lazy", journey)
