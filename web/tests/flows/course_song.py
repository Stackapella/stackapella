"""Song courses (docs/song-courses-plan.md), Amazing Grace's, live for everyone since 2026-10-30: the song's
sheet on Home has Learn it step by step under Play, Edit and Start, quiet, and never in a new singer's
first week. It opens the song's course (the Plus course page, every part's unit open, the melody first),
and a lesson's steps play the song's own lines, loaded with the song. A member who isn't staff opens
it too. With OUT=<dir> it shoots the sheet, the course page and two lessons:

    OUT=~/Code/shots-review/song-courses FLOW_SKINS=game,serious PORT=8431 tool/flows.sh course_song
"""
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
WEEK_AGO = "Date.now() - 30 * 86400000"
LANE = "document.querySelectorAll('[data-lane-note]').length > 3 && !document.querySelector('[data-song-step]') && !document.querySelector('[data-game-title]')"


def open_sheet(f):
    f.wait("document.querySelector('#home-songs [data-song-card]') || document.querySelector('[data-browse-all]')", what="the songs")
    f.run("document.querySelector('[data-browse-all]')?.click()")
    f.wait("[...document.querySelectorAll('#home-songs [data-song-card]')].some((c) => c.textContent.includes('Amazing Grace'))", what="Amazing Grace's card")
    f.run("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace')).querySelector('button').click()")
    f.wait("document.querySelector('[role=dialog]')?.innerText.includes('Amazing Grace')", what="the song's sheet")


def no_star_words(f, where):
    if f.js("document.documentElement.dataset.skin === 'serious'"):
        text = f.js("document.querySelector('#app').innerText")
        assert not re.search(r"\bstars?\b", text, re.I), f"serious mode: no star words on {where}"


def journey(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#lead-title')")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    f.wait(f"{STORE}.learning.loaded", what="the progress")
    f.run(f"{STORE}.learning.progress.startedAt = {WEEK_AGO};")  # past the first week
    open_sheet(f)
    f.wait("document.querySelector('[data-song-course]')", what="Learn it step by step")
    assert f.js("document.querySelector('[data-song-course]').classList.contains('btn-ghost')"), "quiet: Start stays the one primary"
    assert f.js("document.querySelectorAll('[role=dialog] .btn-primary').length") == 1, "one primary action on the sheet"
    f.no_sideways("the song's sheet")
    picture(f, "song-sheet", "document.querySelector('[data-song-course]').scrollIntoView({ block: 'center' })")

    # A new singer's first week keeps the sheet as it was.
    f.run(f"{STORE}.learning.progress.startedAt = Date.now();")
    f.wait("!document.querySelector('[data-song-course]')", what="no course in the first week")
    f.run(f"{STORE}.learning.progress.startedAt = {WEEK_AGO};")
    f.wait("document.querySelector('[data-song-course]')", what="the course again after it")

    # The course page: Up next is the melody's Hear it, every part's unit is on the map.
    f.run("document.querySelector('[data-song-course]').click()")
    f.wait("location.pathname === '/learn/s/amazing-grace' && document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="the song's course")
    assert f.js("document.querySelector('[data-up-next] h2').textContent.trim()") == "Hear it", "Up next: the melody's first lesson"
    assert not f.js("document.querySelector('[role=dialog]')"), "the sheet closed behind it"
    assert not f.js("document.querySelector('[data-sharpen]')"), "no Sharpen up on a song course"
    assert t(f, "songCourse.eyebrow") in f.js("document.querySelector('header').innerText"), "named as the song's step by step course"
    no_star_words(f, "the course page")
    f.no_sideways("the song's course page")
    # The open unit's seven lessons (the finish-line audit, 2026-11-13): no bubble over another, every name whole (on a
    # phone the path scrolls sideways instead), and the lesson card under it doesn't repeat Up next's Start lesson.
    # Game mode draws the open unit as its stage map instead of the path.
    if f.skin != "game":
        path = f.js("""(() => {
            const ol = document.querySelector('[data-lesson-path]');
            const bubbles = [...ol.querySelectorAll('li > button')].map((b) => b.getBoundingClientRect());
            const overlaps = bubbles.slice(1).filter((r, i) => r.left < bubbles[i].right - 0.5).length;
            const cut = [...ol.querySelectorAll('li > span.line-clamp-2')].filter((s) => s.scrollHeight > s.clientHeight + 1 || s.scrollWidth > s.clientWidth + 1).map((s) => s.textContent);
            return { lessons: bubbles.length, overlaps, cut, scrolls: ol.scrollWidth > ol.clientWidth };
        })()""")
        assert path["lessons"] >= 6 and not path["overlaps"] and not path["cut"], f"the lesson path: {path}"
    assert not f.js("document.querySelector('[data-picked-lesson] .btn-primary')"), "the lesson card repeats Up next's Start lesson"
    picture(f, "song-course-page")

    # A part picked: Up next is in its unit.
    f.run(f"{ROUTER}.navigate('/learn/s/amazing-grace?part=alto')")
    f.wait("document.querySelector('[data-up-next]')", what="the course page, for the alto")

    # Lines 1 and 2: the melody's line 1, from the song itself, its words in the lane.
    f.run(f"{ROUTER}.navigate('/learn/song-amazing-grace-soprano-2')")
    f.wait(LANE, what="line 1's notes from the song")
    f.wait_text("maz-")
    assert "G" in f.js("document.querySelector('#app').innerText"), "the song's own key"
    no_star_words(f, "a song lesson")
    f.no_sideways("a song lesson")
    picture(f, "song-lesson-lines")

    # With the others: the alto's whole part, the other three parts sounding with it.
    f.run(f"{ROUTER}.navigate('/learn/song-amazing-grace-alto-5')")
    f.wait(LANE, what="the alto with the others")
    f.no_sideways("singing with the others")
    picture(f, "song-lesson-others")

    # A round (Are You Sleeping): one unit; coming in second starts with the first voice leading in.
    f.run(f"{ROUTER}.navigate('/learn/s/are-you-sleeping')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="the round's course")
    assert f.js(f"{STORE}.learning.courses['song-are-you-sleeping'].units.length") == 1, "a round is one unit"
    second = f.js(f"{STORE}.learning.courses['song-are-you-sleeping'].units[0].lessons.find((l) => l.title.en === 'Come in second').id")
    f.run(f"{ROUTER}.navigate('/learn/{second}')")
    f.wait(LANE, what="coming in second")
    assert f.js("document.querySelectorAll('[data-lane-note=sung]').length") > 3, "your own line to sing"
    f.no_sideways("coming in second")
    picture(f, "song-lesson-round")

    # A library song (Silent Night): its notes come from the catalogue, as the song itself does.
    f.run(f"{ROUTER}.navigate('/learn/song-silent-night-soprano-2')")
    f.wait(LANE, what="Silent Night's lines from the catalogue")
    f.wait_text("Si-")

    # Live for everyone (Hillel, 2026-10-30): a member who isn't staff opens it too.
    f.go("/learn/s/amazing-grace?api=mock&as=member", "document.querySelector('#app h1')")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="the course, for a member")
    compress()


run("course_song", journey)
