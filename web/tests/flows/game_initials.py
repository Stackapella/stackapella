"""Initials on the boards (docs/game-elements-plan.md; docs/game-server-spec.md, section 2): in game mode,
Settings' Game mode section has "Initials on the boards" and your initials; choosing them opens the arcade
letter picker (▲ ▼ or typing); a combination the server refuses says so and keeps the picker open; saved,
the boards show you by them. Under 18, the switch is on and can't be turned off. A board shows someone
who chose initials by them, in HUD type. Serious mode shows none of the rows.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, light  # noqa: E402
from help_shots import t  # noqa: E402

ROW = "document.querySelector('[data-game-initials-row]')"
SWITCH = f"{ROW}.querySelector('[role=switch]')"
PICKER = "document.querySelector('[data-game-initials]')"

BOARD = """const { createApp, h } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const { PartBoard } = await import('/js/components/boards.js');
    document.querySelector('#board-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'board-test', className: 'card m-4 p-4' });
    document.querySelector('#app').prepend(box);
    const app = createApp({ render: () => h(PartBoard, { templateId: 'amazing-grace', partKey: 'alto', partName: 'Alto' }) });
    app.config.globalProperties.t = t;
    app.mount(box);"""


def pick(f, letters):
    for i, letter in enumerate(letters):
        f.run(f"const s = document.querySelectorAll('[data-game-initials] input')[{i}]; s.value = {json.dumps(letter)}; s.dispatchEvent(new Event('input'));")
    f.run("document.querySelector('[data-initials-save]').click()")


def journey(f):
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('#game-heading')")
    f.run("sessionStorage.removeItem('stackapella.mockInitials'); localStorage.removeItem('stackapella.gameEffects')")
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('#game-heading')")
    if f.skin == "serious":
        assert f.js(f"!{ROW}"), "serious mode: no initials rows"
        return
    f.run("const { account, loadAccount } = await import('/js/stores/account.js'); await loadAccount(); account.me.isAdult = true;")  # an adult (the example gives no birth date)
    f.wait(ROW, what="the initials rows")
    assert f.js(f"{SWITCH}.getAttribute('aria-checked')") == "false", "your name on the boards until you choose"
    # Turning it on with no initials opens the picker first.
    f.run(f"{SWITCH}.click()")
    f.wait(PICKER, what="the letter picker")
    f.run("document.getElementById('game')?.scrollIntoView()")
    f.shot("picker")
    light(f)
    f.shot("picker-light")
    dark(f)
    # A refused combination says so and stays open.
    pick(f, "XXX")
    f.wait("document.querySelector('[data-game-initials] [role=alert]')", what="the server's no")
    # The arrows step a letter: A, then ▼ makes B.
    pick(f, "ADA")
    f.wait(f"!{PICKER}", what="saved and closed")
    f.wait(f"{SWITCH}.getAttribute('aria-checked') === 'true'", what="initials on the boards")
    assert "ADA" in f.js("document.querySelector('[data-game-initials-yours]').innerText"), "your initials shown"
    f.shot("initials-set")
    # A board shows someone by their initials.
    f.run(BOARD)
    f.run("document.querySelector('#board-test button')?.click()")
    f.wait("document.querySelector('#board-test [data-initials]')", what="an entry by initials")
    f.shot("board-initials")
    # Under 18: initials only, the switch on and fixed.
    f.go("/settings?api=mock&scenario=&as=young", "document.querySelector('#game-heading')")
    f.wait(ROW, what="the young account's rows")
    assert f.js(f"{SWITCH}.getAttribute('aria-checked')") == "true" and f.js(f"{SWITCH}.disabled"), "under 18: always initials"
    assert t(f, "game.settings.initialsYoung") in f.js(f"{ROW}.innerText"), "says why"
    f.run("sessionStorage.removeItem('stackapella.mockInitials')")


if __name__ == "__main__":
    run("game_initials", journey)
