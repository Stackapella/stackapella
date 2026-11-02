"""The licence's credit (LICENSE, clause 2, the Attribution Assurance License): a self-hosted install
shows "Stackapella, by Hillel Coren · stackapella.com" where everyone sees it on opening the app: a banner
at the top of whichever screen it opens on (until the first move), a line at the foot of Home and the
sign-in screen, and Settings' own line. stackapella.com itself shows none of them.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

ACCOUNT = "(await import('/js/stores/account.js'))"
LINE = "document.querySelector('[data-credit-line]')"
BANNER = "document.querySelector('[data-launch-credit]')"


def credit_ok(f, selector, where):
    f.wait(selector, what=f"the credit on {where}")
    text = f.js(f"{selector}.innerText")
    assert "Hillel Coren" in text and "Stackapella" in text and "stackapella.com" in text, f"{where}: {text}"
    assert f.js(f"{selector}.querySelector('a').href") == "https://stackapella.com/", "the address links to the site"
    visible = f.js(f"(() => {{ const r = {selector}.getBoundingClientRect(); return r.width > 0 && r.height > 0; }})()")
    assert visible, f"{where}: the credit isn't visible"


def journey(f):
    # stackapella.com: no credit anywhere.
    f.go("/?api=mock&scenario=", "document.querySelector('#app header, #app main')")
    f.wait(f"{ACCOUNT}.account.config", what="the config")
    f.js("new Promise((r) => setTimeout(r, 400))")
    assert not f.js(f"!!{LINE} || !!{BANNER}"), "a credit on stackapella.com"

    # A self-hosted install: Home's line.
    f.run("sessionStorage.setItem('stackapella.mockSelfHosted', '1')")
    f.go("/", "document.querySelector('#app header, #app main')")
    f.wait(f"{ACCOUNT}.selfHosted()", what="self-hosted")
    credit_ok(f, BANNER, "Home, opened")
    assert f.js(f"{BANNER}.getBoundingClientRect().top") < 100, "the banner is in view at the top"
    f.wait(LINE, what="Home's own line")
    f.shot("home")

    # The sign-in screen's line.
    f.go("/signin?as=signedOut", "document.querySelector('form')")
    credit_ok(f, LINE, "sign-in")
    f.shot("signin")

    # Opened on another screen: the banner, until the first move.
    f.go("/voice?as=member", "document.querySelector('#app main h1, #app main')")
    credit_ok(f, BANNER, "a screen the app opened on")
    f.shot("launch-banner")
    # The page itself doesn't scroll sideways (Your voice has its own sideways strip of ranges).
    assert f.js("document.documentElement.scrollWidth - document.documentElement.clientWidth") <= 0, "the banner widens the page"
    f.run("(await import('/js/router.js')).navigate('/settings')")
    f.wait(f"!{BANNER}", what="the banner gone after a move")
    # Settings' own line stays.
    f.wait("document.querySelector('[data-credit]')", what="Settings' credit")


if __name__ == "__main__":
    run("self_hosted_credit", journey)
