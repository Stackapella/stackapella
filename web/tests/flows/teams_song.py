"""Teaming up is Plus, the song side (docs/pricing-plan.md, top; behind plus_teams, js/teams.js; Hillel, 2026-11-12: "you
can learn alone for free but to team up becomes more serious"): with the switch off, Invite singers makes a link as
before and nothing is paused; with it on, a free account's Make a link opens the Plus moment (invite_friend), a refusal
from the server (plus_required) opens it too, the starter whose Plus ended sees their friends' song paused, a friend's
paused song (readOnly 'plus_ended') says so calmly with no Plus ask, and an invite to a paused song can't be accepted
and says why. Against the mock; the switch is turned on in the page after auth.config has loaded.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
TEAMS_ON = "await (await import('/js/stores/account.js')).loadAuthConfig(); (await import('/js/teams.js')).teams.on = true"
PAUSED = "document.querySelector('[data-paused-note]')"
MOMENT = "document.querySelector('[data-plus-moment][data-moment=invite_friend]')"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def make_project(f, shared=None, read_only=None):
    """A song with one take on this device, on the account (version 1), optionally a friend's."""
    return f.js(f"""(async () => {{
        const store = await import('/js/stores/projects.js');
        const db = await import('/js/db.js');
        const {{ peaks }} = await import('/js/dsp.js');
        const project = await store.createProject({{ title: 'Teams test', bpm: 120 }});
        const samples = new Float32Array(48000).map((_, i) => 0.3 * Math.sin((2 * Math.PI * 220 * i) / 48000));
        await store.savePart({{ id: crypto.randomUUID(), projectId: project.id, name: 'Lead', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
            offsetSeconds: 0, nudgeSeconds: 0, sampleRate: 48000, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }}, samples);
        const p = await db.get('projects', project.id);
        await db.put('projects', {{ ...p, version: 1, shared: {json.dumps(shared)}, readOnly: {json.dumps(read_only)} }});
        return project.id;
    }})()""")


def open_studio(f, pid, who="member", scenario="", teams=False):
    f.go(f"/p/{pid}?api=mock&as={who}&scenario={scenario}", "document.querySelector('#app')")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 1 && document.querySelector('header')", what="the studio")
    if teams:
        f.run(TEAMS_ON)


def open_together(f):
    if f.size == "phone":
        f.run("document.querySelector('header details') && (document.querySelector('header details').open = true)")
    f.click_t("studio.inviteSingers")
    f.wait_text(t(f, "together.makeLink"))


def make_link(f):
    f.click_t("together.makeLink")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.clear(); sessionStorage.clear()")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")

    # Switch off: a free account makes a link as before; nothing is paused.
    own = make_project(f)
    open_studio(f, own)
    open_together(f)
    make_link(f)
    f.wait(f"[...document.querySelectorAll('[role=dialog] code')].some((c) => c.textContent.includes('/join/'))", what="the link, made")
    assert not f.js(f"!!{MOMENT}") and not f.js(f"!!{PAUSED}"), "the switch is off: no Plus moment and nothing paused"
    f.shot("off-link")

    # Switch on, a free account: Make a link is the Plus moment, its way back this song; the friends' song is paused.
    open_studio(f, own, teams=True)
    open_together(f)
    f.wait(PAUSED, what="the starter's paused note (Sam Lee sings in it)")
    assert t(f, "together.paused.songOwner") in f.js(f"{PAUSED}.innerText"), "not the starter's words"
    make_link(f)
    f.wait(MOMENT, what="the Plus moment for inviting")
    assert t(f, "plus.moment.inviteFriend") in f.js(f"{MOMENT}.innerText"), "not the invite moment's words"
    assert not f.js("[...document.querySelectorAll('[role=dialog] code')].some((c) => c.textContent.includes('/join/'))"), "a link was made without Plus"
    f.shot("on-moment")
    not_now = json.dumps(t(f, "common.notNow"))
    f.run(f"[...{MOMENT}.querySelectorAll('button')].find((b) => b.textContent.includes({not_now})).click()")
    f.wait(f"!{MOMENT}", what="Not now closes the moment")

    # Switch on, Plus, but the server says the plan changed (plus_required): the moment, never an error.
    open_studio(f, own, who="plus", scenario="invites.create:plus_required", teams=True)
    open_together(f)
    make_link(f)
    f.wait(MOMENT, what="the Plus moment after plus_required")
    assert not f.js("!!document.querySelector('[role=dialog] [role=alert]')"), "plus_required shown as an error"

    # A friend's paused song: the calm note naming its starter, no Plus ask; with the switch off, as before.
    friend = make_project(f, shared={"role": "singer", "owner": {"id": "u-ana", "name": "Ana Ruiz"}}, read_only="plus_ended")
    open_studio(f, friend, teams=True)
    f.wait(PAUSED, what="the friend's paused note")
    words = f.js(f"{PAUSED}.innerText")
    assert t(f, "together.paused.songMember", {"name": "Ana Ruiz"}) in words, f"not the friend's words: {words}"
    assert not f.js(f"!!{PAUSED}.querySelector('[data-paused-plus]')"), "a member is asked to buy Plus"
    f.shot("friend-paused")
    open_studio(f, friend)
    f.wait("document.querySelector('header')", what="the studio, switch off")
    assert not f.js(f"!!{PAUSED}"), "paused with the switch off"

    # An invite to a paused song: Accept says it's paused, and why.
    f.go("/join/Tk7Qp2Lm9Vx4?api=mock&as=member&scenario=invites.accept:shared_paused", "document.querySelector('#app')")
    f.run(TEAMS_ON)
    f.wait_text(t(f, "invite.accept"))
    f.click_t("invite.accept")
    f.wait(PAUSED, what="the join page's paused note")
    assert t(f, "together.accept.paused", {"name": "Ana Ruiz"}) in f.js(f"{PAUSED}.innerText"), "not the join page's words"
    f.shot("join-paused")


if __name__ == "__main__":
    run("teams_song", journey)
