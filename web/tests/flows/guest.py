"""A guest on the beta link (Hillel: "let people with the beta link try the app without creating an
account, like normal users will be able to"): /invite/<code> leads with Start singing, which opens
the app with no account. The home, Learn, a lesson, a song in the studio, Sing and Export all work
as a guest, with no sign-up or account call; Share asks for an account only when it's used. Then,
signed in, the guest's song is still there (it's on the device first). Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, new_song, sung_takes, t  # noqa: E402

# Every call that signs up, signs in or changes an account, recorded (reading the config and who's
# signed in is fine: every visitor does that).
SPY = """const { api } = await import('/js/api.js');
    window.__accountCalls = window.__accountCalls ?? [];
    const wrap = (group, name) => {
        const target = name.split('.').reduce((o, k) => o?.[k], api[group]);
        if (typeof target !== 'function' || target.__spied) return;
        const spied = (...args) => { window.__accountCalls.push(group + '.' + name); return target(...args); };
        spied.__spied = true;
        window.__spiedNames = [...(window.__spiedNames ?? []), group + '.' + name];
        const path = name.split('.'); const last = path.pop(); const holder = path.reduce((o, k) => o[k], api[group]);
        holder[last] = spied;
    };
    const names = (o, prefix = '') => Object.entries(o).flatMap(([k, v]) => typeof v === 'function' ? [prefix + k] : v && typeof v === 'object' ? names(v, prefix + k + '.') : []);
    for (const group of ['auth', 'account']) for (const name of names(api[group] ?? {})) if (!['config', 'show', 'me'].includes(name)) wrap(group, name);"""


def go(f, path, ready):
    """Within the app, as a guest moves around it (no reload, so the spy stays)."""
    f.run(f"(await import('/js/router.js')).navigate({json.dumps(path)})")
    f.wait(ready, what=path)


def journey(f):
    f.go("/invite/Pq4Rt7Vb2Nx9?api=mock&as=signedOut", "document.querySelector('h1')")
    f.run(SPY)
    spied = f.js("window.__spiedNames ?? []")
    assert any(n.startswith("auth.") for n in spied) and len(spied) >= 4, f"the spy watches the sign-up and account calls: {spied}"
    f.wait_text(t(f, "early.start"))
    assert not f.js("!!document.querySelector('#invite-email')"), "the account form waits for Make a free account"
    f.click_t("early.start")
    f.wait("location.pathname === '/' && document.querySelector('#lead-title')", what="the home, as a guest")
    f.shot("home")
    go(f, "/learn", "document.querySelectorAll('main ol button, ol button').length > 3")
    go(f, "/learn/1-comfortable", "document.querySelector('main h1')")
    f.shot("lesson")
    grace = new_song(f)
    go(f, f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
    f.js(f"{AUDIO}.unlock()")
    sung_takes(f, ["soprano"])
    go(f, f"/p/{grace}/sing", "document.querySelector('h1')")
    go(f, f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
    # Export works as a guest; Share asks for an account, there and then.
    export = json.dumps(t(f, "studio.export"))
    f.wait(f"[...document.querySelectorAll('#app header button')].some((b) => b.getClientRects().length && b.textContent.trim().endsWith({export}))", what="Export")
    f.run(f"[...document.querySelectorAll('#app header button')].find((b) => b.getClientRects().length && b.textContent.trim().endsWith({export})).click()")
    f.wait(f"document.querySelector('[role=dialog]')?.innerText.includes({json.dumps(t(f, 'export.exportAs', {'format': 'WAV'}))})", what="the Export sheet")
    f.shot("export")
    f.press("Escape")
    share = json.dumps(t(f, "share.title"))
    f.wait(f"[...document.querySelectorAll('#app header button')].some((b) => b.getClientRects().length && b.textContent.trim() === {share})", what="Share")
    f.run(f"[...document.querySelectorAll('#app header button')].find((b) => b.getClientRects().length && b.textContent.trim() === {share}).click()")
    f.wait_text(t(f, "share.signIn"))
    f.shot("share-asks")
    calls = f.js("window.__accountCalls")
    assert calls == [], f"a guest made account calls: {calls}"
    assert not f.js("(await import('/js/stores/account.js')).account.me"), "nobody is signed in"
    # Later, signed in: the guest's song is still here, on the device, to go to the account.
    f.go("/?app&api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.wait(f"(await import('/js/stores/projects.js')).loadProjects().then(() => true)", what="the songs")
    assert f.js(f"(await import('/js/stores/projects.js')).projects.all.some((p) => p.id === {json.dumps(grace)})"), "the guest's song is gone after signing in"


if __name__ == "__main__":
    run("guest", journey, sizes=("desktop",))
