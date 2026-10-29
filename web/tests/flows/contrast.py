"""Buttons and links stay readable in every theme (docs/decisions.md, "themes"; Hillel's bug: Continue
vanished on hover in light). In each of the ten palettes with each accent (the palette's own and the
seven to choose, tool/accents.py), on the home (the course band, Continue)
and the styleguide (every button and form part), every visible button and link is measured at rest
and as hover, active and focus would draw it: its words against what's behind them, 4.5:1 for normal
text, 3:1 for large or bold-large. The :hover, :active and :focus-visible rules are copied onto
classes the script adds, so the states are measured without a pointer.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

THEMES = [f"{c}-{m}" for c in ["warm", "midnight", "graphite", "stage"] for m in ["dark", "light"]]
ACCENTS = [None, "coral", "amber", "green", "teal", "sky", "violet", "rose"]  # tool/accents.py; None is the palette's own

MEASURE = r"""(() => {
    // The state rules, again on classes: .st-hover for :hover, and so on (nested @media included).
    if (!document.getElementById('st-states')) {
        const out = [];
        const walk = (rules) => {
            for (const rule of rules) {
                if (rule.cssRules && !rule.selectorText) walk(rule.cssRules);
                else if (rule.selectorText && /:(hover|active|focus-visible)/.test(rule.selectorText)) {
                    out.push(rule.cssText.replace(/:hover/g, '.st-hover').replace(/:active/g, '.st-active').replace(/:focus-visible/g, '.st-focus'));
                }
            }
        };
        for (const sheet of document.styleSheets) {
            try { walk(sheet.cssRules); } catch (error) { console.debug('A sheet not readable', error); }
        }
        const style = document.createElement('style');
        style.id = 'st-states';
        // Colours are read as they end, not partway through a transition.
        style.textContent = '*, *::before, *::after { transition: none !important; animation: none !important; }\n' + out.join('\n');
        document.head.append(style);
    }
    const ctx = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });
    const rgba = (colour) => {
        ctx.clearRect(0, 0, 1, 1);
        ctx.fillStyle = '#000';
        ctx.fillStyle = colour;
        ctx.fillRect(0, 0, 1, 1);
        const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
        return [r, g, b, a / 255];
    };
    const over = (top, under) => top.slice(0, 3).map((c, i) => c * top[3] + under[i] * (1 - top[3])).concat(1);
    const behind = (el) => {
        const layers = [];
        for (let node = el; node && node.nodeType === 1; node = node.parentElement) {
            const style = getComputedStyle(node);
            const bg = rgba(style.backgroundColor);
            const opacity = Number(style.opacity);
            if (bg[3] > 0) layers.push([...bg.slice(0, 3), bg[3] * opacity]);
            if (bg[3] * opacity >= 0.999) break;
        }
        let colour = rgba(getComputedStyle(document.body).backgroundColor);
        for (const layer of layers.reverse()) colour = over(layer, colour);
        return colour;
    };
    const lum = ([r, g, b]) => {
        const f = (c) => ((c /= 255) <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
    };
    const ratio = (a, b) => {
        const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
        return (x + 0.05) / (y + 0.05);
    };
    const visible = (el) => el.offsetWidth > 0 && el.offsetHeight > 0 && getComputedStyle(el).visibility !== 'hidden';
    const failures = [];
    const elements = [...document.querySelectorAll('button, a[href], [role=button]')].filter((el) => visible(el) && !el.disabled && el.innerText.trim() && !el.closest('[aria-hidden=true]'));
    for (const el of elements) {
        for (const state of ['', 'st-hover', 'st-active', 'st-focus']) {
            if (state) el.classList.add(state);
            const style = getComputedStyle(el);
            const size = parseFloat(style.fontSize);
            const bold = Number(style.fontWeight) >= 700;
            const need = size >= 24 || (bold && size >= 18.66) ? 3 : 4.5;
            const back = behind(el);
            const text = over(rgba(style.color), back);
            const got = ratio(text, back);
            if (state) el.classList.remove(state);
            if (got < need - 0.05) failures.push(`${(state || 'rest').replace('st-', '')}: "${el.innerText.trim().slice(0, 40)}" ${got.toFixed(2)} < ${need}`);
        }
    }
    return [...new Set(failures)];
})()"""


def journey(f):
    problems = []
    for path, ready in [("/?api=mock&as=member", "document.querySelector('#app header')"), ("/styleguide?api=mock&as=admin", "document.querySelector('main h1, h1')")]:
        f.go(path, ready)
        f.wait("!document.querySelector('[role=status][aria-busy=true]')", what="settled")
        for theme in THEMES:
            colour, mode = theme.split("-")
            for accent in ACCENTS:
                # In the flow's own skin (FLOW_SKINS): game mode has its own light or dark (gameMode).
                choice = {"mode": mode, "gameMode": mode, "colour": colour, "accent": accent, "skin": getattr(f, "skin", "game")}
                f.run(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps(choice))}); window.stackapellaTheme.apply();")
                f.wait(f"document.documentElement.dataset.theme === '{theme}' && (document.documentElement.dataset.accent ?? null) === {json.dumps(accent)}", what=f"{theme} {accent}")
                for failure in f.js(MEASURE):
                    problems.append(f"{theme} {accent or 'own'} {path.split('?')[0]} {failure}")
    f.run("localStorage.removeItem('stackapella.appearance'); window.stackapellaTheme.apply();")
    assert not problems, "too little contrast:\n" + "\n".join(problems[:60]) + (f"\n... and {len(problems) - 60} more" if len(problems) > 60 else "")


if __name__ == "__main__":
    run("contrast", journey)
