"""When the app's modules can't load (Vue's CDN unreachable, a dropped line), the page says so calmly with Reload
instead of staying blank (js/boot-watch.js; the coordinator, 2026-11-02). Vue's request is refused through the
harness's own interception (tool/cdp.py), then let through again: the message shows, Reload brings the app, and on a
normal start nothing shows."""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

FAILED = "document.querySelector('[data-boot-failed]')"


def journey(f):
    if getattr(f.chrome, "name", "chrome") not in ("chrome", "cdp") or not f.chrome.cdn:
        return  # the refusal needs the Chrome driver's interception
    serve = f.chrome.answer_cdn
    f.chrome.answer_cdn = lambda params: f.chrome.send("Fetch.failRequest", {"requestId": params["requestId"], "errorReason": "ConnectionRefused"})
    f.expected_errors += ["vue.esm-browser.prod.js", "ERR_CONNECTION_REFUSED", "Failed to load resource", "Failed to fetch dynamically imported module", "TypeError"]
    f.chrome.send("Page.navigate", {"url": f.base + "/?api=mock&scenario="})
    f.wait(FAILED, timeout=20, what="the calm couldn't-load message")
    assert "couldn't load" in f.js(f"{FAILED}.textContent"), f.js(f"{FAILED}.textContent")
    assert not f.js("document.querySelector('.app-splash').getClientRects().length"), "the waiting logo over the message"
    f.shot("couldnt-load")

    # The line back: Reload brings the app, and no message.
    f.chrome.answer_cdn = serve
    f.run(f"{FAILED}.querySelector('button').click()")
    f.wait("window.stackapellaStarted && document.querySelector('#app header, #app main')", timeout=30, what="the app after Reload")
    time.sleep(4)
    assert not f.js(f"!!{FAILED}"), "the couldn't-load message on a normal start"
    assert not f.js("!!document.querySelector('.app-splash')"), "the waiting logo left after the app drew"


if __name__ == "__main__":
    run("boot_failed", journey, sizes=("phone",))
