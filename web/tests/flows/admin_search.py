"""Admin > Growth > Search (Search Console, Hillel): the pages to improve first, with why; the totals;
the top pages and searches by clicks; the sitemaps. And, until the credentials are set, an honest
"Not connected yet" pointing at docs/search-console.md (the example with `connected` false).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin/search?api=mock&scenario=", "document.querySelector('[data-search] [data-to-improve]')")
    assert f.js("document.querySelector('[data-tile=clicks] .text-4xl').textContent.trim()") == "412"
    improve = f.js("[...document.querySelectorAll('[data-improve]')].map((r) => r.dataset.improve)")
    assert improve and all(p.startswith("/") for p in improve), improve
    assert f.js("!!document.querySelector('[data-to-improve] [data-reason]')"), "each page says why it's on the list"
    # The list comes before the top pages and searches.
    assert f.js("document.querySelector('[data-to-improve]').compareDocumentPosition(document.querySelector('[data-search-top]')) & Node.DOCUMENT_POSITION_FOLLOWING"), "the list first"
    f.shot("search")
    f.run("[...document.querySelectorAll('[data-search-top] [role=radio]')][1].click()")
    f.wait("document.querySelector('[data-row=queries]')", what="by search")
    f.run("document.querySelector('[data-search-top]').scrollIntoView({ block: 'center' })")
    f.shot("queries")
    # Not connected: the empty state, no numbers.
    f.run("""const mock = await import('/js/api-mock.js'); const { api } = await import('/js/api.js');
        api.admin.search.show = async () => ({ data: { connected: false, syncedAt: null, from: null, to: null, error: null, totals: { clicks: 0, impressions: 0, ctr: 0 }, pages: [], queries: [], toImprove: [], sitemaps: [], rules: { minImpressions: 50, lowCtr: 0.02, nearTop: [8, 20] } } });""")
    f.run("document.querySelector('a[href=\"/admin/revenue\"]').click()")
    f.wait("document.querySelector('[data-revenue]')", what="another tab")
    f.run("document.querySelector('a[href=\"/admin/search\"]').click()")
    f.wait("document.querySelector('[data-search-off]')", what="Not connected yet")
    assert t(f, "admin.search.offTitle") in f.js("document.querySelector('[data-search-off]').textContent")
    assert not f.js("!!document.querySelector('[data-tile]')"), "no numbers while not connected"
    f.shot("not-connected")


if __name__ == "__main__":
    run("admin_search", journey)
