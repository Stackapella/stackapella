"""The harness's CDN answer (tool/cdp.py answer_cdn) when a navigation cancels the paused request first: Chrome forgets
the request's id, and answering it then is no failure, as the request is gone (2026-11-17: site_integration died on
"Fetch.fulfillRequest: Invalid InterceptionId" under load). Vue's request is held unanswered, the page is left
mid-load, the held request is answered late without an error, and the app then starts as ever from the pinned file."""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def journey(f):
    if getattr(f.chrome, "name", "chrome") not in ("chrome", "cdp") or not f.chrome.cdn:
        return  # the holding needs the Chrome driver's interception
    serve = f.chrome.answer_cdn
    held = []
    f.chrome.answer_cdn = held.append
    f.chrome.send("Page.navigate", {"url": f.base + "/?api=mock&scenario="})
    deadline = time.time() + 30
    while not any("vue" in p["request"]["url"] for p in held):
        assert time.time() < deadline, f"no paused request for Vue (held: {[p['request']['url'] for p in held]})"
        f.chrome.send("Runtime.evaluate", {"expression": "1"})  # reads the events that came meanwhile
        time.sleep(0.1)

    # Away mid-load: the held requests are cancelled with the page.
    f.chrome.answer_cdn = serve
    f.chrome.goto("about:blank")
    time.sleep(1)
    for params in held:
        serve(params)  # late: no error

    # And the app still starts from the pinned files, served as ever.
    f.chrome.send("Page.navigate", {"url": f.base + "/?api=mock&scenario="})
    f.wait("window.stackapellaStarted && document.querySelector('#app header, #app main')", timeout=30, what="the app after the cancelled load")


if __name__ == "__main__":
    run("cdn_cancelled", journey, sizes=("phone",))
