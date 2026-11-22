"""Hillel's bug (2026-11-22): a song shared with one track, then shared again with every track using "Use that link", still
played one track at its link. The share sheet's earlier link is now Update that link: it sends the mix as it sounds now,
with `replaces` (that share's id), so the same link and page carry the new mix (the server swaps it and purges the CDN).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects, guides_ready, new_song, sung_takes, t  # noqa: E402


def open_share(f):
    if f.size == "phone":
        f.run("document.querySelector('header details').open = true")
    label = json.dumps(t(f, "share.title"))
    f.wait(f"[...document.querySelectorAll('#app header button')].some((b) => b.getClientRects().length && b.textContent.trim().endsWith({label}))", what="Share")
    f.run(f"[...document.querySelectorAll('#app header button')].find((b) => b.getClientRects().length && b.textContent.trim().endsWith({label})).click()")
    f.wait("document.querySelector('[role=dialog]')", what="the Share sheet")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    clear_projects(f)
    song = new_song(f, "amazing-grace")
    f.go(f"/p/{song}?api=mock&scenario=&as=member", "document.querySelectorAll('canvas').length > 3")
    sung_takes(f, ["soprano"])  # Share is offered once there's something sung to share
    f.go(f"/p/{song}?api=mock&scenario=&as=member", "document.querySelectorAll('canvas').length > 3")
    guides_ready(f)
    f.wait("(async () => !!(await import('/js/stores/account.js')).account.me)()", what="signed in (the sheet lists earlier links for an account)")
    open_share(f)
    f.wait("document.querySelector('[data-shared-before] [data-share-update]')", what="the earlier link, to update")
    assert t(f, "share.useThat") in f.js("document.querySelector('[data-shared-before]').textContent")
    earlier = f.js("(async () => (await (await import('/js/api.js')).api.harmonies.list({})).data.find((h) => h.visibility === 'unlisted' && h.status === 'ready').id)()")
    f.no_sideways("the Share sheet")
    f.shot("share-update")
    # The mock has no account to save to: the project as the account would have it (a version), so sharing goes on.
    f.run(f"const db = await import('/js/db.js'); const p = await db.get('projects', {json.dumps(song)}); await db.put('projects', {{ ...p, version: Math.max(1, p.version || 0), notSaved: false }});")
    f.run("document.querySelector('[data-share-update]').click()")
    f.wait("globalThis.mockShareBody", timeout=60, what="the mix sent")
    body = f.js("globalThis.mockShareBody")
    assert body.get("replaces") == earlier, f"not that link: {body.get('replaces')} vs {earlier}"
    assert body.get("tracks") is not None and body.get("sha256"), "no new mix sent"
    clear_projects(f)


run("share_update", journey)
