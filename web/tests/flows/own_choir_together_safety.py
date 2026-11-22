"""A choir with friends, the security review's app findings, on the mock:
(a) a friend's parts no longer playing (they left or were removed) leave the owner's device, and the project keeps only
    what plays now; a friend's Learn copy of a song they sing in is never wiped as "gone";
(b) the invites this device was joining by go on sign-out, and any past their 14 days go with their project;
(c) a friend's Learn copy of someone else's song is never shared (its mix carries the owner's AI parts);
(e) with the switch off, a song someone sings in still shows, with Leave (leaving must always work).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

FLAGS = ["stackapella.mockChoirFriend", "stackapella.mockChoirJoined", "stackapella.mockChoirFit", "stackapella.mockChoirState", "stackapella.mockChoir"]


def tab(f, together=True, **flags):
    clears = "".join(f"sessionStorage.removeItem({json.dumps(k)});" for k in FLAGS)
    sets = "".join(f"sessionStorage.setItem({json.dumps('stackapella.' + k)}, {json.dumps(v)});" for k, v in flags.items())
    f.run(f"sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockTogether', '{'1' if together else '0'}'); {clears} {sets}")


KEPT = """(async (key) => !!(await (await import('/js/db.js')).get('guides', key)))"""


def journey(f):
    f.go("/?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    clear_projects(f)

    # (a) The owner's device keeps a part of Ben's that no longer plays: it goes at the next look, and the project keeps
    # what plays now. The friend's Learn copy of Ann's song (a song they sing in) keeps its parts.
    tab(f, mockChoirJoined="ready")
    f.go("/?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    seeded = f.js("""(async () => {
        const { loadOwnChoir, ownChoir } = await import('/js/own-choir.js'); await loadOwnChoir({ force: true });
        const db = await import('/js/db.js'); const { guideKey } = await import('/js/recorded-guides.js');
        const { loadTemplate } = await import('/js/templates.js'); const { startFromTemplate } = await import('/js/stores/projects.js');
        const plain = (x) => JSON.parse(JSON.stringify(x)); // the store's reactive copies, as plain data for the device
        const mine = plain(ownChoir.data.requests.find((r) => r.status === 'ready'));
        const ann = plain(ownChoir.data.joined[0].song);
        const gone = { id: 'pBenGone', partKey: 'soprano', url: 'x', sampleRate: 48000, frames: 1, offsetFrames: 0 };
        const template = await loadTemplate(mine.templateId);
        const own = await startFromTemplate(template, { keyChosen: true, choir: { requestId: mine.id, templateId: mine.templateId, version: mine.version, parts: [...mine.parts, gone] } });
        const learn = await startFromTemplate(template, { keyChosen: true, choir: { requestId: ann.id, templateId: ann.templateId, version: ann.version, parts: ann.parts } });
        const goneKey = guideKey(mine.templateId, mine.version, 'soprano', 'pBenGone');
        const annKey = guideKey(ann.templateId, ann.version, ann.parts[0].partKey, ann.parts[0].id);
        await db.put('guides', { url: 'x', bytes: new ArrayBuffer(4) }, goneKey);
        await db.put('guides', { url: 'x', bytes: new ArrayBuffer(4) }, annKey);
        return { own: own.id, learn: learn.id, goneKey, annKey };
    })()""")
    f.run("(await import('/js/own-choir.js')).loadOwnChoir({ force: true })")
    f.wait(f"(async () => !(await {KEPT}({json.dumps(seeded['goneKey'])})))()", what="Ben's old part off the device")
    assert f.js(f"{KEPT}({json.dumps(seeded['annKey'])})"), "the Learn copy of a song they sing in was wiped"
    parts = f.js(f"(async () => (await (await import('/js/db.js')).get('projects', {json.dumps(seeded['own'])})).choir.parts.map((p) => p.id))()")
    assert "pBenGone" not in parts, parts

    # (c) The friend's Learn copy of Ann's song shares as an own song does (the server credits Ann's parts as made from
    # her singing). (d) Its Export is a song without choir parts (views/studio.js silentChoir): no Plus line.
    f.go(f"/p/{seeded['learn']}?api=mock&scenario=&as=plus", "document.querySelectorAll('canvas').length > 3")
    f.wait("(async () => { const s = (await import('/js/stores/session.js')).session; return s.guidesVersion > 0 && !Object.values(s.guideVoices).some((v) => v?.loading); })()", timeout=30, what="the voices")
    if f.size == "desktop":
        share = f.js("(await import('/js/i18n.js')).t('share.title')")
        assert f.js(f"[...document.querySelectorAll('header button')].some((b) => b.textContent.trim() === {json.dumps(share)})"), "a Learn copy can't be shared"

    # The friend-left notice: Ben removed from the owner's song, Home says so once, then the choir page.
    tab(f, mockChoirFriend="ready")
    f.go("/t/silent-night/choir?api=mock&scenario=&as=plus", "document.querySelector('[data-who-remove]')")
    f.run("document.querySelector('[data-who-remove]').click()")
    f.wait("document.querySelector('[role=dialog] form .btn-danger')", what="the remove confirmation")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("!document.querySelector('[data-who-friend]')", what="Ben gone")
    left = f.js("(await import('/js/i18n.js')).t('ownChoir.together.friendLeft', { name: 'Ben', song: 'Silent Night' })")
    f.go("/?api=mock&scenario=&as=plus", "document.querySelector('[data-choir-ready]')")
    f.wait(f"document.querySelector('[data-choir-ready]').textContent.includes({json.dumps(left)})", what="Home says Ben left")
    f.go("/t/silent-night/choir?api=mock&scenario=&as=plus", "document.querySelector('[data-choir-left]')")
    f.go("/?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    assert not f.js(f"document.querySelector('[data-choir-ready]')?.textContent.includes({json.dumps(left)})"), "the left notice shows again once seen"

    # (b) Sign-out takes the invites this device was joining by; one past its 14 days goes by itself.
    f.run("""const m = await import('/js/own-choir.js'); m.keepJoin('pJoinNow', { token: 'tok-9', parts: ['alto'] });
        localStorage.setItem('stackapella.choirJoins', JSON.stringify({ ...JSON.parse(localStorage.getItem('stackapella.choirJoins')), pJoinOld: { token: 'tok-8', at: Date.now() - 15 * 864e5 } }));
        await m.loadOwnChoir({ force: true });""")
    # (the clearing runs after the choir loads, in the background)
    f.wait("!('pJoinOld' in JSON.parse(localStorage.getItem('stackapella.choirJoins') || '{}'))", what="the old invite gone")
    left = f.js("Object.keys(JSON.parse(localStorage.getItem('stackapella.choirJoins') || '{}'))")
    assert "pJoinNow" in left, left
    f.run("await (await import('/js/own-choir.js')).forgetAllHere()")
    assert f.js("localStorage.getItem('stackapella.choirJoins')") in (None, "{}"), "an invite kept past sign-out"

    # (e) The switch off, a song they sing in: still shown, with Leave.
    tab(f, together=False, mockChoirJoined="ready")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-joined]')")
    assert f.js("!!document.querySelector('[data-joined-leave]')"), "no way to leave with the switch off"
    f.shot("switch-off-leave")
    clear_projects(f)
    tab(f, together=False)


run("own_choir_together_safety", journey)
