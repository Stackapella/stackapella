"""keep1, the first-week keep row (docs/account-nudges-plan.md, N1): a guest back on day 8 with three
lessons starred sees Home's "Your first week" card. In arm b it carries the keep row ("Keep this week:
your note, 8 stars and level 2, on any device. It's free."), Make a free account opens sign-up for
the week, and Not now puts the whole card away and holds the asks back. Arm a is the card as it was.
Signed in, there's no row.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
CARD = "document.querySelector('[data-first-week]')"
KEEP = "document.querySelector('[data-first-week-keep]')"
SETUP = """const day = 86400000; const d = new Date(Date.now() - 8 * day); const arrived = new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    localStorage.setItem('stackapella.funnel', JSON.stringify({ firsts: {}, variant: null, arrived }));
    for (const k of ['firstWeekSeen', 'experiments']) localStorage.removeItem('stackapella.' + k);
    localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] })); // Home's install card asks first; put away here
    const { dayOf, emptyProgress } = await import('/js/course.js'); const db = await import('/js/db.js');
    const practice = { [dayOf(Date.now() - 7 * day)]: 400, [dayOf(Date.now() - 2 * day)]: 600 };
    await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 8 * day, home: 57, range: { low: 45, high: 62 }, practice, xp: { total: 120, days: {} },
        lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() - 7 * day }, '1-match': { stars: 3, bestPercent: 90, at: Date.now() - 7 * day }, '1-high-low': { stars: 2, bestPercent: 80, at: Date.now() - 2 * day } } }, 'progress');"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def home(f, arm, who="signedOut"):
    f.go(f"/?api=mock&scenario=&as={who}&x=keep1-{arm}", "document.querySelector('#app header')")


def journey(f):
    home(f, "b")
    f.run(SETUP)
    home(f, "b")
    f.wait(KEEP, what="the keep row (arm b)")
    f.run(f"{CARD}.scrollIntoView({{ block: 'center' }})")
    what = [t(f, "wrap.week.keepNote")] + ([t(f, "wrap.week.keepStars", {"count": 8}), t(f, "wrap.week.keepLevel", {"level": 2})] if f.skin == "game" else [t(f, "wrap.week.keepLessons", {"count": 3})])
    line = t(f, "wrap.week.keep", {"what": f.js(f"{I18N}.formatList({json.dumps(what)})")})
    assert f.js(f"{KEEP}.textContent").find(line) >= 0, f"{f.js(f'{KEEP}.textContent')!r} lacks {line!r}"
    assert f.js("document.querySelector('[data-first-week-account]').classList.contains('btn-secondary')"), "Home's own action stays the one primary"
    assert not f.js("!!document.querySelector('[data-first-week-done]')"), "Not now replaces Got it"
    f.shot("keep-row")
    f.run("document.querySelector('[data-first-week-account]').click()")
    f.wait("location.pathname === '/signin' && new URLSearchParams(location.search).get('for') === 'week'", what="sign-up, for the week")
    f.wait_text(t(f, "auth.reason.week"))
    home(f, "b")
    f.wait(KEEP, what="the keep row again today")
    f.run("document.querySelector('[data-first-week-not-now]').click()")
    f.wait(f"!{CARD}", what="Not now puts the card away")
    assert f.js("JSON.parse(localStorage.getItem('stackapella.asks')).nots") == 1, "the Not now kept in the ledger"
    # Drawn for real (no preview), as the started keep1 runs: the arm kept, the row shown and counted.
    f.run(SETUP)
    f.run("sessionStorage.removeItem('stackapella.experimentPreview'); sessionStorage.setItem('stackapella.mockExperiments', JSON.stringify([{ id: 'keep1', arms: [{ arm: 'b', weight: 1 }], enrol: true, audience: { signedIn: false, firstWeek: true }, excludes: [] }]));")
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    f.wait(KEEP, what="the keep row, drawn into arm b")
    assert f.js("JSON.parse(localStorage.getItem('stackapella.experiments') || '{}').keep1") == "b", "the arm kept"
    assert f.js("JSON.parse(localStorage.getItem('stackapella.funnel')).firsts.ask_acct_week_shown") is True, "acct_week shown counted"
    assert f.js("(await import('/js/usage.js')).KINDS.has('ask_acct_week_shown') && (await import('/js/usage.js')).KINDS.has('ask_acct_week_not')"), "the kinds the server takes"
    f.run("sessionStorage.removeItem('stackapella.mockExperiments');")
    # Arm a: the card as it was, Got it and no row.
    f.run(SETUP)
    home(f, "a")
    f.wait(f"{CARD} && document.querySelector('[data-first-week-done]')", what="the plain card (arm a)")
    assert not f.js(f"!!{KEEP}"), "no keep row in arm a"
    f.shot("arm-a")
    # Signed in: nothing to keep this way.
    f.run(SETUP)
    home(f, "b", "member")
    f.wait("document.querySelector('main')", what="Home")
    f.js("new Promise((r) => setTimeout(r, 600))")
    assert not f.js(f"!!{KEEP}"), "a keep row signed in"


if __name__ == "__main__":
    run("keep_week", journey)
