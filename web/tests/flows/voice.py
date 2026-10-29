"""Your voice (M5): after some singing (a range, a lesson with three stars, a part recorded), the page
shows the level and the weekly goal, the range on a keyboard with the voice it suits, the badges
earned and the ones to come; with Plus the history from the account, without it a marked sample.
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
    for who in ("member", "plus"):
        f.go(f"/?api=mock&as={who}", "document.querySelector('#app header')")
        f.run(f"""const l = {LEARNING}; await l.loadLearning(); await l.setRange(55, 72); await l.setHome(62);
            await l.finishLesson('1-match', {{ percent: 96, stars: 3 }}); await l.earn('part');""")
        f.go(f"/voice?api=mock&as={who}", "document.querySelector('h1')")
        f.wait_text(t(f, "voice.suits.alto"))
        assert f.js(f"{LEARNING}.learning.progress.badges.threeStars"), "the three stars badge"
        if f.skin == "game":
            f.wait_text(t(f, "badges.firstPart.name"))
        else:
            # Serious mode (docs/skins-plan.md row 6): still earned, never listed.
            assert not f.js("!!document.querySelector('#badges-heading')"), "badges in serious mode"
        if who == "plus":
            f.wait("document.querySelectorAll('svg[role=img] polyline').length >= 1", what="the history chart")
            f.wait_text(t(f, "voice.history.highlights"))
        else:
            f.wait_text(t(f, "voice.history.withPlus"))
        f.shot(who)


if __name__ == "__main__":
    run("voice", journey)
