"""The range check, the same capture as lesson 1's note (components/voice-capture.js): from Sing's chooser, it
listens at once, the first hum is the mic check too; a note held for a second becomes the lowest so far, then the highest,
and the result ranks the parts (sung into the mic by loopback: a held G2, then a held E4).
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


def sing_note(f, pitch):
    f.js(f"(await import('/js/creator-audio.js')).hearNotes([{{ beat: 0, length: 3, pitch: {pitch} }}], {{ tempo: 60 }})")


def journey(f):
    f.go("/", "document.querySelector('#app main, #app h1, #app header')")
    project = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}/sing", "document.querySelector('h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.wait_text(t(f, "sing.chooser.title"))
    f.run("document.querySelector(\'[data-way=whole]\').click()")  # the way starts at once
    f.click_t("sing.chooser.checkRange")
    f.wait("document.querySelector('[data-range]')", what="the range check")
    # The one capture (components/voice-capture.js): it listens at once, and the first hum on a new mic is the
    # mic check too ("Got you, your mic works"), as in lesson 1: no separate check sheet.
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-range] [data-voice-capture]')?.dataset.phase)", what="listening at once")
    assert not f.js("!!document.querySelector('[data-mic-check]')"), "a separate mic check"
    f.wait_text(t(f, "capture.lowest.listening"))
    assert f.js("document.querySelector('[data-range-next]').disabled"), "the lowest can be taken before a note is held"
    assert f.js("!!document.querySelector('[data-range] [data-mic-row]') && !!document.querySelector('[data-range] [data-scale]')"), "the mic row and the scale"
    sing_note(f, 50)
    f.wait("document.querySelector('[data-range] [data-mic-works]')", what="Got you, your mic works")
    f.shot("mic-check")
    f.js(f"await {AUDIO}.engine.stop()")
    sing_note(f, 43)
    held = "!document.querySelector('[data-range-next]').disabled && document.querySelector('[data-range] [data-note]')?.textContent.trim() === NOTE && document.querySelector('[data-range] [data-voice-capture]').dataset.phase !== 'holding'"
    f.wait(held.replace("NOTE", "'G2'"), timeout=12, what="G2 held, the lowest so far")
    f.wait_text(t(f, "capture.lowest.more", {"note": "G2"}))
    f.shot("lowest")
    f.click_t("range.next")
    f.wait("document.querySelector('[data-range]')?.dataset.step === 'high'", what="the highest step")
    f.wait_text(t(f, "capture.highest.listening"))
    f.js(f"await {AUDIO}.engine.stop()")
    sing_note(f, 64)
    try:
        f.wait(held.replace("NOTE", "'E4'"), timeout=12, what="E4 held")
    except AssertionError:
        raise AssertionError(f.js("document.querySelector('[data-range]').innerText"))
    f.shot("highest")
    f.click_t("range.next")
    f.wait_text(t(f, "range.yourRange", {"low": "G2", "high": "E4"}))
    f.shot("result")


if __name__ == "__main__":
    run("range_check", journey)
