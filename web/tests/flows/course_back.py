"""Back from a course goes where you came from (Hillel: opening the vibrato course from Home, then
Back, landed on Learn). Home, a Plus course and Back is Home, by the page's Back and the browser's;
a lesson and Back is its course, then Home; Learn's list, a course and Back is Learn; the free
course from Home and Back is Home, with no loop; a course opened cold (a shared link) goes back to
Learn. Against the mock, as staff (every course open in the mock) past the first week.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

BACK = "document.querySelector('header button[aria-label]')"
COURSE = "document.querySelector('[data-home-courses] a[href^=\"/learn/c/\"]')"


def at(f, path, what):
    f.wait(f"location.pathname === {path!r}", what=what)


def home(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.wait(COURSE, what="Home's courses")


def journey(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("""const { emptyProgress } = await import('/js/course.js'); const db = await import('/js/db.js');
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 30 * 86400000, home: 57 }, 'progress');""")

    # Home, a Plus course, the page's Back: Home.
    home(f)
    course = f.js(f"{COURSE}.getAttribute('href')")
    f.run(f"{COURSE}.click()")
    at(f, course, "the course")
    f.wait(BACK, what="the course's Back")
    f.run(f"{BACK}.click()")
    at(f, "/", "Home again, by the page's Back")

    # The same by the browser's Back.
    f.wait(COURSE, what="Home's courses")
    f.run(f"{COURSE}.click()")
    at(f, course, "the course")
    f.run("history.back()")
    at(f, "/", "Home again, by the browser's Back")

    # A lesson from the course, its Back to the course, then the course's Back to Home.
    f.wait(COURSE, what="Home's courses")
    f.run(f"{COURSE}.click()")
    at(f, course, "the course")
    f.wait("document.querySelector('[data-picked-lesson] button, [data-course-map] button.btn-primary')", what="a lesson to start")
    f.run("document.querySelector('[data-picked-lesson] button, [data-course-map] button.btn-primary').click()")
    f.wait("location.pathname.startsWith('/learn/') && !location.pathname.startsWith('/learn/c/')", what="the lesson")
    f.wait("document.querySelector('header button[aria-label]')", what="the lesson's Back")
    f.run("document.querySelector('header button[aria-label]').click()")
    at(f, course, "back to the course")
    f.wait(BACK, what="the course's Back")
    f.run(f"{BACK}.click()")
    at(f, "/", "and Home")

    # The free course from Home, and Back: Home, with no loop for the browser's Back after it.
    f.run("const { navigate } = await import('/js/router.js'); navigate('/learn');")
    at(f, "/learn", "the free course")
    f.wait(BACK, what="its Back")
    f.run(f"{BACK}.click()")
    at(f, "/", "Home from the free course")
    assert f.js("history.state?.from ?? null") is None, "Home is the entry Home was, not a new one on top"

    # Cold: a course opened from a link, no history in the app: Back goes to Learn.
    f.go(f"{course}?api=mock&scenario=&as=admin", "document.querySelector('header button[aria-label]')")
    f.run(f"{BACK}.click()")
    at(f, "/learn", "Learn, from a course opened cold")


if __name__ == "__main__":
    run("course_back", journey)
