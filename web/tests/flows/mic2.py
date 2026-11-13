"""Experiment mic2's arm b (docs/improvement-log.md row 11; the first-week UX review, 2026-11-12), previewed with ?x=:
lesson 1 opens on Hear it first (one tap plays four voices, then the chord with a gap, "That gap is for your voice"), one
"oo" tip, Turn on my mic with the privacy line at body size, and a quiet "Can't sing right now?" offering Listen through
it or a way back later. A refusal is a choice ("No problem", Listen through it, the steps behind Show me how), and Skip
shows on finding your note only once the mic has failed. Arm a is today's lesson 1; an app's own browser never sees b.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
# The browser says no to the mic.
BLOCK = r"""(() => {
    const query = navigator.permissions.query.bind(navigator.permissions);
    navigator.permissions.query = async (d) => (d.name === 'microphone' ? { state: 'prompt', onchange: null } : query(d));
    navigator.mediaDevices.getUserMedia = async () => { throw new DOMException('Permission denied', 'NotAllowedError'); };
})()"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


# A first visit: the browser hasn't been asked about the mic yet (the harness's Chrome allows it up front).
NOT_ASKED = r"""(() => {
    const query = navigator.permissions.query.bind(navigator.permissions);
    navigator.permissions.query = async (d) => (d.name === 'microphone' ? { state: 'prompt', onchange: null } : query(d));
})()"""


def fresh(f, arm="b"):
    """Lesson 1 from its first step (a lesson keeps its step for the tab), in `arm`."""
    f.run("sessionStorage.removeItem('stackapella.lesson.1-comfortable')")
    f.go(f"/learn/1-comfortable?x=mic2-{arm}", "document.querySelector('main h1')")


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": NOT_ASKED})
    # Arm b, a guest's first visit.
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    fresh(f)
    f.wait("document.querySelector('[data-hear-first]')", what="Hear it first")
    assert f.js("document.querySelector('main h1')?.textContent") == t(f, "mic2.title"), "Hear it first's heading"
    assert f.js("!document.querySelector('[data-chord-promise]') && !document.querySelector('main ol.space-y-2\\\\.5')"), "the reading screen's promise and tips are gone"
    assert t(f, "mic2.tip") in f.js("document.querySelector('[data-mic2-tip]').textContent"), "the one tip"
    why = f.js("document.querySelector('[data-mic-why]')")
    assert why is not None, "the privacy line"
    size = f.js("parseFloat(getComputedStyle(document.querySelector('[data-mic-why]')).fontSize)")
    assert size >= 15, f"the privacy line at body size: {size}px"
    assert f.js("!!document.querySelector('[data-mic-why] svg')"), "its lock"
    assert f.js("!document.querySelector('[data-skip], [data-home-skip]')"), "no Skip beside the ask"
    f.shot("hear-first")
    f.no_sideways("Hear it first")

    # One tap: the chord, then the gap, then the line.
    f.run("document.querySelector('[data-hear-first-play]').click()")
    f.wait("document.querySelector('[data-hear-first]').dataset.playing === 'true'", what="playing at once")
    f.wait("document.querySelector('[data-hear-first]').dataset.heard === 'true'", timeout=20, what="the chord and its gap heard")
    assert f.js("document.querySelector('[data-hear-first-line]').textContent") == t(f, "mic2.gapLine")
    f.shot("heard")

    # Can't sing right now?: Listen through it, or a way back later.
    f.run("document.querySelector('[data-cant-sing-open]').click()")
    f.wait("document.querySelector('[data-cant-sing] [data-cant-sing-listen]')", what="the two ways")
    assert f.js("!!document.querySelector('[data-cant-sing-link], [data-cant-sing-calendar]')"), "a way back later"
    f.shot("cant-sing")

    # A refusal, as a choice: No problem, Listen through it, the steps behind Show me how; then Skip shows.
    fresh(f)
    f.wait("document.querySelector('[data-hear-first]')", what="Hear it first")
    f.run(BLOCK)
    f.js(f"{AUDIO}.unlock()")
    f.go_on()
    f.wait("document.querySelector('[data-mic-refused]')", what="No problem, not an error")
    assert f.js("document.querySelector('[role=dialog] h2, [role=dialog] header')?.textContent").strip().startswith(t(f, "mic2.refused.title")), "the refusal's title"
    assert f.js("!document.querySelector('[data-mic-help] ol')"), "the steps wait behind Show me how"
    f.shot("refused")
    f.run("document.querySelector('[data-mic-refused-how]').click()")
    f.wait("document.querySelector('[data-mic-help] ol')", what="the browser's steps, on asking")
    f.run("document.querySelector('[role=dialog] header button').click()")
    f.wait("!document.querySelector('[data-mic-help]')", what="the help closed")
    f.wait("document.querySelector('[data-home-skip]')", what="Skip, once the mic has failed")
    # Listen through it from the refusal itself.
    fresh(f)
    f.wait("document.querySelector('[data-hear-first]')", what="Hear it first")
    f.run(BLOCK)
    f.js(f"{AUDIO}.unlock()")
    f.go_on()
    f.wait("document.querySelector('[data-mic-refused-listen]')", what="Listen through it in the refusal")
    f.run("document.querySelector('[data-mic-refused-listen]').click()")
    f.wait("!document.querySelector('[data-mic-help]') && document.querySelector('[data-cant-sing] [data-hear-first]')", what="the chord with the practice voice in your place")
    f.run("document.querySelector('[data-cant-sing] [data-hear-first-play]').click()")
    f.wait("document.querySelector('[data-cant-sing] [data-hear-first]').dataset.heard === 'true'", timeout=20, what="heard through")
    assert f.js("document.querySelector('[data-cant-sing] [data-hear-first-line]').textContent") == t(f, "mic2.filledLine")
    f.js("document.querySelector('[data-cant-sing]').scrollIntoView({ block: 'center' })")
    f.shot("listened")
    f.no_sideways("Listen through it")

    # Arm a: today's lesson 1, no Hear it first.
    fresh(f, "a")
    f.wait("document.querySelector('[data-step-line]')", what="lesson 1 as written")
    assert f.js("!document.querySelector('[data-hear-first]')"), "arm a shows Hear it first"

    # An app's own browser: never arm b (its in-app card, as in both arms).
    ua = "Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 350.0.0"
    fresh(f)
    f.run(f"Object.defineProperty(navigator, 'userAgent', {{ get: () => {ua!r}, configurable: true }}); const {{ navigate }} = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    f.wait("document.querySelector('[data-in-app]')", what="the in-app card")
    assert f.js("!document.querySelector('[data-hear-first]')"), "an app's browser in arm b"
    # Android: Open in Chrome, an intent for this same page.
    android = "Mozilla/5.0 (Linux; Android 14; Pixel 8 Build/AP2A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/129.0.0.0 Mobile Safari/537.36 Instagram 350.0.0"
    f.run(f"Object.defineProperty(navigator, 'userAgent', {{ get: () => {android!r}, configurable: true }}); const {{ navigate }} = await import('/js/router.js'); navigate('/learn'); await new Promise((r) => setTimeout(r, 300)); navigate('/learn/1-comfortable');")
    f.wait("document.querySelector('[data-in-app-chrome]')?.getAttribute('href').startsWith('intent://')", what="Open in Chrome")
    f.shot("in-app-android")


if __name__ == "__main__":
    run("mic2", journey)
