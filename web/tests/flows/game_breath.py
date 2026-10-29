"""The super meter (docs/game-elements-plan.md, idea 16; agreed with courses-skills): in game mode, a
Breath and stamina hold with a goal shows three segments to it and "Goal" over its mark, not clipped by
the bar; serious mode keeps today's bar. The recipe to reach a hold with a goal is courses-skills'.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from course_breath import ROUTER, STORE, open_breath  # noqa: E402
from game_frames import dark, light  # noqa: E402

LABEL = "document.querySelector('[data-breath-goal] .ge-goal')"


def journey(f):
    open_breath(f)
    f.run(f"{STORE}.learning.progress.courses = {{ ...({STORE}.learning.progress.courses ?? {{}}), breath: {{ lessons: {{ 'br-1-long': {{ stars: 3, bestPercent: 90, at: 1 }} }}, personal: {{ baseline: 8 }} }} }}")
    f.run("sessionStorage.setItem('stackapella.lesson.br-1-challenge', '1')")
    f.run(f"{ROUTER}.navigate('/learn/br-1-challenge')")
    f.wait("document.querySelector('[data-breath-goal]') || document.querySelector('main h1')", what="the lesson")
    if not f.js("!!document.querySelector('[data-breath-goal]')"):
        f.click(f.js("(await import('/js/i18n.js')).t('course.continue')"))  # on to the hold
    f.wait("document.querySelector('[data-breath-goal]')", what="the goal mark")
    if f.skin == "serious":
        assert f.js(f"!{LABEL}"), "serious mode: today's bar"
        return
    f.wait(LABEL, what="Goal over the mark")
    # Not clipped: the label sits wholly above the bar, inside the page.
    box = f.js(f"(() => {{ const l = {LABEL}.getBoundingClientRect(); const b = document.querySelector('[data-breath-bar]').getBoundingClientRect(); return {{ above: l.bottom <= b.top + 1, left: l.left >= 0, right: l.right <= innerWidth, overflow: getComputedStyle(document.querySelector('[data-breath-bar]')).overflow }}; }})()")
    assert box["above"] and box["left"] and box["right"] and box["overflow"] == "visible", box
    f.run("document.querySelector('[data-breath-bar]').scrollIntoView({ block: 'center' })")
    f.shot("meter-goal")
    light(f)
    f.shot("meter-goal-light")
    dark(f)


if __name__ == "__main__":
    run("game_breath", journey)
