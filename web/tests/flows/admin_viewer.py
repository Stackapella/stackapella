"""A growth viewer (contract admin.me's role `viewer`; Hillel, 2026-11-23: an advisor who reads the numbers): the admin
opens on Growth, its bar shows only Growth, Funnel, Drop-offs and Sources, Home or any other page lands on Growth, and
nothing they can't open is asked for (the server answers those not_admin): no overview and no site counts; the reach
chart is theirs (backend allowed admin.reach.show).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

ALLOWED = ["admin.me", "admin.growth.show", "admin.growth.choirs", "admin.growth.export", "admin.growth.ai", "admin.funnel.show", "admin.dropoffs.show", "admin.sources.show", "admin.reach.show"]
CALLS = "(globalThis.mockCalls ?? []).filter((op) => op.startsWith('admin.'))"


def asked(f):
    return sorted(set(f.js(CALLS)))


def journey(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('h1')")
    # The examples' admin, said to be a viewer; what the admin asks the server for, from here.
    f.run("""const { api } = await import('/js/api.js'); const me = api.admin.me;
        api.admin.me = async () => { const r = await me(); return { ...r, data: { ...r.data, role: 'viewer' } }; };
        globalThis.mockCalls = [];""")
    f.run("(await import('/js/router.js')).navigate('/admin')")
    f.wait("location.pathname === '/admin/growth' && document.querySelector('[data-growth]')", what="Growth, opened in place of Home")
    tabs = f.js("[...document.querySelectorAll('[data-nav-sections] a')].map((a) => a.getAttribute('href'))")
    assert tabs == ["/admin/growth", "/admin/funnel", "/admin/dropoffs", "/admin/sources"], tabs
    assert f.js("document.querySelectorAll('[data-nav-groups] li').length") == 1, "another group on a viewer's bar"
    f.wait("document.querySelector('[data-growth-choirs] [data-n]')", what="the AI choirs")
    assert not f.js("document.querySelector('[data-home-growth], [data-growth-site]')"), "a card a viewer can't open"
    f.wait("document.querySelector('[data-reach-chart]')", what="the reach chart, theirs to read")
    # The numbers as CSV (Hillel, for Saul): a viewer may take them, the chart's for the period shown.
    # Only a download is caught: every other link's click goes through (the nav's links come next).
    f.run("window.saved = []; const real = HTMLAnchorElement.prototype.click; HTMLAnchorElement.prototype.click = function () { if (this.download) window.saved.push(this.download); else real.call(this); };")
    f.run("document.querySelector('[data-csv=growth]').click()")
    f.wait("window.saved.length === 1", what="the chart's CSV")
    assert f.js("window.saved[0]").endswith(".csv"), f.js("window.saved[0]")
    f.run("document.querySelector('[data-csv=choirs]').click()")
    f.wait("window.saved.length === 2", what="the AI choirs' CSV")
    f.wait("document.querySelector('[data-csv=ai]')", what="the AI choice card's CSV")
    f.run("document.querySelector('[data-csv=ai]').click()")
    f.wait("window.saved.length === 3", what="the AI choice card's CSV saved")
    f.no_sideways("a viewer's Growth")
    f.shot("growth")
    # A page that isn't theirs: Growth, and the address says so.
    f.run("(await import('/js/router.js')).navigate('/admin/people')")
    f.wait("location.pathname === '/admin/growth'", what="People lands on Growth")
    for path, ready in (("/admin/funnel", "#funnel-h"), ("/admin/dropoffs", "[data-dropoffs]"), ("/admin/sources", "[data-sources]")):
        f.run(f"document.querySelector('[data-nav-sections] a[href=\"{path}\"]').click()")
        f.wait(f"location.pathname === '{path}' && document.querySelector('{ready}')", what=path)
        before = f.js("window.saved?.length ?? 0")
        f.run("document.querySelector('[data-csv]').click()")
        f.wait(f"(window.saved?.length ?? 0) === {before} + 1", what=f"{path}'s CSV")
    f.shot("sources")
    extra = [op for op in asked(f) if op not in ALLOWED]
    assert not extra, f"a viewer's admin asked for {extra}"


if __name__ == "__main__":
    run("admin_viewer", journey, sizes=("desktop", "phone"))
