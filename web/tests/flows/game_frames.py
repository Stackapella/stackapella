"""Frame strips of game mode's animations (docs/game-elements-plan.md, "each item lands with a frame
strip"): every running CSS animation on the page is paused and set to the same moment, then shot, so
a strip shows exactly what a person sees at 0, 150, 300... ms. Used by the game_* flows.
"""

SEEK = """for (const a of document.getAnimations()) { a.pause(); a.currentTime = %d; }"""


def frames(f, label, times):
    """Shots of the page with every animation at each of `times` (ms), labelled `label-<ms>`."""
    for ms in times:
        f.run(SEEK % ms)
        f.shot(f"{label}-{ms:04d}ms")
    # Then each to its end (a finite one), so a later shot isn't a restarted animation.
    f.run("for (const a of document.getAnimations()) { try { a.finish(); } catch { a.play(); } }")


def light(f):
    """Game light, the daytime arcade (the app allows light in game mode)."""
    f.run("window.stackapellaTheme.choose({ mode: 'light' })")
    f.wait("document.documentElement.dataset.theme.endsWith('-light') && document.documentElement.dataset.skin === 'game'", what="game light")


def dark(f):
    """Back to game dark."""
    f.run("window.stackapellaTheme.choose({ mode: 'dark' })")
    f.wait("document.documentElement.dataset.theme.endsWith('-dark')", what="game dark")
