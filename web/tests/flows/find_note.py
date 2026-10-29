"""Find your note, hands-free (Hillel's redesign): no Start listening, it listens the moment the
step opens. Before it, the way on says "Turn on my mic" with why we listen; the first time on a
mic, the first hum is the mic check as well ("Got you", while it goes on finding the note); a reload straight into the step waits for
a tap anywhere (browsers start no audio before one); nothing heard brings a gentle word while it keeps listening, with the mic picker right
there; "Skip for now" goes on with a typical note (the lesson counts with one star) and later
lessons remind gently. Sung for real (an A3 into the mic), the marker glides on the wide bar, the
bar zooms to the octave around it, and the note is shown big, with what it means.
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


def phase(f):
    return f.js("document.querySelector('[data-listening]')?.dataset.phase")


def journey(f):
    f.go("/learn/1-comfortable", "document.querySelector('main h1')")
    f.js(f"{AUDIO}.unlock()")  # the tap on Continue, as a person's would
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    # Before the mic: the way on says "Turn on my mic", with why we listen in one line. (This
    # browser lets the mic in without asking, so it's told the question is still to come, and the
    # lesson opened again inside the app.)
    f.run("const query = navigator.permissions.query.bind(navigator.permissions); navigator.permissions.query = async (d) => (d.name === 'microphone' ? { state: 'prompt' } : query(d));")
    f.run("const { navigate } = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    f.wait("document.querySelector('[data-go-on]')", what="the lesson again")
    f.wait_text(t(f, "course.mic.turnOn"))
    f.wait("document.querySelector('[data-mic-why]')", what="why we listen")
    f.shot("turn-on-mic")
    # In an app's own browser (Instagram here), a card first: how to open it in Safari, Copy link,
    # and the way on says "Carry on anyway".
    ua = "Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 350.0.0"
    f.run(f"Object.defineProperty(navigator, 'userAgent', {{ get: () => {ua!r}, configurable: true }}); const {{ navigate }} = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    f.wait("document.querySelector('[data-in-app]')", what="the in-app card")
    f.wait_text(t(f, "course.mic.carryOn"))
    f.shot("in-app")
    f.run("delete navigator.userAgent; const { navigate } = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    f.wait_text(t(f, "course.mic.turnOn"))
    f.go_on()
    # The first time on this mic, one hum is the check and the note together (no separate check):
    # it listens at once, and the first voice says "Got you" while it goes on finding the note.
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening at once")
    assert not f.js("!!document.querySelector('[data-mic-check]')"), "a separate mic check"
    assert not f.js("[...document.querySelectorAll('button')].some((b) => b.textContent.includes('Start listening'))"), "a Start listening button"
    f.wait_text(t(f, "listening.listening"))
    f.shot("listening")
    f.js("(await import('/js/creator-audio.js')).hearNotes([{ beat: 0, length: 1, pitch: 57 }], { tempo: 60 })")
    f.wait("document.querySelector('[data-mic-works]')", what="Got you, your mic works")
    f.wait_text(t(f, "micCheck.gotYou"))
    assert f.js("!(await import('/js/mic-check.js')).micNeedsCheck()"), "the mic isn't remembered as heard"
    f.shot("mic-ok")
    f.click_t("listening.startOver")  # a fresh count: what follows hears nothing at all
    # Nothing sung (loopback with nothing playing): the no-sound card (Hillel's Scarlett mockup):
    # the status, the level, the mic, Things to check and Try again; it keeps listening underneath.
    f.wait("['silent', 'quiet'].includes(document.querySelector('[data-no-sound]')?.dataset.why)", timeout=15, what="the no-sound card")  # quiet: the tone's tail may count
    f.wait_text(t(f, "micCheck.checkTitle"))
    assert f.js("document.querySelectorAll('[data-no-sound] ol li').length") == 3, "three things to check"
    assert f.js("!!document.querySelector('[data-no-sound] [data-level-segments]') && !!document.querySelector('[data-no-sound] select')"), "the level and the mic picker"
    assert phase(f) == "listening", f"stopped listening: {phase(f)}"
    f.shot("no-sound")
    assert not f.js("[...document.querySelectorAll('button')].some((b) => b.textContent.trim() === " + repr(t(f, "course.finish")) + ")"), "Finish offered without a note"
    # Skip, I'll set up my mic later: on with a typical note; the lesson counts with one star.
    f.click_t("micCheck.skipSetup")
    f.wait_text(t(f, "course.home.skipped"))
    f.click_t("course.finish")
    f.wait(f"(await import('/js/stores/learning.js')).learning.progress.lessons['1-comfortable']?.stars === 1", what="one star")
    # The next lesson reminds, gently.
    f.wait_text(t(f, "course.next"))
    f.click_t("course.next")
    f.wait("location.pathname === '/learn/1-match'", what="the next lesson")
    f.click_t("course.continue")
    f.wait_text(t(f, "course.home.reminder"))
    f.shot("reminder")
    # Back to find it for real: a held A3 (played into the mic by loopback). The mic is allowed and
    # the audio running, so it listens at once: the marker on the wide bar, the zoom, then the note.
    f.go("/learn/1-comfortable", "document.querySelector('main h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.go_on()
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening by itself")
    f.js("(await import('/js/creator-audio.js')).hearNotes([{ beat: 0, length: 6, pitch: 57 }], { tempo: 60 })")
    f.wait("['hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="hearing the voice")
    f.shot("hearing")
    f.wait("document.querySelector('[data-listening]')?.dataset.phase === 'holding'", what="holding")
    f.wait("[...document.querySelectorAll('[data-listening] [translate=no]')].some((s) => s.textContent === 'G3')", what="the bar zoomed in, its notes named")
    f.shot("zoomed")
    f.wait("document.querySelector('[data-listening]')?.dataset.phase === 'found'", timeout=15, what="the note found")
    note = f.js("document.querySelector('[data-note]').textContent")
    assert note == "A3", f"found {note}"
    f.wait_text(t(f, "listening.suits.highMiddle"))
    f.shot("found")
    assert f.js(f"(await import('/js/stores/learning.js')).learning.progress.home === 57 || Math.round((await import('/js/stores/learning.js')).learning.progress.home) === 57"), "the note isn't kept"
    # A reload straight into the step: no tap yet, so it waits for one anywhere (no button), then
    # listens. The mic was heard here before: no check this time.
    f.go("/learn/1-comfortable", "document.querySelector('main h1')")
    f.run("sessionStorage.setItem('stackapella.lesson.1-comfortable', '1')")
    f.go("/learn/1-comfortable", "document.querySelector('[data-listening]')")
    f.wait("document.querySelector('[data-listening]')?.dataset.phase === 'waiting'", what="waiting for a tap")
    f.wait_text(t(f, "listening.tapToStart"))
    f.shot("tap-to-start")
    f.press(" ")
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening after the tap")

if __name__ == "__main__":
    run("find_note", journey)
