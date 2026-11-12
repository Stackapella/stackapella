"""The lesson-2 account wall (Hillel, 2026-11-06: a free account "after lesson 1 of any course") is
counted as the course's account ask, like the others: ask_acct_course_shown when a guest meets it,
_not for its Back to the course, and _yes for Make a free account, which opens sign-up for the course
and comes back to the lesson. It's a wall, not a nudge: meeting it never touches the asks' ledger.
"""
import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

WALL = "document.querySelector('[data-needs-account]')"
FIRSTS = "JSON.parse(localStorage.getItem('stackapella.funnel') || '{}').firsts || {}"
I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


SENT = "(async () => { (await import('/js/usage.js')).send(); return (globalThis.countsSent ?? []).flatMap((b) => b.events.map((e) => e.kind)); })()"


def journey(f):
    # A guest (a fresh browser, as course.py starts) who has opened the app, then straight to lesson 2.
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    f.go("/learn/1-match", WALL)
    f.wait(f"({FIRSTS}).ask_acct_course_shown && ({FIRSTS}).ask_acct_course_lesson_shown", what="the wall counted shown, and where: a lesson")
    # What it says (one component, CourseWall): why, what comes with the account, free with no card, and a way in
    # for someone who has one. No title card's fanfare before it, and no step count over it.
    text = f.js(f"{WALL}.innerText")
    for key in ("courses.wall.title", "courses.wall.keepAll", "courses.wall.free", "courses.wall.signIn"):
        assert t(f, key) in text, f"the wall says {key}: {text}"
    assert not f.js("document.querySelector('[data-step-bars]')"), "a step count over the wall"
    assert f.js(f"{WALL}.dataset.wallArm") in (None, ""), "arm a (no experiment running): the wall as it is"
    assert "acct_course" not in (f.js("localStorage.getItem('stackapella.asks')") or ""), "the wall wrote to the asks' ledger"
    # What waits, named, and one quiet way back for a guest not making an account (docs/retention-plan.md, idea 2): a
    # text link under the wall's own links, never a second button: the share sheet, or tomorrow in the calendar.
    expected = f.js("(async () => (await import('/js/next-up.js')).nextUpLine('1-match'))()")
    assert expected and f.js("document.querySelector('[data-wall-next]')?.textContent.trim()") == expected, expected
    back = f.js("[...document.querySelectorAll('[data-wall-back] button')].map((b) => b.className)")
    assert len(back) == 1 and "btn-" not in back[0], f"one text link, not a button: {back}"
    if f.js("!!document.querySelector('[data-wall-calendar]')"):
        f.run("globalThis.countsSent = []")
        f.run("document.querySelector('[data-wall-calendar]').click()")
        f.wait("document.querySelectorAll('[data-wall-calendar-at]').length === 2", what="morning or evening")
        f.run("document.querySelector('[data-wall-calendar-at=morning]').click()")
        f.wait(f"{SENT}.then((kinds) => kinds.includes('back_cal_tap'))", what="the calendar entry counted")
    f.shot("wall")
    # Back to the course: its Not now, a plain count each time.
    f.run("globalThis.countsSent = []")
    f.run("document.querySelector('[data-needs-account-not]').click()")
    f.wait("location.pathname !== '/learn/1-match'", what="back to the course")
    f.wait(f"{SENT}.then((kinds) => kinds.includes('ask_acct_course_not'))", what="Back to the course counted as Not now")
    # Again, and Make a free account: counted yes, sign-up for the course, back to this lesson after.
    f.go("/learn/1-match", WALL)
    f.run("document.querySelector('[data-needs-account-yes]').click()")
    f.wait("location.pathname === '/signin'", what="sign-up")
    query = f.js("Object.fromEntries(new URLSearchParams(location.search))")
    assert query == {"mode": "signup", "for": "course", "next": "/learn/1-match"}, json.dumps(query)
    assert f.js(f"({FIRSTS}).ask_acct_course_yes") is True, "Make a free account not counted yes"
    assert "acct_course" not in (f.js("localStorage.getItem('stackapella.asks')") or ""), "the wall wrote to the asks' ledger"
    # Already have an account: the wall's sign-in link opens sign-in, back to this lesson after (Sentry, 2026-11-10: its
    # template called navigate without having it, so the click did nothing and threw "navigate is not a function").
    f.go("/learn/1-match", WALL)
    f.run("document.querySelector('[data-wall-sign-in]').click()")
    f.wait("location.pathname === '/signin'", what="sign-in from the wall")
    query = f.js("Object.fromEntries(new URLSearchParams(location.search))")
    assert query == {"next": "/learn/1-match"}, json.dumps(query)
    # The map's face of the same wall: lesson 1 starred, the map picks lesson 2, and its card asks for the
    # account, counted shown (this device's first shown reset to see it), and its button counted yes.
    f.run("""const { emptyProgress } = await import('/js/course.js'); const db = await import('/js/db.js');
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now(), home: 57, lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() } } }, 'progress');
        const kept = JSON.parse(localStorage.getItem('stackapella.funnel')); delete kept.firsts.ask_acct_course_shown; delete kept.firsts.ask_acct_course_yes;
        localStorage.setItem('stackapella.funnel', JSON.stringify(kept));""")
    f.go("/learn", "document.querySelector('[data-picked-lesson] [data-needs-account]')")
    f.wait(f"({FIRSTS}).ask_acct_course_shown && ({FIRSTS}).ask_acct_course_map_shown", what="the map's wall counted shown, as the map's")
    # What it keeps: lesson 1's stars in game mode; in serious, its lessons, never stars (the retention walk, 2026-11-11:
    # serious said "Your 3 stars from this course so far").
    serious = f.skin == "serious"
    keep = t(f, "courses.wall.keepLessons", {"count": 1}) if serious else t(f, "courses.wall.keepStars", {"count": 3})
    said = f.js("document.querySelector('[data-picked-lesson] [data-needs-account]').innerText")
    assert keep in said, f"the map's wall keeps lesson 1: {keep} in {said}"
    assert not (serious and re.search(r"\bstars?\b", said, re.I)), f"the serious wall says stars: {said}"
    f.shot("map-wall")
    f.run("document.querySelector('[data-picked-lesson] [data-needs-account-yes]').click()")
    f.wait("location.pathname === '/signin'", what="sign-up from the map")
    assert f.js(f"({FIRSTS}).ask_acct_course_yes") is True, "the map's Make a free account not counted yes"
    assert f.js(f"({FIRSTS}).ask_acct_course_map_yes") is True, "the map's yes not counted as the map's"
    # The home's lead card, for this guest coming back: the same ask in place of Continue (or PRESS START), with the
    # lesson it opens still named above it (the first-week walk, 2026-11-08: Continue led straight into the wall).
    f.go("/", "document.querySelector('#app header')")
    f.wait("document.querySelector('[data-unit-progress]') && document.querySelector('[data-needs-account]')", what="the home's ask")
    f.wait(f"({FIRSTS}).ask_acct_course_home_shown", what="the home's ask counted as the home's")
    assert not f.js("document.querySelector('[data-press-start]')"), "PRESS START still leads a guest into the wall"
    assert t(f, "home.lead.continueLesson") not in f.js("document.querySelector('#lead-title').closest('section').innerText"), "Continue lesson still shown to a walled guest"
    assert f.js("document.querySelector('#lead-title')?.textContent") == "Match a note", "the lesson the account opens, named"
    lead = f.js("document.querySelector('#lead-title').closest('section').innerText")
    assert not (serious and re.search(r"\bstars?\b", lead, re.I)), f"the serious home's ask says stars: {lead}"
    f.shot("home-wall")
    # wall1's arm b (previewed): Google and Apple on the wall itself, the email way a link to sign-up.
    f.go("/?x=wall1-b", WALL)
    f.wait(f"{WALL}.dataset.wallArm === 'b' && {WALL}.querySelector('[data-wall-provider]')", what="arm b on the home's ask too")
    f.shot("home-wall-b")
    f.go("/learn/1-match?x=wall1-b", WALL)
    f.wait(f"{WALL}.dataset.wallArm === 'b' && {WALL}.querySelector('[data-wall-provider]')", what="arm b's buttons on the wall")
    f.shot("wall-b")
    shown = f.js(f"[...{WALL}.querySelectorAll('[data-wall-provider]')].map((b) => b.dataset.wallProvider).sort().join()")
    assert shown == "apple,google", f"Google and Apple, as the server offers them: {shown}"
    f.run("HTMLFormElement.prototype.submit = function () { window.__left = { action: this.getAttribute('action'), intended: this.querySelector('[name=intended]')?.value }; };")
    f.run(f"{WALL}.querySelector('[data-wall-provider=google]').click()")
    f.wait("window.__left", what="Google's sign-in started")
    left = f.js("window.__left")
    assert left == {"action": "/auth/google", "intended": "/learn/1-match"}, f"to Google and back to lesson 2: {left}"
    member_never_walled(f)


# Whether the wall ever drew, from the first moment a page loads (a flash of one frame counts).
WATCH_WALL = """window.__wallSeen = false; new MutationObserver(() => { if (document.querySelector('[data-needs-account]')) window.__wallSeen = true; }).observe(document, { childList: true, subtree: true });"""


def member_never_walled(f):
    """A member who opens a later lesson directly (a lesson page's Sing this lesson) on a device that kept no account:
    the lesson waits for the account, and the wall never flashes before it comes (the coordinator, 2026-11-08)."""
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": WATCH_WALL})
    # The free course's lesson 2 as a member; a Plus course's second lesson as the mock's admin (the mock shows Plus
    # courses to staff), signed in all the same.
    for who, lesson in (("member", "1-match"), ("admin", "vib-1-easy")):
        f.go(f"/?api=mock&scenario=&as={who}", "document.querySelector('#app header')")
        f.go(f"/learn/{lesson}", "document.querySelector('main h1')")
        f.wait("document.querySelector('[data-step-bars], [data-go-on]')", what=f"{lesson} open for the member")
        assert not f.js("window.__wallSeen"), f"the account wall flashed for a member opening {lesson} directly"
        assert not f.js("document.querySelector('[data-account-checking]')"), f"{lesson} still says it's checking the account"


if __name__ == "__main__":
    run("course_wall", journey)
