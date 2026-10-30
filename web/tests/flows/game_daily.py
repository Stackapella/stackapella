"""The daily challenge (docs/game-elements-plan.md; docs/game-server-spec.md, section 1): in game mode,
Home's card for today's song wears a "Today's challenge" tag and a yellow ring (a reskin, no new row);
the tag opens the day's board (initials and names, top 10) and Take the challenge, which starts the
song on the day's part; a take of it is sent to the day's board too (`daily`); the days you took it
are stamped on Home's week bulbs. The Daily challenge switch and serious mode show none of it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, light  # noqa: E402
from help_shots import clear_projects, t  # noqa: E402

TAG = "document.querySelector('[data-game-daily]')"
TODAY = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    const today = new Date().toISOString().slice(0, 10);
    api.useTransport(async (url, init = {}) => {
        const answer = await mockFetch(url, init);
        if (init.operation !== 'boards.daily.show') return answer;
        const json = await answer.json(); json.data.date = today;
        return new Response(JSON.stringify(json), { status: 200, headers: { 'Content-Type': 'application/json' } });
    });"""


WEEK = """const { createApp, h } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const { WeekCard } = await import('/js/components/home.js');
    const d = await import('/js/game/daily.js');
    const today = (await import('/js/course.js')).dayOf(Date.now());
    d.dailyDays.clear(); d.dailyDays.add(today);
    document.querySelector('#week-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'week-test', className: 'card m-4 p-4' });
    document.querySelector('#app').prepend(box);
    const app = createApp({ render: () => h(WeekCard, { bare: true }) });
    app.config.globalProperties.t = t;
    app.mount(box);"""


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects'); sessionStorage.removeItem('stackapella.daily')")
    clear_projects(f)
    f.go("/", "document.querySelector('[data-song-card]')")
    # Today's date on the example's challenge (the example is dated), then loaded again.
    f.run(TODAY)
    f.run("const d = await import('/js/game/daily.js'); d.daily.today = null; d.daily.asked = null; await d.loadDaily();")
    if f.skin == "serious":
        f.run("await new Promise((r) => setTimeout(r, 800))")
        assert f.js(f"!{TAG} && !document.querySelector('[data-daily]')"), "serious mode: no challenge"
        return
    f.wait(TAG, what="today's challenge on its card")
    f.run(f"{TAG}.closest('[data-song-card]').scrollIntoView({{ block: 'center' }})")
    f.no_sideways("Home with the challenge")
    f.shot("home-tag")
    f.run(f"{TAG}.click()")
    f.wait("document.querySelector('[data-game-daily-sheet]')", what="the day's board")
    words = f.js("document.querySelector('[data-game-daily-sheet]').innerText")
    assert "ZED" in words, f"an entry by initials: {words}"
    f.shot("sheet")
    light(f)
    f.shot("sheet-light")
    dark(f)
    f.run("document.querySelector('[data-daily-take]').click()")
    f.wait("location.pathname.endsWith('/sing')", timeout=20, what="into the song")
    started = f.js("JSON.parse(sessionStorage.getItem('stackapella.daily'))")
    assert started["partKey"] == "tenor" and started["templateId"] == "michael-row", started
    # A take of it goes to the day's board: the submit carries `daily`.
    sent = f.js("""(async () => { const b = await import('/js/components/boards.js'); const api = await import('/js/api.js'); let body = null;
        const { mockFetch } = await import('/js/api-mock.js');
        api.useTransport(async (url, init = {}) => { if (init.operation !== 'boards.scores.submit') return mockFetch(url, init); body = JSON.parse(init.body ?? 'null'); return new Response(JSON.stringify({ data: { counted: true, why: null, best: 80, improved: true, before: null, daily: { counted: true, best: 80, rank: 3 } } }), { status: 200, headers: { 'Content-Type': 'application/json' } }); });
        await b.submitScore({ templateId: 'michael-row', templateVersion: 2, partKey: 'tenor', score: 80, guideMuted: true }); return body; })()""")
    assert sent.get("daily") == f.js("new Date().toISOString().slice(0, 10)"), f"daily sent: {json.dumps(sent)[:200]}"
    # The days you took it: stamped on Home's week bulbs (a reskin), today and the server's days.
    f.go("/", "document.querySelector('[data-song-card]')")
    f.run(WEEK)
    f.wait("document.querySelector('#week-test [data-daily-stamp]')", what="today's bulb stamped")
    assert f.js("document.querySelectorAll('#week-test [data-daily-stamp]').length") == 1, "only the day taken"
    assert t(f, "game.daily.stamped") in f.js("document.querySelector('#week-test [data-daily-stamp]').textContent"), "a screen reader hears it"
    f.run("document.querySelector('#week-test').scrollIntoView({ block: 'center' })")
    f.shot("week-stamp")
    light(f)
    f.shot("week-stamp-light")
    dark(f)
    f.run("document.querySelector('#week-test').remove()")
    # The switch off: Home's card is itself again.
    f.run("(await import('/js/game/effects.js')).effects.daily = false")
    f.go("/", "document.querySelector('[data-song-card]')")
    assert f.js(f"!{TAG}"), "the switch turns it off"
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    clear_projects(f)


if __name__ == "__main__":
    run("game_daily", journey)
