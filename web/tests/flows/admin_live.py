"""Admin > Numbers > Live (docs/realtime-dashboard-plan.md): right now with its minutes, the week's signed-in singers,
revenue, where people are by area, the last 24 hours' sign-ups (how far they got, by the hour, the new accounts and
the day's sources as totals) and activity. Show admins asks again with ?admins=1 and is remembered. Its answers are
fixtures (tests/fixtures/live/) until backend's examples are in the contract.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

STUB = """const { api } = await import('/js/api.js');
const get = async (name) => (await fetch('/tests/fixtures/live/' + name + '.json')).json();
const [live, pulse, revenue] = await Promise.all([get('admin.live.show'), get('admin.pulse.show'), get('admin.revenue.show')]);
api.admin.live.show = async (q) => { (window.liveQueries ??= []).push(q); return structuredClone(live); };
api.admin.pulse.show = async (q) => { (window.pulseQueries ??= []).push(q); return structuredClone(pulse); };
api.admin.revenue.show = async () => structuredClone(revenue);"""


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin/sources?api=mock&as=admin", "document.querySelector('h1')")
    f.run("Object.keys(localStorage).filter((k) => k.startsWith('stackapella.admin.')).forEach((k) => localStorage.removeItem(k))")
    f.run(STUB)
    f.run("(await import('/js/router.js')).navigate('/admin/live')")
    f.wait("document.querySelector('[data-live-signups]')", what="the live page with its sign-ups")
    f.wait("document.querySelector('[data-live-revenue] [data-currency]')", what="revenue")

    # Right now: the total, signed in and guests, and 30 minutes of views, the last one so far.
    assert f.js("document.querySelector('[data-live-total]').textContent.trim()") == "14"
    assert f.js("document.querySelector('[data-live-split]').textContent.trim()") == t(f, "admin.live.split", {"signedIn": "5", "guests": "9"})
    assert f.js("document.querySelectorAll('[data-live-now] [data-bars] > span').length") == 30
    assert f.js("document.querySelector('[data-live-now] [data-bars] > span:last-child').hasAttribute('data-so-far')"), "this minute is so far"
    assert f.js("document.querySelector('[data-live-state]').dataset.liveState") == "live"
    # The week: 182 signed in, 18 more than the week before (up, with an arrow), and the twelve weeks' line.
    assert f.js("document.querySelector('[data-live-active-n]').textContent.trim()") == "182"
    assert f.js("document.querySelector('[data-live-active] [data-change]').dataset.dir") == "up"
    assert f.js("document.querySelector('[data-live-weeks] polyline').getAttribute('points').split(' ').length") == 12
    # Revenue: each currency's month, the year, and the month if every trial converts.
    assert f.js("document.querySelectorAll('[data-live-revenue] [data-currency]').length") == 2
    assert "$71.54" in f.js("document.querySelector('[data-live-convert]').textContent"), "if all convert"

    # Where they are: busiest first, the empty area last and still shown, three pages each at most.
    assert f.js("[...document.querySelectorAll('[data-area]')].map((a) => a.dataset.area)") == ["courses", "tools", "site", "studio", "app"]
    assert f.js("document.querySelectorAll('[data-area=courses] ol li').length") == 3

    # Sign-ups: the five steps, each a share of those who signed up; the new accounts with their steps; the sources.
    assert f.js("[...document.querySelectorAll('[data-live-funnel] [data-step-n]')].map((n) => n.textContent.trim())") == ["23", "17", "9", "6", "2"]
    assert f.js("document.querySelectorAll('[data-live-signups] [data-bars] > span').length") == 24
    assert f.js("document.querySelectorAll('[data-live-people] a').length") == 4
    assert f.js("document.querySelectorAll('[data-person=Rt4Nx8] [data-person-steps] .bg-accent').length") == 3
    assert f.js("document.querySelector('[data-source=small]').textContent").startswith(t(f, "admin.growth.source.small", {"count": 2})), "small sources folded"
    assert f.js("document.querySelector('[data-source=tool-vocal-range-test]').textContent").startswith(t(f, "admin.growth.source.tool", {"name": "vocal-range-test"}))
    # Activity: busiest first, in the Usage page's words.
    assert f.js("document.querySelector('[data-live-activity] [data-kind]').dataset.kind") == "lesson_start"
    assert f.js("document.querySelector('[data-kind=lesson_done] span').textContent.trim()") == t(f, "admin.usage.kind.lesson_done")

    if f.size == "desktop":
        tops = f.js("[...document.querySelectorAll('[data-live-panel] > section')].map((s) => Math.round(s.getBoundingClientRect().top))")
        assert len(set(tops)) == 1, f"the three numbers side by side at 1440: {tops}"
    f.no_sideways("Live")
    f.shot("live")
    f.run("window.scrollTo(0, document.querySelector('[data-live-signups]').getBoundingClientRect().top + scrollY - 20)")
    f.shot("live-signups")

    # Show admins: asked again with admins, and remembered on this device.
    f.run("document.querySelector('[data-live-admins] [role=switch]').click()")
    f.wait("window.liveQueries.at(-1)?.admins === true && window.pulseQueries.at(-1)?.admins === true", what="asked again with admins")
    assert f.js("localStorage.getItem('stackapella.admin.live.showAdmins')") == "true"
    f.wait("document.querySelector('[data-live-total]')", what="the numbers back")
    # Live is in the menu, after Overview under Numbers.
    assert f.js("!!document.querySelector('a[href=\"/admin/live\"]')"), "Live in the menu"


run("admin_live", journey)
