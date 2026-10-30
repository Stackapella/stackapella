"""A lesson's results (ux-spec 5.8a, Hillel's mockups 48 to 51), in Stage dark and Warm light: the
comfortable note with its keyboard; an exercise's result, sung for real over loopback (the word,
the line, the stars, the target bands with the voice as dots, the numbers, Listen back); and the
lesson's end (the gems, the chips, the unit's path, the level, the Up next card with the next
lesson's own picture).
"""
import json
import time
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def theme(f, name):
    colour, mode = name.split("-")
    f.run(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps({'mode': mode, 'colour': colour}))}); window.stackapellaTheme.apply();")


def tone(f, notes, tempo=60):
    f.run(f"const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js'); engine.preview(renderPart({json.dumps(notes)}, {{ bpm: {tempo}, sampleRate: engine.sampleRate }}), engine.sampleRate);")


def journey(f):
    for name in ["stage-dark", "warm-light"]:
        f.go("/learn/1-comfortable", "document.querySelector('main h1')")
        f.run("localStorage.clear(); sessionStorage.clear(); indexedDB.deleteDatabase('stackapella');")
        f.go("/learn/1-comfortable", "document.querySelector('main h1')")
        theme(f, name)
        f.mic_checked()
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        # The comfortable note: an A3 held, then Finish.
        f.go_on()
        f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening")
        tone(f, [{"beat": 0, "length": 6, "pitch": 57}])
        f.wait("document.querySelector('[data-note]')?.textContent === 'A3'", timeout=20, what="the note found")
        finish = t(f, "course.finish")
        f.wait(f"[...document.querySelectorAll('button')].some((b) => b.textContent.trim() === {finish!r} && !b.disabled)", what="Finish, once the note is kept")
        f.click_t("course.finish")
        f.wait("document.querySelector('[data-note-result]')", what="the note result")
        assert f.js("[...document.querySelectorAll('[data-note-result] figure span')].some((k) => k.textContent === 'A3' && k.classList.contains('gem'))"), "the note's key is a gem"
        time.sleep(1.6)  # the gems have popped in
        f.shot(f"note-{name}")
        # An exercise, sung for real: the turn's notes played into the mic as the turn comes.
        f.click_t("course.next")
        f.wait("location.pathname === '/learn/1-match'", what="the next lesson")
        f.run("sessionStorage.setItem('stackapella.lesson.1-match', '2'); sessionStorage.setItem('stackapella.headphones', '1');")
        f.go("/learn/1-match", "document.querySelector('main h1')")
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.click_t("course.tap.sing")
        f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
        beat = 60 / 72
        f.run(f"""const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
            while ((engine.positionSeconds() ?? 0) < {9 * beat - 0.12}) await new Promise((r) => requestAnimationFrame(r));
            engine.preview(renderPart([{{ beat: 0, length: 2, pitch: 57 }}, {{ beat: 3, length: 2, pitch: 59 }}, {{ beat: 6, length: 2, pitch: 54 }}], {{ bpm: 72, sampleRate: engine.sampleRate }}), engine.sampleRate);""")
        f.wait("document.querySelector('[data-take-result]')", timeout=40, what="the take's result")
        assert f.js("document.querySelectorAll('[data-take-result] svg rect.fill-accent').length") > 10, "the voice as dots in the band"
        f.wait_text(t(f, "results.line.lit", {"lit": 3, "count": 3}))
        time.sleep(1.6)  # the gems have popped in
        f.shot(f"take-{name}")
        # With the guide: a switch under Listen back, kept as a setting. In the free course's first unit
        # it starts on (onboarding step 4): Listen back is Hear it together, your take with the guide under it.
        f.wait_text(t(f, "results.withGuide"))
        f.wait("document.querySelector('[data-with-guide]').getAttribute('aria-checked') === 'true'", what="With the guide on at first in unit 1")
        f.wait_text(t(f, "results.hearTogether"))
        f.run("document.querySelector('[data-with-guide]').click()")
        f.wait("document.querySelector('[data-with-guide]').getAttribute('aria-checked') === 'false'", what="With the guide off")
        assert "hearWithGuide\":false" in f.js("localStorage.getItem('stackapella.singPrefs')"), "kept as a setting"
        f.run("document.querySelector('[data-with-guide]').click()")
        f.wait("document.querySelector('[data-with-guide]').getAttribute('aria-checked') === 'true'", what="With the guide on again")
        assert "hearWithGuide\":true" in f.js("localStorage.getItem('stackapella.singPrefs')"), "kept on"
        f.run("document.querySelector('[data-with-guide]').click()")
        # With the guide sits under the voice, not ahead of it: a tone the engine plays at exactly 1 s
        # is recorded as the take, and withGuide puts the guide's onset at the same place, within
        # 30 ms. Loopback has no hardware path, so nothing is taken off for latency here (the
        # estimate a device needs would pull a loopback take about 40 ms early).
        gap = f.js(f"""(async () => {{
            const {{ engine }} = {AUDIO};
            const {{ renderPart }} = await import('/js/synth.js');
            const {{ withGuide }} = await import('/js/results.js');
            const rate = engine.sampleRate;
            for (const id of [...engine.parts.keys()]) engine.removePart(id); // only the check's tone sounds
            engine.setPart('guide-check', renderPart([{{ beat: 1, length: 1, pitch: 60 }}], {{ bpm: 60, sampleRate: rate }}), rate, {{ volume: 0.8, pan: 0, audible: true, offsetSeconds: 0 }});
            engine.play({{ fromSeconds: 0, bpm: 60, record: true, latencyFrames: 0, leadSeconds: 0 }});
            while ((engine.positionSeconds() ?? 0) < 2.4) await new Promise((r) => requestAnimationFrame(r));
            const take = await engine.stop();
            engine.removePart('guide-check');
            // From 0.4 s: the take opens with the tail of the lesson's last sound, which isn't the check's.
            const onset = (xs) => {{ const from = Math.round(0.4 * take.sampleRate); const part = xs.slice(from); const peak = part.reduce((m, v) => Math.max(m, Math.abs(v)), 0); return (from + part.findIndex((v) => Math.abs(v) > peak * 0.2)) / take.sampleRate; }};
            const sung = onset(take.samples);
            const silent = {{ samples: new Float32Array(take.samples.length), sampleRate: take.sampleRate }};
            const guide = onset(withGuide(silent, [{{ start: 1, end: 2, pitch: 60 }}]).samples);
            return Math.round((sung - guide) * 1000);
        }})()""")
        assert isinstance(gap, (int, float)) and abs(gap) <= 30, f"the guide and the take are {gap} ms apart"
        # The lesson's end: the gems, the chips, the path, the level, Up next.
        f.click_t("course.finish")
        f.wait("document.querySelector('[data-lesson-end]')", what="the lesson's end")
        # Game mode's stage clear names the stage instead.
        clear = t(f, "game.tally.clear", {"unit": 1, "lesson": 2}).upper()
        f.wait(f"document.body.innerText.includes({t(f, 'results.lessonOf', {'place': 2, 'total': 5})!r}) || document.querySelector('[data-end-place]')?.innerText.toUpperCase() === {clear!r}", what="the lesson's place")
        assert f.js("document.querySelectorAll('[data-lesson-end] ol li[data-state=done]').length") == 1, "the first lesson done"
        assert f.js("!!document.querySelector('[data-lesson-end] ol li[data-state=now]')"), "this lesson, just done"
        f.wait("document.querySelector('[data-lesson-end] aside .learn-visual svg')", what="the next lesson's own picture")
        time.sleep(1.6)  # the gems have popped in
        f.shot(f"end-{name}")
        if name == "stage-dark":
            # The first session's wrap (onboarding item 5): not after two lessons; after a third, on
            # the day the device arrived: the session's totals, the goal (3 shown, kept only on a
            # tap), and one keep-it ask (this browser's: an install offer, or an account).
            assert not f.js("!!document.querySelector('[data-session-wrap]')"), "the wrap after two lessons"
            f.run("const { learning } = await import('/js/stores/learning.js'); learning.progress = { ...learning.progress, lessons: { ...learning.progress.lessons, '1-high-low': { stars: 2, bestPercent: 80, at: Date.now() } } };")
            f.wait("document.querySelector('[data-session-wrap]')", what="the session wrap")
            # 3 days suggested, as an outline, and nothing shown chosen until a tap (onboarding review 2, 15).
            assert f.js("document.querySelector('[data-wrap-goal] [data-suggested]').textContent.trim()") == t(f, "wrap.days", {"count": 3}), "3 days suggested"
            assert not f.js("!!document.querySelector('[data-wrap-goal] [aria-checked=true]')"), "nothing shown chosen before a tap"
            f.wait("['prompt', 'account'].includes(document.querySelector('[data-wrap-keep]')?.dataset.ask)", what="one keep-it ask: " + str(f.js("document.querySelector('[data-wrap-keep]')?.dataset.ask ?? 'none'")))
            f.shot("session-wrap")
            f.run("[...document.querySelectorAll('[data-wrap-goal] [role=radio]')][2].click()")
            f.wait("(await import('/js/stores/learning.js')).learning.progress.goal === 5", what="5 days kept")
            assert f.js("document.querySelector('[data-wrap-goal] [aria-checked=true]').textContent.trim()") == t(f, "wrap.days", {"count": 5}), "5 days shown chosen once tapped"
            assert f.js("JSON.parse(localStorage.getItem('stackapella.funnel')).firsts.f_goal") is True, "the goal not counted"
            f.run("document.querySelector('[data-wrap-not-now]').click()")
            f.wait("!document.querySelector('[data-wrap-keep]')", what="Not now")
            assert f.js("JSON.parse(localStorage.getItem('stackapella.keepAsk')).nots") == 1, "Not now not kept"
            # Unit 1 done (onboarding item 6): your voice card, the note, the easy range and Share.
            assert not f.js("!!document.querySelector('[data-voice-card]')"), "the voice card before the unit is done"
            f.run("const { learning } = await import('/js/stores/learning.js'); learning.progress = { ...learning.progress, lessons: { ...learning.progress.lessons, '1-breath': { stars: 2, bestPercent: 80, at: Date.now() }, '1-challenge': { stars: 3, bestPercent: 92, at: Date.now() } } };")
            f.wait("document.querySelector('[data-voice-card] figure')", what="the voice card with its keyboard")
            f.wait_text(t(f, "voiceCard.share"))
            f.run("document.querySelector('[data-voice-card]').scrollIntoView({ block: 'center' })")
            f.shot("voice-card")
    f.run("localStorage.removeItem('stackapella.appearance'); window.stackapellaTheme.apply();")


if __name__ == "__main__":
    run("results", journey)
