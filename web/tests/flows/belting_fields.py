"""First steps to belting's three course fields (the courses lead's names, docs/courses/belting-voice-health.md), on
the Belting course with the fields added as its file loads: "keyRange": { "down": 4, "up": 0 } and "retries": 1 on
its first lesson's play step, and "dailyCap": { "minutes": 10 } on the course.
- The key: no Higher at all, Lower down to four steps and no further, remembered with the lesson.
- Retries: after the step's one take, no Sing it again and no more stars; the way on says "Good enough: move on".
- The daily cap: a lesson's time on screen joins the day's count (progress.courses.belting.capDay); with ten minutes
  spent today, the next Belting lesson rests ("That's today's belting.") while another course's opens as usual.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import every_theme  # noqa: E402
from help_shots import AUDIO, t  # noqa: E402

LESSON = "belting-1-call"
STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
# The fields, added to the course's file as it loads (the content doesn't carry them yet).
PATCH = """(() => {
    if (window.__beltingPatched) return; window.__beltingPatched = true;
    const plain = window.fetch.bind(window);
    window.fetch = async (input, init) => {
        const response = await plain(input, init);
        const url = typeof input === 'string' ? input : input?.url ?? '';
        if (url.includes('/course/plus/courses.json') && response.ok) {
            // The course is still "soon": opened here as it will be once it's live.
            const list = await response.clone().json();
            const items = Array.isArray(list) ? list : list.courses;
            items.forEach((c) => c.id === 'belting' && (c.status = 'live'));
            return new Response(JSON.stringify(list), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        if (!url.includes('/course/plus/belting/course.json') || !response.ok) return response;
        const course = await response.clone().json();
        course.dailyCap = { minutes: 10 };
        const lesson = course.units.flatMap((u) => u.lessons).find((l) => l.id === 'belting-1-call');
        const play = lesson.steps.find((s) => s.kind === 'play');
        Object.assign(play.exercise, { keyRange: { down: 4, up: 0 }, retries: 1, turn: false }); // turn: false, so loopback hears the guide as the take
        return new Response(JSON.stringify(course), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };
})();"""
KEY = "document.querySelector('[data-key-control]')"


def open_lesson(f, lesson):
    f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
    f.wait("document.querySelector('main h1')", what=f"{lesson} open")


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": PATCH})
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.mic_checked()
    f.run(PATCH)
    f.run(f"const L = {STORE}; await L.loadLearning(); const p = L.learning.progress; const keys = {{ ...(p.keys ?? {{}}) }}; delete keys['{LESSON}']; const courses = {{ ...(p.courses ?? {{}}) }}; delete courses.belting; Object.assign(p, {{ keys, courses }});")

    # The key: no Higher, Lower four steps and no further, remembered.
    open_lesson(f, LESSON)
    f.click_t("course.continue")
    f.wait(KEY, what="the play step's key control")
    higher, lower = t(f, "keyControl.higher"), t(f, "keyControl.lower")
    assert not f.js(f"!!{KEY}.querySelector('[aria-label=\"{higher}\"]')"), "Higher is shown on a lower-only lesson"
    for _ in range(5):
        f.run(f"const b = {KEY}.querySelector('[aria-label=\"{lower}\"]'); if (!b.disabled) b.click();")
        f.js("new Promise((r) => setTimeout(r, 150))")
    f.wait(f"{STORE}.learning.progress.keys?.['{LESSON}'] === -4", what="four steps lower, kept")
    assert f.js(f"{KEY}.querySelector('[aria-label=\"{lower}\"]').disabled"), "Lower goes past four steps"
    f.shot("lower-only")

    # Retries: one take, then Good enough. A voice into the loopback while the take records (find_note's way).
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.tap.sing")
    if f.js("!!document.querySelector('[data-headphones]')"):
        f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=30, what="the take recording")
    f.js("(await import('/js/creator-audio.js')).hearNotes([{ beat: 0, length: 16, pitch: 55 }], { tempo: 60 })")
    f.wait("document.querySelector('[data-take-result]')", timeout=120, what="the take's result")
    f.run(f"{AUDIO}.engine.setLoopback(false)")
    page = f.js("document.querySelector('main').textContent")
    assert t(f, "course.replay") not in page and t(f, "results.playAgainStars") not in page, "Sing it again after the step's one take"
    good = t(f, "course.goodEnough")
    f.wait(f"document.querySelector('[data-go-on]')?.textContent.includes({good!r})", what="Good enough: move on")
    f.shot("good-enough")

    # The daily cap: the lesson's time counts; with ten minutes spent today, the next lesson rests.
    f.run(f"{ROUTER}.navigate('/learn/c/belting')")
    f.wait(f"({STORE}.learning.progress.courses?.belting?.capDay?.seconds ?? 0) > 0", what="the lesson's time counted")
    f.run(f"const L = {STORE}; const {{ dayOf }} = await import('/js/course.js'); const p = L.learning.progress; p.courses = {{ ...p.courses, belting: {{ ...(p.courses?.belting ?? {{}}), capDay: {{ day: dayOf(), seconds: 600 }} }} }};")
    f.run(f"sessionStorage.removeItem('stackapella.lesson.{LESSON}')")  # opened fresh, not resumed mid-lesson (never checked then)
    open_lesson(f, LESSON)
    f.wait("document.querySelector('[data-cap-rest]')", what="the day's rest")
    f.wait_text(t(f, "course.dailyCap.title"))
    f.wait_text(t(f, "course.dailyCap.line"))
    f.shot("rested")
    if f.skin == "game":
        # Framed like every arcade card, at AA in all eight themes (the game agent's reskin).
        assert f.js("getComputedStyle(document.querySelector('[data-cap-rest]')).boxShadow") != "none", "the rest card's arcade frame"
        every_theme(f, "the day's rest")
    # Another course stays open.
    open_lesson(f, "vib-1-swing")
    assert not f.js("!!document.querySelector('[data-cap-rest]')"), "another course rests too"


if __name__ == "__main__":
    run("belting_fields", journey)
