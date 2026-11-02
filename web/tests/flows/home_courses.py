"""Home's Plus courses (Hillel: "add the styles/harmony etc parts to the main app page"; trimmed
2026-10-30 to one row): at the bottom of Home, one row of three, the course under way first, and
All courses (the Learn page has every one). A live course an
account can see (staff now; everyone once plus_courses is on) opens its page; the rest say Coming
soon. Game mode calls the band Bonus stages, serious mode Courses.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import t  # noqa: E402

CARDS = "[...document.querySelectorAll('[data-course-card]')]"


def journey(f):
    # A new singer's first week: no Plus courses on Home yet (Hillel: "Yes, hide in week one").
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.wait("document.querySelector('[data-workout-step], [data-new-song], main')", what="Home")
    f.js("new Promise((r) => setTimeout(r, 800))")
    assert not f.js("!!document.querySelector('[data-home-courses]')"), "Plus courses in the first week"
    # A member who started weeks ago, before launch: every course is still to come.
    f.run("const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js'); await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 30 * 86400000 }, 'progress');")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-home-courses]')")
    title = t(f, "courses.homeTitleGame" if f.skin == "game" else "courses.homeTitle")
    assert f.js("document.querySelector('#band-courses').textContent.trim()") == title, "the band's name in this skin"
    soon = t(f, "courses.soon")
    statuses = f.js(f"{CARDS}.map((c) => c.querySelector('[data-course-status]').textContent.trim())")
    assert statuses and all(s == soon for s in statuses), f"before launch, a member sees: {statuses}"
    assert not f.js(f"{CARDS}.some((c) => c.querySelector('a'))"), "a course still to come opens"
    # Staff see the live ones, which open their course.
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('[data-home-courses]')")
    f.wait("document.querySelector('[data-course-card=vibrato] a')", what="Vibrato open for staff")
    f.js("document.querySelector('[data-home-courses]').scrollIntoView({ block: 'start' })")
    f.shot("courses")
    # One row of three, the open ones first (the featured Vibrato leading), and All courses to the Learn page.
    shown = f.js(f"{CARDS}.map((c) => c.dataset.courseCard)")
    assert len(shown) == 3 and shown[0] == "vibrato", f"one row of three, Vibrato first: {shown}"
    assert f.js("!!document.querySelector('[data-all-courses]')"), "All courses"
    # A course under way comes first.
    f.run("""const s = await import('/js/stores/learning.js'); await s.loadLearning();
        const p = s.learning.progress; s.learning.progress = { ...p, courses: { ...(p.courses ?? {}), counterpoint: { lessons: { [Object.keys((await s.loadPlusCourses(), s.learning.courses.counterpoint.units[0].lessons.reduce((o, l) => ({ ...o, [l.id]: 1 }), {})))[0]]: { stars: 2, bestPercent: 80, at: Date.now() } } } } };""")
    f.wait(f"{CARDS}[0]?.dataset.courseCard === 'counterpoint'", what="the course under way first")
    f.shot("courses-under-way")
    # Below the song library.
    assert f.js("document.querySelector('[data-home-courses]').getBoundingClientRect().top > document.querySelector('section[aria-labelledby=band-songs]').getBoundingClientRect().top"), "the courses aren't below the library"
    f.run("document.querySelector('[data-course-card=vibrato] a').click()")
    f.wait("location.pathname === '/learn/c/vibrato'", what="Vibrato's page")

    # Back the next day in the first week (onboarding step 7): the course card says what yesterday gave.
    f.run("""const day = 86400000; const y = new Date(Date.now() - day); y.setHours(12, 0, 0, 0);
        const d = new Date(Date.now() - 2 * day); const arrived = new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        localStorage.setItem('stackapella.funnel', JSON.stringify({ firsts: {}, variant: null, arrived }));
        const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js');
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 2 * day, home: 57, range: { low: 52, high: 64 }, lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: +y }, '1-match': { stars: 2, bestPercent: 80, at: +y } } }, 'progress');""")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-welcome]')")
    # (Serious mode counts lessons, never stars.)
    welcome = t(f, "home.lead.welcomeYesterday", {"note": "A3", "stars": 5}) if f.skin == "game" else t(f, "home.lead.welcomeYesterdaySerious", {"note": "A3", "lessons": 2})
    assert f.js("document.querySelector('[data-welcome]').textContent.trim()") == welcome, f.js("document.querySelector('[data-welcome]').textContent")
    f.shot("welcome-back")
    # Day 2: Start workout is the strip's primary button again.
    f.wait("document.querySelector('[data-start-workout]')", what="the workout's Start")
    assert f.js("document.querySelector('[data-start-workout]').classList.contains('btn-primary')"), "day 2: Start workout is primary"
    # The day the course started (the lead's F20): Continue lesson is the one primary action, so Start is secondary.
    f.run("""const db = await import('/js/db.js'); const kept = await db.get('learning', 'progress'); await db.put('learning', { ...kept, startedAt: Date.now() - 60000 }, 'progress');""")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-start-workout]')")
    assert f.js("document.querySelector('[data-start-workout]').classList.contains('btn-secondary')"), "day 0: Start workout is secondary"
    # Day 7 (onboarding step 11): the first week's card, its plain facts, put away with Got it.
    f.run("""const day = 86400000; const d = new Date(Date.now() - 7 * day); const arrived = new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        localStorage.setItem('stackapella.funnel', JSON.stringify({ firsts: {}, variant: null, arrived }));
        const { dayOf, emptyProgress } = await import('/js/course.js'); const db = await import('/js/db.js');
        const practice = { [dayOf(Date.now() - 6 * day)]: 400, [dayOf(Date.now() - 3 * day)]: 600, [dayOf(Date.now() - day)]: 500 };
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 7 * day, home: 57, range: { low: 43, high: 62 }, practice, lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() - 6 * day }, '1-match': { stars: 2, bestPercent: 80, at: Date.now() - 3 * day } } }, 'progress');""")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-first-week]')")
    assert "A3" in f.js("document.querySelector('[data-first-week]').textContent"), "the week's note"
    f.shot("first-week")
    f.run("document.querySelector('[data-first-week-done]').click()")
    f.wait("!document.querySelector('[data-first-week]')", what="put away")

if __name__ == "__main__":
    run("home_courses", journey)
