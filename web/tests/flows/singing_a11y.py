"""The singing path with a screen reader (the accessibility statement's untested path; the
coordinator's ARIA pass): lesson 1's note finder says its state calmly (a phase once it has held,
not every flicker of a wavering voice) and the note found as one line; lesson 2's take says Listen,
Your turn and Scoring in a polite line, and its result takes focus on its heading, the measure, so
a screen reader reads it first and Tab goes on to Listen back.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    f.wait_text(t(f, "course.start"))
    f.click_t("course.start")
    f.wait("location.pathname === '/learn/1-comfortable'", what="the first lesson")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.go_on()
    # The note finder: its spoken line is the phase, once it holds (never empty for long, never every flicker).
    spoken = "document.querySelector('[data-listening] p.sr-only[aria-live=polite]')?.textContent.trim() ?? ''"
    f.wait(f"{spoken} === {json.dumps(t(f, 'listening.listening'))}", what="Listening, said")
    f.run(f"""window.__spoken = []; const line = document.querySelector('[data-listening] p.sr-only[aria-live=polite]');
        new MutationObserver(() => window.__spoken.push(line.textContent.trim())).observe(line, {{ childList: true, characterData: true, subtree: true }});""")
    f.run(f"const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js'); engine.preview(renderPart([{{ beat: 0, length: 6, pitch: 57 }}], {{ bpm: 60, sampleRate: engine.sampleRate }}), engine.sampleRate);")
    f.wait("document.querySelector('[data-note]')?.textContent === 'A3'", timeout=20, what="the note found")
    said = f.js("window.__spoken")
    assert len(said) <= 4, f"the note finder said too much: {said}"
    found = f.js("document.querySelector('[data-listening] [role=status]')?.textContent.trim()")
    assert found and found.startswith(t(f, "listening.yourNote") + ": A3."), f"the note found, said as one line: {found!r}"

    # Lesson 2's take: the phase in a polite line, then focus on the result's heading.
    f.click_t("course.finish")
    f.wait("document.querySelector('[data-note-result]')", what="the note result")
    f.click_t("course.next")
    f.wait("location.pathname === '/learn/1-match'", what="the next lesson")
    f.click_t("course.tap.listen")
    f.wait(f"{AUDIO}.engine.playing", what="hearing it")
    phase = "document.querySelector('p[aria-live=polite].min-h-6')?.textContent.trim()"
    f.wait(f"!{AUDIO}.engine.playing", timeout=20, what="heard")
    f.click_t("course.continue")
    f.click_t("course.tap.try")
    # Headphones are asked once a visit, and not on every path: answered when asked.
    f.wait(f"document.body.innerText.includes({json.dumps(t(f, 'headphones.title'))}) || document.body.innerText.includes({json.dumps(t(f, 'course.wait.done'))})", timeout=30, what="the headphones question or the notes found")
    if t(f, "headphones.title") in f.js("document.body.innerText"):
        f.click_t("headphones.on")
    f.wait_text(t(f, "course.wait.done"), timeout=30)
    f.click_t("course.continue")
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=30, what="recording the turn")
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
        engine.preview(renderPart([{{ beat: 0, length: 3, pitch: 57 }}, {{ beat: 3, length: 3, pitch: 59 }}, {{ beat: 6, length: 3, pitch: 57 }}, {{ beat: 9, length: 6, pitch: 55 }}], {{ bpm: 60, sampleRate: engine.sampleRate }}), engine.sampleRate);""")
    f.wait(f"{phase} === {json.dumps(t(f, 'course.phase.turn'))}", timeout=30, what="Your turn, in the polite line")
    f.wait("document.activeElement?.matches('[data-result-heading]')", timeout=60, what="focus on the result's heading")
    heading = f.js("document.activeElement.textContent.trim()")
    assert heading, "the result's heading says nothing"
    f.shot("result-focused")


if __name__ == "__main__":
    run("singing_a11y", journey)
