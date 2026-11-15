"""The timing check after the first take (Hillel, 2026-11-15: "Timing check after the first take"; docs/decisions.md).

A first take on a mic with no timing records at once with the estimate: no timing sheet before it. Its result offers the
check in one quiet line (the first week too); measured, the take moves by the difference between the estimate and the
measured round trip, so it lines up as if measured first, and it's marked measured (never moved twice). The next take
records with the measured timing and offers nothing. A beta take still measures first (beta_singer's own).
"""
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from resing import AUDIO, I18N, SESSION, t  # noqa: E402

LINE = "document.querySelector('[data-timing-recheck]')"
PART = f"(({{ session }}) => session.parts.find((p) => p.id === session.practice.focus))({SESSION})"
LANG = os.environ.get("TIMING_LANG", "en")  # TIMING_LANG=es: the same in Spanish
ESTIMATE = 4800  # 100 ms at 48 kHz: far from a loopback's real round trip, so the move is plain to see


def take(f, first=False):
    """A short take of the Alto over loopback, kept, to its result; no timing sheet on the way."""
    if first:
        f.click_t("sing.coach.action.record", {"part": t(f, "parts.alto")})
    else:
        f.click_t("sing.recorded.again")
    f.wait(f"document.querySelector('[role=dialog] select') || {AUDIO}.engine.playing?.recording", timeout=20, what="recording")
    assert not f.js("!!document.querySelector('[role=dialog] select')"), "the timing check before the take"
    f.wait(f"{AUDIO}.engine.positionInfo()?.position > 3", timeout=30, what="a few seconds recorded")
    f.click_t("sing.recordingStage.stopKeep")
    f.wait("document.querySelector('[data-take-review]')", what="the take's result")
    f.js("new Promise((r) => setTimeout(r, 400))")


def journey(f):
    f.go(f"/?lang={LANG}", "document.querySelector('#app header')")
    f.mic_checked()
    f.run("Object.keys(localStorage).filter((k) => k.startsWith('stackapella.latency')).forEach((k) => localStorage.removeItem(k))")
    grace = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{grace}/sing?lang={LANG}", "document.querySelector('h1')")
    f.run("document.querySelector('[data-way=parts]').click()")
    f.wait_text(t(f, "sing.chooser.yourPart"))
    alto = t(f, "parts.alto")
    f.run(f"[...document.querySelectorAll('main button, .card-interactive')].find((b) => b.textContent.includes({alto!r})).click()")
    f.wait_text(t(f, "sing.coach.skipToRecord"))
    f.click_t("sing.coach.skipToRecord")
    # Wired headphones, over loopback, with no timing for this mic: the estimate is set far from the real one.
    f.run(f"""const a = {AUDIO}; await a.unlock(); await a.openMic(); a.engine.setLoopback(true); a.chooseRoute('headphones');
        a.engine.estimatedLatencyFrames = () => Math.round({ESTIMATE} * a.engine.sampleRate / 48000);
        sessionStorage.setItem('stackapella.headphones', '1');""")
    assert f.js(f"{AUDIO}.audio.latencyFrames") is None, "a timing kept for this mic"

    # The first take: straight in, with the estimate.
    take(f, first=True)
    assert f.js(f"{AUDIO}.audio.latencyEstimated"), "the first take didn't use the estimate"
    used = f.js(f"JSON.parse(JSON.stringify({PART}.recordedWith))")
    assert used["estimated"] and used["latencyFrames"] == round(ESTIMATE * f.js(f"{AUDIO}.engine.sampleRate") / 48000), used
    before = f.js(f"{PART}.offsetSeconds")
    f.wait(f"{LINE}?.dataset.reason === 'estimated'", what="the check offered under the take")
    assert f.js(f"{LINE}.textContent.includes({I18N}.t('timing.recheck.estimated'))"), "its words"
    f.shot("offered")
    f.no_sideways("the take's result with the offer")

    # The check, measured: the take moves by the estimate less the measured round trip, and is marked measured.
    f.run(f"{LINE}.querySelector('[data-timing-recheck-open]').click()")
    f.wait(f"document.querySelector('[role=dialog]') && document.body.innerText.includes({I18N}.t('timing.title'))", what="the timing check")
    f.click_t("timing.start")
    f.wait(f"{AUDIO}.audio.latencyFrames !== null && !{AUDIO}.audio.latencyEstimated", timeout=40, what="the timing measured")
    f.shot("measured")
    rate = f.js(f"{AUDIO}.engine.sampleRate")
    measured = f.js(f"{AUDIO}.audio.latencyFrames")
    take_rate = f.js(f"{PART}.sampleRate")
    expected = before + used["latencyFrames"] / take_rate - measured / rate
    f.wait(f"!{PART}.recordedWith.estimated", what="the take marked measured")
    after = f.js(f"{PART}.offsetSeconds")
    assert abs(after - expected) < 1e-6, f"the take moved to {after}, not {expected} (before {before}, measured {measured} at {rate})"
    assert abs(after - before) > 0.02, f"the take barely moved ({before} to {after}): the test's estimate isn't far from the real timing"
    # Its place is what plays: the engine has it there (its nudge, the singer's own, untouched).
    assert f.js(f"{PART}.nudgeSeconds || 0") == 0, "the singer's nudge was changed"
    assert abs(f.js(f"{AUDIO}.engine.parts.get({PART}.id)?.offsetSeconds") - after) < 0.002, "the engine plays the take where it was"
    # Saved: a reload keeps the move, and the next measurement doesn't move it again.
    f.wait(f"(await import('/js/db.js')).get('parts', {PART}.id).then((p) => p && !p.recordedWith.estimated && Math.abs(p.offsetSeconds - {after}) < 1e-6)", what="the move saved")
    f.run(f"(await import('/js/stores/session.js')).lineUpEstimated({measured} + 960, {rate}, {AUDIO}.audio.mic?.label ?? null)")
    assert abs(f.js(f"{PART}.offsetSeconds") - after) < 1e-9, "a measured take moved again"
    f.press("Escape")
    f.wait("!document.querySelector('[role=dialog]')", what="the check closed")

    # The next take records with the measured timing, and offers nothing.
    take(f)
    assert not f.js(f"{PART}.recordedWith.estimated"), "the second take used the estimate"
    assert not f.js(f"!!{LINE}"), "the check offered again after it was measured"
    f.run(f"{AUDIO}.engine.setLoopback(false)")


if __name__ == "__main__":
    run("timing_after", journey, sizes=("phone",))
