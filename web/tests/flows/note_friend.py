"""Lesson 1's note card asks "What's yours?" (docs/improvement-log.md, row 4): the card's link opens the course's first
lesson tagged share-note with the sender's note; a friend landing there is asked for theirs with the sender's beside it,
and at the end sees both. A tone over loopback stands in for a voice, as in the course flow. Without ?their=, lesson 1
is as it was.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # Lesson 1 as before, for anyone not sent by a friend: no line.
    f.go("/learn/1-comfortable?api=mock&scenario=", "document.querySelector('main h1')")
    f.run("sessionStorage.removeItem('stackapella.friendNote')")
    f.wait_text(t(f, "chordMoment.promise"))
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.go_on()
    f.wait("document.querySelector('[data-listening]')", what="lesson 1 listening")
    assert not f.js("!!document.querySelector('[data-friend-note]')"), "no friend's line without a friend"

    # A friend's link: asked for theirs, the sender's note beside it.
    f.go("/learn/1-comfortable?api=mock&scenario=&ref=share-note&their=C4", "document.querySelector('main h1')")
    assert f.js("localStorage.getItem('stackapella.ref')") == "share-note", "the arrival's tag"
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    # The lesson may open on its first step again, or where it was left (the note step): on to the note either way.
    f.wait("document.querySelector('[data-go-on]:not(:disabled), [data-listening]')", what="lesson 1 open")
    if not f.js("!!document.querySelector('[data-listening]')"):
        f.go_on()
    f.wait(f"document.querySelector('[data-friend-note]')?.textContent.trim() === {json.dumps(t(f, 'course.friendNote.ask', {'note': 'C4'}))}", what="the friend's ask")
    f.no_sideways("the friend's lesson 1")
    f.shot("ask")
    # Reopened at the note step, it waits for a tap to listen (the first hum after a page load).
    f.run("if (document.querySelector('[data-listening]')?.dataset.phase === 'waiting') document.dispatchEvent(new PointerEvent('pointerdown'))")
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening")
    f.run(f"const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js'); engine.preview(renderPart([{{ beat: 0, length: 6, pitch: 57 }}], {{ bpm: 60, sampleRate: engine.sampleRate }}), engine.sampleRate);")
    f.wait("document.querySelector('[data-note]')?.textContent === 'A3'", timeout=20, what="the note found")
    f.click_t("course.finish")
    f.wait(f"document.querySelector('[data-friend-compare]')?.textContent.trim() === {json.dumps(t(f, 'course.friendNote.both', {'theirs': 'C4', 'yours': 'A3'}))}", what="both notes at the end")
    f.shot("both")

    # Their own card asks the next friend, its link tagged and carrying their note.
    f.run("navigator.canShare = () => false")
    f.click_t("results.share.note")
    f.wait("document.querySelector('[data-share-result] img')", what="the note's card")
    link = f.js("document.querySelector('[data-share-result]').dataset.shareLink")
    assert link.endswith("/learn/1-comfortable?ref=share-note&their=A3"), link
    f.js("new Promise((r) => setTimeout(r, 900))")
    f.shot("card")


run("note_friend", journey)
