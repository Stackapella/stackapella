"""A deploy, as the browser sees it: the app loads (its service worker in control), a module changes on
the server, and the next visit gets the new one, never the old, for a module imported after the page
loads (as the admin and Sing are) and for the admin's own code, which the service worker never keeps.
It depends on the server's `Cache-Control: no-cache` (tool/serve.php, as .do/nginx.conf): with a
max-age, the browser's memory cache answers such a module before the service worker sees it.
Then "A new version is ready": the server's build changes (tool/mock-build.txt), and the prompt
shows, but not while a take is being recorded or a lesson is under way; Not now hides it; Refresh
reloads into the new build.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

WEB = Path(__file__).resolve().parents[2]
PROBES = [WEB / "js" / "deploy-probe.js", WEB / "js" / "admin" / "deploy-probe.js"]
BUILD = WEB / "tool" / "mock-build.txt"
VERSION = "(await import('/js/version.js'))"
PROMPT = "document.querySelector('[data-new-version]')"


def write(version):
    for probe in PROBES:
        probe.write_text(f"// A file for tests/flows/deploy.py only: removed when the flow ends.\nexport const VERSION = {version};\n")


def versions(f):
    return f.js("Promise.all([import('/js/deploy-probe.js'), import('/js/admin/deploy-probe.js')]).then(([a, b]) => [a.VERSION, b.VERSION].join())")


def journey(f):
    try:
        write(1)
        f.go("/?api=mock&scenario=", "document.querySelector('header')")
        f.wait("!!navigator.serviceWorker.controller", what="the service worker in control")
        assert versions(f) == "1,1", versions(f)
        write(2)  # the deploy
        f.go("/?api=mock&scenario=", "document.querySelector('header')")
        assert versions(f) == "2,2", f"an old module after the deploy: {versions(f)}"
        # Old words beside new code (as an old worker once served): the screen ends with real words,
        # never keys.
        title = t(f, "auth.signInTitle")
        f.run("const { locale } = await import('/js/i18n.js'); delete locale.english.auth; delete locale.messages.auth;")
        f.run("(await import('/js/router.js')).navigate('/signin')")
        f.wait(f"document.querySelector('h1')?.textContent.trim() === {json.dumps(title)}", what="real words after fetching them again")
        assert not f.js("/\\bauth\\.[a-z]/.test(document.body.innerText)"), "a raw key on screen"
        new_version(f)
    finally:
        for probe in PROBES:
            probe.unlink(missing_ok=True)
        BUILD.unlink(missing_ok=True)


def t(f, key):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)})")


def new_version(f):
    f.go("/?api=mock&scenario=", "document.querySelector('header')")
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
    f.wait("!window.beforeRefresh && document.querySelector('header')", what="the page reloaded")
    f.wait(f"{VERSION}.version.loaded === 'mock2222222222222222222222222222222222222'", what="running the new build")
    # Hillel had to click Refresh twice: the new build's own worker taking over the reloaded page
    # isn't a newer version. Once it's in charge, no prompt.
    f.wait("navigator.serviceWorker.controller?.scriptURL.includes('build=mock2222222222222222222222222222222222222')", timeout=30, what="the new build's worker in charge")
    f.run("await new Promise((r) => setTimeout(r, 1000))")
    assert not f.js(f"!!{PROMPT}"), "the prompt came back after one Refresh"


if __name__ == "__main__":
    run("deploy", journey, sizes=("desktop",))
