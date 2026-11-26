"""Plus's start-now consent (contract/api.md billing.prices and billing.checkout): a buyer in the EU, EEA or UK may start
Plus at once only by ticking the server's words, which bear on their 14-day right to cancel. A buyer elsewhere sees no
box and goes straight to Checkout. In the EU the box shows the server's words; Get Plus without the tick says to tick
it and opens nothing; ticked, Checkout opens and the tick is sent. Where the server asks though the prices didn't (the
account's own country), its refusal brings the box and the same line, never a dead end.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
PAGE = "/plus?api=mock&as=member"
BOX = "document.querySelector('[data-plus-consent]')"
BUY = "document.querySelector('button.btn-lg')"
WORDS = "I want it to start now, and I understand that I lose my 14-day right to cancel for the time I've already used."


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def spy(f):
    """Checkout's page is never left: what opens, and what billing.checkout was sent."""
    f.run("""const p = await import('/js/stores/plan.js'); window.opened = null; p.stripe.open = (url) => (window.opened = url);
        const { api } = await import('/js/api.js'); const real = api.billing.checkout; window.sent = null;
        api.billing.checkout = (body) => { window.sent = body; return real(body); };""")


def open_page(f):
    f.go(PAGE, "document.querySelector('button.btn-lg')")
    f.wait("(await import('/js/stores/account.js')).account.me?.id && (await import('/js/stores/plan.js')).plan.prices", what="signed in, with the prices")
    f.wait(f"!{BUY}.disabled", what="Get Plus ready")
    spy(f)


def journey(f):
    # Elsewhere (the United States): no box, straight to Checkout.
    f.go(PAGE, "document.querySelector('h1')")
    f.run("sessionStorage.removeItem('stackapella.mockStartNow')")
    open_page(f)
    assert not f.js(f"!!{BOX}"), "the consent box outside the EU, EEA and UK"
    f.run(f"{BUY}.click()")
    f.wait("window.opened?.startsWith('https://checkout.stripe.com/')", what="Checkout, no box")
    assert f.js("window.sent.startNowConsent") is False

    # In the EU: the server's words, ticked before Checkout.
    f.run("sessionStorage.setItem('stackapella.mockStartNow', '1')")
    open_page(f)
    f.wait(f"!!{BOX}", what="the consent box")
    assert f.js(f"{BOX}.textContent.trim()") == WORDS, f.js(f"{BOX}.textContent.trim()")
    assert f.js(f"{BOX}.querySelector('input').checked") is False, "ticked for them"
    f.run(f"{BUY}.click()")
    f.wait_text(t(f, "plus.consentFirst"))
    assert f.js("window.opened") is None and f.js("window.sent") is None, "Checkout without the tick"
    f.run(f"{BOX}.scrollIntoView({{ block: 'center' }})")
    f.shot("eu-tick-first")
    f.run(f"{BOX}.querySelector('input').click()")
    f.wait(f"!document.body.innerText.includes({json.dumps(t(f, 'plus.consentFirst'))})", what="the line gone once ticked")
    f.run(f"{BUY}.click()")
    f.wait("window.opened?.startsWith('https://checkout.stripe.com/')", what="Checkout, ticked")
    assert f.js("window.sent.startNowConsent") is True
    f.run(f"{BOX}.scrollIntoView({{ block: 'center' }})")
    f.shot("eu-ticked")

    # The server asks though the prices didn't: its refusal brings the box.
    f.run("sessionStorage.removeItem('stackapella.mockStartNow')")
    open_page(f)
    assert not f.js(f"!!{BOX}")
    f.run("sessionStorage.setItem('stackapella.mockStartNow', '1')")
    f.run(f"{BUY}.click()")
    f.wait(f"!!{BOX}", what="the box, from the server's refusal")
    f.wait_text(t(f, "plus.consentFirst"))
    assert f.js("window.opened") is None
    f.run(f"{BOX}.querySelector('input').click()")
    f.run(f"{BUY}.click()")
    f.wait("window.opened?.startsWith('https://checkout.stripe.com/')", what="Checkout once ticked")
    f.run("sessionStorage.removeItem('stackapella.mockStartNow')")


if __name__ == "__main__":
    run("plus_consent", journey)
