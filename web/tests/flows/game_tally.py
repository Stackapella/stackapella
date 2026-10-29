"""Stage clear as a reskin of the lesson end (docs/game-elements-plan.md; Hillel: "reskinned these
elements"): in game mode the result's own stars drop one by one, its +XP chip counts up to the real XP,
"Lesson 3 of 5 done" reads "Stage 2-3 clear", the word lands in the display face and the level bar is
a power bar that fills; Next works from the first frame and a tap lands everything; nothing is added.
Celebrations off and serious mode give today's lesson end.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, frames, light  # noqa: E402
from help_shots import t  # noqa: E402

END = "document.querySelector('[data-lesson-end]')"
PLACE = "document.querySelector('[data-end-place]').innerText"
XP = "document.querySelector('[data-end-xp]').innerText"


def mount(f):
    f.run("""const { createApp, h } = await import('vue');
        const { t } = await import('/js/i18n.js');
        const { LessonEnd } = await import('/js/components/results.js');
        const { levelFor } = await import('/js/course.js');
        const { levelWord, endLine } = await import('/js/results.js');
        document.querySelector('#end-test')?.remove();
        const box = Object.assign(document.createElement('main'), { id: 'end-test', className: 'fixed inset-0 z-40 overflow-auto bg-ink p-6' });
        document.body.append(box);
        window.nexted = 0;
        const done = { stars: 2, xp: 20, level: levelFor(150), levelledUp: false, word: levelWord(2, 82), line: endLine({ stars: 2, before: 0, scored: true }), fresh: true, percent: 82, streak: 1, place: 3, total: 5, unit: 2 };
        const app = createApp({ render: () => h(LessonEnd, { done, path: [], next: { lesson: { id: '2-next', title: 'Long notes' }, minutes: 4, line: null }, onNext: () => window.nexted++ }) });
        app.config.globalProperties.t = t;
        app.mount(box);""")
    f.wait(END, what="the lesson end")


def journey(f):
    f.go("/settings?api=mock&as=signedOut", "document.querySelector('#game-heading')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    mount(f)
    lesson_of = t(f, "results.lessonOf", {"place": 3, "total": 5})
    if f.skin == "serious":
        assert f.js(PLACE) == lesson_of, "serious mode: today's lesson end"
        return
    assert f.js("!document.querySelector('[data-game-tally]')"), "nothing added"
    assert f.js(PLACE).upper() == t(f, "game.tally.clear", {"unit": 2, "lesson": 3}).upper(), f.js(PLACE)
    f.click(t(f, "course.next"))
    assert f.js("window.nexted") == 1, "Next works from the first frame"
    mount(f)
    frames(f, "end", [0, 300, 600, 900, 1300, 1800])
    f.wait(f"{XP}.includes('20')", timeout=5, what="the XP counted up to the real number")
    f.shot("end")
    light(f)
    f.shot("end-light")
    dark(f)
    # A tap lands it at once.
    mount(f)
    f.run("document.querySelector('[data-lesson-end]').dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))")
    f.wait(f"{END}.classList.contains('is-landed') && {XP}.includes('20')", timeout=1, what="a tap lands every count")
    # Celebrations off: today's lesson end.
    f.run("(await import('/js/game/effects.js')).effects.celebrations = false")
    mount(f)
    assert f.js(PLACE) == lesson_of, "Celebrations off: today's words"
    f.run("localStorage.removeItem('stackapella.gameEffects')")


if __name__ == "__main__":
    run("game_tally", journey)
