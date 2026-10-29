"""The song builder's note editor (Hillel: "if I press play then stop I can't press play again"; "if I
change from lane to staff the draw select options don't seem to work"): Play, Stop, Play again, each
time the guides sounding and the playhead moving; then in Staff, Draw adds a note, Select picks it,
an arrow moves it and Delete removes it, with the tool kept across the switch.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, t  # noqa: E402
from sound import TAP  # noqa: E402

PLAY = "[...document.querySelectorAll('button')].find((b) => b.textContent.trim() === {!r} || b.textContent.trim() === {!r})"


def playing(f, label):
    """Play is pressed: the engine plays, the guides sound, and the playhead moves."""
    f.wait(f"{AUDIO}.engine.playing", what=f"{label}: playing")
    f.js(TAP)
    first = f.js("window.__playhead?.()")
    f.js("new Promise((r) => setTimeout(r, 900))")
    peak = f.js("(() => { window.__listening = false; return window.__peak; })()")
    later = f.js("window.__playhead?.()")
    print(f"    {label}: peak {peak:.3f}, playhead {first} -> {later}")
    assert peak > 0.01, f"{label}: silent (peak {peak})"
    assert later is not None and first is not None and later > first, f"{label}: the playhead didn't move ({first} -> {later})"


def journey(f):
    f.go("/create?copy=amazing-grace", "document.querySelector('#app header')")
    f.wait_text(t(f, "create.edit.title"))
    f.js(f"{AUDIO}.unlock()")
    play = PLAY.format(t(f, "create.edit.playAll"), t(f, "studio.stop"))
    # The playhead where it's drawn: the lane's red line, or the staff's.
    f.run("window.__playhead = () => { const r = document.querySelector('svg rect.fill-record, [data-playhead]'); return r ? +(r.getAttribute('x') ?? r.getBoundingClientRect().left) : null; };")
    f.run(f"{play}.click()")
    playing(f, "the first Play")
    f.run(f"{play}.click()")
    f.wait(f"!{AUDIO}.engine.playing", what="stopped")
    f.js("new Promise((r) => setTimeout(r, 300))")
    print("    after Stop the button reads", repr(f.js(f"{play}.textContent.trim()")))
    f.run(f"{play}.click()")
    f.js("new Promise((r) => setTimeout(r, 1500))")
    print("    then", repr(f.js(f"{play}.textContent.trim()")), "playing:", f.js(f"!!{AUDIO}.engine.playing"))
    playing(f, "Play after Stop")
    f.run(f"{play}.click()")
    f.wait(f"!{AUDIO}.engine.playing", what="stopped again")
    if f.size != "desktop":
        return  # a phone edits through the note's sheet, not the staff
    # The playhead (Hillel: "I'd like to be able to manage the playhead better"): a press on the ruler
    # puts it there, its handle drags, the keys move it, Play starts from it, and Back to the start.
    beat = "+document.querySelector('[data-playhead-handle]').getAttribute('aria-valuenow')"
    f.run("""const svg = document.querySelector('[data-note]').closest('svg'); const r = svg.getBoundingClientRect();
        svg.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, clientX: r.left + 8 + 6 * 44 + 3, clientY: r.top + 8 }));""")
    f.wait(f"{beat} > 0", what="a press on the ruler moving the playhead")
    at = f.js(beat)
    f.run("""const h = document.querySelector('[data-playhead-handle]'); const r = h.getBoundingClientRect(); const y = r.top + r.height / 2;
        h.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, clientX: r.left + r.width / 2, clientY: y, pointerId: 1 }));
        h.dispatchEvent(new PointerEvent('pointermove', { bubbles: true, clientX: r.left + r.width / 2 + 3 * 44, clientY: y, pointerId: 1 }));
        h.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, pointerId: 1 }));""")
    f.wait(f"{beat} > {at} + 1", what="dragging the handle")
    at = f.js(beat)
    f.run("document.querySelector('[data-playhead-handle]').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))")
    f.wait(f"Math.abs({beat} - {at} - 1) < 0.01", what="ArrowRight: a beat on")
    f.run("document.querySelector('[data-playhead-handle]').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', shiftKey: true, bubbles: true }))")
    f.wait(f"Math.abs({beat} - ({at} + 1 - 3)) < 0.01", what="Shift+ArrowLeft: a bar back (3/4)")
    from_beat = f.js(beat)
    f.run(f"{play}.click()")
    f.wait(f"{AUDIO}.engine.playing", what="Play from the playhead")
    start = f.js(f"{AUDIO}.engine.positionSeconds() * 80 / 60")
    print(f"    played from beat {start:.2f}, the playhead at {from_beat}")
    assert abs(start - from_beat) < 0.6, f"Play started at beat {start}, not the playhead's {from_beat}"
    f.run(f"{play}.click()")
    f.wait(f"!{AUDIO}.engine.playing", what="stopped")
    f.run("document.querySelector('[data-rewind]').click()")
    f.wait(f"{beat} === 0", what="Back to the start")
    f.shot("playhead")
    # Hillel: the toolbar on one line (zoom moved under the notes), and the note's actions together.
    tops = f.js("[...document.querySelectorAll('[role=radiogroup], button')].filter((b) => b.closest('section > div') === document.querySelector('[data-rewind]')?.parentElement).map((b) => Math.round(b.getBoundingClientRect().top))")
    assert tops and max(tops) - min(tops) < 20, f"the toolbar wraps: {sorted(set(tops))}"
    assert f.js("!!document.querySelector('[data-zoom]')"), "zoom isn't under the notes"
    f.run("document.querySelector('[data-note]').dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))")
    f.wait("document.querySelector('[data-note-actions]')", what="the note's actions")
    labels = f.js("[...document.querySelectorAll('[data-note-actions] button')].map((b) => b.getAttribute('data-action') + ':' + (b.title || ''))")
    print(f"    note actions: {labels}")
    assert [l.split(':')[0] for l in labels] == ["split", "join", "delete"] and all(l.split(':', 1)[1] for l in labels), f"the note's actions: {labels}"
    f.shot("note-actions")
    # The staff: the tool chosen on the lane stays, and Draw, Select, move and delete work there.
    radio = "[...document.querySelectorAll('[role=radio]')].find((b) => b.textContent.trim() === {!r})"
    f.run(radio.format(t(f, "create.edit.draw")) + ".click()")
    f.run(radio.format(t(f, "create.edit.staff")) + ".click()")
    f.wait("document.querySelector('[data-staff-note]')", what="the staff")
    assert f.js(radio.format(t(f, "create.edit.draw")) + ".getAttribute('aria-checked')") == "true", "Draw wasn't kept across the switch"
    # The part's notes, as its card counts them (the staff shows only the phrase around the selection).
    notes = "+(document.querySelector('li.border-accent')?.innerText.match(/· (\\d+)/)?.[1] ?? -1)"
    before = f.js(notes)
    # A press in the room after the line's last note, on the middle line: a note there.
    f.run("""const svg = document.querySelector('[data-staff-note]').closest('svg'); const heads = [...svg.querySelectorAll('[data-staff-note] ellipse')];
        const x = svg.viewBox.baseVal.width - 12 - 46 * 1.2; const y = 34 + 4 * 5;  // in the room after the line, on the middle line
        const p = new DOMPoint(x, y).matrixTransform(svg.getScreenCTM());
        svg.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, clientX: p.x, clientY: p.y }));""")
    f.wait(f"{notes} === {before + 1}", what="Draw adding a note on the staff")
    f.shot("staff-drawn")
    # Select: the new note picked, an arrow raises it, Delete removes it.
    f.run(radio.format(t(f, "create.edit.select")) + ".click()")
    f.run("const g = [...document.querySelectorAll('[data-staff-note]')].at(-1); g.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));")
    f.wait("[...document.querySelectorAll('[data-staff-note]')].at(-1).getAttribute('fill') !== 'currentColor'", what="the note selected")
    y0 = f.js("+[...document.querySelectorAll('[data-staff-note] ellipse')].at(-1).getAttribute('cy')")
    f.run("window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }))")
    f.wait(f"+[...document.querySelectorAll('[data-staff-note] ellipse')].at(-1).getAttribute('cy') < {y0}", what="ArrowUp moving it up")
    f.run("window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Delete', bubbles: true }))")
    f.wait(f"{notes} === {before}", what="Delete removing it")


if __name__ == "__main__":
    run("editor_playhead", journey)
