"""Admin > Numbers > Choir (admin.choir.show): the six numbers with their moves on the period before, the two daily
trends (with their table views), the funnel, why clips failed, each capture song, the tries to a first choir, the
parts that failed their checks and every count grouped; the period remembered. Against the mock (the contract's example).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin?api=mock&scenario=&as=admin", "document.querySelector('main')")
    f.run("localStorage.removeItem('stackapella.admin.choir.period')")
    f.go("/admin/choir?api=mock&scenario=&as=admin", "document.querySelector('[data-admin-choir] [data-choir-panel]')")
    # Under Numbers, beside the other reads.
    assert f.js("[...document.querySelectorAll('[data-nav-sections] a')].some((a) => a.getAttribute('href') === '/admin/choir')"), "Choir isn't under Numbers"
    # Live first (admin.choir.live): the queue, today against the meter, the month's GPU cost, the day's failures, the
    # latest requests (a song and its facts, never a singer), and the worker (the example's last poll is long gone with
    # songs queued: quiet, said in words).
    f.wait("document.querySelector('[data-choir-live] [data-live-tiles]')", what="the Live panel")
    assert f.js("document.querySelectorAll('[data-live-tile]').length") == 4
    assert f.js("document.querySelectorAll('[data-live-failures] li').length") == 2
    assert f.js("[...document.querySelectorAll('[data-live-request]')].map((r) => r.dataset.liveRequest)") == ["ready", "failed"]
    # The worker's state against a clock (the example's times are fixed): quiet 30 minutes after its last poll with songs
    # waiting, not 2 minutes after; the line on the page says one or the other.
    quiet = f.js("(async () => { const { workerState } = await import('/js/admin/choir.js'); const { data } = await (await import('/js/api.js')).api.admin.choir.live(); const at = Date.parse(data.worker.lastPollAt); return [workerState(data, at + 30 * 60000).quiet, workerState(data, at + 2 * 60000).quiet]; })()")
    assert quiet == [True, False], quiet
    assert f.js("['yes', 'no'].includes(document.querySelector('[data-live-worker]').dataset.quiet)")
    values = f.js("Object.fromEntries([...document.querySelectorAll('[data-choir-panel] [data-tile]')].map((d) => [d.dataset.tile, d.querySelector('[data-value]')?.textContent.trim() ?? null]))")
    assert values == {"made": "52", "sent": "84", "madeShare": "62%", "firstTry": "70%", "gpu": "5.1", "plus": "3"}, values
    moves = f.js("Object.fromEntries([...document.querySelectorAll('[data-choir-panel] [data-tile]')].map((d) => [d.dataset.tile, d.querySelector('[data-move]')?.dataset.dir ?? null]))")
    assert moves == {"made": "up", "sent": "up", "madeShare": "down", "firstTry": None, "gpu": "up", "plus": "up"}, moves
    # The trends: a column a day with a count, each with a table of the same numbers.
    assert f.js("document.querySelectorAll('[data-choir-trend]').length") == 2
    assert f.js("document.querySelectorAll('[data-choir-trend=choir_take_sent] [data-day] path').length") == 2, "a column a day with takes"
    f.run("document.querySelector('[data-choir-trend=choir_take_sent] button').click()")
    f.wait("document.querySelector('[data-choir-trend=choir_take_sent] table')", what="the trend's table")
    assert f.js("document.querySelectorAll('[data-choir-trend=choir_take_sent] tbody tr').length") == 30, "a row a day of the 30"
    f.run("document.querySelector('[data-choir-trend=choir_take_sent] button').click()")
    # The funnel from the intro, each step's share of it; the reasons most first.
    steps = f.js("[...document.querySelectorAll('[data-choir-funnel] [data-bar-row]')].map((r) => r.dataset.barRow)")
    assert steps == ["intro", "take", "sent", "requested", "ready", "seen", "played"], steps
    assert "19%" in f.js("document.querySelector('[data-choir-funnel] [data-bar-row=played]').textContent"), "played's share of the intro"
    reasons = f.js("[...document.querySelectorAll('[data-choir-reasons] [data-bar-row]')].map((r) => r.dataset.barRow)")
    assert reasons[:3] == ["notes", "quiet", "noisy"], reasons
    # Each capture song (Happy Birthday's 71% made a voice; Joy to the World too few to tell).
    rows = f.js("Object.fromEntries([...document.querySelectorAll('[data-choir-song]')].map((r) => [r.dataset.choirSong, r.lastElementChild.textContent.trim()]))")
    assert rows == {"capture": "71%", "silent": t(f, "admin.choir.tooFew"), "grace": t(f, "admin.choir.tooFew"), "joy": t(f, "admin.choir.tooFew")}, rows
    assert f.js("document.querySelectorAll('[data-choir-tries] [data-bar-row]').length") == 4
    assert f.js("document.querySelectorAll('[data-choir-gates] [data-bar-row]').length") == 2
    # Every count, grouped.
    assert f.js("!!document.querySelector('[data-choir-group=choir_guide_] [data-kind=choir_guide_off]')"), "the guide counts aren't grouped"
    assert f.js("!!document.querySelector('[data-choir-group=own_choir_] [data-kind=own_choir_ready]')"), "the server's counts aren't grouped"
    f.no_sideways("Choir")
    f.shot("choir")
    # The period, remembered.
    f.run("document.querySelector('[data-choir-period] [role=radio]:nth-child(1)').click()")
    f.wait("localStorage.getItem('stackapella.admin.choir.period') === '7d'", what="7 days remembered")
    f.run("localStorage.removeItem('stackapella.admin.choir.period')")


if __name__ == "__main__":
    run("admin_choir", journey)
