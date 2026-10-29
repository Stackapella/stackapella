"""Plus (M5), against the mock: the shelf marks the song of the month free; the Plus page from an
upgrade moment says what led there, shows the yearly price as a monthly one and the trial, and
Checkout opens Stripe's page (caught here); Export offers MP3 and each part with Plus, and leads a
free account to Plus; on Plus, the page shows the plan as it stands, with Manage.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
PLAN = "(await import('/js/stores/plan.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # The shelf: the song of the month, free this month.
    f.go("/?api=mock&as=member", "document.querySelector('#band-songs')")
    f.wait_text("Silent Night")
    f.wait_text(t(f, "home.songs.freeMonth"))

    # The Plus page from the second workout of the day.
    f.go("/plus?api=mock&as=member&from=workout", "document.querySelector('h1')")
    f.wait_text(t(f, "plus.moment.workout"))
    f.wait_text(t(f, "plus.yearly"))
    f.run("const s = document.querySelector('select'); s.value = 'usd'; s.dispatchEvent(new Event('change'));")
    f.wait_text("$3.33")
    # The mock's billing.show is a Plus account's (no trial on offer), so the button may say Get Plus.
    f.wait(f"document.body.innerText.includes({I18N}.t('plus.startTrial', {{ days: 7 }})) || document.body.innerText.includes({I18N}.t('plus.subscribe'))", what="the button")
    f.shot("plus-page")
    f.run(f"const p = {PLAN}; window.opened = null; p.stripe.open = (url) => (window.opened = url);")
    f.run("document.querySelector('button.btn-lg').click()")
    f.wait("window.opened && window.opened.startsWith('https://checkout.stripe.com/')", what="Checkout opened")

    # Export: MP3 is Plus for a free account, with the way there.
    f.go("/?api=mock&as=member", "document.querySelector('#app header')")
    project_id = f.js("""(async () => {
        const store = await import('/js/stores/projects.js');
        const { peaks } = await import('/js/dsp.js');
        const project = await store.createProject({ title: 'Plus test', bpm: 120 });
        const samples = new Float32Array(48000).map((_, i) => 0.3 * Math.sin((2 * Math.PI * 220 * i) / 48000));
        await store.savePart({ id: crypto.randomUUID(), projectId: project.id, name: 'Lead', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
            offsetSeconds: 0, nudgeSeconds: 0, sampleRate: 48000, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }, samples);
        return project.id;
    })()""")
    f.go(f"/p/{project_id}?api=mock&as=member", "document.querySelector('#app')")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 1 && document.querySelector('header')", what="the studio")
    # Sing together before the song is on the account (106.png): Save and invite, first.
    if f.size == "desktop":
        f.run(f"const db = await import('/js/db.js'); const p = await db.get('projects', '{project_id}'); p.version = 0; await db.put('projects', p);")
        f.click_t("studio.inviteSingers")
        f.wait("document.querySelector('[data-save-first]')", what="Save and invite")
        f.shot("save-first")
        # The mock keeps cloud save off, so the save can't go through here: it says so, and stays.
        f.click_t("together.saveAndInvite")
        f.wait_text(t(f, "together.notSavedYet"))
        f.press("Escape")
        f.wait("!document.querySelector('[role=dialog]')", what="the sheet closed")
    if f.size == "phone":
        f.run("document.querySelector('header details').open = true")
    f.click_t("studio.export")
    f.wait("[...document.querySelectorAll('[role=dialog] [role=radio]')].some((b) => b.textContent.includes('MP3'))", what="the format choice")
    f.run("[...document.querySelectorAll('[role=dialog] [role=radio]')].find((b) => b.textContent.includes('MP3')).click()")
    f.wait_text(t(f, "plus.moment.mp3"))
    f.shot("export-locked")

    # On Plus: the plan as it stands.
    f.go("/plus?api=mock&as=plus", "document.querySelector('h1')")
    f.wait_text(t(f, "plus.yours"))
    f.wait_text(t(f, "plus.manage"))
    f.shot("on-plus")

    # On Plus, MP3 export: lamejs from the CDN encodes the mix.
    f.go(f"/p/{project_id}?api=mock&as=plus", "document.querySelector('#app')")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 1 && document.querySelector('header')", what="the studio")
    f.wait("(await import('/js/stores/account.js')).account.me?.plan === 'plus'", what="on Plus")
    f.run("window.downloads = []; HTMLAnchorElement.prototype.click = function () { if (this.download) window.downloads.push([this.download, this.href]); };")
    if f.size == "phone":
        f.run("document.querySelector('header details').open = true")
    f.click_t("studio.export")
    f.wait("[...document.querySelectorAll('[role=dialog] [role=radio]')].some((b) => b.textContent.includes('MP3'))", what="the format choice")
    f.run("[...document.querySelectorAll('[role=dialog] [role=radio]')].find((b) => b.textContent.includes('MP3')).click()")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.downloads.length === 1", timeout=60, what="the MP3")
    head = f.js("(async () => { const [name, url] = window.downloads[0]; const b = new Uint8Array(await (await fetch(url)).arrayBuffer()); return [name, b.length, b[0], b[1]]; })()")
    assert head[0].endswith(".mp3") and head[1] > 5000 and head[2] == 0xFF and (head[3] & 0xE0) == 0xE0, f"an MP3: {head}"


if __name__ == "__main__":
    run("plus", journey)
