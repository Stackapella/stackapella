"""Accessibility of the week's new surfaces (the coordinator's pass, 2026-11-08), with first_week_a11y's checks in all
eight colour themes of the skin it runs in, at a phone and a desktop size: a name for every control, AA contrast,
targets of 24 px or room round them, visible focus, Tab kept inside an open sheet, Escape closing it, reflow at 320 px,
nothing moving under reduced motion, no page errors. And here: focus comes back to the control that opened a dialog when
it closes (WCAG 2.4.3), and a result is announced (a status region, WCAG 4.1.3). The surfaces: the lesson-2 account wall
(CourseWall, Google and Apple on it), Your part (course-parts.js), the firm headphones ask, a rhythm step's result ("on pitch
and time"), a looping step, the share card's sheet and its alt text, a director's and a member's group page, the
group's link for a singer, and the learning tracks request.

    FLOW_SKINS=game,serious PORT=8362 tool/flows.sh week_a11y
    A11Y_THEMES=stage-dark,warm-light WEEK_SCREENS=wall-a,tracks ...   # some of it
"""
import json
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from first_week_a11y import check  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402
from course_choir import COURSE, serve_song  # noqa: E402
from course_parts import with_parts  # noqa: E402
import headphones_needed  # noqa: E402
import live_loop  # noqa: E402
import rhythm_step  # noqa: E402
from result_share import MOUNT  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"
I18N = "(await import('/js/i18n.js'))"
WALL = "document.querySelector('[data-needs-account]')"
LESSON = "chp-1-hear"
SCREENS = os.environ.get("WEEK_SCREENS", "").split(",") if os.environ.get("WEEK_SCREENS") else None

# The control that has focus, as a short description, for "focus came back to it".
FOCUSED = "(() => { const e = document.activeElement; return e && e !== document.body ? (e.dataset.focusTag || e.textContent.trim().slice(0, 40) || e.getAttribute('aria-label') || e.tagName) : null; })()"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def focus_returns(f, screen, opener, failures):
    """Opens the dialog from `opener` (a JS expression for the control), closes it with Escape, and checks focus is back
    on that control (WCAG 2.4.3: a dialog gives focus back where it was)."""
    f.run(f"{opener}.dataset.focusTag = 'opener'; {opener}.focus(); {opener}.click();")
    f.wait("!!document.querySelector('[role=dialog][aria-modal=true]')", what=f"{screen}: the dialog")
    time.sleep(0.3)
    if not f.js("[...document.querySelectorAll('[role=dialog][aria-modal=true]')].at(-1)?.contains(document.activeElement)"):
        failures.append(f"{screen}: focus doesn't move into the dialog as it opens")
    f.press("Escape")
    time.sleep(0.4)
    back = f.js(FOCUSED)
    if back != "opener":
        failures.append(f"{screen}: focus after the dialog closes is on {back!r}, not the control that opened it")


def announced(f, screen, selector, failures):
    """A result that appears after singing is said by a screen reader: it sits in a status region (role=status, or
    aria-live polite or assertive), or takes focus as it appears (a heading with tabindex=-1, read when focused), WCAG 4.1.3."""
    ok = f.js(f"""(() => {{ const el = document.querySelector({json.dumps(selector)}); if (!el) return null;
        return document.activeElement === el || !!el.closest('[role=status], [role=alert], [aria-live=polite], [aria-live=assertive]'); }})()""")
    if ok is None:
        failures.append(f"{screen}: no {selector} to check")
    elif not ok:
        failures.append(f"{screen}: {selector} isn't in a status region, so a screen reader doesn't hear it")


def journey(f):
    failures = []
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    f.expected_errors += ["/contract/examples/"]

    def screen(name, steps):
        if SCREENS and name not in SCREENS:
            return
        try:
            steps()
        except (AssertionError, TimeoutError) as error:
            failures.append(f"{name}: couldn't open: {error}")

    # CourseWall: a guest at lesson 2, with Google and Apple on the wall.
    def wall():
        f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
        f.go("/learn/1-match", WALL)
        f.wait(f"{WALL}.querySelector('[data-wall-provider]')", what="Google and Apple on the wall")
        check(f, "wall", failures)
    screen("wall", wall)

    # Your part, on the course page of a course with parts.
    def part():
        f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
        open_course(f, COURSE)
        serve_song(f)
        with_parts(f)
        f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
        f.wait("document.querySelector('[data-course-part]')", what="Your part")
        check(f, "your-part", failures)
    screen("your-part", part)

    # The firm headphones ask, from the lesson's Start.
    def headphones():
        f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
        open_course(f, COURSE)
        line = headphones_needed.LINE
        f.run(f"""const store = {STORE}; const course = store.learning.courses['{COURSE}']; const notes = {json.dumps(line)};
            const step = {{ kind: 'play', text: {{ en: 'Sing your line over the lead.' }}, exercise: {{ tempo: 100, turn: false, notes, with: [{{ notes: notes.map((n) => ({{ ...n, step: n.step + 4 }})), volume: 0.8 }}] }} }};
            course.units[0].lessons[0] = {{ ...course.units[0].lessons[0], steps: [step] }};
            store.learning.courses = {{ ...store.learning.courses, '{COURSE}': {{ ...course, headphones: 'needed' }} }};""")
        f.mic_checked()
        f.run("sessionStorage.removeItem('stackapella.headphones'); sessionStorage.removeItem('stackapella.speaker'); sessionStorage.removeItem('stackapella.headphonesAsked'); localStorage.removeItem('stackapella.headphones.never');")
        f.run(f"sessionStorage.setItem('stackapella.lesson.{LESSON}', '0')")
        f.run(f"{ROUTER}.navigate('/learn/{LESSON}')")
        f.wait("document.querySelector('[data-play-disc]')", what="the lesson's Start")
        start = "document.querySelector('[data-play-disc]').closest('button')"
        f.run(f"{start}.click()")
        f.wait("document.querySelector('[data-headphones-needed]')", what="the firm ask")
        check(f, "headphones-needed", failures)
        # From the keyboard: the ask takes focus, and Escape gives it back to Start.
        if not f.js("!!document.querySelector('[role=dialog][aria-modal=true]')"):
            focus_returns(f, "headphones-needed", start, failures)
    screen("headphones-needed", headphones)

    # A rhythm step's result: pitch and timing, announced.
    def rhythm():
        f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
        open_course(f, COURSE)
        f.run(f"""const store = {STORE}; const course = store.learning.courses['{COURSE}']; const notes = {json.dumps(rhythm_step.GROOVE)};
            const step = {{ kind: 'play', text: {{ en: 'Sing the groove on the beat.' }}, exercise: {{ tempo: 100, turn: false, rhythm: true, notes, with: [{{ notes: notes.map((n) => ({{ ...n, vibrato: {{ cents: 0 }} }})), volume: 1 }}] }} }};
            course.units[0].lessons[0] = {{ ...course.units[0].lessons[0], steps: [step] }};
            store.learning.courses = {{ ...store.learning.courses, '{COURSE}': {{ ...course }} }};""")
        f.mic_checked()
        f.js(f"{AUDIO}.unlock()")
        f.run(f"sessionStorage.setItem('stackapella.lesson.{LESSON}', '0')")
        f.run(f"{ROUTER}.navigate('/learn/{LESSON}')")
        f.wait_text("Sing the groove on the beat.")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.wait("document.querySelector('[data-play-disc]')", what="the step's Start (its exercise loads after the step's words)")
        f.run("document.querySelector('[data-play-disc]').closest('button').click()")
        if f.js("!!document.querySelector('[role=dialog]')"):
            f.click_t("headphones.on")
        f.wait("document.querySelector('[data-result-heading]')", timeout=40, what="the result")
        f.js(f"{AUDIO}.engine.setLoopback(false)")
        announced(f, "rhythm-result", "[data-result-heading]", failures)
        check(f, "rhythm-result", failures)
    screen("rhythm-result", rhythm)

    # A looping step: the second step, its first take kept beneath it.
    def looping():
        f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
        open_course(f, COURSE)
        live_loop.lesson(f)
        f.mic_checked()
        f.js(f"{AUDIO}.unlock()")
        f.run(f"sessionStorage.setItem('stackapella.lesson.{LESSON}', '0')")
        f.run(f"{ROUTER}.navigate('/learn/{LESSON}')")
        f.wait_text("Lay down the first loop.")
        live_loop.sing(f)
        f.wait("document.querySelector('[data-result-heading]')", timeout=40, what="the first take's result")
        f.js(f"{AUDIO}.engine.setLoopback(false)")
        f.click_t("course.continue")
        f.wait_text("Now sing over your loop.")
        check(f, "loop-step", failures)
    screen("loop-step", looping)

    # The share card's sheet: the button, the sheet, the card's alt text, focus back on Share after.
    def share():
        f.go("/?app&api=mock&scenario=&as=member", "document.querySelector('#app header')")
        unit = "{ kind: 'unit', title: 'Unit done', headline: 'Your first harmony, all the way through', line: 'Learn to sing harmony', text: 'I finished it. Try it free:', lessonId: 'first-lesson' }"
        f.run(MOUNT.replace("h(ShareResult, { result, entry,", f"h(ShareResult, {{ milestone: {unit}, entry,").replace("color: '#c9b456' }", "color: '#c9b456', courseSlug: 'harmony' }"))
        opener = "document.querySelector('#share-test button')"
        f.run(f"{opener}.click()")
        f.wait("document.querySelector('[data-share-result] img')", what="the card")
        alt = f.js("document.querySelector('[data-share-result] img').alt")
        if "Your first harmony" not in alt or "Unit done" not in alt:
            failures.append(f"share-card: the card's alt text doesn't say what's on it: {alt!r}")
        check(f, "share-card", failures)
        focus_returns(f, "share-card", opener, failures)
    screen("share-card", share)

    # The group pages: a director's, a member's (with a part), the link a singer opens, and the tracks request.
    def director():
        f.go("/groups/Gp4rQs?api=mock&scenario=&as=plus", "document.querySelector('[data-ask-tracks]')")
        check(f, "group-director", failures)
    screen("group-director", director)

    def member():
        f.go("/groups/Gp4rQs?api=mock&scenario=&as=member", "document.querySelector('h1')")
        f.run("sessionStorage.setItem('stackapella.mockChoirMember', '1')")
        f.go("/groups/Gp4rQs?api=mock&scenario=&as=member", "document.querySelector('[data-my-part]')")
        check(f, "group-member", failures)
        f.run("sessionStorage.removeItem('stackapella.mockChoirMember')")
    screen("group-member", member)

    def link():
        f.go("/choir/Hk3nB8vQ2pL7sD5wR9tY1cX4?api=mock&scenario=&as=signedOut", "document.querySelector('#invite-email')")
        check(f, "group-link", failures)
    screen("group-link", link)

    def tracks():
        f.go("/groups/Gp4rQs?api=mock&scenario=&as=plus", "document.querySelector('[data-ask-tracks] button')")
        opener = "document.querySelector('[data-ask-tracks] button')"
        f.run(f"{opener}.click()")
        f.wait("document.querySelector('[data-tracks-form]')", what="the tracks form")
        check(f, "tracks-form", failures)
        f.go("/groups/Gp4rQs?api=mock&scenario=&as=plus", "document.querySelector('[data-ask-tracks] button')")
        focus_returns(f, "tracks-form", opener, failures)
    screen("tracks", tracks)

    unique = list(dict.fromkeys(failures))
    if unique:
        raise AssertionError(f"{len(unique)} problems:\n  " + "\n  ".join(unique[:80]))


if __name__ == "__main__":
    run("week_a11y", journey, sizes=("phone", "desktop"))
