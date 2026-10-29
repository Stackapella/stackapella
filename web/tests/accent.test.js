// A custom accent (js/theme-boot.js's fitAccent): fitted to each theme exactly as tool/accents.py
// fits it (tests/vectors/accent.json, which the apps check too), always readable.
import { assert, test } from './harness.js';

async function boot() {
    if (window.stackapellaTheme?.fitAccent) return window.stackapellaTheme;
    await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = '/js/theme-boot.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.append(script);
    });
    return window.stackapellaTheme;
}

const channels = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
const near = (a, b) => channels(a).every((v, i) => Math.abs(v - channels(b)[i]) <= 1);

test('accent: a custom colour is fitted to every theme as the vectors say, the same as accents.py', async () => {
    const { fitAccent } = await boot();
    const vectors = await (await fetch('/tests/vectors/accent.json')).json();
    assert(vectors.length >= 100, `${vectors.length} vectors`);
    const wrong = vectors.filter((v) => {
        const got = fitAccent(v.colour, v.theme);
        return !got || !near(got.accent, v.accent) || !near(got.strong, v.strong) || !near(got.ink, v.ink) || got.adjusted !== v.adjusted;
    });
    assert(!wrong.length, `${wrong.length} differ, e.g. ${JSON.stringify(wrong[0])} got ${JSON.stringify(wrong[0] && fitAccent(wrong[0].colour, wrong[0].theme))}`);
    assert(fitAccent('nonsense', 'stage-dark') === null && fitAccent('#ff0000', 'nope') === null, 'refuses what it cannot fit');
});
