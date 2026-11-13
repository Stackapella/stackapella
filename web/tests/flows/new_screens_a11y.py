"""Accessibility of what's new since 2026-11-12 (the coordinator's pass, 2026-11-13; docs/a11y-review-2026-11.md):
Admin > Live, Corrections, Growth's Visits, Guides' staged line, teaming up's screens (the switch on, through
fixtures), lesson 1 as a friend's card opens it, the free tools' share buttons, and Settings' Free tools. On each, in
all eight themes: names and roles, AA contrast, visible focus, Tab kept in a sheet, Escape, nothing moving under
reduced motion and reflow at 320 px (first_week_a11y's check). Live says only its state as it changes, never its
numbers every 15 seconds.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from first_week_a11y import check  # noqa: E402

FIX = "const get = async (dir, name) => (await fetch('/tests/fixtures/' + dir + '/' + name + '.json')).json(); const { api } = await import('/js/api.js');"
TEAMS_ON = "await (await import('/js/stores/account.js')).loadAuthConfig(); (await import('/js/teams.js')).teams.on = true;"


def journey(f):
    failures = []
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    f.expected_errors += ["/contract/examples/"]

    def visit(name, opener, ready, then=None):
        try:
            opener()
            f.wait(ready, what=name)
            if then:
                then()
            check(f, name, failures)
        except (AssertionError, TimeoutError) as error:
            failures.append(f"{name}: couldn't open: {error}")

    def admin(path, stub):
        def go():
            f.go("/admin/sources?api=mock&as=admin", "document.querySelector('h1')")
            f.run(stub)
            f.run(f"(await import('/js/router.js')).navigate({json.dumps(path)})")
        return go

    def app(path, stub=""):
        def go():
            f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
            if stub:
                f.run(stub)
            f.run(f"(await import('/js/router.js')).navigate({json.dumps(path)})")
        return go

    live = FIX + """const [l, p, r] = await Promise.all([get('live', 'admin.live.show'), get('live', 'admin.pulse.show'), get('live', 'admin.revenue.show')]);
api.admin.live.show = async () => structuredClone(l); api.admin.pulse.show = async () => structuredClone(p); api.admin.revenue.show = async () => structuredClone(r);"""
    visit("admin-live", admin("/admin/live", live), "document.querySelector('[data-live-signups]')")
    # Polling numbers never chatter: no live region inside Live's numbers; its state alone is a status.
    if f.js("!!document.querySelector('[data-live-panel] [aria-live], [data-live-areas] [aria-live], [data-live-signups] [aria-live]')"):
        failures.append("admin-live: a live region inside the polled numbers (it would speak every 15 s)")
    if not f.js("!!document.querySelector('[data-live-state]')?.closest('[role=status], [aria-live]')"):
        failures.append("admin-live: the Live / Reconnecting state isn't a status")

    corrections = FIX + """const list = await get('corrections', 'admin.corrections.list'); api.admin.corrections.list = async () => structuredClone(list);"""
    visit("admin-corrections", admin("/admin/corrections", corrections), "document.querySelector('[data-corrections-list] [data-correction]')")
    visit("admin-corrections-sheet", admin("/admin/corrections", corrections), "document.querySelector('[data-correction] [data-act]')",
          lambda: (f.run("document.querySelector('[data-correction] [data-act=rejected]').click()"), f.wait("document.querySelector('[data-corrections-sheet]')")))

    visits = """const { api } = await import('/js/api.js'); const fx = await (await fetch('/tests/fixtures/growth-today-chart.json')).json();
const m = fx.data.chart.metrics; m.appStarts = { now: m.signUps.now, previous: m.signUps.previous };
m.visits.projection = { ...JSON.parse(JSON.stringify(m.arrivals.projection)), basis: 'borrowedShape', shapeFrom: 'arrivals' };
api.admin.growth.show = async () => JSON.parse(JSON.stringify(fx)); localStorage.removeItem('stackapella.admin.growth.period');"""
    visit("admin-growth-visits", admin("/admin/growth", visits), "document.querySelector('[data-strip=appStarts]')")
    visit("admin-guides-staged", admin("/admin/guides", "true"), "document.querySelector('[data-guide-staged]')")

    together = FIX + TEAMS_ON + """const list = await get('together', 'groups.list'); const show = await get('together', 'groups.show');
api.groups.list = async () => structuredClone(list); api.groups.show = async () => structuredClone(show);
api.billing.show = async () => ({ data: { plan: 'free', trial: { days: 7, intervals: ['year'] } } }); (await import('/js/stores/plan.js')).plan.billing = null;"""
    visit("groups-paused-tag", app("/groups", together), "document.querySelector('[data-group-paused]')")
    visit("groups-plus-sheet", app("/groups", together), "document.querySelector('[data-group-paused]')",
          lambda: (f.click(f.js("(await import('/js/skin.js')).sk('groups.start', 'game.groups.start')")), f.wait("document.querySelector('[data-plus-moment] [data-plus-trial]')")))
    visit("group-paused-leader", app("/groups/Gp4rQs", together), "document.querySelector('[data-paused-note]')")
    joinpaused = FIX + TEAMS_ON + """const js = await get('together', 'groups.join.show'); api.groups.join.show = async () => structuredClone(js);"""
    visit("choir-join-paused", app("/choir/tok123", joinpaused), "document.querySelector('[data-paused-sing]')")
    visit("plus-teams", app("/plus?from=group_start", TEAMS_ON), "document.querySelector('#plus-heading')")
    visit("settings-free-tools", app("/settings"), "document.querySelector('[data-free-tools]')")

    def friend():
        f.go("/learn/1-comfortable?api=mock&scenario=&ref=share-note&their=C4", "document.querySelector('main h1')")
    visit("lesson1-friend", friend, "document.querySelector('[data-go-on]:not(:disabled), [data-friend-note]')",
          lambda: (f.run("document.querySelector('[data-go-on]:not(:disabled)')?.click()"), f.wait("document.querySelector('[data-friend-note]')")))

    for name, page in [("key-finder-share", "vocal-key-finder"), ("voicer-share", "satb-voicer"), ("transposer-share", "chord-transposer")]:
        def tool(page=page):
            f.go(f"/tests/{page}-demo.html", "document.querySelector('[data-share]')")
            if page == "chord-transposer":
                f.run("window.__transposer.set('G C D G'); window.__transposer.shift(2)")
        visit(name, tool, "document.querySelector('[data-share]:not([hidden])')")

    # Data tables in their own scroller (the Plus page's Free and Plus, Growth's steps) may scroll sideways at 320 px:
    # WCAG 1.4.10 exempts content that needs two dimensions, and they're older than this pass.
    tables = ("plus-teams: at 320 px scrolls sideways: div.overflow-x-auto", "admin-growth-visits: at 320 px scrolls sideways: div.-mx-1.mt-4.overflow-x-auto")
    failures = [x for x in failures if not x.startswith(tables)]
    assert not failures, "\n".join(failures[:40])


run("new_screens_a11y", journey)
