"""The daily challenge for every solo singer (docs/daily-challenge-plan.md): in the first week, from its second day
once that day's 5 minutes are sung, the course card's Today line offers today's song part (not before). Serious mode
says it plainly ("Today's song: <title>, the <part> part.") with Sing it, straight to the song: no board, no sheet.
Game mode keeps its line and the day's board in a sheet. Taking it twice opens the same song (your copy, on the
day's part), never a new one each day; opening it counts daily_viewed, and a scored take started from it daily_sung.
Under that take, a line says so with Share: the card names the day, the song and the part (its lines as bars), and its
link is that part's public page; a take not started from it shows none of it. Every theme at AA on Home with the line
and on the share sheet. Run it in both skins (FLOW_SKINS=game,serious).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import every_theme  # noqa: E402
from help_shots import clear_projects, t  # noqa: E402

LINE = "document.querySelector('[data-today]')"
# The course started 3 days ago; `seconds` sung today (the day counts at 300).
SEED = """const db = await import('/js/db.js'); const {{ emptyProgress, dayOf }} = await import('/js/course.js');
    await db.put('learning', {{ ...emptyProgress(), startedAt: Date.now() - 3 * 86400000, home: 57, range: {{ low: 50, high: 67 }},
        practice: {{ [dayOf(Date.now())]: {seconds} }}, lessons: {{ '1-comfortable': {{ stars: 3, bestPercent: 95, at: Date.now() - 86400000 }} }} }}, 'progress');"""
# Today's date on the example's challenge, then the pick asked again (as Home does, through the module both skins share).
TODAY = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    const today = new Date().toISOString().slice(0, 10);
    api.useTransport(async (url, init = {}) => {
        const answer = await mockFetch(url, init);
        if (init.operation !== 'boards.daily.show') return answer;
        const json = await answer.json(); json.data.date = today; delete json.data.cup;
        return new Response(JSON.stringify(json), { status: 200, headers: { 'Content-Type': 'application/json' } });
    });
    const p = await import('/js/daily-pick.js'); p.dailyPick.today = null; p.dailyPick.asked = null;
    if (document.documentElement.dataset.skin === 'game') await (await import('/js/game/daily.js')).loadDaily(); else await p.loadPick();"""
# Under a scored take: Sing's report slot with today's challenge's line and Share (the take's numbers, as scoreTake gives them).
SHARE = """const { createApp, h } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const { DailyShare } = await import('/js/components/daily-share.js');
    document.querySelector('#share-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'share-test', className: 'card m-4 p-4' });
    document.querySelector('#app').prepend(box);
    const score = { status: 'scored', percent: 86, word: 'close', timingPercent: 90, entryMs: 40,
        phrases: [{ index: 0, percent: 92 }, { index: 1, percent: 78 }, { index: 2, percent: 64 }, { index: 3, percent: 95 }] };
    const app = createApp({ render: () => h(DailyShare, { templateId: 'michael-row', partKey: %s, title: 'Michael, Row the Boat Ashore', partName: 'Tenor', score, color: '#45eca4' }) });
    app.config.globalProperties.t = t;
    app.mount(box);"""
SENT = "(async () => { (await import('/js/usage.js')).send(); return (globalThis.countsSent ?? []).flatMap((b) => b.events.map((e) => e.kind)); })()"


def home(f, seconds):
    f.run(SEED.format(seconds=seconds))
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-lead]') && !document.querySelector('[data-lead] .skeleton')")
    f.run(TODAY)
    f.js("new Promise((r) => setTimeout(r, 400))")


def take(f):
    """Takes the challenge from the Today line; returns the song's project id."""
    if f.skin == "game":
        f.click_t("home.today.dailyGo")
        f.wait("document.querySelector('[data-daily-take]') && !document.querySelector('[data-daily-take]').disabled", what="the day's board")
        f.run("document.querySelector('[data-daily-take]').click()")
    else:
        f.wait("!document.querySelector('[data-today-daily-sing]').disabled", what="Sing it")
        f.run("document.querySelector('[data-today-daily-sing]').click()")
    f.wait("location.pathname.endsWith('/sing')", timeout=20, what="into the song")
    return f.js("location.pathname.split('/')[2]")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects'); sessionStorage.removeItem('stackapella.daily'); globalThis.countsSent = []")
    clear_projects(f)

    # Day 3 of the first week, today not sung yet: the first-week path as it was, no challenge.
    home(f, 0)
    assert not f.js(f"!!{LINE}"), "the challenge before today's five minutes"

    # Today's five minutes sung: today's song on the Today line.
    home(f, 300)
    f.wait(f"{LINE}?.dataset.today === 'daily'", what="today's challenge on the Today line")
    words = f.js(f"{LINE}.innerText")
    if f.skin == "game":
        assert t(f, "game.today.daily") in words, words
    else:
        song = f.js("(await import('/js/templates.js')).loadTemplate('michael-row').then((s) => s.title)")
        assert song in words and t(f, "home.today.dailySing") in words, f"serious names the song and offers Sing it: {words}"
        assert not f.js("!!document.querySelector('[data-game-daily], [data-game-daily-sheet]')"), "serious mode: no tag, no board"
    f.no_sideways("Home with today's challenge")
    f.run(f"{LINE}.scrollIntoView({{ block: 'center' }})")
    f.shot("home-daily")
    every_theme(f, "Home with today's challenge")

    # Taken: the song on the day's part, the challenge opened and counted.
    first = take(f)
    started = f.js("JSON.parse(sessionStorage.getItem('stackapella.daily'))")
    assert started["templateId"] == "michael-row" and started["partKey"] == "tenor", started
    assert "daily_viewed" in f.js(SENT), "daily_viewed counted"
    # A scored take of it counts daily_sung (Sing calls this on a scored take, in either skin); another song part doesn't.
    f.run("globalThis.countsSent = []; const p = await import('/js/daily-pick.js'); p.countSung('michael-row', 'bass');")
    assert "daily_sung" not in f.js(SENT), "a take of another part counted as the challenge"
    f.run("const p = await import('/js/daily-pick.js'); p.countSung('michael-row', 'tenor');")
    assert "daily_sung" in f.js(SENT), "daily_sung counted"

    # Under a scored take of it: the line and Share, the card made on the device, its link the part's public page.
    f.run(SHARE % "'bass'")
    f.js("new Promise((r) => setTimeout(r, 300))")
    assert not f.js("!!document.querySelector('[data-daily-share]')"), "a take not started from the challenge says it was"
    f.run(SHARE % "'tenor'")
    f.wait("document.querySelector('[data-daily-share]')", what="today's challenge under the take")
    said = f.js("document.querySelector('[data-daily-share]').innerText")
    assert ("86%" in said) and (t(f, "game.daily.sung", {"percent": 86}) if f.skin == "game" else t(f, "home.today.dailySung", {"percent": 86})) in said, said
    f.run("document.querySelector('[data-daily-share] button').click()")
    f.wait("document.querySelector('[data-share-result] img')", timeout=20, what="the card made")
    alt = f.js("document.querySelector('[data-share-result] img').alt")
    assert "Michael, Row the Boat Ashore" in alt and "Tenor" in alt, f"the card names the song and part: {alt}"
    link = f.js("document.querySelector('[data-share-result]').dataset.shareLink")
    # On the challenge's own day, the one address that leads to the day's part (the server's /today redirect, tagged
    # today; Today's song, the retention plan's idea 6); after it, the part's own page.
    assert link.endswith("/today"), f"the link is /today on its day: {link}"
    f.no_sideways("the challenge's share sheet")
    f.shot("share-card")
    every_theme(f, "the challenge's share sheet")
    f.press("Escape")
    f.run("document.querySelector('#share-test').remove()")

    # Again: the same song (your copy, turned to the day's part), not a second one.
    count = f.js("(async () => { const s = await import('/js/stores/projects.js'); await s.loadProjects(); return s.projects.list.length; })()")
    home(f, 300)
    f.wait(f"{LINE}?.dataset.today === 'daily'", what="today's challenge again")
    again = take(f)
    assert again == first, f"a second song for the same challenge: {first} then {again}"
    assert f.js("(async () => { const s = await import('/js/stores/projects.js'); await s.loadProjects(); return s.projects.list.length; })()") == count, "the song list grew"
    clear_projects(f)
    f.run("sessionStorage.removeItem('stackapella.daily')")


if __name__ == "__main__":
    run("daily_challenge", journey)
