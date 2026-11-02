"""Honest words about installing (docs/account-nudges-plan.md, F1): on an iPhone the Home Screen app
keeps its own storage, apart from Safari's, so it starts fresh. Nothing promises a guest that
installing keeps their work; there the ask is a free account. Home's card as a guest on an iPhone,
with nothing recorded and with songs only here, and as someone signed in.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
IPHONE = "Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1"
CARD = "document.querySelector('[data-install]')"
# A real iPhone never has Chrome's install offer: the flow's Chrome does, so it's put away.
NO_PROMPT = "(await import('/js/components/install.js')).install.prompt = null;"
# The flow's Chrome showed its offer for a moment, which took the ask ledger's one ask today: cleared.
FRESH_DAY = "localStorage.removeItem('stackapella.asks');"
SONGS = "const { projects } = await import('/js/stores/projects.js'); projects.all = [{ id: 'kept-here', recorded: true, updatedAt: Date.now() }];"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.chrome.send("Emulation.setUserAgentOverride", {"userAgent": IPHONE, "platform": "iPhone"})
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    f.wait("document.querySelector('main')", what="Home")
    f.js("new Promise((r) => setTimeout(r, 600))")
    f.run(NO_PROMPT + FRESH_DAY)
    f.run(f"({CARD} ?? document.querySelector('main')).scrollIntoView({{ block: 'center' }})")
    assert not f.js(f"!!{CARD}"), "a guest on an iPhone with nothing recorded is offered the app"
    f.shot("guest-nothing-recorded")
    f.run(SONGS)
    f.js("new Promise((r) => setTimeout(r, 300))")
    f.run(f"({CARD} ?? document.querySelector('main')).scrollIntoView({{ block: 'center' }})")
    f.wait(f"{CARD}?.dataset.form === 'keep'", what="the songs card asking for an account")
    text = f.js(f"{CARD}.textContent")
    assert t(f, "install.keepSafari") in text, text
    assert "installed app" not in text, "a promise that installing keeps them"
    assert f.js(f"{CARD}.querySelector('[data-install-account]').classList.contains('btn-secondary')"), "Home's own action stays the one primary"
    f.shot("guest-songs")
    f.run(f"{CARD}.querySelector('[data-install-account]').click()")
    f.wait("location.pathname === '/signin' && new URLSearchParams(location.search).get('for') === 'songs'", what="sign-up, for the songs")
    f.wait_text(t(f, "auth.reason.songs"))
    f.run(FRESH_DAY)
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.wait("document.querySelector('main')", what="Home")
    f.js("new Promise((r) => setTimeout(r, 600))")
    f.run(NO_PROMPT)
    f.run(f"({CARD} ?? document.querySelector('main')).scrollIntoView({{ block: 'center' }})")
    f.wait(f"{CARD}?.dataset.form === 'offer'", what="the plain offer, signed in")
    assert t(f, "install.whySafari") in f.js(f"{CARD}.textContent"), "the honest offer on Safari"
    f.shot("signed-in")


if __name__ == "__main__":
    run("honest_install", journey)
