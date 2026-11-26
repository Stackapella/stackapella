"""Admin > Choir groups (admin.groups.list; docs/choir-tracks-plan.md, "How we'll know"): every group in the server's
order (most singers in the last two weeks first), its director, members and songs, its last 8 weeks as a small bar of
singers, this week's singers and the 8 weeks' takes and scored runs. A table on wider screens, a card a group on
phones; totals only, never which member. Reached from the People group of the admin's nav. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# The rows in view: the table's on wider screens, the cards' on phones.
ROWS = "[...document.querySelectorAll('[data-choir-groups-table] tbody tr, [data-choir-groups-cards] li')].filter((r) => r.offsetParent)"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def row(f, group):
    return f"{ROWS}.find((r) => r.dataset.group === {json.dumps(group)})"


def journey(f):
    # From the admin's nav: People, then Choir groups.
    f.go("/admin/people?api=mock&as=admin", "document.querySelector('h1')")
    f.click(t(f, "admin.nav.choirGroups"), "a, button")
    f.wait(f"location.pathname === '/admin/groups' && {ROWS}.length", what="the choir groups")

    # The server's order, kept: the group with singers first.
    assert f.js(f"{ROWS}.map((r) => r.dataset.group).join(',')") == "Gp4rQs,Hq5sTu", f.js(f"{ROWS}.map((r) => r.dataset.group).join(',')")
    text = f.js(f"{row(f, 'Gp4rQs')}.innerText")
    for bit in ("Thursday Singers", "Ana Ruiz", "14", "9", "23", "58"):
        assert bit in text, f"{bit} in {text!r}"
    assert t(f, "admin.choirGroups.intro", {"shown": 2, "total": 2, "zone": "UTC"}).split(" (")[0] in f.js("document.querySelector('[data-choir-groups]').innerText")

    # The 8 weeks: a bar a week, oldest first, the last week's 9 singers of 14 the tallest; a quiet week a sliver.
    bars = f"{row(f, 'Gp4rQs')}.querySelectorAll('[data-group-weeks] [data-week]')"
    assert f.js(f"{bars}.length") == 8, "eight weeks"
    assert f.js(f"[...{bars}].map((b) => b.dataset.singers).join(',')") == "0,0,0,0,0,0,6,9"
    heights = f.js(f"[...{bars}].map((b) => Math.round(b.getBoundingClientRect().height))")
    assert heights[7] > heights[6] > heights[0] > 0, heights
    assert all(w >= 8 for w in f.js(f"[...{bars}].map((b) => b.getBoundingClientRect().width)")), "each bar is drawn (its classes are in the stylesheet)"
    assert "6, 9" in f.js(f"{row(f, 'Gp4rQs')}.querySelector('[data-group-weeks]').getAttribute('aria-label')"), "the bar says its numbers"

    # A group nobody has sung in yet: zeros, every week a sliver.
    quiet = f.js(f"{row(f, 'Hq5sTu')}.innerText")
    assert "Riverside Chorale" in quiet and "Sam Okafor" in quiet, quiet
    f.no_sideways("the choir groups")
    f.shot("choir-groups")

    # A group's seats for staff (admin.groups.seats.show): the pack, who holds the seats, the director's moves; a staff
    # move in sudo with a reason, not counted against the director's 3 in 30 days.
    f.run(f"{row(f, 'Gp4rQs')}.querySelector('[data-admin-seats-open]').click()")
    f.wait("document.querySelector('[data-admin-seats-status=active]')", what="the group's seats")
    sheet = f.js("document.querySelector('[data-admin-seats]').innerText")
    assert t(f, "admin.choirGroups.seats.used", {"used": "11", "bought": "12"}) in sheet, sheet
    for name in ("Ada Lovelace", "Alan Turing", "Mary Somerville"):
        assert name in sheet, f"{name} in the seats"
    assert f.js("document.querySelector('[data-admin-seats-stripe]').target") == "_blank"
    f.shot("seats")
    f.run("window.mockCalls = []; document.querySelector('[data-admin-seats-move]').click()")
    last = "[...document.querySelectorAll('[role=dialog]')].at(-1)"
    f.wait(f"{last}?.querySelector('[data-admin-seats-from]')", what="the move sheet")
    f.run(f"const r = {last}.querySelector('textarea'); r.value = 'The director asked by email'; r.dispatchEvent(new Event('input'));")
    f.run(f"{last}.querySelector('button[type=submit]').click()")
    f.wait("window.mockCalls.includes('admin.groups.seats.update')", what="the seat moved")
    f.wait_text(t(f, "admin.choirGroups.seats.moved"))
    f.press("Escape")
    f.wait("!document.querySelector('[data-admin-seats]')", what="the seats closed")

    # No groups yet: said plainly.
    f.go("/admin/people?api=mock&as=admin", "document.querySelector('h1')")
    f.run("const { api } = await import('/js/api.js'); api.admin.groups.list = async () => ({ data: { groups: [], total: 0, timezone: 'UTC' } });")
    f.run("(await import('/js/router.js')).navigate('/admin/groups')")
    f.wait("document.querySelector('[data-choir-groups-none]')", what="no groups yet")
    f.shot("choir-groups-none")


if __name__ == "__main__":
    run("admin_choir_groups", journey, sizes=("desktop", "phone"))
