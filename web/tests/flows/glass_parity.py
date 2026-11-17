"""One gem, two renderers (docs/glass-spec.md 5, test b): on tests/glass-demo.html, js/glass.js's gemBar on a canvas
beside css/glass.css's .gem-note, in each of the eight themes, every solid state (upcoming, next, now with its ring,
settled) by the four parts by three heights (22, 34 and 54 px). Each pair is shot as real pixels and compared with
CIEDE2000: the mean over the pair's box (the gem, its drops, its glow and its ring) is at most 2. The worst pixels are
reported, not held: a canvas and a browser antialias a round corner each their own way. Dashed states are left out:
browsers choose their own dash lengths.

And the gems' contrast on the canvas's real pixels (the spec's test c): every pixel of the band the words sit on is
4.5:1 or better against the words' colour (the gem's ink; on a Settled gem, the theme's text), and the ring's middle is
3:1 or better against the ground under the lane, so the note being sung never relies on colour alone. And a word's
place (inside, above, none) is the same in every state at every height and width, so no word jumps out of its gem as
its note comes, rings, settles or is hit.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

THEMES = [f"{c}-{m}" for c in ["warm", "midnight", "graphite", "stage"] for m in ["dark", "light"]]

MEASURE = r"""(async (png, pairs, cssWidth) => {
    const image = new Image();
    image.src = 'data:image/png;base64,' + png;
    await image.decode();
    const canvas = Object.assign(document.createElement('canvas'), { width: image.width, height: image.height });
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(image, 0, 0);
    const scale = image.width / cssWidth;
    // sRGB to CIELAB (D65), then CIEDE2000.
    const lin = (c) => ((c /= 255) <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    const lab = (r, g, b) => {
        const [R, G, B] = [lin(r), lin(g), lin(b)];
        const f = (t) => (t > 216 / 24389 ? Math.cbrt(t) : (24389 / 27 * t + 16) / 116);
        const x = f((0.4124 * R + 0.3576 * G + 0.1805 * B) / 0.95047), y = f(0.2126 * R + 0.7152 * G + 0.0722 * B), z = f((0.0193 * R + 0.1192 * G + 0.9505 * B) / 1.08883);
        return [116 * y - 16, 500 * (x - y), 200 * (y - z)];
    };
    const rad = Math.PI / 180;
    const de2000 = ([L1, a1, b1], [L2, a2, b2]) => {
        const C1 = Math.hypot(a1, b1), C2 = Math.hypot(a2, b2), Cm = (C1 + C2) / 2;
        const G = 0.5 * (1 - Math.sqrt(Cm ** 7 / (Cm ** 7 + 25 ** 7)));
        const a1p = a1 * (1 + G), a2p = a2 * (1 + G);
        const C1p = Math.hypot(a1p, b1), C2p = Math.hypot(a2p, b2);
        const h = (b, a) => { const v = Math.atan2(b, a) / rad; return v < 0 ? v + 360 : v; };
        const h1p = h(b1, a1p), h2p = h(b2, a2p);
        const dLp = L2 - L1, dCp = C2p - C1p;
        let dhp = C1p * C2p === 0 ? 0 : h2p - h1p;
        if (dhp > 180) dhp -= 360; else if (dhp < -180) dhp += 360;
        const dHp = 2 * Math.sqrt(C1p * C2p) * Math.sin((dhp / 2) * rad);
        const Lm = (L1 + L2) / 2, Cmp = (C1p + C2p) / 2;
        let hm = h1p + h2p;
        if (C1p * C2p !== 0) hm = Math.abs(h1p - h2p) > 180 ? (h1p + h2p + (h1p + h2p < 360 ? 360 : -360)) / 2 : (h1p + h2p) / 2;
        const T = 1 - 0.17 * Math.cos((hm - 30) * rad) + 0.24 * Math.cos(2 * hm * rad) + 0.32 * Math.cos((3 * hm + 6) * rad) - 0.2 * Math.cos((4 * hm - 63) * rad);
        const dTheta = 30 * Math.exp(-(((hm - 275) / 25) ** 2));
        const RC = 2 * Math.sqrt(Cmp ** 7 / (Cmp ** 7 + 25 ** 7));
        const SL = 1 + (0.015 * (Lm - 50) ** 2) / Math.sqrt(20 + (Lm - 50) ** 2), SC = 1 + 0.045 * Cmp, SH = 1 + 0.015 * Cmp * T;
        const RT = -Math.sin(2 * dTheta * rad) * RC;
        return Math.sqrt((dLp / SL) ** 2 + (dCp / SC) ** 2 + (dHp / SH) ** 2 + RT * (dCp / SC) * (dHp / SH));
    };
    const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
    const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
    const probe = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });
    const rgb = (colour) => { probe.clearRect(0, 0, 1, 1); probe.fillStyle = colour; probe.fillRect(0, 0, 1, 1); return [...probe.getImageData(0, 0, 1, 1).data].slice(0, 3); };
    return pairs.map((p) => {
        const w = Math.round(p.w * scale), h = Math.round(p.hgt * scale);
        const band = ctx.getImageData(Math.round((p.a.x + p.band.x) * scale), Math.round((p.a.y + p.band.y) * scale), Math.max(1, Math.round(p.band.w * scale)), Math.max(1, Math.round(p.band.h * scale))).data;
        const ink = rgb(p.ink);
        let words = Infinity;
        for (let i = 0; i < band.length; i += 4) words = Math.min(words, ratio(ink, [band[i], band[i + 1], band[i + 2]]));
        let ring = null;
        if (p.ringAt) {
            const px = ctx.getImageData(Math.round((p.a.x + p.ringAt.x) * scale), Math.round((p.a.y + p.ringAt.y) * scale), 1, 1).data;
            ring = ratio([px[0], px[1], px[2]], rgb(p.surf));
        }
        const A = ctx.getImageData(Math.round(p.a.x * scale), Math.round(p.a.y * scale), w, h).data;
        const B = ctx.getImageData(Math.round(p.b.x * scale), Math.round(p.b.y * scale), w, h).data;
        const d = [];
        for (let i = 0; i < A.length; i += 4) d.push(de2000(lab(A[i], A[i + 1], A[i + 2]), lab(B[i], B[i + 1], B[i + 2])));
        d.sort((x, y) => x - y);
        const mean = d.reduce((s, v) => s + v, 0) / d.length;
        return { state: p.state, part: p.part, h: p.h, mean: Math.round(mean * 100) / 100, p99: Math.round(d[Math.floor(d.length * 0.99)] * 100) / 100, words: Math.round(words * 100) / 100, ring: ring === null ? null : Math.round(ring * 100) / 100 };
    });
})"""


def journey(f):
    problems = []
    worst = (0, "")
    edge = (0, "")
    lowest = (99, "")
    for theme in THEMES:
        f.go(f"/tests/glass-demo.html?theme={theme}", "window.__ready === true")
        pairs = f.js("window.__pairs")
        jumps = f.js("window.__jumps")
        problems += [f"{theme}: '{j['word']}' at h {j['h']}, w {j['w']} moves by state: {j['states']}" for j in jumps[:10]]
        size = f.js("({ w: document.documentElement.scrollWidth, h: document.documentElement.scrollHeight, scale: window.devicePixelRatio || 1 })")
        png = f.chrome.send("Page.captureScreenshot", {"format": "png", "clip": {"x": 0, "y": 0, "width": size["w"], "height": size["h"], "scale": 1}, "captureBeyondViewport": True})["data"]
        for r in f.js(f"{MEASURE}({json.dumps(png)}, {json.dumps(pairs)}, {size['w']})"):
            name = f"{theme} {r['state']} part {r['part']} h {r['h']}"
            if r["mean"] > worst[0]:
                worst = (r["mean"], name)
            if r["p99"] > edge[0]:
                edge = (r["p99"], name)
            if r["words"] < lowest[0]:
                lowest = (r["words"], name)
            if r["words"] < 4.5:
                problems.append(f"{name}: words {r['words']}:1 on the band, under 4.5")
            if r["ring"] is not None and r["ring"] < 3:
                problems.append(f"{name}: the ring {r['ring']}:1 on the ground, under 3")
            if r["mean"] > 2:
                problems.append(f"{name}: mean {r['mean']}, 99th percentile {r['p99']}")
    print(f"     worst mean CIEDE2000: {worst[0]} ({worst[1]}); worst 99th percentile: {edge[0]} ({edge[1]}); lowest words: {lowest[0]}:1 ({lowest[1]})")
    assert not problems, "canvas and CSS gems differ:\n" + "\n".join(problems[:40]) + (f"\n... and {len(problems) - 40} more" if len(problems) > 40 else "")


if __name__ == "__main__":
    run("glass_parity", journey, sizes=("desktop",))
