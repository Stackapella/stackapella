"""Choose your part as a character select (docs/game-elements-plan.md; the proposal's board 09), as a
reskin of the part list: in game mode each part is a portrait in its colour carrying its own numbers
from the song (its range, how many notes, how often it leaps) and "Best for you" when the range check
says so; a 1P cursor in your accent follows the arrows; a tap chooses it, as the list did. The
Character select switch and serious mode give today's list.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, frames, light  # noqa: E402
from help_shots import SESSION, clear_projects, new_song, t  # noqa: E402

SELECT = "document.querySelector('[data-game-select]')"


def part_step(f):
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('[data-way=whole]')")
    f.run("document.querySelector('[data-way=whole]').click()")
    f.wait_text(t(f, "sing.chooser.yourPart"))


def journey(f):
    f.go("/voice?api=mock&as=signedOut", "document.querySelector('#app header, #app main')")
    f.run("localStorage.removeItem('stackapella.gameEffects'); localStorage.removeItem('stackapella.singPrefs')")
    part_step(f)
    if f.skin == "serious":
        assert f.js(f"!{SELECT}"), "serious mode: today's list"
        clear_projects(f)
        return
    f.wait(SELECT, what="the character select")
    assert f.js(f"{SELECT}.querySelectorAll('.ge-portrait').length") == 4, "four portraits"
    assert f.js("!document.querySelector('[data-game-stats]')"), "no stats card: each portrait has its own"
    notes = f.js(f"(async () => {{ const s = {SESSION}; return s.templatePart(s.session.parts.filter((p) => p.templatePartKey)[0]).guide.notes.filter((n) => Number.isFinite(n.pitch)).length; }})()")
    first = f.js("document.querySelector('.ge-portrait').innerText + ' ' + document.querySelector('.ge-portrait').textContent")
    assert t(f, "game.select.notes", {"count": notes}) in first, f"the first part's real note count {notes}: {first}"
    f.no_sideways("the character select")
    frames(f, "select", [0, 250, 600])
    # The arrows move the 1P cursor.
    at = "[...document.querySelectorAll('.ge-portrait')].indexOf(document.querySelector('.ge-portrait.is-picked'))"
    start = f.js(at)
    key, to = ("ArrowRight", start + 1) if start < 3 else ("ArrowLeft", start - 1)
    f.run(f"const b = document.querySelector('.ge-portrait.is-picked'); b.focus(); b.dispatchEvent(new KeyboardEvent('keydown', {{ key: '{key}', bubbles: true }}))")
    f.wait(f"{at} === {to}", what="the cursor moved")
    picked = f.js("document.querySelector('.ge-portrait.is-picked .ge-portrait-name').innerText")
    f.shot("select")
    light(f)
    f.shot("select-light")
    dark(f)
    # A tap chooses it: the song starts on that part.
    f.run("document.querySelector('.ge-portrait.is-picked').click()")
    f.wait(f"!{SELECT}", timeout=5, what="locked in")
    f.wait(f"!!{SESSION}.session.practice?.focus", timeout=10, what="a part chosen")
    # The switch off: today's list.
    f.run("(await import('/js/game/effects.js')).effects.select = false")
    part_step(f)
    assert f.js(f"!{SELECT}"), "the switch turns it off"
    print(f"    picked {picked}")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    clear_projects(f)


if __name__ == "__main__":
    run("game_select", journey)
