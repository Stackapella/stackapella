"""Admin analytics (docs/admin-analytics-plan.md, slices 1 and 2), on the contract's examples: the
home's last 7 days with the change on the 7 before; Growth's people using the app, signed in and
guests, 12 weeks against the 12 before; and Revenue: MRR and ARR in each currency (never converted), store subscriptions without a
price said as a count, subscribers by state, store and plan, 12 months, and one-off income.
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
    f.go("/admin?api=mock&scenario=", "document.querySelector('[data-home-growth] [data-tile]')")
    dirs = f.js("Object.fromEntries([...document.querySelectorAll('[data-home-growth] [data-tile] [data-change]')].map((c) => [c.closest('[data-tile]').dataset.tile, c.dataset.dir]))")
    assert dirs["signUps"] in ("up", "down", "same") and dirs["activeSingers"] in ("up", "down", "same"), dirs
    f.shot("home")
    # Growth opens on today: the chart's today against the same weekday a week before (admin_growth
    # covers 7 and 30 days).
    f.go("/admin/growth?api=mock&scenario=", "document.querySelector('[data-reach-chart] [data-today]')")
    assert f.js("document.querySelector('[data-reach-chart]').dataset.measure") in ("dayOpens", "dayDevices")
    assert f.js("document.querySelectorAll('[data-reach-chart] [data-today] [data-row]').length") == 2
    f.run("document.querySelector('[data-reach-chart]').scrollIntoView({ block: 'center' })")
    f.shot("reach")
    f.go("/admin/revenue?api=mock&scenario=", "document.querySelector('[data-revenue] [data-tile=mrr]')")
    mrr = f.js("[...document.querySelectorAll('[data-tile=mrr] [data-currency]')].map((e) => e.dataset.currency + ' ' + e.textContent.trim())")
    assert mrr == ["gbp £8.98", "usd $46.58"], mrr
    assert t(f, "admin.revenue.unpriced", {"count": 2}) in f.js("document.querySelector('[data-unpriced]').textContent"), "the store subscriptions as a count"
    assert f.js("document.querySelector('[data-by=\"state:paying\"]').textContent").strip().endswith("11"), f.js("document.querySelector('[data-by=\"state:paying\"]').textContent")
    assert f.js("document.querySelectorAll('[data-months] [data-month]').length") == 12, "12 months"
    assert f.js("document.querySelectorAll('[data-one-off-row]').length") == 2, "the one-off rows"
    f.shot("revenue")
    f.run("document.querySelector('[data-months] [role=radio]:not([aria-checked=true])')?.click()")
    f.run("document.querySelector('[data-one-off]').scrollIntoView({ block: 'center' })")
    f.shot("revenue-months")


if __name__ == "__main__":
    run("admin_analytics", journey)
