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
            await l.finishLesson('1-match', {{ percent: 96, stars: 3 }}); await l.earn('part');
            // Past week one (a beginner's page folds the rest: checked below), with a range from five days before (the band).
            const earlier = new Date(Date.now() - 5 * 86400000); const iso = `${{earlier.getFullYear()}}-${{String(earlier.getMonth() + 1).padStart(2, '0')}}-${{String(earlier.getDate()).padStart(2, '0')}}`;
            await l.save?.({{ ...l.learning.progress }});
            const db = await import('/js/db.js'); const kept = await db.get('learning', 'progress');
            await db.put('learning', {{ ...kept, startedAt: Date.now() - 10 * 86400000, rangeHistory: [{{ date: iso, low: 57, high: 69, how: 'measured', at: earlier.getTime() }}, ...(kept.rangeHistory ?? [])] }}, 'progress');""")
        f.go(f"/voice?api=mock&as={who}", "document.querySelector('h1')")
        f.wait_text(t(f, "voice.suits.alto"))
        assert f.js(f"{LEARNING}.learning.progress.badges.threeStars"), "the three stars badge"
        if f.skin == "game":
            f.wait_text(t(f, "badges.firstPart.name"))
        else:
            # Serious mode (docs/skins-plan.md row 6): still earned, never listed.
            assert not f.js("!!document.querySelector('#badges-heading')"), "badges in serious mode"
        # History is free with an account, on any plan (Hillel, 2026-11-07).
        f.wait("document.querySelectorAll('svg[role=img] polyline').length >= 1", what="the history chart")
        f.wait_text(t(f, "voice.history.highlights"))
        assert t(f, "voice.history.withAccount") not in f.js("document.body.innerText"), "no account ask when signed in"
        # Progress you can see (Brief F): the range over time, a band from two days' ranges.
        f.wait("document.querySelector('[data-range-band] polygon')", what="the range over time")
        # Wider at both ends since five days before (A3 to A4, now G3 to C5): said in words above the band.
        f.wait("document.querySelector('[data-range-change]')", what="the range's change in words")
        f.shot(who)
    # A beginner (week one): the singing line first, the rest under All your measures, opened by a tap.
    f.run("const db = await import('/js/db.js'); const kept = await db.get('learning', 'progress'); await db.put('learning', { ...kept, startedAt: Date.now() - 86400000 }, 'progress');")
    f.go("/voice?api=mock&as=member", "document.querySelector('[data-all-measures] summary')")
    assert f.js("!document.querySelector('[data-all-measures]').open"), "All your measures open for a beginner"
    f.wait("document.querySelector('[data-history-chart]')", what="the singing line, in view")
    f.shot("beginner")
    f.run("document.querySelector('[data-all-measures] summary').click()")
    f.wait("document.querySelector('[data-all-measures]').open && document.querySelector('[data-voice-range]').offsetParent", what="the rest, opened")


if __name__ == "__main__":
    run("voice", journey)
