"""Hillel: Loop stays off once you turn it off (playing a phrase, the next phrase and another step
never turn it back on, and it's kept with the song); and Words, Notes and Staff switch the stage at
once while stopped, in part by part and in the whole song."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402

STAGE = "document.querySelector('[data-stage-view]')"
LOOP = f"{SESSION}.session.practice.loop"


def sing_on(f, grace, way):
    f.go(f"/p/{grace}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: '{way}', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'soprano')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")


def play(f):
    f.run(f"document.querySelector('button[aria-label=\"{t(f, 'studio.play')}\"]').click()")
    f.wait(f"{SESSION}.session.playing", what="playing")


def stop(f):
    f.run(f"await {SESSION}.stopPlayback()")


def views(f, where):
    stop(f)
    for view in ("notes", "staff", "words"):
        f.sing_view(view)
        f.wait(f"{STAGE}?.dataset.stageView === '{view}'", what=f"{where}: the stage says {view}")
        if view == "notes":
            f.wait(f"{STAGE}.querySelector('canvas')", what=f"{where}: the lane, stopped")
        elif view == "staff":
            f.wait(f"{STAGE}.querySelector('svg ellipse')", what=f"{where}: the staff, stopped")
        else:
            f.wait(f"!{STAGE}.querySelector('canvas, svg ellipse')", what=f"{where}: the words, stopped")
        assert not f.js(f"{SESSION}.session.playing"), "a view switch started playback"
        f.shot(f"{where}-{view}")


def journey(f):
    if f.size != "desktop":
        return  # the transport (Loop) is the wide screen's; a phone coaches with the big button
    f.go("/", "document.querySelector('#app header')")
    f.js(f"{AUDIO}.unlock()")
    clear_projects(f)
    grace = new_song(f, "happy-birthday")
    sing_on(f, grace, "parts")
    # Untouched, the step's Play loops the phrase (part by part's default).
    play(f)
    f.wait(LOOP, what="a phrase loops by default")
    # Loop off: it stays off through a play, the next phrase and another step.
    f.click_t("sing.transport.loopLine")
    f.wait(f"!{LOOP}", what="Loop off")
    stop(f)
    play(f)
    assert not f.js(LOOP), "playing the phrase turned Loop back on"
    stop(f)
    f.press("n")
    play(f)
    assert not f.js(LOOP), "the next phrase turned Loop back on"
    stop(f)
    f.click_t("sing.coach.step.singAlong")
    play(f)
    assert not f.js(LOOP), "another step turned Loop back on"
    stop(f)
    # Kept with the song: opened again, still off.
    f.wait(f"{SESSION}.session.project.practice.loopChosen === true", what="the choice kept")
    f.js("new Promise((r) => setTimeout(r, 900))")  # the practice saves half a second after
    sing_on(f, grace, "parts")
    chosen = f.js(f"{SESSION}.session.practice.loopChosen")
    play(f)
    assert not f.js(LOOP), f"Loop came back on when the song opened again (chosen: {chosen})"
    views(f, "parts")
    sing_on(f, grace, "whole")
    views(f, "whole")


if __name__ == "__main__":
    run("sing_loop", journey)
