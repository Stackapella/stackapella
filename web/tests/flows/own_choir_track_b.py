"""Track B before Track A (Hillel, 2026-11-21: Hear yourself as a choir may launch first): ai_voices OFF, own_choir and
own_choir_songs ON. Nothing of Track A shows (no AI tag on a library song's card or sheet, no AI guide in a library
song's studio, no Be the voice), even though the catalogue still carries AI guides; Hear yourself as a choir works end
to end: the sheet's row, the ready song's Listen, Share and Download, the choir project's studio with every part
labelled as made from the singer's own voice, and Settings' saved voice deleted in a styled confirmation.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

SESSION = "(await import('/js/stores/session.js')).session"
TRACK_A = "[data-card-ai], [data-ai-voice-go], [data-guide-ai]"


def track_a_gone(f, where):
    assert not f.js(f"document.querySelector({json.dumps(TRACK_A)})"), f"Track A shows on {where}"


def sheet(f, song):
    card = f"[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes({json.dumps(song)}))"
    f.run("document.querySelector('[data-browse-all]')?.click()")
    f.wait(card, what=f"{song}'s card")
    f.run(f"{card}.querySelector('button').click()")
    f.wait(f"document.querySelector('[role=dialog]')?.innerText.includes({json.dumps(song)})", what="the song's sheet")


def journey(f):
    f.go("/?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    f.run("""sessionStorage.removeItem('stackapella.mockAiVoices'); sessionStorage.setItem('stackapella.mockOwnChoir', '1');
        sessionStorage.setItem('stackapella.mockOwnChoirSongs', '1'); sessionStorage.removeItem('stackapella.mockChoirState');
        sessionStorage.removeItem('stackapella.mockChoir');""")
    clear_projects(f)
    f.go("/?api=mock&scenario=&as=plus", "document.querySelector('#home-songs [data-song-card]') || document.querySelector('[data-browse-all]')")
    f.wait("(async () => { const c = (await import('/js/stores/account.js')).account.config; return c?.ownChoir === true && c?.ownChoirSongs === true && c?.aiVoices === false; })()", what="Track B on, Track A off")
    assert f.js("(async () => (await import('/js/stores/catalogue.js')).catalogue.list.some((e) => e.parts?.some((p) => p.guide?.source === 'ai')))()"), "the catalogue carries AI guides (the app must hide them)"

    # Library songs: no AI tag on a card, and the sheet has the choir row but no AI voices row.
    track_a_gone(f, "Home")
    sheet(f, "Silent Night")
    f.wait("document.querySelector('[data-song-choir]')", what="the choir row")
    assert not f.js("document.querySelector('[role=dialog] [data-ai-voice]')"), "an AI voice on the library sheet"
    f.shot("sheet")
    f.press("Escape")

    # A library song's studio: real or synth guides only, nothing labelled AI.
    project = f.js("(async () => { const { loadTemplate } = await import('/js/templates.js'); const { startFromTemplate } = await import('/js/stores/projects.js'); return (await startFromTemplate(await loadTemplate('silent-night'), { keyChosen: true })).id; })()")
    f.go(f"/p/{project}?api=mock&scenario=&as=plus", "document.querySelectorAll('canvas').length > 3")
    f.wait(f"{SESSION}.guidesVersion > 0 && !Object.values({SESSION}.guideVoices).some((v) => v?.loading)", timeout=30, what="the voices")
    assert not f.js(f"Object.values({SESSION}.guideVoices).some((v) => v?.ai)"), "an AI guide plays in a library song"
    assert not f.js("document.querySelector('[data-ai-voice]')"), "an AI label in a library song's studio"
    track_a_gone(f, "the library studio")

    # The ready choir: Listen, Share and Download (Plus), all with Track A off.
    f.go("/t/silent-night/choir?api=mock&scenario=&as=plus", "document.querySelector('[data-choir-play]')")
    track_a_gone(f, "the choir page")
    f.run("(await import('/js/audio.js')).unlock(); document.querySelector('[data-choir-play]').click()")
    f.wait("(async () => { const a = await import('/js/audio.js'); return a.engine.playing && [...a.engine.parts.keys()].filter((k) => k.startsWith('choir:')).length === 4; })()", timeout=30, what="the choir playing")
    f.run("document.querySelector('[data-choir-play]').click()")
    f.run("window.__files = 0; const made = URL.createObjectURL; URL.createObjectURL = (b) => { window.__files += 1; return made.call(URL, b); };")
    f.run("document.querySelector('[data-choir-download]').click()")
    f.wait("window.__files === 1", timeout=30, what="the download, with Plus")
    f.shot("choir-ready")

    # The choir project's studio: every part labelled as made from their own singing, and Share opens.
    f.run("document.querySelector('[data-choir-learn-part]').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing/.test(location.pathname)", what="Sing on the choir project")
    choir = f.js("location.pathname.split('/')[2]")
    f.go(f"/p/{choir}?api=mock&scenario=&as=plus", "document.querySelectorAll('canvas').length > 3")
    f.wait(f"{SESSION}.guidesVersion > 0 && !Object.values({SESSION}.guideVoices).some((v) => v?.loading)", timeout=30, what="the choir's voices")
    assert f.js(f"Object.values({SESSION}.guideVoices).filter((v) => v?.kind === 'real' && v.choir).length") == 4, "four parts made from their singing"
    f.wait("document.querySelectorAll('[data-status] [data-ai-voice=tag]').length === 4", what="each part labelled AI, their own voice")
    track_a_gone(f, "the choir studio")
    f.shot("choir-studio")
    f.go(f"/p/{choir}?api=mock&scenario=&as=plus&sheet=share", "document.querySelector('[role=dialog]')")
    f.wait("document.querySelector('[role=dialog] form, [role=dialog] [data-share]')", what="the share sheet")
    f.press("Escape")

    # Settings: the saved voice, deleted in a styled confirmation.
    f.run("const { ownChoir } = await import('/js/own-choir.js'); if (ownChoir.data) ownChoir.data.voice ??= { savedAt: new Date().toISOString(), lastUsedAt: new Date().toISOString(), expiresAt: new Date(Date.now() + 864e5 * 300).toISOString() };")
    f.go("/settings?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    f.wait("document.querySelector('[data-saved-voice]')", what="the saved voice")
    f.run("document.querySelector('[data-saved-voice-delete]').click()")
    f.wait("document.querySelector('[role=dialog] form .btn-danger')", what="the delete voice confirmation")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("!document.querySelector('[data-saved-voice]')", what="the voice gone")
    track_a_gone(f, "Settings")
    clear_projects(f)


run("own_choir_track_b", journey)
