"""N4, the Safari loss card (docs/account-nudges-plan.md): a guest in Safari on an iPhone, with a song
recorded here, back after five days away, sees Home's card say plainly that Safari may clear this:
"It deletes a website's data after 7 days without a visit, and these are only here: 1 song." Make
a free account opens sign-up for it; Not now puts it away for a month. Back after two days, nothing.

One account ask at a time (js/asks.js): a guest past their open lessons, three starred, has the course
wall on Home, which asks for the account itself, so the card waits, and isn't counted as the day's ask.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
IPHONE = "Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1"
HOME = "/?api=mock&scenario=&as=signedOut"
CARD = "document.querySelector('[data-install]')"
NO_PROMPT = "(await import('/js/components/install.js')).install.prompt = null;"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def away(f, days):
    """The last visit `days` ago, and the ask ledger fresh; the next load reads the gap."""
    f.run(f"""const d = new Date(Date.now() - {days} * 86400000); localStorage.setItem('stackapella.lastVisit', new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10));
        sessionStorage.removeItem('stackapella.daysAway'); localStorage.removeItem('stackapella.asks');""")


def home(f):
    f.go(HOME, "document.querySelector('#app header')")
    f.wait("document.querySelector('main')", what="Home")
    f.run(NO_PROMPT)
    f.js("new Promise((r) => setTimeout(r, 600))")


def journey(f):
    f.chrome.send("Emulation.setUserAgentOverride", {"userAgent": IPHONE, "platform": "iPhone"})
    # A real iPhone never offers to install: Chrome's offer is stopped before any page script sees it.
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": "addEventListener('beforeinstallprompt', (e) => e.stopImmediatePropagation(), true);"})
    f.go(HOME, "document.querySelector('#app header')")
    f.run("""const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js'); const day = 86400000;
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 12 * day, home: 57, lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() - 9 * day }, '1-match': { stars: 2, bestPercent: 80, at: Date.now() - 9 * day }, '1-high-low': { stars: 2, bestPercent: 80, at: Date.now() - 5 * day } } }, 'progress');""")
    # Three lessons starred and the next one walled: the course wall on Home, and no card with it.
    away(f, 5)
    home(f)
    f.wait("document.querySelector('main [data-needs-account]')", what="the course wall on Home")
    f.js("new Promise((r) => setTimeout(r, 600))")
    assert not f.js(f"!!{CARD}"), "the loss card beside the course wall: two asks at once"
    assert f.js("document.querySelectorAll('[data-ask], [data-needs-account]').length") == 1, "one account ask on the page"
    ledger = f.js("JSON.parse(localStorage.getItem('stackapella.asks'))") or {}  # nothing kept is nothing recorded
    assert not ledger.get("today") and not ledger.get("riskAt"), f"the card, held back, kept no day and started no month: {ledger}"
    f.run("document.querySelector('main [data-needs-account]').scrollIntoView({ block: 'center' })")
    f.shot("walled-one-ask")
    # A guest with a song recorded here and their open lesson still to sing: no wall, so the card.
    f.run("""const db = await import('/js/db.js'); const { emptyProgress } = await import('/js/course.js');
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 12 * 86400000, home: 57 }, 'progress');
        const s = await import('/js/stores/projects.js'); const p = await s.createProject({ title: 'Only here' });
        await s.savePart({ id: crypto.randomUUID(), projectId: p.id, name: 'Lead', color: '#f59e6b', order: 0, volume: 1, pan: 0, muted: false, solo: false,
            offsetSeconds: 0, frames: 4800, sampleRate: 48000, peaks: [0.2, 0.4], createdAt: Date.now() }, new Float32Array(4800));""")
    away(f, 2)
    home(f)
    assert f.js(f"{CARD}?.dataset.form") != "risk", "the loss card after two days away"
    away(f, 5)
    home(f)
    f.wait(f"{CARD}?.dataset.form === 'risk'", what="the Safari loss card")
    text = f.js(f"{CARD}.textContent")
    assert t(f, "install.riskTitle") in text and t(f, "install.risk", {"what": t(f, "install.riskSongs", {"count": 1})}) in text, text
    assert not f.js("!!document.querySelector('main [data-needs-account]')"), "no wall with the card"
    f.run(f"{CARD}.scrollIntoView({{ block: 'center' }})")
    f.shot("safari-risk")
    f.run(f"{CARD}.querySelector('[data-install-account]').click()")
    f.wait("location.pathname === '/signin' && new URLSearchParams(location.search).get('for') === 'risk'", what="sign-up, for keeping it safe")
    f.wait_text(t(f, "auth.reason.risk"))
    home(f)
    f.wait(f"{CARD}?.dataset.form === 'risk'", what="the card again today")
    # The card's own Not now: a walled guest's Home has the course wall's Not now too, earlier on the page.
    f.run(f"{CARD}.querySelector('[data-install-later]').click()")
    f.wait(f"!{CARD}", what="Not now")
    f.run("const l = JSON.parse(localStorage.getItem('stackapella.asks')); l.day = null; l.notAt -= 15 * 86400000; localStorage.setItem('stackapella.asks', JSON.stringify(l)); sessionStorage.removeItem('stackapella.daysAway');")
    home(f)
    assert f.js(f"{CARD}?.dataset.form") != "risk", "the loss card again within the month"


if __name__ == "__main__":
    run("safari_risk", journey)
