"""Sung words are readable in full where someone sings (docs/design-rules.md, docs/studio-words-plan.md):
an element's words on screen, not cut by width or line clamp, at 16 px or more. Shared by the recording flows."""
import json

READABLE = """((selector) => {
    const els = [...document.querySelectorAll(selector)];
    if (!els.length) return 'missing';
    for (const el of els) {
        const box = el.getBoundingClientRect();
        const style = getComputedStyle(el);
        if (box.width < 1 || box.height < 1 || style.visibility === 'hidden') return 'hidden: ' + el.outerHTML.slice(0, 80);
        if (box.top < 0 || box.bottom > innerHeight || box.left < 0 || box.right > innerWidth) return 'off screen: ' + JSON.stringify(box);
        // A hidden line is 16 px or more; a font's glyphs can overhang its line box by a pixel or two.
        if (el.scrollWidth > el.clientWidth + 1 || el.scrollHeight > el.clientHeight + 4) return 'cut (' + el.scrollHeight + ' > ' + el.clientHeight + '): ' + el.textContent;
        if (parseFloat(style.fontSize) < 16) return 'small (' + style.fontSize + '): ' + el.textContent;
        if (!el.textContent.trim()) return 'empty';
    }
    return 'ok';
})"""


def words_readable(f, selector, what):
    found = f.js(f"{READABLE}({json.dumps(selector)})")
    assert found == "ok", f"{what}: {found}"


def text_of(f, selector):
    return f.js(f"[...document.querySelectorAll({json.dumps(selector)})].map((e) => e.textContent.trim()).join(' / ')")


THEMES = [f"{c}-{m}" for c in ["warm", "midnight", "graphite", "stage"] for m in ["dark", "light"]]

CONTRAST = r"""((selector) => {
    const ctx = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });
    const rgba = (colour) => {
        ctx.clearRect(0, 0, 1, 1); ctx.fillStyle = '#000'; ctx.fillStyle = colour; ctx.fillRect(0, 0, 1, 1);
        const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
        return [r, g, b, a / 255];
    };
    const over = (top, under) => top.slice(0, 3).map((c, i) => c * top[3] + under[i] * (1 - top[3])).concat(1);
    const behind = (el) => {
        const layers = [];
        for (let node = el; node && node.nodeType === 1; node = node.parentElement) {
            const style = getComputedStyle(node);
            const bg = rgba(style.backgroundColor);
            if (bg[3] > 0) layers.push([...bg.slice(0, 3), bg[3] * Number(style.opacity)]);
            if (bg[3] * Number(style.opacity) >= 0.999) break;
        }
        let colour = rgba(getComputedStyle(document.body).backgroundColor);
        for (const layer of layers.reverse()) colour = over(layer, colour);
        return colour;
    };
    const lum = ([r, g, b]) => {
        const f = (c) => ((c /= 255) <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
    };
    const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
    const out = [];
    for (const el of document.querySelectorAll(selector)) {
        if (!el.textContent.trim()) continue;
        const style = getComputedStyle(el);
        const size = parseFloat(style.fontSize);
        const need = size >= 24 || (Number(style.fontWeight) >= 700 && size >= 18.66) ? 3 : 4.5;
        const back = behind(el);
        const got = ratio(over(rgba(style.color), back), back);
        if (got < need - 0.05) out.push(`"${el.textContent.trim().slice(0, 30)}" ${got.toFixed(2)} < ${need}`);
    }
    return out;
})"""


def words_contrast(f, selector, what):
    """The words at AA against what's behind them, in all eight themes (the flow's own skin)."""
    problems = []
    # The flow's own look comes back afterwards (a serious flow stays serious; removing the choice made it game).
    f.run("window.__wordsLookBefore = localStorage.getItem('stackapella.appearance')")
    f.run("document.head.append(Object.assign(document.createElement('style'), { id: 'words-still', textContent: '*, *::before, *::after { transition: none !important; }' }))")
    for theme in THEMES:
        colour, mode = theme.split("-")
        choice = {"mode": mode, "gameMode": mode, "colour": colour, "accent": None, "skin": getattr(f, "skin", "game")}
        f.run(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps(choice))}); window.stackapellaTheme.apply();")
        f.wait(f"document.documentElement.dataset.theme === '{theme}'", what=theme)
        problems += [f"{theme}: {p}" for p in f.js(f"{CONTRAST}({json.dumps(selector)})")]
    f.run("const own = window.__wordsLookBefore; if (own === null) localStorage.removeItem('stackapella.appearance'); else localStorage.setItem('stackapella.appearance', own); window.stackapellaTheme.apply(); document.getElementById('words-still')?.remove();")
    assert not problems, f"{what}, too little contrast:\n" + "\n".join(problems)
