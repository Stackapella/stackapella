"""Serious mode's bubbles keep their icons readable (docs/bubble-circles-plan.md, 4e): on the styleguide's specimens, in
each of the eight palettes with each accent, the bubble is shot with its icon hidden, and every pixel of the box the
icon sits in (28% to 72% each way) is measured against the icon's own colour: 4.5:1 or better (the bubble's light and
crescent sit above that box, so the icon keeps the flat fill's contrast). Real pixels, not the computed colours: the
gradient, the crescent and the rim are what the eye sees. A disabled (locked) bubble is left out, as WCAG does.
"""
import base64
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

THEMES = [f"{c}-{m}" for c in ["warm", "midnight", "graphite", "stage"] for m in ["dark", "light"]]
ACCENTS = [None, "coral", "amber", "green", "teal", "sky", "violet", "rose"]  # tool/accents.py; None is the palette's own

SPECIMENS = r"""(() => {
    const ctx = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });
    const rgb = (colour) => { ctx.clearRect(0, 0, 1, 1); ctx.fillStyle = colour; ctx.fillRect(0, 0, 1, 1); return [...ctx.getImageData(0, 0, 1, 1).data].slice(0, 3); };
    const section = document.querySelector('[data-bubbles] .card');
    section.scrollIntoView({ block: 'center' });
    const box = section.getBoundingClientRect();
    const specimens = [...section.querySelectorAll('[data-bubble-specimen]')].filter((el) => !el.disabled).map((el) => {
        const r = el.getBoundingClientRect();
        return { name: el.title || el.textContent.trim() || 'bubble', ink: rgb(getComputedStyle(el).color), x: r.left - box.left, y: r.top - box.top, w: r.width, h: r.height };
    });
    if (!document.getElementById('bubble-hide')) {
        const style = document.createElement('style');
        style.id = 'bubble-hide';
        style.textContent = '[data-bubble-specimen] { color: transparent !important; } [data-bubble-specimen] > * { visibility: hidden !important; } *, *::before, *::after { transition: none !important; animation: none !important; }';
        document.head.append(style);
    }
    return { clip: { x: box.left + scrollX, y: box.top + scrollY, width: box.width, height: box.height }, specimens };
})()"""

MEASURE = r"""(async (png, specimens) => {
    const image = new Image();
    image.src = 'data:image/png;base64,' + png;
    await image.decode();
    const canvas = Object.assign(document.createElement('canvas'), { width: image.width, height: image.height });
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(image, 0, 0);
    const scale = image.width / document.querySelector('[data-bubbles] .card').getBoundingClientRect().width;
    const lum = ([r, g, b]) => {
        const f = (c) => ((c /= 255) <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
    };
    const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
    return specimens.map((s) => {
        const x0 = Math.round((s.x + s.w * 0.28) * scale), y0 = Math.round((s.y + s.h * 0.28) * scale);
        const w = Math.max(1, Math.round(s.w * 0.44 * scale)), h = Math.max(1, Math.round(s.h * 0.44 * scale));
        const data = ctx.getImageData(x0, y0, w, h).data;
        let worst = Infinity;
        for (let i = 0; i < data.length; i += 4) worst = Math.min(worst, ratio(s.ink, [data[i], data[i + 1], data[i + 2]]));
        return { name: s.name, worst: Math.round(worst * 100) / 100 };
    });
})"""


def journey(f):
    problems = []
    lowest = (99, "")
    f.go("/styleguide?api=mock&as=admin", "document.querySelector('[data-bubbles]')")
    for theme in THEMES:
        colour, mode = theme.split("-")
        for accent in ACCENTS:
            choice = {"mode": mode, "gameMode": mode, "colour": colour, "accent": accent, "skin": "serious"}
            f.run("document.getElementById('bubble-hide')?.remove()")
            f.run(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps(choice))}); window.stackapellaTheme.apply();")
            f.wait(f"document.documentElement.dataset.theme === '{theme}' && (document.documentElement.dataset.accent ?? null) === {json.dumps(accent)}", what=f"{theme} {accent}")
            found = f.js(SPECIMENS)
            png = f.chrome.send("Page.captureScreenshot", {"format": "png", "clip": {**found["clip"], "scale": 1}, "captureBeyondViewport": True})["data"]
            for result in f.js(f"{MEASURE}({json.dumps(png)}, {json.dumps(found['specimens'])})"):
                if result["worst"] < lowest[0]:
                    lowest = (result["worst"], f"{theme} {accent or 'own'} {result['name']}")
                if result["worst"] < 4.45:
                    problems.append(f"{theme} {accent or 'own'}: {result['name']} {result['worst']} < 4.5")
    f.run("document.getElementById('bubble-hide')?.remove(); localStorage.removeItem('stackapella.appearance'); window.stackapellaTheme.apply();")
    print(f"     lowest: {lowest[0]} ({lowest[1]})")
    assert not problems, "a bubble's icon too faint:\n" + "\n".join(problems[:60]) + (f"\n... and {len(problems) - 60} more" if len(problems) > 60 else "")


if __name__ == "__main__":
    run("bubble_contrast", journey, sizes=("desktop",))
