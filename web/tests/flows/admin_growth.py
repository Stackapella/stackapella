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


# A server from before the fair comparisons (no metrics, tracking or period): the cards of before, read from `tiles`.
# Its answer is the example from then (tests/fixtures/growth-before-compare.json), so these checks keep their numbers.
OLD_SERVER = """const { api } = await import('/js/api.js'); const old = await (await fetch('/tests/fixtures/growth-before-compare.json')).json();
api.admin.growth.show = async (q) => { (window.growthQueries ??= []).push(q); return JSON.parse(JSON.stringify(old)); };"""


def pick(f, key):
    """Chooses a period as a person would: the compact menu on a phone, the segments from sm up."""
    if f.size == "phone":
        f.run(f"const m = document.querySelector('[data-growth-period-select]'); m.value = '{key}'; m.dispatchEvent(new Event('change'))")
    else:
        f.click(t(f, f"admin.growth.periods.{key}"), "[data-growth-periods] button")


def open_growth(f, stub):
    """Growth with its answers through `stub`: set on another admin page, then Growth opened in the app."""
    f.go("/admin/sources?api=mock&as=admin", "document.querySelector('h1')")
    f.run(stub)
    f.run("(await import('/js/router.js')).navigate('/admin/growth')")


def journey(f):
    f.go("/admin/sources?api=mock&as=admin", "document.querySelector('h1')")
    # Start from no remembered choices: Today.
    f.run("Object.keys(localStorage).filter((k) => k.startsWith('stackapella.admin.')).forEach((k) => localStorage.removeItem(k))")
    open_growth(f, OLD_SERVER)
    f.wait("document.querySelector('[data-growth-tiles] li')", what="the cards of before")

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
    f.run("const { api } = await import('/js/api.js'); api.admin.growth.show = window.realGrowth;")

    # 4 weeks (30 days before the fair comparisons): the steps and the cards over the period, the sources still a week.
    f.run("const { api } = await import('/js/api.js'); const show = api.admin.sources.show; api.admin.sources.show = (q) => { (window.sourcesQueries ??= []).push(q); return show(q); };")
    pick(f, "28d")
    f.wait("window.growthQueries?.at(-1)?.period === '28d' && window.growthQueries.at(-1).days === 30 && document.querySelector('[data-growth-tiles] li')", what="4 weeks")
    # The cards follow the period: against the 30 days before and the 30 ending a week ago.
    labels = f.js("[...document.querySelectorAll('[data-tile=arrivals] dt')].map((d) => d.textContent.trim())")
    assert labels == [t(f, "admin.growth.vsDaysBefore", {"count": 30}), t(f, "admin.growth.vsWeekEarlier")], labels
    assert f.js("window.sourcesQueries?.at(-1)?.days") == 7, "the sources stay a week"
    # The chart too: a bar a day for 30 days, each beside the day 30 earlier; then 7.
    f.wait("document.querySelector('[data-reach-chart]').dataset.view === 'days' && document.querySelectorAll('[data-reach-chart] [data-bar]').length === 30", what="30 daily bars")
    f.run("document.querySelector('[data-reach-chart]').scrollIntoView({ block: 'center' })")
    f.shot("reach-30")
    pick(f, "7d")
    f.wait("document.querySelectorAll('[data-reach-chart] [data-bar]').length === 7", what="7 daily bars")
    assert f.js("document.querySelector('[data-reach-chart] [data-bar]').title").find(t(f, "admin.reach.dayBefore", {"count": 7, "total": "~", "change": "~"}).split("~")[0]) >= 0, "each day beside the day a week earlier"
    # Today so far is the last bar (Hillel: "include today as the last day also"): lighter, dashed, named.
    last = "[...document.querySelectorAll('[data-reach-chart] [data-bar]')].at(-1)"
    assert f.js(f"!!{last}.querySelector('[data-so-far]')"), "today isn't the last bar"
    assert f.js(f"{last}.title").startswith(t(f, "admin.reach.daySoFarLabel", {"to": "~", "account": "~", "guest": "~"}).split("~")[0]), f.js(f"{last}.title")
    assert t(f, "admin.reach.todaySoFar") in f.js("document.querySelector('[data-reach-chart]').innerText"), "today so far, said"
    f.run("document.querySelector('[data-reach-chart]').scrollIntoView({ block: 'center' })")
    f.shot("reach-7")
    # The range is remembered (Hillel: "remember the today/7 day/ setting when I leave the page and come back").
    f.run("document.querySelector('[data-nav-sections] a[href=\"/admin/usage\"]').click()")
    f.wait("location.pathname === '/admin/usage'", what="another page")
    f.run("document.querySelector('[data-nav-sections] a[href=\"/admin/growth\"]').click()")
    f.wait("window.growthQueries?.at(-1)?.period === '7d' && document.querySelector('[data-growth-periods] [aria-checked=true]')?.textContent.trim() === " + json.dumps(t(f, "admin.growth.periods.7d")), what="7 days, remembered")
    open_growth(f, OLD_SERVER)
    f.wait("document.querySelector('[data-growth-periods] [aria-checked=true]')?.textContent.trim() === " + json.dumps(t(f, "admin.growth.periods.7d")), what="7 days, after a reload")
    # 12 weeks: the cards over 84 days, and the chart a week at a time beside the week 12 earlier.
    pick(f, "12w")
    f.wait("window.growthQueries?.at(-1)?.period === '12w' && window.growthQueries.at(-1).days === 84 && document.querySelector('[data-reach-chart]').dataset.view === 'weeks'", what="12 weeks")
    f.wait("document.querySelectorAll('[data-reach-chart] [data-bar]').length === 12", what="12 weekly bars")
    labels = f.js("[...document.querySelectorAll('[data-tile=arrivals] dt')].map((d) => d.textContent.trim())")
    assert labels[0] == t(f, "admin.growth.vsWeeksBefore"), labels
    f.run("document.querySelector('[data-reach-chart]').scrollIntoView({ block: 'center' })")
    f.shot("reach-12-weeks")
    assert f.js("!!document.querySelector('[data-growth-updated] button')"), "Refresh over days too"

    # One header bar; the Numbers pages as underline tabs, Overview first (Hillel's menu by job, 2026-11-10).
    pages = json.loads(f.js("JSON.stringify([...document.querySelectorAll('[data-nav-sections] a')].map((a) => a.getAttribute('href')))"))
    assert pages == ["/admin/growth", "/admin/usage", "/admin/activity", "/admin/funnel", "/admin/dropoffs", "/admin/sources", "/admin/breadth", "/admin/sharing", "/admin/cohorts", "/admin/experiments", "/admin/revenue"], pages
    assert f.js("document.querySelector('[data-nav-sections] [aria-current=page]').getAttribute('href')") == "/admin/growth"
    groups = f.js("[...document.querySelectorAll('header [data-nav-groups] a')].map((a) => a.textContent.trim().replace(/\\d+$/, '').trim())")
    assert groups == [t(f, f"admin.nav.group.{k}") for k in ("inbox", "users", "library", "marketing", "numbers", "settings")], groups
    # A phone scrolls the groups in their row; none is cut off once scrolled to, and the page never scrolls sideways.
    f.no_sideways("the six groups")
    # The account: its initials, opening its name and Sign out.
    f.run("const summary = document.querySelector('[data-admin-account]'); summary.focus(); summary.click();")
    f.wait(f"document.querySelector('[data-admin-account]').parentElement.open && document.querySelector('[data-admin-account]').parentElement.innerText.includes({json.dumps(t(f, 'admin.signOut'))})", what="the account's Sign out")
    f.press("Escape")
    f.wait("!document.querySelector('[data-admin-account]').parentElement.open && document.activeElement === document.querySelector('[data-admin-account]')", what="closed, focus back")
    site_sections(f)
    fair_cards(f)
    today_chart(f)
    period_charts(f)


# The fair comparisons (docs/growth-compare-spec.md): backend's example at 10:23 on Mon 9 Nov in London
# (tests/fixtures/growth-compare.json), changed by window.growthVariant (a function of the data) where a case needs it.
# The example answers for Today; asked for another period, it answers for that one (its period and the window before), as
# the server does.
FAIR = """const { api } = await import('/js/api.js'); const fx = await (await fetch('/tests/fixtures/growth-compare.json')).json();
const PERIODS = { today: ['2026-11-09', '2026-11-08', '2026-11-08'], '7d': ['2026-11-03', '2026-10-27', '2026-11-02'], '28d': ['2026-10-13', '2026-09-15', '2026-10-12'], month: ['2026-11-01', '2026-10-01', '2026-10-09'], '12w': ['2026-08-18', '2026-05-26', '2026-08-17'] };
api.admin.growth.show = async (q) => { (window.growthQueries ??= []).push(q); let data = JSON.parse(JSON.stringify(fx.data));
  const p = PERIODS[q.period] ? q.period : 'today'; const [from, before, beforeTo] = PERIODS[p];
  data.period = { ...data.period, key: p, from, early: p === 'today' && data.period.early };
  data.comparisons = [{ key: 'previous', from: before, to: beforeTo, cut: '10:23', scaledDays: null }, ...(['today', '7d'].includes(p) ? [{ key: 'usual', windows: 4, available: 4 }] : [])];
  if (!['today', '7d'].includes(p)) data.metrics.forEach((m) => { m.usual = null; });
  return { data: window.growthVariant ? window.growthVariant(data, q) : data }; };"""


# Each period's words on a card: what the number covers, and the window before (spec 7, F1).
PERIOD_WORDS = {
    "today": ("so far today", "Yesterday by 10:23"),
    "7d": ("in the last 7 days", "The 7 days before, to 10:23"),
    "28d": ("in the last 4 weeks", "The 4 weeks before, to 10:23"),
    "month": ("since Nov 1", "Oct 1 to 9, to 10:23"),
    "12w": ("in the last 12 weeks", "The 12 weeks before"),
}


def card(f, key):
    return f"document.querySelector('[data-growth-metrics] [data-metric={key}]')"


def fair_cards(f):
    """Like with like, said in words before any percentage (spec 3 and 4): the clock line from the server, How we're
    tracking, the period at the top, every verdict's chip (blue up, orange down, with an arrow and a word), too few
    said with both counts, the usual range as a strip, estimated rows, no history, the early-day and break lines."""
    f.run("localStorage.removeItem('stackapella.admin.growth.period')")
    open_growth(f, FAIR)
    f.wait("document.querySelector('[data-growth-metrics] li')", what="the fair cards")
    # The pill that's on and the cards always agree, period by period (the coordinator: a pill on 12 weeks over cards
    # saying "so far today" must never happen).
    for key, (covers, before) in PERIOD_WORDS.items():
        f.run(f"document.querySelector('[data-growth-periods] [data-period=\\'{key}\\']').click()")
        f.wait(f"window.growthQueries.at(-1).period === '{key}' && document.querySelector('[data-growth-periods] [aria-checked=true]')?.dataset.period === '{key}' && document.querySelector('[data-growth-metrics] [data-metric=arrivals]')", what=f"the {key} cards")
        text = f.js("document.querySelector('[data-growth-metrics] [data-metric=arrivals]').innerText")
        named = f.js("document.querySelector('[data-growth-metrics] [data-metric=arrivals] [data-row=previous] dt').firstChild.textContent.trim()")
        assert covers in text and named == before, f"{key}: the pill and the card disagree: {named!r}, {text!r}"
    f.run("document.querySelector('[data-growth-periods] [data-period=today]').click()")
    f.wait("document.querySelector('[data-growth-periods] [aria-checked=true]')?.dataset.period === 'today' && window.growthQueries.at(-1).period === 'today'", what="back to Today")
    assert f.js("document.querySelector('[data-growth-clock]').textContent.trim()") == t(f, "admin.growth.clock", {"zone": "London", "cut": "10:23"}), "the report zone's time, from the server"
    # How we're tracking: the last 7 days against a usual week, under the period (and its charts), above the cards.
    assert f.js("document.querySelector('[data-track-sentence]').textContent.trim()") == "Arrivals are up on a usual week. Visits, lesson 1 and sign-ups: not enough history to compare yet."
    assert f.js("document.querySelector('[data-growth-periods]').compareDocumentPosition(document.querySelector('[data-growth-tracking]')) & Node.DOCUMENT_POSITION_FOLLOWING"), "the period above the panel"
    assert f.js("document.querySelector('[data-growth-tracking]').compareDocumentPosition(document.querySelector('[data-growth-metrics]')) & Node.DOCUMENT_POSITION_FOLLOWING"), "the panel above the cards"
    assert f.js("document.querySelector('[data-growth-tracking] [data-track=arrivals] [data-usually]').textContent.trim()") == "Usually 1 to 16 by now"
    # Arrivals: up on both rows, its dot past the band in blue.
    arrivals = card(f, "arrivals")
    assert f.js(f"{arrivals}.querySelector('[data-verdict]').dataset.verdict") == "up"
    assert f.js(f"[...{arrivals}.querySelectorAll('[data-verdict] > span')].map((x) => x.textContent.trim()).join(' ') || {arrivals}.querySelector('[data-verdict]').textContent.trim()") == "↑ Up", "an arrow and a word"
    assert f.js(f"{arrivals}.querySelector('[data-range-strip]').dataset.inside") == "no"
    assert "bg-up" in f.js(f"{arrivals}.querySelector('[data-range-dot]').className"), "outside the band, in the up colour"
    assert f.js(f"{arrivals}.querySelector('[data-usually]').textContent.trim()") == "usually 2 to 16 by 10:23"
    rows = f.js(f"[...{arrivals}.querySelectorAll('[data-row]')].map((r) => r.innerText.replace(/\\s+/g, ' ').trim())")
    assert rows == ["Yesterday by 10:23 12 +18", "A usual Monday by 10:23 estimated about 9 +21"], rows
    # Visits: too few, both counts in words, no sign or percentage.
    visits = card(f, "visits")
    assert f.js(f"{visits}.querySelector('[data-verdict]').dataset.verdict") == "tooFew"
    assert f.js(f"{visits}.querySelector('[data-few]').textContent.trim()") == "4 so far, about 1 by 10:23 yesterday"
    assert not f.js(f"{visits}.querySelector('[data-change]')"), "too few: no change shown"
    # Asks: n of m said yes; a comparison with no history yet says from when.
    asks = card(f, "asks")
    assert "6 of 40 said yes" in f.js(f"{asks}.innerText")
    assert "Compared from Nov 10" in f.js(f"{asks}.querySelector('[data-row=previous]').innerText")
    f.no_sideways("the fair cards")
    f.shot("fair-today")

    # Every verdict's chip, from the same card.
    for verdict, words in [("down", "↓ Down"), ("maybeUp", "↑ Maybe up"), ("maybeDown", "↓ Maybe down"), ("same", "About the same"), ("none", "None either time"), ("new", "New")]:
        f.run(f"""window.growthVariant = (d) => {{ const m = d.metrics.find((x) => x.key === 'arrivals');
            m.usual = {{ ...m.usual, verdict: '{verdict}', change: {{ kind: 'percent', value: -30 }} }}; return d; }};""")
        f.click(t(f, "admin.usage.refresh"), "[data-growth-updated] button")
        f.wait(f"{arrivals}?.querySelector('[data-verdict]')?.dataset.verdict === '{verdict}'", what=f"the {verdict} chip")
        got = f.js(f"[...{arrivals}.querySelectorAll('[data-verdict] > span')].map((x) => x.textContent.trim()).join(' ') || {arrivals}.querySelector('[data-verdict]').textContent.trim()")
        assert got == words, f"{verdict}: {got!r}"
        tone = f.js(f"[...{arrivals}.querySelector('[data-verdict]').querySelectorAll('span')].map((s) => s.className).join(' ')")
        assert ("text-down" in tone) == (verdict in ("down", "maybeDown")) and ("text-up" in tone) == (verdict == "maybeUp"), f"{verdict}: the arrow's colour {tone}"
    # A number inside its usual range: an ink dot, and about the same.
    f.run("""window.growthVariant = (d) => { const m = d.metrics.find((x) => x.key === 'arrivals'); m.now = 9; m.usual = { ...m.usual, verdict: 'same', change: { kind: 'count', value: 0 } }; return d; };""")
    f.click(t(f, "admin.usage.refresh"), "[data-growth-updated] button")
    f.wait(f"{arrivals}?.querySelector('[data-range-strip]')?.dataset.inside === 'yes'", what="a dot inside the band")
    assert "bg-cream" in f.js(f"{arrivals}.querySelector('[data-range-dot]').className")
    # Early in the day: the one line above the cards.
    f.run("window.growthVariant = (d) => { d.period.early = true; return d; };")
    f.click(t(f, "admin.usage.refresh"), "[data-growth-updated] button")
    f.wait("document.querySelector('[data-growth-early]')", what="the early-day line")
    # How this is worked out: a plain panel, opened and closed.
    f.run("document.querySelector('[data-growth-how-link]').click()")
    assert f.js("getComputedStyle(document.querySelector('[data-growth-how]')).display") != "none" and f.js("document.querySelector('[data-growth-how-link]').getAttribute('aria-expanded')") == "true"
    # 7 days: the period sent to the server, the rows named for the week, and counting's change said once.
    f.run("""window.growthVariant = (d, q) => { if (q.period !== '7d') return d; d.period = { key: '7d', from: '2026-11-03', to: '2026-11-09', cut: '10:23', dayShare: 0.433, early: false };
        d.comparisons = [{ key: 'previous', from: '2026-10-27', to: '2026-11-02', cut: '10:23', scaledDays: null }, { key: 'usual', windows: 4, available: 4 }]; return d; };""")
    pick(f, "7d")
    f.wait("window.growthQueries.at(-1).period === '7d' && document.querySelector('[data-growth-break]')", what="7 days, with the break line")
    assert f.js("document.querySelector('[data-growth-break]').textContent.trim()") == "Counting changed on Nov 8 (crawlers left out), so earlier days read a little higher."
    assert f.js(f"{arrivals}.querySelector('[data-row=previous] dt').textContent.trim()") == "The 7 days before, to 10:23"
    assert f.js(f"{arrivals}.querySelector('[data-row=usual] dt').firstChild.textContent.trim()") == "A usual week (the 4 before)"
    assert "in the last 7 days" in f.js(f"{arrivals}.innerText")
    f.no_sideways("the fair cards, 7 days")
    f.shot("fair-7d")
    # Site visits by section: a column against the 7 days before, an arrow only where it's up or down, no chips.
    f.run("""window.growthVariant = (d) => { const set = (k, p) => { const s = d.sections.find((x) => x.key === k); if (s) s.previous = { ...s.previous, ...p }; };
        set('sv_tools', { verdict: 'up', change: { kind: 'percent', value: 40 }, expected: 30 }); set('sv_home', { verdict: 'tooFew', expected: 2 }); set('sv_courses', { verdict: 'same', change: { kind: 'count', value: 3 }, expected: 4 }); return d; };""")
    f.click(t(f, "admin.usage.refresh"), "[data-growth-updated] button")
    cell = lambda k: f"document.querySelector('[data-growth-site-table] [data-section={k}] [data-col=vs]')"
    f.wait(f"{cell('sv_tools')}?.dataset.verdict === 'up'", what="the sections' column")
    assert f.js(f"{cell('sv_tools')}.innerText.trim()") == "↑+40%" or f.js(f"{cell('sv_tools')}.innerText.replace(/\\s+/g, '')") == "↑+40%Up", f.js(f"{cell('sv_tools')}.innerText")
    assert f.js(f"!!{cell('sv_tools')}.querySelector('.text-up')"), f"the arrow in the up colour: {f.js(cell('sv_tools') + '.innerHTML')}"
    assert f.js(f"{cell('sv_home')}.innerText.trim()") == "Too few"
    assert f.js(f"{cell('sv_courses')}.innerText.trim()") == "+3" and not f.js(f"{cell('sv_courses')}.querySelector('.text-up, .text-down')"), "about the same: ink, no arrow"
    assert not f.js("document.querySelector('[data-growth-site-table] [data-col=vs] [data-verdict]')"), "no chips in the column"
    f.run("document.querySelector('[data-growth-site]').scrollIntoView()")
    f.no_sideways("the sections' column")
    f.shot("fair-sections")
    f.run("window.growthVariant = null")


# The contract's Today answer with a real day's shape (tests/fixtures/growth-today-chart.json): whole counts an hour,
# yesterday's 20 arrivals by the hour, a usual Monday's band with spread, and the projection's path along that shape.
TODAY_CHART = """const { api } = await import('/js/api.js'); const fx = await (await fetch('/tests/fixtures/growth-today-chart.json')).json();
api.admin.growth.show = async () => JSON.parse(JSON.stringify(fx));"""


def today_chart(f):
    """Today's chart (spec 4.4 and 11): hour by hour as running totals, against yesterday and a usual day; a shown
    projection dotted and labelled "Projected" at the mark and in the legend; a hidden one gives its reason and draws
    nothing dashed; a table with the same numbers, no projected number in Today's column. The mock's own example."""
    open_growth(f, "localStorage.removeItem('stackapella.admin.growth.period');\n" + TODAY_CHART)
    f.wait("document.querySelectorAll('[data-growth-charts] [data-chart]').length === 2", what="the two charts")
    # The charts at the top (Hillel): the period, then the charts, then How we're tracking and the cards.
    order = f.js("['[data-growth-periods]', '[data-growth-charts]', '[data-growth-tracking]', '[data-growth-metrics]'].map((s) => document.querySelector(s)).every((el, i, all) => !i || all[i - 1].compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING)")
    assert order, "the period, the charts, How we're tracking, the cards"
    f.run("window.scrollTo(0, 0)")
    f.shot("growth-top")
    arrivals, visits = "document.querySelector('[data-chart=arrivals]')", "document.querySelector('[data-chart=visits]')"
    # Arrivals: the projection shown, said so at the mark and in the legend.
    assert f.js(f"!!{arrivals}.querySelector('[data-projection] [data-line-projected]')"), "the projection's dotted line"
    label = f.js(f"{arrivals}.querySelector('[data-projected-label]').textContent.trim()")
    assert label == "Projected 69 (41 to 98)", label
    assert f.js(f"!!{arrivals}.querySelector('[data-legend-projected]')") and "Projected (an estimate)" in f.js(f"{arrivals}.querySelector('[data-chart-legend]').innerText")
    assert f.js(f"{arrivals}.querySelector('[data-line-projected]').getAttribute('stroke-dasharray')"), "dotted, never solid"
    name = f.js(f"{arrivals}.querySelector('svg[role=img]').getAttribute('aria-label')")
    assert name.startswith("Arrivals in the app today, hour by hour: 30 by 10:23;") and name.endswith("Projected about 69 by midnight."), name
    # The now dot is the end of today's line and sits on the now rule; the fan and the dotted line start from it.
    for chart in (arrivals, visits):
        ends = f.js(f"""(() => {{ const c = {chart}; const d = c.querySelector('[data-line-today]').getAttribute('d').trim().split(/[ML]/).filter(Boolean).at(-1).trim().split(/\\s+/).map(Number);
            const dot = c.querySelector('[data-now-dot]'); const rule = c.querySelector('[data-now-rule]');
            return [d[0], d[1], +dot.getAttribute('cx'), +dot.getAttribute('cy'), +rule.getAttribute('x1')]; }})()""")
        assert abs(ends[0] - ends[2]) < 0.2 and abs(ends[1] - ends[3]) < 0.2 and abs(ends[2] - ends[4]) < 0.2, f"line end, dot and rule: {ends}"
    starts = f.js(f"""[...{arrivals}.querySelectorAll('[data-projection-fan], [data-line-projected]')].map((p) => +p.getAttribute('d').slice(1).split(' ')[0])""")
    rule = f.js(f"+{arrivals}.querySelector('[data-now-rule]').getAttribute('x1')")
    assert all(abs(x - rule) < 0.2 for x in starts), f"the fan and the projection start at now: {starts} against {rule}"
    # Sign-ups: a strip under Arrivals in six 4-hour cells, the hours still to come empty.
    strip = f"[...{arrivals}.querySelectorAll('[data-signup-strip] [data-strip-now]')].map((c) => c.textContent.trim()).join(',')"
    assert f.js(strip) == "0,0,0,·,·,·", f.js(strip)
    assert f.js(f"{arrivals}.querySelector('[data-strip-cell]').title") == "00:00 to 04:00: 0 signed up, 0 the period before"
    # Visits: hidden, with its reason, and nothing dashed but yesterday.
    assert f.js(f"{visits}.querySelector('[data-projection-hidden]').textContent.trim()") == "Not enough history to project yet."
    assert not f.js(f"!!{visits}.querySelector('[data-projection], [data-legend-projected]')"), "no projection drawn or named"
    # Hovering 09:00: one tooltip, every series at that hour.
    f.run(f"""const svg = {arrivals}.querySelector('svg[role=img]'); const b = svg.getBoundingClientRect(); const w = b.width;
        svg.dispatchEvent(new PointerEvent('pointermove', {{ clientX: b.left + 40 + (9 / 24) * (w - 54), clientY: b.top + 100, bubbles: true }}));""")
    f.wait(f"{arrivals}.querySelector('[data-chart-tip]')?.textContent.startsWith('By 09:00')", what="the tooltip at 09:00")
    # The table: every hour, today's own count and its running total; projected only in its own column, from now on.
    f.run(f"{arrivals}.querySelector('[data-chart-table-toggle]').click()")
    f.wait(f"{arrivals}.querySelectorAll('[data-chart-table] tbody tr').length === 24", what="24 hours in the table")
    cells = f.js(f"[...{arrivals}.querySelectorAll('[data-chart-table] [data-col=today]')].map((c) => c.textContent.trim())")
    assert all("about" not in c and "Projected" not in c for c in cells), cells
    assert cells[11:] == [""] * 13, f"no today's count after now: {cells[11:]}"
    projected = f.js(f"[...{arrivals}.querySelectorAll('[data-chart-table] [data-col=projected]')].map((c) => c.textContent.trim())")
    assert all(p == "" for p in projected[:10]) and all(p.startswith("about ") for p in projected[10:]), projected
    f.run(f"{arrivals}.querySelector('[data-chart-table-toggle]').click()")
    f.run(f"{arrivals}.querySelector('[data-chart-table-toggle]').closest('figure').querySelector('.relative').dispatchEvent(new PointerEvent('pointerleave'))")
    f.wait(f"!{arrivals}.querySelector('[data-chart-tip]')", what="the tooltip gone")
    f.run(show("[data-growth-charts]"))
    f.no_sideways("today's charts")
    f.shot("chart-today")
    light_shot(f, "chart-today", "[data-growth-charts]")


def show(selector):
    """Scrolls `selector` to the top, from its heading, clear of the sticky header."""
    return f"const c = document.querySelector({json.dumps(selector)}); window.scrollTo(0, c.getBoundingClientRect().top + window.scrollY - document.querySelector('#app header').offsetHeight - 16)"


def light_shot(f, label, selector):
    """The same view in light, for the shots Hillel sees (both modes), then back to dark."""
    f.run("window.stackapellaTheme.choose({ mode: 'light' })")
    f.wait("document.documentElement.dataset.theme.endsWith('-light')", what="light mode")
    f.run(show(selector))
    f.shot(f"{label}-light")
    f.run("window.stackapellaTheme.choose({ mode: 'dark' })")
    f.wait("document.documentElement.dataset.theme.endsWith('-dark')", what="dark mode")


# Backend's real answers per period (tests/fixtures/growth-charts/, schema-checked): 10:23 on Mon 9 Nov unless named.
# window.growthPick[period] swaps in another answer (an early or too-few case) before the period is opened.
CHARTS = """const { api } = await import('/js/api.js');
const pick = { today: '../growth-today-chart', '7d': '7d-1023-shown', '28d': '28d-1023-shown', month: 'month-1023-shown', '12w': '12w-thursday-shown' };
window.growthPick = {};
api.admin.growth.show = async (q) => { const name = window.growthPick[q.period] ?? pick[q.period] ?? '7d-1023-shown';
  return (await fetch(`/tests/fixtures/growth-charts/${name}.json`)).json(); };"""


def open_period(f, period, pick=None):
    """Opens `period` from another (so it asks again), with `pick` as its answer."""
    other = "7d" if period != "7d" else "28d"
    f.run(f"window.growthPick = {json.dumps({period: pick} if pick else {})}")
    f.run(f"document.querySelector('[data-growth-periods] [data-period=\"{other}\"]').click()")
    f.wait(f"document.querySelector('[data-growth-charts]')?.dataset.chartPeriod === '{other}'", what=f"{other} first")
    f.run(f"document.querySelector('[data-growth-periods] [data-period=\"{period}\"]').click()")
    f.wait(f"document.querySelector('[data-growth-charts]')?.dataset.chartPeriod === '{period}' && document.querySelectorAll('[data-growth-charts] [data-chart]').length === 2", what=f"{period}'s charts")


def chart_checks(f, chart, name, slots, now_slot):
    """What every column chart keeps: a slot a period position, the table matching the data with no projected number in
    This period, the projected column only on the current slot, and nothing dashed or hatched unless a projection shows."""
    c = f"document.querySelector('[data-chart={chart}]')"
    assert f.js(f"{c}.querySelectorAll('[data-slot]').length") == slots, f"{chart}: {slots} slots"
    shown = f.js(f"!!{c}.querySelector('[data-projection], [data-projection-rate]')")
    if shown:
        assert f.js(f"{c}.querySelector('[data-projected-label]').textContent.trim()").startswith("Projected"), "said at the mark"
        assert "Projected (an estimate)" in f.js(f"{c}.querySelector('[data-chart-legend]').innerText"), "and in the legend"
        dashed = f.js(f"[...{c}.querySelectorAll('[data-projected-rest], [data-line-projected]')].every((m) => m.getAttribute('stroke-dasharray'))")
        assert dashed, "a projection is never solid"
        assert not f.js(f"!!{c}.querySelector('[data-projection-hidden]')"), "no reason line when it shows"
    else:
        assert f.js(f"{c}.querySelector('[data-projection-hidden]').textContent.trim()"), f"{chart}: a hidden projection says why"
        assert not f.js(f"!!{c}.querySelector('[data-legend-projected], [data-projected-rest], [data-line-projected], [data-projected-label]')"), f"{chart}: nothing projected drawn or named"
    # The table: the data's own counts in This period, never "about" or "Projected"; projected only on the current slot.
    data = json.loads((Path(__file__).parent.parent / f"fixtures/growth-charts/{name}.json").read_text())["data"]["chart"]["metrics"][chart]["now"]
    f.run(f"{c}.querySelector('[data-chart-table-toggle]').click()")
    f.wait(f"{c}.querySelectorAll('[data-chart-table] tbody tr').length === {slots}", what=f"{chart}'s table")
    cells = f.js(f"[...{c}.querySelectorAll('[data-chart-table] [data-col=now]')].map((x) => x.textContent.trim())")
    assert cells == ["" if v is None else str(round(v)) for v in data], f"{chart}: the table is the data: {cells} against {data}"
    projected = f.js(f"[...{c}.querySelectorAll('[data-chart-table] [data-col=projected]')].map((x) => x.textContent.trim())")
    assert all((p.startswith("about ") if i == now_slot else p == "") for i, p in enumerate(projected)), projected
    f.run(f"{c}.querySelector('[data-chart-table-toggle]').click()")


def period_charts(f):
    """Every period's charts (spec 11.2, F3b to F3e) from backend's real answers: columns against the period before's
    ticks, today's (or this week's) so far plus its hatched projected rest, the month's daily rate over the days to come,
    hidden projections with their reason, the sign-up strip under Arrivals, and a table for each."""
    open_growth(f, "localStorage.removeItem('stackapella.admin.growth.period');\n" + CHARTS)
    f.wait("document.querySelector('[data-growth-periods] [data-period=\"7d\"]')", what="the periods")
    # The picker: on a phone a compact menu, one line (Hillel: five options stacked pushed the charts down); from sm up
    # the segmented control. Either one chooses the same period.
    menu, seg = "document.querySelector('[data-growth-period-select]')", "document.querySelector('[data-growth-periods]')"
    if f.size == "phone":
        assert f.js(f"!!{menu}.offsetParent && !{seg}.offsetParent"), "a phone: the menu, not the segments"
        assert f.js(f"{menu}.getBoundingClientRect().height") <= 56, "one line"
        assert f.js(f"[...{menu}.options].map((o) => o.textContent.trim()).join(',')") == "Today,7 days,4 weeks,This month,12 weeks"
        assert f.js(f"{menu}.getAttribute('aria-label')") == t(f, "admin.growth.periods.label"), "named for a screen reader"
        f.run(f"{menu}.value = 'month'; {menu}.dispatchEvent(new Event('change'))")
        f.wait("document.querySelector('[data-growth-charts]')?.dataset.chartPeriod === 'month'", what="This month from the menu")
        assert f.js(f"{menu}.value") == "month" and f.js(f"{seg}.querySelector('[aria-checked=true]').dataset.period") == "month"
        f.run("window.scrollTo(0, 0)")
        f.shot("picker-menu")
    else:
        assert f.js(f"!{menu}.offsetParent && !!{seg}.offsetParent"), "from sm up: the segments"

    # 7 days: columns, today's so far and its projected rest, the header with the period's projected total.
    open_period(f, "7d")
    arrivals = "document.querySelector('[data-chart=arrivals]')"
    assert f.js(f"{arrivals}.dataset.chartKind") == "days"
    assert f.js(f"{arrivals}.querySelector('[data-projected-label]').textContent.trim()") == "Projected 69"
    assert f.js(f"{arrivals}.querySelectorAll('[data-column]').length") == 6 and f.js(f"!!{arrivals}.querySelector('[data-slot=\"6\"] [data-so-far]')"), "six days in ink, today so far"
    assert f.js(f"{arrivals}.querySelectorAll('[data-before-tick]').length") == 7, "the week before as ticks"
    # Each tick on a halo and wider than its column, so it shows on a column's top as well as above it.
    assert f.js(f"{arrivals}.querySelectorAll('[data-before-halo]').length") == 7, "a halo under each tick"
    wider = f.js(f"""[...{arrivals}.querySelectorAll('[data-slot]')].filter((g) => g.querySelector('[data-column]')).every((g) => {{
        const t = g.querySelector('[data-before-tick]'); return t.getBoundingClientRect().width > g.querySelector('[data-column]').getBoundingClientRect().width + 4; }})""")
    assert wider, "the ticks reach past their columns"
    assert f.js(f"{arrivals}.querySelector('[data-projected-rest]').getAttribute('fill').startsWith('url(#hatch')"), "the rest hatched"
    header = f.js(f"{arrivals}.querySelector('[data-chart-header]').textContent.trim()")
    assert header == "166 so far in these 7 days. Projected about 205 by tonight (177 to 234); the 7 days before had 156.", header
    assert f.js("document.querySelector('[data-chart=visits] [data-projection-hidden]').textContent.trim()") == "Not enough history to project yet."
    chart_checks(f, "arrivals", "7d-1023-shown", 7, 6)
    chart_checks(f, "visits", "7d-1023-shown", 7, 6)
    # The tooltip on today's column: so far, the week before, and the projection said as one.
    f.run(f"{arrivals}.querySelector('[data-slot=\"6\"]').dispatchEvent(new PointerEvent('pointerenter'))")
    tip = f.js(f"{arrivals}.querySelector('[data-chart-tip]').textContent.trim()")
    assert "30 so far" in tip and "A week before: 20" in tip and "Projected by tonight: about 69 (41 to 98)" in tip, tip
    f.run(f"{arrivals}.querySelector('[data-chart-table-toggle]').closest('figure').querySelector('.relative').dispatchEvent(new PointerEvent('pointerleave'))")
    # The sign-up strip: a cell a day, lined up with the columns, the week before small beneath.
    strip = f"{arrivals}.querySelector('[data-signup-strip]')"
    assert f.js(f"{strip}.querySelectorAll('[data-strip-cell]').length") == 7
    assert f.js(f"[...{strip}.querySelectorAll('[data-strip-now]')].map((c) => c.textContent.trim()).join(',')") == "1,0,0,1,0,0,0"
    off = f.js(f"""(() => {{ const cols = [...{arrivals}.querySelectorAll('[data-slot] rect')].map((r) => r.getBoundingClientRect()); const cells = [...{strip}.querySelectorAll('[data-strip-cell]')].map((c) => c.getBoundingClientRect());
        return Math.max(...cells.map((c, i) => Math.abs(c.left + c.width / 2 - (cols[i].left + cols[i].width / 2)))); }})()""")
    assert off < 1.5, f"the strip's cells sit under their columns: off by {off}px"
    f.no_sideways("7 days' charts")
    f.run(show("[data-growth-charts]"))
    f.shot("chart-7d")
    f.run(show("[data-chart=arrivals]"))
    f.shot("chart-7d-arrivals")
    light_shot(f, "chart-7d", "[data-growth-charts]")

    # Too early at 04:00 (a quarter of a usual day not in yet), and too few: the reason, nothing projected.
    open_period(f, "7d", "7d-0400-early")
    assert f.js(f"{arrivals}.querySelector('[data-projection-hidden]').textContent.trim()") == "Projection from about 06:00, once a quarter of a usual day is in."
    chart_checks(f, "arrivals", "7d-0400-early", 7, 6)
    open_period(f, "7d", "7d-1023-tooFew")
    assert f.js(f"{arrivals}.querySelector('[data-projection-hidden]').textContent.trim()") == "Too few so far to project."
    chart_checks(f, "arrivals", "7d-1023-tooFew", 7, 6)

    # 4 weeks: 28 columns that still read at a phone's width.
    open_period(f, "28d")
    chart_checks(f, "arrivals", "28d-1023-shown", 28, 27)
    assert f.js(f"{arrivals}.querySelectorAll('[data-signup-strip] [data-strip-cell]').length") == 4, "4 weeks: a cell a 7 days"
    widths = f.js(f"[...{arrivals}.querySelectorAll('[data-column]')].map((c) => c.getBoundingClientRect().width)")
    assert min(widths) >= 5, f"28 columns still read: {min(widths)}px"
    labels = f.js(f"[...{arrivals}.querySelectorAll('[data-x-label]')].map((l) => {{ const b = l.getBoundingClientRect(); return {{ left: b.left, right: b.right }}; }})")
    assert all(labels[i]["right"] <= labels[i + 1]["left"] for i in range(len(labels) - 1)), "the dates don't collide"
    f.no_sideways("4 weeks' charts")
    f.run(show("[data-growth-charts]"))
    f.shot("chart-28d")
    f.run(show("[data-chart=arrivals]"))
    f.shot("chart-28d-arrivals")

    # This month: every day of it, the days to come empty, last month's ticks, one dashed line at the daily rate.
    open_period(f, "month")
    assert f.js(f"{arrivals}.querySelectorAll('[data-future]').length") == 21, "21 days to come"
    assert f.js(f"{arrivals}.querySelectorAll('[data-future] path').length") == 0, "drawn as empty space: no baseline of their own"
    # The strip by 7 days from the 1st, each cell spanning its days' columns; the days to come, nothing yet.
    strip = f"{arrivals}.querySelector('[data-signup-strip]')"
    assert f.js(f"[...{strip}.querySelectorAll('[data-strip-cell]')].map((c) => c.dataset.span).join(',')") == "7,7,7,7,2"
    assert f.js(f"[...{strip}.querySelectorAll('[data-strip-now]')].map((c) => c.textContent.trim()).join(',')") == "2,0,·,·,·"
    assert f.js(f"[...{strip}.querySelectorAll('[data-strip-before]')].map((c) => c.textContent.trim()).join(',')") == "2,2,3,2,1"
    assert f.js(f"{strip}.querySelectorAll('[data-strip-cell]')[1].title") == "Nov 8 to Nov 14: 0 signed up, 2 the period before"
    off = f.js(f"""Math.abs({strip}.querySelectorAll('[data-strip-cell]')[1].getBoundingClientRect().left - {arrivals}.querySelector('[data-slot="7"] rect').getBoundingClientRect().left)""")
    assert off < 1.5, f"a week's cell starts under its first day's column: off by {off}px"
    assert f.js(f"{arrivals}.querySelector('[data-projection-rate] [data-projected-label]').textContent.trim()") == "Projected about 25 a day"
    assert f.js(f"{arrivals}.querySelectorAll('[data-projection-rate] [data-line-projected]').length") == 1, "one line, never a column a future day"
    header = f.js(f"{arrivals}.querySelector('[data-chart-header]').textContent.trim()")
    assert header == "November so far 214. Projected about 761 for the month (645 to 878). October had 692.", header
    chart_checks(f, "arrivals", "month-1023-shown", 30, 8)
    f.no_sideways("this month's charts")
    f.run(show("[data-growth-charts]"))
    f.shot("chart-month")
    f.run(show("[data-chart=arrivals]"))
    f.shot("chart-month-arrivals")
    light_shot(f, "chart-month", "[data-growth-charts]")
    # At 04:00 the month still projects (a daily rate), but today's column doesn't yet.
    open_period(f, "month", "month-0400-shown")
    assert f.js(f"!!{arrivals}.querySelector('[data-projection-rate]') && !{arrivals}.querySelector('[data-projected-rest]')"), "the rate, no column"

    # 12 weeks: Monday-to-Sunday weeks, this week so far plus its projected rest, said "by Sunday".
    open_period(f, "12w")
    assert f.js(f"{arrivals}.dataset.chartKind") == "weeks"
    assert f.js(f"{arrivals}.querySelector('[data-projected-label]').textContent.trim()") == "Projected 208"
    assert "Weeks run Monday to Sunday." in f.js(f"{arrivals}.querySelector('[data-so-far-note]').textContent")
    chart_checks(f, "arrivals", "12w-thursday-shown", 12, 11)
    f.no_sideways("12 weeks' charts")
    f.run(show("[data-growth-charts]"))
    f.shot("chart-12w")
    f.run(show("[data-chart=arrivals]"))
    f.shot("chart-12w-arrivals")
    open_period(f, "12w", "12w-1023-early")
    assert f.js(f"{arrivals}.querySelector('[data-projection-hidden]').textContent.trim()") == "Projection from about Tuesday evening."
    chart_checks(f, "arrivals", "12w-1023-early", 12, 11)


def site_sections(f):
    """The whole public site's visits by section, the last 7 days, beside Sources: a row a section with a bar a day and
    the week's total, and every public page's total under them. The Steps' first number says it is the home page's."""
    f.go("/admin/sources?api=mock&as=admin", "document.querySelector('h1')")
    f.run("""const { api } = await import('/js/api.js'); const show = api.admin.counts.show;
api.admin.counts.show = async (q) => { const r = await show(q); const days = ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07', '2026-11-08'];
  return { ...r, data: { ...r.data, days: [...r.data.days, ...days.map((date, i) => ({ date, kind: 'sv_tools', guest: 30 + i * 5, account: 1 })), { date: '2026-11-08', kind: 'sv_home', guest: 20, account: 2 }, { date: '2026-11-07', kind: 'sv_course', guest: 12, account: 0 }, { date: '2026-11-08', kind: 'sv_part', guest: 10, account: 0 }, { date: '2026-11-08', kind: 'part_play', guest: 3, account: 0 }] } }; };
const growth = api.admin.growth.show; api.admin.growth.show = async (q) => { const r = await growth(q); return { ...r, data: { ...r.data, to: '2026-11-08' } }; };""")
    f.run("(await import('/js/router.js')).navigate('/admin/growth')")
    f.wait("document.querySelector('[data-growth-site-table]')", what="site visits by section")
    tools = "document.querySelector('[data-growth-site-table] [data-section=sv_tools]')"
    assert f.js(f"{tools}.querySelector('[data-col=week]').textContent.trim()") == "322", f.js(f"{tools}.innerText")
    assert f.js(f"{tools}.querySelectorAll('[data-day]').length") == 7, "a bar a day"
    assert f.js(f"[...{tools}.querySelectorAll('[data-day]')].map((b) => b.dataset.n).join(',')") == "31,36,41,46,51,56,61"
    assert all(w >= 8 for w in f.js(f"[...{tools}.querySelectorAll('[data-day]')].map((b) => b.getBoundingClientRect().width)")), "the bars are drawn"
    assert f.js("document.querySelector('[data-growth-site-total]').textContent.trim()") == "366", "every public page's total"
    assert f.js("document.querySelectorAll('[data-growth-site-table] tbody tr').length") == 9, "every section, quiet ones too"
    assert f.js("document.querySelector('[data-growth-site-table] [data-section=sv_part] [data-col=week]').textContent.trim()") == "10", "song part pages"
    assert f.js("document.querySelector('[data-growth-part-plays]').textContent") == t(f, "admin.growth.sections.partPlays", {"plays": "3", "visits": "10"}), "parts played, against those pages' visits"
    assert t(f, "admin.growth.step.visits") == "Home page visits"
    assert "Home page visits" in f.js("document.querySelector('[data-growth-steps]').innerText"), "the Steps' first number named for what it counts"
    f.run("document.querySelector('[data-growth-site]').scrollIntoView()")
    f.no_sideways("site visits by section")
    f.shot("site-sections")

if __name__ == "__main__":
    run("admin_growth", journey, sizes=("desktop", "phone"))
