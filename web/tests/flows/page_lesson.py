"""A lesson opened from its own public page (js/page-lesson.js; Hillel, 2026-11-08, "Yes, once"): a lesson page's Sing this
lesson (?ref=lesson-page, our site's referrer) opens that lesson once for a guest, its unit's lock waived; the next lesson
from a page in the same course is walled as usual; the same address without the page behind it is walled, and locked for
a member whose unit isn't open. Against the mock; the site's referrer is this server's origin (the app's host here has
no "app." to drop).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

WALL = "document.querySelector('[data-needs-account]')"
I18N = "(await import('/js/i18n.js'))"


def t(f, key):
    return f.js(f"{I18N}.t({json.dumps(key)})")


def from_page(f, lesson, who="signedOut"):
    """Arrive at a lesson the way its public page sends you: the ref, and the site as the referrer."""
    f.chrome.send("Page.navigate", {"url": f"{f.base}/learn/{lesson}?ref=lesson-page&api=mock&scenario=&as={who}", "referrer": f"{f.base}/courses/the-course/a-page"})
    f.wait(f"location.pathname === '/learn/{lesson}' && document.querySelector('#app header')", what=f"{lesson} from its page")


def shows(f, what):
    f.wait(f"(document.querySelector('[data-step-bars], [data-go-on]') && !{WALL}) || {WALL} || document.body.innerText.includes({json.dumps(t(f, 'course.locked'))})", what=what)
    if f.js(f"!!{WALL}"):
        return "walled"
    return "locked" if f.js(f"document.body.innerText.includes({json.dumps(t(f, 'course.locked'))})") else "open"


def journey(f):
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    # A guest from a unit-3 lesson's page: it opens, no wall, no lock.
    from_page(f, "3-steps-up")
    assert shows(f, "the lesson from its page") == "open", "a lesson from its own page isn't open to a guest"
    f.shot("from-page")
    # A second lesson from a page in the same course: walled as usual.
    from_page(f, "3-steps-down")
    assert shows(f, "a second lesson from a page") == "walled", "a second lesson from a page in the same course wasn't walled"
    # A fresh guest at the same address without the page behind it: pasted, or a browser that drops the referrer (a
    # privacy extension, an in-app browser like Instagram's or Reddit's). The usual wall, with its usual words.
    f.run("localStorage.clear(); sessionStorage.clear()")
    f.go("/learn/3-steps-up?ref=lesson-page&api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    assert shows(f, "the address with no page behind it") == "walled", "a pasted link opened the lesson"
    assert t(f, "courses.wall.title") in f.js(f"{WALL}.innerText"), "not the usual wall's words"
    assert f.js("document.querySelector('[data-needs-account-yes]')") is not None, "the wall without its way in"
    # A member straight to a unit-3 lesson, no page: locked, as their unit isn't open.
    f.run("localStorage.clear(); sessionStorage.clear()")
    f.go("/learn/3-jumps?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    assert shows(f, "a member's unit-3 lesson, no page") == "locked", "a member's locked lesson opened without its page"


if __name__ == "__main__":
    run("page_lesson", journey)
