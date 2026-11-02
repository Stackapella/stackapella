// The course-page demos' helpers (js/public/voices.js, the arranger's section): each ornament's pitch
// path has the shape the app's style scorer listens for (js/style-scores.js), and the mode tune differs
// from Dorian to Aeolian only on its sixth.
import { hz, modeTune, ornamentPath, styledLine } from '/js/public/voices.js';
import { CRY_CENTS, CRY_SECONDS, FALL_BY, SCOOP_FROM, SCOOP_WITHIN, SLIDE_SECONDS } from '/js/style-scores.js';
import { assert, test } from './harness.js';

const cents = (f, midi) => 1200 * Math.log2(f / hz(midi));

test('demo ornaments: a scoop, a fall-off, a slide and a cry each in the shape the lessons judge', () => {
    const midi = 64;
    const scoop = ornamentPath('scoop', { midi });
    const from = -cents(scoop[0].f, midi);
    assert(from >= SCOOP_FROM[0] && from <= SCOOP_FROM[1] && scoop[1].at + scoop[1].slide <= SCOOP_WITHIN && Math.abs(cents(scoop[1].f, midi)) < 1, `scoop from ${from} cents, in ${scoop[1].at + scoop[1].slide} s`);
    const fall = ornamentPath('fall', { midi, length: 1.6 });
    assert(-cents(fall[1].f, midi) >= FALL_BY && 1.6 - fall[1].at <= 0.4, `fall of ${-cents(fall[1].f, midi)} cents, ${1.6 - fall[1].at} s before the end`);
    const slide = ornamentPath('slide', { midi, to: 67, length: 1.6 });
    assert(slide[1].slide >= SLIDE_SECONDS[0] && slide[1].slide <= SLIDE_SECONDS[1] && Math.abs(cents(slide[1].f, 67)) < 1, 'a slide to G');
    const cry = ornamentPath('cry', { midi });
    const up = cents(cry[1].f, midi);
    const held = cry[2].at - cry[1].at;
    assert(up >= CRY_CENTS[0] && up <= CRY_CENTS[1] && held >= CRY_SECONDS[0] && held <= CRY_SECONDS[1] && cry[1].head && Math.abs(cents(cry[2].f, midi)) < 1, `cry ${up} cents for ${held} s, back down`);
    assert(ornamentPath('plain', { midi }).length === 1, 'plain is the note alone');
});

test('demo modes: Dorian and Aeolian differ only on the sixth', () => {
    const dorian = modeTune(62, 9);
    const aeolian = modeTune(62, 8);
    const differ = dorian.map((n, i) => Math.round(cents(n.f, 62) - cents(aeolian[i].f, 62))).map((d, i) => (d ? i : null)).filter((i) => i !== null);
    assert(differ.length === 2 && differ.every((i) => Math.round(cents(dorian[i].f, 62)) === 900 && Math.round(cents(aeolian[i].f, 62)) === 800), `differ at ${differ}`);
});

test('demo styled line: plain notes on their beats, and each move where it was asked for', () => {
    const notes = [{ beat: 0, length: 1, step: 0 }, { beat: 1, length: 3, step: 4, style: 'scoop' }, { beat: 4, length: 1, step: 2, style: 'slide' }, { beat: 5, length: 3, step: 0, style: 'fall' }];
    const plain = styledLine(notes.map(({ style, ...n }) => n), { midi: 60, beat: 0.5 });
    assert(plain.length === 4 && plain.every((p, i) => p.at === notes[i].beat * 0.5), 'one point a note, on its beat');
    const line = styledLine(notes, { midi: 60, beat: 0.5 });
    assert(line.every((p, i) => !i || p.at >= line[i - 1].at), 'in time order');
    const scoop = line.filter((p) => p.at >= 0.5 && p.at < 2);
    assert(-cents(scoop[0].f, 64) >= SCOOP_FROM[0] && Math.abs(cents(scoop[1].f, 64)) < 1, 'the scoop starts under "val-" and arrives');
    const into = line.find((p) => p.slide && Math.abs(cents(p.f, 60)) < 1);
    assert(into && into.at < 2.5 && into.slide >= SLIDE_SECONDS[0] && into.slide <= SLIDE_SECONDS[1], 'the slide glides into the next note across its beat');
    const last = line[line.length - 1];
    assert(-cents(last.f, 60) >= FALL_BY && 4 - last.at <= 0.4, 'the fall-off drops at the end of the last note');
});
