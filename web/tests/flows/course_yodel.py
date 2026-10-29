"""The Yodeling course (docs/courses/yodel.md), as staff: the course page, every built lesson
opening on its Why, the siren sung over loopback with a flipping voice played into it (its result
says where the voice flipped, and the flip is kept with the course), a step set from the singer's flip note saying so until the flip is
found, and the same step once a flip is kept. With OUT=<dir>, shots in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/yodel PORT=8300 tool/flows.sh course_yodel
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from course_folk import ROUTER, STORE, open_course  # noqa: E402
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"

LESSONS = {
    "yodel-1-flip": "Herders called across valleys with a voice that flips",
    "yodel-1-two": "Your voice has two sounds",
    "yodel-1-hear": "A yodel flips",
    "yodel-1-challenge": "Four hooks in rhythm",
    "yodel-2-yodelay": "Yodelers switch vowel at the flip",
    "yodel-2-down": "The way down is the flip singers often slam",
    "yodel-2-leaps": "The same flip across",
    "yodel-2-challenge": "Herders called across valleys",
    "yodel-3-chord": "A yodel sounds right when both its notes",
    "yodel-3-lope": "Cowboy yodels sway",
    "yodel-3-hear": "Over a chord, a yodel's top note",
    "yodel-3-challenge": "A cowboy song with a yodel tag",
    "yodel-4-roll": "A roll is several flips on one breath",
    "yodel-4-ladder": "The same roll at three speeds",
    "yodel-4-hear": "Fast, a roll either flips cleanly",
    "yodel-4-challenge": "A line of Red River Valley sung plainly",
}


def journey(f):
    open_course(f, "yodel")
    f.run(f"{ROUTER}.navigate('/learn/c/yodel')")
    f.wait("document.querySelector('[data-course-map]')", what="the map")
    f.wait_text("Find the flip")
    f.no_sideways("the Yodeling course page")
    picture(f, "yodel-course-page")
    for lesson, headline in LESSONS.items():
        if lesson in ("yodel-2-yodelay", "yodel-3-chord", "yodel-4-roll"):
            # A unit opens on two thirds of the unit before's stars, for staff too: finish it first.
            unit = int(lesson.split("-")[1])
            for done in [k for k in LESSONS if k.startswith(f"yodel-{unit - 1}-")]:
                f.js(f"{STORE}.finishLesson('{done}', {{ percent: 95, stars: 3 }})")
        f.run(f"{ROUTER}.navigate('/learn/{lesson}')")
        f.wait_text(headline)
        f.no_sideways(lesson)
        if lesson == "yodel-1-hear":
            f.click_t("course.continue")
            f.wait_text("Which one flipped?")
            f.no_sideways("Flip or slide?")
            picture(f, "yodel-flip-or-slide")
    # The siren: the lane on its path, sung over loopback (the mic hears the guide's smooth siren).
    f.mic_checked()
    open_course(f, "yodel")
    f.run(f"{ROUTER}.navigate('/learn/yodel-1-flip')")
    f.wait_text("Herders called across valleys")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-wave-lane] canvas')", what="the siren's lane")
    f.no_sideways("the siren")
    picture(f, "yodel-siren")
    f.click_t("wave.sing")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the siren")
    # A siren that flips, sung into the take: up with a jump from 6 to 8 above the note, and down
    # with one from 8 to 6, twice, after the lane's two-beat lead.
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
        const home = (await import('/js/stores/learning.js')).learning.progress.home ?? 57;
        const once = (at) => [
            {{ beat: at, length: 1.5, pitch: home - 3, bend: [[0, 0], [1, 800]] }},
            {{ beat: at + 1.5, length: 1.5, pitch: home + 9, bend: [[0, 0], [1, 300]] }},
            {{ beat: at + 3, length: 1.5, pitch: home + 12, bend: [[0, 0], [1, -300]] }},
            {{ beat: at + 4.5, length: 1.5, pitch: home + 5, bend: [[0, 0], [1, -800]] }},
        ];
        for (const id of [...engine.parts.keys()]) engine.removePart(id); // the lane's own smooth guide would drown it
        while ((engine.positionSeconds() ?? 0) < 2 - 0.12) await new Promise((r) => requestAnimationFrame(r));
        engine.preview(renderPart(once(0), {{ bpm: 90, sampleRate: engine.sampleRate }}), engine.sampleRate);""")
    f.wait("document.querySelector('[data-wave-result] [data-wave-line]')", timeout=60, what="the siren's result")
    # The siren's own words (the finder itself is tested on a rendered siren in style-scores.test.js;
    # a voice played into a live take is at the mercy of the page's timing): a flip found is kept.
    line = f.js("document.querySelector('[data-wave-line]').textContent")
    flip = f.js(f"{STORE}.learning.progress.courses?.yodel?.personal?.flip ?? null")
    seen = f.js(f"({STORE}.learning.progress.courses?.yodel?.personal?.flipSeen ?? []).length")
    if "twice in the same place" in line:
        assert flip is not None and 3 <= flip <= 15, f"the flip kept with the course: {flip}"
    elif "flipped around" in line:
        assert flip is None and seen >= 1, f"one siren's flip, waiting for a second: flip {flip}, seen {seen}"
    else:
        assert "No flip yet" in line and flip is None, f"the siren's line: {line}, flip {flip}"
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    picture(f, "yodel-siren-result")
    # A step set from the flip note says so until the flip is found, and not once it's kept.
    f.run("await (await import('/js/stores/learning.js')).setCoursePersonal('yodel', { flip: undefined })")  # as before the siren
    f.run(f"{ROUTER}.navigate('/learn/yodel-1-two')")
    f.wait_text("Your voice has two sounds")
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-needs-flip]')", what="the flip-first notice")
    f.wait_text(t(f, "courses.flipFirst"))
    picture(f, "yodel-needs-flip")
    f.run(f"await (await import('/js/stores/learning.js')).setCoursePersonal('yodel', {{ flip: 7 }})")
    f.wait("!document.querySelector('[data-needs-flip]')", what="the notice gone once the flip is kept")
    compress()


if __name__ == "__main__":
    run("course_yodel", journey)
