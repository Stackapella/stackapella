"""Frame strips of game mode's animations (docs/game-elements-plan.md, "each item lands with a frame
strip"): every running CSS animation on the page is paused and set to the same moment, then shot, so
a strip shows exactly what a person sees at 0, 150, 300... ms. Used by the game_* flows. Also every_theme: a screen
at AA in each of the eight colour themes (each has its own arcade).
"""
import json
import time
from pathlib import Path

from app_audit import APP_CONTRAST

# The pixel face's characters (web/tool/pixel_font.py writes them), and the symbols Press Start 2P never had, which
# fall back to the next face in the stack as they always did: reported by name, never a failure.
PIXEL = json.loads((Path(__file__).parent / "pixel_glyphs.json").read_text())["codepoints"]
NEVER_HAD = "♩♪♫♬♭♮♯▸▾▴◂✓✗✕✦✶·↗−⋯⋮●○"


def cjk(ch):
    """Kana, kanji and hangul (a word shown in its own script, like the Other languages course's とうきょう): no pixel
    face has them, so they fall back to the device's own face, which is what they should do."""
    return "\u3040" <= ch <= "\u30ff" or "\u3400" <= ch <= "\u9fff" or "\uac00" <= ch <= "\ud7af" or "\uff00" <= ch <= "\uffef"


PIXEL_FACE = """((have) => {
    const has = new Set(have);
    const missing = new Set();
    let used = false;
    const walk = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let node = walk.nextNode(); node; node = walk.nextNode()) {
        const el = node.parentElement;
        if (!el || !node.textContent.trim() || !el.getClientRects().length) continue;
        if (!getComputedStyle(el).fontFamily.includes('Stackapella Pixel')) continue;
        used = true;
        for (const ch of node.textContent) if (ch.trim() && !has.has(ch.codePointAt(0))) missing.add(ch);
    }
    return { used, loaded: !used || document.fonts.check('16px "Stackapella Pixel"'), missing: [...missing].join('') };
})"""


def pixel_face(f, what):
    """The fallback check: where game mode draws in the pixel face, the face has loaded and has every character drawn
    (anything Press Start 2P never had, like ♯, falls back as it always did and is only reported)."""
    f.run("await document.fonts.ready")
    found = f.js(f"{PIXEL_FACE}({json.dumps(PIXEL)})")
    assert found["loaded"], f"{what}: the pixel face never loaded"
    missing = "".join(ch for ch in found["missing"] if ch not in NEVER_HAD and not cjk(ch))
    assert not missing, f"{what}: drawn in the pixel face but not in it: {missing!r}"

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


THEMES = [(c, m) for m in ("dark", "light") for c in ("stage", "warm", "midnight", "graphite")]


def every_theme(f, what, settle="for (const a of document.getAnimations()) { try { a.finish(); } catch {} }"):
    """On a desktop: the screen at AA in each of the eight colour themes (the 8-theme check, 2026-11-02), then back to
    the look the flow had (its stored choice and theme cookie as they were, so a later check that sets its own look
    isn't outvoted by a newer cookie). `settle` puts the screen's animations where a person reads it (to their end,
    by default)."""
    if f.size != "desktop":
        return
    pixel_face(f, what)
    f.run("window.__lookBefore = [localStorage.getItem('stackapella.appearance'), document.cookie.split('; ').find((c) => c.startsWith('sa_theme=')) ?? null]")
    for colour, mode in THEMES:
        f.run(f"window.stackapellaTheme.choose({{ colour: '{colour}', mode: '{mode}' }})")
        time.sleep(0.4)
        f.run(settle)
        low = f.chrome.evaluate(APP_CONTRAST)
        assert not low, f"{what} in {colour} {mode}: under AA: {low[:3]}"
    f.run("""const [own, cookie] = window.__lookBefore;
        if (own === null) localStorage.removeItem('stackapella.appearance'); else localStorage.setItem('stackapella.appearance', own);
        document.cookie = (cookie ?? 'sa_theme=') + '; path=/; max-age=' + (cookie ? 31536000 : 0) + '; samesite=lax';
        window.stackapellaTheme.apply();""")
    time.sleep(0.3)
