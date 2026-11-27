"""Feedback, easy to find and calm to send (docs/feedback-plan.md; js/views/contact.js, js/feedback.js), with the
feedback2 switch on, against the mock:
- a guest on a phone finds Settings on Home (the icon shows there now, named Settings), and Contact us at the top of
  Account and device; the form opens with focus on its heading, no topic chosen, the chips 44 px radios, the email
  optional; sent with no email, the sent card (focused, announced) says we can't reply, and Done goes back;
- an account on a sound or broken topic sees "Sent with it" above Send, turns one item off with Change, and ticks
  "Don't reply to me": sent with reply false, without that item, and Done goes to `from`;
- Back goes to `from` only when it's an app path on this host, else Settings;
- Hear yourself as a choir's failure has "Tell us what happened" from the first failure, with the choir song;
- the choir ask, both ways: "Sounds right" thanks in place and isn't asked again for that song; "Something's off" opens
  the form with the choir song and what's off as chips.
With feedback2 off the form is as before (tests/flows/contact.py)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SENT = "(globalThis.mockBodies ?? []).filter((b) => b.operation === 'support.create')"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def switch_on(f):
    f.go("/?api=mock&scenario=", "document.querySelector('#app header, #app main')")
    f.run("sessionStorage.setItem('stackapella.mockFeedback2', '1'); sessionStorage.setItem('stackapella.mockOwnChoir', '1'); localStorage.removeItem('stackapella.choirAsk'); sessionStorage.removeItem('stackapella.mockChoir');")


def last_sent(f):
    return f.js(f"JSON.parse(JSON.stringify({SENT}.at(-1)?.body ?? null))")


def journey(f):
    switch_on(f)

    # A guest: Settings from Home, on a phone too, named Settings.
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('[data-guest-settings]')")
    assert f.js("document.querySelector('[data-guest-settings]').getClientRects().length > 0"), "the guest's Settings isn't on screen"
    assert f.js("document.querySelector('[data-guest-settings]').getAttribute('aria-label')") == t(f, "settings.title")
    assert f.js("document.querySelector('[data-guest-settings]').getBoundingClientRect().height") >= 44, "a 44 px target"
    f.no_sideways("Home, a guest")
    f.shot("home-guest")
    f.run("document.querySelector('[data-guest-settings]').click()")
    f.wait("location.pathname === '/settings' && document.querySelector('[data-contact-top]')", what="Contact us in Settings")
    # The top of Account and device: right under its heading, and only once.
    assert f.js("document.querySelector('#settings-account-heading').nextElementSibling === document.querySelector('[data-contact-top]')"), "not first in the group"
    assert f.js("document.querySelectorAll('[data-contact-row]').length") == 1, "Contact us twice"
    f.run("document.querySelector('[data-contact-top]').scrollIntoView({ block: 'center' })")
    f.shot("settings-card")
    f.run("document.querySelector('[data-contact-top]').click()")
    f.wait("location.pathname === '/contact' && document.querySelector('[data-contact-message]')", what="Contact us")
    assert f.js("document.activeElement?.tagName") == "H1", "focus on the heading at open"
    assert f.js("!document.querySelector('[data-topics] [aria-checked=true]')"), "a topic chosen for them"
    heights = f.js("[...document.querySelectorAll('[data-topic]')].map((c) => c.getBoundingClientRect().height)")
    assert all(h >= 44 for h in heights), heights
    assert f.js("document.querySelector('[data-topic]').getAttribute('role')") == "radio"
    assert not f.js("document.querySelector('[data-contact-email]').required"), "the email is optional"
    assert t(f, "contact.emailOptional") in f.js("document.querySelector('main').innerText")
    assert t(f, "contact.sentNothingGuest") in f.js("document.querySelector('[data-sent-with]').innerText"), "an idea-or-nothing message says only it goes"
    f.no_sideways("Contact us, a guest")
    f.shot("form-guest")
    # A typed email that isn't one is said; empty is fine.
    f.type("[data-contact-message]", "The alto is wrong in bar 12 of Silent Night.")
    f.type("[data-contact-email]", "not-an-email")
    f.wait_text(t(f, "contact.need.validEmail"))
    f.type("[data-contact-email]", "")
    f.wait("!document.querySelector('[data-contact-send]').disabled", what="Send ready without an email")
    f.run("globalThis.mockBodies = []; document.querySelector('[data-contact-send]').click()")
    f.wait("document.querySelector('[data-contact-sent]')", what="the sent card")
    assert f.js("document.activeElement === document.querySelector('[data-contact-sent]')"), "the sent card takes focus"
    assert f.js("document.querySelector('[data-contact-sent]').getAttribute('role')") == "status"
    assert f.js("!!document.querySelector('[data-cant-reply]')"), "no email: we can't reply"
    body = last_sent(f)
    assert body["moment"] == "settings" and "email" not in body and "category" not in body and "context" not in body, body
    f.shot("sent-guest")
    f.run("document.querySelector('[data-contact-done]').click()")
    f.wait("location.pathname === '/settings'", what="Done back to Settings")

    # An account, Something's broken: Sent with it above Send, one item off, Don't reply to me; Done to `from`.
    f.go("/contact?from=/settings/reminders&moment=help&api=mock&scenario=&as=member", "document.querySelector('[data-contact-message]')")
    f.wait("document.querySelector('[data-no-reply]')", what="the account loaded")
    assert f.js("document.querySelector('[data-no-reply]').closest('div.space-y-1').querySelector('input[type=email]').readOnly"), "the account's email, read-only, with Don't reply to me"
    f.run("document.querySelector('[data-topic=broken]').click()")
    f.wait("document.querySelector('[data-sent-summary]')", what="Sent with it")
    summary = f.js("document.querySelector('[data-sent-summary]').textContent")
    kinds = [t(f, f"contact.device.{kind}") for kind in ("phone", "tablet", "computer")]
    assert t(f, "contact.summary.page") in summary and any(k in summary for k in kinds), summary
    assert f.js("document.querySelector('[data-sent-with]').compareDocumentPosition(document.querySelector('[data-contact-send]')) & Node.DOCUMENT_POSITION_FOLLOWING"), "Sent with it isn't above Send"
    f.run("document.querySelector('[data-sent-change]').click()")
    f.wait("document.querySelector('[data-item=device]')", what="the list")
    assert "Mozilla" not in f.js("document.querySelector('[data-diag-details]').innerText"), "the user agent is shown"
    f.run("document.querySelector('[data-item=device]').click(); document.querySelector('[data-no-reply] input').click();")
    f.type("[data-contact-message]", "Reminders didn't save when I changed the time.")
    f.shot("form-account")
    f.run("globalThis.mockBodies = []; document.querySelector('[data-contact-send]').click()")
    f.wait("document.querySelector('[data-contact-sent]')", what="sent")
    body = last_sent(f)
    assert body["reply"] is False and body["category"] == "bug" and body["moment"] == "help", body
    ctx = body["context"]
    assert ctx["page"] == "/settings/reminders" and "device" not in ctx and "browser" not in ctx and ctx["skin"] in ("game", "serious") and ctx["language"], ctx
    assert t(f, "contact.sentDone") in f.js("document.querySelector('[data-contact-sent]').innerText"), "Don't reply to me: no address in the thanks"
    f.run("document.querySelector('[data-contact-done]').click()")
    f.wait("location.pathname === '/settings/reminders'", what="Done to from")

    # Back: to `from` when it's an app path here, else Settings.
    f.go("/contact?from=//evil.example/x&api=mock&scenario=&as=member", "document.querySelector('[data-contact-message]')")
    f.run("document.querySelector('[data-header-btn]').click()")
    f.wait("location.pathname === '/settings' && location.host === new URL(document.baseURI).host", what="Back to Settings, never another site")

    # Hear yourself as a choir's failure: Tell us what happened from the first failure, with the choir song.
    f.go("/t/amazing-grace/choir?api=mock&scenario=&as=member", "document.querySelector('[data-choir-failed]')")
    f.wait("document.querySelector('[data-choir-tell-us]')", what="Tell us what happened")
    href = f.js("document.querySelector('[data-choir-tell-us]').getAttribute('href')")
    assert "moment=own_choir" in href and "topic=broken" in href and "choir=" in href and "from=%2Ft%2Famazing-grace%2Fchoir" in href, href
    f.shot("choir-failed")
    f.run("document.querySelector('[data-choir-tell-us]').click()")
    f.wait("document.querySelector('[data-topic=broken][aria-checked=true]')", what="the form on Something's broken")
    assert t(f, "contact.context.choir") in f.js("document.querySelector('[data-sent-summary]').textContent"), "the choir song goes along"

    # The choir ask: "Sounds right" thanks in place, and isn't asked again for this song.
    f.go("/t/silent-night/choir?api=mock&scenario=&as=member", "document.querySelector('[data-choir-play]')")
    assert not f.js("document.querySelector('[data-choir-ask]')"), "asked before hearing it"
    f.run("(await import('/js/audio.js')).unlock(); document.querySelector('[data-choir-play]').click()")
    f.wait("document.querySelector('[data-choir-ask]')", timeout=20, what="the choir ask")
    f.run("document.querySelector('[data-choir-play]').click()")
    sizes = f.js("[...document.querySelectorAll('[data-choir-ask] button')].map((b) => [b.className, Math.round(b.getBoundingClientRect().width)])")
    assert len(sizes) == 2 and sizes[0][0] == sizes[1][0], f"two equal buttons: {sizes}"
    assert not f.js("document.querySelector('[data-choir-ask]').innerText.includes(" + json.dumps(t(f, "common.notNow")) + ")"), "a Not now"
    f.no_sideways("the choir ask")
    f.shot("choir-ask")
    f.run("document.querySelector('[data-choir-ask-right]').click()")
    f.wait("document.activeElement === document.querySelector('[data-choir-ask-thanks]')", what="the thanks, focused")
    f.go("/t/silent-night/choir?api=mock&scenario=&as=member", "document.querySelector('[data-choir-play]')")
    f.run("(await import('/js/audio.js')).unlock(); document.querySelector('[data-choir-play]').click()")
    f.js("new Promise((r) => setTimeout(r, 1500))")
    assert not f.js("document.querySelector('[data-choir-ask]')"), "asked again for the same song"
    f.run("document.querySelector('[data-choir-play]').click()")

    # "Something's off": the form with the choir song and what's off.
    f.run("localStorage.removeItem('stackapella.choirAsk')")
    f.go("/t/silent-night/choir?api=mock&scenario=&as=member", "document.querySelector('[data-choir-play]')")
    f.run("(await import('/js/audio.js')).unlock(); document.querySelector('[data-choir-play]').click()")
    f.wait("document.querySelector('[data-choir-ask-off]')", timeout=20, what="the choir ask again, the list cleared")
    f.run("document.querySelector('[data-choir-play]').click(); document.querySelector('[data-choir-ask-off]').click()")
    f.wait("location.pathname === '/contact' && document.querySelector('[data-choir-issues]')", what="the form with what's off")
    assert f.js("document.querySelectorAll('[data-issue]').length") == 4
    f.run("document.querySelector('[data-issue=timing]').click()")
    f.type("[data-contact-message]", "The tenor comes in late.")
    f.shot("choir-off")
    f.run("globalThis.mockBodies = []; document.querySelector('[data-contact-send]').click()")
    f.wait("document.querySelector('[data-contact-sent]')", what="sent")
    body = last_sent(f)
    assert body["moment"] == "choir_ask" and body["context"]["choirIssue"] == "timing" and body["context"].get("choirRequestId"), body
    f.run("document.querySelector('[data-contact-done]').click()")
    f.wait("location.pathname === '/t/silent-night/choir'", what="Done back to the choir")
    f.run("sessionStorage.removeItem('stackapella.mockFeedback2'); sessionStorage.removeItem('stackapella.mockOwnChoir'); localStorage.removeItem('stackapella.choirAsk');")


if __name__ == "__main__":
    run("feedback", journey)
