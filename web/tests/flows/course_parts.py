"""Your part (js/course-parts.js; docs/courses/choir-part-sections-plan.md, "Engine"), ahead of the content:
Learn your part for rehearsal opened as staff with `parts` added in memory (its song steps on "yours", one
tenor card), as courses will write it. The course page offers the four parts in score order under its
title; picking Tenor keeps it with the course's progress (personal.part, the hook course_choir sets);
lesson 1's first card offers the same choice; and the lesson's song steps, its words and its kept take
are read for the tenor. A course without parts shows no pick (plus_courses, course_choir).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from course_folk import ROUTER, STORE, open_course  # noqa: E402
from course_choir import COURSE, serve_song  # noqa: E402

PICK = "document.querySelector('[data-course-part]')"


def with_parts(f):
    """The course as courses will write it: `parts`, "yours" for the alto, and a tenor card on lesson 1."""
    f.run(f"""
        const store = {STORE};
        const course = store.learning.courses['{COURSE}'];
        const yours = (spec) => (spec?.part === 'alto' ? {{ ...spec, part: 'yours' }} : spec);
        for (const unit of course.units) for (const lesson of unit.lessons) lesson.steps = lesson.steps.map((step) => ({{
            ...step,
            ...(step.part === 'alto' ? {{ part: 'yours' }} : {{}}),
            ...(step.listen?.song ? {{ listen: {{ ...step.listen, song: yours(step.listen.song) }} }} : {{}}),
            ...(step.exercise?.song ? {{ exercise: {{ ...step.exercise, song: yours(step.exercise.song) }} }} : {{}}),
        }}));
        course.units[0].lessons[0].steps[1].byPart = {{ tenor: {{ headline: {{ en: 'Now the tenor on its own.' }}, steps: [{{ title: {{ en: 'Bethlehem' }}, detail: {{ en: 'A long held note' }} }}] }} }};
        course.parts = {{ template: 'o-come-all-ye-faithful', choose: ['soprano', 'alto', 'tenor', 'bass'], default: 'alto' }};
        store.learning.courses = {{ ...store.learning.courses, '{COURSE}': {{ ...course }} }};
    """)


def journey(f):
    open_course(f, COURSE)
    serve_song(f)
    with_parts(f)
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait(f"{PICK} && document.querySelector('[data-up-next]')", what="Your part under the title, above Up next")
    labels = f.js(f"[...{PICK}.querySelectorAll('[role=radio]')].map((b) => b.textContent.trim())")
    assert labels == ["Soprano", "Alto", "Tenor", "Bass"], f"the parts in score order: {labels}"
    suggested = f.js(f"{PICK}.dataset.part")
    assert suggested in ("soprano", "alto", "tenor", "bass"), f"a part chosen before any pick: {suggested}"
    f.no_sideways("the course page with Your part")
    f.shot("course-page")
    # Pick the tenor: kept with the course's progress, where course_choir's hook sets it.
    f.run(f"[...{PICK}.querySelectorAll('[role=radio]')].find((b) => b.textContent.trim() === 'Tenor').click()")
    f.wait(f"{PICK}.dataset.part === 'tenor' && {STORE}.learning.progress.courses['{COURSE}']?.personal?.part === 'tenor'", what="the tenor picked and kept")
    assert f.js(f"{PICK}.querySelector('[aria-checked=true]').textContent.trim()") == "Tenor", "the tenor checked"
    # Lesson 1's first card offers the same choice.
    f.run(f"sessionStorage.setItem('stackapella.lesson.chp-1-hear', '0')")
    f.run(f"{ROUTER}.navigate('/learn/chp-1-hear')")
    f.wait(f"{PICK}?.dataset.part === 'tenor'", what="Your part on lesson 1's first card")
    f.no_sideways("lesson 1's first card")
    f.shot("lesson-1")
    # Its song steps, its words and its kept take, read for the tenor.
    lesson = f"{STORE}.findLesson('chp-1-hear')"
    assert f.js(f"{lesson}.part") == "tenor", "the lesson read for the tenor"
    assert f.js(f"{lesson}.lesson.steps[1].listen.song.part") == "tenor", "a learn card's example sings the tenor"
    assert f.js(f"{lesson}.lesson.steps[2].exercise.song.part") == "tenor", "Find the notes on the tenor"
    assert f.js(f"{lesson}.lesson.steps[3].exercise.keep") == "choir-part.first:tenor", "the tenor's own first take"
    f.run("sessionStorage.setItem('stackapella.lesson.chp-1-hear', '1')")
    f.run(f"{ROUTER}.navigate('/learn/c/{COURSE}')")
    f.wait("document.querySelector('[data-course-map]')", what="the course page between steps")
    f.run(f"{ROUTER}.navigate('/learn/chp-1-hear')")
    f.wait_text("Now the tenor on its own.")
    assert f.js("document.body.innerText.includes('Bethlehem')"), "the tenor's card, not the alto's"
    assert not f.js(f"!!{PICK}"), "the pick only on the first card"
    # Back to the alto: the step's own words again.
    f.run(f"await {STORE}.setCoursePersonal('{COURSE}', {{ part: 'alto' }})")
    f.wait("document.body.innerText.includes('Now the alto on its own.')", what="the alto's words again")


if __name__ == "__main__":
    run("course_parts", journey)
