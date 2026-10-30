"""Growth's Overview (admin.growth.show, admin.sources.show, admin.funnel.show): four tiles against
yesterday and last week, how far they got with what didn't go on between nested steps, where they
came from with the small sources folded last, and the experiment's card. Today so far opens first,
with when it was updated and Refresh; a failed refresh keeps the numbers. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def tile(f, key):
    return f.js(f"document.querySelector('[data-tile={key}]').innerText.replace(/\\s+/g, ' ')")


def journey(f):
    f.go("/admin/growth?api=mock&as=admin", "document.querySelector('[data-growth-tiles] li')")
    f.run("const { api } = await import('/js/api.js'); const show = api.admin.growth.show; api.admin.growth.show = (q) => { (window.growthQueries ??= []).push(q); return show(q); };")

    # The tiles: a change as a percentage from 20 up, as a number under it, with a sign and words.
    arrivals = tile(f, "arrivals")
    assert "+11% vs" in arrivals and "+40% vs" in arrivals, arrivals
    signed = tile(f, "signedUp")
    assert "+2 vs" in signed and t(f, "admin.growth.few") in signed, signed
    assert t(f, "admin.growth.ofArrivals") in tile(f, "lesson1"), "lesson 1 as a share of arrivals"
    assert "same as" in tile(f, "plusStarted"), tile(f, "plusStarted")

    # How far they got: a bar a step, and losses only where a step is part of the one before.
    assert f.js("document.querySelectorAll('[data-growth-steps] [data-step]').length") == 7
    lost = f.js("[...document.querySelectorAll('[data-growth-lost]')].map((l) => l.innerText)")
    assert len(lost) == 4 and not any("-" in x for x in lost), f"lost lines: {lost}"
    assert not f.js("document.querySelector('[data-step=start] [data-growth-lost]')"), "no loss from Start to arrivals (they also come straight in)"

    # Where they came from: the folded row stays last, whatever the sort.
    f.wait("document.querySelector('[data-growth-sources] tbody tr')", what="the sources")
    assert f.js("[...document.querySelectorAll('[data-growth-sources] tbody tr')].at(-1).dataset.source") == "small"
    f.run("document.querySelector('[data-sort=day2]').click()")
    f.wait("document.querySelector('[data-sort=day2]').closest('th').getAttribute('aria-sort') === 'descending'", what="sorted by day 2")
    order = f.js("[...document.querySelectorAll('[data-growth-sources] tbody tr')].map((r) => r.dataset.source).join(',')")
    assert order == "harmony,none,hn,small", order

    # The experiment, in one card that opens the Funnel.
    assert f.js("!!document.querySelector('[data-growth-experiment] h2')"), "the experiment's card"

    # Today: when it was updated, and Refresh asks again for today.
    f.wait("document.querySelector('[data-growth-updated]')", what="when it was updated")
    f.click(t(f, "admin.usage.refresh"), "[data-growth-updated] button")
    f.wait("window.growthQueries?.at(-1)?.days === 1", what="today again")
    f.no_sideways("the overview")
    f.shot("overview")

    # A refresh that fails keeps the numbers and says so.
    f.run("const { api } = await import('/js/api.js'); window.realGrowth = api.admin.growth.show; api.admin.growth.show = async () => { throw new Error('offline'); };")
    f.click(t(f, "admin.usage.refresh"), "[data-growth-updated] button")
    f.wait("document.querySelector('[data-growth-updated]').textContent.includes(" + json.dumps(t(f, "admin.usage.notUpdated", {"when": "~"}).split("~")[0]) + ")", what="couldn't update")
    assert "+11% vs" in tile(f, "arrivals"), "the numbers stay"
    f.run("const { api } = await import('/js/api.js'); api.admin.growth.show = (q) => { (window.growthQueries ??= []).push(q); return window.realGrowth(q); };")

    # Seven days: no Refresh, the range said.
    f.click(t(f, "admin.usage.days", {"count": 7}), "[role=radiogroup] button")
    f.wait("window.growthQueries?.at(-1)?.days === 7 && document.querySelector('[data-growth-tiles] li')", what="seven days")
    assert f.js("!document.querySelector('[data-growth-updated]')"), "no Refresh over days"
    assert f.js("!!document.querySelector('[data-growth-tiles-today]')"), "the tiles still say they're today's"

    # The Growth group: Overview first, Campaigns last; Links has moved to People.
    nav = f.js("JSON.stringify([...document.querySelectorAll('header a[href^=\"/admin/\"]')].map((a) => a.getAttribute('href')))")
    hrefs = json.loads(nav)
    assert hrefs.index("/admin/growth") < hrefs.index("/admin/funnel") < hrefs.index("/admin/usage") < hrefs.index("/admin/mudeo"), hrefs
    assert "/admin/links" not in hrefs, "Links is in People, not Growth"


if __name__ == "__main__":
    run("admin_growth", journey, sizes=("desktop", "phone"))
