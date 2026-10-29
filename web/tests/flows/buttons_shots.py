"""The app's buttons as Hillel reviews them (the flat buttons: "lets change to a more modern flat
style instead. the blocks will stand out even more"): Home, Sing on Amazing Grace, the studio and its
transport, a lesson and Settings, at both sizes, in Warm light and Stage dark, for before and after.

    OUT=~/Code/shots-review/flat-buttons/before OUT_DARK=1 PORT=8410 tool/flows.sh buttons_shots
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, SESSION, clear_projects, compress, guides_ready, home_page, light_first, new_song, shoot  # noqa: E402


def journey(f):
    assert OUT, "the shots need OUT=<dir>"
    home_page(f, "member")
    light_first(f)
    clear_projects(f)
    song = new_song(f)
    home_page(f, "member")
    shoot(f, "home", scroll="window.scrollTo(0, 0)")
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("document.querySelector('main button')", what="Sing")
    shoot(f, "sing")
    f.go(f"/p/{song}", "document.querySelectorAll('canvas').length > 1")
    guides_ready(f)
    shoot(f, "studio", scroll="window.scrollTo(0, 0)")
    lesson = f.js("(async () => { const { learning, loadLearning } = await import('/js/stores/learning.js'); await loadLearning(); return learning.course.units[0].lessons[1].id; })()")
    f.go(f"/learn/{lesson}", "document.querySelector('main button')")
    shoot(f, "lesson")
    f.go("/settings", "document.querySelector('main h1, main h2')")
    shoot(f, "settings", scroll="window.scrollTo(0, 0)")
    compress()
    clear_projects(f)


if __name__ == "__main__":
    run("buttons_shots", journey)
