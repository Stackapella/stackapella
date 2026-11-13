"""Four of the finish-line audit's screen items (docs/web-finish-audit-2026-11-13.md), fixed by frontend2:
- Ideas: each part wears its song colour (soprano, alto, tenor, bass as a song's first four), and a card says its way in.
- Your voice: with no range yet the keyboard takes the width, with no empty column at its left; the history has a scale
  (0, 50 and 100%), the first and last dates and a dot for each score.
- Learn: the weekly goal is three numbers under "Days a week" that never wrap, and the reminder is a real button; a
  course's units count lessons in both skins (game mode adds its stars after).
- A course with its own safety note says the care line once, the teacher line in the note's box.
Against the mock, in the skin it runs in.
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
    # Ideas: the dots in a song's part colours, in order.
    f.go("/ideas?api=mock&as=member", "document.querySelector('[data-idea]')")
    dots = f.js("[...document.querySelector('[data-idea]').querySelectorAll('li > span[aria-hidden]')].map((d) => getComputedStyle(d).borderColor)")
    want = f.js("(async () => { const { PART_COLORS } = await import('/js/theme.js'); const { paint } = await import('/js/colors.js'); const probe = document.createElement('i'); document.body.append(probe); const out = PART_COLORS.slice(0, 4).map((c) => { probe.style.color = paint(c); return getComputedStyle(probe).color; }); probe.remove(); return out; })()")
    assert dots == want[: len(dots)], f"Ideas' part colours {dots}, a song's {want}"
    assert f.js("!!document.querySelector('[data-idea-way-in]')"), "the card says its way in"
    f.shot("ideas")

    # Your voice with no range: the keys take the width.
    f.go("/voice?api=mock&as=member", "document.querySelector('[data-history-chart]')")
    f.run(f"const l = {LEARNING}; await l.loadLearning();")
    if not f.js(f"!!{LEARNING}.learning.progress.range"):
        gap = f.js("(() => { const svg = document.querySelector('svg[role=img][viewBox^=\"0 0 \"][aria-label]'); const box = svg.parentElement.parentElement.getBoundingClientRect(); return Math.round(svg.getBoundingClientRect().left - box.left); })()")
        assert gap <= 2, f"the keyboard starts {gap} px in, with no range"
    # One check, as the chart redraws when the takes load.
    scale = "(() => { const l = [...document.querySelectorAll('[data-history-chart] text')].map((x) => x.textContent.trim()); return ['0%', '50%', '100%'].every((p) => l.includes(p)); })()"
    f.wait(scale, what="the history's scale (0, 50 and 100%)")
    assert f.js("document.querySelectorAll('[data-history-chart] circle').length") >= 1, "a dot for each score"
    f.run("document.querySelector('[data-history-chart]').scrollIntoView({ block: 'center' })")
    f.shot("voice")

    # Learn: the goal as numbers, one line each; the reminder a button; a unit counts lessons.
    f.go("/learn?api=mock&as=member", "document.querySelector('[role=radiogroup] .seg-option')")
    goals = f.js("[...document.querySelectorAll('[role=radiogroup] .seg-option')].map((b) => [b.innerText.trim(), b.getAttribute('aria-label'), b.getClientRects().length && Math.round(b.getBoundingClientRect().height)])")
    assert [g[0] for g in goals] == ["2", "3", "5"], goals
    assert all(g[1] for g in goals), f"each goal named in full: {goals}"
    assert len({g[2] for g in goals}) == 1, f"the goals on one line each: {goals}"
    assert t(f, "course.hub.goalWeek") in f.js("document.body.innerText"), "Days a week"
    f.wait("document.querySelector('[data-remind-days]')", what="the reminder (once the account is in)")
    remind = f.js("document.querySelector('[data-remind-days]').className")
    assert "btn-secondary" in remind, f"the reminder is a button: {remind}"
    f.run("document.querySelector('[data-remind-days]').scrollIntoView({ block: 'center' })")
    f.shot("learn")

    # A Plus course: the unit counts lessons; the care line once (the mock opens Vibrato to an admin, as the audit's walk).
    f.go("/learn/c/vibrato?api=mock&as=admin", "document.querySelector('[data-course-safety]')")
    unit = f.js("document.body.innerText")
    assert t(f, "course.mapLessons", {"done": 0, "total": 5}) in unit, "the unit counts lessons"
    assert unit.count(t(f, "courses.teacher")) == 1, "the teacher line once"
    assert t(f, "courses.notTeacher") not in unit, "the general care line not repeated beside the course's own"
    f.run("document.querySelector('[data-course-safety]').scrollIntoView({ block: 'center' })")
    f.no_sideways("a Plus course")
    f.shot("course")


run("finish_polish", journey)
