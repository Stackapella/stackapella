"""Hum a tune (docs/hum-a-tune-plan.md): from New song (staff only until it's switched on), hum in
your own time and the app writes it down: two lines of Amazing Grace hummed (the arranger's fixture,
played into the mic over loopback) come back as notes with a tempo, bars and a key, play back, and
go on to the note editor and the parts.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"
I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/?api=mock&as=admin", "document.querySelector('[data-new-song]')")
    f.mic_checked()
    f.run("document.querySelector('[data-new-song]').click()")
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.wait("document.querySelector('[data-new-hum]')", what="Hum a tune, offered to staff")
    f.run("document.querySelector('[data-new-hum]').click()")
    f.wait("location.pathname.startsWith('/create') && document.querySelector('[data-hum-tune]')", what="the hum step")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("document.querySelector('[data-hum-record]').click()")
    f.wait("document.querySelector('[data-hum-tune]')?.dataset.state === 'recording'", what="recording")
    f.run(f"""const {{ engine }} = {AUDIO}; const bytes = await (await fetch('/tests/fixtures/hum-two-lines.wav')).arrayBuffer();
        const decoded = await engine.ctx.decodeAudioData(bytes); engine.preview(decoded.getChannelData(0), decoded.sampleRate);""")
    f.shot("humming")
    f.js("new Promise((r) => setTimeout(r, 20500))")
    f.run("document.querySelector('[data-hum-stop]').click()")
    f.wait("document.querySelector('[data-hum-found]')", timeout=60, what="the tune written down")
    found = f.js("document.querySelector('[data-hum-found]').textContent")
    print("    found:", found)
    f.shot("found")
    f.run("document.querySelector('[data-hum-play]').click()")
    f.wait(f"{AUDIO}.engine.ctx.state === 'running'", what="playing back")
    # Line 1 again: hum just that line (the fixture's first line) and it takes its place.
    f.wait("document.querySelectorAll('[data-hum-lines] button').length >= 2", what="a line to hum again")
    f.run("document.querySelector('[data-hum-lines] button').click()")
    f.wait("document.querySelector('[data-hum-tune]')?.dataset.state === 'recording'", what="humming line 1 again")
    f.run(f"""const {{ engine }} = {AUDIO}; const bytes = await (await fetch('/tests/fixtures/hum-two-lines.wav')).arrayBuffer();
        const decoded = await engine.ctx.decodeAudioData(bytes); const first = decoded.getChannelData(0).slice(0, Math.round(9.5 * decoded.sampleRate));
        engine.preview(first, decoded.sampleRate);""")
    f.js("new Promise((r) => setTimeout(r, 10000))")
    f.run("document.querySelector('[data-hum-stop]').click()")
    f.wait("document.querySelector('[data-hum-tune]')?.dataset.state === 'idle' && document.querySelector('[data-hum-found]')", timeout=60, what="line 1 in its place")
    print("    after line 1 again:", f.js("document.querySelector('[data-hum-found]').textContent"))
    # With a click (one tap away): the beat is the click's, 80 a minute here, so the tempo is known.
    f.run("document.querySelector('[data-hum-click] [role=switch]').click()")
    f.wait("document.querySelector('[data-hum-click] .stepper')", what="the click's tempo")
    f.run("document.querySelector('[data-hum-record]').click()")
    f.wait("document.querySelector('[data-hum-tune]')?.dataset.state === 'recording'", what="humming to the click")
    f.run(f"""const {{ engine }} = {AUDIO}; const bytes = await (await fetch('/tests/fixtures/hum-two-lines.wav')).arrayBuffer();
        const decoded = await engine.ctx.decodeAudioData(bytes); engine.preview(decoded.getChannelData(0), decoded.sampleRate);""")
    f.js("new Promise((r) => setTimeout(r, 20500))")
    f.run("document.querySelector('[data-hum-stop]').click()")
    f.wait("document.querySelector('[data-hum-tune]')?.dataset.state === 'idle' && document.querySelector('[data-hum-found]')", timeout=60, what="the tune, to the click")
    # (The fixture isn't hummed to this click, so only the tempo is checked: a singer hums with it.)
    clicked = f.js("document.querySelector('[data-hum-found]').textContent")
    print("    with the click:", clicked)
    assert "80" in clicked and not f.js("!!document.querySelector('[data-hum-unsure]')"), f"the click's tempo, and sure of it: {clicked}"
    next_step = t(f, "create.frame.next", {"step": t(f, "create.step.edit")})
    f.wait(f"[...document.querySelectorAll('button')].some((b) => b.textContent.trim() === {json.dumps(next_step)} && !b.disabled)", what="on to the notes")
    # The notes in the editor, then the harmony suggested for the hummed tune.
    f.click(next_step, "button")
    f.wait("!document.querySelector('[data-hum-tune]')", what="the note editor")
    f.click(t(f, "create.frame.next", {"step": t(f, "create.step.parts")}), "button")
    f.click_t("create.parts.suggest")
    f.wait_text(t(f, "create.parts.chordsTitle"))
    f.wait("(async () => (await (await import('/js/stores/templates.js')).loadDraft(location.pathname.split('/').pop())).template.parts.length === 4)()", timeout=20, what="four parts")
    f.shot("harmonised")


if __name__ == "__main__":
    run("hum_tune", journey, sizes=("desktop", "phone"))
