"""Drones (docs/tune-chord-plan.md, slices 1 and 3): in Sing's settings, under Practice, Drone is Off
until picked. Key note plays the key's note and its pure fifth under the song; The chord plays each
chord tuned pure; both are steady straight tones on the AudioContext clock with the guides, at their
own level. Neither plays in the studio's mix or goes into a file. Off stops it at once.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song  # noqa: E402


def journey(f):
    f.go("/voice", "document.querySelector('#app header, #app main')")
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'parts', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.js(f"{AUDIO}.unlock()")
    f.run(f"window.__s = {SESSION}; window.__a = {AUDIO};")
    S, A = "window.__s", "window.__a"
    audible = f"!!{S}.levels().get('drone')?.audible"

    f.wait("document.querySelector('header [data-settings-open]')", what="Sing's header")
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-drone]')", what="Drone in Sing's settings")
    f.run("document.querySelector('[data-drone]').scrollIntoView({ block: 'center' })")
    assert f.js("document.querySelector('[data-drone] [aria-pressed=true]').textContent.trim()") == f.js("(await import('/js/i18n.js')).t('sing.drone.off')"), "Off until picked"
    assert not f.js(audible), "no drone at first"
    f.js("new Promise((r) => setTimeout(r, 400))")
    f.shot("drone-off")

    # Key note: rendered with the guides, in the engine, audible.
    f.click(f.js("(await import('/js/i18n.js')).t('sing.drone.key')"), "[data-drone] button")
    f.wait(f"{S}.session.practice.drone === 'key' && {A}.engine.parts.has('drone') && {audible}", timeout=30, what="the key drone")
    f.wait_text(f.js("(await import('/js/i18n.js')).t('sing.drone.keyHint')"))
    f.js("new Promise((r) => setTimeout(r, 400))")
    f.shot("drone-key")
    key_length = f.js(f"{A}.engine.parts.get('drone').buffer?.length ?? 0")

    # The chord: the drone rendered again, now the song's chords tuned pure.
    f.click(f.js("(await import('/js/i18n.js')).t('sing.drone.chord')"), "[data-drone] button")
    f.wait(f"{S}.session.practice.drone === 'chord' && {audible}", timeout=30, what="the chord drone")
    f.js("new Promise((r) => setTimeout(r, 400))")
    f.shot("drone-chord")

    # Never in the studio's mix, nor a file.
    f.run(f"{S}.setScreen('studio')")
    assert not f.js(audible), "the studio leaves the drone out"
    f.run(f"{S}.setScreen('sing')")
    assert f.js(audible), "back in Sing it plays"

    # Off: at once.
    f.click(f.js("(await import('/js/i18n.js')).t('sing.drone.off')"), "[data-drone] button")
    f.wait(f"!({audible})", what="the drone off")
    assert key_length > 0, "the drone was a real buffer"
    f.press("Escape")
    clear_projects(f)


if __name__ == "__main__":
    run("tune_drone", journey)
