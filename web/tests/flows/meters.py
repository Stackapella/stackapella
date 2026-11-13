"""Metre changes (docs/meters-plan.md, step 1), as a singer sees them: the fixture from the parked Here We Come
A-wassailing (verse in 6/8, two chorus phrases in 4/4, back to 6/8) opened as a song. Sing's staff on the chorus's first
line writes 4/4 where it starts, with no bar line inside its 6/8 beats; the line across the return to 6/8 writes 6/8 after
its bar line; the printed parts write each new time signature and "♩. = ♩". Shots for Hillel's OK. Against the mock.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SESSION = "(await import('/js/stores/session.js'))"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    song = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { validateTemplate } = await import('/js/templates.js');
        const fixture = await (await fetch('/tests/fixtures/wassail-meters.json')).json();
        return (await startFromTemplate(validateTemplate(fixture), { singers: 4, renderGuides: false })).id;
    })()""")

    # Sing, the chorus's first line (beats 16 to 24): 4/4 written at its start, its bar line at 20 only.
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length >= 4", what="the song open")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', view: 'staff', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'soprano')); s.savePractice();""")
    f.run(f"const s = {SESSION}; s.selectPhrase(s.session.phraseList.findIndex((p) => Math.abs(p.startBeat - 16) < 0.01))")
    f.wait("document.querySelector('[data-staff-metre]')", what="the staff's 4/4")
    assert "4" in f.js("document.querySelector('[data-staff-metre]').textContent"), "not 4/4"
    f.shot("staff-chorus")

    # The line that crosses back into 6/8 (verse 2's first phrase starts at 32, the chorus's second ends there).
    f.run(f"const s = {SESSION}; s.selectPhrase(s.session.phraseList.findIndex((p) => Math.abs(p.startBeat - 32) < 0.01))")
    f.wait("[...document.querySelectorAll('[data-staff-metre]')].some((g) => g.textContent.includes('8'))", what="the staff's 6/8")
    f.shot("staff-verse2")

    # The printed parts: each change written, with the beat it was.
    f.go(f"/p/{song}/print", "document.querySelector('[data-score-metre]')")
    assert f.js("document.querySelectorAll('[data-score-equal]').length") >= 2, "the beat equivalences"
    f.shot("print")


if __name__ == "__main__":
    run("meters", journey)
