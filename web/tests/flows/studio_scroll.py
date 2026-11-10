"""The studio's room on phones and short screens (docs/studio-words-plan.md, "Banner room"): below md or
under 720 px tall the page scrolls as one, the transport docked at the bottom with the page padded by
its height, and the ruler, lane and words stuck under the top edge once the header scrolls away. Record
brings the row being recorded under the sticky words; Stop moves nothing. A tall desktop keeps the fixed
column with its own tracks scroller. Run at 390 px, 320 x 568, a short laptop and a desktop.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from words_check import words_readable  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def box(selector):
    return f"document.querySelector({json.dumps(selector)}).getBoundingClientRect()"


def visible_between_edges(f, selector, what):
    """The element sits under the sticky block and above the dock."""
    got = f.js(f"""JSON.stringify((() => {{ const r = {box(selector)}; const top = {box('[data-sticky-words]')}.bottom; const bottom = {box('[data-studio-dock]')}.top;
        return {{ ok: r.top >= top - 2 && r.bottom <= bottom + 2, r: [Math.round(r.top), Math.round(r.bottom)], edges: [Math.round(top), Math.round(bottom)] }}; }})())""")
    found = json.loads(got)
    assert found["ok"], f"{what}: {found}"


def words_on_screen(f, what):
    """The line at the playhead is on screen, above the dock."""
    got = json.loads(f.js(f"JSON.stringify((() => {{ const r = {box('[data-now-words]')}; return [Math.round(r.top), Math.round(r.bottom), Math.round({box('[data-studio-dock]')}.top)]; }})())"))
    assert got[0] >= 0 and got[1] <= got[2], f"{what}: {got}"


def getComputedStyle_dock(f):
    return f.js("getComputedStyle(document.querySelector('[data-studio-dock]')).backgroundColor")


def journey(f):
    f.go("/", "document.querySelector('h1')")
    f.mic_checked()
    project = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3 && document.querySelector('[data-now-words]')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    page = f.js("!!document.querySelector('[data-page-scroll]')")
    if f.size == "desktop":
        # A tall desktop: unchanged, the column fixed and the tracks scrolling inside it.
        assert not page, "a tall desktop scrolls as a page"
        assert f.js("document.scrollingElement.scrollHeight <= innerHeight + 1"), "the desktop page scrolls"
        f.shot("tall")
        return
    assert page, f"{f.size} keeps the fixed column"
    assert f.js("document.scrollingElement.scrollHeight > innerHeight"), "the page doesn't scroll"
    tracks_box = "document.querySelector('[data-tracks]').parentElement.parentElement"
    assert f.js(f"getComputedStyle({tracks_box}).overflowY") == "visible" and f.js(f"{tracks_box}.scrollHeight <= {tracks_box}.clientHeight + 1"), "a second scroller for the tracks"
    f.no_sideways("the studio as a page")
    f.shot("top")
    # Scrolled to the end: the words stay under the top edge, the last row ends above the dock.
    f.run("window.scrollTo(0, document.scrollingElement.scrollHeight)")
    # Sticky, so at most at the top edge however far the page goes (it only reaches it when the page scrolls that far).
    f.wait(f"window.scrollY > 0 && {box('[data-sticky-words]')}.top >= -1 && getComputedStyle(document.querySelector('[data-sticky-words]')).position === 'sticky'", what="the words held on screen")
    words_on_screen(f, "the words while scrolled")
    # Nothing in the block is half off the screen: its first line, the parts bar on a phone, sits inside it.
    assert f.js(f"[...document.querySelector('[data-sticky-words]').children].every((c) => !c.offsetHeight || c.getBoundingClientRect().top >= -1)"), "a line of the sticky block cut at the top"
    words_readable(f, "[data-now-words]", "the words while scrolled")
    assert f.js(f"Math.abs({box('[data-studio-dock]')}.bottom - innerHeight) < 1"), "the transport isn't docked"
    assert f.js(f"[...document.querySelectorAll('[data-tracks] [data-track]')].at(-1).getBoundingClientRect().bottom <= {box('[data-studio-dock]')}.top + 1"), "the last row ends under the transport"
    f.shot("scrolled")
    # Record the last part from the top of the page: it comes under the sticky words.
    f.run("window.scrollTo(0, 0)")
    f.run("[...document.querySelectorAll('[data-tracks] [data-pick]')].at(-1).click()")
    bass = t(f, "parts.bass")
    f.click_t("studio.recordNamed", {"name": bass})
    f.wait("document.querySelector('[data-headphones]')", what="the headphones question")
    f.click_t("headphones.start")
    f.wait("document.querySelector('[data-timing]')", what="the timing check")
    f.click_t("timing.skip")
    f.wait(f"{AUDIO}.engine.playing?.recording && document.querySelector('[data-recording-row]')", what="recording")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 2", what="2 s recorded")
    f.run("await new Promise((r) => setTimeout(r, 600))")  # a smooth scroll settles
    visible_between_edges(f, "[data-recording-row]", "the row being recorded")
    # The dock is solid while recording (its tint sits on the panel), so no row shows through it.
    assert f.js("(([r, g, b, a]) => a === undefined || Number(a) >= 0.99)(getComputedStyle(document.querySelector('[data-studio-dock]')).backgroundColor.match(/[\\d.]+/g))"), getComputedStyle_dock(f)
    words_readable(f, "[data-now-words]", "the words while recording")
    words_on_screen(f, "the words while recording")
    f.shot("recording")
    # Stop: nothing jumps (the row stays where it was).
    before = f.js(f"Math.round({box('[data-recording-row]')}.top)")
    f.run("window.recordedRow = document.querySelector('[data-recording-row]')")
    f.click_t("studio.stopRecording")
    f.wait(f"!{AUDIO}.engine.playing?.recording", what="stopped")
    f.run("await new Promise((r) => setTimeout(r, 400))")
    after = f.js("Math.round(window.recordedRow.getBoundingClientRect().top)")
    assert abs(after - before) <= 2, f"the row jumped at Stop: {before} then {after}"


if __name__ == "__main__":
    run("studio_scroll", journey, sizes=("phone", "se", "laptop", "desktop"))
