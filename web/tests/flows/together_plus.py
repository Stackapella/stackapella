"""Teaming up is Plus (docs/pricing-plan.md, top; Hillel, 2026-11-12): frontend2's moments, against fixtures
(tests/fixtures/together/) until backend's server side is on main.
- Start a group without Plus: the Plus moment with the trial, before the form; Plus's page says why and, once Plus is
  on, leads back to the start sheet. A refusal (plus_required) brings the same moment, never an error.
- A paused choir (its leader's Plus ended): the leader's note with the way back to Plus, Invite and Share a song gone;
  a member's note with nothing to buy; its join page takes nobody new and offers singing alone; a join refused as
  shared_paused says the same. Paused is tagged in the list and in Admin > Choir groups, with a count.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

FIX = "const get = async (name) => (await fetch('/tests/fixtures/together/' + name + '.json')).json(); const { api, ApiError } = await import('/js/api.js');"
NAV = "(await import('/js/router.js')).navigate({path})"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def go(f, path):
    f.run(NAV.format(path=json.dumps(path)))


TEAMS_ON = "await (await import('/js/stores/account.js')).loadAuthConfig(); (await import('/js/teams.js')).teams.on = true"


def journey(f):
    f.go("/groups?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    # With the plus_teams switch off (the mock's auth.config until it's on): starting a group is free, as before.
    f.wait("document.querySelector('#app main')", what="Choir groups")
    f.click(f.js("(await import('/js/skin.js')).sk('groups.start', 'game.groups.start')"))
    f.wait("document.querySelector('[role=dialog]')", what="the start sheet")
    assert not f.js("!!document.querySelector('[data-plus-moment]')"), "no Plus sheet while the switch is off"
    f.press("Escape")
    # Switched on (auth.config plusTeams), from here on.
    f.run(TEAMS_ON)
    f.run("sessionStorage.removeItem('stackapella.plus.next')")
    # The list: a paused group is tagged.
    f.run(FIX + "const list = await get('groups.list'); api.groups.list = async () => structuredClone(list);")
    go(f, "/")
    go(f, "/groups")
    f.wait("document.querySelector('[data-group-paused]')", what="the paused tag on the list")

    # Start a group, free: the Plus moment first, with the trial; no form behind it.
    # A free account with the trial still to take (the mock's billing example is a Plus account's).
    f.run("const { api } = await import('/js/api.js'); api.billing.show = async () => ({ data: { plan: 'free', trial: { days: 7, intervals: ['year'] } } }); const { plan } = await import('/js/stores/plan.js'); plan.billing = null;")
    f.click(f.js("(await import('/js/skin.js')).sk('groups.start', 'game.groups.start')"))
    f.wait("document.querySelector('[data-plus-moment][data-moment=group_start]')", what="the Plus moment")
    assert not f.js("!!document.querySelector('[role=dialog] form')"), "no form before Plus"
    assert f.js("document.querySelector('[data-plus-moment]').textContent").find(t(f, "groups.plusText")) >= 0
    f.wait("document.querySelector('[data-plus-trial]')", what="the trial offered")
    f.shot("start-moment")
    f.run("document.querySelector('[data-plus-go]').click()")
    f.wait("location.pathname === '/plus' && location.search.includes('from=group_start')", what="the Plus page from the moment")
    f.wait(f"document.body.innerText.includes({json.dumps(t(f, 'plus.moment.groupStart'))})", what="why it's here")
    assert json.loads(f.js("sessionStorage.getItem('stackapella.plus.next')"))["path"] == "/groups?start=1"
    # Plus on (as Checkout would leave it): the way back, once, opens the start sheet.
    f.run("const { account } = await import('/js/stores/account.js'); Object.assign(account.me, { plan: 'plus', birthGiven: true, isAdult: true });")
    f.wait("document.querySelector('[data-plus-next]')", what="Start your choir group")
    f.shot("plus-next")
    f.run("document.querySelector('[data-plus-next]').click()")
    f.wait("location.pathname === '/groups' && document.querySelector('[role=dialog] form')", what="the start sheet, with Plus")
    assert f.js("sessionStorage.getItem('stackapella.plus.next')") is None, "the way back is used once"
    # A refusal all the same (the plan changed elsewhere): the moment, not an error under the name.
    f.run(FIX + "const no = await get('groups.create.plus_required'); api.groups.create = async () => { throw new ApiError({ status: 403, code: no.code, operation: 'groups.create' }); };")
    f.run("const n = document.querySelector('[role=dialog] input.input'); n.value = 'Thursday Singers'; n.dispatchEvent(new Event('input')); const a = document.querySelector('[role=dialog] input[type=checkbox]'); if (!a.checked) a.click();")
    f.run("document.querySelector('[role=dialog] form button[type=submit]').click()")
    f.wait("document.querySelector('[data-plus-moment]')", what="the moment after plus_required")
    # Made (docs/improvement-log.md, row 2): the group opens on its link, ready to send, Copy the one primary.
    f.run(FIX + "const show = await get('groups.show'); delete show.data.readOnly; show.data.link = null; show.data.songs = []; api.groups.create = async () => ({ data: { id: 'Gp4rQs' } }); api.groups.show = async () => structuredClone(show); api.groups.link.create = async () => ({ data: { url: 'https://stackapella.test/choir/tok123' } });")
    f.run("document.querySelector('[data-plus-moment] button.btn-secondary').click()")
    go(f, "/")
    go(f, "/groups")
    f.click(f.js("(await import('/js/skin.js')).sk('groups.start', 'game.groups.start')"))
    f.wait("document.querySelector('[role=dialog] form')", what="the start sheet again")
    f.run("const n = document.querySelector('[role=dialog] input.input'); n.value = 'Thursday Singers'; n.dispatchEvent(new Event('input')); const a = document.querySelector('[role=dialog] input[type=checkbox]'); if (!a.checked) a.click();")
    f.run("document.querySelector('[role=dialog] form button[type=submit]').click()")
    f.wait("document.querySelector('[data-group-made] input')?.value.includes('/choir/tok123')", what="the new group's link, in front")
    assert f.js("location.search") == "", "the address made plain"
    assert f.js("document.querySelectorAll('main .btn-primary').length") == 1, "one primary: Copy"
    assert not f.js("!!document.querySelector('[data-invite]')"), "Invite singers is already open"
    f.no_sideways("the new group")
    f.shot("made-link")
    f.run("const { account } = await import('/js/stores/account.js'); account.me.plan = 'free';")

    # The leader's paused choir: the note with the way back to Plus; nothing to invite or share.
    f.run(FIX + "const show = await get('groups.show'); api.groups.show = async () => structuredClone(show);")
    go(f, "/groups")
    go(f, "/groups/Gp4rQs")
    f.wait("document.querySelector('[data-paused-note]')", what="the leader's paused note")
    assert f.js("document.querySelector('[data-paused-note]').textContent").find(t(f, "groups.paused.leader")) >= 0
    assert f.js("!!document.querySelector('[data-paused-plus]')"), "the leader's way back to Plus"
    assert not f.js("!!document.querySelector('[data-invite], [data-share-song]')"), "no invite or new song while paused"
    f.no_sideways("the paused group")
    f.shot("leader-paused")
    # A member's: the leader named, nothing to buy.
    f.run(FIX + "const show = await get('groups.show'); show.data.role = 'member'; api.groups.show = async () => structuredClone(show);")
    go(f, "/groups")
    go(f, "/groups/Gp4rQs")
    f.wait(f"document.querySelector('[data-paused-note]')?.textContent.includes({json.dumps(t(f, 'groups.paused.member', {'name': 'Ana Ruiz'}))})", what="the member's paused note")
    assert not f.js("!!document.querySelector('[data-paused-plus]')"), "nothing to buy for a member"
    f.shot("member-paused")

    # The join page of a paused choir: nobody new, and singing alone offered instead.
    f.run(FIX + "const js = await get('groups.join.show'); api.groups.join.show = async () => structuredClone(js);")
    go(f, "/choir/tok123")
    f.wait("document.querySelector('[data-paused-note]') && document.querySelector('[data-paused-sing]')", what="the paused join page")
    assert f.js("document.querySelector('[data-paused-note]').textContent").find(t(f, "groups.paused.join", {"name": "Ana Ruiz"})) >= 0
    assert not f.js(f"[...document.querySelectorAll('button')].some((b) => b.textContent.trim() === {json.dumps(t(f, 'groups.join'))})"), "no Join"
    f.no_sideways("the paused join page")
    f.shot("join-paused")
    # Paused after the page opened: the join refused (shared_paused) says the same.
    f.run(FIX + "const js = await get('groups.join.show'); delete js.data.readOnly; api.groups.join.show = async () => structuredClone(js); api.groups.join = Object.assign(async () => { throw new ApiError({ status: 403, code: 'shared_paused', operation: 'groups.join' }); }, { show: api.groups.join.show });")
    go(f, "/")
    go(f, "/choir/tok124")
    f.click(t(f, "groups.join"))
    f.wait("document.querySelector('[data-paused-note]')", what="paused after a refused join")

    # Admin > Choir groups: the paused one tagged, and counted.
    f.go("/admin/sources?api=mock&as=admin", "document.querySelector('h1')")
    f.run(FIX + "const ag = await get('admin.groups.list'); api.admin.groups.list = async () => structuredClone(ag);")
    go(f, "/admin/groups")
    f.wait("document.querySelector('[data-choir-groups] [data-paused]')", what="the paused tag in admin")
    assert t(f, "admin.choirGroups.pausedCount", {"count": 1}) in f.js("document.querySelector('[data-choir-groups]').textContent")
    f.shot("admin-paused")


run("together_plus", journey)
