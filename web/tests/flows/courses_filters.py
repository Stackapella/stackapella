"""The courses page's filters (Hillel's design, 2026-11-13; site/courses.blade.php, js/public/courses-filter.js): a group's
chip shows only that group's cards and hides the others, pressed and remembered for the next visit; a search narrows the
cards as you type, and one that matches nothing says so with a way back to every course; every chip is a 44 px button
and nothing scrolls sideways. Needs the real server (a site page)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

STATE = """JSON.stringify({
  pressed: [...document.querySelectorAll('[data-filter][aria-pressed=true]')].map((c) => c.dataset.filter),
  groups: [...document.querySelectorAll('[data-course-group]')].filter((g) => !g.hidden).map((g) => g.dataset.courseGroup),
  cards: [...document.querySelectorAll('[data-course-card]')].filter((c) => !c.hidden).length,
  empty: !document.querySelector('[data-courses-empty]').hidden,
  emptyLine: document.querySelector('[data-courses-empty] [data-words]').textContent,
  chipHeights: [...document.querySelectorAll('[data-filter]')].map((c) => Math.round(c.getBoundingClientRect().height)),
})"""


def journey(f):
    f.go("/courses", "document.querySelector('[data-courses-filter]')")
    f.wait("document.fonts.status === 'loaded'", what="fonts")
    f.run("try { localStorage.removeItem('stackapella.coursesChip'); } catch {}")
    f.go("/courses", "document.querySelector('[data-courses-filter]')")
    start = json.loads(f.js(STATE))
    assert start["pressed"] == ["all"] and len(start["groups"]) >= 3, f"All, every group: {start}"
    assert min(start["chipHeights"]) >= 44, f"a chip under 44 px: {start['chipHeights']}"
    f.no_sideways("/courses")
    every = start["cards"]

    # A group's chip: only its cards, the others' groups hidden; remembered for the next visit.
    f.run("document.querySelector('[data-filter=harmony]').click()")
    harmony = json.loads(f.js(STATE))
    assert harmony["pressed"] == ["harmony"] and harmony["groups"] == ["harmony"], f"Harmony alone: {harmony}"
    assert 0 < harmony["cards"] < every
    f.shot("harmony")
    f.go("/courses", "document.querySelector('[data-courses-filter]')")
    assert json.loads(f.js(STATE))["pressed"] == ["harmony"], "the chip is remembered"
    f.run("document.querySelector('[data-filter=all]').click()")

    # A search narrows as you type; one that matches nothing says so, and Show all courses brings every course back.
    f.run("const s = document.querySelector('[data-courses-search]'); s.value = 'vibrato'; s.dispatchEvent(new Event('input'));")
    found = json.loads(f.js(STATE))
    assert found["cards"] == 1 and not found["empty"], f"vibrato finds one course: {found}"
    f.run("const s = document.querySelector('[data-courses-search]'); s.value = 'zzzq'; s.dispatchEvent(new Event('input'));")
    none = json.loads(f.js(STATE))
    assert none["cards"] == 0 and none["empty"] and "zzzq" in none["emptyLine"], f"the empty state: {none}"
    f.shot("empty")
    f.run("document.querySelector('[data-courses-reset]').click()")
    back = json.loads(f.js(STATE))
    assert back["cards"] == every and back["pressed"] == ["all"] and not back["empty"], f"every course again: {back}"
    assert f.js("document.querySelector('[data-courses-search]').value") == ""


run("courses_filters", journey, sizes=("narrow", "phone", "desktop"))
