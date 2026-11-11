"""A unit's stage clear (docs/game-feel-plan.md, slice 2; Hillel's yes, 2026-10-31): when a unit's last lesson is first
done, game mode's title card comes back as "Stage 2 clear!" with the unit's name, and "Level 5!" when a level came with
it, over the lesson end; a tap ends it at once. learn.js shows it only through the Title cards switch (effect
'titles'), so serious mode and the switch off never see it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import every_theme, frames  # noqa: E402
from help_shots import t  # noqa: E402

CARD = "document.querySelector('[data-game-clear]')"


def mount(f, level):
    f.run(f"""const {{ createApp, h }} = await import('vue');
        const {{ t }} = await import('/js/i18n.js');
        const {{ TitleCard, CLEAR_MS }} = await import('/js/game/title-card.js');
        document.querySelector('#clear-test')?.remove();
        const box = Object.assign(document.createElement('div'), {{ id: 'clear-test' }});
        document.body.append(box);
        window.cleared = 0;
        const app = createApp({{ render: () => h(TitleCard, {{ unit: 2, lesson: 5, unitTitle: 'Breathing', lessonTitle: 'Long notes', colour: 'var(--color-part-3)', clear: true, level: {level or 'null'}, ms: CLEAR_MS, onDone: () => window.cleared++ }}) }});
        app.config.globalProperties.t = t;
        app.mount(box);""")
    f.wait(CARD, what="the stage clear")
    # The card is in the page before its words are (a gate read "Breathing2" mid-render, 2026-11-11): wait for them.
    clear = t(f, "game.title.clear", {"unit": 2})
    f.wait(f"{CARD}.textContent.includes({json.dumps(clear)})", what="the stage clear's words")


def journey(f):
    f.go("/settings?api=mock&as=signedOut", "document.querySelector('#app main')")
    mount(f, 5)
    text = f.js(f"{CARD}.textContent")
    assert t(f, "game.title.clear", {"unit": 2}) in text, text
    assert t(f, "game.title.levelUp", {"level": 5}) in text, "the level that came with it"
    assert "Long notes" not in text, "a stage clear names the unit, not the lesson"
    frames(f, "stage-clear", [0, 300, 900])
    f.shot("stage-clear")
    every_theme(f, "the stage clear card")
    f.run(f"{CARD}.click()")
    f.wait("window.cleared === 1 && !document.querySelector('[data-game-clear]') || window.cleared === 1", what="a tap ends it")
    mount(f, None)
    assert f.js("!document.querySelector('[data-game-level-up]')"), "no level line without a level"
    f.no_sideways("the stage clear")


if __name__ == "__main__":
    run("game_stage_clear", journey)
