"""Shared private arrangements need everyone's own Plus (Hillel, 2026-11-17: "make sure to access private arrangments all of
the users (creator and otehrs) have plus accounts"; docs/pricing-plan.md, top; behind plus_private, js/teams.js's
teams.private). Without Plus a shared private song stays listed with every take kept, and opening it shows the Plus sheet
(private_shared), never an error: a friend's song (readOnly 'plus_needed'), your own once shared, someone's song by its
link (templates.show's plus_required, reason private_shared), turning your song's link on, and joining by an invite. With
the switch off nothing changes. Once in Spanish too. Against the mock; the switch is turned on in the page after
auth.config has loaded. FLOW_SKINS=game,serious runs it in both skins.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
PRIVATE_ON = "await (await import('/js/stores/account.js')).loadAuthConfig(); (await import('/js/teams.js')).teams.private = true"
SHEET = "document.querySelector('[data-plus-moment][data-moment=private_shared]')"
NOTE = "document.querySelector('[data-private-note]')"
FRIEND = {"role": "singer", "owner": {"id": None, "name": "Ana Ruiz"}}


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def make_project(f, shared=None, read_only=None):
    """A song with one take on this device, on the account (version 1), optionally a friend's."""
    return f.js(f"""(async () => {{
        const store = await import('/js/stores/projects.js');
        const db = await import('/js/db.js');
        const {{ peaks }} = await import('/js/dsp.js');
        const project = await store.createProject({{ title: 'Our Song for Gran', bpm: 120 }});
        const samples = new Float32Array(48000).map((_, i) => 0.3 * Math.sin((2 * Math.PI * 220 * i) / 48000));
        await store.savePart({{ id: crypto.randomUUID(), projectId: project.id, name: 'Lead', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
            offsetSeconds: 0, nudgeSeconds: 0, sampleRate: 48000, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }}, samples);
        const p = await db.get('projects', project.id);
        await db.put('projects', {{ ...p, version: 1, shared: {json.dumps(shared)}, readOnly: {json.dumps(read_only)} }});
        return project.id;
    }})()""")


def make_template(f):
    """A private arrangement of your own on this device (Amazing Grace's notes under a new id)."""
    return f.js("""(async () => {
        const db = await import('/js/db.js');
        const template = await fetch('/templates/amazing-grace.json').then((r) => r.json());
        const id = crypto.randomUUID();
        await db.put('templates', { id, template: { ...template, id, title: 'Our Song for Gran', version: 1 }, visibility: 'private', libraryOptIn: false, savedVersion: 0, state: 'device', pending: false, deleted: false, createdAt: Date.now(), updatedAt: Date.now() });
        return id;
    })()""")


def open_studio(f, pid, lang="", on=True):
    f.go(f"/p/{pid}?api=mock&as=member&scenario={lang}", "document.querySelector('#app')")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 1 && document.querySelector('header')", what="the studio")
    if on:
        f.run(PRIVATE_ON)


def sheet_says(f, title, text):
    f.wait(SHEET, what="the Plus sheet (private_shared)")
    words = f.js(f"{SHEET}.closest('[role=dialog]').innerText")
    assert title in words and text in words, f"not the sheet's words: {words}"
    assert f.js(f"{SHEET}.querySelectorAll('.btn-primary').length") == 1, "one primary"
    assert not f.js("!!document.querySelector('[role=dialog] [role=alert]')"), "said as an error"
    f.no_sideways("the Plus sheet")


def not_now(f):
    label = json.dumps(t(f, "common.notNow"))
    f.run(f"[...{SHEET}.querySelectorAll('button')].find((b) => b.textContent.includes({label})).click()")
    f.wait(f"!{SHEET}", what="Not now closes the sheet")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.clear(); sessionStorage.clear()")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")

    # Switch off: a friend's song read plus_needed by an account that knows nothing of it opens as before.
    friends = make_project(f, shared=FRIEND, read_only="plus_needed")
    open_studio(f, friends, on=False)
    assert not f.js(f"!!{SHEET}") and not f.js(f"!!{NOTE}"), "the switch is off: no sheet and no note"

    # A friend's shared private arrangement, no Plus of their own: the sheet as it opens; closed, the calm note stays,
    # with the way to Plus (Hillel: "Read-only until Plus").
    open_studio(f, friends)
    sheet_says(f, t(f, "together.private.otherTitle", {"name": "Ana Ruiz"}), t(f, "together.private.otherText"))
    f.shot("friend-sheet")
    not_now(f)
    f.wait(NOTE, what="the calm note")
    assert "Ana Ruiz" in f.js(f"{NOTE}.innerText"), "the note doesn't name whose song it is"
    assert "from=private_shared" in f.js(f"{NOTE}.querySelector('[data-paused-plus]').getAttribute('href')"), "the note's way to Plus"
    f.shot("friend-note")

    # Your own song, shared while you have no Plus: its own words.
    own = make_project(f, read_only="plus_needed")
    open_studio(f, own)
    sheet_says(f, t(f, "together.private.ownTitle"), t(f, "together.private.ownText"))
    f.shot("own-sheet")
    f.run(f"{SHEET}.querySelector('[data-plus-go]').click()")
    f.wait("location.pathname === '/plus' && location.search.includes('from=private_shared')", what="the Plus page from the sheet")
    f.wait_text(t(f, "plus.moment.privateShared"))
    assert json.loads(f.js("sessionStorage.getItem('stackapella.plus.next')"))["path"] == f"/p/{own}", "the way back"

    # Someone's song by its link, refused (plus_required, reason private_shared): the sheet and the note, never an error.
    f.go("/t/0b5e8c1e-7f1a-4c2e-9d3b-2a6f5e4d3c21?api=mock&as=member&scenario=templates.show:plus_required", "document.querySelector('#app')")
    sheet_says(f, t(f, "together.private.songTitle"), t(f, "together.private.otherText"))
    f.shot("link-sheet")
    not_now(f)
    f.wait(NOTE, what="the calm note in the song's place")

    # Turning your own song's link on without Plus: the sheet, and the choice stays on Only me.
    mine = make_template(f)
    f.go(f"/t/{mine}?api=mock&as=member&scenario=", "document.querySelector('#app input[name=visibility]')")
    f.run(PRIVATE_ON)
    f.run("document.querySelectorAll('#app input[name=visibility]')[1].click()")
    sheet_says(f, t(f, "together.private.linkTitle"), t(f, "together.private.linkText"))
    assert f.js("document.querySelectorAll('#app input[name=visibility]')[0].checked"), "the link turned on without Plus"
    f.shot("turn-link-on")
    not_now(f)

    # Joining by an invite without Plus (invites.accept's plus_required): the sheet, its way back the invite.
    f.go("/join/tok?api=mock&as=member&scenario=invites.accept:plus_required", "document.querySelector('#app')")
    f.run(PRIVATE_ON)
    f.click_t("invite.accept")
    sheet_says(f, t(f, "together.private.otherTitle", {"name": f.js("(await (await import('/js/api.js')).api.invites.show('tok')).data.owner.name")}), t(f, "together.private.joinText"))
    f.shot("join-sheet")

    # In Spanish: a friend's song.
    open_studio(f, friends, lang="&lang=es")
    assert f.js("document.documentElement.lang") == "es", "in Spanish"
    sheet_says(f, t(f, "together.private.otherTitle", {"name": "Ana Ruiz"}), t(f, "together.private.otherText"))
    assert "arreglos privados" in f.js(f"{SHEET}.innerText"), "not Spanish words"
    f.shot("es-friend-sheet")
    not_now(f)
    f.wait(NOTE, what="the calm note, in Spanish")
    f.shot("es-friend-note")


if __name__ == "__main__":
    run("private_plus", journey)
