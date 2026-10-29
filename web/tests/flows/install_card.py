"""The install card (docs/operations-plan.md, "Keeping songs safe"): with the browser's offer to
install (headless Chrome offers it on localhost), the home offers it; "Not now" ends the plain offer. Once a song is recorded the card says
the songs are only on this device, and "Not now" holds it back for a week. Installing calls the
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


def journey(f):
    f.go(HOME, "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.installDismissed'); localStorage.removeItem('stackapella.safeCardDismissed')")
    f.go(HOME, "document.querySelector('#app header')")
    f.wait("true", what="loaded")
    time.sleep(1)  # Chrome offers to install here for real; ours replaces it, to see the call
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
    # A recorded song: the card comes back with what's at stake.
    f.run("""(async () => { const s = await import('/js/stores/projects.js'); const p = await s.createProject({ title: 'Safe test' });
        await s.savePart({ id: crypto.randomUUID(), projectId: p.id, name: 'Lead', color: '#f59e6b', order: 0, volume: 1, pan: 0, muted: false, solo: false,
            offsetSeconds: 0, frames: 4800, sampleRate: 48000, peaks: [0.2, 0.4], createdAt: Date.now() }, new Float32Array(4800));
        await s.loadProjects(); window.safeProject = p.id; })()""")
    f.wait("window.safeProject", what="a recorded project")
    f.wait(f"{CARD} === (await import('/js/i18n.js')).t('install.safeTitle')", what="install.safeTitle")
    f.shot("songs")
    f.click_t("install.later")
    f.go(HOME, "document.querySelector('#app header')")
    f.run(OFFER)
    time.sleep(0.5)
    assert not f.js("!!document.querySelector('#install-title')"), "held back for a week"
    # A week later it's back.
    f.run("localStorage.setItem('stackapella.safeCardDismissed', String(Date.now() - 8 * 24 * 3600 * 1000))")
    f.go(HOME, "document.querySelector('#app header')")
    f.run(OFFER)
    f.wait(f"{CARD} === (await import('/js/i18n.js')).t('install.safeTitle')", what="install.safeTitle")
    f.run("(async () => { const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all.filter((x) => x.title === 'Safe test')) await s.deleteProject(p.id); })()")


if __name__ == "__main__":
    run("install_card", journey)
