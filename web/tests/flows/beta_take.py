"""M2, a beta singer's take on the mock: from My parts into the studio's beta mode (the banner,
Hillel's retake note with its phrase to loop, the key locked), the timing check that can't be
skipped, the sound check, a take over loopback, the report before sending (with Hear it as
learners will), Record again, the verdict against the bar (Not ready yet over loopback, Close
with the phrases to loop and another part suggested after three tries, Ready to send), and sending
through the outbox back to My parts.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/beta?api=mock&scenario=&as=singer", "document.querySelector('main h1')")
    f.mic_checked()  # the mic check (5.1) has its own journeys
    f.wait_text(t(f, "beta.parts.title"))
    # Amazing Grace's alto has a retake note: Record another take.
    f.run("[...document.querySelectorAll('li.card')].find((li) => li.textContent.includes('Amazing Grace')).querySelector('.btn-primary').click()")
    f.wait("location.pathname.startsWith('/p/') && document.querySelectorAll('canvas').length > 3", what="the studio in beta mode")
    alto = t(f, "parts.alto")
    f.wait_text(t(f, "beta.studio.recordingFor", {"part": alto, "song": "Amazing Grace"}))
    f.wait_text(t(f, "beta.parts.hillelSays"))
    f.shot("beta-studio")
    assert f.js("(await import('/js/stores/projects.js')).projects.list.every((p) => !p.beta)"), "the beta project is listed among the singer's projects"
    # Record: headphones, then the timing check with no way to skip it.
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.js("localStorage.removeItem('stackapella.beta.soundCheck')")
    f.click_t("studio.recordNamed", {"name": alto})
    f.wait("document.querySelector('[role=dialog]')", what="the headphones question")
    f.click_t("headphones.start")
    f.wait("document.querySelector('[role=dialog] select')", what="the timing check")
    assert t(f, "timing.skip") not in f.js("document.querySelector('[role=dialog]').innerText"), "the timing check can be skipped"
    f.wait_text(t(f, "timing.requiredForGuides"))
    f.click_t("timing.start")
    f.wait(f"document.body.innerText.includes({I18N}.t('timing.startRecording'))", timeout=40, what="the timing measured")
    f.click_t("timing.startRecording")
    # The sound check, once on this device: 10 seconds, then the level checks.
    f.wait_text(t(f, "beta.sound.title"))
    f.click_t("beta.sound.start")
    f.wait(f"document.body.innerText.includes({I18N}.t('beta.sound.done'))", timeout=30, what="the sound check")
    f.shot("sound-check")
    f.click_t("beta.sound.done")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 2", what="2 s recorded")
    f.click_t("studio.stopRecording")
    f.wait_text(t(f, "beta.studio.checkAndSend"))
    # Record again from the report: the longer second take is the one checked and sent.
    f.click_t("beta.studio.checkAndSend")
    f.wait(f"document.body.innerText.includes({I18N}.t('beta.report.again'))", timeout=30, what="the first report")
    f.click_t("beta.report.again")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="recording again")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 5", what="5 s recorded")
    f.click_t("studio.stopRecording")
    f.wait_text(t(f, "beta.studio.checkAndSend"))
    f.run("const m = await import('/js/api.js'); const create = m.api.beta.submissions.create; window.sentFrames = []; m.api.beta.submissions.create = (body) => { window.sentFrames.push(body.frames / body.sampleRate); return create(body); };")
    # The report: over loopback the other parts are what's heard, so it can't be scored. Not ready
    # yet, said kindly, and no Send.
    f.click_t("beta.studio.checkAndSend")
    verdict(f, "notReady")
    f.shot("report-not-ready")
    assert t(f, "beta.report.send") not in dialog_text(f), "Send is offered under the bar"
    f.click_t("common.close")
    # A take a few phrases from the bar (sung for the test): Close, the phrases to loop. It's the
    # third under the bar, so another part or the course is suggested too.
    use_take(f, "{ cents: jitter(28), lateMs: jitter(70) }")
    f.click_t("beta.studio.checkAndSend")
    verdict(f, "close")
    f.wait_text(t(f, "beta.report.toFix"))
    assert "Amazing grace" in dialog_text(f) or "that saved a wretch" in dialog_text(f), "the phrases aren't named by their words"
    f.wait_text(t(f, "beta.report.otherTitle"))
    assert t(f, "beta.report.send") not in dialog_text(f), "Send is offered when close"
    f.shot("report-close")
    f.click_t("common.close")
    # A good take: Ready to send, the scores beside the bar, then sending.
    good = use_take(f, "{ cents: jitter(8), lateMs: jitter(25), vibrato: { hz: 5.5, cents: 14 } }")
    f.click_t("beta.studio.checkAndSend")
    verdict(f, "ready")
    f.wait_text(t(f, "beta.report.needs", {"percent": 90}))
    f.shot("report")
    f.click_t("beta.report.hear")
    f.wait(f"{AUDIO}.engine.playing && !{AUDIO}.engine.playing.recording", what="hearing it as learners will")
    f.click_t("studio.stop")
    f.type("[role=dialog] textarea", "Phrase 3 felt better this time")
    f.click_t("beta.report.send")
    f.wait("location.pathname === '/beta'", what="back to My parts")
    f.wait("(await (await import('/js/db.js')).all('betaTakes')).length === 0", timeout=20, what="the take sent and gone from the outbox")
    sent = f.js("window.sentFrames")
    assert sent and abs(sent[0] - good) < 0.5, f"the good take wasn't the one sent: {sent} vs {good}"


def dialog_text(f):
    return f.js("document.querySelector('[role=dialog]')?.innerText ?? ''")


def verdict(f, expected):
    f.wait("document.querySelector('[data-verdict]')", timeout=30, what="the verdict")
    found = f.js("document.querySelector('[data-verdict]').dataset.verdictIs")
    assert found == expected, f"the verdict is {found}, not {expected}: {dialog_text(f)[:400]}"


def use_take(f, faults):
    """Puts a take sung by the test's synthesized singer (fixtures.js) with `faults` in place of the
    alto's, lined up as a recorded take is; returns its length in seconds."""
    return f.js(f"""(async () => {{
        const {{ sing }} = await import('/tests/fixtures.js');
        const s = await import('/js/stores/session.js');
        let seed = 3;
        const by = new Map();
        const jitter = (amount) => (note) => {{
            if (!by.has(note)) by.set(note, new Map());
            if (!by.get(note).has(amount)) {{ seed = (seed * 16807) % 2147483647; by.get(note).set(amount, ((seed / 2147483647) * 2 - 1) * amount); }}
            return by.get(note).get(amount);
        }};
        const part = s.session.parts.find((p) => p.templatePartKey === 'alto');
        const notes = s.templatePart(part).guide.notes;
        const sung = sing(part.sampleRate, {{ notes, bpm: s.session.project.bpm, ...{faults} }});
        const pad = Math.max(0, Math.round(-(part.offsetSeconds ?? 0) * part.sampleRate));
        const take = new Float32Array(pad + sung.length);
        take.set(sung, pad);
        await s.storeTake(part, {{ frames: take.length, peaks: part.peaks.slice() }}, take); // new peaks: the studio re-reads the take
        return (take.length - pad) / part.sampleRate;
    }})()""")

if __name__ == "__main__":
    run("beta_take", journey)
