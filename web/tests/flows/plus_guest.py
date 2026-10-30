"""A guest tries a Plus course (Hillel, 2026-10-30: "Guests can try it"): signed out, with the
feature on for everyone (auth.config plusCourses), every live course opens at /learn/c/<slug>, the
site's links, including the six whose slug isn't their id, and at /learn/c/<id>; its first lesson
starts with no account, and the second asks for Plus.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

COURSES = [("pitch", "perfect-your-pitch"), ("breath", "breath-and-stamina"), ("ear", "ear-training"), ("vibrato", "vibrato"), ("counterpoint", "counterpoint"),
           ("improvising-harmony", "improvising-harmony"), ("barbershop", "barbershop"), ("folk", "folk-and-early-music"), ("yodel", "yodeling"), ("pop", "pop-and-country")]
STORE = "(await import('/js/stores/learning.js'))"


def journey(f):
    f.go("/?api=mock&as=signedOut&scenario=", "document.querySelector('#app h1, #app header')")
    for course_id, slug in COURSES:
        for path in ([slug, course_id] if slug != course_id else [slug]):
            f.go(f"/learn/c/{path}", "document.querySelector('[data-up-next]') || document.querySelector('main [role=status], main p')")
            f.wait("document.querySelector('[data-up-next]')", what=f"{path}: the course, signed out")
        first = f.js(f"(async () => {{ const s = {STORE}; await s.loadLearning(); await s.loadPlusCourses(); const {{ courseLessons }} = await import('/js/courses.js'); return courseLessons(s.learning.courses[{json.dumps(course_id)}]).map((l) => l.lesson.id).slice(0, 2); }})()")
        f.go(f"/learn/{first[0]}", "document.querySelector('main h1')")
        f.wait("document.querySelector('[data-go-on]')", what=f"{course_id}: lesson 1 starts with no account")
        if course_id == "pitch":
            f.shot("lesson-1")
            plus_only = f.js("(await import('/js/i18n.js')).t('courses.plusOnly')")
            f.go(f"/learn/{first[1]}", "document.querySelector('main')")
            f.wait_text(plus_only)
    f.go("/learn/c/perfect-your-pitch", "document.querySelector('[data-up-next]')")
    f.shot("course")


run("plus_guest", journey)
