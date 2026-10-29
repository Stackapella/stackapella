"""A new project (Hillel's decision, "new projects start at once"): New project opens the studio at
once, with no window and no name; the projects list shows a placeholder for a blank name; the name
is set in place on the studio's title; project settings explain each setting, and the next new
project starts with the settings last changed.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def new_song(f):
    """Home's one New song: its sheet."""
    f.run("document.querySelector('[data-new-song]').click()")
    return f.wait("document.querySelector('[data-new-sheet]')", what="New song's sheet")


def just_record(f):
    new_song(f)
    f.click(t(f, "home.new.record"), "[data-new-sheet] button")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    # One New song (docs/home-create-plan.md): Your songs' first card, blank with a plus (Hillel), and
    # nothing else; its sheet's two choices, each to its place.
    f.wait("document.querySelector('[data-new-song]')", what="New song")
    label = t(f, "projects.new")
    count = f.js(f"[...document.querySelectorAll('section[aria-labelledby=band-projects] button')].filter((b) => b.textContent.includes({label!r})).length")
    assert count == 1, f"{count} New song controls in Your songs"
    new_song(f)
    f.shot("new-song")
    # Two choices (Hillel: a song from the library is learned by tapping it, not from here).
    assert f.js("document.querySelectorAll('[data-new-sheet] > button').length") == 2, "New song doesn't have just two choices"
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.wait_text(t(f, "create.home.chords"))
    f.press("Escape")
    new_song(f)
    f.click(t(f, "home.new.record"), "[data-new-sheet] button")
    f.wait("location.pathname.startsWith('/p/') && document.querySelector('#song-name')", what="the studio, at once")
    first = f.js("location.pathname.split('/')[2]")
    assert f.js("document.querySelector('#song-name').value") == "", "a name was filled in"
    assert f.js("document.querySelector('#song-name').placeholder") == t(f, "project.namePlaceholder"), "no tap-to-name hint"
    f.shot("blank")
    # Blank in the list: the placeholder.
    f.go("/", "document.querySelector('#app header')")
    f.wait_text(t(f, "project.untitled"))
    # Named in place.
    f.go(f"/p/{first}", "document.querySelector('#song-name')")
    f.type("#song-name", "Our first harmony")
    f.run("document.querySelector('#song-name').dispatchEvent(new Event('change'))")
    f.wait(f"(await (await import('/js/db.js')).get('projects', '{first}')).title === 'Our first harmony'", what="the name saved")
    # Settings explained, and remembered for the next new project.
    f.run("document.querySelector('#song-name').nextElementSibling.click()")
    f.wait_text(t(f, "project.hint.countIn"))
    f.shot("settings")
    # The count-in is On or Off (Hillel's call): turned off, and remembered off.
    # The switch is named by its label (aria-labelledby), the words in its row.
    f.run(f"[...document.querySelectorAll('[role=dialog] [role=switch]')].find((b) => document.getElementById(b.getAttribute('aria-labelledby'))?.textContent.trim() === \"{t(f, 'studio.setup.countIn')}\").click()")
    f.click_t("common.save")
    f.wait("!document.querySelector('[role=dialog]')", what="settings saved")
    f.go("/", "document.querySelector('#app header')")
    f.wait_text("Our first harmony")
    just_record(f)
    f.wait("location.pathname.startsWith('/p/') && location.pathname.split('/')[2] !== " + repr(first), what="a second project")
    second = f.js("location.pathname.split('/')[2]")
    assert f.js(f"(await (await import('/js/db.js')).get('projects', '{second}')).countInBars") == 0, "the count-in wasn't remembered"
    # The free plan's three songs used: New song shows the upgrade moment (99.png), its slots full.
    f.run("const s = await import('/js/stores/projects.js'); window.third = (await s.createProject({ title: 'Third' })).id;")
    third = f.js("window.third")
    f.go("/", "document.querySelector('#app header')")
    just_record(f)
    f.wait("document.querySelector('[data-plus-moment] [data-slots]')", what="the upgrade moment with its slots")
    f.wait_text(t(f, "plus.moment.makeRoom"))
    f.shot("upgrade-moment")
    f.press("Escape")
    for project in (first, second, third):
        f.run(f"(await import('/js/stores/projects.js')).deleteProject('{project}')")


if __name__ == "__main__":
    run("new_project", journey)
