"""Never a blank screen (Hillel: "when I click some pages (ie settings or a course) the screen is
blank for a second. can we speed it up or add progress indicator?"): a screen still loading shows
its frame (Back, its title, a quiet skeleton, a spinner in the accent) after a moment, busy and
saying what's opening, then the screen itself with focus on its heading; a second open is instant;
and the screens most often opened next (Settings among them) are fetched ahead, so they open at once.
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
NAVIGATE = "(await import('/js/router.js')).navigate"
SLOW = {"offline": False, "latency": 600, "downloadThroughput": 50000, "uploadThroughput": 50000}
FAST = {"offline": False, "latency": 0, "downloadThroughput": -1, "uploadThroughput": -1}
# Whether the loading frame ever showed, from now on.
WATCH = "window.__framed = false; new MutationObserver(() => { if (document.querySelector('[data-screen-loading]')) window.__framed = true; }).observe(document.body, { childList: true, subtree: true });"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#lead-title')")
    title = t(f, "reminders.title")

    # A screen not fetched ahead, on a slow line: its frame, then the screen.
    f.chrome.send("Network.enable")  # throttling needs it
    f.chrome.send("Network.emulateNetworkConditions", SLOW)
    f.run(f"{NAVIGATE}('/settings/reminders')")
    f.wait("document.querySelector('[data-screen-loading]')", timeout=10, what="the loading frame")
    assert f.js("document.querySelector('[data-screen-loading]').getAttribute('aria-busy')") == "true", "busy"
    assert f.js("document.querySelector('[data-screen-loading] [role=status]').textContent.trim()") == t(f, "common.loadingScreen", {"screen": title})
    assert f.js("document.querySelector('[data-screen-loading]').innerText").find(title) >= 0, "its title in the frame"
    assert f.js("!document.querySelector('#main h1')"), "no heading until the screen's own"
    f.shot("loading")
    f.chrome.send("Network.emulateNetworkConditions", FAST)
    f.wait(f"document.querySelector('#main h1')?.textContent.trim() === {json.dumps(title)} && !document.querySelector('[data-screen-loading]')", timeout=30, what="the screen itself")
    f.wait("document.activeElement === document.querySelector('#main h1')", what="focus on its heading")

    # A second open: instant, no frame.
    f.run(f"{NAVIGATE}('/'); {WATCH}")
    f.wait("document.querySelector('#lead-title')", what="home again")
    f.run(f"{NAVIGATE}('/settings/reminders')")
    f.wait(f"document.querySelector('#main h1')?.textContent.trim() === {json.dumps(title)}", what="Reminders again")
    assert not f.js("window.__framed"), "a second open never shows the frame"

    # Settings, fetched ahead while the home was idle: opens with no frame, even on a slow line.
    time.sleep(4)  # the browser idle: the likely screens fetched ahead
    f.run(f"{NAVIGATE}('/'); {WATCH}")
    f.wait("document.querySelector('#lead-title')", what="home again")
    f.chrome.send("Network.emulateNetworkConditions", SLOW)
    f.run(f"{NAVIGATE}('/settings')")
    f.wait(f"document.querySelector('#main h1')?.textContent.trim() === {json.dumps(t(f, 'settings.title'))}", timeout=5, what="Settings at once")
    assert not f.js("window.__framed"), "Settings was fetched ahead: no frame"
    f.chrome.send("Network.emulateNetworkConditions", FAST)


if __name__ == "__main__":
    run("screen_loading", journey)
