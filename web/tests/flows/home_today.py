"""Home's Today line (docs/home-returning-plan.md, experiment ret1, arm b; Today's song in both arms): past the first week, one
suggestion in the course card. Back after five days: a warm-up, opening today's workout. Three days
on one lesson with two of them sung: try it slower, opening the lesson at 85% marked Slower. The free
course finished: a song in your range first, then a course. On a day with nothing else, today's challenge:
game mode's line, serious mode's calm one naming the song (docs/daily-challenge-plan.md). Arm a, and a first-week
singer who hasn't sung today, see none of it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from safari_risk import away  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
HOME = "/?api=mock&scenario=&as=member"  # an account: from lesson 2 a guest meets the free-account ask, and this is about lessons past it
LINE = "document.querySelector('[data-today]')"
DAY = 86400000
SEED = """const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js');
    await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - %d * 86400000, home: 57, range: { low: 50, high: 67 },
        lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() - 9 * 86400000 }, '1-match': { stars: 2, bestPercent: 80, at: Date.now() - 9 * 86400000 } } }, 'progress');"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def sk(f, serious, game):
    return t(f, game if f.js("document.documentElement.dataset.skin") == "game" else serious)


def home(f, arm="b"):
    f.go(f"{HOME}&x=ret1-{arm}", "document.querySelector('#app header')")
    f.wait("document.querySelector('[data-lead]') && !document.querySelector('[data-lead] .skeleton')", what="the course card")
    f.js("new Promise((r) => setTimeout(r, 400))")


def local_day(offset):
    return f"(() => {{ const d = new Date(Date.now() - {offset} * 86400000); return `${{d.getFullYear()}}-${{String(d.getMonth() + 1).padStart(2, '0')}}-${{String(d.getDate()).padStart(2, '0')}}`; }})()"


def journey(f):
    f.go(HOME, "document.querySelector('#app header')")
    # A singer in their first week: the first-week card speaks, never the Today line.
    f.run(SEED % 3)
    away(f, 5)
    home(f)
    assert not f.js(f"!!{LINE}"), "the Today line in the first week"

    # Back after five days, past the first week: the warm-up.
    f.run(SEED % 12)
    away(f, 5)
    home(f)
    f.wait(f"{LINE}?.dataset.today === 'gap'", what="the gap's warm-up")
    assert sk(f, "home.today.gap", "game.today.gap") in f.js(f"{LINE}.textContent"), f.js(f"{LINE}.textContent")
    f.shot("home-today-gap")
    # Arm a: Home as today.
    home(f, "a")
    assert not f.js(f"!!{LINE}"), "arm a shows the Today line"
    home(f)
    f.click_t("home.today.gapGo")
    f.wait("location.pathname === '/workout'", what="today's workout")

    # Stuck: the same next lesson for four days, sung on two of them.
    away(f, 1)
    home(f)
    assert f.js(f"{LINE}?.dataset.today ?? 'daily'") == "daily", "a Today line with nothing to say (game mode may have its challenge)"
    lesson = f.js("JSON.parse(localStorage.getItem('stackapella.todayWatch')).id")
    f.run(f"""localStorage.setItem('stackapella.todayWatch', JSON.stringify({{ id: {json.dumps(lesson)}, day: {local_day(4)} }}));
        const db = await import('/js/db.js'); const p = await db.get('learning', 'progress');
        await db.put('learning', {{ ...p, practice: {{ [{local_day(2)}]: 120, [{local_day(1)}]: 90 }} }}, 'progress');""")
    away(f, 1)
    home(f)
    f.wait(f"{LINE}?.dataset.today === 'stuck'", what="try it slower")
    f.shot("home-today-stuck")
    f.click_t("home.today.stuckGo")
    f.wait(f"location.pathname === '/learn/{lesson}' && location.search.includes('slower=1')", what="the lesson, slower")
    f.wait("document.querySelector('[data-slower]')", what="the lesson marked Slower")
    # Said once a lesson: on a later day, no more.
    f.run(f"localStorage.setItem('stackapella.todaySaid', JSON.stringify({{ id: {json.dumps(lesson)}, day: {local_day(1)} }}));")
    away(f, 1)
    home(f)
    assert f.js(f"{LINE}?.dataset.today") != "stuck", "try it slower said twice for one lesson"

    # The free course finished: a song in your range first, then a course.
    f.run("""const db = await import('/js/db.js'); const { allLessons, loadLearning } = await import('/js/stores/learning.js'); await loadLearning();
        const p = await db.get('learning', 'progress'); const lessons = { ...p.lessons };
        for (const { lesson } of allLessons()) lessons[lesson.id] = { stars: 3, bestPercent: 95, at: Date.now() - 86400000 };
        await db.put('learning', { ...p, lessons }, 'progress');""")
    away(f, 1)
    home(f)
    f.wait(f"{LINE}?.dataset.today === 'done' && document.querySelector('[data-today-song]')", what="the song after the course")
    assert sk(f, "home.today.done", "game.today.done") in f.js(f"{LINE}.textContent")
    f.shot("home-today-done")
    f.run("document.querySelector('[data-today-song]').click()")
    f.wait("new URLSearchParams(location.search).get('song')", what="the song opened on Home")

    # A day with nothing else: today's challenge (game mode's line; serious mode's names the song).
    f.run("""const db = await import('/js/db.js'); const p = await db.get('learning', 'progress');
        await db.put('learning', { ...p, lessons: { '1-comfortable': p.lessons['1-comfortable'] } }, 'progress');""")
    away(f, 1)
    home(f)
    f.run("const p = await import('/js/daily-pick.js'); p.dailyPick.today = { date: new Date().toISOString().slice(0, 10), templateId: 'amazing-grace', partKey: 'soprano' };")
    f.js("new Promise((r) => setTimeout(r, 200))")
    f.wait(f"{LINE}?.dataset.today === 'daily'", what="today's challenge")
    if f.js("document.documentElement.dataset.skin") != "game":
        assert "Amazing Grace" in f.js(f"{LINE}.innerText"), "serious mode names the song"
    f.shot("home-today-daily")
    # Today's song is seen by everyone (docs/retention-plan.md, idea 6, 2026-11-11): ret1's arm a shows it too, while the
    # other cases stay arm b's alone (the gap, above).
    home(f, "a")
    f.run("const p = await import('/js/daily-pick.js'); p.dailyPick.today = { date: new Date().toISOString().slice(0, 10), templateId: 'amazing-grace', partKey: 'soprano' };")
    f.wait(f"{LINE}?.dataset.today === 'daily'", what="today's song in arm a too")


if __name__ == "__main__":
    run("home_today", journey)
