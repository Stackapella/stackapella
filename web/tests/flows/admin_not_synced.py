"""Admin Home's "What needs you now" with the songs the last deploy's sync couldn't load (admin.overview's
songsNotSynced) and the weekly sitemap check's problems (sitemapProblems, "and N more" past 5): an item each, "2 songs didn't sync on the last deploy", its lines (the file and why) on expand, each song
still on its live version, and counted on Home in the nav. Nothing when the list is empty (the contract's example).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
CONTRACT = Path(__file__).resolve().parents[3] / "contract" / "examples"
OVERVIEW = json.loads((CONTRACT / "admin.overview.200.json").read_text())
LINES = ["songs/amazing-grace.json: parts.alto: an unknown voice range", "songs/shenandoah.json: the file isn't valid JSON (line 41)"]
OVERVIEW["data"]["songsNotSynced"] = LINES
# The sitemap check's cap: the first 5, then "and N more".
PAGES = [f"/songs/part-{i}: noindex on a page in the sitemap" for i in range(1, 6)] + ["and 2 more"]
OVERVIEW["data"]["sitemapProblems"] = PAGES
# The overview with two songs that didn't sync; everything else from the contract.
NOT_SYNCED = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    api.useTransport(async (url, init = {}) => init.operation === 'admin.overview'
        ? new Response(%s, { status: 200, headers: { 'Content-Type': 'application/json' } })
        : mockFetch(url, init));""" % json.dumps(json.dumps(OVERVIEW))

ITEM = "document.querySelector('[data-admin-needs] [data-needs-list=notSynced]')"
SITEMAP = "document.querySelector('[data-admin-needs] [data-needs-list=sitemap]')"
HOME_COUNT = "document.querySelector('[data-nav-sections] a[href=\"/admin\"] span')"
GROUP_COUNT = "(+document.querySelector('[data-nav-groups] a[href=\"/admin\"] [data-group-count]')?.textContent || 0)"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # The contract's example: a clean sync, so nothing is said or counted.
    f.go("/admin?api=mock&scenario=", "document.querySelector('main')")
    f.wait("document.querySelector('[data-admin-needs]') && document.querySelector('[data-admin-needs]').innerText.trim().length > 30", what="What needs you now")
    assert not f.js(f"!!{ITEM} || !!{SITEMAP}"), "a clean sync and a clean sitemap say nothing"
    f.wait("document.querySelector('[data-nav-sections]')", what="Today's pages")
    assert not f.js(f"!!{HOME_COUNT}"), "and counts nothing on Home"
    # Today's count once the overview is in (the review queue and feedback, from the contract's example).
    f.wait("document.querySelector('[data-nav-groups] a[href=\"/admin\"] [data-group-count]')", what="Today's count")
    clean = f.js(GROUP_COUNT)

    # Two songs didn't sync: one item, counted on Home, its lines on expand.
    f.go("/admin/sources?api=mock&scenario=", "document.querySelector('main')")
    f.run(NOT_SYNCED)
    f.run("(await import('/js/router.js')).navigate('/admin')")
    f.wait(ITEM, what="the songs that didn't sync")
    summary = f.js(f"{ITEM}.querySelector('summary').innerText.trim()")
    assert summary.startswith(t(f, "admin.needs.notSynced.title", {"count": 2})), summary
    assert summary.startswith("2 songs didn't sync on the last deploy"), summary
    assert not f.js(f"{ITEM}.open"), "the lines wait for a tap"
    f.run(f"{ITEM}.querySelector('summary').click()")
    lines = f.js(f"[...{ITEM}.querySelectorAll('[data-need-line]')].map((l) => l.textContent.trim())")
    assert lines == LINES, lines
    assert t(f, "admin.needs.notSynced.note") in f.js(f"{ITEM}.innerText"), "each still on its live version"
    f.wait(HOME_COUNT, what="Home's count")
    # The sitemap check: its own item, counted with what the "and 2 more" line stands for.
    assert f.js(f"{SITEMAP}.querySelector('summary').innerText.trim()").startswith("Search check: 7 page problems")
    f.run(f"{SITEMAP}.querySelector('summary').click()")
    assert f.js(f"[...{SITEMAP}.querySelectorAll('[data-need-line]')].map((l) => l.textContent.trim())") == PAGES
    assert f.js(f"{HOME_COUNT}.textContent.trim()") == "9", f.js(f"{HOME_COUNT}.textContent")
    assert f.js(GROUP_COUNT) == clean + 9, f"and in Today's count: {f.js(GROUP_COUNT)} against {clean} before"
    f.no_sideways("What needs you now")
    f.run(f"{ITEM}.scrollIntoView({{ block: 'center' }})")
    f.shot("not-synced")


if __name__ == "__main__":
    run("admin_not_synced", journey, sizes=("desktop", "phone"))
