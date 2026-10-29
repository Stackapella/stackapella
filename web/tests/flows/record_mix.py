"""A beta singer: "there are toggle to turn off/on tracks but when he record he only hears his
track". While you record, you hear the other parts: the engine's output is measured (an analyser on
the play bus), not just the levels map. Sing plays every other part: after Just recorded's mixer
turned some off (its mutes, and its listening, are for the review only, never saved), and with the
studio's mutes saved (they stay in the studio). What you hear turns parts off for the visit, before
a take and during one; without headphones the others play softer. The studio keeps its toggles.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, light_first, new_song, t  # noqa: E402
from sound import TAP  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def heard_while_recording(f, where, seconds=2.5):
    """Records for a moment past the count-in and returns what played: the peak and the audible voices."""
    f.wait(f"document.querySelector('[role=dialog] select') || {AUDIO}.engine.playing?.recording", timeout=20, what=f"{where}: the timing check or recording")
    if f.js("!!document.querySelector('[role=dialog] select')"):
        f.click_t("timing.start")
        f.wait(f"document.body.innerText.includes({I18N}.t('timing.startRecording'))", timeout=40, what="the timing measured")
        f.click_t("timing.startRecording")
    f.wait(f"{AUDIO}.engine.playing?.recording && {SESSION}.session.countIn === null && {SESSION}.session.position > {SESSION}.session.recordFrom + 0.3", timeout=30, what=f"{where}: recording past the count-in")
    f.js(TAP)
    f.js(f"new Promise((r) => setTimeout(r, {seconds * 1000}))")
    peak = f.js("(() => { window.__listening = false; return window.__peak; })()")
    voices = f.js(f"[...{AUDIO}.engine.parts].filter(([, p]) => p.mix?.audible).map(([id]) => id).join(' ')")
    names = f.js(f"Object.fromEntries({SESSION}.session.parts.map((p) => [p.id, p.templatePartKey ?? p.name]))")
    for pid, name in names.items():
        voices = voices.replace(pid, name)
    print(f"    record {where}: peak {peak:.3f}, audible: {voices or 'nothing'}")
    return peak, voices


def mix(key):
    """The engine's mix for a part's guide, by its template part key."""
    return f"{AUDIO}.engine.parts.get({SESSION}.session.parts.find((p) => p.templatePartKey === '{key}').id + ':guide')?.mix"


def open_hearing(f):
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-hearing]')", what="Session settings' What you hear")


def hearing_switch(f, key):
    f.run(f"""const {{ t }} = {I18N}; const name = t('parts.{key}');
        [...document.querySelectorAll('[data-hearing] [role=switch]')].find((b) => b.textContent.includes(name)).click();""")


def stop(f):
    f.run("(document.querySelector('[data-stop]') ?? [...document.querySelectorAll('button')].find((b) => /stop/i.test(b.getAttribute('aria-label') ?? b.textContent)))?.click()")
    f.wait(f"!{SESSION}.session.recording", timeout=20, what="recording stopped")


def journey(f):
    f.go("/voice", "document.querySelector('#app header, #app main')")
    f.mic_checked()
    light_first(f)  # the shots are for Hillel's review, in the light theme
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    # A timing already measured for this mic (the timing check has its own journeys).
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['headphones', 'speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute(a.audio.route || 'headphones');""")
    clear_projects(f)
    song = new_song(f)
    problems = []
    # Sing, the whole song, the alto.
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    f.press("r")
    peak, voices = heard_while_recording(f, "Sing, whole song")
    if peak < 0.01 or "soprano" not in voices or "alto" in voices:
        problems.append(f"Sing: {voices or 'nothing'} (peak {peak:.3f})")
    stop(f)
    # Just recorded: Hear it with the others leaves Listen as it was (never saved into practice).
    f.wait(f"document.body.innerText.includes({I18N}.t('sing.recorded.title', {{ part: {I18N}.t('parts.alto') }}))", timeout=20, what="Just recorded")
    f.run(f"document.querySelector('[aria-label=\"' + {I18N}.t('sing.recorded.hearOthers') + '\"]').click()")
    f.wait(f"{SESSION}.session.reviewing && {AUDIO}.engine.playing", what="the review playing your take")
    f.run(f"document.querySelector('[aria-label=\"' + {I18N}.t('studio.stop') + '\"]')?.click()")
    assert f.js(f"!{SESSION}.session.practice.withOthers && !{SESSION}.session.practice.compare"), "the review's listening was saved into practice"
    f.click_t("sing.recorded.again")
    peak, voices = heard_while_recording(f, "Sing, again after the review")
    if peak < 0.01 or "soprano" not in voices:
        problems.append(f"Sing again after the review: {voices or 'nothing'} (peak {peak:.3f})")
    stop(f)
    # What you hear: the soprano off before recording, the tenor off during the take (a fresh visit).
    f.go(f"/p/{song}/sing", "document.querySelector('[data-settings-open]')")
    assert f.js(f"!{SESSION}.session.reviewing"), "the review's listening outlived the review"
    f.shot("modes")
    open_hearing(f)
    f.shot("hearing-panel")
    hearing_switch(f, "soprano")
    f.wait(f"{SESSION}.session.hearing.off.length === 1 && document.querySelector('[data-hearing-changed]')", what="the soprano off, and the button marked")
    f.press("Escape")
    f.press("r")
    peak, voices = heard_while_recording(f, "Sing, the soprano off in What you hear")
    if peak < 0.01 or "soprano" in voices or "tenor" not in voices:
        problems.append(f"What you hear, before recording: {voices or 'nothing'} (peak {peak:.3f})")
    f.shot("recording-line")
    open_hearing(f)
    f.shot("hearing-recording")
    hearing_switch(f, "tenor")
    f.wait(f"!{mix('tenor')}.audible && {mix('bass')}.audible", what="the tenor off mid-take, live")
    # Your own guide: off while recording, on when you turn it on mid-take (and the take says so).
    assert f.js(f"!{mix('alto')}.audible"), "your guide played while recording before you turned it on"
    hearing_switch(f, "alto")
    f.wait(f"{mix('alto')}.audible && {SESSION}.session.guideHeard", what="your guide on mid-take")
    f.shot("hearing-recording-guide")
    stop(f)
    # Remembered: leaving Sing and coming back keeps them (per song, on this device); the project's
    # parts aren't touched, so the studio is as it was. Settings' Reset clears them.
    f.go(f"/p/{song}", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.screen === 'studio' && {SESSION}.session.parts.length > 3", what="the studio")
    assert f.js(f"!{SESSION}.session.parts.some((p) => p.muted || p.solo)"), "What you hear reached the studio"
    f.go(f"/p/{song}/sing", "document.querySelector('[data-settings-open]')")
    f.wait(f"{SESSION}.session.hearing.off.length === 2 && {SESSION}.session.hearing.recordGuide && document.querySelector('[data-hearing-changed]')", what="What you hear remembered, and Settings marked")
    f.run("document.querySelector('[data-settings-open]').click()")
    f.click_t("sing.settings.reset")
    f.wait(f"{SESSION}.session.hearing.off.length === 0 && !{SESSION}.session.hearing.recordGuide", what="Reset clearing What you hear")
    f.press("Escape")
    # No headphones: the others softer while recording, and What you hear says so.
    f.run(f"{AUDIO}.chooseRoute('speakers')")
    f.go(f"/p/{song}/sing", "document.querySelector('[data-settings-open]')")
    f.click_t("sing.recorded.again") if f.js(f"document.body.innerText.includes({I18N}.t('sing.recorded.again'))") else f.press("r")
    heard_while_recording(f, "Sing, no headphones")
    assert f.js(f"Math.abs({mix('soprano')}.volume - (await import('/js/practice.js')).SPEAKERS_UNDER) < 1e-6"), "the others aren't softer on speakers"
    open_hearing(f)
    f.wait("document.querySelector('[data-speakers]')", what="the speakers notice")
    f.shot("speakers")
    stop(f)
    f.run(f"{AUDIO}.chooseRoute('wired')")
    # The studio's mutes, saved, stay in the studio: Sing still plays every other part.
    f.run(f"const s = {SESSION}; for (const p of s.session.parts) if (p.templatePartKey !== 'alto') s.updatePart(p, {{ muted: true }});")
    f.js("new Promise((r) => setTimeout(r, 1200))")  # the part saves settle (updatePart saves after 400 ms)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3 && {SESSION}.session.parts.some((p) => p.muted)", what="Sing, with the studio's mutes")
    f.click_t("sing.recorded.again") if f.js(f"document.body.innerText.includes({I18N}.t('sing.recorded.again'))") else f.press("r")
    peak, voices = heard_while_recording(f, "Sing, the studio's mutes saved")
    if peak < 0.01 or "soprano" not in voices:
        problems.append(f"Sing with the studio's mutes: {voices or 'nothing'} (peak {peak:.3f})")
    stop(f)
    # The studio (Hillel: "play all tracks"): recording a track, every unmuted track plays, the one
    # being recorded too (its guide); muted, it's silent; with a solo, only the soloed play.
    f.run(f"const s = {SESSION}; for (const p of s.session.parts) s.updatePart(p, {{ muted: false, solo: false }});")
    f.js("new Promise((r) => setTimeout(r, 1200))")  # the part saves settle (updatePart saves after 400 ms)
    f.go(f"/p/{song}", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3 && {SESSION}.session.screen === 'studio'", what="the studio")
    pick = f"document.querySelector(`[data-pick][aria-label=\"${{{I18N}.t('studio.pickNamed', {{ name: {I18N}.t('parts.tenor') }})}}\"]`)"
    f.run(f"{pick}.click(); {SESSION}.session.position = 0;")
    f.wait(f"{pick}.getAttribute('aria-pressed') === 'true'", what="the tenor picked")
    f.press("r")
    peak, voices = heard_while_recording(f, "studio, recording the tenor")
    if peak < 0.01 or "tenor" not in voices or "soprano" not in voices:
        problems.append(f"studio, all tracks: {voices or 'nothing'} (peak {peak:.3f})")
    f.run(f"const s = {SESSION}; s.updatePart(s.session.parts.find((p) => p.templatePartKey === 'tenor'), {{ muted: true }});")
    f.wait(f"!{mix('tenor')}.audible && {mix('soprano')}.audible", what="the recorded track muted: silent, the rest playing")
    f.run(f"const s = {SESSION}; s.updatePart(s.session.parts.find((p) => p.templatePartKey === 'bass'), {{ solo: true }});")
    f.wait(f"{mix('bass')}.audible && !{mix('soprano')}.audible && !{mix('tenor')}.audible", what="a solo: only the soloed")
    stop(f)
    # The mixer with the bass soloed and the tenor really muted: the tenor's M fully lit, the others'
    # M softly lit and not pressed (silent under the solo), every silent lane greyed, no "Not soloed".
    f.wait("document.querySelectorAll('[data-implied-mute]').length === 2", what="the others silent under the solo")
    assert f.js("[...document.querySelectorAll('[data-implied-mute]')].every((b) => b.getAttribute('aria-pressed') === 'false' && b.getAttribute('aria-describedby'))"), "the implied mute is pressed or undescribed"
    assert f.js("document.querySelectorAll('[data-lane].grayscale').length === 3"), "the silent lanes aren't greyed"
    f.shot("studio-mixer")
    f.run(f"const s = {SESSION}; for (const p of s.session.parts) s.updatePart(p, {{ muted: false, solo: false }});")
    # A second tap on the picked track clears it: nothing picked, Record waits for a pick, focus stays.
    f.run(f"{pick}.focus(); {pick}.click();")
    f.wait(f"{pick}.getAttribute('aria-pressed') === 'false' && document.querySelector('[data-record]').disabled", what="no track picked, Record waiting")
    assert f.js(f"document.querySelector('[data-record]').innerText.includes({I18N}.t('studio.pickFirst'))"), "Record doesn't say to pick a track"
    assert f.js(f"document.activeElement === {pick}"), "focus left the track"
    assert not f.js("document.querySelector('[data-pick][aria-pressed=true]')"), "a track is still picked"
    f.shot("studio-none-picked")
    assert not problems, "; ".join(problems)


if __name__ == "__main__":
    run("record_mix", journey)
