"""Growth's Sources (admin.sources.show): every source with how far its arrivals got, as a table on
wider screens and a card a source on phones, sortable on each column (later steps by their share of
arrivals), the folded small sources always last, over 7, 30 or 90 days. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# The rows in view: the table's on wider screens, the cards' on phones.
ROWS = "[...document.querySelectorAll('[data-sources-table] tbody tr, [data-sources-cards] li')].filter((r) => r.offsetParent)"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def order(f):
    return f.js(f"{ROWS}.map((r) => r.dataset.source).join(',')")


def sort_by(f, key):
    f.run(f"""const button = document.querySelector('[data-sort={key}]');
        if (button?.offsetParent) button.click();
        else {{ const s = document.querySelector('[data-sources-sort]'); s.value = '{key}'; s.dispatchEvent(new Event('change')); }}""")


def journey(f):
    # From the Overview's table, All sources.
    f.go("/admin/growth?api=mock&as=admin", "document.querySelector('[data-growth-all-sources]')")
    f.run("document.querySelector('[data-growth-all-sources]').click()")
    f.wait(f"location.pathname === '/admin/sources' && {ROWS}.length", what="the sources")
    f.run("const { api } = await import('/js/api.js'); const show = api.admin.sources.show; api.admin.sources.show = (q) => { (window.sourcesQueries ??= []).push(q); return show(q); };")

    # Most arrivals first, the folded row last; a campaign tag is shown as written.
    assert order(f) == "none,hn,harmony,small", order(f)
    assert f.js(f"{ROWS}.find((r) => r.dataset.source === 'hn').querySelector('[translate=no]').textContent.trim()") == "hn"
    assert t(f, "admin.growth.source.small", {"count": 3}) in f.js(f"{ROWS}.at(-1).innerText")

    # Lesson 1 sorts by its share of each source's arrivals; the small row stays last.
    sort_by(f, "lesson1")
    f.wait(f"{ROWS}.map((r) => r.dataset.source).join(',') === 'harmony,none,hn,small'", what="sorted by Lesson 1's share")
    assert "53.7%" in f.js(f"{ROWS}.find((r) => r.dataset.source === 'hn').innerText"), "hn's Lesson 1 share"
    sort_by(f, "week1")
    f.wait(f"{ROWS}.map((r) => r.dataset.source).join(',') === 'harmony,none,hn,small'", what="sorted by week 1's share")
    f.no_sideways("the sources")
    f.shot("sources")

    # Ninety days asks for ninety.
    f.click(t(f, "admin.usage.days", {"count": 90}), "[role=radiogroup] button")
    f.wait(f"window.sourcesQueries?.at(-1)?.days === 90 && {ROWS}.length", what="ninety days")


if __name__ == "__main__":
    run("admin_sources", journey, sizes=("desktop", "phone"))
