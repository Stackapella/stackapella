"""M3, Sing (docs/singing-ux-plan.md, "Verification"): a new template project opens the chooser;
part by part, the alto's coach, a phrase heard and counted, Your turn, then Skip to record, a take
over loopback and Just recorded; the whole song on the round (Are You Sleeping): the Stage shows
the first line before playing, the part strip shows each part's note while playing, Get ready
before the alto's late entry, the turn colour on your own, the Staff and the Notes lane; recording
shows red; reload keeps the progress; Back returns to the studio.
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
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def new_project(f, template):
    return f.js(f"""(async () => {{
        const {{ startFromTemplate }} = await import('/js/stores/projects.js');
        const {{ loadTemplate }} = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('{template}'), {{ singers: 4 }})).id;
    }})()""")


def step_action(f, key, values):
    """The step's action: the transport's Play on a wide screen, the big button on a phone."""
    if f.size == "desktop":
        f.click_t("studio.play")
    else:
        f.wait_text(t(f, key, values))
        f.run("document.querySelector('[data-coach] .btn-primary').click()")


def journey(f):
    f.go("/", "document.querySelector('#app main, #app h1, #app header')")
    f.mic_checked()  # the mic check (5.1) has its own journeys
    # Part by part, on Amazing Grace.
    grace = new_project(f, "amazing-grace")
    f.go(f"/p/{grace}/sing", "document.querySelector('h1')")
    f.wait_text(t(f, "sing.chooser.title"))
    alto = t(f, "parts.alto")
    f.shot("chooser")
    # Three equal ways (Hillel), each a large button that starts at once; no Start button.
    sizes = f.js("[...document.querySelectorAll('[data-way]')].map((b) => { const r = b.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height), Math.round(r.top)]; })")
    assert len(sizes) == 3, sizes
    assert len({w for w, _, _ in sizes}) == 1 and len({h for _, h, _ in sizes}) == 1, f"the ways aren't the same size: {sizes}"
    in_a_row = len({top for _, _, top in sizes}) == 1
    assert in_a_row == (f.size == "desktop"), f"the ways aren't {'in a row' if f.size == 'desktop' else 'stacked'}: {sizes}"
    f.run("document.querySelector(\'[data-way=parts]\').click()")  # the way starts at once
    f.wait_text(t(f, "sing.chooser.yourPart"))
    f.run(f"[...document.querySelectorAll('main button, .card-interactive')].find((b) => b.textContent.includes({alto!r})).click()")
    f.wait_text(t(f, "sing.coach.hint.listen", {"part": alto}))
    # 66.png: between plays the stage is the phrase you're on (desktop); Play starts the step's action.
    if f.size == "desktop":
        f.wait("document.querySelector('[data-phrase-stage]')", what="the phrase stage")
    f.shot("coach")
    # Not in your range? (128.png): Lower moves the whole song a semitone, and the key says so.
    key = f"{SESSION}.session.project"
    start = f.js(f"{key}.transpose || 0")
    f.run("document.querySelectorAll('[data-key-control] button')[0].click()")  # Lower
    f.wait(f"({key}.transpose || 0) === {start - 1} && {key}.key !== {key}.template.key || {start - 1} === 0", what="Lower moved the song's key")
    f.run("document.querySelectorAll('[data-key-control] button')[1].click()")  # Higher
    f.wait(f"({key}.transpose || 0) === {start}", what="Higher put it back")
    f.js(f"{AUDIO}.unlock()")
    # N readies the next phrase between plays (the stage says so); the step bar's Listen starts afresh.
    if f.size == "desktop":
        f.press("n")
        f.wait("document.querySelector('[data-phrase-stage]')?.textContent.includes(" + repr(t(f, "sing.coach.phraseReady", {"number": 2})) + ")", what="N readied phrase 2")
        f.run("document.querySelector('[data-coach] ol li button').click()")
        f.wait("document.querySelector('[data-phrase-stage]')?.textContent.includes(" + repr(t(f, "sing.coach.phraseReady", {"number": 1})) + ")", what="back on phrase 1")
    step_action(f, "sing.coach.action.listen", {"part": alto, "number": 1})
    f.wait(f"{AUDIO}.engine.playing", what="phrase 1 playing")
    f.wait(f"({SESSION}.session.practice.progress?.alto?.phrases?.[0] ?? 0) >= 1", timeout=30, what="phrase 1 counted after a round")
    f.click_t("studio.pause")
    count = f.js(f"{SESSION}.session.practice.progress.alto.phrases[0]")
    f.wait_text(t(f, "sing.coach.practised", {"count": count}))
    # On your own: Your turn shows during the turn.
    f.run("document.querySelectorAll('[data-coach] ol')[1].querySelectorAll('li')[1].querySelector('button')?.click()")
    f.run(f"(await import('/js/stores/session.js')).session.practice.phrase = 1")
    f.click_t("sing.coach.next", {"step": t(f, "sing.coach.step.singAlong")})
    f.wait_text(t(f, "sing.coach.hint.singAlong"))
    f.click_t("sing.coach.next", {"step": t(f, "sing.coach.step.own")})
    f.wait_text(t(f, "sing.coach.hint.own"))
    step_action(f, "sing.coach.action.own", {"part": alto, "number": 1})
    f.wait(f"{SESSION}.session.phase === 'turn'", timeout=40, what="your turn")
    f.wait_text(t(f, "sing.turn.title"))
    # Your turn is a band in your colour at the top, with Settings; the words and the transport stay,
    # Again beside Pause. Your guide comes back from Settings' What you hear.
    f.shot("your-turn")
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-hearing]')", what="Session settings")
    f.run(f"[...document.querySelectorAll('[data-hearing] [role=switch]')].find((b) => b.textContent.includes({t(f, 'sing.hear.yours', {'part': alto})!r})).click()")
    f.press("Escape")
    f.wait(f"{SESSION}.session.phase !== 'turn'", what="the guide back on")
    f.click_t("studio.pause")
    # The song's title has room beside the part chip.
    assert f.js("(() => { const p = document.querySelector('header p'); return p.scrollWidth <= p.clientWidth; })()"), "the title is cut short"
    # Reload: the step and the count come back.
    f.go(f"/p/{grace}/sing", "document.querySelector('main')")
    f.wait_text(t(f, "sing.coach.hint.own"))
    # Skip to record, then a take and Just recorded.
    f.click_t("sing.coach.skipToRecord")
    f.wait_text(t(f, "sing.coach.hint.record", {"part": alto}))
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.click_t("sing.coach.action.record", {"part": alto})
    f.wait(f"document.querySelector('[role=dialog] select') || {AUDIO}.engine.playing?.recording", timeout=20, what="the timing check or recording")
    if f.js("!!document.querySelector('[role=dialog] select')"):
        f.click_t("timing.start")
        f.wait(f"document.body.innerText.includes({I18N}.t('timing.startRecording'))", timeout=40, what="the timing measured")
        f.click_t("timing.startRecording")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="recording")
    # Recording strips everything else away (113.png): the stage, the lane, Take 1, no part strip.
    f.wait(f"document.querySelector('[data-recording-stage]') && document.body.innerText.includes({I18N}.t('sing.recordingStage.take', {{ number: 1 }}))", what="the recording stage")
    assert f.js(f"!document.querySelector('[aria-label=\"' + {I18N}.t('sing.parts') + '\"]')"), "the parts show while recording"
    # The tuning meter across the Stage (or the lane's line, in the Notes view) on your turn.
    labels = json.dumps([t(f, "sing.tuning.none"), t(f, "sing.tuning.on")])
    f.wait(f"document.querySelector('canvas') || [...document.querySelectorAll('[role=img]')].some((e) => {labels}.includes(e.getAttribute('aria-label')) || (e.getAttribute('aria-label') ?? '').includes('cents'))", what="the tuning meter")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 2.5", what="2.5 s recorded")
    f.shot("recording")
    # Start over: the take goes, and the same part records again as Take 2; Esc stops and keeps.
    f.click_t("sing.recordingStage.startOver")
    f.wait(f"{AUDIO}.engine.playing?.recording && document.body.innerText.includes({I18N}.t('sing.recordingStage.take', {{ number: 2 }}))", timeout=20, what="recording again, Take 2")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 2", what="2 s recorded again")
    f.press("Escape")
    f.wait("document.querySelector('[data-take-review]')", what="the review after the take")
    # How it went: worked out in the background (over loopback only the other parts are heard, so a
    # short take may come back as couldn't hear, which is said plainly and costs nothing).
    f.wait(f"document.body.innerText.includes({I18N}.t('report.unheard')) || document.body.innerText.includes({I18N}.t('report.unheardSerious')) || document.querySelector('[data-take-review] svg[role=img]')", timeout=30, what="How it went")
    f.click_t("sing.review.nudgeTitle")
    f.click_t("sing.recorded.lineUp")
    f.wait(f"!document.body.innerText.includes({I18N}.t('sing.recorded.lining'))", timeout=30, what="lined up")
    f.shot("just-recorded")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    # On to the studio (one tap from Just recorded), which has the take.
    f.click_t("sing.openStudio")
    f.wait(f"location.pathname === '/p/{grace}' && document.querySelectorAll('canvas').length > 3", what="the studio")
    assert f.js(f"{SESSION}.session.parts.find((p) => p.templatePartKey === 'alto').frames > 0"), "the take isn't in the studio"

    # The whole song, on the round: the alto comes in at bar 3.
    round_ = new_project(f, "are-you-sleeping")
    f.go(f"/p/{round_}/sing", "document.querySelector('h1')")
    # Alto was picked on Amazing Grace, so the range is known: no part picker, the part that fits.
    f.wait(f"{SESSION}.session.practice.focus", what="a part picked for your range")
    assert t(f, "sing.chooser.yourPart") not in f.js("document.body.innerText"), "the part picker asked again"
    assert f.js("!!document.querySelector('[data-way=parts] [data-last-time]') && !document.querySelector('[data-way=whole] [data-last-time]')"), "part by part isn't marked Last time"
    f.run("document.querySelector(\'[data-way=whole]\').click()")  # the way starts at once
    # The round's alto (comes in at bar 3), whichever part fitted: the header's part changes it.
    f.run(f"const s = {SESSION}; s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto'))")
    f.wait("document.querySelector('main')", what="the stage")
    f.wait_text("Are")  # the first line, before playing
    f.shot("stage-idle")
    f.js(f"{AUDIO}.unlock()")
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing", what="playing")
    # The part cards are gone at every width (Hillel): the part is the header's chip.
    assert f.js("!document.querySelector('[aria-label=\"' + (await import('/js/i18n.js')).t('sing.parts') + '\"] li')"), "the part cards still show"
    f.wait_text(t(f, "sing.getReady.card"), timeout=20)
    f.shot("get-ready")
    f.click_t("studio.pause")
    # On your own: the stage takes your colour while you sing.
    f.click_t("practice.mode.own")
    f.run(f"{SESSION}.seek(8 * 60 / 100)")
    f.click_t("studio.play")
    f.wait_text(t(f, "sing.turn.band"), timeout=10)
    f.shot("turn-colour")
    f.click_t("studio.pause")
    # The chords underneath: a soft backing voice, on when asked.
    f.click_t("sing.settings.open")
    f.wait("document.querySelector('[role=dialog]')", what="Settings")
    f.shot("settings")
    f.click_t("sing.chords.title", )
    f.wait(f"{AUDIO}.engine.parts.has('backing')", timeout=20, what="the chords rendered")
    f.press("Escape")
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing", what="playing with the chords")
    f.click_t("studio.pause")
    f.sing_view("staff")
    f.wait("document.querySelector('main svg ellipse')", what="the staff")
    f.shot("staff")
    f.sing_view("notes")
    f.wait("document.querySelector('main canvas')", what="the notes lane")
    f.shot("notes")
    # The lane has the width (no parts column beside it); the mic level is in the header on a wide screen.
    assert f.js("!document.querySelector('main aside')"), "the parts column is back beside the lane"
    if f.size == "desktop":
        assert f.js("!document.querySelector('header [role=meter]')"), "a mic level is back in the header (the dot shows loudness)"
        assert f.js("document.querySelector('main canvas').getBoundingClientRect().width > innerWidth * 0.8"), "the lane doesn't take the width"
    # Your voice live on your turn: over loopback, with the others off in What you hear, the alto's own notes
    # (synthesized) stand in for a voice; the line is drawn and the notes it hits light up.
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run(f"""const s = {SESSION}; for (const p of s.session.parts) if (p.templatePartKey !== 'alto') s.toggleHearing(p.id);
        await (await import('/js/audio.js')).openMic(); s.seek(8 * 60 / 100);""")
    f.click_t("studio.play")
    f.wait_text(t(f, "sing.turn.band"), timeout=10)
    f.run(f"""const {{ engine }} = {AUDIO}; const s = {SESSION}; const {{ renderPart }} = await import('/js/synth.js');
        const alto = s.templatePart(s.session.focusPart).guide.notes.filter((n) => n.beat >= 8 && n.beat < 16).map((n) => ({{ ...n, beat: n.beat - 8 }}));
        engine.preview(renderPart(alto, {{ bpm: 100, sampleRate: engine.sampleRate, transpose: s.session.project.transpose || 0 }}), engine.sampleRate);""")
    f.wait("Number(document.querySelector('[data-voice]')?.dataset.voice) > 10 && Number(document.querySelector('[data-lit]')?.dataset.lit) >= 2", timeout=12, what="the live line, and notes lit")
    f.shot("live-voice")
    f.click_t("studio.pause")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    for project in (grace, round_):
        f.run(f"(await import('/js/stores/projects.js')).deleteProject('{project}')")


if __name__ == "__main__":
    run("sing", journey)
