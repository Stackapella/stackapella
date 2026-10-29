"""The range check, hands-free like Find your note: from Sing's chooser, it listens at once; the
voice is a marker on the bar, a note held for a second becomes "Lowest so far", then the highest,
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
    # The first time anything needs the mic: the check's sheet over it (103.png). A voice passes it,
    # and Continue goes on.
    f.wait("document.querySelector('[data-mic-check]')", what="the mic check")
    sing_note(f, 50)
    f.wait("document.querySelector('[data-mic-check]')?.dataset.state === 'ok'", what="Sounds good")
    f.shot("mic-check")
    f.click_t("micCheck.continue")
    f.wait("!document.querySelector('[data-mic-check]')", what="the check done")
    f.js(f"await {AUDIO}.engine.stop()")
    f.wait_text(t(f, "listening.listeningShort"))
    assert f.js("!!document.querySelector('[data-range] [data-level-meter]')"), "no level meter"
    assert f.js("[...document.querySelectorAll('[data-range] button')].find((b) => b.textContent.trim() === " + json.dumps(t(f, "range.next")) + ").disabled"), "the lowest can be taken before a note is held"
    sing_note(f, 43)
    f.wait("document.querySelector('[data-range-note]')?.textContent.trim() === 'G2' && document.querySelector('[data-range-note]').classList.contains('text-good')", timeout=10, what="G2 held")
    f.shot("lowest")
    f.click_t("range.next")
    f.wait("document.querySelector('[data-range]')?.dataset.step === 'high'", what="the highest step")
    f.js(f"await {AUDIO}.engine.stop()")
    sing_note(f, 64)
    try:
        f.wait("document.querySelector('[data-range-note]')?.textContent.trim() === 'E4' && document.querySelector('[data-range-note]').classList.contains('text-good')", timeout=10, what="E4 held")
    except AssertionError:
        raise AssertionError(f.js("document.querySelector('[data-range]').innerText"))
    f.shot("highest")
    f.click_t("range.next")
    f.wait_text(t(f, "range.yourRange", {"low": "G2", "high": "E4"}))
    f.shot("result")


if __name__ == "__main__":
    run("range_check", journey)
