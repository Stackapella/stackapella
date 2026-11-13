"""Send diagnostics (the finish-line audit, 2026-11-13; contract/api.md support.create, context.audioErrors): when the mic
fails, its help offers Send diagnostics, which opens the contact form about sound, with what goes along shown open: the
device's details and this visit's sound problems, each line as it's sent. It goes as `diagnostics` with the lines, only
while the details are on; switched off, nothing goes along. Back returns to the screen it came from. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
FAIL = "{ name: 'NotReadableError', message: 'Could not start audio source' }"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/learn?api=mock&as=member", "document.querySelector('#app main')")
    # The mic fails as openMic notes it, and its help opens.
    f.run(f"sessionStorage.removeItem('stackapella.soundLog'); const e = {FAIL}; (await import('/js/sound-log.js')).noteSound('mic', e); (await import('/js/mic-help.js')).showMicHelp(e);")
    f.wait("document.querySelector('[data-send-diagnostics]')", what="Send diagnostics in the mic's help")
    f.shot("mic-help")
    f.run("document.querySelector('[data-send-diagnostics]').click()")
    f.wait("location.pathname === '/contact' && document.querySelector('textarea')", what="the diagnostics form")
    assert not f.js("!!document.querySelector('[data-mic-help]')"), "the mic's help closed"
    assert f.js("document.querySelector('h1')?.innerText") == t(f, "contact.diagnostics.title"), f.js("document.querySelector('h1')?.innerText")
    assert not f.js("!!document.querySelector('[role=radiogroup]')"), "no topics to choose: it's about sound"
    # What goes along, open before anything's typed: the device and the sound problem, word for word.
    sent = f.js("document.querySelector('#contact-sent')?.innerText ?? ''")
    assert t(f, "contact.context.device") in sent and "/learn mic: NotReadableError Could not start audio source" in sent, sent
    f.type("textarea", "The mic stopped as I pressed Sing.")
    f.no_sideways("Send diagnostics")
    f.shot("diagnostics")
    f.run("window.mockBodies = [];")
    f.click(t(f, "contact.send"), "button[type=submit]")
    f.wait("(window.mockBodies ?? []).some((b) => b.operation === 'support.create')", what="the message sent")
    body = f.js("window.mockBodies.find((b) => b.operation === 'support.create').body")
    assert body["category"] == "diagnostics", body
    assert any(line.endswith("/learn mic: NotReadableError Could not start audio source") for line in body["context"]["audioErrors"]), body
    assert set(body["context"]) <= {"appVersion", "browser", "device", "page", "timingCheck", "audioErrors"}, f"nothing else goes along: {list(body['context'])}"
    assert body["context"]["page"] == "/learn", body["context"]
    # Switched off, nothing goes along; and nothing noted on this visit is said so.
    f.go("/contact?api=mock&as=member&diagnostics=1&from=/learn", "document.querySelector('textarea')")
    f.run("sessionStorage.removeItem('stackapella.soundLog')")
    f.go("/contact?api=mock&as=member&diagnostics=1&from=/learn", "document.querySelector('textarea')")
    assert t(f, "contact.diagnostics.noSound") in f.js("document.querySelector('#contact-sent').innerText"), "says none were noted"
    f.run("document.querySelector('[role=switch]').click()")
    f.type("textarea", "No sound at all.")
    f.run("window.mockBodies = [];")
    f.click(t(f, "contact.send"), "button[type=submit]")
    f.wait("(window.mockBodies ?? []).some((b) => b.operation === 'support.create')", what="the message sent without details")
    body = f.js("window.mockBodies.find((b) => b.operation === 'support.create').body")
    assert "context" not in body and body["category"] == "diagnostics", body
    # Back goes where it came from.
    f.go("/contact?api=mock&as=member&diagnostics=1&from=/learn", "document.querySelector('textarea')")
    f.click(t(f, "common.back"), "a, button")
    f.wait("location.pathname === '/learn'", what="back to the lesson map")


run("contact_diagnostics", journey)
