"""Offline (docs/roadmap.md, L): the app opened once online, then the server gone, as it is on a
train. The course, the songs on the device (the four that come with the app, one started from the
catalogue, one of your own), Sing and the studio, with playback and a WAV export, still work; every
screen that needs the server says so plainly, with no blank screen and no uncaught error. And a new
version of the app keeps the pinned files it carries over (MP3 export's encoder).

It runs its own server (on a free port, the mock API), so that stopping it cuts off the page and the
service worker alike (DevTools' offline switch doesn't reach the worker's own fetches):

    python3 -I tests/flows/offline.py           # (tool/flows.sh offline runs it too)
    OFFLINE_SURVEY=1 python3 -I tests/flows/offline.py   # what each screen shows, without failing
"""
import json
import os
import re
import socket
import subprocess
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402
from help_shots import AUDIO, home_page, new_song, sung_takes, t  # noqa: E402

WEB = Path(__file__).resolve().parents[2]
SURVEY = bool(os.environ.get("OFFLINE_SURVEY"))
# Each failed request offline is logged by the browser; those are the point, not errors.
GONE = ("ERR_CONNECTION_REFUSED", "Failed to fetch", "NetworkError", "net::ERR", "Load failed", "status of 503", "status of 504")

# What the screen shows: its heading, whether it says it needs a connection, and how much is on it.
LOOK = """(() => {
    const sheet = [...document.querySelectorAll('[role=dialog]')].at(-1);
    const text = (sheet ?? document.querySelector('#app'))?.innerText ?? '';
    return JSON.stringify({
        h1: (sheet ?? document.querySelector('#app'))?.querySelector('h1, h2')?.textContent.trim().slice(0, 60) ?? null,
        offline: /offline|connection|online|couldn.t load|can.t reach/i.test(text),
        words: text.trim().length,
        line: (text.match(/[^\\n]*(offline|connection|online|couldn.t load|can.t reach)[^\\n]*/i) ?? [''])[0].slice(0, 120),
        start: text.trim().replace(/\\s+/g, ' ').slice(0, 160),
    });
})()"""


def free_port():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def serve(port):
    return subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "tool/serve.php"], cwd=WEB, env={**os.environ, "API": "mock"},
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def look(f, path, ready="document.querySelector('#app h1, #app h2, #app main')", then=None):
    """A screen offline: opened fresh (from the service worker), its look and its errors."""
    f.chrome.errors.clear()
    try:
        f.chrome.goto(f.base + path, ready, timeout=15)
        if then:
            then()
        time.sleep(1)
        seen = json.loads(f.js(LOOK))
    except Exception as error:  # a screen that doesn't open is what this is here to find
        seen = {"h1": None, "offline": False, "words": 0, "line": f"didn't open: {str(error)[:100]}"}
    seen["errors"] = [e[:140] for e in f.chrome.errors if not any(g in e for g in GONE) and "favicon" not in e][:3]
    return seen


def main():
    port = free_port()
    server = serve(port)
    time.sleep(0.8)
    size, skin = os.environ.get("OFFLINE_SIZE", "desktop"), os.environ.get("OFFLINE_SKIN", "game")
    f = flow.Flow(f"http://127.0.0.1:{port}", size, "offline", skin)
    report, failures = [], []
    try:
        # Online, once: the app and its worker, a song from the catalogue started, one of your own.
        home_page(f, "member")
        f.wait("navigator.serviceWorker.controller", timeout=30, what="the service worker in control")
        f.run("localStorage.setItem('stackapella.installDismissed', '1');")
        grace = new_song(f)
        f.go(f"/p/{grace}?api=mock&as=member", "document.querySelectorAll('canvas').length > 3")
        f.js(f"{AUDIO}.unlock()")
        sung_takes(f, ["soprano", "alto"])
        own = f.js("(async () => { const s = await import('/js/stores/projects.js'); return (await s.createProject({ title: 'On the train' })).id; })()")
        server.terminate()
        server.wait()

        # "works": it opens and does its job from the device. "says": it needs the server, and says
        # so plainly (never a blank screen, never an uncaught error).
        sheet = lambda key: lambda: open_sheet(f, key)  # noqa: E731
        screens = [
            ("works", "home", "/?api=mock&scenario=&as=member", None),
            ("works", "learn", "/learn?api=mock&as=member", None),
            ("works", "lesson", "/learn/1-match?api=mock&as=member", None),
            ("works", "workout", "/workout?api=mock&as=member", None),
            ("works", "voice", "/voice?api=mock&as=member", None),
            ("works", "sing", f"/p/{grace}/sing?api=mock&as=member", None),
            ("works", "studio", f"/p/{grace}?api=mock&as=member", None),
            ("works", "own-song", f"/p/{own}?api=mock&as=member", None),
            ("works", "export", f"/p/{grace}?api=mock&as=member", sheet("studio.export")),
            ("works", "share", f"/p/{grace}?api=mock&as=member", sheet("share.title")),
            ("says", "share-create", f"/p/{grace}?api=mock&as=member", lambda: (open_sheet(f, "share.title"), f.wait_text(t(f, "share.createLink")), f.click_t("share.createLink"), time.sleep(3))),
            ("works", "settings", "/settings?api=mock&scenario=&as=member", None),
            ("says", "storage", "/storage?api=mock&scenario=&as=member", None),
            ("says", "reminders", "/settings/reminders?api=mock&scenario=&as=member", None),
            ("says", "plus", "/plus?api=mock&scenario=&as=member", None),
            ("says", "groups", "/groups?api=mock&scenario=&as=member", None),
            ("says", "contact", "/contact?api=mock&scenario=&as=member", lambda: contact_send(f)),
            ("works", "sign-in", "/signin?api=mock&scenario=&as=signedOut", lambda: (f.type("input[type=email]", "ada@example.com"), f.click_t("auth.continue"), time.sleep(2))),
        ]
        for kind, name, path, then in screens:
            seen = look(f, path, then=then)
            report.append((name, seen))
            if os.environ.get("OUT") and name in ("plus", "studio"):  # the UI guide's offline screens
                # The line that says so, in view.
                f.run("[...document.querySelectorAll('#app p, #app h2, #app [role=status], #app [role=alert]')].find((e) => /offline|connection|online|can.t reach/i.test(e.textContent))?.scrollIntoView({ block: 'center' })")
                time.sleep(0.5)
                Path(os.environ["OUT"]).expanduser().mkdir(parents=True, exist_ok=True)
                f.chrome.screenshot(str(Path(os.environ["OUT"]).expanduser() / f"offline-{name}-{size}{'' if skin == 'game' else '-serious'}.png"))
            if seen["words"] < 40:
                failures.append(f"{name}: a blank screen offline ({seen['line'] or 'nothing on it'})")
            elif kind == "says" and not seen["offline"]:
                failures.append(f"{name}: doesn't say it needs a connection")
            if seen["errors"]:
                failures.append(f"{name}: page errors offline: {seen['errors']}")
    finally:
        if server.poll() is None:
            server.terminate()
        f.chrome.errors.clear()
        f.close()
    failures += carry_over_check()
    for name, seen in report:
        print(f"     {name:10} h1={seen['h1']!r} offline-words={seen['offline']} words={seen['words']} {seen['line']!r} errors={seen['errors']}")
        if SURVEY:
            print(f"                {seen.get('start', '')!r}")
    print(f"{'ok  ' if not failures else 'FAIL'} offline")
    for failure in failures:
        print("     " + failure)
    sys.exit(1 if failures and not SURVEY else 0)


def carry_over_check():
    """The carry-over on a fresh server and browser, so the survey's worker is left alone."""
    port = free_port()
    server = serve(port)
    time.sleep(0.8)
    f = flow.Flow(f"http://127.0.0.1:{port}", "desktop", "offline-carry")
    try:
        home_page(f, "member")
        f.wait("navigator.serviceWorker.controller", timeout=30, what="the service worker in control")
        carried_over(f)
        return []
    except AssertionError as error:
        return [f"carry-over: {error}"]
    finally:
        server.terminate()
        f.chrome.errors.clear()
        f.close()


def carried_over(f):
    """MP3 export's encoder, kept the first time it's fetched, survives a new version of the app
    (sw.js CARRIED): served a changed sw.js, the new worker takes over with the encoder in its cache."""
    lame = json.dumps(re.search(r"const LAME = '([^']+)'", (WEB / "js" / "mp3.js").read_text()).group(1))
    f.js(f"fetch({lame}, {{ mode: 'cors' }}).then((r) => r.ok)")
    # Kept for good: listed in the cache's keys (caches.match answers while the write is still going).
    f.wait(f"caches.keys().then((keys) => Promise.all(keys.map((k) => caches.open(k).then((c) => c.keys())))).then((all) => all.flat().some((r) => r.url === {lame}))", what="the encoder kept")
    # A file kept just now is still settling; an update comes long after, so give it that moment.
    time.sleep(1.5)
    before = f.js("caches.keys()")
    sw = WEB / "sw.js"
    original = sw.read_text()
    try:
        sw.write_text(re.sub(r"const CACHE = '([^']+)';", r"const CACHE = '\1-next';", original, count=1))
        f.run("await (await navigator.serviceWorker.getRegistration()).update();")
        f.wait(f"caches.keys().then((keys) => keys.length === 1 && !{json.dumps(before)}.includes(keys[0]))", timeout=30, what="the new version in charge")
        assert f.js(f"caches.match({lame}).then((hit) => !!hit)"), "the encoder was lost with the old version"
    finally:
        sw.write_text(original)
    # And back to this version, online, so nothing is left to install once the server's gone.
    f.run("await (await navigator.serviceWorker.getRegistration()).update();")
    f.wait(f"caches.keys().then((keys) => keys.length === 1 && {json.dumps(before)}.includes(keys[0]))", timeout=30, what="this version back in charge")
    assert f.js(f"caches.match({lame}).then((hit) => !!hit)"), "the encoder was lost coming back"


def contact_send(f):
    """A message written and sent (with an email to reply to, if the account isn't known offline)."""
    f.type("textarea", "The mic went quiet.")
    f.run("const box = document.querySelector('#app input[type=email]:not([readonly])'); if (box) { box.value = 'ada@example.com'; box.dispatchEvent(new Event('input', { bubbles: true })); }")
    f.click_t("contact.send")
    time.sleep(2)


def open_sheet(f, key):
    label = json.dumps(t(f, key))
    f.wait(f"[...document.querySelectorAll('#app header button')].some((b) => b.getClientRects().length && b.textContent.trim().endsWith({label}))", what=key)
    f.run(f"[...document.querySelectorAll('#app header button')].find((b) => b.getClientRects().length && b.textContent.trim().endsWith({label})).click()")
    f.wait("document.querySelector('[role=dialog]')", what=f"the {key} sheet")


if __name__ == "__main__":
    main()
