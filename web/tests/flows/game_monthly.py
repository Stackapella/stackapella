"""Monthly boards (docs/game-elements-plan.md): in game mode a part's board gains a third choice in its
own Friends | Everyone switch, This month (everyone's bests set this month, so a new singer can
place), asking the server for `period=month`. The Monthly boards switch and serious mode keep the
two choices.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, light  # noqa: E402
from help_shots import t  # noqa: E402

BOARD = """const { createApp, h } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const { PartBoard } = await import('/js/components/boards.js');
    const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    window.boardAsked = [];
    api.useTransport(async (url, init = {}) => { if (init.operation === 'boards.show') window.boardAsked.push(String(url)); return mockFetch(url, init); });
    document.querySelector('#board-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'board-test', className: 'card m-4 p-4' });
    document.querySelector('#app').prepend(box);
    const app = createApp({ render: () => h(PartBoard, { templateId: 'amazing-grace', partKey: 'alto', partName: 'Alto' }) });
    app.config.globalProperties.t = t;
    app.mount(box);
    box.querySelector('button').click();"""
OPTIONS = "document.querySelectorAll('#board-test [data-board-scope]').length"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("await (await import('/js/stores/account.js')).loadAccount()")
    if f.skin == "game":
        # Before: the switch off, as the board was.
        f.run("(await import('/js/game/effects.js')).effects.monthly = false")
        f.run(BOARD)
        f.wait("document.querySelector('#board-test [data-board-entry], #board-test [data-board-scope]')", what="the board")
        f.wait(f"{OPTIONS} === 2", what="Friends and Everyone")
        f.run("window.scrollTo(0, 0)")
        f.shot("before")
        f.run("(await import('/js/game/effects.js')).effects.monthly = true")
    f.run(BOARD)
    f.wait(f"{OPTIONS} >= 2", what="the board's choices")
    if f.skin == "serious":
        assert f.js(OPTIONS) == 2, "serious mode: Friends and Everyone only"
        return
    assert f.js(OPTIONS) == 3, "This month beside them"
    assert f.js("document.querySelector('#board-test [data-board-scope=month]').textContent.trim()") == t(f, "boards.month")
    f.run("document.querySelector('#board-test [data-board-scope=month]').click()")
    f.wait("window.boardAsked.some((u) => u.includes('period=month') && u.includes('scope=global'))", what="this month's board asked for")
    f.wait("document.querySelector('#board-test [data-board-scope=month]').getAttribute('aria-checked') === 'true' && document.querySelector('#board-test [data-board-entry]')", what="this month's board")
    f.no_sideways("the board's three choices")
    f.run("window.scrollTo(0, 0)")
    f.shot("this-month")
    light(f)
    f.shot("this-month-light")
    dark(f)
    f.run("localStorage.removeItem('stackapella.gameEffects'); (await import('/js/api.js')).useTransport((await import('/js/api-mock.js')).mockFetch)")


if __name__ == "__main__":
    run("game_monthly", journey)
