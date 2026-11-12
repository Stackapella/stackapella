// Game mode's faces speak every language the app is written in (before Spanish is switched on, 2026-11-12): each face
// loaded as css/input.css declares it (its files and unicode-ranges), every Spanish, German, French and Portuguese
// letter and mark drawn by the face itself, never by a fallback, and the pixel face's accented capitals drawn at full
// height with their mark above (tool/pixel_font.py), never squeezed into a lowercase's size.
import { assert, test } from './harness.js';

const FACES = ['Stackapella Pixel', 'VT323', 'Bungee'];
const LETTERS = 'ÁÉÍÓÚÑÜáéíóúñü¿¡«»ÄÖäößẞÀÂÇÈÊËÎÏÔÙÛàâçèêëîïôùûœŒÃÕãõ„“”‘’€–…·º';
const CAPITALS = 'ÀÁÂÃÄÅÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ';

let loaded;
function loadFaces() {
    loaded ??= (async () => {
        const css = await (await fetch('/css/input.css')).text();
        const faces = [];
        for (const [, block] of css.matchAll(/@font-face\s*{([^}]*)}/g)) {
            const family = block.match(/font-family:\s*'([^']+)'/)?.[1];
            if (!FACES.includes(family)) continue;
            const src = block.match(/url\('([^']+)'\)/)[1];
            const range = block.match(/unicode-range:\s*([^;]+);/)?.[1];
            const face = new FontFace(`Test ${family}`, `url(${src})`, range ? { unicodeRange: range } : {});
            faces.push(face);
            document.fonts.add(face);
        }
        await Promise.all(faces.map((face) => face.load()));
        return faces;
    })();
    return loaded;
}

const context = document.createElement('canvas').getContext('2d');
function measure(text, family, fallback) {
    context.font = `40px "Test ${family}", ${fallback}`;
    return context.measureText(text);
}

test('game fonts: each face draws every Spanish, German, French and Portuguese letter itself, none from a fallback', async () => {
    const faces = await loadFaces();
    assert(FACES.every((family) => faces.some((face) => face.family.replace(/"/g, '') === `Test ${family}`)), `input.css declares ${faces.map((face) => face.family).join(', ')}`);
    const fallen = [];
    for (const family of FACES) {
        // A letter the face has measures the same whatever comes after it in the stack; one it lacks takes the fallback's
        // width. The fallbacks are the other two game faces, loaded and of very different widths: a generic family can lack
        // the letter too and hand both to the same system face (ẞ did).
        const others = FACES.filter((other) => other !== family).map((other) => `"Test ${other}", monospace`);
        const missing = [...LETTERS].filter((ch) => new Set(others.map((fallback) => measure(ch, family, fallback).width)).size > 1);
        if (missing.length) fallen.push(`${family}: ${missing.join('')}`);
    }
    assert(!fallen.length, `drawn by a fallback: ${fallen.join('; ')}`);
});

test('game fonts: the pixel face draws an accented capital at full height with its mark above, not as its lowercase', async () => {
    await loadFaces();
    const plain = measure('O', 'Stackapella Pixel', 'monospace');
    const squeezed = [...CAPITALS].filter((ch) => {
        const m = measure(ch, 'Stackapella Pixel', 'monospace');
        // The capital sits on the line as O does and reaches above it by its mark (O's top is 1 em; a one-row mark, 1.125,
        // a two-row one, 1.25: at 40 px, 5 or 10 px above O).
        return Math.abs(m.actualBoundingBoxDescent - plain.actualBoundingBoxDescent) > 1 || m.actualBoundingBoxAscent < plain.actualBoundingBoxAscent + 4;
    });
    assert(!squeezed.length, `squeezed into the cell: ${squeezed.join('')}`);
    const lower = measure('ó', 'Stackapella Pixel', 'monospace');
    assert(lower.actualBoundingBoxAscent <= plain.actualBoundingBoxAscent + 1, 'a lowercase keeps its mark inside the cell');
});
