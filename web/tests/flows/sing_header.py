"""Hillel: "more consistent with less clutter". Sing's header holds back, the title, the part chip
and Settings, nothing else (the view, What you hear and your guide live in Settings); Settings is in
the top-right corner. A phone's part chip is Settings' first row. The modes are centred, with no
"You'll hear" line under them.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import SESSION, clear_projects, light_first, new_song  # noqa: E402


def journey(f):
    f.go("/voice", "document.querySelector('#app header, #app main')")
    light_first(f)
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("document.querySelector('header [data-settings-open]')", what="Sing's header")
    f.shot("header")
    if f.size != "desktop":
        f.no_sideways("Sing")
    controls = f.js("[...document.querySelectorAll('header button, header [role=meter], header [role=group]')].filter((e) => e.getClientRects().length).length")
    expected = 2 if f.size != "desktop" else 3  # a phone's part chip is Settings' first row
    assert controls == expected, f"the header has {controls} controls, not {expected}"
    assert not f.js("document.querySelector('[data-hear-line]')"), "the You'll hear line is back"
    modes = f.js("(() => { const g = document.querySelector('[role=group][aria-label]'); const r = g.getBoundingClientRect(); const p = g.parentElement; const box = p.getBoundingClientRect(); return Math.abs((r.left + r.right) / 2 - (box.left + p.clientWidth / 2)); })()")
    assert modes < 4, f"the modes aren't centred ({modes}px off)"
    # Hillel: scrolling Settings scrolled the screen behind. A real wheel over the sheet scrolls the
    # sheet; the page and Sing's stage hold still, and the page unlocks when it closes.
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-session-settings]')", what="Session settings")
    stage = "(document.querySelector('[data-stage-view]')?.scrollTop ?? 0)"
    before = f.js(f"[scrollY, {stage}]")
    box = f.js("(() => { const r = document.querySelector('[data-session-settings]').getBoundingClientRect(); return [r.left + r.width / 2, Math.min(innerHeight - 40, r.top + 200)]; })()")
    for _ in range(4):
        f.chrome.send("Input.dispatchMouseEvent", {"type": "mouseWheel", "x": box[0], "y": box[1], "deltaX": 0, "deltaY": 300})
    f.wait("document.querySelector('[data-session-settings]').closest('[role=dialog], .overflow-y-auto').scrollTop > 0", what="the sheet scrolled")
    assert f.js(f"[scrollY, {stage}]") == before, "the screen behind Settings scrolled"
    assert f.js("document.documentElement.classList.contains('overflow-hidden')"), "the page isn't held while Settings is open"
    f.press("Escape")
    f.wait("!document.documentElement.classList.contains('overflow-hidden')", what="the page free again")
    if f.size != "desktop":
        f.run("document.querySelector('[data-settings-open]').click()")
        f.wait("document.querySelector('[data-part-picker]')", what="Settings' part picker")
        f.shot("settings-part")
        f.run("document.querySelector('[data-part-picker]').click()")
        f.wait("document.querySelector('[role=dialog] [aria-pressed]')", what="the part sheet")
    # Hillel: no zooming by accident. No double-tap zoom anywhere; a pinch on the lane doesn't zoom the page.
    assert f.js("getComputedStyle(document.documentElement).touchAction") == "manipulation", "a double tap can zoom"
    f.sing_view("notes")
    f.wait("document.querySelector('main canvas')", what="the notes lane")
    assert "pan-y" in f.js("getComputedStyle(document.querySelector('main canvas')).touchAction"), "a pinch on the lane zooms the page"
    # Guide voices only when some part has a real voice to choose.
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-hearing]')", what="What you hear")
    shown = f.js("document.querySelector('[data-hearing]').innerText.includes((await import('/js/i18n.js')).t('sing.settings.voices'))")
    assert not shown, "Guide voices shows with no real voice to choose"
    f.press("Escape")
    f.sing_view("words")
    top_right = f.js("""(() => { const s = document.querySelector('header [data-settings-open]').getBoundingClientRect();
        return s.top < 80 && innerWidth - s.right < 40; })()""")
    assert top_right, "Settings isn't in the top-right corner"


if __name__ == "__main__":
    run("sing_header", journey)
