"""Choir seats, the director's side (docs/choir-seats-plan.md; behind choir_seats), against the mock: a group with
twelve singers who'd need a seat sees the Seats card with the server's price and twelve suggested; Buy seats goes to
Checkout and back, where the card waits for Stripe's webhook and fills; Change seats up leaves seats free, down warns
who loses theirs; Give a seat moves one from a seated singer, three times, and the fourth says the date it can move
again; a payment Stripe is retrying, seats that end and seats that have ended each say so with one way on. Start a
group with the switch on asks for no Plus (plus_teams on too) and says what the choir will pay. A member without a seat
sees one calm line and the group's own song waiting for it, with nothing to buy; so does one the server refuses. In Spanish the card says it in Spanish.
Switched off, none of it shows and Start a group is Plus's, as before.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
GROUP = "/groups/Gp4rQs?api=mock&scenario=&as=plus"
CARD = "document.querySelector('[data-seats]')"
# Teaming up is Plus only while plus_teams is on (the mock's auth.config keeps it off): on in the page, as together_plus does.
TEAMS_ON = "await (await import('/js/stores/account.js')).loadAuthConfig(); (await import('/js/teams.js')).teams.on = true"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def seats(f, state):
    f.run(f"sessionStorage.setItem('stackapella.mockSeats', JSON.stringify({json.dumps(state)}))")


def status(f, want):
    f.wait(f"{CARD}?.dataset.seatsStatus === {json.dumps(want)}", what=f"the seats card, {want}")


def text(f, selector):
    return f.js(f"document.querySelector({json.dumps(selector)})?.textContent.trim() ?? ''")


def shot(f, name):
    """A shot with the toasts that came before it cleared (each said in its own step)."""
    f.run("(await import('/js/components/ui.js')).toasts.list = []")
    f.shot(name)


def press(f, selector):
    f.run(f"document.querySelector({json.dumps(selector)}).click()")


def journey(f):
    f.go(GROUP, "document.querySelector('h1')")
    f.run("sessionStorage.setItem('stackapella.mockAccount', JSON.stringify({ isAdult: true })); sessionStorage.setItem('stackapella.mockChoirSeats', '1');")
    seats(f, {"status": "none", "extra": 11})
    f.go(GROUP, "document.querySelector('[data-seats]')")
    status(f, "none")
    f.wait("document.querySelector('[data-seats-price]')", what="the seat price")
    price = text(f, "[data-seats-price]")
    assert "15" in price and price == t(f, "groups.seats.price", {"price": f.js("(await import('/js/i18n.js')).formatNumber(15, { style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 })"), "min": 10}), price
    assert f.js("document.querySelector('[data-seats-count]').value") == "12", "a seat for each singer who'd need one"
    assert f.js("document.querySelectorAll('[data-seat-tag=needed]').length") == 12 and f.js("document.querySelectorAll('[data-seat-tag=own]').length") == 1, "each singer's seat"
    assert not f.js("!!document.querySelector('[data-seat-give]')"), "no seat to give before any are bought"
    f.run(f"{CARD}.scrollIntoView({{ block: 'start' }})")
    shot(f, "none")
    # In the EU, EEA and UK: the consent to start at once, ticked before Buy seats (the words Plus's page shows).
    f.run("sessionStorage.setItem('stackapella.mockStartNow', '1')")
    f.go(GROUP, "document.querySelector('[data-seats-consent]')")
    assert f.js("document.querySelector('[data-seats-buy]').disabled"), "Buy seats before the tick"
    assert text(f, "[data-seats-consent]") == f.js("(await import('/js/stores/plan.js')).plan.prices.startNowWords.text")
    f.run("document.querySelector('[data-seats-consent] input').click()")
    f.wait("!document.querySelector('[data-seats-buy]').disabled", what="Buy seats once ticked")
    f.run("sessionStorage.removeItem('stackapella.mockStartNow')")
    # Buy: Checkout, then back (the mock's Checkout comes straight back), where the card waits for the webhook.
    f.run("(await import('/js/stores/plan.js')).stripe.open = (url) => location.assign(url);")
    press(f, "[data-seats-buy]")
    f.wait("location.search === ''", what="back from Checkout, the address made plain")
    status(f, "active")
    f.wait_text(t(f, "groups.seats.ready"))
    assert text(f, "[data-seats-used]") == t(f, "groups.seats.used", {"used": 12, "bought": 12}), text(f, "[data-seats-used]")
    assert f.js("document.querySelectorAll('[data-seat-tag=seated]').length") == 12
    # More seats: two free for the next singers.
    press(f, "[data-seats-change]")
    f.wait("document.querySelector('[data-seats-change-form]')", what="Change seats")
    press(f, "[data-seats-more]")
    press(f, "[data-seats-more]")
    press(f, "[data-seats-save]")
    f.wait("document.querySelector('[data-seats-free]')", what="seats free")
    assert text(f, "[data-seats-free]") == t(f, "groups.seats.free", {"count": 2})
    # Fewer than are taken: it says who loses theirs, then two singers need a seat.
    press(f, "[data-seats-change]")
    f.wait("document.querySelector('[data-seats-change-form]')", what="Change seats")
    f.run("const i = document.querySelector('[data-seats-change-form] [data-seats-count]'); i.value = '10'; i.dispatchEvent(new Event('change'));")
    f.wait("document.querySelector('[data-seats-drop]')", what="who loses a seat")
    assert text(f, "[data-seats-drop]") == t(f, "groups.seats.drop", {"count": 2})
    shot(f, "change")
    press(f, "[data-seats-save]")
    f.wait("document.querySelector('[data-seats-waiting]')", what="singers waiting for a seat")
    assert text(f, "[data-seats-used]") == t(f, "groups.seats.used", {"used": 10, "bought": 10})
    assert f.js("document.querySelectorAll('[data-seat-give]').length") == 2, "Give a seat on each who needs one"
    f.run(f"{CARD}.scrollIntoView({{ block: 'start' }})")
    shot(f, "active")
    # Give a seat, three times in 30 days; the fourth says when.
    for moved in range(3):
        name = f.js("document.querySelector('[data-seat-give]').closest('li').querySelector('[translate=no]').textContent")
        press(f, "[data-seat-give]")
        f.wait("document.querySelector('[data-seats-give-form] [data-seats-holder]')", what="whose seat")
        press(f, "[data-seats-give-form] [data-seats-holder] input")
        press(f, "[data-seats-move]")
        f.wait_text(t(f, "groups.seats.moved", {"name": name}))
        f.wait("!document.querySelector('[data-seats-give-form]')", what="the seat moved")
    press(f, "[data-seat-give]")
    f.wait("document.querySelector('[data-seats-give-form] [data-seats-holder]')", what="whose seat")
    press(f, "[data-seats-give-form] [data-seats-holder] input")
    press(f, "[data-seats-move]")
    f.wait("document.querySelector('[data-seats-move-wait]')", what="the date a seat can move again")
    assert "30" in text(f, "[data-seats-move-wait]")
    shot(f, "move-wait")
    # The payment's states, each with one way on.
    for state, hook in (("past_due", "[data-seats-past-due]"), ("canceled", "[data-seats-canceled]")):
        seats(f, {"status": state, "bought": 12, "extra": 11, "holders": []})
        f.go(GROUP, "document.querySelector('[data-seats]')")
        status(f, state)
        f.wait(f"document.querySelector({json.dumps(hook)})", what=state)
        assert f.js("document.querySelectorAll('[data-seats-portal]').length") == 1 and not f.js("!!document.querySelector('[data-seats-change]')"), state
    seats(f, {"status": "lapsed", "bought": 0, "extra": 11})
    f.go(GROUP, "document.querySelector('[data-seats]')")
    status(f, "lapsed")
    assert text(f, "[data-seats-why]") == t(f, "groups.seats.lapsed") and text(f, "[data-seats-buy]") == t(f, "groups.seats.buyAgain")
    f.run(f"{CARD}.scrollIntoView({{ block: 'start' }})")
    shot(f, "lapsed")
    # In Spanish.
    seats(f, {"status": "none", "extra": 11})
    f.go(GROUP + "&lang=es", "document.querySelector('[data-seats-price]')")
    f.wait("document.querySelector('#group-seats')?.textContent.trim() === 'Plazas'", what="the card in Spanish")
    assert "al año" in text(f, "[data-seats-total]"), text(f, "[data-seats-total]")
    f.run(f"{CARD}.scrollIntoView({{ block: 'start' }})")
    shot(f, "spanish")
    # Start a group, without Plus: no Plus moment, the seats line instead.
    f.go("/groups?api=mock&scenario=&as=member&lang=en", "document.querySelector('h1')")
    f.run(TEAMS_ON)
    f.click_t("groups.start")
    f.wait("document.querySelector('[data-seats-start]')", what="the seats line before Start a group")
    assert not f.js("!!document.querySelector('[data-plus-moment]')"), "no Plus moment with seats on"
    assert text(f, "[data-seats-start]") == t(f, "groups.seats.startLine", {"price": t(f, "groups.seats.price", {"price": f.js("(await import('/js/i18n.js')).formatNumber(15, { style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 })"), "min": 10})})
    shot(f, "start")
    # A member without a seat: one calm line, the group's own song waits for it, the library's song still opens; no card.
    f.run("sessionStorage.setItem('stackapella.mockChoirMember', '1'); sessionStorage.setItem('stackapella.mockMemberSeat', 'needed')")
    f.go("/groups/Gp4rQs?api=mock&as=member&lang=en", "document.querySelector('[data-seat-ask]')")
    assert text(f, "[data-seat-ask]") == t(f, "groups.seats.ask", {"name": "Ana Ruiz"}), text(f, "[data-seat-ask]")
    assert f.js("document.querySelectorAll('[data-seat-waiting]').length") == 1, "the group's own song waits for the seat"
    assert f.js("document.querySelectorAll('[data-group-song] button.order-last').length") == 1, "the library song still opens"
    assert not f.js("!!document.querySelector('[data-seats], [data-seat-tag], [data-plus-moment]')"), "a card, tags or anything to buy for a member"
    shot(f, "member-ask")
    # The server says no seat though the page thought otherwise: the same line, never an error.
    f.run("sessionStorage.removeItem('stackapella.mockMemberSeat'); sessionStorage.setItem('stackapella.mockSeatRefused', 'b2c3d4e5-6f70-4182-93a4-b5c6d7e8f901')")
    f.go("/groups/Gp4rQs?api=mock&as=member&lang=en", "document.querySelector('[data-group-song]')")
    f.wait("document.querySelector('[data-seat-ask]') && document.querySelectorAll('[data-seat-waiting]').length === 1", what="the line, from the server's refusal")
    f.run("sessionStorage.removeItem('stackapella.mockSeatRefused'); sessionStorage.removeItem('stackapella.mockChoirMember')")

    # Switched off: no card, no tags, and Start a group is Plus's as before.
    f.run("sessionStorage.removeItem('stackapella.mockChoirSeats')")
    f.go(GROUP, "document.querySelector('[data-group-song]')")
    f.wait_text("Sam Lee")
    assert not f.js("!!document.querySelector('[data-seats], [data-seat-tag]')"), "seats with the switch off"
    f.go("/groups?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.run(TEAMS_ON)
    f.click_t("groups.start")
    f.wait("document.querySelector('[data-plus-moment]')", what="the Plus moment, as before")
    assert not f.js("!!document.querySelector('[data-seats-start]')")


if __name__ == "__main__":
    run("choir_seats", journey)
