"""Contact us (contract/api.md, support.create): from Settings, a message with what it's about and the
device's details (shown before sending), sent, then the thanks; signed out, an email to reply to is
asked for. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/settings?api=mock&as=member", "document.querySelector('#profile')")
    f.click(t(f, "contact.title"), "a")
    f.wait("location.pathname === '/contact' && document.querySelector('textarea')", what="Contact us")
    # What goes along is a tap away, and the device's details are on for a recording problem.
    f.click(t(f, "contact.seeSent"), "button")
    assert t(f, "contact.context.device") in f.js("document.querySelector('dl').innerText"), "the device's details are shown before sending"
    assert f.js("document.querySelector('button[type=submit]').disabled"), "Send waits for a message"
    f.type("textarea", "The click and my voice drift apart on my iPhone.")
    f.shot("contact")
    f.run("window.mockCalls = [];")
    f.click(t(f, "contact.send"), "button")
    f.wait_text(t(f, "contact.sentTitle", {"email": f.js("(await import('/js/stores/account.js')).account.me.email")}))
    assert "support.create" in f.js("window.mockCalls"), "sent"
    # Signed out: an email to reply to.
    f.go("/contact?api=mock&as=signedOut", "document.querySelector('textarea')")
    assert f.js("!!document.querySelector('input[type=email]:not([readonly])')"), "asks where to reply"
    # An idea leaves the device's details off.
    f.click(t(f, "contact.topic.idea"), "[role=radio]")
    assert f.js("document.querySelector('[role=switch]').getAttribute('aria-checked') === 'false'"), "an idea sends no device details"
    f.type("textarea", "A metronome that follows me.")
    f.wait_text(t(f, "contact.need.email"))
    f.shot("contact-signed-out")


if __name__ == "__main__":
    run("contact", journey)
