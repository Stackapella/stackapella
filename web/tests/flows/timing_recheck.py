"""A fresh timing check, offered (master plan 15b, an r/acappella reader's idea): singing over
Bluetooth with a measured timing, headphones reconnecting (the browser's devicechange) make the take
after it say so in one quiet line under its result, with Check timing a tap away; it's said once.
A new singer's first week hears nothing of it. Every RECHECK_TAKES takes is the unit test's.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from resing import AUDIO, I18N, SESSION, setup_dialogs, t  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
LINE = "document.querySelector('[data-timing-recheck]')"


def started(f, days_ago):
    f.run(f"""const s = {STORE}; await s.loadLearning(); const db = await import('/js/db.js');
        const p = {{ ...s.learning.progress, startedAt: Date.now() - {days_ago} * 86400000 }}; s.learning.progress = p; await db.put('learning', JSON.parse(JSON.stringify(p)), 'progress');""")


def take(f, first=False):
    """A short take of the Alto over loopback, kept, to its result."""
    if first:
        f.click_t("sing.coach.action.record", {"part": t(f, "parts.alto")})
    else:
        f.click_t("sing.recorded.again")
    setup_dialogs(f)
    f.wait(f"{AUDIO}.engine.positionInfo()?.position > 3", timeout=30, what="a few seconds recorded")
    f.click_t("sing.recordingStage.stopKeep")
    f.wait("document.querySelector('[data-take-review]')", what="the take's result")
    f.js("new Promise((r) => setTimeout(r, 400))")


def reconnect(f):
    # With the mic open and settled (a device change as it opens is the opening's own: audio.js).
    f.wait(f"{AUDIO}.audio.micOpenedAt !== null && {AUDIO}.engine.ctx.currentTime - {AUDIO}.audio.micOpenedAt > 3.2", timeout=10, what="the mic settled")
    f.run("navigator.mediaDevices.dispatchEvent(new Event('devicechange'))")
    f.wait(f"{AUDIO}.audio.reconnected", what="the reconnect noticed")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    f.mic_checked()
    started(f, 2)  # week one first
    grace = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{grace}/sing", "document.querySelector('h1')")
    f.run("document.querySelector('[data-way=parts]').click()")
    f.wait_text(t(f, "sing.chooser.yourPart"))
    alto = t(f, "parts.alto")
    f.run(f"[...document.querySelectorAll('main button, .card-interactive')].find((b) => b.textContent.includes({alto!r})).click()")
    f.wait_text(t(f, "sing.coach.skipToRecord"))
    f.click_t("sing.coach.skipToRecord")
    # Bluetooth, with a measured timing for this mic (as a timing check leaves it), over loopback.
    f.run(f"""const a = {AUDIO}; await a.unlock(); await a.openMic(); a.engine.setLoopback(true);
        localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.bluetooth.${{a.engine.sampleRate}}`, '2400');
        a.chooseRoute('bluetooth'); sessionStorage.setItem('stackapella.headphones', '1');""")
    assert f.js(f"{AUDIO}.audio.latencyFrames") == 2400, "the measured timing in use"

    # Week one: headphones reconnect, and the take after says nothing of it.
    reconnect(f)
    take(f, first=True)
    assert not f.js(f"!!{LINE}"), "offered in a new singer's first week"

    # Past the first week: reconnect, and the next take's result offers the check, once.
    started(f, 10)
    reconnect(f)
    take(f)
    f.wait(f"{LINE}?.dataset.reason === 'reconnect'", what="the reconnect line under the take")
    assert f.js(f"{LINE}.textContent.includes({I18N}.t('timing.recheck.reconnect'))"), "its words"
    f.shot("recheck-line")
    f.no_sideways("the take's result with the line")
    f.run(f"{LINE}.querySelector('[data-timing-recheck-open]').click()")
    f.wait(f"document.querySelector('[role=dialog]') && document.body.innerText.includes({I18N}.t('timing.title'))", what="the timing check")
    assert not f.js(f"document.body.innerText.includes({I18N}.t('timing.skip'))"), "the check offered after a take can't swap the timing for an estimate"
    f.shot("timing-check")
    f.press("Escape")
    f.wait("!document.querySelector('[role=dialog]')", what="the check closed")

    # Said once: the take after says nothing.
    take(f)
    assert not f.js(f"!!{LINE}"), "offered twice for one reconnect"
    f.run(f"{AUDIO}.engine.setLoopback(false)")


if __name__ == "__main__":
    run("timing_recheck", journey)
