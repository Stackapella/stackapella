"""Home's one way to start a song (docs/home-create-plan.md), shot for Hillel's review in Warm light
at both sizes: a first visit, Your songs with one of each kind (a recording, a song written in the
builder, a draft) above the song library, and New song's sheet. help_shots.py's atlas mode:

    OUT=~/Code/shots-review/home-new PORT=8410 tool/flows.sh home_shots
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, clear_projects, compress, light_first, shoot  # noqa: E402

HOME = "/?api=mock&scenario=&as=member"


def journey(f):
    assert OUT, "the shots need OUT=<dir>"
    f.go(HOME, "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    clear_projects(f)
    f.go("/?api=mock&as=signedOut", "document.querySelector('[data-new-song]')")
    light_first(f)
    shoot(f, "home-first-visit", scroll="window.scrollTo(0, 0)")
    f.go(HOME, "document.querySelector('[data-new-song]')")
    f.run("""const p = await import('/js/stores/projects.js'); await p.createProject({ title: 'Our Lullaby' });
        const { mine } = await import('/js/stores/templates.js');
        mine.list.push({ id: 'shot-written', state: 'device', updatedAt: Date.now() - 3600e3, template: { title: 'My Grace', parts: [{}, {}, {}, {}], voicings: {} } });
        mine.drafts.push({ id: 'shot-draft', updatedAt: Date.now() - 2 * 3600e3, template: { title: 'Evening Song' } });""")
    f.wait("document.querySelector('section[aria-labelledby=band-projects] [data-kind=draft]') && document.querySelector('section[aria-labelledby=band-projects] [data-kind=written]')", what="one of each kind")
    light_first(f)
    shoot(f, "home-your-songs", scroll="document.querySelector('section[aria-labelledby=band-projects]').scrollIntoView({ block: 'start' }); window.scrollBy(0, -16)")
    f.run("document.querySelector('[data-new-song]').click()")
    f.wait("document.querySelector('[data-new-sheet]')", what="New song's sheet")
    shoot(f, "home-new-song-sheet")
    compress()


if __name__ == "__main__":
    run("home_shots", journey)
