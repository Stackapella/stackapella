"""Settings links to the legal pages (the finish-line audit, 2026-11-13: the app linked to none): Terms, Privacy, Community
guidelines and Accessibility under Contact, as the site's footer lists them, each to its page on the public site, named as a
group for a screen reader, a guest's and a member's Settings alike, at phone and desktop size.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

PAGES = ["/legal/terms", "/legal/privacy", "/legal/community-guidelines", "/legal/accessibility"]
LINKS = "[...document.querySelectorAll('[data-legal] a')].map((a) => [a.innerText.trim(), new URL(a.href).pathname, Math.round(a.getBoundingClientRect().height)])"


def journey(f):
    for who in ("", "&as=member"):
        f.go(f"/settings?api=mock{who}", "document.querySelector('[data-legal] a')")
        links = f.js(LINKS)
        assert [path for _, path, _ in links] == PAGES, f"the legal links: {links}"
        assert all(name for name, _, _ in links), f"a legal link without words: {links}"
        assert all(height >= 24 for _, _, height in links), f"a legal link under 24 px tall: {links}"
        assert f.js("document.querySelector('[data-legal]').getAttribute('aria-label')"), "the legal links aren't named as a group"
        f.run("document.querySelector('[data-legal]').scrollIntoView({ block: 'center' })")
        f.no_sideways("Settings with its legal links")
        f.shot("legal-" + ("member" if who else "guest"))


run("settings_legal", journey)
