"""The way to the website from the app (Hillel, 2026-11-26: "remove '< Stackapella website' from top of app, users can
just click the app logo"): Home's header has no link in words, for anyone; its logo goes to the website's home in the
app's language (/es/ in Spanish), named "Stackapella website". Settings lists the website first among its footer links.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import t  # noqa: E402

LOGO = "document.querySelector('[data-logo-site]')"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    # As members see it now: own_choir on, so Home carries its Hear yourself as a choir panel (the coordinator's review).
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockChoirState', 'fresh'); sessionStorage.removeItem('stackapella.mockChoir'); sessionStorage.setItem('stackapella.mockAiFeatures', '1'); sessionStorage.setItem('stackapella.mockAiSource', 'card');")
    # Even someone who came from the site (an older device may still keep that mark) sees no link in words.
    f.run("localStorage.setItem('stackapella.fromSite', '1')")
    f.go("/?api=mock&scenario=&as=member", f"{LOGO}")
    assert not f.js("!!document.querySelector('[data-site-home], [data-site-home-phone]')"), "a link to the website in words"
    site = f.js("(await import('/js/hosts.js')).siteHomeUrl('en')")
    assert f.js(f"{LOGO}.getAttribute('href')") == site, "the logo doesn't go to the website"
    assert f.js(f"{LOGO}.getAttribute('aria-label')") == t(f, "home.siteHome"), f.js(f"{LOGO}.getAttribute('aria-label')")
    assert f.js(f"{LOGO}.getBoundingClientRect().height") >= 44, "the logo under 44 px to tap"
    f.wait("document.querySelector('[data-choir-panel]')", what="the Home choir panel, as members see Home")
    f.no_sideways("Home's header")
    f.run("window.scrollTo(0, 0)")
    f.shot("home")
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-settings-site-home]')")
    assert f.js("document.querySelector('[data-settings-site-home]').getAttribute('href')") == site
    f.run("document.querySelector('[data-legal]').scrollIntoView({ block: 'center' })")
    f.shot("settings")
    f.run("localStorage.removeItem('stackapella.fromSite'); ['mockOwnChoir', 'mockChoirState', 'mockAiFeatures', 'mockAiSource'].forEach((k) => sessionStorage.removeItem('stackapella.' + k));")

if __name__ == "__main__":
    run("site_home", journey)
