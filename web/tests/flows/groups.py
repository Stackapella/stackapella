"""Choir groups (M7), against the mock: the groups you direct or sing in; a group as its director
sees it (the link, the songs with who sings which part and how far they've got, the singers),
assigning a part; Open opening the song on their part; the group's link page; and a member's view
(their part first, Learn your part the one primary, their tile marked).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/groups?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    # An adult's account (the mock's has a birth date but says nothing of being 18): the start sheet's tick is theirs to give.
    f.run("sessionStorage.setItem('stackapella.mockAccount', JSON.stringify({ isAdult: true }))")
    f.go("/groups?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    f.wait_text("Thursday Singers")
    f.wait_text(t(f, "groups.youDirectCount", {"count": 2}))
    f.shot("groups")
    f.click("Thursday Singers", "a")
    f.wait("location.pathname === '/groups/Gp4rQs'", what="the group")
    f.wait_text("Sam Lee")
    f.wait_text(t(f, "groups.status.recorded"))
    f.wait_text(t(f, "groups.covered", {"count": 2, "total": 4}))
    # Invite singers shows the link card (the mock's link was made before: a new one to send).
    f.click_t("groups.invite")
    f.wait_text(t(f, "groups.linkHint"))
    f.shot("director")
    f.click_t("groups.assign")
    f.wait("document.querySelector('select[aria-label]')", what="the part pickers")
    f.run("const s = document.querySelector('select[aria-label]'); s.value = 'soprano'; s.dispatchEvent(new Event('change'));")
    f.wait(f"!document.body.innerText.includes({json.dumps(t(f, 'groups.refused.other'))})", what="assigned")
    f.click_t("groups.doneAssigning")
    # A song nobody has a part in yet: one tap gives each singer the part they have in the last song.
    f.wait("document.querySelector('[data-same-parts]')", what="Same parts as")
    f.wait_text(t(f, "groups.sameParts", {"title": "Amazing Grace"}))
    f.run("const { api } = await import('/js/api.js'); const real = api.groups.assignments.update; api.groups.assignments.update = (...args) => { window.sentParts = args; return real(...args); };")
    f.run("document.querySelector('[data-same-parts]').click()")
    f.wait("window.sentParts", what="the parts sent")
    sent = f.js("JSON.stringify(window.sentParts)")
    assert json.loads(sent)[2] == {"assignments": [{"memberId": "Ty6uIoPq", "partKey": "alto"}, {"memberId": "Bn5mKl8j", "partKey": "tenor"}]}, sent
    # Open: a project from the song, on to Sing.
    f.run(f"[...document.querySelectorAll('button')].find((b) => b.textContent.trim() === {json.dumps(t(f, 'groups.openSong'))}).click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing, on the song")
    # The group's link page.
    f.go("/choir/Hk3nB8vQ2pL7sD5wR9tY1cX4?api=mock&as=member", "document.querySelector('h1')")
    f.wait_text(t(f, "invite.from", {"name": "Ana Ruiz"}))
    f.shot("join")
    # Before anyone has a part, the preview lights none of them.
    f.wait("document.querySelectorAll('[data-gem-state=faint]').length === 0 && document.querySelectorAll('[data-gem]').length > 0", what="every part shown alike")
    f.click_t("groups.join")
    f.wait("location.pathname.startsWith('/groups/')", what="joined")
    # A member's view leads with their part: its state in words, Learn your part as the one primary, their tile marked.
    f.run("sessionStorage.setItem('stackapella.mockChoirMember', '1')")
    f.go("/groups/Gp4rQs?api=mock&as=member", "document.querySelector('h1')")
    f.wait("document.querySelector('[data-my-part]')", what="your part's line")
    f.wait_text(t(f, "groups.status.started"))
    f.wait("document.querySelector('[data-mine]')", what="your part's tile marked")
    f.wait(f"[...document.querySelectorAll('.btn-primary')].map((b) => b.textContent.trim()).join('|') === {json.dumps(t(f, 'groups.learnPart'))}", what="one primary: Learn your part")
    f.shot("member")
    # On a phone the song's buttons sit under its title, never squeezing it.
    f.wait("[...document.querySelectorAll('[data-group-song]')].every((row) => row.firstElementChild.offsetWidth > row.offsetWidth * 0.6)", what="the title takes the width")

    # Home: the parts to learn come first, for a member only; Learn opens the song on Sing, and a second
    # Learn goes back to the same copy instead of making another.
    f.go("/?api=mock&as=member", "document.querySelector('[data-choir-part]')")
    f.wait_text(t(f, "groups.learnTitle"))
    f.wait("!document.querySelector('section[aria-labelledby=band-groups]')", what="one choir band, not two")
    f.shot("home-member")
    f.run(f"[...document.querySelectorAll('[data-choir-part] button')][0].click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing, from Home")
    first = f.js("location.pathname")
    f.go("/groups/Gp4rQs?api=mock&as=member", "document.querySelector('[data-my-part]')")
    f.click_t("groups.learnPart")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing, again")
    assert f.js("location.pathname") == first, "Learn your part made a second copy of the song"
    assert f.js("(await import('/js/stores/projects.js')).projects.list.filter((p) => p.template?.id === 'amazing-grace').length") == 1, "one copy of the song"
    # Someone who directs (no part of their own) keeps Home as it was.
    f.run("sessionStorage.removeItem('stackapella.mockChoirMember')")
    f.go("/?api=mock&as=plus", "document.querySelector('section[aria-labelledby=band-groups]')")
    assert not f.js("!!document.querySelector('[data-choir-part]')"), "no parts to learn on a director's Home"

    # A choir's own song (choir learning tracks, stage 1): its link, opened signed out, asks to sign in and
    # comes back to it, never "isn't here"; signed in, a song that's really gone still says so.
    sid = "3f2a8c1e-5b7d-4e9a-a1c2-7d8e9f0a1b2c"
    f.go(f"/t/{sid}?api=mock&as=signedOut&scenario=templates.show:not_found", "document.querySelector('[data-template-sign-in]')")
    f.wait_text(t(f, "template.signInToOpen"))
    href = f.js("document.querySelector('[data-template-sign-in] a.btn-primary').getAttribute('href')")
    assert href.startswith("/signin?next=") and f"%2Ft%2F{sid}" in href, f"sign-in comes back to the song: {href}"
    f.shot("song-signed-out")
    f.go(f"/t/{sid}?api=mock&as=member&scenario=templates.show:not_found", "document.querySelector('main [role=alert]')")
    f.wait_text(t(f, "template.missing"))
    assert not f.js("!!document.querySelector('[data-template-sign-in]')"), "signed in, no sign-in"

    # Signed in, the director's choir song (given to them, so imported) leads with Share with the group: one
    # tap shares it there and opens it, the address left clean. (The mock never syncs your own songs, so it's
    # put on the device as a sync would.)
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('main')")
    f.js("""(async () => { const db = await import('/js/db.js'); const tpl = await (await fetch('/templates/amazing-grace.json')).json();
        await db.put('templates', { id: '%s', template: { ...tpl, id: '%s', title: 'Shenandoah (our arrangement)' }, visibility: 'private', libraryOptIn: false,
            imported: true, savedVersion: 1, state: 'saved', pending: false, deleted: false, createdAt: Date.now(), updatedAt: Date.now() }); return true; })()""" % (sid, sid))
    f.go(f"/t/{sid}?api=mock&as=member", "document.querySelector('[data-share-choir] a')")
    label = t(f, "template.choir.shareWith", {"name": "Thursday Singers"})
    assert f.js("document.querySelector('[data-share-choir] a').textContent.trim()") == label, "Share with the group you direct"
    assert f.js("document.querySelectorAll('main .btn-primary').length") == 1, "one primary: sharing, not learning"
    # It can't be an idea or have a link (imported_private), so neither is offered: one line says where it lives.
    f.wait_text(t(f, "template.choirPrivate"))
    assert not f.js("!!document.querySelector('main input[name=visibility]')"), "no Only me / Anyone with the link"
    assert t(f, "template.yoursImported") in f.js("document.querySelector('#yours-heading').textContent"), "Your song, not You made this"
    f.shot("song-share")
    f.run("window.mockBodies = [];")
    f.run("document.querySelector('[data-share-choir] a').click()")
    f.wait("location.pathname === '/groups/Gp4rQs' && !location.search", what="the group, the address clean")
    f.wait(f"window.mockBodies.some((b) => b.operation === 'groups.songs.create' && b.body?.templateId === '{sid}')", what="the song shared")
    # No group yet: Start a group opens at once, and the new group gets the song.
    f.go(f"/groups?api=mock&as=member&start=1&share={sid}", "document.querySelector('[role=dialog] input')")
    f.type("[role=dialog] input:not([type=checkbox])", "Riverside Chorus")
    f.run("document.querySelector('[role=dialog] input[type=checkbox]').click()")
    f.run("window.mockBodies = [];")
    f.click(t(f, "groups.make"), "[role=dialog] button")
    f.wait(f"window.mockBodies.some((b) => b.operation === 'groups.songs.create' && b.body?.templateId === '{sid}') && !location.search", what="shared into the new group")

    # A director from the directors' pages (/groups?start=1): signed out, the wall, which keeps the address through sign-up and
    # says a group needs an account; signed in, the start sheet at once and the address made plain.
    f.go("/groups?api=mock&as=signedOut&start=1", "document.querySelector('[data-account-wall]')")
    assert not f.js("!!document.querySelector('[role=dialog]')"), "no start sheet over the wall"
    assert f.js("document.querySelector('[data-account-wall]').dataset.reason") == "groupsStart", "said to a director starting one, not to a singer"
    f.wait_text(t(f, "wall.groupsStart"))
    sign_up = f.js("document.querySelector('[data-wall-sign-up]').getAttribute('href')")
    assert "next=%2Fgroups%3Fapi%3Dmock%26as%3DsignedOut%26start%3D1" in sign_up or "start%3D1" in sign_up, f"the wall keeps ?start=1: {sign_up}"
    f.run("document.querySelector('[data-wall-sign-up]').click()")
    f.wait_text(t(f, "auth.introGroups"))
    f.wait_text(t(f, "auth.reason.groupsStart"))
    f.go("/groups?api=mock&as=member&start=1", "document.querySelector('[role=dialog] input')")
    f.wait("location.search === ''", what="the address made plain")
    # A new group with no songs: its first step, Share a song, is the page's primary; the picker asks for a song.
    f.go("/groups?api=mock&as=member", "document.querySelector('h1')")
    f.run("""const { api } = await import('/js/api.js'); const show = api.groups.show;
        api.groups.show = async (...a) => { const r = await show(...a); return { ...r, data: { ...r.data, songs: [], members: r.data.members.filter((m) => m.role === 'director') } }; };
        (await import('/js/router.js')).navigate('/groups/Gp4rQs');""")
    f.wait("document.querySelector('[data-share-song]')", what="the new group")
    f.wait_text(t(f, "groups.noSongsDirector"))
    assert "btn-primary" in f.js("document.querySelector('[data-share-song]').className"), "Share a song leads"
    assert f.js("document.querySelectorAll('main .btn-primary').length") == 1, "one primary"
    f.shot("new-group")
    f.run("document.querySelector('[data-share-song]').click()")
    f.wait("document.querySelector('[role=dialog] select')", what="the picker")
    # Its first line asks for a song (your newest song not yet shared is picked when you have one, as here).
    assert f.js("document.querySelector('[role=dialog] select').options[0].textContent") == t(f, "groups.pickSong"), "Pick a song, not a blank"

    # A choir singer's first run. Back from the group's own link after signing up (?join=1), they join at once and hear so;
    # opened from anywhere else, the link waits for Join.
    link = "/choir/Hk3nB8vQ2pL7sD5wR9tY1cX4"
    f.go(f"{link}?api=mock&as=signedOut", "document.querySelector('#invite-email')")
    f.go(f"{link}?api=mock&as=member", "document.querySelector('h1')")
    f.wait_text(t(f, "groups.join"))
    assert f.js("location.pathname.startsWith('/choir/')"), "no join without the way back from the link"
    f.go(f"{link}?api=mock&as=member&join=1", "location.pathname.startsWith('/groups/')")
    f.wait_text(t(f, "groups.joinedToast", {"name": "Thursday Singers"}))
    # Under 18 by their own birth date: said kindly, with no "I'm 18 or over" to tick, here or on Start a group.
    f.run("sessionStorage.setItem('stackapella.mockAccount', JSON.stringify({ isAdult: false, birthGiven: true }))")
    f.go(f"{link}?api=mock&as=member&scenario=groups.join:adult_required", "document.querySelector('h1')")
    f.click_t("groups.join")
    f.wait("document.querySelector('[data-too-young]')", what="the kind word for under 18")
    f.wait_text(t(f, "groups.tooYoung"))
    assert not f.js("!!document.querySelector('input[type=checkbox]')"), "no tick to claim 18"
    f.shot("under-18")
    f.go("/groups?api=mock&scenario=&as=member&start=1", "document.querySelector('[role=dialog] [data-too-young]')")
    assert not f.js("!!document.querySelector('[role=dialog] input[type=checkbox]')"), "no tick on Start a group either"
    f.run("sessionStorage.setItem('stackapella.mockAccount', JSON.stringify({ isAdult: true }))")
    # A member with no part yet: told so on the group, and the choir sits above the library on Home.
    f.run("sessionStorage.setItem('stackapella.mockChoirMember', '1')")
    f.go("/groups?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.run("""const { api } = await import('/js/api.js'); const show = api.groups.show;
        api.groups.show = async (...a) => { const r = await show(...a); return { ...r, data: { ...r.data, members: r.data.members.map((m) => (m.role === 'director' ? m : { ...m, assignments: [] })) } }; };
        (await import('/js/router.js')).navigate('/groups/Gp4rQs');""")
    f.wait("document.querySelector('[data-no-part-yet]')", what="no part yet, said")
    f.shot("no-part-yet")
    f.run("(await import('/js/router.js')).navigate('/')")
    f.wait("document.querySelector('section[aria-labelledby=band-groups]')", what="the choir on Home")
    first = f.js("document.querySelector('main section[aria-labelledby^=band-]')?.getAttribute('aria-labelledby')")
    assert first == "band-groups", f"the choir before the library: {first}"
    f.run("sessionStorage.removeItem('stackapella.mockChoirMember'); sessionStorage.removeItem('stackapella.mockAccount')")

    # Send us your song (docs/choir-tracks-plan.md): one quiet line, the director's only. The form takes the piece, the
    # score's files (each sent as it's picked: scores.create, the PUT, complete) and one of the two rights sentences,
    # word for word the server's; Send waits for all three and says what's missing. Nothing but its fields is sent.
    ADD = """const input = document.querySelector('[data-score-input]'); const dt = new DataTransfer();
        dt.items.add(new File(['%%PDF-1.4 a score'], %s, { type: %s })); input.files = dt.files; input.dispatchEvent(new Event('change', { bubbles: true }));"""
    f.go("/groups/Gp4rQs?api=mock&scenario=&as=plus", "document.querySelector('[data-ask-tracks]')")
    # The songs sent before, and where each has got to: a delivered one is ready, with Open.
    f.wait("document.querySelector('[data-tracks-requests] [data-tracks-request=delivered] [data-tracks-open]')", what="the requests, one ready to open")
    f.wait_text(t(f, "groups.tracks.status.received"))
    f.click_t("groups.tracks.askLink")
    f.wait("document.querySelector('[data-tracks-form]')", what="the tracks form")
    f.wait_text(t(f, "groups.tracks.publicDomainSentence"))
    f.shot("tracks-form")
    send = "document.querySelector('[data-tracks-form] button[type=submit]')"
    needs = "document.querySelector('[data-tracks-needs]')?.textContent.trim()"
    f.type("[data-tracks-form] input[maxlength='200']", "God So Loved the World")
    assert f.js(f"{send}.disabled") and f.js(needs) == t(f, "groups.tracks.needsScore"), f.js(needs)
    # A file that isn't a score never goes.
    f.run("window.mockBodies = [];")
    f.run(ADD % ('"notes.txt"', '"text/plain"'))
    f.wait_text(t(f, "groups.tracks.scoreWrongKind", {"name": "notes.txt"}))
    assert not f.js("window.mockBodies.some((b) => b.operation === 'groups.tracks.scores.create')"), "a text file was sent"
    f.run("document.querySelector('[data-score=failed] button').click()")
    # A PDF goes, and is ready; its type and size are what the server is told.
    f.run(ADD % ('"God So Loved the World.pdf"', '""'))
    f.wait("document.querySelector('[data-score=ready]')", what="the file sent and checked")
    sent = f.js("window.mockBodies.find((b) => b.operation === 'groups.tracks.scores.create')?.body")
    assert sent["contentType"] == "application/pdf" and sent["bytes"] == 16 and len(sent["sha256"]) == 64, sent
    assert f.js(f"{send}.disabled") and f.js(needs) == t(f, "groups.tracks.needsRights"), f.js(needs)
    f.run("document.querySelector('[data-tracks-rights] input[value=public-domain]').click()")
    f.wait(f"!{send}.disabled", what="Send open")
    f.shot("tracks-ready")
    f.run(f"{send}.click()")
    f.wait("document.querySelector('[data-tracks-sent]')", what="sent")
    body = f.js("window.mockBodies.find((b) => b.operation === 'groups.tracks.create')?.body")
    assert body == {"piece": "God So Loved the World", "rights": "public-domain", "rightsConfirmed": True, "scoreIds": ["Sc0rE1"]}, body
    f.shot("tracks-sent")
    # A file that isn't what it says: the server's check, said plainly.
    f.go("/groups/Gp4rQs?api=mock&scenario=groups.tracks.scores.complete:score_type&as=plus", "document.querySelector('[data-ask-tracks]')")
    f.click_t("groups.tracks.askLink")
    f.run(ADD % ('"Shenandoah.pdf"', '"application/pdf"'))
    f.wait_text(t(f, "groups.tracks.scoreType"))
    # Five an hour: said plainly.
    f.go("/groups/Gp4rQs?api=mock&scenario=groups.tracks.create:rate_limited&as=plus", "document.querySelector('[data-ask-tracks]')")
    f.click_t("groups.tracks.askLink")
    f.type("[data-tracks-form] input[maxlength='200']", "Shenandoah")
    f.run(ADD % ('"Shenandoah.musicxml"', '""'))
    f.wait("document.querySelector('[data-score=ready]')", what="the MusicXML sent")
    f.run("document.querySelector('[data-tracks-rights] input[value=own-arrangement]').click()")
    f.run(f"{send}.click()")
    f.wait_text(t(f, "groups.tracks.tooMany"))
    # A member never sees it.
    f.run("sessionStorage.setItem('stackapella.mockChoirMember', '1')")
    f.go("/groups/Gp4rQs?api=mock&scenario=&as=member", "document.querySelector('[data-my-part]')")
    assert not f.js("!!document.querySelector('[data-ask-tracks]')"), "a member never sees the tracks request"
    f.run("sessionStorage.removeItem('stackapella.mockChoirMember')")

    # The next rehearsal (js/rehearsal.js), the director's to set, tucked into the Group section; shown to everyone once set,
    # and nothing at all without one. Dates are set here from today, so the checks never depend on the day they run.
    ISO = "(n) => { const d = new Date(); d.setDate(d.getDate() + n); const p = (x) => String(x).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`; }"
    def with_rehearsal(days, weekly=False, since=None):
        """groups.show answers with a rehearsal `days` from today (None: no rehearsal), and the songs' since counts."""
        rehearsal = "null" if days is None else f"{{ date: iso({days}), weekly: {json.dumps(weekly)}, next: iso({days}), previous: {f'iso({days - 7})' if weekly else 'null'} }}"
        f.run(f"""const iso = {ISO}; const {{ api }} = await import('/js/api.js'); const show = api.groups.show;
            api.groups.show = async (...a) => {{ const r = await show(...a); return {{ ...r, data: {{ ...r.data, rehearsal: {rehearsal},
                songs: r.data.songs.map((x) => ({{ ...x, sinceRehearsal: {json.dumps(since)} }})) }} }}; }};""")
    f.go("/groups?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    with_rehearsal(None)
    f.run("(await import('/js/router.js')).navigate('/groups/Gp4rQs')")
    f.wait("document.querySelector('[data-rehearsal-row]')", what="the Next rehearsal row")
    assert not f.js("!!document.querySelector('[data-rehearsal]')"), "no rehearsal line without a date"
    assert t(f, "groups.rehearsal.notSet") in f.js("document.querySelector('[data-rehearsal-row]').textContent"), "Not set"
    f.run("document.querySelector('[data-rehearsal-row]').click()")
    f.wait("document.querySelector('[data-rehearsal-form] input[type=date]')", what="the date sheet")
    f.wait_text(t(f, "groups.rehearsal.hint"))
    f.run(f"const iso = {ISO}; const i = document.querySelector('[data-rehearsal-form] input[type=date]'); i.value = iso(9); i.dispatchEvent(new Event('input'));")
    f.run("window.mockBodies = [];")
    f.run("document.querySelector('[data-rehearsal-form]').requestSubmit()")
    f.wait("!document.querySelector('[data-rehearsal-form]')", what="saved")
    body = f.js("window.mockBodies.find((b) => b.operation === 'groups.update')?.body")
    assert body and body["rehearsal"]["weekly"] is True and body["rehearsal"]["date"] == f.js(f"({ISO})(9)"), f"the date and weekly sent: {body}"
    f.shot("rehearsal-sheet-saved")
    # Set: the line under the name, and the director's since-count on each song with singers.
    f.go("/groups?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    with_rehearsal(2, weekly=True, since={"started": 1, "recorded": 1})
    f.run("(await import('/js/router.js')).navigate('/groups/Gp4rQs')")
    f.wait("document.querySelector('[data-rehearsal]')", what="the rehearsal line")
    assert f.js("document.querySelector('[data-rehearsal]').textContent").startswith(t(f, "groups.rehearsal.next", {"date": ""}).strip()), "Next rehearsal: ..."
    since = t(f, "groups.rehearsal.since", {"started": 1, "count": 2, "date": ""}).strip()
    assert since in f.js("document.querySelector('main').innerText"), f"the director's since-count: {since}"
    f.shot("rehearsal-director")
    # The row keeps to one line on a phone: the short date there, the long one in the line under the name.
    row = f.js("(() => { const r = document.querySelector('[data-rehearsal-row]'); return { text: r.textContent, h: r.getBoundingClientRect().height }; })()")
    assert f.js(f"new Intl.DateTimeFormat(document.documentElement.lang || 'en', {{ weekday: 'short', day: 'numeric', month: 'short' }}).format(new Date(Date.now() + 2 * 864e5))") in row["text"], f"the short date in the row: {row}"
    assert row["h"] < 64, f"the Next rehearsal row on one line: {row}"
    # A member: two days before it, an unrecorded part says so, plainly; and Home's row too.
    f.run("sessionStorage.setItem('stackapella.mockChoirMember', '1')")
    f.go("/groups?api=mock&scenario=&as=member", "document.querySelector('h1')")
    with_rehearsal(2, weekly=True)
    f.run("(await import('/js/router.js')).navigate('/groups/Gp4rQs')")
    f.wait("document.querySelector('[data-my-part] [data-rehearsal-soon]')", what="days to rehearsal on the member's part")
    assert t(f, "groups.rehearsal.inDays", {"days": 2}) in f.js("document.querySelector('[data-my-part]').textContent")
    assert "text-bad" not in f.js("document.querySelector('[data-my-part] [data-rehearsal-soon]').className") and "text-warn" not in f.js("document.querySelector('[data-my-part] [data-rehearsal-soon]').className"), "information, not alarm"
    assert not f.js("!!document.querySelector('[data-rehearsal-row]')"), "only the director sets it"
    f.shot("rehearsal-member")
    f.run("(await import('/js/router.js')).navigate('/')")
    f.wait("document.querySelector('[data-choir-part] [data-rehearsal-soon]')", what="Home's row says it")
    f.shot("rehearsal-home")
    f.run("sessionStorage.removeItem('stackapella.mockChoirMember')")


if __name__ == "__main__":
    run("groups", journey)
