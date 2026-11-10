"""Serious mode's bubbles (docs/bubble-circles-plan.md), shot for Hillel's review: the account chip ("HC"), Home's
lesson path and the course map, with two lessons done, in Warm light and Stage dark at both sizes:

    OUT=~/Code/shots-review/bubbles OUT_DARK=1 FLOW_SKINS=serious PORT=8410 tool/flows.sh bubble_shots
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, compress, home_page, progress, shoot  # noqa: E402


def journey(f):
    assert OUT, "the shots need OUT=<dir>"
    home_page(f, "member")
    f.run("localStorage.setItem('stackapella.asks', JSON.stringify({ stopped: ['install'] }));")
    ids = f.js("(async () => { const { learning, loadLearning } = await import('/js/stores/learning.js'); await loadLearning(); return learning.course.units[0].lessons.map((l) => l.id); })()")
    progress(f, {ids[0]: {"stars": 3, "bestPercent": 95}, ids[1]: {"stars": 2, "bestPercent": 80}}, 120, days=2)
    home_page(f, "member")
    f.wait("document.querySelector('[data-lesson-path]')", what="Home's lesson path")
    shoot(f, "bubble-home-top", scroll="window.scrollTo(0, 0)")
    shoot(f, "bubble-home-path", scroll="document.querySelector('[data-lesson-path]').scrollIntoView({ block: 'center' })")
    f.go("/learn?api=mock&as=member", "document.querySelector('[data-course-map] [aria-current=step]')")
    shoot(f, "bubble-map", scroll="document.querySelector('[data-course-map] [aria-current=step]').scrollIntoView({ block: 'center' })")
    compress()


if __name__ == "__main__":
    run("bubble_shots", journey)
