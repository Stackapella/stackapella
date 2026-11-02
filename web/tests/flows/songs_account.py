"""N2, songs only here (docs/account-nudges-plan.md): from day 7, a guest with a song recorded where
installing is done or can't keep it (Firefox, a desktop without the offer, the iPhone's added app)
sees Home's card ask for the free account: "Your songs are only on this device. 1 song, 1 take. A
free account keeps a copy and opens them on any device." In week one, no card. Not now holds every
ask back 14 days.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
HOME = "/?api=mock&scenario=&as=signedOut"
CARD = "document.querySelector('[data-install]')"
# The flow's Chrome offers to install; a browser that can't is what N2 is for, so the offer goes, and
# the day's one ask it took is given back.
NO_PROMPT = "(await import('/js/components/install.js')).install.prompt = null; localStorage.removeItem('stackapella.asks');"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def arrived(f, days):
    f.run(f"""const d = new Date(Date.now() - {days} * 86400000); const arrived = new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        localStorage.setItem('stackapella.funnel', JSON.stringify({{ firsts: {{}}, variant: null, arrived }}));""")


def home(f):
    f.go(HOME, "document.querySelector('#app header')")
    f.wait("document.querySelector('main')", what="Home")
    f.js("new Promise((r) => setTimeout(r, 600))")
    f.run(NO_PROMPT)
    f.js("new Promise((r) => setTimeout(r, 300))")


def journey(f):
    f.go(HOME, "document.querySelector('#app header')")
    f.run("""(async () => { const s = await import('/js/stores/projects.js'); const p = await s.createProject({ title: 'Only here' });
        await s.savePart({ id: crypto.randomUUID(), projectId: p.id, name: 'Lead', color: '#f59e6b', order: 0, volume: 1, pan: 0, muted: false, solo: false,
            offsetSeconds: 0, frames: 4800, sampleRate: 48000, peaks: [0.2, 0.4], createdAt: Date.now() }, new Float32Array(4800));
        window.onlyHere = p.id; })()""")
    f.wait("window.onlyHere", what="a recorded song")
    arrived(f, 3)
    home(f)
    assert not f.js(f"!!{CARD}"), "a card in week one where installing can't help"
    arrived(f, 8)
    home(f)
    f.wait(f"{CARD}?.dataset.form === 'account'", what="the songs card asking for an account")
    text = f.js(f"{CARD}.textContent")
    assert t(f, "install.keepHere", {"songs": 1, "takes": 1}) in text, text
    assert f.js(f"{CARD}.querySelector('[data-install-account]').classList.contains('btn-secondary')"), "Home's own action stays the one primary"
    f.run(f"{CARD}.scrollIntoView({{ block: 'center' }})")
    f.shot("songs-account")
    f.click_t("install.later")
    f.wait(f"!{CARD}", what="Not now")
    ledger = f.js("JSON.parse(localStorage.getItem('stackapella.asks'))")
    assert ledger["nots"] == 1 and "acct_songs" not in ledger["stopped"], ledger
    f.run("(async () => { const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all.filter((x) => x.title === 'Only here')) await s.deleteProject(p.id); })()")


if __name__ == "__main__":
    run("songs_account", journey)
