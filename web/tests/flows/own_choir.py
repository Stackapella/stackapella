"""Hear yourself as a choir (track B, phase 3; docs/own-choir-app-plan.md; js/own-choir.js), on the mock with own_choir on
for the tab: the song's sheet has one row for it; the page asks for the terms once (English, one button); Start singing
opens Sing on a choir project; a take sent as the clip (its first 30 seconds from beat 0, a 16-bit WAV, the part) makes a
song that is on its way, then ready: Home's one quiet notice, the page's Listen, and the studio playing every part as
"AI voice made from your singing"; Delete and Stop in styled confirmations. Off, the sheet has no such row.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

SESSION = "(await import('/js/stores/session.js')).session"
LABEL = "AI voice made from your singing"


def tab(f, on):
    f.run(f"sessionStorage.setItem('stackapella.mockOwnChoir', '{'1' if on else '0'}'); sessionStorage.setItem('stackapella.mockChoirState', 'fresh'); sessionStorage.removeItem('stackapella.mockChoir');")


def sheet(f, song="Amazing Grace"):
    card = f"[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes({json.dumps(song)}))"
    f.run("document.querySelector('[data-browse-all]')?.click()")
    f.wait(card, what=f"{song}'s card")
    f.run(f"{card}.querySelector('button').click()")
    f.wait(f"document.querySelector('[role=dialog]')?.innerText.includes({json.dumps(song)})", what="the song's sheet")


def journey(f):
    # A guest with the switch on: no choir to ask for, so no call (a 401 would be a page error on every guest page).
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('h1')")
    tab(f, True)
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#home-songs [data-song-card]') || document.querySelector('[data-browse-all]') || document.querySelector('h1')")
    f.wait("(await import('/js/stores/account.js')).account.config?.ownChoir === true", what="own_choir on")
    assert not f.js("(globalThis.mockCalls ?? []).some((op) => op.startsWith('ownChoir.'))"), "a guest's pages never ask for a choir"
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    tab(f, False)
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]') || document.querySelector('[data-browse-all]')")
    clear_projects(f)
    sheet(f)
    assert not f.js("document.querySelector('[data-song-choir]')"), "no choir row while it's off"
    f.press("Escape")

    # On: the sheet's row, then the terms once.
    tab(f, True)
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]') || document.querySelector('[data-browse-all]')")
    f.wait("(await import('/js/stores/account.js')).account.config?.ownChoir === true", what="own_choir on")
    sheet(f)
    f.wait("document.querySelector('[data-song-choir-go]')", what="the choir row")
    f.shot("sheet-row")
    f.run("document.querySelector('[data-song-choir-go]').click()")
    f.wait("location.pathname === '/t/amazing-grace/choir' && document.querySelector('[data-choir-agree]')", what="the terms")
    f.shot("terms")
    f.run("document.querySelector('[data-choir-agree]').click()")
    f.wait("document.querySelector('[data-choir-start]')", what="Start singing")
    assert "free" in f.js("document.querySelector('[data-choir-quota]')?.textContent ?? ''"), "the free one is said"
    f.no_sideways("the choir page")
    f.shot("start")

    # Start singing: Sing on a choir project. A take (a steady tone, 20 s, from beat 0) goes as the clip, as Make my choir sends it.
    f.run("document.querySelector('[data-choir-start]').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing on the choir project")
    project = f.js("location.pathname.split('/')[2]")
    assert f.js(f"(async () => {{ const s = await import('/js/stores/projects.js'); await s.loadProjects(); return !!s.projects.all.find((p) => p.id === {json.dumps(project)})?.choir; }})()"), "a choir project"
    f.run("""const { sendClip } = await import('/js/own-choir.js');
        const samples = new Float32Array(48000 * 20).map((_, i) => 0.3 * Math.sin(i / 20));
        window.choirRequest = await sendClip({ templateId: 'amazing-grace', partKey: 'alto', samples, sampleRate: 48000, startSeconds: -0.5 });""")
    clip = f.js("globalThis.mockChoirClip")
    assert clip["partKey"] == "alto" and clip["offsetFrames"] == 0 and clip["frames"] == 48000 * 20 - 24000 and len(clip["sha256"]) == 64, clip
    # As Make my choir does: the project remembers its song's request.
    f.run(f"const db = await import('/js/db.js'); const p = await db.get('projects', {json.dumps(project)}); await db.put('projects', {{ ...p, choir: {{ requestId: window.choirRequest.id }} }});")

    # Ready by the next look (the mock makes it at once): Home's notice first (opening the choir page would mark it heard),
    # then the page's Listen, the studio's parts made from their singing.
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-choir-notice]')")
    f.shot("notice")
    f.run("document.querySelector('[data-choir-notice] button').click()")
    f.wait("document.querySelector('[data-choir-play]') && document.querySelector('[data-choir-progress]')", what="the ready song")
    assert "0 of 4" in f.js("document.querySelector('[data-choir-progress]').textContent")
    assert f.js("!document.querySelector('[data-choir-learn-part]').classList.contains('btn-primary')"), "learning is a quiet option"
    f.shot("ready")
    assert f.js("!!document.querySelector('[data-choir-share]')"), "Share beside Download"
    assert not f.js("document.querySelector('[data-choir-stop]')"), "no Stop on a ready song"
    # The finished track plays here, every part on the engine; a second press stops it.
    f.run("(await import('/js/audio.js')).unlock(); document.querySelector('[data-choir-play]').click()")
    f.wait("(async () => { const a = await import('/js/audio.js'); return a.engine.playing && [...a.engine.parts.keys()].filter((k) => k.startsWith('choir:')).length === 4; })()", timeout=30, what="the finished track playing")
    f.run("document.querySelector('[data-choir-play]').click()")
    f.wait("(async () => !(await import('/js/audio.js')).engine.playing)()", what="stopped")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    assert not f.js("document.querySelector('[data-choir-notice]')"), "seen: the notice is gone"
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3")
    f.wait(f"{SESSION}.guidesVersion > 0 && !Object.values({SESSION}.guideVoices).some((v) => v?.loading)", timeout=30, what="the voices")
    assert f.js(f"Object.values({SESSION}.guideVoices).filter((v) => v?.kind === 'real' && v.choir).length") == 4, "four parts made from their singing"
    f.wait("document.querySelectorAll('[data-status] [data-ai-voice=tag]').length === 4", what="each row says AI")
    f.shot("studio")

    # Delete this choir, then Stop: styled confirmations naming what goes.
    f.go("/t/amazing-grace/choir?api=mock&scenario=&as=member", "document.querySelector('[data-choir-delete]')")
    f.run("document.querySelector('[data-choir-delete]').click()")
    f.wait("document.querySelector('[role=dialog] form .btn-danger')", what="the delete confirmation")
    assert "Amazing Grace" in f.js("document.querySelector('[role=dialog] h2').textContent")
    f.shot("delete")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    # The voice is kept (Hillel: "store a model to make it easier"): the next song is one tap, no singing.
    f.wait("document.querySelector('[data-choir-again]') && document.querySelector('[data-choir-start]')", what="Make my choir from the saved voice")
    f.shot("again")
    f.run("document.querySelector('[data-choir-again]').click()")
    # On its way (the mock makes it at the next look, so it may already be ready): either way it came from the saved voice.
    f.wait("document.querySelector('[data-choir-wait]') || document.querySelector('[data-choir-play]')", what="made from the saved voice")
    assert not f.js("globalThis.mockChoirClip?.sha256"), "no clip sent: the saved voice made it"
    # Settings shows the voice kept, and deletes it in a styled confirmation that names what goes and what stays.
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-saved-voice]')")
    f.run("document.querySelector('[data-saved-voice-delete]').click()")
    f.wait("document.querySelector('[role=dialog] form .btn-danger')", what="the delete voice confirmation")
    body = f.js("document.querySelector('[role=dialog] form p').textContent")
    assert "Songs already made stay" in body, body
    f.shot("settings-voice")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("!document.querySelector('[data-saved-voice]')", what="the voice gone from Settings")
    # The song made from the saved voice is ready, and a ready song's page has no Stop (it's off a celebratory screen):
    # it's deleted first, then Stop turns everything off.
    f.go("/t/amazing-grace/choir?api=mock&scenario=&as=member", "document.querySelector('[data-choir-delete]') || document.querySelector('[data-choir-stop]')")
    assert not f.js("document.querySelector('[data-choir-delete]') && document.querySelector('[data-choir-stop]')"), "no Stop beside a ready song"
    if f.js("!!document.querySelector('[data-choir-delete]')"):
        f.run("document.querySelector('[data-choir-delete]').click()")
        f.wait("document.querySelector('[role=dialog] form .btn-danger')", what="the delete confirmation")
        f.run("document.querySelector('[role=dialog] form').requestSubmit()")
        f.wait("document.querySelector('[data-choir-stop]')", what="Stop, once no song is ready")
    f.run("document.querySelector('[data-choir-stop]').click()")
    f.wait("document.querySelector('[role=dialog] form .btn-danger')", what="the stop confirmation")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("location.pathname === '/'", what="home, everything deleted")
    clear_projects(f)


if __name__ == "__main__":
    run("own_choir", journey)
