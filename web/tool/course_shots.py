"""The course pages' screenshots (/courses/<slug>, server/resources/views/site/course.blade.php): two
real screens of each Plus course, phone size, in Warm light and Stage dark, from the course's own flow
(tests/flows/course_<flow>.py). They're copied into icons/help/ as course-<slug>--1.png and
course-<slug>--1-dark.png (and --2), with the same in webp, where the site's appshot finds them. The
alt text is the page's words (lang/en/site.php, courses.pages.<slug>.shots, in the same order).

    python3 -I tool/course_shots.py                   # copy from ~/Code/shots-review/<folder>/
    RUN=1 PORT=8440 python3 -I tool/course_shots.py   # run each course's flow first, then copy
    ONLY=vibrato,yodeling python3 -I tool/course_shots.py

The flows already write compressed png and webp (as help_shots does), so they're copied as they are.
"""
import os
import shutil
import subprocess
import sys
from pathlib import Path

WEB = Path(__file__).resolve().parents[1]
HELP = WEB / "icons" / "help"
REVIEW = Path(os.environ.get("REVIEW", "~/Code/shots-review")).expanduser()
ONLY = [s for s in os.environ.get("ONLY", "").split(",") if s]

# slug: (the course's flow, its shots folder under REVIEW, the two screens' names there)
COURSES = {
    "vibrato": ("plus_courses", "plus-courses", ["plus-course-lesson-1", "plus-course-wave-result"]),
    "perfect-your-pitch": ("course_pitch", "pitch", ["pitch-lesson-1", "pitch-bullseye-result"]),
    "breath-and-stamina": ("course_breath", "breath", ["breath-lesson-1", "breath-bar-result"]),
    "ear-training": ("course_ear", "ear", ["ear-lesson-1", "ear-bullseye-result"]),
    "counterpoint": ("course_counterpoint", "counterpoint", ["counterpoint-round-singing", "counterpoint-choices-lane"]),
    "improvising-harmony": ("course_improvising", "improvising", ["improvising-lesson-1", "improvising-part-lane"]),
    "folk-and-early-music": ("course_folk", "folk", ["folk-lesson-1-lane", "folk-ring-choose"]),
    "yodeling": ("course_yodel", "yodel", ["yodel-siren-result", "yodel-flip-or-slide"]),
    "barbershop": ("course_barbershop", "barbershop", ["barbershop-lesson-1", "barbershop-ring-result"]),
    "pop-and-country": ("course_pop", "pop", ["pop-lesson-1", "pop-colour-selfcheck"]),
}


def run(flow, folder):
    """Runs one course's flow with its shots going to REVIEW/<folder>."""
    env = {**os.environ, "OUT": str(REVIEW / folder)}
    result = subprocess.run(["sh", str(WEB / "tool" / "flows.sh"), flow], cwd=WEB, env=env)
    if result.returncode != 0:
        sys.exit(f"The {flow} flow failed, so its shots weren't copied")


def main():
    missing = []
    for slug, (flow, folder, bases) in COURSES.items():
        if ONLY and slug not in ONLY:
            continue
        if os.environ.get("RUN"):
            run(flow, folder)
        for n, base in enumerate(bases, start=1):
            for theme in ("", "-dark"):
                for ext in ("png", "webp"):
                    source = REVIEW / folder / f"{base}{theme}-phone.{ext}"
                    if not source.is_file():
                        missing.append(str(source))
                        continue
                    shutil.copyfile(source, HELP / f"course-{slug}--{n}{theme}.{ext}")
        print(f"ok   {slug}")
    if missing:
        sys.exit("Missing, so those pages show no screen yet:\n  " + "\n  ".join(missing))


if __name__ == "__main__":
    main()
