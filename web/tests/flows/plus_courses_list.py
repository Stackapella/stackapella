"""The Plus courses lists (Hillel, 2026-10-28: a lean placeholder to redesign): Home's Courses row
and More courses on the Learn hub (where All courses leads), as a free member once the courses are
open to them. A live course is a card that's one link, with the way in; the courses still to come are
quieter, their names only, and do nothing. No primary button among them.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
ACCOUNT = "(await import('/js/stores/account.js')).account"
BAND = "document.querySelector('[data-home-courses]')"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def open_to_member(f):
    f.wait(f"{ACCOUNT}.me?.id", what="the account")
    f.run(f"const a = {ACCOUNT}; a.me = {{ ...a.me, plusCourses: true }};")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    # Weeks in (Home keeps the courses out of a new singer's first week).
    f.run("const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js'); await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 30 * 86400000 }, 'progress');")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-home-courses]')")
    open_to_member(f)
    f.wait(f"{BAND}?.querySelector('[data-course-card]')", what="the Courses band")
    # Room under Home's last band, so the shot shows the cards whole.
    f.run(f"document.body.style.paddingBottom = '40vh'; {BAND}.scrollIntoView({{ block: 'start' }}); window.scrollBy(0, -16)")
    f.shot("home-band")
    # Live courses are links, with the way in; none of them a button.
    assert f.js(f"[...{BAND}.querySelectorAll('[data-course-card]')].every((c) => c.querySelector('a[href^=\"/learn/c/\"]'))"), "a live course that isn't a link"
    assert not f.js(f"{BAND}.querySelector('[data-course-card] .btn-primary, [data-course-card] button')"), "a button on a course card"
    f.no_sideways("Home's Courses band")

    f.go("/learn", "document.querySelector('[data-course-map]')")
    open_to_member(f)
    # Grouped (Your voice, Harmony and a cappella, Styles), a short row a course, a link each.
    f.wait("document.querySelector('[data-more-courses] [data-course-row]')", what="More courses")
    groups = f.js("[...document.querySelectorAll('[data-course-group]')].map((g) => g.dataset.courseGroup)")
    assert groups == ["skills", "harmony", "styles"], groups
    assert f.js("[...document.querySelectorAll('[data-course-row]')].every((r) => r.querySelector('a[href^=\"/learn/c/\"]'))"), "a course row that isn't a link"
    f.run("document.querySelector('[data-more-courses]').scrollIntoView({ block: 'start' }); window.scrollBy(0, -16)")
    f.shot("learn-more-courses")
    f.no_sideways("the Learn hub's More courses")
    assert not f.js("document.querySelector('[data-more-courses] .btn-primary')"), "a primary button among the courses"
    # Courses still to come: by name, under their group, doing nothing.
    soon = f.js("(await import('/js/stores/learning.js')).learning.catalogue.filter((c) => c.status === 'soon').map((c) => c.title.en)")
    said = f.js("[...document.querySelectorAll('[data-courses-soon]')].map((p) => p.textContent).join(' ')")
    assert all(name in said for name in soon), [n for n in soon if n not in said]
    assert not f.js("document.querySelector('[data-courses-soon] a, [data-courses-soon] button')"), "a course still to come that does something"
    # A course under way: on top, as a card with how far you got.
    f.run("""const { learning } = await import('/js/stores/learning.js'); const id = document.querySelector('[data-course-row]').dataset.courseRow;
        const lesson = learning.courses[id].units[0].lessons[0].id;
        learning.progress = { ...learning.progress, courses: { ...(learning.progress.courses ?? {}), [id]: { lessons: { [lesson]: { stars: 2, bestPercent: 70, at: Date.now() } } } } };""")
    f.wait("document.querySelector('[data-courses-started] [data-course-progress]')", what="the course under way, on top")
    f.run("document.querySelector('[data-courses-started]').scrollIntoView({ block: 'center' })")
    f.shot("learn-soon")


if __name__ == "__main__":
    run("plus_courses_list", journey)
