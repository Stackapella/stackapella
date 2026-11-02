"""Admin analytics slices 3 to 5 (docs/admin-analytics-plan.md), on the contract's examples: Growth >
Cohorts (each month's sign-ups and how far they got, counts only under 20), Growth > Activity (the
latest events, 24 hourly bars, what's used, free accounts at their limit) and Revenue's churn and
trials.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def journey(f):
    f.go("/admin/cohorts?api=mock&scenario=", "document.querySelector('[data-cohorts] [data-cohort]')")
    assert f.js("document.querySelectorAll('[data-cohort]').length") == 12, "a year of months"
    small = f.js("[...document.querySelectorAll('[data-cohort] [data-col]')].map((c) => c.textContent.trim()).find((x) => x.includes('%'))")
    rows = f.js("[...document.querySelectorAll('[data-cohort]')].map((r) => [r.querySelector('[data-signed-up]').textContent.trim(), r.querySelector('[data-col=finishedSong]').textContent.trim()])")
    for signed, finished in rows:
        if int(signed.replace(",", "")) < 20:
            assert "%" not in finished, f"a percentage of {signed}: {finished}"
    f.shot("cohorts")
    f.go("/admin/activity?api=mock&scenario=", "document.querySelector('[data-activity-feed] [data-event]')")
    assert f.js("document.querySelectorAll('[data-activity-hours] [data-hour]').length") == 24, "24 hours"
    assert "App Store" in f.js("document.querySelector('[data-event=plus_started]').textContent"), "the store by name"
    f.shot("activity")
    f.go("/admin/revenue?api=mock&scenario=", "document.querySelector('[data-churn] [data-churn-month]')")
    assert f.js("document.querySelectorAll('[data-churn-month]').length") == 12
    f.run("document.querySelector('[data-churn]').scrollIntoView({ block: 'center' })")
    f.shot("churn")


if __name__ == "__main__":
    run("admin_insights", journey)
