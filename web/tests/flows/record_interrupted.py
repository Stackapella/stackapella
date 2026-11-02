"""M0: a take survives an interruption, and the mic opens during setup and closes on leaving.

Records a new part in the demo project, stops the audio mid-take (as a call or a locked screen
does), and checks the take was kept with the message, then that leaving the studio closes the mic.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"
I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/tests/seed.html?title=Interrupted", "location.pathname.startsWith('/p/')")
    f.wait("document.querySelectorAll('canvas').length > 3", what="the studio")
    f.mic_checked()  # the mic check (5.1) has its own journeys
    parts = f.js("document.querySelectorAll('[aria-label^=\"Mute \"]').length")
    f.js(f"{AUDIO}.engine.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.js("document.querySelector('footer button.glass-record').click()")
    f.wait_text(t(f, "headphones.title"))
    # The mic is already open while the person reads the headphones question (setup, not count-in).
    f.wait(f"{AUDIO}.audio.mic !== null", what="the mic opened during setup")
    f.shot("headphones")
    f.click(t(f, "headphones.start"))
    # A timing check first when this setup has none yet: skipped, with an estimate.
    f.wait(f"{AUDIO}.engine.playing?.recording || document.body.innerText.includes({json.dumps(t(f, 'timing.title'))})", what="recording, or the timing check")
    if not f.js(f"{AUDIO}.engine.playing?.recording"):
        f.click(t(f, "timing.skip"))
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 1.5", what="1.5 s recorded")
    f.shot("recording")
    f.js(f"{AUDIO}.engine.ctx.suspend()")  # a call, or the screen locking
    f.wait_text(t(f, "studio.takeKept", {"time": "~"}).split("~")[0].strip())
    f.shot("kept")
    f.wait(f"document.querySelectorAll('[aria-label^=\"Mute \"]').length > {parts}", what="the new part")
    f.js(f"{AUDIO}.engine.ctx.resume()")
    f.js("(await import('/js/router.js')).navigate('/')")
    f.wait("location.pathname === '/'", what="the projects page")
    f.wait(f"{AUDIO}.audio.mic === null && {AUDIO}.engine.mic === null", what="the mic closed on leaving")
    f.js("(async () => { const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.list.filter((p) => p.title === 'Interrupted')) await s.deleteProject(p.id); })()")


if __name__ == "__main__":
    run("record_interrupted", journey)
