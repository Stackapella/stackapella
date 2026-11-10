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


if __name__ == "__main__":
    run("whatsnext", journey)
