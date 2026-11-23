"""The way back to the website (the consultant's UX review, 2026-11-22): someone who came from the public site sees
"Stackapella home" beside the logo on Home (short on a phone), going to the site; nobody else does. Settings lists the
website's home first among its footer links, for everyone.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import t  # noqa: E402

LINK = "[...document.querySelectorAll('[data-site-home], [data-site-home-phone]')].find((a) => a.getClientRects().length)"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.run("localStorage.removeItem('stackapella.fromSite')")
    # As members see it now: own_choir on, so Home carries its Hear yourself as a choir panel (the coordinator's review).
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockChoirState', 'fresh'); sessionStorage.removeItem('stackapella.mockChoir');")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-lead]')")
    assert not f.js(f"!!({LINK})"), "the link for someone who didn't come from the site"
    # Came from the site (the referrer is the site's own host, remembered at start).
    f.run("localStorage.setItem('stackapella.fromSite', '1')")
    f.go("/?api=mock&scenario=&as=member", f"!!({LINK})")
    site = f.js("(await import('/js/hosts.js')).publicSiteUrl()")
    assert f.js(f"{LINK}.getAttribute('href')") == site, "it doesn't go to the site"
    words = t(f, "home.siteHome")  # a phone has it on its own line under the bar (beside the logo it crowded the buttons)
    assert f.js(f"{LINK}.innerText.trim()") == words, f.js(f"{LINK}.innerText")
    assert f.js(f"{LINK}.getBoundingClientRect().height") >= 44, "under 44 px to tap"
    f.wait("document.querySelector('[data-choir-panel]')", what="the Home choir panel, as members see Home")
    f.no_sideways("Home's header with the way back")
    f.shot("home")
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-settings-site-home]')")
    assert f.js("document.querySelector('[data-settings-site-home]').getAttribute('href')") == site
    f.run("document.querySelector('[data-legal]').scrollIntoView({ block: 'center' })")
    f.shot("settings")
    f.run("localStorage.removeItem('stackapella.fromSite'); sessionStorage.removeItem('stackapella.mockOwnChoir'); sessionStorage.removeItem('stackapella.mockChoirState');")


if __name__ == "__main__":
    run("site_home", journey)
