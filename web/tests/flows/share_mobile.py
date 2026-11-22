"""Sharing in plain sight (Hillel's dad, 2026-11-23: on a phone he recorded a song but couldn't find how to share it). A
first-time singer's take review offers Share in its footer (one tap to the share sheet), and the studio shows Share on a
phone without opening the More menu.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402


def journey(f):
    f.go("/voice", "document.querySelector('#app header, #app main')")
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    clear_projects(f)
    song = new_song(f, "are-you-sleeping")
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'soprano')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    f.press("r")
    f.wait(f"{AUDIO}.engine.playing?.recording && {AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 3", timeout=30, what="3 s sung")
    f.press("Escape")
    f.wait("document.querySelector('[data-take-review]')", timeout=30, what="the take review")
    # One tap from the finished take: Share, in the footer, labelled.
    f.wait("document.querySelector('[data-review-share]')", what="Share in the take review")
    assert f.js("document.querySelector('[data-review-share]').textContent.trim()") == t(f, "share.title")
    f.shot("take-review-share")
    f.run("document.querySelector('[data-review-share]').click()")
    # The share sheet (a guest's asks them to sign in first; an account's makes the link).
    words = [t(f, "share.createLink"), t(f, "share.signIn")]
    f.wait("(() => { const d = document.querySelector('[role=dialog]')?.innerText ?? ''; return " + repr(words) + ".some((w) => d.includes(w)); })()", what="the share sheet, one tap away")
    f.shot("share-sheet")
    f.press("Escape")
    # The studio: Share on the header itself, never only in More.
    f.go(f"/p/{song}", "document.querySelectorAll('canvas').length > 3")
    if f.size == "phone":
        f.wait("document.querySelector('[data-studio-share]')?.getClientRects().length", what="Share on a phone's studio header")
        f.shot("studio-share")
    clear_projects(f)


run("share_mobile", journey)
