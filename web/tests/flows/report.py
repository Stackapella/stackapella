"""How it went (M5): a take of Amazing Grace's alto, sung by a synthesized voice over loopback, is
scored in the background; Just recorded shows the stars and the percent, See details opens what
went well, each phrase's stars and the pitch chart with the singer's line, and Replay with your line
plays it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
SESSION = "(await import('/js/stores/session.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


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
    f.click_t("sing.coach.skipToRecord")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    # Only the singer is heard: the other parts off in What you hear for this take.
    f.run(f"const s = {SESSION}; s.session.parts.filter((p) => p.templatePartKey !== 'alto').forEach((p) => s.toggleHearing(p.id));")
    f.click_t("sing.coach.action.record", {"part": alto})
    f.wait(f"document.querySelector('[role=dialog] select') || {AUDIO}.engine.playing?.recording", timeout=20, what="the timing check or recording")
    if f.js("!!document.querySelector('[role=dialog] select')"):
        f.click_t("timing.start")
        f.wait(f"document.body.innerText.includes({I18N}.t('timing.startRecording'))", timeout=40, what="the timing measured")
        f.click_t("timing.startRecording")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="recording")
    # The alto, sung (the synth) from where the take starts, through the loopback.
    f.run(f"""const {{ engine }} = {AUDIO}; const s = {SESSION}; const {{ renderPart }} = await import('/js/synth.js');
        const alto = s.session.parts.find((p) => p.templatePartKey === 'alto'); const notes = s.templatePart(alto).guide.notes.filter((n) => n.beat < 24);
        const sr = engine.sampleRate; const lead = Math.max(0, engine.playing.startTime - engine.ctx.currentTime - 0.03);
        const sung = renderPart(notes, {{ bpm: s.session.project.bpm, sampleRate: sr }});
        const voice = new Float32Array(Math.round(lead * sr) + sung.length); voice.set(sung, Math.round(lead * sr));
        engine.preview(voice, sr);""")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 17", timeout=40, what="two phrases sung")
    f.click_t("sing.recordingStage.stopKeep")
    # The review (79.png): the score as the headline, each phrase with its percent, the picked
    # phrase's pitch line against its notes, Play phrase, and one clear decision.
    f.wait("document.querySelector('[data-take-review]')", what="the review")
    f.wait("document.querySelector('[data-take-review] svg[role=img] polyline')", timeout=40, what="the singer's line on the chart")
    if f.size != "desktop":
        f.no_sideways("the take's review")
    headline = f.js("document.querySelector('#recorded-title').textContent")
    assert "%" in headline, f"the score isn't the headline: {headline}"
    assert not f.js("document.querySelector('[data-take-review] [aria-label*=star]')"), "stars on the review"
    f.wait_text(t(f, "sing.review.phrase", {"number": 2}))
    f.shot("how-it-went")
    f.click_t("sing.review.playPhrase", {"number": 1})
    f.wait(f"{SESSION}.session.playing", what="playing the phrase")
    f.click_t("studio.stop")
    assert f.js(f"{SESSION}.session.parts.find((p) => p.templatePartKey === 'alto').score?.pitchPercent >= 70"), "the score is kept with the part"


if __name__ == "__main__":
    run("report", journey)
