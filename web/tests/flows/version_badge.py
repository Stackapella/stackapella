"""No build badge (Hillel, 2026-10-27: "we can remove the version now"): nobody sees the build on
screen, an admin included, before or after a deploy. "A new version is ready" still comes
(tests/flows/deploy.py), and the build goes with a message from Contact.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def shown(f, build):
    return f.js(f"[...document.querySelectorAll('button, span, p')].some((e) => e.textContent.includes({build!r}))")


def journey(f):
    for who in ("member", "signedOut", "admin"):
        f.go(f"/?api=mock&scenario=&as={who}", "document.querySelector('#app header')")
        time.sleep(0.8)
        assert not shown(f, "mock000"), f"the build shows for {who}"
    # A deploy: the prompt comes, and still no build on screen.
    f.run("const real = window.fetch; window.fetch = (url, init) => (String(url) === '/up' ? Promise.resolve(new Response(JSON.stringify({ status: 'ok', build: 'abcdef1234567', builtAt: '2026-10-26T19:42:07Z' }), { headers: { 'Content-Type': 'application/json' } })) : real(url, init));")
    f.run("await (await import('/js/version.js')).checkVersion({ now: true })")
    f.wait("document.querySelector('[data-new-version]')", what="the new version prompt")
    assert not shown(f, "abcdef1"), "the new build shows on screen"
    f.go("/settings?api=mock&scenario=&as=admin", "document.querySelector('h1')")
    time.sleep(0.5)
    assert not shown(f, "mock000"), "the build shows in Settings"


if __name__ == "__main__":
    run("version_badge", journey)
