"""The install card (docs/operations-plan.md, "Keeping songs safe"): with the browser's offer to
install (headless Chrome offers it on localhost), the home offers it once the device has done something (never on a
brand-new guest's first home); "Not now" ends the plain offer. Once a song is recorded the card says
the songs are only on this device. Every "Not now" holds back all the asks for 14 days (js/asks.js). Installing calls the
browser's own prompt.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

OFFER = """(() => { const e = new Event('beforeinstallprompt'); e.prompt = () => (window.prompted = true);
    e.userChoice = Promise.resolve({ outcome: 'dismissed' }); window.dispatchEvent(e); })()"""
CARD = "document.querySelector('#install-title')?.textContent ?? ''"
HOME = "/?api=mock&scenario=&as=signedOut"
# Days pass for the ask ledger: the last Not now and today's ask moved back.
LATER = """(() => { const l = JSON.parse(localStorage.getItem('stackapella.asks')); l.notAt -= {days} * 86400000; l.day = null;
    localStorage.setItem('stackapella.asks', JSON.stringify(l)); })()"""


def journey(f):
    f.go(HOME, "document.querySelector('#app header')")
    f.run("for (const k of ['asks', 'installDismissed', 'safeCardDismissed', 'keepAsk', 'remindAsk', 'installAskedOn']) localStorage.removeItem('stackapella.' + k)")
    f.go(HOME, "document.querySelector('#app header')")
    f.wait("true", what="loaded")
    time.sleep(1)  # Chrome offers to install here for real; ours replaces it, to see the call
    f.run(OFFER)
    # A brand-new guest's first home is Start singing and nothing competing (the coordinator, 2026-11-08): no card yet.
    time.sleep(0.5)
    assert not f.js("!!document.querySelector('#install-title')"), "the install card on a brand-new guest's first home"
    # Lesson 1 done on this device: the card comes.
    f.run("""const { emptyProgress } = await import('/js/course.js'); const db = await import('/js/db.js');
        await db.put('learning', { ...emptyProgress(), startedAt: Date.now(), home: 57, lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() } } }, 'progress');""")
    f.go(HOME, "document.querySelector('#app header')")
    time.sleep(1)
    f.run(OFFER)
    f.wait(f"{CARD} === (await import('/js/i18n.js')).t('install.title')", what="install.title")
    f.shot("offer")
    f.click_t("install.button")
    f.wait("window.prompted === true && !document.querySelector('#install-title')", what="the browser's prompt, and the card gone")
    f.run(OFFER)
    f.click_t("install.later")
    f.wait("!document.querySelector('#install-title')", what="dismissed")
    f.go(HOME, "document.querySelector('#app header')")
    f.run(OFFER)
    time.sleep(0.5)
    assert not f.js("!!document.querySelector('#install-title')"), "the plain offer stays away"
    # A recorded song, 15 days on: the card comes back with what's at stake.
    f.run(LATER.replace("{days}", "15"))
    f.run("""(async () => { const s = await import('/js/stores/projects.js'); const p = await s.createProject({ title: 'Safe test' });
        await s.savePart({ id: crypto.randomUUID(), projectId: p.id, name: 'Lead', color: '#f59e6b', order: 0, volume: 1, pan: 0, muted: false, solo: false,
            offsetSeconds: 0, frames: 4800, sampleRate: 48000, peaks: [0.2, 0.4], createdAt: Date.now() }, new Float32Array(4800));
        await s.loadProjects(); window.safeProject = p.id; })()""")
    f.wait("window.safeProject", what="a recorded project")
    f.go(HOME, "document.querySelector('#app header')")
    f.run(OFFER)
    f.wait(f"{CARD} === (await import('/js/i18n.js')).t('install.safeTitle')", what="install.safeTitle")
    f.shot("songs")
    f.click_t("install.later")
    f.go(HOME, "document.querySelector('#app header')")
    f.run(OFFER)
    time.sleep(0.5)
    assert not f.js("!!document.querySelector('#install-title')"), "held back"
    f.run(LATER.replace("{days}", "13"))
    f.go(HOME, "document.querySelector('#app header')")
    f.run(OFFER)
    time.sleep(0.5)
    assert not f.js("!!document.querySelector('#install-title')"), "held back for 14 days"
    # 14 days on it's back.
    f.run(LATER.replace("{days}", "2"))
    f.go(HOME, "document.querySelector('#app header')")
    f.run(OFFER)
    f.wait(f"{CARD} === (await import('/js/i18n.js')).t('install.safeTitle')", what="install.safeTitle")
    f.run("(async () => { const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all.filter((x) => x.title === 'Safe test')) await s.deleteProject(p.id); })()")


if __name__ == "__main__":
    run("install_card", journey)
