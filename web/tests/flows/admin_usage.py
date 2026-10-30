"""Usage (admin.counts.show): the anonymous daily counts as each moment's total (signed in and
guests) by area, the Plus courses by course and the free course by lesson, one moment day by day
and week by week, and the top countries; Today so far (near live: refreshed while in view, with
when it was updated and Refresh), or over 7, 30 or 90 days. Against the mock (its example), then
counts made up for today and a week ago.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# Today and a week ago (UTC, as the server keeps them), some of each area's moments.
FAKE = """const { api } = await import('/js/api.js');
    const day = (back) => new Date(Date.UTC(new Date().getUTCFullYear(), new Date().getUTCMonth(), new Date().getUTCDate() - back)).toISOString().slice(0, 10);
    const rows = [['app_open', 40, 22], ['open_game', 12, 9], ['open_serious', 28, 13], ['game_on', 2, 1], ['sharpen_up', 0, 4], ['then_now', 0, 3],
        ['fact_source', 1, 2], ['hum_start', 6, 5], ['hum_tap', 2, 1], ['take_use', 0, 3], ['track_add', 2, 4], ['exp_stems', 0, 1], ['share_note', 3, 2],
        ['su_progress', 0, 2], ['card_day2', 0, 5], ['sug_melody', 7, 2], ['sug_switch', 1, 0],
        ['pl_s:pitch', 0, 6], ['pl_d:pitch', 0, 4], ['pl_s:vibrato', 0, 2], ['fl_s:1-comfortable', 9, 3], ['fl_d:1-comfortable', 8, 3]];
    const days = [0, 7].flatMap((back) => rows.map(([kind, guest, account]) => ({ date: day(back), kind, guest, account })));
    api.admin.counts.show = async (q) => { window.countsQuery = q; return { data: { days, totals: [], countries: [{ country: 'CA', count: 50 }, { country: null, count: 4 }] } }; };"""


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def total(f, kind):
    return f.js(f"document.querySelector('[data-kind={kind}] span:last-child').firstChild.textContent.trim()")


def journey(f):
    # Today so far opens first (the mock's example is from other days: nothing yet today), with when it was updated.
    f.go("/admin/usage?api=mock&as=admin", "document.querySelector('[data-usage-updated]')")
    f.wait_text(t(f, "admin.usage.noneToday"))
    updated = t(f, "admin.usage.updated", {"when": "~"}).split("~")[0]  # "Updated ", then how long ago
    f.wait(f"document.querySelector('[data-usage-updated]').textContent.trim().startsWith({json.dumps(updated)})", what="when it was updated")

    # Refresh asks again for today (days=1), quietly: made-up counts for today and a week ago.
    f.run(FAKE)
    f.click(t(f, "admin.usage.refresh"), "[data-usage-updated] button")
    f.wait("window.countsQuery?.days === 1 && document.querySelector('[data-kind=open_game]')", what="today's counts")
    assert total(f, "open_game") == "21", total(f, "open_game")
    assert f.js("!document.querySelector('[data-usage-days]')"), "today: no day by day"
    f.wait("document.querySelector('[data-usage-countries] li')", what="today's countries")
    f.shot("today")

    f.click(t(f, "admin.usage.days", {"count": 7}), "[role=radiogroup] button")
    f.wait("window.countsQuery?.days === 7 && document.querySelectorAll('[data-usage-days] > div').length === 7", what="a week")
    f.wait("document.querySelectorAll('[data-usage-weeks] li').length === 1", what="one week's total")
    f.click(t(f, "admin.usage.days", {"count": 30}), "[role=radiogroup] button")
    f.wait("window.countsQuery?.days === 30 && document.querySelector('[data-detail-id=pitch]')", what="the Plus courses by course")
    f.wait("document.querySelectorAll('[data-usage-area]').length === 8", what="every area")
    assert total(f, "open_game") == "42", total(f, "open_game")
    pitch = f.js("document.querySelector('[data-detail-id=pitch]').innerText.replace(/\\s+/g, ' ').trim()")
    assert pitch.endswith("12 8"), pitch
    assert f.js("!!document.querySelector('[data-usage-detail=free] [data-detail-id=\"1-comfortable\"]')"), "the free course's first lesson"
    assert f.js("document.querySelectorAll('[data-usage-detail=free] [data-detail-id]').length === 1"), "a lesson with no counts is left out"
    f.shot("usage")
    f.run("document.querySelector('[data-usage-area=plus]').scrollIntoView()")
    f.shot("courses")
    f.run("window.scrollTo(0, 0)")

    # A refresh that fails keeps the numbers and says so.
    f.run("const { api } = await import('/js/api.js'); api.admin.counts.show = async () => { throw new Error('offline'); };")
    f.click(t(f, "admin.usage.refresh"), "[data-usage-updated] button")
    f.wait("document.querySelector('[data-usage-updated]').textContent.includes(" + json.dumps(t(f, "admin.usage.notUpdated", {"when": "~"}).split("~")[0]) + ")", what="couldn't update")
    assert total(f, "open_game") == "42", "the numbers stay"

    # A moment's name picks it for day by day.
    f.run("document.querySelector('[data-kind=hum_start] button').click()")
    f.wait_text(t(f, "admin.usage.dayByDay", {"kind": t(f, "admin.usage.kind.hum_start")}))


if __name__ == "__main__":
    run("admin_usage", journey, sizes=("desktop", "phone"))
