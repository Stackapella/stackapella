"""Admin's reads for the retention work (docs/retention-plan.md): Numbers > Drop-offs (admin.dropoffs.show: the week's
worst steps, every path's steps against their neighbours, their weeks before, last week and the last 28 days, "too few"
under the floor) and Numbers > Sharing (admin.sharing.show: the four measures with their move on last week, a row a
surface), and both on Home's What needs you now. Against the mock (the contract's examples).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # Drop-offs: the two worst from the example, its lesson's three steps, the two drops marked.
    f.go("/admin/dropoffs?api=mock&scenario=&as=admin", "document.querySelector('[data-dropoffs] [data-dropoff-worst]')")
    assert f.js("document.querySelectorAll('[data-dropoff-worst]').length") == 2
    first = f.js("document.querySelector('[data-dropoff-worst]').innerText")
    assert "Lesson 4, Words to a tune" in first and "61" in first, first
    assert f.js("document.querySelectorAll('[data-dropoff-path=\"lesson:4-words\"] [data-dropoff-step]').length") == 3
    assert f.js("document.querySelectorAll('[data-dropoff-drop]').length") == 2
    f.no_sideways("Drop-offs")
    f.shot("dropoffs")
    # Sharing: the four measures with their moves; a surface under the floor says so.
    f.go("/admin/sharing?api=mock&scenario=&as=admin", "document.querySelector('[data-sharing] [data-surface]')")
    values = f.js("Object.fromEntries([...document.querySelectorAll('[data-sharing-panel] [data-measure]')].map((d) => [d.dataset.measure, d.querySelector('[data-value]')?.textContent.trim() ?? null]))")
    assert values == {"sharesPer100": "15", "arrivalsPerShare": "0.81", "arrivalsThatSing": "47%", "eachHundredBring": "5.7"}, values
    assert f.js("document.querySelector('[data-measure=sharesPer100] [data-move]').dataset.dir") == "up"
    rows = f.js("[...document.querySelectorAll('[data-surface]')].map((r) => r.dataset.surface + ' ' + r.lastElementChild.textContent.trim())")
    assert rows == ["note 0.27", "group " + t(f, "admin.sharing.tooFew")], rows
    f.no_sideways("Sharing")
    f.shot("sharing")
    # Breadth: three or more this week and its moves, a stacked bar a week for guests and accounts, the ladder.
    f.go("/admin/breadth?api=mock&scenario=&as=admin", "document.querySelector('[data-breadth] [data-breadth-headline]')")
    assert f.js("document.querySelector('[data-measure=threePlus] [data-value]').textContent.trim()") == "25%"
    assert f.js("document.querySelector('[data-measure=lastWeek] dd').dataset.dir") == "up"
    assert f.js("document.querySelectorAll('[data-breadth-bars] [data-week]').length") == 5
    assert f.js("document.querySelectorAll('[data-feature]').length") == 11
    ladder = f.js("[...document.querySelectorAll('[data-feature]')].slice(0, 3).map((r) => r.children[3].textContent.trim())")
    assert ladder == ["63%", "25%", t(f, "admin.breadth.tooFew")], ladder
    f.no_sideways("Breadth")
    f.shot("breadth")
    f.run("document.querySelector('[data-breadth-table-toggle]').click()")
    f.wait("document.querySelector('[data-breadth-weeks-table] tbody tr')", what="the weeks as a table")
    # Home: the worst drop-offs as rows, and sharing with its weakest surface named.
    f.go("/admin?api=mock&scenario=&as=admin", "document.querySelector('[data-needs-dropoffs] [data-needs-dropoff]') && document.querySelector('[data-needs-sharing] [data-sharing-panel]')")
    assert f.js("document.querySelectorAll('[data-needs-dropoff]').length") == 2
    assert f.js("document.querySelector('[data-needs-dropoff]').getAttribute('href')") == "/admin/dropoffs#path-lesson-4-words"
    f.wait("document.querySelector('[data-needs-breadth]')", what="Breadth's line")
    assert f.js("document.querySelector('[data-needs-breadth]').textContent.trim()") == t(f, "admin.breadth.homeLine", {"share": "25%", "last": "10%"})
    weakest = f.js("document.querySelector('[data-needs-sharing-weakest]').textContent.trim()")
    assert weakest == t(f, "admin.needs.weakest", {"surface": t(f, "admin.sharing.surface.note"), "perShare": "0.27"}), weakest
    f.run("document.querySelector('[data-needs-dropoffs]').scrollIntoView({ block: 'start' })")
    f.no_sideways("Home with drop-offs and sharing")
    f.shot("home-cards")
    # The drop-off's link opens its path on the Drop-offs page.
    f.run("document.querySelector('[data-needs-dropoff]').click()")
    f.wait("location.pathname === '/admin/dropoffs' && document.querySelector('#path-lesson-4-words')", what="the path's table")


if __name__ == "__main__":
    run("admin_retention", journey)
