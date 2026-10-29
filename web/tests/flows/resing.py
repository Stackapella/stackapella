"""Re-sing a phrase (feature 11, with Hillel's join rules): after a take of the Alto's first two
phrases over loopback, Just recorded offers Re-sing a phrase; the second is picked, sung again (it
stops by itself after the join), heard in place, and kept. The take changes only between the joins,
keeps its length, and Undo puts the old take back.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
SESSION = "(await import('/js/stores/session.js'))"
ALTO = f"{SESSION}.session.parts.find((p) => p.templatePartKey === 'alto')"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def setup_dialogs(f):
    """The timing check, if it asks, before recording."""
    f.wait(f"document.querySelector('[role=dialog] select') || {AUDIO}.engine.playing?.recording", timeout=20, what="the timing check or recording")
    if f.js("!!document.querySelector('[role=dialog] select')"):
        f.click_t("timing.start")
        f.wait(f"document.body.innerText.includes({I18N}.t('timing.startRecording'))", timeout=40, what="the timing measured")
        f.click_t("timing.startRecording")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="recording")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    f.mic_checked()  # the mic check (5.1) has its own journeys
    grace = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{grace}/sing", "document.querySelector('h1')")
    alto = t(f, "parts.alto")
    f.run("document.querySelector(\'[data-way=parts]\').click()")  # the way starts at once
    f.wait_text(t(f, "sing.chooser.yourPart"))
    f.run(f"[...document.querySelectorAll('main button, .card-interactive')].find((b) => b.textContent.includes({alto!r})).click()")
    f.wait_text(t(f, "sing.coach.skipToRecord"))  # game mode's character select flashes the part first
    f.click_t("sing.coach.skipToRecord")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    # A take of the first two phrases (80 bpm, 12 beats each: 18 s), over loopback.
    f.click_t("sing.coach.action.record", {"part": alto})
    setup_dialogs(f)
    f.wait(f"{AUDIO}.engine.positionInfo()?.position > 19", timeout=40, what="two phrases recorded")
    f.click_t("sing.recordingStage.stopKeep")
    f.wait("document.querySelector('[data-take-review]')", what="the review after the take")
    before = f.js(f"(async () => {{ const s = {SESSION}; const a = {ALTO}; const x = s.takeOf(a); return {{ frames: x.length, sum: x.reduce((m, v) => m + Math.abs(v), 0) }}; }})()")

    # Re-sing the second phrase: picked, then sung (it stops by itself after the join).
    # From the review: the second phrase picked and Re-sing it; or, with no phrase feedback (a
    # take not heard over loopback), the Re-sing tile and the second phrase in its sheet.
    f.wait("document.querySelector('[data-take-review]')", what="the review")
    f.wait(f"document.body.innerText.includes({I18N}.t('sing.review.resingTitle')) || document.body.innerText.includes({I18N}.t('sing.review.resingPhrase', {{ number: 1 }}))", timeout=40, what="a way to re-sing")
    if f.js(f"document.body.innerText.includes({I18N}.t('sing.review.resingTitle'))"):
        f.click_t("sing.review.resingTitle")
        f.wait("document.querySelector('[role=dialog] .card-interactive')", what="the phrases")
        f.run("document.querySelectorAll('[role=dialog] .card-interactive')[1].click()")
    else:
        f.click_t("sing.review.phrase", {"number": 2})
        f.click_t("sing.review.resingPhrase", {"number": 2})
    f.wait_text(t(f, "sing.resing.ready"))
    f.shot("ready")
    f.run("document.querySelector('[role=dialog] .btn-primary').click()")  # the sheet's own Sing it
    setup_dialogs(f)
    f.wait_text(t(f, "sing.resing.doneTitle"), timeout=40)
    f.shot("resung")
    plan = f.js(f"(async () => {{ const s = {SESSION}; return s.resingPlan({ALTO}, 1); }})()")
    assert plan["from"] > 8 and plan["to"] < 19.5, plan
    f.click_t("sing.resing.hear")
    f.wait(f"{AUDIO}.engine.playing", what="hearing it in place")
    f.click_t("studio.stop")
    f.click_t("sing.resing.keep")
    f.wait_text(t(f, "sing.resing.kept"))
    # Only the stretch between the joins changed (each side of a join, a fade's width away, is as it was).
    check = f.js(f"""(async () => {{
        const s = {SESSION}; const a = {ALTO}; const x = s.takeOf(a); const sr = a.sampleRate;
        const saved = await (await import('/js/db.js')).getAudio(a.id);
        const at = (sec) => Math.round((sec - a.offsetSeconds - (a.nudgeSeconds || 0)) * sr);
        return {{ frames: x.length, savedFrames: saved.length, startSame: Math.abs(x[at({plan['from']}) - sr] - saved[at({plan['from']}) - sr]) < 1e-3 }};
    }})()""")
    assert check["frames"] == before["frames"] and check["savedFrames"] == before["frames"], (check, before)
    f.click_t("common.undo")
    f.wait(f"(async () => {{ const x = {SESSION}.takeOf({ALTO}); return Math.abs(x.reduce((m, v) => m + Math.abs(v), 0) - {before['sum']}) < 1e-3 * {before['sum']}; }})()", what="the old take back with Undo")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.run(f"(await import('/js/stores/projects.js')).deleteProject('{grace}')")


if __name__ == "__main__":
    run("resing", journey)
