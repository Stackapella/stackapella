"""What's next (feature 13) and song links: a link to a song (/?song=<id>) opens it on the home;
a song part page's link (&part=<key>) starts the song on that part; the course map, with no lesson
waiting, shows What's next, whose suggestion opens its song.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
LEARNING = "(await import('/js/stores/learning.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/?song=michael-row&ref=song-page", "document.querySelector('#app header')")
    f.wait("document.querySelector('[role=dialog]')", what="the song's dialog")
    f.wait_text("Michael")
    assert f.js("!location.search.includes('song=')"), "the link's song left the address"
    # A song part page's Open in the app: the part named comes with it, and Start gives you that part.
    f.go("/?song=michael-row&part=tenor&ref=part-page", "document.querySelector('#app header')")
    f.wait("document.querySelector('[role=dialog] [data-song-start]')", what="the song's dialog, from a part page")
    assert f.js("!location.search.includes('part=')"), "the link's part left the address"
    f.run("document.querySelector('[role=dialog] [data-song-start]').click()")
    f.wait("/^\\/p\\/[^/]+\\/sing$/.test(location.pathname)", timeout=20, what="the song started")
    focus = f.js("""(async () => { const { project, parts } = await (await import('/js/stores/projects.js')).openProject(location.pathname.split('/')[2]);
        return parts.find((s) => s.id === project.practice.focus)?.templatePartKey ?? null; })()""")
    assert focus == "tenor", f"the part page's part should be yours: {focus}"
    # Every lesson of the free course with stars (unit 8 included, free since 2026-11-06): nothing waits, so What's next shows on the map.
    f.run(f"""const l = {LEARNING}; await l.loadLearning(); await l.setRange(55, 72);
        for (const {{ lesson }} of l.allLessons()) await l.finishLesson(lesson.id, {{ percent: 95, stars: 3 }});""")
    f.go("/learn?api=mock&as=member", "document.querySelector('h1')")
    f.wait("(await import('/js/stores/learning.js')).learning.loaded", what="the course")
    f.wait_text(t(f, "next.title"), timeout=30)
    f.shot("map")
    f.run("document.querySelector('[aria-labelledby=next-title] a').click()")
    f.wait("location.pathname === '/' && document.querySelector('[role=dialog]')", what="the suggested song open")
    # A library song's link opens on the song alone, never waiting on the whole catalogue (the first-open speed pass,
    # 2026-11-13): with the catalogue failing (and none kept on the device), a member's sheet still opens, Start ready.
    # The mock answers silent-night as the server does an open song: reviewed, and to a guest too (grant "open").
    f.go("/?api=mock&as=member", "document.querySelector('#app header')")
    f.run("sessionStorage.setItem('stackapella.mockOpenSong', 'silent-night')")
    clean = "localStorage.removeItem('stackapella.catalogue')"
    f.run(clean)
    f.go("/?api=mock&as=member&scenario=templates.catalogue:server_error&song=silent-night&ref=song-page", "document.querySelector('#app header')")
    f.wait("document.querySelector('[role=dialog] [data-song-start]:not([disabled])')", what="the library song's sheet, without the catalogue")
    assert f.js("!location.search.includes('song=')"), "the link's song left the address"
    # A guest may hear an open song but needs a free account to start it: never the song alone for a guest, so with the
    # catalogue down no Start is offered; with it, the sheet says an account is needed, as before.
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run(clean)
    f.go("/?api=mock&as=signedOut&scenario=templates.catalogue:server_error&song=silent-night&ref=song-page", "document.querySelector('#app header')")
    f.wait("(window.mockBodies ?? []).some((b) => b.operation === 'account.show')", what="the guest known")
    f.run("await new Promise((r) => setTimeout(r, 2500))")
    assert not f.js("(window.mockBodies ?? []).some((b) => b.operation === 'templates.show')"), "a guest's library song fetched to open on its own"
    assert not f.js("!!document.querySelector('[role=dialog] [data-song-start]')"), "a guest's library song opened to start without the catalogue"
    f.go("/?api=mock&scenario=&as=signedOut&song=silent-night&ref=song-page", "document.querySelector('#app header')")
    f.wait_text(t(f, "templates.needsAccount"))
    assert not f.js("!!document.querySelector('[role=dialog] [data-song-start]')"), "a guest can start an account song"
    f.run("sessionStorage.removeItem('stackapella.mockOpenSong')")
    f.go("/?api=mock&scenario=", "document.querySelector('#app header')")


if __name__ == "__main__":
    run("whatsnext", journey)
