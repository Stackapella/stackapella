"""A browser too old for the import map (Safari before 16.4) gets a plain message, not a blank page
(js/old-browser.js): the browser's import map support forced off, the message shows in light and
dark with the way to the website, and the app stays out of sight; a browser that has it never sees it.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

OLD = "HTMLScriptElement.supports = (type) => type !== 'importmap';"
MESSAGE = "document.querySelector('[data-old-browser]')"


def journey(f):
    f.go("/learn", "document.querySelector('#app h1')")
    assert not f.js(f"!!{MESSAGE}"), "a browser with import maps never sees the message"
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": OLD})
    # Game mode's own dark, then serious mode in Warm light: the message takes the page's look.
    for scheme, choice in (("dark", "{ skin: 'game', at: Date.now() }"), ("light", "{ skin: 'serious', mode: 'light', colour: 'warm', at: Date.now() }")):
        f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-color-scheme", "value": scheme}]})
        f.run(f"localStorage.setItem('stackapella.appearance', JSON.stringify({choice}));")
        f.go("/learn", MESSAGE)
        assert (f.js(f"getComputedStyle(document.body).backgroundColor") == "rgb(0, 0, 0)") == (scheme == "dark"), f"{scheme}: the page's own look"
        assert f.js("document.getElementById('app').hidden"), "the app stays out of sight"
        assert "16.4" in f.js(f"{MESSAGE}.innerText"), "it says which version"
        assert f.js(f"!!{MESSAGE}.querySelector('a[href]')"), "with the way to the website"
        f.no_sideways("the old browser message")
        f.shot(f"old-browser-{scheme}")


run("old_browser", journey)
