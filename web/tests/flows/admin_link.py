"""Home's Admin link (Hillel: "a link in the header ONLY I CAN SEE"): there for an account whose live
account.show says `admin` (the full admin gate), and no trace of it anywhere for a guest, a member or
an editor: not hidden, not disabled, not in the page at all. The server's /admin guard is the real
one; this is a convenience. Against the mock, whose `admin` is true for `as=admin` only.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# Any sign of the link: the element, a link to /admin, or the shield in the header.
TRACE = "!!document.querySelector('[data-admin-link], a[href^=\"/admin\"]') || document.documentElement.outerHTML.includes('data-admin-link') || document.documentElement.outerHTML.includes('href=\"/admin')"


def home(f, who, ready):
    f.go(f"/?api=mock&scenario=&as={who}", "document.querySelector('#app header')")
    f.wait(ready, what=f"Home's header, as {who}")


def journey(f):
    home(f, "signedOut", "document.querySelector('#app header a[href=\"/signin\"]')")
    assert not f.js(TRACE), "a guest sees no trace of Admin"

    for who in ("member", "editor"):
        home(f, who, "document.querySelector('#app header a[href=\"/settings\"]')")
        assert not f.js(TRACE), f"{who}: no trace of Admin"
        if who == "member":
            f.shot("member")

    # An account the server calls an admin: a quiet shield and the word, at the header's end, to /admin.
    home(f, "admin", "document.querySelector('[data-admin-link]')")
    assert f.js("document.querySelector('[data-admin-link]').getAttribute('href')") == "/admin"
    assert f.js("document.querySelector('[data-admin-link]').getAttribute('aria-label')") == "Admin"
    assert f.js("!document.querySelector('[data-admin-link]').nextElementSibling"), "at the header's end"
    f.no_sideways("Home as admin")
    f.shot("admin")

    # The live account decides: the server saying otherwise (or signing out) takes it away at once.
    f.run("const { account } = await import('/js/stores/account.js'); account.me = { ...account.me, admin: false };")
    f.wait("!document.querySelector('[data-admin-link]')", what="gone when the account isn't an admin")
    f.run("const { account } = await import('/js/stores/account.js'); account.me = null;")
    f.wait("!document.querySelector('[data-admin-link]')", what="gone when signed out")
    assert not f.js(TRACE), "no trace after signing out"

    # Nothing kept on the device can bring it back: it's read only from account.show.
    f.run("localStorage.setItem('stackapella.admin', 'true'); sessionStorage.setItem('stackapella.admin', 'true');")
    home(f, "member", "document.querySelector('#app header a[href=\"/settings\"]')")
    assert not f.js(TRACE), "a member with a planted flag sees no trace of Admin"
    f.run("localStorage.removeItem('stackapella.admin'); sessionStorage.removeItem('stackapella.admin');")


if __name__ == "__main__":
    run("admin_link", journey, sizes=("desktop", "phone"))
