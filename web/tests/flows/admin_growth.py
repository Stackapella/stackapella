"""Growth's Overview (as Hillel redrew it) (admin.growth.show, admin.sources.show, admin.funnel.show): four tiles against
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


def journey(f):
    f.go("/admin/growth?api=mock&as=admin", "document.querySelector('[data-growth-tiles] li')")
    f.run("const { api } = await import('/js/api.js'); const show = api.admin.growth.show; api.admin.growth.show = (q) => { (window.growthQueries ??= []).push(q); return show(q); };")

    # The cards, one layout: the number, its share of arrivals, then labelled rows against yesterday and
    # last week (a percentage from 20 up, a number under it), up and down said with an arrow and a sign.
    rows = lambda key: f.js(f"[...document.querySelectorAll('[data-tile={key}] [data-change]')].map((r) => r.dataset.dir + ' ' + r.querySelector('dd').textContent.trim())")
    assert rows("arrivals") == ["up ↑+11%", "up ↑+40%"], rows("arrivals")
    assert rows("signedUp") == ["up ↑+2", "up ↑+3"], rows("signedUp")
    assert rows("plusStarted")[1] == "same " + t(f, "admin.growth.same"), rows("plusStarted")
    assert f.js("document.querySelector('[data-tile=arrivals] dt').textContent.trim()") == t(f, "admin.growth.vsYesterday")
    assert "59.5%" in f.js("document.querySelector('[data-tile=lesson1] [data-share]').textContent"), "lesson 1 as a share of arrivals"
    assert "2.4%" in f.js("document.querySelector('[data-tile=plusStarted] [data-share]').textContent"), "Plus as a share of arrivals"
    assert not f.js("document.querySelector('[data-tile=arrivals] [data-share]')"), "arrivals are the base"
    # Low sample under 20; none above.
    assert f.js("!!document.querySelector('[data-tile=signedUp] [data-few]') && !document.querySelector('[data-tile=arrivals] [data-few]')"), "Low sample"

    # How far they got: a row a step with devices and share; between each pair what didn't continue (or how
    # many fewer, where the next isn't a part of the one before), the biggest real drop marked and said.
    assert f.js("document.querySelectorAll('[data-growth-steps] [data-step]').length") == 7
    lost = f.js("[...document.querySelectorAll('[data-growth-lost]')].map((l) => l.innerText)")
    assert len(lost) == 6 and not any("-" in x.split("·")[0] for x in lost), f"drop lines: {lost}"
    assert t(f, "admin.growth.fewer", {"share": "23.6%"}) in lost[1], f"Start to In the app is fewer, not a loss: {lost[1]}"
    biggest = f.js("[...document.querySelectorAll('[data-growth-lost][data-biggest]')].map((l) => l.innerText)")
    assert len(biggest) == 1 and "83.3%" in biggest[0] and t(f, "admin.growth.biggestDrop") in biggest[0], biggest

    # Where they came from: always the last 7 days, sorted by day-2 return; the folded row stays last.
    f.wait("document.querySelector('[data-growth-sources] tbody tr')", what="the sources")
    assert f.js("!!document.querySelector('[data-growth-last7]')"), "Always last 7 days"
    order = lambda: f.js("[...document.querySelectorAll('[data-growth-sources] tbody tr')].map((r) => r.dataset.source).join(',')")
    assert order() == "harmony,none,hn,small", order()
    f.run("document.querySelector('[data-sort=arrivals]').click()")
    f.wait("document.querySelector('[data-sort=arrivals]').closest('th').getAttribute('aria-sort') === 'descending'", what="sorted by arrivals")
    assert order() == "none,hn,harmony,small", order()
    assert t(f, "admin.growth.source.noneHint") in f.js("document.querySelector('[data-source=none] th').innerText"), "No tag says what it means"

    # The experiment, in one card that opens the Funnel.
    assert f.js("!!document.querySelector('[data-growth-experiment] h2')"), "the experiment's card"

    # Today: when it was updated, and Refresh asks again for today.
    f.wait("document.querySelector('[data-growth-updated]')", what="when it was updated")
    f.click(t(f, "admin.usage.refresh"), "[data-growth-updated] button")
    f.wait("window.growthQueries?.at(-1)?.days === 1", what="today again")
    # People using the app follows the switch (Hillel: "today/7 day/30 days all show the same thing"):
    # today, two rows (today, or the last whole day, beside the same weekday a week before).
    f.wait("document.querySelector('[data-reach-chart][data-view=today] [data-today]')", what="the chart's today")
    assert f.js("document.querySelectorAll('[data-reach-chart] [data-today] [data-row]').length") == 2, "today and a week before"
    assert t(f, "admin.reach.todaySoFar") in f.js("document.querySelector('[data-reach-chart] [data-today]').textContent"), "today so far, from the server"
    f.no_sideways("the overview")
    f.shot("overview")
    for part in ("steps", "sources"):
        f.run(f"document.querySelector('[data-growth-{part}]').scrollIntoView({{ block: 'start' }})")
        f.shot(part)
    f.run("window.scrollTo(0, 0)")

    # A refresh that fails keeps the numbers and says so.
    f.run("const { api } = await import('/js/api.js'); window.realGrowth = api.admin.growth.show; api.admin.growth.show = async () => { throw new Error('offline'); };")
    f.click(t(f, "admin.usage.refresh"), "[data-growth-updated] button")
    f.wait("document.querySelector('[data-growth-updated]').textContent.includes(" + json.dumps(t(f, "admin.usage.notUpdated", {"when": "~"}).split("~")[0]) + ")", what="couldn't update")
    assert rows("arrivals")[0] == "up ↑+11%", "the numbers stay"
    f.run("const { api } = await import('/js/api.js'); api.admin.growth.show = (q) => { (window.growthQueries ??= []).push(q); return window.realGrowth(q); };")

    # Thirty days: the steps and the cards over the period, the sources still a week.
    f.run("const { api } = await import('/js/api.js'); const show = api.admin.sources.show; api.admin.sources.show = (q) => { (window.sourcesQueries ??= []).push(q); return show(q); };")
    f.click(t(f, "admin.usage.days", {"count": 30}), "[role=radiogroup] button")
    f.wait("window.growthQueries?.at(-1)?.days === 30 && document.querySelector('[data-growth-tiles] li')", what="thirty days")
    # The cards follow the period: against the 30 days before and the 30 ending a week ago.
    labels = f.js("[...document.querySelectorAll('[data-tile=arrivals] dt')].map((d) => d.textContent.trim())")
    assert labels == [t(f, "admin.growth.vsDaysBefore", {"count": 30}), t(f, "admin.growth.vsWeekEarlier")], labels
    assert f.js("window.sourcesQueries?.at(-1)?.days") == 7, "the sources stay a week"
    # The chart too: a bar a day for 30 days, each beside the day 30 earlier; then 7.
    f.wait("document.querySelector('[data-reach-chart]').dataset.view === 'days' && document.querySelectorAll('[data-reach-chart] [data-bar]').length === 30", what="30 daily bars")
    f.run("document.querySelector('[data-reach-chart]').scrollIntoView({ block: 'center' })")
    f.shot("reach-30")
    f.click(t(f, "admin.usage.days", {"count": 7}), "[role=radiogroup] button")
    f.wait("document.querySelectorAll('[data-reach-chart] [data-bar]').length === 7", what="7 daily bars")
    assert f.js("document.querySelector('[data-reach-chart] [data-bar]').title").find(t(f, "admin.reach.dayBefore", {"count": 7, "total": "~", "change": "~"}).split("~")[0]) >= 0, "each day beside the day a week earlier"
    f.shot("reach-7")
    # 12 weeks: the cards over 84 days, and the chart a week at a time beside the week 12 earlier.
    f.click(t(f, "admin.growth.weeks12"), "[role=radiogroup] button")
    f.wait("window.growthQueries?.at(-1)?.days === 84 && document.querySelector('[data-reach-chart]').dataset.view === 'weeks'", what="12 weeks")
    f.wait("document.querySelectorAll('[data-reach-chart] [data-bar]').length === 12", what="12 weekly bars")
    labels = f.js("[...document.querySelectorAll('[data-tile=arrivals] dt')].map((d) => d.textContent.trim())")
    assert labels[0] == t(f, "admin.growth.vsWeeksBefore"), labels
    f.run("document.querySelector('[data-reach-chart]').scrollIntoView({ block: 'center' })")
    f.shot("reach-12-weeks")
    assert f.js("!!document.querySelector('[data-growth-updated] button')"), "Refresh over days too"

    # One header bar; the Growth pages as underline tabs, Overview first, Sources kept, Campaigns last.
    pages = json.loads(f.js("JSON.stringify([...document.querySelectorAll('[data-nav-sections] a')].map((a) => a.getAttribute('href')))"))
    assert pages == ["/admin/growth", "/admin/revenue", "/admin/cohorts", "/admin/activity", "/admin/search", "/admin/funnel", "/admin/experiments", "/admin/sources", "/admin/usage", "/admin/mudeo"], pages
    assert f.js("document.querySelector('[data-nav-sections] [aria-current=page]').getAttribute('href')") == "/admin/growth"
    assert f.js("document.querySelectorAll('header [data-nav-groups] a').length") == 5, "every group in the bar"
    # The account: its initials, opening its name and Sign out.
    f.run("const summary = document.querySelector('[data-admin-account]'); summary.focus(); summary.click();")
    f.wait(f"document.querySelector('[data-admin-account]').parentElement.open && document.querySelector('[data-admin-account]').parentElement.innerText.includes({json.dumps(t(f, 'admin.signOut'))})", what="the account's Sign out")
    f.press("Escape")
    f.wait("!document.querySelector('[data-admin-account]').parentElement.open && document.activeElement === document.querySelector('[data-admin-account]')", what="closed, focus back")

if __name__ == "__main__":
    run("admin_growth", journey, sizes=("desktop", "phone"))
