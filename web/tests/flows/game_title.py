"""A lesson's title card (docs/game-elements-plan.md; the proposal's board 04): the first time a lesson
opens in game mode, "Stage 1-1" with the unit's and the lesson's names slides in over the lesson and
goes by itself in 1.2 s; a tap ends it sooner; it isn't shown again for that lesson, nor with the
Title cards switch off, nor in serious mode. Frames of the slide from a card held open.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, every_theme, frames, light  # noqa: E402
from help_shots import t  # noqa: E402

CARD = "document.querySelector('[data-game-title]')"

HELD = """const { createApp, h } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const { TitleCard } = await import('/js/game/title-card.js');
    document.querySelector('#title-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'title-test' });
    document.body.append(box);
    const app = createApp({ render: () => h(TitleCard, { unit: 2, lesson: 3, unitTitle: 'Steady notes', lessonTitle: 'Vowels', colour: '#ff4fa8', ms: 600000 }) });
    app.config.globalProperties.t = t;
    app.mount(box);"""


def journey(f):
    # A free account: from lesson 2 a guest is asked to make one (Hillel, 2026-11-06), and the titles run on past lesson 1.
    f.go("/learn?api=mock&as=member", "document.querySelector('#app')")
    f.run("localStorage.removeItem('stackapella.titlesSeen'); localStorage.removeItem('stackapella.gameEffects')")
    f.go("/learn/1-comfortable?api=mock&as=member", "document.querySelector('main h1')")
    if f.skin == "serious":
        assert f.js(f"!{CARD}"), "serious mode: no title card"
        return
    f.wait(CARD, what="the title card")
    words = f.js(f"{CARD}.innerText")
    assert t(f, "game.title.stage", {"unit": 1, "lesson": 1}).upper() in words.upper(), words
    f.wait(f"!{CARD}", timeout=5, what="gone by itself")
    assert f.js("JSON.parse(localStorage.getItem('stackapella.titlesSeen')).includes('1-comfortable')"), "kept as seen"
    # A repeat visit: no card.
    f.go("/learn/1-comfortable?api=mock&as=member", "document.querySelector('main h1')")
    f.run("await new Promise((r) => setTimeout(r, 400))")
    assert f.js(f"!{CARD}"), "not on a repeat visit"
    # A tap ends it at once.
    f.go("/learn/1-match?api=mock&as=member", "document.querySelector('main h1')")
    f.wait(CARD, what="the next lesson's card")
    f.run(f"{CARD}.click()")
    f.wait(f"!{CARD}", timeout=2, what="a tap ends it")
    # The Title cards switch off: none.
    f.run("(await import('/js/game/effects.js')).effects.titles = false")
    f.go("/learn/1-high-low?api=mock&as=member", "document.querySelector('main h1')")
    f.run("await new Promise((r) => setTimeout(r, 400))")
    assert f.js(f"!{CARD}"), "the switch turns it off"
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    # The slide, frame by frame, in game dark and light.
    f.run(HELD)
    f.wait(CARD, what="the held card")
    frames(f, "title", [0, 120, 240, 400, 700, 1080])
    light(f)
    frames(f, "title-light", [600])
    dark(f)
    every_theme(f, "the title card", settle="for (const a of document.getAnimations()) { a.pause(); a.currentTime = 600; }")
    # Shown, a tap is the card's (it ends it); leaving, a tap goes through to what shows under it. A slow phone's tap in
    # the fade, or on the card's last invisible moments, went to the card and was lost (the Slow 4G walks, 2026-11-13).
    at = lambda ms: f.js(f"(() => {{ for (const a of document.getAnimations()) {{ a.pause(); a.currentTime = {ms}; }} return getComputedStyle({CARD}).pointerEvents; }})()")
    assert at(600) == "auto", "the card shown takes its tap"
    assert at(1100) == "none" and at(1200) == "none", "the card leaving takes no tap"
    assert not f.js(f"{CARD}.contains(document.elementFromPoint(innerWidth / 2, innerHeight / 2))"), "a tap in the fade lands under the card"
    f.run("localStorage.removeItem('stackapella.titlesSeen')")


if __name__ == "__main__":
    run("game_title", journey)
