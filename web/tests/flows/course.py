"""M3, the course: the home's Start singing opens unit 1's first lesson at once; the learner's comfortable
note is found (a tone over loopback stands in for singing); the lesson ends with its stars and XP;
the next lesson's Listen, Try (Wait for me) and Sing it steps run, Lower and Higher move its key, and the scored step says when it couldn't
hear (it earns nothing then, and costs nothing); the home's course band marks the next lesson on the unit's path; the map shows the stars.
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
    f.go("/", "document.querySelector('#app header')")
    f.wait_text(t(f, "course.start"))
    f.click_t("course.start")  # straight in: no questions first
    f.wait("location.pathname === '/learn/1-comfortable'", what="the first lesson")
    f.wait_text(t(f, "chordMoment.promise"))  # the chord moment, promised before the mic
    f.shot("learn")
    # Your comfortable note: a steady tone over loopback stands in for a voice. It listens at once
    # (the first voice is the mic check as well).
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.go_on()
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening at once (the first hum is the mic check too)")
    f.run(f"const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js'); engine.preview(renderPart([{{ beat: 0, length: 6, pitch: 57 }}], {{ bpm: 60, sampleRate: engine.sampleRate }}), engine.sampleRate);")
    # Live: the voice glides on the bar, then the note found shows big.
    f.wait("document.querySelector('[data-note]')?.textContent === 'A3'", timeout=20, what="the note found")
    f.click_t("course.finish")
    # The note's own end (ux-spec 5.8a): the note big, the keyboard round it, the stars and the XP.
    f.wait("document.querySelector('[data-note-result]')", what="the note result")
    if f.skin == "game":
        f.wait("document.querySelector('canvas[data-confetti=\"3\"]')", timeout=2, what="the confetti for three new stars")
        f.wait_text(t(f, "results.xp", {"xp": 25}))
    else:
        # Serious mode (docs/skins-plan.md): no confetti, no stars, no XP: the note itself.
        f.js("new Promise((r) => setTimeout(r, 800))")
        assert not f.js("document.querySelector('canvas[data-confetti]')"), "confetti in serious mode"
        assert t(f, "results.xp", {"xp": 25}) not in f.js("document.body.innerText"), "XP in serious mode"
    f.wait_text(t(f, "results.note.highMiddle", {"note": "A3"}))
    assert f.js("document.querySelector('[data-note-result] [data-note]').textContent") == "A3", "the note"
    # Your note in a chord (onboarding item 3), in both skins: the end of the held note with two
    # guide voices, played once by itself after the stars, the lanes lit, then what it means.
    f.wait("document.querySelector('[data-chord-moment]')?.dataset.playing === 'true'", what="the chord moment, playing by itself")
    f.wait("document.querySelector('[data-chord-line]')", timeout=10, what="the chord played to its end")
    assert f.js("JSON.parse(localStorage.getItem('stackapella.funnel')).firsts.f_chord") is True, "the chord moment isn't counted"
    f.shot("done")
    # Share my note (onboarding item 6): the note's card, with a name if you type one (kept on this device).
    f.run("navigator.canShare = () => false")  # Download, not the device's share sheet (which needs a person)
    f.click_t("results.share.note")
    f.wait("document.querySelector('[data-share-result] img')", what="the note's card")
    f.run("const input = document.querySelector('[data-share-name] input'); input.value = 'Maya'; input.dispatchEvent(new Event('input'));")
    f.wait("document.querySelector('[data-share-result] img')?.alt && document.querySelector('[data-share-result] img')", what="the card again, with the name")
    f.js("new Promise((r) => setTimeout(r, 900))")
    f.shot("note-card")
    f.click_t("results.share.download")
    f.wait("localStorage.getItem('stackapella.shareName') === 'Maya' && JSON.parse(localStorage.getItem('stackapella.funnel')).firsts.f_name", what="the name kept, and counted")
    f.press("Escape")
    f.click_t("course.next")
    f.wait("location.pathname === '/learn/1-match'", what="the next lesson")
    # The lane before anything is pressed: its notes spread across it (they once sat squashed in a
    # corner until Play redrew them).
    f.wait("document.querySelector('[role=img] .absolute.flex')", what="the lane's notes")
    spread = f.js("(() => { const lane = document.querySelector('[role=img] .absolute.flex').parentElement; const box = lane.getBoundingClientRect(); const right = Math.max(...[...lane.querySelectorAll('.absolute.flex')].map((n) => n.getBoundingClientRect().right)); return (right - box.left) / box.width; })()")
    assert spread > 0.8, f"the lane's notes are squashed: they reach {spread:.0%} across"
    f.shot("lane-first-load")
    f.click_t("course.tap.listen")
    f.wait(f"{AUDIO}.engine.playing", what="hearing it")
    f.wait(f"!{AUDIO}.engine.playing", timeout=20, what="heard")
    f.click_t("course.continue")
    f.click_t("course.tap.try")
    # Wait for me: each note plays and waits until it's found. Never a headphones question here (nothing
    # sounds under you, onboarding review 2, 8): unanswered, each note plays a moment then goes quiet.
    f.js("new Promise((r) => setTimeout(r, 800))")
    assert not f.js("!!document.querySelector('[data-headphones]')"), "no headphones question in Wait for me"
    # The Wait for me view (80.png): the note's pills, its bar, and no Stop.
    f.wait("document.querySelector('[data-wait-lane]') && document.querySelector('[data-wait-pills]')", what="Wait for me's lane and pills")
    f.wait("document.querySelectorAll('[data-wait-pills] li svg').length >= 1", what="a note found (its pill ticked)")
    f.shot("wait-for-me")
    f.wait_text(t(f, "course.wait.done"), timeout=30)  # every note found: it ends by itself
    f.click_t("course.continue")
    # Not in your range? (128.png): Lower moves this lesson's exercises a semitone, kept with the
    # course's progress; Higher puts it back.
    shown = "document.querySelector('[data-key-control] [role=status]').textContent"
    before = f.js(shown)
    f.run("document.querySelectorAll('[data-key-control] button')[0].click()")  # Lower
    f.wait(f"{shown} !== {before!r}", what="Lower moved the key")
    f.wait("(async () => { const { learning } = await import('/js/stores/learning.js'); return learning.progress.keys?.[location.pathname.split('/').pop()] === -1; })()", what="the key kept for this lesson")
    f.shot("key-lower")
    f.run("document.querySelectorAll('[data-key-control] button')[1].click()")  # Higher
    f.wait(f"{shown} === {before!r}", what="Higher put it back")
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the turn")
    # Each note sits where it's drawn: a sung note (a glass gem, drawn under its words) on the same row as the guide note it
    # echoes, at its own beat (Hillel's challenge screenshot had them fall into a staircase).
    places = f.js("""(() => {
        const lane = document.querySelector('.lane-stage'); const box = lane.getBoundingClientRect();
        return [...lane.querySelectorAll(':scope > [data-lane-note]')].filter((e) => e.style.top).map((e) => {
            const r = e.getBoundingClientRect();
            return { gem: e.dataset.laneNote === 'sung', position: getComputedStyle(e).position, top: Math.round(r.top - box.top - parseFloat(e.style.top)), left: Math.round(r.left - box.left - parseFloat(e.style.left)), y: Math.round(r.top - box.top) };
        });
    })()""")
    assert all(p["position"] == "absolute" and abs(p["top"]) <= 1 and abs(p["left"]) <= 1 for p in places), f"a note isn't where it's drawn: {places}"
    guide = [p["y"] for p in places if not p["gem"]]
    sung = [p["y"] for p in places if p["gem"]]
    # The lane draws a turn once (the labelled lane): the notes you sing back are the ones you heard, so no separate guide row.
    assert sung and (not guide or guide == sung), f"the sung notes aren't on their guide notes' rows: {guide} vs {sung}"
    assert not f.js("document.querySelector('.lane-stage .lane-speck')"), "the drifting specks are back"
    f.shot("sing-it")
    f.wait_text(t(f, "course.notHeard"), timeout=40)  # loopback hears the guide, but nobody in the turn
    f.click_t("course.finish")
    # Couldn't hear: no stars and no XP, and nothing lost.
    # Couldn't hear you (game mode asks "Continue?" instead).
    f.wait(f"document.body.innerText.includes({t(f, 'course.done.unheardTitle')!r}) || document.body.innerText.includes({t(f, 'game.done.continue')!r})", what="couldn't hear")
    assert not f.js("(await import('/js/stores/learning.js')).learning.progress.lessons['1-match']"), "an unheard lesson was recorded"
    f.click_t("course.next")
    f.wait("location.pathname === '/learn/1-high-low'", what="on to the third lesson")
    f.go("/", "document.querySelector('#app header')")
    f.wait(f"document.querySelector('[data-press-start]') || document.body.innerText.includes({t(f, 'course.continue')!r})", what="Continue (Press start in game mode's title screen)")
    f.wait("document.querySelector('[aria-current=step]')", what="the unit's path, the next lesson marked")
    f.click_t("home.lead.map")
    f.wait("location.pathname === '/learn' && document.querySelectorAll('main ol button, ol button').length > 5", what="the map")
    f.shot("map")
    # Settings: Start the course over (asked once), then Undo puts the lessons and stars back.
    lessons = "Object.keys((await import('/js/stores/learning.js')).learning.progress.lessons).length"
    had = f.js(lessons)
    assert had > 0, "no lesson done to start over from"
    f.go("/settings", "document.querySelector('[data-restart]')")
    # (The button waits for the course to load, and Undo replaces it once the restart is done.)
    start_over = t(f, "settings.course.startOver")
    undo = t(f, "common.undo")
    f.wait(f"[...document.querySelectorAll('[data-restart] button')].some((b) => b.textContent.trim() === {start_over!r} && !b.disabled)", what="Start the course over, ready")
    f.run("document.querySelector('[data-restart] button').click()")
    f.wait_text(t(f, "settings.course.confirmTitle"))
    f.click(start_over, selector="[role=dialog] button")
    f.wait(f"{lessons} === 0", what="the course started over")
    f.wait("(await import('/js/stores/learning.js')).learning.progress.home !== null", what="the note kept")
    f.wait(f"[...document.querySelectorAll('[data-restart] button')].some((b) => b.textContent.trim() === {undo!r} && !b.disabled)", what="Undo, ready")
    f.run("document.querySelector('[data-restart] button').click()")
    f.wait(f"{lessons} === {had}", what="Undo put the lessons back")


if __name__ == "__main__":
    run("course", journey)
