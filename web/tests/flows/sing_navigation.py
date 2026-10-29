"""Leaving Sing goes back where the person came from (Hillel's report: stopping Sing landed him in
the studio): a song started from the home's "Learn a harmony" goes back home; Sing opened from the
studio goes back to the studio; a lesson's song goes back to its lesson; a cold link goes home.
The studio stays one tap away ("Open in the studio"), and the studio's own back follows the same rule.
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def back(f):
    """Sing's or the studio's back arrow (or the chooser's Back)."""
    label = t(f, "common.back")
    f.wait(f"[...document.querySelectorAll('button')].some((b) => b.getAttribute('aria-label') === {label!r} || b.textContent.trim() === {label!r})", what="a back button")
    f.run(f"[...document.querySelectorAll('button')].find((b) => b.getAttribute('aria-label') === {label!r} || b.textContent.trim() === {label!r}).click()")


def studio_back(f):
    """The studio's back: the arrow on a phone, the logo on a wide screen."""
    f.wait("document.querySelector('footer')", what="the studio")
    f.run("[...document.querySelectorAll('header button, header a')].find((el) => el.offsetWidth && (el.getAttribute('href') === '/' || el.querySelector('svg'))).click()")


def journey(f):
    # From the home: a library song's sheet, Start, then back: the home, never the studio.
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#band-songs')")
    f.wait("document.querySelector('#home-songs [data-song-card] button')", what="the songs")
    f.run("document.querySelector('#home-songs [data-song-card] button').click()")
    f.click_t("templates.start")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing")
    project = f.js("location.pathname.split('/')[2]")
    back(f)
    f.wait("location.pathname === '/'", what="back home")

    # From the studio's Sing button: back to the studio; the studio's back then goes home.
    f.go(f"/p/{project}", "document.querySelector('footer')")
    f.click_t("sing.open")
    f.wait(f"location.pathname === '/p/{project}/sing'", what="Sing from the studio")
    back(f)
    f.wait(f"location.pathname === '/p/{project}'", what="back to the studio")
    time.sleep(0.5)
    studio_back(f)
    f.wait("location.pathname === '/'", what="the studio's back goes home")

    # Sing opened cold (a shared link, a reload): back goes home. The studio is one tap away.
    f.go(f"/p/{project}/sing", "document.querySelector('h1, header')")
    f.wait("document.querySelector('[data-way=studio]')", what="the chooser")
    f.run("document.querySelector('[data-way=studio]').click()")
    f.wait(f"location.pathname === '/p/{project}'", what="the studio")
    studio_back(f)
    f.wait(f"location.pathname === '/p/{project}/sing'", what="the studio's back returns to Sing")
    back(f)
    f.wait("location.pathname === '/'", what="cold Sing goes home")

    # A lesson's song: back to the lesson.
    f.run("""const s = await import('/js/stores/projects.js'); const { loadTemplate } = await import('/js/templates.js');
        const p = await s.startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4, course: { lessonId: '4-words' } }); window.lessonProject = p.id;""")
    lesson = f.js("window.lessonProject")
    f.go(f"/p/{lesson}/sing", "document.querySelector('h1, header')")
    back(f)
    f.wait("location.pathname === '/learn/4-words'", what="back to the lesson")

    f.run("const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all) await s.deleteProject(p.id);")


if __name__ == "__main__":
    run("sing_navigation", journey)
