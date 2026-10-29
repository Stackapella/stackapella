"""The home, direction C (docs/decisions.md, "the home page"): the course leads every state, and
the bands under it follow js/home.js. A first visit gets Start singing (lesson 1, the one way in), then
the songs, then a place to make your own. A learner mid-course
sees the unit's path with the next lesson marked. Someone who sang in a project this week gets a
shorter course band on a phone (still with the path and "Lesson n of m") and their projects second. A beta singer on Plus gets their parts second, a Plus
mark, and no free-plan meter.
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
# The bands js/home.js orders (choir groups, when someone has them, always come last, and the Plus
# courses under the course card, are outside it).
BANDS = "[...document.querySelectorAll('section[aria-labelledby^=band-]')].map((s) => s.getAttribute('aria-labelledby').slice(5)).filter((b) => b !== 'groups' && b !== 'courses').join(' ')"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def progress(f, lessons, xp):
    """Puts course progress on the device, as if earned."""
    starred = {lesson: {"stars": 2, "bestPercent": 80, "at": 0} for lesson in lessons}
    f.run(f"""const {{ emptyProgress }} = await import('/js/course.js'); const db = await import('/js/db.js');
        const day = new Date().toISOString().slice(0, 10);
        await db.put('learning', {{ ...emptyProgress(), startedAt: Date.now(), lessons: {json.dumps(starred)}, xp: {{ total: {xp}, days: {{}} }}, practice: {{ [day]: 600 }} }}, 'progress');""")


def home(f, who):
    f.go(f"/?api=mock&scenario=&as={who}", "document.querySelector('#app header')")
    f.wait("document.querySelector('#lead-title') && document.querySelector('section[aria-labelledby^=band-]')", what="the home")
    time.sleep(0.6)


SONG_CARDS = "document.querySelectorAll('#home-songs > li').length"
SONG_TOGGLE = "document.querySelector('div > button[aria-controls=home-songs]')"


def songs_band(f):
    """Two rows of songs (a tablet up), Show more and Show fewer; on a phone the band ends with See all."""
    f.wait("(await import('/js/templates.js')).listTemplates().then((l) => l.length > 0) && document.querySelector('#home-songs')", what="the songs")
    # The library is the catalogue only (docs/home-create-plan.md): your own songs are in Your songs.
    f.run("const { mine } = await import('/js/stores/templates.js'); mine.list.push({ id: 'flow-mine', state: 'device', template: { title: 'Flow song', parts: [{}, {}], voicings: {} } });")
    f.wait("document.querySelector('section[aria-labelledby=band-projects] [data-kind=written]')", what="your written song in Your songs")
    assert not f.js("document.querySelector('#home-songs').innerText.includes('Flow song')"), "your song is in the library too"
    total = f.js("(await import('/js/templates.js')).listTemplates().then((l) => l.length)")
    if f.size == "desktop":
        columns = 4  # Hillel's Home design: four to a row
        cells = f.js("document.querySelectorAll('#home-songs > li').length")
        assert cells == total if total <= columns * 2 else cells % columns == 0, f"{cells} cells for {total} songs"
        if total > f.js(SONG_CARDS):
            f.run("document.querySelector('[data-browse-all]').click()")
            f.wait(f"{SONG_CARDS} === {total}", what="every song")
            f.shot("songs-open")
            f.click(t(f, "home.songs.showFewer"), "button[aria-controls=home-songs]")
            f.wait(f"document.querySelectorAll('#home-songs > li').length === {cells}", what="whole rows again")
    else:
        if total > 6:
            assert f.js(SONG_CARDS) == 7, "six songs and Browse all"
            f.run("document.querySelector('[data-browse-all]').click()")
            f.wait(f"{SONG_CARDS} === {total}", what="every song, in a grid")
            f.shot("songs-open")
            f.click(t(f, "home.songs.showFewer"), "button[aria-controls=home-songs]")
            f.wait(f"{SONG_CARDS} === 7", what="the band again")
        else:
            assert f.js(SONG_CARDS) == total, "every song shows on the phone's band"
    f.run("const { mine } = await import('/js/stores/templates.js'); mine.list.splice(0, mine.list.length, ...mine.list.filter((r) => !r.id.startsWith('flow-')));")


def journey(f):
    # 1. A first visit, signed out.
    home(f, "signedOut")
    f.wait_text(t(f, "home.lead.title"))
    f.wait(f"{BANDS} === 'projects songs'", what="Your songs, then the library")
    assert t(f, "home.songs.first") in f.js("document.body.innerText"), "songs to hear first"
    f.shot("first-visit")
    songs_band(f)
    # One way in: Start goes to lesson 1 (no Skip ahead).
    assert t(f, "home.lead.free") in f.js("document.body.innerText"), "the small print"
    f.click_t("course.start")
    f.wait("location.pathname === '/learn/1-comfortable'", what="lesson 1")

    # 2. A learner mid-course: units 1 to 4 done, the first lesson of unit 5 too.
    units = f.js("(await (await fetch('/course/course.json')).json()).units.map((u) => u.lessons.map((l) => l.id))")
    progress(f, [lesson for unit in units[:4] for lesson in unit] + ["5-drone"], 340)
    home(f, "member")
    f.wait("document.querySelector('[aria-current=step]')", what="the path")
    # textContent: the eyebrow is set in capitals by CSS; game mode's title screen calls the unit a stage.
    words = f.js("document.body.textContent")
    assert t(f, "home.lead.unit", {"number": 5, "title": "Your first harmony"}) in words or t(f, "game.home.stage", {"number": 5, "title": "Your first harmony"}) in words, "the unit"
    # Continue lesson, or game mode's title screen's Press start (the same button, reskinned).
    f.wait(f"document.querySelector('[data-press-start]') || document.body.innerText.includes({t(f, 'home.lead.continueLesson')!r})", what="Continue lesson")
    # Nothing else waiting on a learner: today's workout comes second (42.png: a strip of its steps).
    f.wait(f"{BANDS} === 'workout projects songs'", what="workout, Your songs, the library")
    f.wait("document.querySelector('section[aria-labelledby=band-groups]')", what="the choir groups the mock account sings in")
    assert t(f, "home.projects.used", {"used": 0, "limit": 3}) in f.js("document.body.innerText"), "the meter"
    f.shot("mid-course")

    # 3. They sang in a project this week: it comes second, under a compact course band.
    f.run("""const s = await import('/js/stores/projects.js'); const p = await s.createProject({ title: 'Silent Night for Mum' });
        await s.savePart({ id: crypto.randomUUID(), projectId: p.id, name: 'Lead', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false,
            offsetSeconds: 0, frames: 48000, sampleRate: 48000, peaks: Array.from({ length: 90 }, (_, i) => Math.abs(Math.sin(i / 4)) * 0.5), createdAt: Date.now() }, new Float32Array(48000));""")
    home(f, "member")
    f.wait(f"{BANDS} === 'projects songs'", what="projects second")
    # Still the course's progress (Hillel: "why don't I see course progress"): the unit, its path with the
    # next lesson marked, "Lesson n of m" and the course map; a wide screen keeps the whole card.
    f.wait("!!document.querySelector('[aria-current=step]')", what="the unit's path")
    lead = f.js("document.querySelector('section[aria-labelledby=lead-title]').innerText")
    assert t(f, "home.lead.map") in lead and " of " in lead, f"no unit progress in the course card: {lead!r}"
    full = t(f, "home.lead.continueLesson") in lead or f.js("!!document.querySelector('section[aria-labelledby=lead-title] [data-press-start]')")
    assert full == (f.size == "desktop"), f"the {'full' if full else 'short'} course card on {f.size}"
    # Hillel's case: partway through unit 1 (its first lesson sung), the same progress for unit 1.
    f.run("""const { learning } = await import('/js/stores/learning.js'); const unit = learning.course.units[0];
        learning.progress.lessons = { [unit.lessons[0].id]: { stars: 2 } };""")
    f.wait("document.querySelector('section[aria-labelledby=lead-title] [aria-current=step]')", what="unit 1's path")
    lead = f.js("document.querySelector('section[aria-labelledby=lead-title]').innerText")
    total = f.js("(await import('/js/stores/learning.js')).learning.course.units[0].lessons.length")
    assert t(f, "home.lead.lessonOf", {"place": 2, "total": total, "minutes": 0}).split(" · ")[0] in lead, f"not lesson 2 of unit 1: {lead!r}"
    f.wait_text("Silent Night for Mum")
    f.shot("project-maker")

    # 4. A beta singer on Plus: parts waiting come second; Plus by the name; no meter.
    home(f, "singerPlus")
    f.wait(f"{BANDS} === 'beta projects songs'", what="beta parts second")
    f.wait_text(t(f, "beta.status.retake"))
    # The Plus mark: beside the name from a tablet up; on a phone, in the account link's name.
    assert t(f, "home.plus") in f.js("document.querySelector('header').innerText + ' ' + document.querySelector('header a[href=\"/settings\"]').getAttribute('aria-label')"), "the Plus mark"
    assert t(f, "home.projects.used", {"used": 1, "limit": 3}) not in f.js("document.body.innerText"), "no meter on Plus"
    f.shot("beta-singer-plus")

    f.run("const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all) await s.deleteProject(p.id);")


if __name__ == "__main__":
    run("home", journey)
