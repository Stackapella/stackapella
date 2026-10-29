"""M0: a take survives an interruption, and the mic opens during setup and closes on leaving.

Records a new part in the demo project, stops the audio mid-take (as a call or a locked screen
does), and checks the take was kept with the message, then that leaving the studio closes the mic.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"


def journey(f):
    f.go("/tests/seed.html?title=Interrupted", "location.pathname.startsWith('/p/')")
    f.wait("document.querySelectorAll('canvas').length > 3", what="the studio")
    f.mic_checked()  # the mic check (5.1) has its own journeys
    parts = f.js("document.querySelectorAll('[aria-label^=\"Mute \"]').length")
    f.js(f"{AUDIO}.engine.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.js("document.querySelector('footer button.glass-record').click()")
    f.wait_text("Headphones on?")
    # The mic is already open while the person reads the headphones question (setup, not count-in).
    f.wait(f"{AUDIO}.audio.mic !== null", what="the mic opened during setup")
    f.shot("headphones")
    f.click("They're on")
    f.wait_text("Timing check")
    f.click("Skip, use an estimate")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 1.5", what="1.5 s recorded")
    f.shot("recording")
    f.js(f"{AUDIO}.engine.ctx.suspend()")  # a call, or the screen locking
    f.wait_text("Recording stopped: your take is saved up to")
    f.shot("kept")
    f.wait(f"document.querySelectorAll('[aria-label^=\"Mute \"]').length > {parts}", what="the new part")
    f.js(f"{AUDIO}.engine.ctx.resume()")
    f.js("(await import('/js/router.js')).navigate('/')")
    f.wait("location.pathname === '/'", what="the projects page")
    f.wait(f"{AUDIO}.audio.mic === null && {AUDIO}.engine.mic === null", what="the mic closed on leaving")
    f.js("(async () => { const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.list.filter((p) => p.title === 'Interrupted')) await s.deleteProject(p.id); })()")


if __name__ == "__main__":
    run("record_interrupted", journey)
