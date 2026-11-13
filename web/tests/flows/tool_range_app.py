"""The app opened from the range test's result (docs/retention-plan.md, idea 7): ?range=A2-D4 is the singer's range from
the first screen (kept with the course's progress, the address made plain), so Home's picks are ranked to fit it and no
range check asks; a range already known is never overwritten. Settings' Practice section has one row to the free tools.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

RANGE = "(await import('/js/stores/learning.js')).learning.progress.range"


def journey(f):
    # A fresh device (each flow's browser starts empty): no range yet.
    f.go("/?api=mock&scenario=&as=member&range=A2-D4&ref=tool-vocal-range-test", "document.querySelector('#app header')")
    f.wait(f"JSON.stringify({RANGE}) === JSON.stringify({{ low: 45, high: 62 }})", what="the tool's range kept")
    assert "range=" not in f.js("location.search"), "the address made plain"
    assert f.js("(await import('/js/stores/learning.js')).knownRange()") is not None, "a known range: no range check asks"
    f.wait("document.querySelector('[data-song-card], [data-template-card], [data-library] a, [data-library] button')", what="Home's songs")
    f.shot("home")
    # A range already known stays as it is.
    f.go("/?api=mock&scenario=&as=member&range=C3-A4", "document.querySelector('#app header')")
    f.js("new Promise((r) => setTimeout(r, 800))")
    assert f.js(f"JSON.stringify({RANGE})") == '{"low":45,"high":62}', f.js(f"JSON.stringify({RANGE})")
    # Settings: the free tools, one row in Practice.
    f.run("(await import('/js/router.js')).navigate('/settings')")
    f.wait("document.querySelector('[data-free-tools]')", what="Free tools in Settings")
    assert f.js("new URL(document.querySelector('[data-free-tools]').href).pathname") == "/tools"
    f.run("document.querySelector('[data-free-tools]').scrollIntoView({ block: 'center' })")
    f.no_sideways("Settings")
    f.shot("settings")


run("tool_range_app", journey)
