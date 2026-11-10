"""Streak! (docs/game-feel-plan.md, slice 2; Hillel's yes, 2026-10-31): on the day's first lesson end, with 2 days or
more in a row, game mode bursts "Streak! 3 days" over the lesson end's streak chip, once, with the Pop-ups switch. A
later lesson the same day shows the chip alone; serious mode shows neither (the chip is game mode's).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import t  # noqa: E402
from game_frames import every_theme  # noqa: E402

POP = "document.querySelector('[data-game-popup=streak]')"


def mount(f, fresh):
    f.run(f"""const {{ createApp, h }} = await import('vue');
        const {{ t }} = await import('/js/i18n.js');
        const {{ LessonEnd }} = await import('/js/components/results.js');
        const {{ levelFor }} = await import('/js/course.js');
        const {{ levelWord, endLine }} = await import('/js/results.js');
        document.querySelector('#end-test')?.remove();
        const box = Object.assign(document.createElement('main'), {{ id: 'end-test', className: 'fixed inset-0 z-40 overflow-auto bg-ink p-6' }});
        document.body.append(box);
        const done = {{ stars: 2, xp: 20, level: levelFor(150), levelledUp: false, word: levelWord(2, 82), line: endLine({{ stars: 2, before: 0, scored: true }}), fresh: true, percent: 82, streak: 3, streakNew: {str(fresh).lower()}, place: 3, total: 5, unit: 2 }};
        const app = createApp({{ render: () => h(LessonEnd, {{ done, path: [], next: null }}) }});
        app.config.globalProperties.t = t;
        app.mount(box);""")
    f.wait("document.querySelector('[data-lesson-end]')", what="the lesson end")


def journey(f):
    f.go("/settings?api=mock&as=signedOut", "document.querySelector('#app main')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    mount(f, True)
    if f.skin == "serious":
        assert f.js(f"!{POP} && !document.querySelector('[data-end-streak]')"), "serious mode: no streak chip, no burst"
        return
    f.wait(POP, what="Streak!")
    assert t(f, "game.popup.streak", {"count": 3}) in f.js(f"{POP}.textContent"), "the real days in a row"
    f.run("for (const a of document.getAnimations()) a.currentTime = 900")
    f.shot("streak")
    mount(f, False)
    assert f.js(f"!{POP} && !!document.querySelector('[data-end-streak]')"), "a later lesson the same day: the chip alone"
    f.no_sideways("the lesson end")
    every_theme(f, "the lesson end")


if __name__ == "__main__":
    run("game_streak", journey)
