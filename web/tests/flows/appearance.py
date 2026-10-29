"""The theme picker (docs/decisions.md, "themes", and "the theme picker, refined"): dark and Stage until a
choice; the home's Theme button opens one panel with Light, Dark and Auto, the five named colours and
the accents; choosing
shows at once and keeps the panel open (to compare); arrow keys move through a group; Escape and a
click outside close it; Settings has the same panel inline; the choice survives a reload (set before
the page paints). Signed out.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key):
    return f.js(f"{I18N}.t({json.dumps(key)})")


def theme(f):
    return f.js("document.documentElement.dataset.theme")


PANEL = "document.querySelector('#app header [role=dialog], [role=dialog]')"


def radio(f, label, scope="[role=dialog]"):
    f.click(label, f"{scope} [role=radio]")


def theme_card(f, family, scope="[role=dialog]"):
    """A theme card by its family (its words now carry a description too, and Sky is also an accent)."""
    f.run(f"document.querySelector('{scope} [role=radio][data-value={family}]').click()")


def journey(f):
    f.go("/?api=mock&as=signedOut", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.appearance'); window.stackapellaTheme.apply();")
    assert theme(f) == "stage-dark", f"dark and Stage until a choice: {theme(f)}"
    f.wait("document.querySelector('#app header button[aria-haspopup=dialog]')", what="the Theme button")
    f.wait("document.querySelector('#app header button[aria-haspopup=dialog]')", what="the Theme button"); f.run("document.querySelector('#app header button[aria-haspopup=dialog]').click()")
    f.wait(PANEL, what="the panel")
    radio(f, t(f, "appearance.mode.light"))
    f.wait("document.documentElement.dataset.theme === 'warm-light'", what="Warm light, light's own colour")
    assert f.js(f"!!{PANEL}"), "a choice keeps the panel open"
    # Named for the mode: Midnight in light is "Sky".
    theme_card(f, "midnight")
    f.wait("document.documentElement.dataset.theme === 'midnight-light'", what="Sky (Midnight light)")
    assert f.js(f"!!{PANEL}"), "still open, to compare"
    assert t(f, "appearance.colour.midnight.dark") not in f.js(PANEL + ".innerText"), "a dark name shown in light"
    f.shot("panel-sky-light")
    # Arrow keys: the colour group moves on to the next and chooses it.
    f.run("const g = document.querySelectorAll('[role=dialog] [role=radiogroup]')[1]; g.querySelector('[aria-checked=true]').focus(); g.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));")
    f.wait("document.documentElement.dataset.theme === 'graphite-light'", what="the next colour by arrow key")
    # Someone who had chosen Plum (gone) sees the mode's own colour.
    f.run("window.stackapellaTheme.choose({ mode: 'light', colour: 'plum' });")
    assert theme(f) == "warm-light", f"plum light shows as Warm: {theme(f)}"
    f.run("window.stackapellaTheme.choose({ mode: 'dark', colour: 'plum' });")
    assert theme(f) == "stage-dark", f"plum dark shows as Stage: {theme(f)}"
    f.run("window.stackapellaTheme.choose({ mode: 'light', colour: 'graphite' });")
    theme_card(f, "stage")  # back to the panel's own choosing: Stage, in light
    f.wait("document.documentElement.dataset.theme === 'stage-light'", what="Spotlight (Stage light)")
    # Auto: the headless browser's scheme.
    scheme = f.js("matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'")
    radio(f, t(f, "appearance.mode.auto"))
    # (Kept as the light or dark of the skin showing: game mode has its own.)
    f.wait(f"(c => (c.skin === 'serious' ? c.mode : c.gameMode))(JSON.parse(localStorage.getItem('stackapella.appearance'))) === 'system' && document.documentElement.dataset.theme === 'stage-{scheme}'", what="matching the device: " + str(f.js("localStorage.getItem('stackapella.appearance') + ' ' + document.documentElement.dataset.theme")))
    radio(f, t(f, "appearance.mode.dark"))
    f.wait("document.documentElement.dataset.theme === 'stage-dark'", what="Stage dark")
    f.shot("panel-stage-dark")
    # An accent: links and highlights take it, and the panel stays open.
    radio(f, t(f, "appearance.accent.coral"))
    f.wait("document.documentElement.dataset.accent === 'coral'", what="the coral accent")
    accent = f.js("getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim()")
    assert accent == f.js("(await import('/js/accent-colours.js')).ACCENT_COLOURS['stage-dark'].coral"), accent
    assert f.js(f"!!{PANEL}"), "still open"
    f.shot("panel-coral")
    # Your own colour: a dark navy that can't read on Stage dark is fitted (lighter), with a note
    # and a preview in both modes; it's set before the page paints, on any page.
    f.run("const c = document.querySelector('[role=dialog] input[type=color], #app input[type=color]'); c.value = '#1a2a6c'; c.dispatchEvent(new Event('input')); c.dispatchEvent(new Event('change'));")
    f.wait("document.documentElement.dataset.accent === 'custom'", what="the custom accent")
    fitted = f.js("getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim()")
    assert fitted and fitted != "#1a2a6c", f"not fitted: {fitted}"
    ratio = f.js("""(() => { const lin = (c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4); const long = (h) => (h.length === 4 ? '#' + [...h.slice(1)].map((c) => c + c).join('') : h); const lum = (x) => { const h = long(x); return [1, 3, 5].map((i) => lin(parseInt(h.slice(i, i + 2), 16) / 255)).reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0); }; const a = lum(getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim()); const b = lum(getComputedStyle(document.documentElement).getPropertyValue('--color-ink').trim()); return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05); })()""")
    assert ratio >= 4.5, f"the custom accent reads at {ratio:.2f}:1"
    f.wait_text(t(f, "appearance.accent.madeLighter"))
    f.wait("document.querySelector('[data-custom-preview]')", what="the preview in both modes")
    f.shot("panel-custom")
    f.go("/settings", "document.querySelector('main, #app h1')")
    assert f.js("document.documentElement.dataset.accent") == "custom", "the custom accent didn't come back on another page"
    f.go("/", "document.querySelector('#app header')")
    f.wait("document.querySelector('#app header button[aria-haspopup=dialog]')", what="the Theme button"); f.run("document.querySelector('#app header button[aria-haspopup=dialog]').click()")
    f.wait(PANEL, what="the panel again")
    radio(f, t(f, "appearance.accent.own"))
    f.wait("!document.documentElement.dataset.accent", what="the theme's own accent again")
    assert not f.js("document.documentElement.style.getPropertyValue('--color-accent')"), "the custom colour stayed on the page"
    # Escape closes it (on a phone it's a sheet, which Escape closes too).
    f.run("document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))")
    f.wait(f"!{PANEL}", what="Escape closes it")
    if f.size == "desktop":
        f.wait("document.querySelector('#app header button[aria-haspopup=dialog]')", what="the Theme button"); f.run("document.querySelector('#app header button[aria-haspopup=dialog]').click()")
        f.wait(PANEL, what="open again")
        f.run("document.querySelector('main, #app').dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))")
        f.wait(f"!{PANEL}", what="a click outside closes it")
    # Settings has no look section of its own: the same Theme button sits in its header.
    f.go("/settings", "document.querySelector('main, #app h1')")
    assert f.js("!document.querySelector('#look')"), "a look section in Settings"
    f.wait("document.querySelector('#app button[aria-haspopup=dialog]')", what="the Theme button on Settings")
    f.run("document.querySelector('#app button[aria-haspopup=dialog]').click()")
    f.wait(PANEL, what="the panel from Settings")
    theme_card(f, "midnight")
    f.wait("document.documentElement.dataset.theme === 'midnight-dark'", what="Midnight dark from Settings")
    f.shot("settings")
    # Kept: the page opens in it before it paints.
    f.go("/", "document.querySelector('#app header')")
    assert theme(f) == "midnight-dark", theme(f)
    f.run("localStorage.removeItem('stackapella.appearance'); window.stackapellaTheme.apply();")


if __name__ == "__main__":
    run("appearance", journey)
