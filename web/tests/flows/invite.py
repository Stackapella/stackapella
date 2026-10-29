"""Hillel's shared invite link (contract/api.md, inviteLink.show; admin.invite.*): /invite/<code>
welcomes and lets you in as a guest, or makes an account, the code waiting in the tab for the
sign-up (guest.py has the guest's whole way in); and the admin's
Links page shows the invite link (Copy, Pause, Replace with its own confirmation, no cap) beside the
preview link. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/invite/Pq4Rt7Vb2Nx9?api=mock&as=signedOut", "document.querySelector('h1')")
    f.wait_text(t(f, "invite.headline"))
    f.wait_text(t(f, "invite.from", {"name": t(f, "invite.founder")}))
    # Start singing (as a guest) leads; making an account is the second choice.
    f.wait_text(t(f, "early.start"))
    f.wait_text(t(f, "early.accountWhy"))
    f.shot("invite")
    f.click_t("early.makeAccount")
    f.wait("document.querySelector('#invite-email')", what="the account form")
    f.type("#invite-email", "sam@example.com")
    f.click(t(f, "invite.accept"), "button")
    f.wait_text(t(f, "invite.sent.title"))
    # The link from the email: a new account, so a bit about you first. (The mock answers as the
    # persona in the address, so the account is the member once the link has signed in.)
    f.go("/signin/link?token=Lk8mQ2pL7vR4tB8nW3cJ5hYz&api=mock&as=member", "document.querySelector('h1')")
    f.wait_text(t(f, "auth.profile.title"))
    f.type("input[autocomplete=name]", "Sam Flow")
    f.run("const [m, y] = document.querySelectorAll('form fieldset select'); m.value = '4'; m.dispatchEvent(new Event('change')); y.value = '1990'; y.dispatchEvent(new Event('change'));")
    f.shot("a-bit-about-you")
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("location.pathname.startsWith('/choir/')", what="on to where the link was for")
    # Hillel's Links page.
    f.go("/admin/links?api=mock&as=admin", "document.querySelector('#invite-heading')")
    f.wait("document.querySelector('section[aria-labelledby=invite-heading] code') && document.querySelector('section[aria-labelledby=preview-heading] code')", what="both links, loaded")
    assert not f.js("!!document.querySelector('section[aria-labelledby=invite-heading] input[type=number]')"), "the invite link has no cap"
    f.click(t(f, "admin.mudeo.replace"), "section[aria-labelledby=invite-heading] button")
    f.wait_text(t(f, "admin.links.replaceBody"))
    f.shot("replace")


if __name__ == "__main__":
    run("invite", journey, sizes=("desktop",))
