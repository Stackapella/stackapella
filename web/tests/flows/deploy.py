"""A deploy, as the browser sees it (the PWA update pass, 2026-11-14: sw.js serves a page its own build's files, and a
new build's worker waits beside it until the page asks it to take over: js/sw-update.js).
- A page left open across a deploy: a module it imports after the deploy is still its own build's (it was the new
  build's, beside the old ones: an open installed app ran two builds at once).
- The app opened after a deploy, before anything is tapped: the new build's worker takes over and the page reloads on
  it, with no prompt; the admin's own code is never kept, so it's always the server's.
- "A new version is ready" while the page is open: held while a take is recorded or a lesson is under way; Not now hides
  it; Refresh lands on the new build at once (its worker installed and waiting), never the old one again.
- A file the build's cache should hold and doesn't comes from the network (never a blank page).
- The kill switch (sw.js KILL, tool/sw-kill.txt here): a page stuck on a broken kept build comes back whole, from the
  network, with no worker left.
It depends on the server's `Cache-Control: no-cache` (tool/serve.php, as .do/nginx.conf).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

WEB = Path(__file__).resolve().parents[2]
PROBES = [WEB / "js" / "deploy-probe.js", WEB / "js" / "admin" / "deploy-probe.js"]
LATE = WEB / "js" / "deploy-probe-late.js"  # a module the open page imports only after the deploy
BUILD = WEB / "tool" / "mock-build.txt"
KILL = WEB / "tool" / "sw-kill.txt"
VERSION = "(await import('/js/version.js'))"
STARTED = "window.stackapellaStarted"  # the app's own modules in: its import map too (a reload in between has neither yet)
PROMPT = "document.querySelector('[data-new-version]')"
A, B = "mockaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa", "mockbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
WORKER = "(navigator.serviceWorker.controller?.scriptURL ?? '')"


def write(version):
    for probe in PROBES + [LATE]:
        probe.write_text(f"// A file for tests/flows/deploy.py only: removed when the flow ends.\nexport const VERSION = {version};\n")


def versions(f):
    return f.js("Promise.all([import('/js/deploy-probe.js'), import('/js/admin/deploy-probe.js')]).then(([a, b]) => [a.VERSION, b.VERSION].join())")


def journey(f):
    try:
        write(1)
        BUILD.write_text(A)
        f.go("/?api=mock&scenario=", "document.querySelector('header')")
        f.wait(f"{WORKER}.includes('build={A}')", what="build A's worker in control")
        f.go("/?api=mock&scenario=", "document.querySelector('header')")  # a page build A's worker served
        assert versions(f) == "1,1", versions(f)
        f.run("await fetch('/js/deploy-probe-late.js')")  # build A's worker keeps its copy, as it keeps every file it serves
        f.wait("caches.match('/js/deploy-probe-late.js').then((hit) => !!hit)", what="build A's copy kept")
        write(2)  # the deploy
        BUILD.write_text(B)
        # The open page: what it imports now is its own build's, never the new one's beside its old modules.
        late = f.js("import('/js/deploy-probe-late.js').then((m) => m.VERSION)")
        assert late == 1, f"the open page got the new build's module: {late}"
        # The open page learns of the new build and its worker installs and waits (as it would in the background).
        f.run(f"await {VERSION}.checkVersion({{ now: true }})")
        f.wait(f"navigator.serviceWorker.getRegistration().then((r) => (r?.waiting?.scriptURL ?? '').includes('build={B}'))", timeout=60, what="build B's worker installed and waiting")
        # Opened again, nothing done: the waiting build takes over within SILENT_MS and the page reloads on it, no prompt.
        f.go("/?api=mock&scenario=", "document.querySelector('header')")
        f.wait(f"{STARTED} && {WORKER}.includes('build={B}') && {VERSION}.version.loaded === {B!r}", timeout=40, what="the app reopened on the new build")
        assert versions(f) == "2,2", f"an old module after the deploy: {versions(f)}"
        assert not f.js(f"!!{PROMPT}"), "a prompt on a page already on the new build"
        # Old words beside new code (as an old worker once served): the screen ends with real words, never keys.
        title = t(f, "auth.signInTitle")
        f.run("const { locale } = await import('/js/i18n.js'); delete locale.english.auth; delete locale.messages.auth;")
        f.run("(await import('/js/router.js')).navigate('/signin')")
        f.wait(f"document.querySelector('h1')?.textContent.trim() === {json.dumps(title)}", what="real words after fetching them again")
        assert not f.js("/\\bauth\\.[a-z]/.test(document.body.innerText)"), "a raw key on screen"
        new_version(f)
        missing_file(f)
        kill_switch(f)
    finally:
        for probe in PROBES + [LATE]:
            probe.unlink(missing_ok=True)
        BUILD.unlink(missing_ok=True)
        KILL.unlink(missing_ok=True)


def t(f, key):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)})")


def new_version(f):
    f.go("/?api=mock&scenario=", "document.querySelector('header')")
    f.run("document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))")  # someone's using it: the prompt asks
    f.wait(f"{VERSION}.version.loaded", what="the build this page started with")
    assert not f.js(f"!!{PROMPT}"), "no prompt before a deploy"
    BUILD.write_text("mock1111111111111111111111111111111111111")  # the deploy
    # Recording: it waits.
    f.run("(await import('/js/stores/session.js')).session.recording = true;")
    f.run(f"await {VERSION}.checkVersion({{ now: true }})")
    assert f.js(f"{VERSION}.version.ready"), "the new build was found"
    f.run("await new Promise((r) => setTimeout(r, 1500))")
    assert not f.js(f"!!{PROMPT}"), "held while recording"
    f.run("(await import('/js/stores/session.js')).session.recording = false;")
    f.wait(PROMPT, what="the prompt once the take ends")
    assert t(f, "version.ready") in f.js(f"{PROMPT}.innerText"), "says a new version is ready"
    f.shot("new-version")
    # Part way through a lesson: it waits, and shows again back on the map.
    f.run("(await import('/js/router.js')).navigate('/learn/1-match')")
    f.wait("location.pathname === '/learn/1-match' && document.querySelector('main h1')", what="a lesson open")
    f.wait(f"!{PROMPT}", what="held during the lesson")
    f.run("(await import('/js/router.js')).navigate('/learn')")
    f.wait(PROMPT, what="the prompt back on the map")
    f.click(t(f, "version.notNow"), "[data-new-version] button")
    f.wait(f"!{PROMPT}", what="Not now")
    # Found again (another deploy): Refresh reloads into it.
    BUILD.write_text("mock2222222222222222222222222222222222222")
    f.run(f"await {VERSION}.checkVersion({{ now: true }})")
    f.wait(PROMPT, what="the prompt for the next build")
    f.run("window.beforeRefresh = true")
    f.click(t(f, "version.refresh"), "[data-new-version] button")
    f.wait(f"!window.beforeRefresh && {STARTED} && document.querySelector('header')", what="the page reloaded")
    f.wait(f"{STARTED} && {VERSION}.version.loaded === 'mock2222222222222222222222222222222222222'", what="running the new build")
    # Hillel had to click Refresh twice: the new build's own worker taking over the reloaded page
    # isn't a newer version. Once it's in charge, no prompt.
    f.wait("navigator.serviceWorker.controller?.scriptURL.includes('build=mock2222222222222222222222222222222222222')", timeout=30, what="the new build's worker in charge")
    f.run("await new Promise((r) => setTimeout(r, 1000))")
    assert not f.js(f"!!{PROMPT}"), "the prompt came back after one Refresh"


def missing_file(f):
    """A file the build's cache should hold and doesn't (an eviction, a failed write): from the network, never a blank page."""
    f.run("for (const key of await caches.keys()) if (key.startsWith('stackapella-')) await (await caches.open(key)).delete('/js/router.js');")
    f.go("/?api=mock&scenario=", "document.querySelector('header')")
    assert f.js("!!window.stackapellaStarted"), "the app didn't start with a file missing from its cache"


def kill_switch(f):
    """A page stuck on a broken kept build (its main.js broken in the cache): the kill switch brings it back whole."""
    f.expected_errors.append("Unexpected token ';'")  # the broken main.js planted below, as it fails
    f.run("""for (const key of await caches.keys()) if (key.startsWith('stackapella-'))
        await (await caches.open(key)).put('/js/main.js', new Response('export const broken = ;', { headers: { 'Content-Type': 'text/javascript' } }));""")
    f.go("/?api=mock&scenario=", "document.body")
    f.wait("!window.stackapellaStarted && !!document.querySelector('[data-boot-failed]')", timeout=20, what="the broken build stuck")
    KILL.write_text("on")  # the deploy that sets KILL
    f.go("/?api=mock&scenario=", "document.body")  # the next open: the browser checks sw.js, and the kill worker takes over
    f.wait("!!window.stackapellaStarted && !!document.querySelector('header') && !navigator.serviceWorker.controller", timeout=40, what="the page back, whole, with no worker")
    assert f.js("caches.keys().then((keys) => keys.filter((k) => k.startsWith('stackapella-')).length)") == 0, "a cache of ours kept"
    KILL.unlink()


if __name__ == "__main__":
    run("deploy", journey, sizes=("desktop",))
