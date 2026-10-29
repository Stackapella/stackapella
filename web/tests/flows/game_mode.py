"""Game mode (docs/skins-plan.md; Hillel: "a section in the settings"): its own section in Settings,
not in the theme sheet; off is the serious skin (<html data-skin="serious">), set before
the page paints and kept after a reload; signed in, it goes to the account; on again, it's game.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SKIN = "document.documentElement.dataset.skin"
SWITCH = "document.querySelector('[data-game-mode] [role=switch]')"


def journey(f):
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-game-mode]')")
    start = f.js(SKIN)
    assert start == f.skin, f"the skin before paint: {start}, not {f.skin}"
    # Its own section in Settings (Hillel: "a section in the settings (not at the top)").
    assert f.js("!!document.querySelector('section#game [data-game-mode]')"), "Game mode isn't its own section"
    f.run(f"if ({SWITCH}.getAttribute('aria-checked') !== 'true') {SWITCH}.click()")
    f.wait(f"{SKIN} === 'game'", what="game mode on")
    f.js("new Promise((r) => setTimeout(r, 1000))")  # the toggle's turning-on moment plays out (0.9 s) before the next press
    f.run(f"{SWITCH}.click()")
    f.wait(f"{SKIN} === 'serious' && {SWITCH}.getAttribute('aria-checked') === 'false'", what="game mode off: the serious skin")
    f.wait("(await import('/js/stores/account.js')).account.me?.appearance?.skin === 'serious'", what="saved to the account")
    f.shot("serious")
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-game-mode]')")
    assert f.js(SKIN) == "serious", "the skin wasn't kept"
    # Not in the theme sheet (Hillel: "we don't need it in the theme modal"); on again from Settings.
    f.run("[...document.querySelectorAll('button')].find((b) => b.getAttribute('aria-haspopup') === 'dialog' && b.closest('header, section'))?.click()")
    f.wait("document.querySelector('[data-theme-panel]')", what="the theme sheet")
    assert not f.js("document.querySelector('[data-theme-panel] [data-game-mode]')"), "Game mode is in the theme sheet"
    f.press("Escape")
    f.run(f"{SWITCH}.click()")
    f.wait(f"{SKIN} === 'game'", what="game mode on again")
    # Game mode turns dark by itself (Hillel: "auto set to dark but show toggle to return to light"):
    # the theme sheet still offers light, light gives a light game page, and that choice is kept.
    f.run("window.stackapellaTheme.choose({ mode: 'light', gameMode: null })")
    f.run(f"{SWITCH}.click()")
    f.wait(f"{SKIN} === 'serious' && document.documentElement.dataset.theme.endsWith('-light')", what="serious mode in light")
    f.js("new Promise((r) => setTimeout(r, 1000))")  # the toggle's turn-on runs its course before another tap
    f.run(f"{SWITCH}.click()")
    f.wait(f"{SKIN} === 'game' && document.documentElement.dataset.theme.endsWith('-dark')", what="game mode on: dark by itself")
    f.run("[...document.querySelectorAll('button')].find((b) => b.getAttribute('aria-haspopup') === 'dialog' && b.closest('header, section'))?.click()")
    f.wait("document.querySelector('[data-theme-panel] [role=radio][data-value=light]')", what="light or dark offered in game mode")
    f.run("document.querySelector('[data-theme-panel] [role=radio][data-value=light]').click()")
    f.wait(f"{SKIN} === 'game' && document.documentElement.dataset.theme.endsWith('-light')", what="a light game page")
    f.shot("game-light")
    f.press("Escape")
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-game-mode]')")
    assert f.js("window.stackapellaTheme.chosen().gameMode") == "light", "light in game mode wasn't kept"
    if f.skin == "game":  # (a serious run's harness puts the serious skin back on every load)
        assert f.js("document.documentElement.dataset.theme.endsWith('-light')"), "light in game mode wasn't shown after a reload"


if __name__ == "__main__":
    run("game_mode", journey)
