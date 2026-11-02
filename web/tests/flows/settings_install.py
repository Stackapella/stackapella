"""Settings' Install the app (Hillel: always an option to install when this isn't the installed app,
even after Home's card was put away): the browser's own install where it offered one, the steps on
an iPhone, open in a browser first inside Instagram's, the browser's menu elsewhere, and nothing at
all inside the installed app. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

ROW = "document.querySelector('[data-install-row]')"
IPHONE = "Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1"
INSTAGRAM = "Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 350.0.0"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def again(f, setup=""):
    """Settings opened again inside the app (the row reads where it is when it appears)."""
    f.run(f"{setup} const {{ navigate }} = await import('/js/router.js'); navigate('/'); await new Promise((r) => setTimeout(r, 300)); navigate('/settings');")
    f.wait(f"{ROW} || document.querySelector('#device')", what="This device")


def journey(f):
    # Home's card put away: the row is there all the same, with the browser's menu.
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('#device')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    again(f)
    f.wait(ROW, what="Install the app, though Home's card was put away")
    assert t(f, "settings.install.title") in f.js(f"{ROW}.innerText"), "titled"
    # (This browser may have offered its install already: put away here, to see the menu's line.)
    f.run("(await import('/js/components/install.js')).install.prompt = null;")
    f.wait(f"document.querySelector('[data-install-how]').textContent === {json.dumps(t(f, 'settings.install.menu'))}", what="the browser's menu")

    # The browser offered its install: the button opens it, and once accepted the row goes.
    f.run("""const { install } = await import('/js/components/install.js');
        install.prompt = { prompt: () => (window.prompted = true), userChoice: Promise.resolve({ outcome: 'accepted' }) };""")
    f.wait("document.querySelector('[data-install-go]')", what="Install")
    f.run("document.querySelector('#device').scrollIntoView({ block: 'center' })")
    f.no_sideways("Settings with Install")
    f.shot("install")
    f.run("document.querySelector('[data-install-go]').click()")
    f.wait(f"window.prompted && !{ROW}", what="the browser's prompt, then installed")

    # An iPhone's Safari: the steps.
    again(f, f"Object.defineProperty(navigator, 'userAgent', {{ get: () => {IPHONE!r}, configurable: true }});")
    f.wait(f"{ROW} && document.querySelector('[data-install-how]').textContent === {json.dumps(t(f, 'install.iphone'))}", what="the iPhone's steps")
    assert f.js("!document.querySelector('[data-install-go]')"), "no button where the browser has no prompt"
    f.run("document.querySelector('#device').scrollIntoView({ block: 'center' })")
    f.shot("iphone")

    # Instagram's browser: open it in a browser first, never the button.
    again(f, f"Object.defineProperty(navigator, 'userAgent', {{ get: () => {INSTAGRAM!r}, configurable: true }});")
    f.wait(f"{ROW} && document.querySelector('[data-install-how]').textContent.includes('Instagram')", what="open in a browser first")

    # Inside the installed app: nothing.
    again(f, "delete navigator.userAgent; const real = window.matchMedia.bind(window); window.matchMedia = (q) => (q.includes('standalone') ? { matches: true, addEventListener() {}, removeEventListener() {} } : real(q));")
    f.run("await new Promise((r) => setTimeout(r, 500))")
    assert f.js(f"!{ROW}"), "no Install inside the installed app"


if __name__ == "__main__":
    run("settings_install", journey)
