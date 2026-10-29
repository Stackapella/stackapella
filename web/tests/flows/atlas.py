"""The screens overview's states that the user guide's shots don't have (Hillel's review atlas): home
signed in with songs, Sing's whole song in Staff, Plus, Sign in, an invite, Today's workout, the
song builder's start and the admin's home. Run with OUT=<dir> (help_shots.py's atlas mode): Warm
light, as <id>-<size>.png there, at both sizes.

    OUT=~/Code/shots-review/atlas/raw PORT=8410 tool/flows.sh atlas
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, SESSION, clear_projects, compress, light_first, new_song, shoot  # noqa: E402

MEMBER = "api=mock&scenario=&as=member"


def page(f, shot_id, path, ready="document.querySelector('main h1, main h2, #app h1')"):
    f.go(path, ready)
    light_first(f)
    shoot(f, shot_id, scroll="window.scrollTo(0, 0)")


def journey(f):
    assert OUT, "the atlas needs OUT=<dir>"
    f.go(f"/?{MEMBER}", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    clear_projects(f)
    song = new_song(f)
    page(f, "home--signed-in", f"/?{MEMBER}")
    f.go(f"/p/{song}/sing?{MEMBER}", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', view: 'staff', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("document.querySelector('main svg ellipse')", what="the staff")
    light_first(f)
    shoot(f, "sing--staff")
    page(f, "plus", f"/plus?{MEMBER}")
    page(f, "signin", "/signin?api=mock&as=signedOut")
    page(f, "invite", "/invite/Pq4Rt7Vb2Nx9?api=mock&as=signedOut")
    page(f, "workout", f"/workout?{MEMBER}")
    page(f, "create--start", f"/create?{MEMBER}")
    page(f, "admin--home", "/admin?api=mock&as=admin")
    compress()


if __name__ == "__main__":
    run("atlas", journey)
