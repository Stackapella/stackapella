"""The app's move to its new address (docs/app-subdomain-plan.md, "Old installs"), on two origins of
this machine (127.0.0.1 is the old address, localhost the new): a song with a take and a chosen look
on the old one; its "Stackapella has moved" page (the server's, stood in by tool/serve.php's
/dev/moving) finds it, Bring my songs opens /moved on the new address and hands it over; the old
address's service worker goes; the new address has the song, its take and the look, and something
already there stays as it was.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    old = f.base
    new = f.base.replace("127.0.0.1", "localhost")
    assert old != new, "the flows run on 127.0.0.1"
    f.expected_errors += ["404 (Not Found) " + new]
    # The new address first: something already there stays.
    f.base = new
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    f.run("const store = await import('/js/stores/projects.js'); await store.createProject({ title: 'Already here' });")
    # The old address: a song with a take, and a look.
    f.base = old
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    moved_id = f.js("""(async () => {
        const store = await import('/js/stores/projects.js');
        const { peaks } = await import('/js/dsp.js');
        const p = await store.createProject({ title: 'Carried over' });
        const s = new Float32Array(48000).map((_, i) => 0.3 * Math.sin(i / 20));
        await store.savePart({ id: crypto.randomUUID(), projectId: p.id, name: 'Lead', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
            offsetSeconds: 0, nudgeSeconds: 0, sampleRate: 48000, frames: s.length, framesPerPeak: 512, peaks: peaks(s, 512) }, s);
        return p.id; })()""")
    f.run("localStorage.setItem('stackapella.appearance', JSON.stringify({ mode: 'light', colour: 'midnight', at: Date.now() }));")
    f.wait("!!navigator.serviceWorker.controller", what="the old address's service worker")
    found, bring, moved = t(f, "moving.found", {"count": 1}), t(f, "moving.bring"), t(f, "moving.moved")  # the moving page has no app modules
    # Its moving page.
    f.go(f"/dev/moving?to={new}/", "document.querySelector('[data-moving] h1')")
    f.wait(f"document.querySelector('[data-moving]').innerText.includes({json.dumps(found)})", what="the song found")
    f.shot("moving")
    # A real key on the focused button: opening a window needs the person's own tap or key.
    f.run(f"[...document.querySelectorAll('[data-moving] button')].find((b) => b.textContent === {json.dumps(bring)}).focus()")
    f.press("Enter")
    f.wait(f"document.querySelector('[data-moving] h1')?.textContent === {json.dumps(moved)}", timeout=40, what="moved")
    f.shot("moved")
    assert f.js("navigator.serviceWorker.getRegistrations().then((r) => r.length)") == 0, "the old service worker is gone"
    # The new address has it, its take and the look; what was there stays.
    f.base = new
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    titles = f.js("(await import('/js/db.js')).all('projects').then((all) => all.map((p) => p.title).sort().join())")
    assert titles == "Already here,Carried over", titles
    frames = f.js(f"(async () => {{ const db = await import('/js/db.js'); const parts = await db.allBy('parts', 'projectId', {json.dumps(moved_id)}); return (await db.getAudio(parts[0].id)).length; }})()")
    assert frames == 48000, f"the take came across: {frames}"
    assert f.js("document.documentElement.dataset.theme") == "midnight-light", "the look came across"
    f.base = old


if __name__ == "__main__":
    run("moving", journey, sizes=("desktop",))
