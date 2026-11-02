"""The theme follows the person across Stackapella's hosts (docs/decisions.md, "the theme follows
you"): chosen in the app on one host (app.stackapella.test), the public site on another
(site.stackapella.test, the same domain) opens in it before it paints, mode, colour and accent; then a
choice on the site comes back to the app. The cookie's domain is the one the server names in
<meta name="theme-cookie-domain"> (put in by the flow, as the server does).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

META = "const m = document.createElement('meta'); m.name = 'theme-cookie-domain'; m.content = '.stackapella.test'; document.head.append(m);"


def journey(f):
    if f.skin != "game":
        return  # about the shared cookie, not a skin (a serious run's harness writes this address's storage first)
    port = f.base.rsplit(":", 1)[1]
    app = f"http://app.stackapella.test:{port}"
    site = f"http://site.stackapella.test:{port}"
    f.expected_errors += ["404 (Not Found) " + app, "404 (Not Found) " + site]
    f.base = app
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    f.run(META + "window.stackapellaTheme.choose({ mode: 'light', colour: 'midnight', accent: 'coral' });")
    assert f.js("document.documentElement.dataset.theme") == "midnight-light"
    # The other host: its own storage is empty, the cookie carries the choice.
    f.base = site
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    # (A serious-skin run's harness keeps only the skin there, before any page script.)
    assert f.js("JSON.parse(localStorage.getItem('stackapella.appearance') || '{}').colour ?? null") is None, "a different address's storage"
    assert f.js("document.documentElement.dataset.theme") == "midnight-light", f.js("document.documentElement.dataset.theme")
    assert f.js("document.documentElement.dataset.accent") == "coral", "the accent too"
    # Chosen there, it comes back to the app (the later choice wins over the app's own storage).
    f.run(META + "window.stackapellaTheme.choose({ mode: 'dark', colour: 'midnight', accent: null });")
    f.base = app
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    assert f.js("document.documentElement.dataset.theme") == "midnight-dark", f.js("document.documentElement.dataset.theme")
    assert f.js("!document.documentElement.dataset.accent"), "the theme's own accent again"
    # Hillel (2026-11-01, live): a host-only sa_theme left on the app's host from before the cookie was
    # shared sits beside the shared one and is listed first; the newer choice made on the site wins.
    f.run("document.cookie = 'sa_theme=' + encodeURIComponent(JSON.stringify({ skin: 'game', mode: 'dark', at: 1 })) + '; path=/'; localStorage.clear();")
    assert f.js("document.cookie.split('; ').filter((c) => c.startsWith('sa_theme=')).length") == 2, "the stale host-only cookie beside the shared one"
    f.base = site
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    f.run(META + "window.stackapellaTheme.choose({ skin: 'serious' });")
    f.base = app
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    assert f.js("document.documentElement.dataset.skin") == "serious", "game mode turned off on the site, still on in the app: " + f.js("document.cookie")
    # A choice in the app (with the shared domain) leaves one sa_theme: the host-only one goes.
    f.run(META + "window.stackapellaTheme.choose({ skin: 'game' });")
    assert f.js("document.cookie.split('; ').filter((c) => c.startsWith('sa_theme=')).length") == 1, f.js("document.cookie")
    f.run("document.cookie = 'sa_theme=; path=/; max-age=0; domain=.stackapella.test'; localStorage.clear();")


if __name__ == "__main__":
    run("theme_hosts", journey, sizes=("desktop",))
