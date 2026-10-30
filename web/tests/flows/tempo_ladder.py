"""Build up to speed (docs/practice-tracks-plan.md, the tempo ladder): a switch under Speed in Sing's
settings, off until turned on, that starts at 70%. On your own, on a looped phrase, a clean round
goes up 5% at the turn's end with no stop: the next speed's guides were rendered ahead, the run
carries on from the exact frame the turn ends on, at the new tempo, and the guides are slower no more.
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
    # f.wait's conditions can't await: the two modules, on the page.
    f.run(f"window.__s = {SESSION}; window.__a = {AUDIO};")
    S, A = "window.__s", "window.__a"

    # The switch: under Speed, off; on, the song plays at 70%.
    f.wait("document.querySelector('header [data-settings-open]')", what="Sing's header")
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-ladder][role=switch]')?.getAttribute('aria-checked') === 'false'", what="Build up to speed, off")
    f.run("document.querySelector('[data-ladder]').scrollIntoView({ block: 'center' })")
    f.js("new Promise((r) => setTimeout(r, 400))")
    f.shot("ladder-off")
    f.run("document.querySelector('[data-ladder][role=switch]').click()")
    f.wait(f"{S}.session.speed === 0.7 && {S}.session.practice.ladder", what="70%, on the ladder")
    f.js("new Promise((r) => setTimeout(r, 400))")
    f.shot("ladder")
    f.press("Escape")

    # On your own, on the first phrase: listen, then your turn, round and round.
    guide = f"{S}.session.parts.find((p) => p.templatePartKey === 'alto').guideSeconds"
    slow = f.js(guide)
    f.run(f"""const s = {SESSION}; s.setMode('own'); s.selectPhrase(0); s.session.practice.loop = true; await s.listenThenRepeat();""")
    f.wait(f"{S}.session.playing", what="playing")
    f.run(f"window.__rung = null; {S}.renderAtSpeed(0.75).then((r) => (window.__rung = r));")
    f.wait("window.__rung", timeout=30, what="75% rendered ahead")
    f.wait(f"{S}.session.phase === 'turn'", timeout=30, what="your turn")
    climbed = f.js(f"""(async () => {{ const {{ engine }} = {AUDIO}; const s = {SESSION}; const end = engine.passEnd();
        window.__boundary = end / engine.sampleRate; return s.climb(end); }})()""")
    assert climbed, "the climb was refused"
    assert f.js(f"{SESSION}.session.speed") == 0.75 and f.js(f"{SESSION}.session.playing"), "75%, still playing"
    # The next round starts at the boundary, at the new tempo: the listen of the same phrase.
    f.wait(f"{A}.engine.ctx.currentTime > window.__boundary + 0.2", timeout=30, what="past the turn's end")
    info = f.js(f"(() => {{ const i = {A}.engine.positionInfo(); return {{ phase: i.phase, position: i.position }}; }})()")
    phrase = f.js(f"(() => {{ const p = {S}.session.phraseList[0]; return {{ start: p.startSeconds, end: p.endSeconds }}; }})()")
    assert info["phase"] == "listen" and phrase["start"] - 0.01 <= info["position"] <= phrase["end"], f"after the climb: {info}, the phrase at 75% {phrase}"
    fast = f.js(guide)
    assert abs(fast - slow * 0.7 / 0.75) < 0.5, f"the guide at 75% lasts {fast:.2f} s, at 70% {slow:.2f} s"
    f.run(f"await {SESSION}.stopPlayback()")
    clear_projects(f)


if __name__ == "__main__":
    run("tempo_ladder", journey)
