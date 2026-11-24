"""The choir wait's banner (Hillel, 2026-11-24: "a banner while it's made"): one line under the app bar while a choir made
from their singing is on its way, ready or failed, from the server's state.
- ready first (Listen), then on its way: being made ("1 of 4 parts ready"), in line ("about 6 min"), slower than usual;
- Close hides it until its state changes (a reload keeps it closed);
- it never shows on that song's own choir page; its tap goes there;
- in Spanish too.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

PAGE = "/settings?api=mock&scenario=&as=member"
FLAGS = ["stackapella.mockChoir", "stackapella.mockChoirMaking", "stackapella.mockChoirQueued", "stackapella.mockChoirSlow", "stackapella.mockChoirState"]


def t(f, key, params=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(params or {})})")


def banner(f, state, page=PAGE):
    """Opens `page` and waits for the banner in `state`; returns its words."""
    f.go(page, "document.querySelector('#app main, #app h1')")
    f.wait(f"document.querySelector('[data-choir-banner]')?.dataset.state === {json.dumps(state)}", timeout=20, what=f"the banner, {state}")
    return f.js("document.querySelector('[data-choir-banner-words]').textContent.trim()")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.run("localStorage.removeItem('stackapella.choirBannerClosed'); sessionStorage.setItem('stackapella.mockOwnChoir', '1');" + "".join(f"sessionStorage.removeItem({json.dumps(k)});" for k in FLAGS))

    # Ready (Silent Night, unheard): Listen.
    words = banner(f, "ready")
    assert words == t(f, "choirWait.banner.ready", {"song": "Silent Night"}), words
    assert f.js("document.querySelector('[data-choir-banner-open]').textContent.trim()") == t(f, "choirWait.banner.listen")
    assert f.js("document.querySelector('[data-choir-banner]').getBoundingClientRect().top < 200"), "not under the bar"
    f.no_sideways("the banner, ready")
    f.shot("ready")
    # Settings' row for the email (shown while the choir email is switched on, as here).
    f.wait("document.querySelector('[data-choir-email-setting]')", what="Email me when my choir is ready")
    assert t(f, "choirWait.settings.title") in f.js("document.querySelector('[data-choir-email-setting]').innerText"), "the row's words"
    f.run("document.querySelector('[data-choir-email-setting]').scrollIntoView({ block: 'center' })")
    f.shot("settings-row")
    f.run("window.scrollTo(0, 0)")

    # Heard: the next song, being made from the saved voice (one part ready, one making, two waiting).
    f.run("""const { api } = await import('/js/api.js');
        await api.ownChoir.requests.seen('Rq5Sn1');
        sessionStorage.setItem('stackapella.mockChoirMaking', '1');
        await api.ownChoir.requests.create({ templateId: 'amazing-grace' });""")
    words = banner(f, "making")
    assert words == t(f, "choirWait.banner.making", {"song": "Amazing Grace", "n": 1, "count": 4}), words
    assert f.js("document.querySelector('[data-choir-banner-open]').textContent.trim()") == t(f, "choirWait.banner.see")
    f.shot("making")

    # In line, about 6 minutes; slower than usual.
    f.run("sessionStorage.setItem('stackapella.mockChoirQueued', '1')")
    words = banner(f, "queued")
    assert words == t(f, "choirWait.banner.queued", {"song": "Amazing Grace", "m": 6}), words
    f.shot("queued")
    f.run("sessionStorage.setItem('stackapella.mockChoirSlow', '1')")
    words = banner(f, "slow")
    assert words == t(f, "choirWait.banner.slow", {"song": "Amazing Grace"}), words
    f.shot("slow")

    # Close: gone, and still gone after a reload, until the state changes.
    f.run("document.querySelector('[data-choir-banner-close]').click()")
    f.wait("!document.querySelector('[data-choir-banner]')", what="closed")
    f.go(PAGE, "document.querySelector('#app main, #app h1')")
    f.js("new Promise((r) => setTimeout(r, 800))")
    assert not f.js("!!document.querySelector('[data-choir-banner]')"), "a closed banner came back with the same state"
    f.run("sessionStorage.removeItem('stackapella.mockChoirSlow')")
    banner(f, "queued")

    # Its tap: the song's choir page, where the banner never shows.
    f.run("document.querySelector('[data-choir-banner-open]').click()")
    f.wait("location.pathname === '/t/amazing-grace/choir'", what="the song's choir page")
    f.js("new Promise((r) => setTimeout(r, 800))")
    assert not f.js("!!document.querySelector('[data-choir-banner]')"), "the banner on its own song's page"

    # In Spanish.
    words = banner(f, "queued", PAGE + "&lang=es")
    assert words == t(f, "choirWait.banner.queued", {"song": f.js("(await import('/js/song-titles.js')).songTitle({ id: 'amazing-grace', title: 'Amazing Grace' })"), "m": 6}), words
    f.no_sideways("the banner in Spanish")
    f.shot("es-queued")
    f.run("localStorage.removeItem('stackapella.choirBannerClosed');" + "".join(f"sessionStorage.removeItem({json.dumps(k)});" for k in FLAGS))


if __name__ == "__main__":
    run("choir_banner", journey)
