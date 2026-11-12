"""A passing message never covers the header (docs/design-rules.md): the toast sits just under the bar at the top of
the screen, measured as it shows (js/components/ui.js Toasts, topBarBottom). Home's bar, a screen's Back and title, a
lesson's bar and the admin's sticky bar, at the top and scrolled, at phone and desktop size.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SHOW = "(await import('/js/components/ui.js')).toast('A message that passes by', { seconds: 30 })"
CLEAR = "(await import('/js/components/ui.js')).toasts.list = []"
GAP = """(() => { const toast = document.querySelector('[data-toast-tone]'); const x = document.documentElement.clientWidth / 2;
    const header = document.elementsFromPoint(x, 2).map((el) => el.closest('header')).find((h) => h && !h.closest('[data-toasts]'));
    return header ? Math.round(toast.getBoundingClientRect().top - header.getBoundingClientRect().bottom) : 99; })()"""


def clear_of_header(f, where):
    f.run(CLEAR)
    f.run(SHOW)
    f.wait("document.querySelector('[data-toast-tone]')", what=f"the toast on {where}")
    f.wait(f"{GAP} >= 0", what=f"the toast under the header on {where} ({f.js(GAP)} px)")
    # And on screen, near the top: never pushed below the window (it once sat at 1044 px in an 844 px phone).
    top = f.js("Math.round(document.querySelector('[data-toast-tone]').getBoundingClientRect().top)")
    assert 0 <= top <= f.js("innerHeight") / 3, f"the toast near the top on {where}: {top} px"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    clear_of_header(f, "Home")
    f.shot("home")
    f.run("(await import('/js/router.js')).navigate('/settings')")
    f.wait("document.querySelector('[data-ui=page-header]')", what="Settings")
    clear_of_header(f, "Settings")
    f.go("/learn/1-comfortable?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    clear_of_header(f, "a lesson")
    f.go("/admin/feedback?api=mock&as=admin", "document.querySelector('h1')")
    clear_of_header(f, "the admin")
    f.shot("admin")
    # Scrolled, the admin's bar stays (sticky) and the toast follows it.
    f.run("document.body.style.minHeight = '300vh'; window.scrollTo(0, 800)")
    f.wait(f"{GAP} >= 0", what="under the sticky bar, scrolled")
    f.run(CLEAR)


run("toast_clear", journey)
