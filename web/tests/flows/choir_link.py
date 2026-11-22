"""The site's "Try it with <song>" link (/?song=…&choir=1&ref=your-choir) opens that song's choir page with no flash of
Home first (Hillel, 2026-11-23), on a cold load and on a warm one (the app already cached).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

def journey(f):
    for load in ("cold", "warm"):
        f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
        f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1')")
        # Home never paints on the way: the app's first route is already the choir page.
        f.go("/?api=mock&scenario=&as=member&song=happy-birthday&choir=1&ref=your-choir", "location.pathname === '/t/happy-birthday/choir'")
        assert f.js("document.documentElement.dataset.firstRoute") == "/t/happy-birthday/choir", f"{load}: Home came first"
        assert "ref=your-choir" in f.js("location.search"), f"{load}: the ref was lost"
        assert not f.js("!!document.querySelector('#home-songs')"), f"{load}: Home is showing"
        assert f.js("sessionStorage.getItem('stackapella.choirFromSite')") == "happy-birthday", f"{load}: the site's song wasn't kept"
        f.shot(f"choir-link-{load}")


run("choir_link", journey)
