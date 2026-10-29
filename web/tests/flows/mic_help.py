"""A blocked mic (Hillel's iPhone, in Find your note): "Let's get your mic working" opens instead
of a line of red, says the mic is blocked, shows this browser's own steps (Safari on iPhone and
Chrome on Android, in Stage dark and Warm light), keeps the raw error under Details, and the moment
the browser says the mic is allowed it closes by itself and Find your note carries on listening.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
DEVICES = {
    "iphone": "Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1",
    "android": "Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36",
}
STEPS = {"iphone": "micHelp.platform.iosSafari", "android": "micHelp.platform.androidChrome"}

# The browser says no (as when the mic is blocked for the site), and its permission can be watched.
BLOCK = r"""(() => {
    window.micState = 'denied';
    window.micStatus = { get state() { return window.micState; }, onchange: null };
    const query = navigator.permissions.query.bind(navigator.permissions);
    navigator.permissions.query = async (d) => (d.name === 'microphone' ? window.micStatus : query(d));
    const real = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
    navigator.mediaDevices.getUserMedia = async (c) => {
        if (window.micState !== 'granted') throw new DOMException('Permission denied', 'NotAllowedError');
        return real(c);
    };
})()"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/learn/1-comfortable", "document.querySelector('main h1')")
    f.run(BLOCK)
    f.js(f"{AUDIO}.unlock()")
    f.go_on()  # it starts listening at once: the blocked mic brings the help
    f.wait("document.querySelector('[data-mic-help]')", what="the mic help, at once")
    f.run("document.querySelector('[role=dialog] [data-mic-help]').closest('[role=dialog]').querySelector('header button').click()")  # the close, its one way out
    f.wait_text(t(f, "listening.noMic"))
    for device, ua in DEVICES.items():
        f.run(f"Object.defineProperty(navigator, 'userAgent', {{ get: () => {json.dumps(ua)}, configurable: true }})")
        for theme in ["stage-dark", "warm-light"]:
            colour, mode = theme.split("-")
            f.run(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps({'mode': mode, 'colour': colour}))}); window.stackapellaTheme.apply();")
            f.click_t("listening.micAgain")
            f.wait("document.querySelector('[data-mic-help]')", what="the mic help")
            cause = f.js("document.querySelector('[data-mic-help]').dataset.cause")
            assert cause == "blocked", f"the cause is {cause}"
            # v2 (77.png): the state in the header, the setup as a sentence, the steps numbered and labelled.
            f.wait(f"document.querySelector('[data-mic-help] ol')?.getAttribute('aria-label') === {json.dumps(t(f, 'micHelp.stepsFor', {'name': t(f, STEPS[device])}))}", what="this device's steps")
            assert f.js("document.querySelector('[role=dialog] header')?.dataset.tone") == "bad", "the header doesn't carry the blocked state"
            assert f.js(f"!document.body.innerText.includes({json.dumps(t(f, 'micHelp.notNow'))})"), "Not now is back"
            assert f.js("!document.querySelector('[role=alert].text-bad')"), "a line of red as well"
            f.shot(f"{device}-{theme}")
            f.run("document.querySelector('[data-mic-help]').closest('[role=dialog]').querySelector('header button').click()")
            f.wait("!document.querySelector('[data-mic-help]')", what="the help closed")
    # Allowed in the browser's settings: the help closes by itself and the screen carries on.
    f.click_t("listening.micAgain")
    f.wait("document.querySelector('[data-mic-help]')", what="the mic help again")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("window.micState = 'granted'; window.micStatus.onchange?.();")
    f.wait("!document.querySelector('[data-mic-help]')", what="the help closing by itself")
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening, now the mic works")
    f.run("localStorage.removeItem('stackapella.appearance'); window.stackapellaTheme.apply();")


if __name__ == "__main__":
    run("mic_help", journey)
